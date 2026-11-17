import Foundation
import Darwin

/// Official CLI account sessions only. No token files are read by the app.
@MainActor
enum SubscriptionCLIClient {
    static func executable(provider: AIProvider) throws -> URL {
        let name = provider == .antigravity ? "agy" : "grok"
        let home = FileManager.default.homeDirectoryForCurrentUser.path
        let directories = [home + "/.local/bin", "/opt/homebrew/bin", "/usr/local/bin", home + "/.antigravity/bin", home + "/.grok/bin"]
            + (ProcessInfo.processInfo.environment["PATH"] ?? "").split(separator: ":").map(String.init)
        guard let path = directories.filter({ $0.hasPrefix("/") }).map({ $0 + "/" + name })
            .first(where: { FileManager.default.isExecutableFile(atPath: $0) }) else {
            throw ScenarioError.invalid("Installez \(provider.cliName), puis réessayez.")
        }
        return URL(fileURLWithPath: path)
    }

    static func environment(_ source: [String: String]) -> [String: String] {
        // Do not inherit API credentials, provider overrides, or injected runtime options.
        let allowed = ["HOME", "USER", "LOGNAME", "PATH", "TMPDIR", "LANG", "LC_ALL", "SHELL", "SSL_CERT_FILE", "SSL_CERT_DIR", "HTTPS_PROXY", "HTTP_PROXY", "NO_PROXY"]
        var result = source.filter { allowed.contains($0.key) }
        result["PATH"] = "/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:" + (source["PATH"] ?? "")
        result["GROK_DISABLE_AUTOUPDATER"] = "1"
        result["AGY_CLI_DISABLE_AUTO_UPDATE"] = "true"
        for family in ["CURSOR", "CLAUDE"] {
            for feature in ["SKILLS", "RULES", "AGENTS", "MCPS", "HOOKS"] {
                result["GROK_\(family)_\(feature)_ENABLED"] = "0"
            }
        }
        return result
    }

    static func models(provider: AIProvider, signIn: Bool = false) async throws -> [AIModel] {
        if signIn && provider == .grok {
            let result = try await NativeProcessRun.run(executable: executable(provider: provider), arguments: ["login"],
                timeout: 600, environment: environment(ProcessInfo.processInfo.environment), serviceName: provider.cliName)
            guard result.status == 0 else { throw ScenarioError.invalid("La connexion Grok n’a pas été terminée.") }
        }
        let connection = try ACPConnection(provider: provider)
        defer { connection.close() }
        let session = try await connection.openSession(signIn: signIn)
        return try ACPModelSelection(session: session).models
    }

    static func generate(provider: AIProvider, model: String, system: String, prompt: String) async throws -> String {
        let connection = try ACPConnection(provider: provider)
        defer { connection.close() }
        let session = try await connection.openSession()
        let selection = try ACPModelSelection(session: session)
        guard selection.models.contains(where: { $0.id == model }) else {
            throw ScenarioError.invalid("Ce modèle n’est plus disponible. Actualisez les modèles dans les réglages.")
        }
        if let option = selection.configID {
            _ = try await connection.request("session/set_config_option", ["sessionId": connection.sessionID, "configId": option, "value": model])
        } else {
            _ = try await connection.request("session/set_model", ["sessionId": connection.sessionID, "modelId": model])
        }
        // All conversation context travels in stdin, never command-line arguments.
        let input = "Consignes de l’application :\n" + system
            + "\nRéponds directement dans la conversation. N’utilise aucun outil, fichier, terminal, recherche ou sous-agent.\n\nDemande :\n" + prompt
        let result = try await connection.request("session/prompt", ["sessionId": connection.sessionID,
            "prompt": [["type": "text", "text": input]]], timeout: 240)
        guard result["stopReason"] as? String == "end_turn", !connection.answer.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty else {
            throw ScenarioError.invalid("La réponse de \(provider.cliName) est incomplète. Vérifiez votre quota, puis réessayez.")
        }
        return connection.answer
    }
}

/// Supports both the current ACP config-options API and the legacy model picker.
struct ACPModelSelection {
    let models: [AIModel]
    let configID: String?

