import Foundation

struct ChatGPTEventStream {
    private var dataLines: [String] = []
    private var eventName = ""
    private(set) var text = ""
    private(set) var completed = false
    private var pendingSize = 0

    mutating func consume(_ line: String) throws {
        if line.isEmpty {
            defer { dataLines = []; eventName = ""; pendingSize = 0 }
            guard !dataLines.isEmpty else { return }
            let data = dataLines.joined(separator: "\n")
            if data == "[DONE]" { return }
            guard let root = try JSONSerialization.jsonObject(with: Data(data.utf8)) as? [String: Any] else {
                throw ScenarioError.invalid("Le flux ChatGPT est invalide.")
            }
            let kind = root["type"] as? String ?? eventName
            switch kind {
            case "response.output_text.delta":
                text += root["delta"] as? String ?? ""
                guard text.utf8.count <= 20_000_000 else { throw ScenarioError.invalid("La proposition ChatGPT est trop volumineuse.") }
            case "response.completed":
                if text.isEmpty, let response = root["response"] as? [String: Any],
                   let output = response["output"] as? [[String: Any]] {
                    text = output.flatMap { $0["content"] as? [[String: Any]] ?? [] }
                        .filter { $0["type"] as? String == "output_text" }
                        .compactMap { $0["text"] as? String }.joined()
                }
                completed = true
            case "response.failed", "error":
                let error = ((root["response"] as? [String: Any])?["error"] as? [String: Any])
                    ?? (root["error"] as? [String: Any]) ?? root
                throw OAuthServiceError(status: 200, code: error["code"] as? String)
            case "response.incomplete":
                throw ScenarioError.invalid("ChatGPT a interrompu la proposition. Réessayez avec une demande plus courte.")
            default: break
            }
        } else if line.hasPrefix("data:") {
            let value = String(line.dropFirst(5)).removingPrefixSpace
            pendingSize += value.utf8.count
            guard pendingSize <= 20_000_000 else { throw ScenarioError.invalid("Le flux ChatGPT est trop volumineux.") }
            dataLines.append(value)
        } else if line.hasPrefix("event:") {
            eventName = String(line.dropFirst(6)).trimmingCharacters(in: .whitespaces)
        }
    }

    func result() throws -> String {
        guard completed, !text.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty else {
            throw ScenarioError.invalid("La réponse ChatGPT est incomplète. Aucune proposition n’a été appliquée.")
        }
        return text
    }
}

private extension String {
    var removingPrefixSpace: String { hasPrefix(" ") ? String(dropFirst()) : self }
}

@MainActor
enum ChatGPTAPI {
    static func models(token: String, sessionConfiguration: URLSessionConfiguration = .ephemeral) async throws -> [AIModel] {
        var request = URLRequest(url: URL(string: ChatGPTProtocol.resource + "/models")!)
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        let data = try await ChatGPTOAuthClient(sessionConfiguration: sessionConfiguration).data(request)
        guard let root = try JSONSerialization.jsonObject(with: data) as? [String: Any],
              let entries = root["models"] as? [[String: Any]] else {
            throw ScenarioError.invalid("ChatGPT n’a pas renvoyé les modèles disponibles pour ce compte.")
        }
        var seen = Set<String>()
        return entries.compactMap { entry in
            guard entry["visibility"] as? String == "list", let slug = entry["slug"] as? String,
                  !slug.isEmpty, seen.insert(slug).inserted else { return nil }
            return AIModel(id: slug, name: entry["display_name"] as? String)
        }
    }

