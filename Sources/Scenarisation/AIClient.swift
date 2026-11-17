import Foundation
import Security

enum AIProvider: String, CaseIterable, Identifiable {
    case ollama, lmstudio, chatgpt, claude, antigravity, grok, openai, mistral, compatible
    var id: String { rawValue }
    var label: String {
        switch self {
        case .ollama: "Ollama"
        case .lmstudio: "LM Studio"
        case .chatgpt: "ChatGPT"
        case .claude: "Claude (via Claude Code)"
        case .antigravity: "Gemini / Antigravity"
        case .grok: "Grok"
        case .openai: "OpenAI"
        case .mistral: "Mistral"
        case .compatible: "Autre service compatible"
        }
    }
    var isLocal: Bool { self == .ollama || self == .lmstudio }
    var usesACP: Bool { self == .antigravity || self == .grok }
    var isAccountConnection: Bool { self == .chatgpt || self == .claude || usesACP }
    var cliName: String { self == .antigravity ? "Antigravity CLI" : "Grok Build" }
    var requiresKey: Bool { self == .openai || self == .mistral }
    var defaultEndpoint: String {
        switch self {
        case .ollama: "http://127.0.0.1:11434"
        case .lmstudio: "http://127.0.0.1:1234/v1"
        case .chatgpt: "https://api.openai.com/v1"
        case .claude: "https://claude.ai"
        case .antigravity: "https://antigravity.google"
        case .grok: "https://grok.com"
        case .openai: "https://api.openai.com/v1"
        case .mistral: "https://api.mistral.ai/v1"
        case .compatible: "https://"
        }
    }
    var setupURL: URL? {
        switch self {
        case .ollama: URL(string: "https://ollama.com/download")!
        case .lmstudio: URL(string: "https://lmstudio.ai/")!
        case .chatgpt: ChatGPTProtocol.usageURL
        case .claude: URL(string: "https://code.claude.com/docs/en/quickstart")!
        case .antigravity: URL(string: "https://antigravity.google/docs/cli/tutorial")!
        case .grok: URL(string: "https://docs.x.ai/build/overview")!
        case .openai: URL(string: "https://platform.openai.com/api-keys")!
        case .mistral: URL(string: "https://console.mistral.ai/api-keys")!
        case .compatible: nil
        }
    }
}

struct AIConfiguration: Equatable, Sendable {
    let provider: String
    let endpoint: String
    let model: String

    var service: AIProvider { AIProvider(rawValue: provider) ?? .compatible }
    var usesOllama: Bool { service == .ollama }
    var isOnThisMac: Bool {
        guard let host = (try? baseURL())?.host else { return false }
        return ["localhost", "127.0.0.1", "::1", "[::1]"].contains(host)
    }
    var isReady: Bool {
        if service == .claude || service.usesACP { return !model.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty }
        return (try? requestURL()) != nil
    }
    var credentialAccount: String { provider + ":" + endpoint.trimmingCharacters(in: CharacterSet(charactersIn: "/ ")) }

    func baseURL() throws -> URL {
        if service.isAccountConnection && endpoint != service.defaultEndpoint {
            throw ScenarioError.invalid("L’adresse d’une connexion par compte ne peut pas être personnalisée.")
        }
        guard let base = URL(string: endpoint.trimmingCharacters(in: .whitespacesAndNewlines)),
              let host = base.host, base.user == nil, base.password == nil, base.query == nil, base.fragment == nil,
              base.scheme == "https" || (base.scheme == "http" && ["localhost", "127.0.0.1", "::1", "[::1]"].contains(host)) else {
            throw ScenarioError.invalid("Utilisez une adresse HTTPS, ou HTTP pour un serveur sur ce Mac.")
        }
        return base
    }

    func modelsURL() throws -> URL {
        guard !service.usesACP else { throw ScenarioError.invalid("Les modèles sont fournis par \(service.cliName).") }
        guard service != .claude else { throw ScenarioError.invalid("La connexion Claude est gérée par Claude Code.") }
        return try baseURL().appendingPathComponent(usesOllama ? "api/tags" : "models")
    }