    init(session: [String: Any]) throws {
        if let option = (session["configOptions"] as? [[String: Any]])?.first(where: { $0["category"] as? String == "model" || $0["id"] as? String == "model" }),
           let id = option["id"] as? String, let options = option["options"] as? [[String: Any]] {
            configID = id
            func flatten(_ values: [[String: Any]]) -> [AIModel] {
                values.flatMap { value -> [AIModel] in
                    if let nested = value["options"] as? [[String: Any]] { return flatten(nested) }
                    guard let id = value["value"] as? String, !id.isEmpty else { return [] }
                    return [AIModel(id: id, name: value["name"] as? String)]
                }
            }
            models = Self.unique(flatten(options))
        } else {
            configID = nil
            let state = session["models"] as? [String: Any]
            models = Self.unique((state?["availableModels"] as? [[String: Any]] ?? []).compactMap {
                guard let id = $0["modelId"] as? String, !id.isEmpty else { return nil }
                return AIModel(id: id, name: $0["name"] as? String)
            })
        }
        guard !models.isEmpty else {
            throw ScenarioError.invalid("Aucun modèle fourni par le compte. Mettez l’outil officiel à jour et vérifiez votre abonnement.")
        }
    }

    private static func unique(_ models: [AIModel]) -> [AIModel] {
        var seen = Set<String>()
        return models.filter { seen.insert($0.id).inserted }
    }
}

struct ACPRemoteError: LocalizedError {
    let code: Int
    let service: String
    var errorDescription: String? {
        if code == -32000 { return "Connectez votre compte dans les réglages de l’assistant (\(service))." }
        return "\(service) a refusé la demande (code \(code)). Vérifiez votre connexion, votre quota et la version de l’outil."
    }
}

/// One owned JSON-RPC subprocess per operation, with bounded output and no client tools.
@MainActor
final class ACPConnection {
    let provider: AIProvider
    private let process = Process()
    private let input = Pipe()
    private let output = Pipe()
    private let directory: URL
    private var reader: Task<Void, Never>?
    private var pending: [Int: CheckedContinuation<Data, Error>] = [:]
    private let writer = DispatchQueue(label: "eu.scenarisation.acp.stdin")
    private var timers: [Int: Task<Void, Never>] = [:]
    private var nextID = 0
    private var buffer = Data()
    private var receivedBytes = 0
    private var closed = false
    private(set) var sessionID = ""
    private(set) var answer = ""

    init(provider: AIProvider, executable: URL? = nil, arguments: [String]? = nil) throws {
        self.provider = provider
        directory = FileManager.default.temporaryDirectory.appendingPathComponent("Scenarisation-ACP-" + UUID().uuidString)
        try FileManager.default.createDirectory(at: directory, withIntermediateDirectories: false, attributes: [.posixPermissions: 0o700])
        do {
            process.executableURL = try executable ?? SubscriptionCLIClient.executable(provider: provider)
            process.arguments = arguments ?? (provider == .antigravity ? ["acp", "--sandbox", "--disable-telemetry"] : ["agent", "stdio"])
            process.currentDirectoryURL = directory
            process.environment = SubscriptionCLIClient.environment(ProcessInfo.processInfo.environment)
            process.standardInput = input
            _ = fcntl(input.fileHandleForWriting.fileDescriptor, F_SETNOSIGPIPE, 1)
            process.standardOutput = output
            process.standardError = FileHandle.nullDevice
            let cleanupDirectory = directory
            process.terminationHandler = { _ in try? FileManager.default.removeItem(at: cleanupDirectory) }
            try process.run()
            let handle = output.fileHandleForReading
            reader = Task.detached { [weak self] in
                while !Task.isCancelled {
                    let data = handle.availableData
                    if data.isEmpty { break }
                    guard let self else { return }
                    await self.receive(data)
                }
                await self?.ended()
            }
        } catch {
            try? FileManager.default.removeItem(at: directory)
            throw error
        }
    }

    func openSession(signIn: Bool = false) async throws -> [String: Any] {
        let initialized = try await request("initialize", ["protocolVersion": 1,
            "clientInfo": ["name": "scenarisation", "title": "Scénarisation", "version": "1.0"],
            "clientCapabilities": ["fs": ["readTextFile": false, "writeTextFile": false], "terminal": false]])
        guard initialized["protocolVersion"] as? Int == 1 else {
            throw ScenarioError.invalid("Version ACP incompatible. Mettez \(provider.cliName) à jour.")
        }
        let method = provider == .antigravity ? "oauth-personal" : "cached_token"
        let methods = initialized["authMethods"] as? [[String: Any]] ?? []
        guard methods.contains(where: { $0["id"] as? String == method }) else {
            throw ScenarioError.invalid("\(provider.cliName) ne propose pas la connexion par compte attendue. Mettez-le à jour et connectez-vous dans son interface officielle.")
        }
        // Explicitly select account authentication, even when a CLI has a saved API preference.
        _ = try await request("authenticate", ["methodId": method, "_meta": ["headless": !signIn]], timeout: signIn ? 600 : 30)
        let session = try await request("session/new", ["cwd": directory.path, "mcpServers": []])
        guard let id = session["sessionId"] as? String, !id.isEmpty else {
            throw ScenarioError.invalid("\(provider.cliName) n’a pas ouvert de conversation.")
        }
        sessionID = id
        return session
    }