    static func response(model: String, system: String, prompt: String, token: String,
                         sessionConfiguration: URLSessionConfiguration = .ephemeral) async throws -> String {
        var request = URLRequest(url: URL(string: ChatGPTProtocol.resource + "/responses")!)
        request.httpMethod = "POST"; request.timeoutInterval = 180
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("text/event-stream", forHTTPHeaderField: "Accept")
        request.httpBody = try JSONSerialization.data(withJSONObject: [
            "model": model, "instructions": system, "input": [["role": "user", "content": prompt]],
            "store": false, "stream": true
        ])
        let session = URLSession(configuration: sessionConfiguration, delegate: NoRedirect(), delegateQueue: nil)
        defer { session.invalidateAndCancel() }
        let (bytes, response) = try await session.bytes(for: request)
        guard let http = response as? HTTPURLResponse else { throw ScenarioError.invalid("Réponse ChatGPT invalide.") }
        guard (200..<300).contains(http.statusCode) else {
            var data = Data()
            for try await byte in bytes {
                data.append(byte)
                if data.count >= 16_384 { break }
            }
            let root = (try? JSONSerialization.jsonObject(with: data)) as? [String: Any]
            let code = (root?["error"] as? [String: Any])?["code"] as? String
            throw OAuthServiceError(status: http.statusCode, code: code)
        }
        // Some gateways return JSON, or omit/mislabel the SSE content type.
        // Inspect that body before deciding whether inference failed.
        if http.value(forHTTPHeaderField: "Content-Type")?.lowercased().contains("text/event-stream") != true {
            var data = Data()
            for try await byte in bytes {
                data.append(byte)
                if data.count % 4096 == 0 { try Task.checkCancellation() }
                guard data.count <= 20_000_000 else { throw ScenarioError.invalid("La réponse ChatGPT est trop volumineuse.") }
            }
            try Task.checkCancellation()
            return try nonStreamingResult(data, response: http)
        }
        var stream = ChatGPTEventStream()
        var line = Data()
        // AsyncBytes.lines skips blank lines on some macOS versions. SSE needs
        // those delimiters to distinguish complete events from partial data.
        for try await byte in bytes {
            if byte == 10 {
                if line.last == 13 { line.removeLast() }
                guard let text = String(data: line, encoding: .utf8) else {
                    throw ScenarioError.invalid("Le flux ChatGPT n’est pas encodé correctement.")
                }
                try Task.checkCancellation()
                try stream.consume(text)
                line.removeAll(keepingCapacity: true)
                if stream.completed { break }
            } else {
                line.append(byte)
                if line.count % 4096 == 0 { try Task.checkCancellation() }
                guard line.count <= 20_000_000 else { throw ScenarioError.invalid("Le flux ChatGPT est trop volumineux.") }
            }
        }
        if !stream.completed {
            if !line.isEmpty {
                guard let text = String(data: line, encoding: .utf8) else { throw ScenarioError.invalid("Le flux ChatGPT est invalide.") }
                try stream.consume(text)
            }
            try stream.consume("")
        }
        try Task.checkCancellation()
        return try stream.result()
    }

    private static func nonStreamingResult(_ data: Data, response: HTTPURLResponse) throws -> String {
        if let root = (try? JSONSerialization.jsonObject(with: data)) as? [String: Any] {
            if let error = root["error"] as? [String: Any] {
                throw OAuthServiceError(status: response.statusCode, code: error["code"] as? String)
            }
            if root["status"] as? String == "failed" {
                throw OAuthServiceError(status: response.statusCode, code: nil)
            }
            // A buffered response is usable only if it explicitly finished.
            // A JSON-shaped scenario alone is never evidence of completion.
            guard root["object"] as? String == "response", root["status"] as? String == "completed" else {
                throw ScenarioError.invalid("La réponse ChatGPT est incomplète. Aucune proposition n’a été appliquée.")
            }
            var stream = ChatGPTEventStream()
            let event = try JSONSerialization.data(withJSONObject: ["type": "response.completed", "response": root])
            try stream.consume("data: " + String(decoding: event, as: UTF8.self))
            try stream.consume("")
            return try stream.result()
        }
        if let body = String(data: data, encoding: .utf8) {
            let start = body.trimmingCharacters(in: .whitespacesAndNewlines)
            if start.hasPrefix("data:") || start.hasPrefix("event:") || start.hasPrefix(":") {
                var stream = ChatGPTEventStream()
                for line in body.components(separatedBy: "\n") {
                    try stream.consume(line.hasSuffix("\r") ? String(line.dropLast()) : line)
                    if stream.completed { break }
                }
                if !stream.completed { try stream.consume("") }
                return try stream.result()
            }
        }
        // Never expose the response body: it may contain account information
        // or content from the user's scenario.
        throw ScenarioError.invalid("ChatGPT a renvoyé une réponse illisible (HTTP \(response.statusCode)). Réessayez ; si le problème persiste, vérifiez votre connexion réseau et l’accès à votre abonnement dans « Gérer l’utilisation ».")
    }
}