    func requestURL() throws -> URL {
        guard !service.usesACP else { throw ScenarioError.invalid("Les demandes passent par \(service.cliName).") }
        guard service != .claude else { throw ScenarioError.invalid("Les demandes Claude passent par Claude Code.") }
        let base = try baseURL()
        guard !model.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty else {
            throw ScenarioError.invalid("Choisissez un modèle dans les réglages de l’assistant.")
        }
        return base.appendingPathComponent(usesOllama ? "api/chat" : service == .chatgpt ? "responses" : "chat/completions")
    }
}

enum CredentialStore {
    static let service = "eu.scenarisation.mac.ai"
    static func read(account: String) throws -> String {
        let query: [String: Any] = [kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: service, kSecAttrAccount as String: account,
            kSecReturnData as String: true, kSecMatchLimit as String: kSecMatchLimitOne]
        var result: CFTypeRef?
        let status = SecItemCopyMatching(query as CFDictionary, &result)
        if status == errSecItemNotFound { return "" }
        guard status == errSecSuccess, let data = result as? Data, let value = String(data: data, encoding: .utf8) else {
            throw ScenarioError.invalid("Lecture du Trousseau impossible (\(status)).")
        }
        return value
    }
    static func save(_ value: String, account: String) throws {
        let query: [String: Any] = [kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: service, kSecAttrAccount as String: account]
        if value.isEmpty {
            let status = SecItemDelete(query as CFDictionary)
            guard status == errSecSuccess || status == errSecItemNotFound else {
                throw ScenarioError.invalid("Suppression de la clé impossible (\(status)).")
            }
            return
        }
        let data = Data(value.utf8)
        var status = SecItemUpdate(query as CFDictionary, [kSecValueData as String: data] as CFDictionary)
        if status == errSecItemNotFound {
            var attributes = query
            attributes[kSecValueData as String] = data
            attributes[kSecAttrAccessible as String] = kSecAttrAccessibleWhenUnlockedThisDeviceOnly
            status = SecItemAdd(attributes as CFDictionary, nil)
        }
        guard status == errSecSuccess else { throw ScenarioError.invalid("Enregistrement dans le Trousseau impossible (\(status)).") }
    }
}

// Never forward credentials to a redirect destination.
final class NoRedirect: NSObject, URLSessionTaskDelegate, Sendable {
    func urlSession(_ session: URLSession, task: URLSessionTask, willPerformHTTPRedirection response: HTTPURLResponse,
                    newRequest request: URLRequest, completionHandler: @escaping @Sendable (URLRequest?) -> Void) {
        completionHandler(nil)
    }
}

struct AIClient {
    /// Discovers models without sending a document or generating a completion.
    static func models(configuration: AIConfiguration, key: String,
                       sessionConfiguration: URLSessionConfiguration = .ephemeral) async throws -> [AIModel] {
        if configuration.service.usesACP { return try await SubscriptionCLIClient.models(provider: configuration.service) }
        if configuration.service == .chatgpt {
            _ = try configuration.baseURL()
            return try await ChatGPTAPI.models(token: key, sessionConfiguration: sessionConfiguration)
        }
        guard configuration.service != .claude else {
            throw ScenarioError.invalid("Connectez votre compte avec Claude Code dans les réglages.")
        }
        var request = URLRequest(url: try configuration.modelsURL())
        request.timeoutInterval = 15
        if !key.isEmpty { request.setValue("Bearer \(key)", forHTTPHeaderField: "Authorization") }
        let root = try await perform(request, configuration: configuration, sessionConfiguration: sessionConfiguration)
        guard let entries = root[configuration.usesOllama ? "models" : "data"] as? [[String: Any]] else {
            throw ScenarioError.invalid("Le service n’a pas renvoyé de liste de modèles. Vous pouvez saisir un modèle dans les options avancées.")
        }
        var seen = Set<String>()
        var models: [AIModel] = []
        for entry in entries {
            try Task.checkCancellation()
            guard let rawID = entry[configuration.usesOllama ? "name" : "id"] as? String else { continue }
            let id = rawID.trimmingCharacters(in: .whitespacesAndNewlines)
            guard !id.isEmpty, seen.insert(id).inserted else { continue }
            let capabilities = entry["capabilities"] as? [String: Any]
            if capabilities?["completion_chat"] as? Bool == false { continue }
            if configuration.service == .openai && !AIModel.isOpenAIChatModel(id) { continue }
            if configuration.usesOllama {
                // /api/tags also includes embedding-only models. /api/show reads
                // metadata without loading the model or generating any content.
                var detailsRequest = URLRequest(url: try configuration.baseURL().appendingPathComponent("api/show"))
                detailsRequest.httpMethod = "POST"
                detailsRequest.timeoutInterval = 15
                detailsRequest.setValue("application/json", forHTTPHeaderField: "Content-Type")
                if !key.isEmpty { detailsRequest.setValue("Bearer \(key)", forHTTPHeaderField: "Authorization") }
                detailsRequest.httpBody = try JSONSerialization.data(withJSONObject: ["model": id])
                let details = try await perform(detailsRequest, configuration: configuration, sessionConfiguration: sessionConfiguration)
                if let features = details["capabilities"] as? [String], !features.contains("completion") { continue }
            }
            models.append(AIModel(id: id, size: (entry["size"] as? NSNumber)?.int64Value))
        }
        return models.sorted { $0.id.localizedStandardCompare($1.id) == .orderedAscending }
    }