    func request(_ method: String, _ params: [String: Any], timeout: Double = 30) async throws -> [String: Any] {
        try Task.checkCancellation()
        guard !closed else { throw CancellationError() }
        nextID += 1
        let id = nextID
        let data: Data = try await withTaskCancellationHandler {
            try await withCheckedThrowingContinuation { continuation in
                pending[id] = continuation
                do {
                    try send(["jsonrpc": "2.0", "id": id, "method": method, "params": params])
                    timers[id] = Task { [weak self] in
                        do { try await Task.sleep(for: .seconds(timeout)) } catch { return }
                        self?.fail(ScenarioError.invalid("\(self?.provider.cliName ?? "Le service") met trop de temps à répondre. Réessayez."))
                    }
                } catch { fail(error) }
            }
        } onCancel: { Task { @MainActor [weak self] in self?.close() } }
        try Task.checkCancellation()
        guard let result = try JSONSerialization.jsonObject(with: data) as? [String: Any] else {
            throw ScenarioError.invalid("Réponse ACP invalide.")
        }
        return result
    }

    private func send(_ message: [String: Any]) throws {
        var data = try JSONSerialization.data(withJSONObject: message)
        data.append(0x0a)
        let payload = data
        let handle = input.fileHandleForWriting
        writer.async { [weak self] in
            do { try handle.write(contentsOf: payload) }
            catch { Task { @MainActor in self?.fail(error) } }
        }
    }

    private func receive(_ data: Data) {
        guard !closed else { return }
        receivedBytes += data.count
        guard receivedBytes <= 20_000_000 else { fail(ScenarioError.invalid("La réponse du service est trop volumineuse.")); return }
        buffer.append(data)
        while let end = buffer.firstIndex(of: 0x0a) {
            let line = buffer.subdata(in: buffer.startIndex..<end)
            buffer.removeSubrange(buffer.startIndex...end)
            if line.isEmpty { continue }
            do {
                guard let message = try JSONSerialization.jsonObject(with: line) as? [String: Any] else { throw CocoaError(.coderReadCorrupt) }
                try receiveMessage(message)
            } catch { fail(error); return }
        }
    }

    private func receiveMessage(_ message: [String: Any]) throws {
        if let method = message["method"] as? String {
            if let id = message["id"] {
                // This is a text assistant: never grant tools, filesystem or terminal access.
                if method == "session/request_permission" {
                    try send(["jsonrpc": "2.0", "id": id, "result": ["outcome": ["outcome": "cancelled"]]])
                } else {
                    try send(["jsonrpc": "2.0", "id": id, "error": ["code": -32601, "message": "Client capability not supported"]])
                }
            } else if method == "session/update", let params = message["params"] as? [String: Any],
                      params["sessionId"] as? String == sessionID,
                      let update = params["update"] as? [String: Any], update["sessionUpdate"] as? String == "agent_message_chunk",
                      let content = update["content"] as? [String: Any], content["type"] as? String == "text",
                      let text = content["text"] as? String {
                answer += text
            }
            return
        }
        guard let id = message["id"] as? Int, let continuation = pending.removeValue(forKey: id) else { return }
        timers.removeValue(forKey: id)?.cancel()
        if let error = message["error"] as? [String: Any] {
            continuation.resume(throwing: ACPRemoteError(code: error["code"] as? Int ?? -32603, service: provider.cliName))
        } else if let result = message["result"] as? [String: Any] {
            do { continuation.resume(returning: try JSONSerialization.data(withJSONObject: result)) }
            catch { continuation.resume(throwing: error) }
        } else {
            continuation.resume(throwing: ScenarioError.invalid("Réponse ACP invalide."))
        }
    }

    private func ended() {
        fail(ScenarioError.invalid("\(provider.cliName) s’est arrêté. Vérifiez son installation et votre connexion."))
    }

    private func fail(_ error: Error) {
        let continuations = pending.values
        pending.removeAll()
        for timer in timers.values { timer.cancel() }
        timers.removeAll()
        for continuation in continuations { continuation.resume(throwing: error) }
        shutdown()
    }

    func close() { fail(CancellationError()) }

    private func shutdown() {
        guard !closed else { return }
        closed = true
        if process.isRunning {
            process.terminate()
            let running = process
            Task {
                try? await Task.sleep(for: .seconds(2))
                if running.isRunning { Darwin.kill(running.processIdentifier, SIGKILL) }
            }
        }
        let handle = input.fileHandleForWriting
        writer.async { try? handle.close() }
        reader?.cancel()
        reader = nil
    }
}
