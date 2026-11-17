import Foundation
import Darwin

struct NativeProcessResult: Sendable {
    let status: Int32
    let output: Data
}

/// Runs an owned subprocess asynchronously. Input never appears in its arguments.
@MainActor
final class NativeProcessRun {
    private let process = Process()
    private var continuation: CheckedContinuation<NativeProcessResult, Error>?
    private var timer: Task<Void, Never>?
    private var stopped = false
    private var timedOut = false
    private var outputURL: URL?
    private var inputHandle: FileHandle?
    private var outputHandle: FileHandle?

    static func run(executable: URL, arguments: [String], input: Data = Data(), timeout: Double = 180,
                    environment: [String: String]? = nil, serviceName: String = "Claude Code") async throws -> NativeProcessResult {
        let run = NativeProcessRun()
        let directory = FileManager.default.temporaryDirectory.appendingPathComponent("Scenarisation-AI-" + UUID().uuidString)
        try FileManager.default.createDirectory(at: directory, withIntermediateDirectories: false,
            attributes: [.posixPermissions: 0o700])
        defer { try? FileManager.default.removeItem(at: directory) }
        let inputURL = directory.appendingPathComponent("input")
        let outputURL = directory.appendingPathComponent("output")
        FileManager.default.createFile(atPath: inputURL.path, contents: input, attributes: [.posixPermissions: 0o600])
        FileManager.default.createFile(atPath: outputURL.path, contents: Data(), attributes: [.posixPermissions: 0o600])
        run.inputHandle = try FileHandle(forReadingFrom: inputURL)
        run.outputHandle = try FileHandle(forWritingTo: outputURL)
        defer { try? run.inputHandle?.close(); try? run.outputHandle?.close() }
        run.outputURL = outputURL
        run.process.executableURL = executable; run.process.arguments = arguments
        run.process.currentDirectoryURL = directory
        run.process.standardInput = run.inputHandle
        run.process.standardOutput = run.outputHandle
        run.process.standardError = FileHandle.nullDevice
        run.process.environment = environment ?? ClaudeCodeClient.environment(ProcessInfo.processInfo.environment)
        run.serviceName = serviceName
        try Task.checkCancellation()
        return try await withTaskCancellationHandler {
            try await withCheckedThrowingContinuation { continuation in
                run.continuation = continuation
                run.process.terminationHandler = { _ in Task { @MainActor in run.complete() } }
                do {
                    try run.process.run()
                    run.timer = Task {
                        do { try await Task.sleep(for: .seconds(timeout)) } catch { return }
                        run.timedOut = true; run.stop()
                    }
                } catch {
                    run.continuation?.resume(throwing: ScenarioError.invalid("Impossible de lancer \(serviceName). Vérifiez son installation."))
                    run.continuation = nil
                }
            }
        } onCancel: { Task { @MainActor in run.stop() } }
    }

    private var serviceName = "Claude Code"

    private func stop() {
        stopped = true
        guard process.isRunning else { return }
        process.terminate()
        Task {
            try? await Task.sleep(for: .seconds(2))
            if process.isRunning { Darwin.kill(process.processIdentifier, SIGKILL) }
        }
    }
    private func complete() {
        timer?.cancel(); timer = nil
        guard let continuation else { return }
        self.continuation = nil
        if timedOut {
            continuation.resume(throwing: ScenarioError.invalid("\(serviceName) met trop de temps à répondre. Réessayez.")); return
        }
        if stopped { continuation.resume(throwing: CancellationError()); return }
        do {
            guard let outputURL,
                  let size = try FileManager.default.attributesOfItem(atPath: outputURL.path)[.size] as? NSNumber,
                  size.intValue <= 20_000_000 else {
                throw ScenarioError.invalid("La réponse de \(serviceName) est trop volumineuse." )
            }
            continuation.resume(returning: NativeProcessResult(status: process.terminationStatus,
                output: try Data(contentsOf: outputURL)))
        } catch { continuation.resume(throwing: error) }
    }
}

struct ClaudeCodeAccount: Equatable, Sendable {
    let email: String?
    var label: String { email ?? "Compte Claude" }
}