    /// Used only when a user explicitly tests a manually entered model.
    static func checkConnection(configuration: AIConfiguration, key: String,
                                sessionConfiguration: URLSessionConfiguration = .ephemeral) async throws {
        if configuration.service.usesACP {
            _ = try await SubscriptionCLIClient.models(provider: configuration.service)
            return
        }
        if configuration.service == .chatgpt {
            _ = try await models(configuration: configuration, key: key, sessionConfiguration: sessionConfiguration)
            return
        }
        if configuration.service == .claude {
            guard try await ClaudeCodeClient.account() != nil else { throw ScenarioError.invalid("Connectez votre compte Claude.") }
            return
        }
        var request = URLRequest(url: try configuration.requestURL())
        request.httpMethod = "POST"
        request.timeoutInterval = 90
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        if !key.isEmpty { request.setValue("Bearer \(key)", forHTTPHeaderField: "Authorization") }
        var body: [String: Any] = ["model": configuration.model, "stream": false,
            "messages": [["role": "user", "content": "Réponds uniquement par OK."]]]
        if configuration.usesOllama { body["think"] = false }
        request.httpBody = try JSONSerialization.data(withJSONObject: body)
        let root = try await perform(request, configuration: configuration, sessionConfiguration: sessionConfiguration)
        guard let content = content(root, configuration: configuration),
              !content.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty else {
            throw ScenarioError.invalid("Le modèle n’a pas répondu. Vérifiez son nom ou choisissez un autre modèle.")
        }
    }

    static func propose(scenario: Scenario, prompt: String, configuration: AIConfiguration, key: String,
                        sessionConfiguration: URLSessionConfiguration = .ephemeral) async throws -> Scenario {
        let source = String(decoding: try scenario.encoded(), as: UTF8.self)
        let system = AssistantPrompt.pedagogy + "\n" + AssistantPrompt.scenarioFormat
            + "\nRetourne uniquement un objet JSON complet compatible avec Scénarisation, sans balises Markdown."
        let user = "Demande de l’enseignant:\n\(prompt)\n\nScénario actuel:\n\(source)"
        let text = try await generate(system: system, user: user, configuration: configuration, key: key,
            sessionConfiguration: sessionConfiguration)
        return try Self.scenario(from: text)
    }

    static func generate(system: String, user: String, configuration: AIConfiguration, key: String,
                         sessionConfiguration: URLSessionConfiguration = .ephemeral) async throws -> String {
        if configuration.service.usesACP {
            return try await SubscriptionCLIClient.generate(provider: configuration.service, model: configuration.model, system: system, prompt: user)
        }
        if configuration.service == .chatgpt {
            _ = try configuration.requestURL()
            return try await ChatGPTAPI.response(model: configuration.model, system: system, prompt: user,
                token: key, sessionConfiguration: sessionConfiguration)
        }
        if configuration.service == .claude {
            return try await ClaudeCodeClient.generate(model: configuration.model, system: system, prompt: user)
        }
        var request = URLRequest(url: try configuration.requestURL())
        request.httpMethod = "POST"
        // A buffered reply sends nothing until generation ends, and a local
        // model can need several minutes for a complete scenario.
        request.timeoutInterval = configuration.isOnThisMac ? 900 : 180
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        if !key.isEmpty { request.setValue("Bearer \(key)", forHTTPHeaderField: "Authorization") }
        var body: [String: Any] = ["model": configuration.model, "stream": false, "messages": [
            ["role": "system", "content": system],
            ["role": "user", "content": user]]]
        if configuration.service.isLocal {
            // The length cap bounds a runaway generation: a full scenario
            // is a few thousand tokens.
            body["stream"] = true
            if configuration.usesOllama {
                // Reasoning adds about a minute to each step of the dialogue on
                // a local model; the JSON protocol already structures the answer.
                body["format"] = "json"; body["think"] = false
                body["options"] = ["num_predict": 12_000]
            } else { body["max_tokens"] = 12_000 }
            request.timeoutInterval = 300
            request.httpBody = try JSONSerialization.data(withJSONObject: body)
            return try await stream(request, configuration: configuration, sessionConfiguration: sessionConfiguration)
        }
        request.httpBody = try JSONSerialization.data(withJSONObject: body)
        let root = try await perform(request, configuration: configuration, sessionConfiguration: sessionConfiguration)
        guard let text = content(root, configuration: configuration) else {
            throw ScenarioError.invalid("Le modèle n’a pas renvoyé de réponse.")
        }
        return text
    }

    static func scenario(from content: String) throws -> Scenario {
        var text = content.trimmingCharacters(in: .whitespacesAndNewlines)
        if text.hasPrefix("```") {
            var lines = text.components(separatedBy: "\n")
            guard lines.count >= 3, lines.last?.trimmingCharacters(in: .whitespaces) == "```" else {
                throw ScenarioError.invalid("Le JSON généré est incomplet.")
            }
            lines.removeFirst(); lines.removeLast()
            text = lines.joined(separator: "\n")
        }
        return try Scenario.decode(Data(text.utf8), strict: true)
    }

    private static func content(_ root: [String: Any], configuration: AIConfiguration) -> String? {
        if configuration.usesOllama { return (root["message"] as? [String: Any])?["content"] as? String }
        return ((root["choices"] as? [[String: Any]])?.first?["message"] as? [String: Any])?["content"] as? String
    }

    /// Local servers stream the answer: one JSON object per line for Ollama,
    /// server-sent events for LM Studio. Reading fragments as they arrive keeps
    /// the idle timeout between two of them rather than on the whole
    /// generation, which a local model cannot finish within a fixed delay.
    private static func stream(_ request: URLRequest, configuration: AIConfiguration,
                               sessionConfiguration: URLSessionConfiguration) async throws -> String {
        sessionConfiguration.timeoutIntervalForResource = 1800
        let session = URLSession(configuration: sessionConfiguration, delegate: NoRedirect(), delegateQueue: nil)
        defer { session.invalidateAndCancel() }
        let incomplete = ScenarioError.invalid("La réponse du modèle est incomplète. Réessayez.")
        do {
            let (bytes, response) = try await session.bytes(for: request)
            try check(response)
            let events = (response as? HTTPURLResponse)?.value(forHTTPHeaderField: "Content-Type")?
                .lowercased().contains("text/event-stream") == true
            if !configuration.usesOllama && !events {
                // The server ignored the streaming request and buffered its reply.
                var data = Data()
                for try await byte in bytes {
                    data.append(byte)
                    guard data.count <= 20_000_000 else { throw ScenarioError.invalid("La réponse IA est trop volumineuse ou invalide.") }
                }
                guard let root = (try? JSONSerialization.jsonObject(with: data)) as? [String: Any],
                      let text = content(root, configuration: configuration) else { throw incomplete }
                return answer(text)
            }
            var text = ""
            var blank = 0
            for try await line in bytes.lines {
                try Task.checkCancellation()
                var payload = Substring(line)
                if events {
                    guard line.hasPrefix("data:") else { continue }
                    payload = line.dropFirst(5)
                    if payload.trimmingCharacters(in: .whitespaces) == "[DONE]" {
                        guard text.isEmpty else { return answer(text) }
                        break
                    }
                }
                guard let root = (try? JSONSerialization.jsonObject(with: Data(payload.utf8))) as? [String: Any] else { continue }
                // Do not display the raw error: it may quote the document.
                guard root["error"] == nil else {
                    throw ScenarioError.invalid("\(configuration.service.label) n’a pas pu terminer la réponse. Réessayez ou choisissez un autre modèle.")
                }
                // Reasoning travels in separate fields, which are ignored.
                let choice = (root["choices"] as? [[String: Any]])?.first
                let fragment = ((configuration.usesOllama ? root["message"] : choice?["delta"]) as? [String: Any])?["content"] as? String ?? ""
                text += fragment
                // Constrained JSON output can degenerate into endless whitespace.
                let trailing = fragment.reversed().prefix { $0.isWhitespace }.count
                blank = trailing == fragment.count ? blank + trailing : trailing
                guard blank <= 400 else { throw ScenarioError.invalid("Le modèle s’est emballé sans terminer sa réponse. Réessayez ou choisissez un autre modèle.") }
                guard text.utf8.count <= 20_000_000 else { throw ScenarioError.invalid("La réponse IA est trop volumineuse ou invalide.") }
                let reason = configuration.usesOllama
                    ? (root["done"] as? Bool == true ? root["done_reason"] as? String ?? "stop" : nil)
                    : choice?["finish_reason"] as? String
                guard let reason else { continue }
                guard reason != "length" else {
                    throw ScenarioError.invalid("La réponse dépasse la capacité du modèle. Demandez une modification plus courte ou choisissez un autre modèle.")
                }
                return answer(text)
            }
            throw incomplete
        } catch let error as URLError { throw transportError(error, configuration: configuration) }
    }