@MainActor
enum ClaudeCodeClient {
    static func executable() throws -> URL {
        let home = FileManager.default.homeDirectoryForCurrentUser
        let candidates = [
            home.appendingPathComponent(".local/bin/claude"),
            URL(fileURLWithPath: "/opt/homebrew/bin/claude"),
            URL(fileURLWithPath: "/usr/local/bin/claude"),
            home.appendingPathComponent(".claude/local/claude")
        ]
        guard let executable = candidates.first(where: { FileManager.default.isExecutableFile(atPath: $0.path) }) else {
            throw ScenarioError.invalid("Installez Claude Code pour connecter votre compte Claude, puis réessayez.")
        }
        return executable
    }

    static func environment(_ source: [String: String]) -> [String: String] {
        var environment = source
        // This option must use the user's Claude account, without a silent API billing fallback.
        for key in source.keys where key.hasPrefix("ANTHROPIC_") || key.hasPrefix("CLAUDE_CODE_")
            || key == "CLAUDE_CONFIG_DIR" || key == "CLAUDECODE" {
            environment.removeValue(forKey: key)
        }
        environment["PATH"] = "/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:" + (source["PATH"] ?? "")
        environment["CLAUDE_CODE_DISABLE_NONESSENTIAL_TRAFFIC"] = "1"
        return environment
    }

    static func account() async throws -> ClaudeCodeAccount? {
        let result = try await NativeProcessRun.run(executable: executable(), arguments: ["auth", "status"], timeout: 20)
        return try account(from: result)
    }
    static func account(from result: NativeProcessResult) throws -> ClaudeCodeAccount? {
        guard let root = (try? JSONSerialization.jsonObject(with: result.output)) as? [String: Any] else {
            throw ScenarioError.invalid("Claude Code n’a pas renvoyé son état de connexion. Mettez-le à jour, puis réessayez.")
        }
        guard result.status == 0, root["loggedIn"] as? Bool == true else { return nil }
        guard ["claude.ai", "oauth_token"].contains(root["authMethod"] as? String ?? "") else {
            throw ScenarioError.invalid("Claude Code utilise une clé API ou un fournisseur externe. Connectez votre compte Claude pour utiliser cette option.")
        }
        return ClaudeCodeAccount(email: root["email"] as? String)
    }
    static func signIn() async throws -> ClaudeCodeAccount {
        let result = try await NativeProcessRun.run(executable: executable(), arguments: ["auth", "login"], timeout: 600)
        guard result.status == 0, let account = try await account() else {
            throw ScenarioError.invalid("La connexion Claude n’a pas été terminée. Réessayez dans les réglages.")
        }
        return account
    }
    static func generationArguments(model: String, system: String) -> [String] {
        ["--safe-mode", "--setting-sources", "", "--settings", "{\"disableAllHooks\":true}",
         "--strict-mcp-config", "--mcp-config", "{\"mcpServers\":{}}", "--tools", "",
         "--disallowedTools", "mcp__*", "--no-session-persistence", "--max-turns", "1",
         "--print", "--output-format", "json", "--model", model, "--system-prompt", system]
    }
    static func generate(model: String, system: String, prompt: String) async throws -> String {
        guard try await account() != nil else { throw ScenarioError.invalid("Connectez votre compte Claude dans les réglages de l’assistant.") }
        let result = try await NativeProcessRun.run(executable: executable(),
            arguments: generationArguments(model: model, system: system), input: Data(prompt.utf8))
        return try response(from: result)
    }
    static func response(from result: NativeProcessResult) throws -> String {
        guard result.status == 0,
              let root = (try? JSONSerialization.jsonObject(with: result.output)) as? [String: Any],
              root["is_error"] as? Bool == false, root["subtype"] as? String == "success",
              let text = root["result"] as? String, !text.isEmpty else {
            throw ScenarioError.invalid("Claude Code n’a pas pu préparer la proposition. Vérifiez votre connexion et votre quota, ou mettez Claude Code à jour.")
        }
        return text
    }
}