    /// Some servers leave a reasoning model's thoughts inline, before its answer.
    static func answer(_ text: String) -> String {
        let trimmed = text.trimmingCharacters(in: .whitespacesAndNewlines)
        guard trimmed.hasPrefix("<think>"), let end = trimmed.range(of: "</think>") else { return text }
        return String(trimmed[end.upperBound...])
    }

    private static func transportError(_ error: URLError, configuration: AIConfiguration) -> Error {
        switch error.code {
        case .cannotConnectToHost, .cannotFindHost, .networkConnectionLost:
            let hint = configuration.isOnThisMac
                ? "Ouvrez \(configuration.service.label) et démarrez son serveur, puis réessayez."
                : "Vérifiez votre connexion et l’adresse du service."
            return ScenarioError.invalid("Impossible de joindre le service. \(hint)")
        case .timedOut:
            return ScenarioError.invalid(configuration.isOnThisMac
                ? "Le modèle local met trop de temps à répondre. Raccourcissez la demande ou choisissez un modèle plus léger."
                : "Le service met trop de temps à répondre. Réessayez dans un instant.")
        case .notConnectedToInternet: return ScenarioError.invalid("Votre Mac n’est pas connecté à Internet.")
        default: return error
        }
    }

    private static func perform(_ request: URLRequest, configuration: AIConfiguration,
                                sessionConfiguration: URLSessionConfiguration) async throws -> [String: Any] {
        sessionConfiguration.timeoutIntervalForResource = max(240, request.timeoutInterval)
        let session = URLSession(configuration: sessionConfiguration, delegate: NoRedirect(), delegateQueue: nil)
        defer { session.invalidateAndCancel() }
        let data: Data
        let response: URLResponse
        do { (data, response) = try await session.data(for: request) }
        catch let error as URLError { throw transportError(error, configuration: configuration) }
        try Task.checkCancellation()
        try check(response)
        guard data.count <= 20_000_000,
              let root = try JSONSerialization.jsonObject(with: data) as? [String: Any] else {
            throw ScenarioError.invalid("La réponse IA est trop volumineuse ou invalide.")
        }
        return root
    }

    private static func check(_ response: URLResponse) throws {
        guard let response = response as? HTTPURLResponse else { throw ScenarioError.invalid("Réponse réseau invalide.") }
        guard (200..<300).contains(response.statusCode) else {
            // Do not display raw responses: some gateways echo credentials or document contents.
            let hint: String
            switch response.statusCode {
            case 401, 403: hint = "Vérifiez votre clé API et les autorisations du modèle."
            case 404: hint = "Vérifiez l’adresse ou le modèle. Si la liste est indisponible, saisissez le modèle dans les options avancées."
            case 429: hint = "Quota ou limite de requêtes atteint. Réessayez plus tard."
            case 300..<400: hint = "Redirection refusée. Indiquez directement l’adresse finale."
            default: hint = "Le serveur IA n’a pas pu traiter la demande."
            }
            throw ScenarioError.invalid("HTTP \(response.statusCode). \(hint)")
        }
    }
}
