import Foundation

// Claude Code, Antigravity CLI et Grok CLI sont des programmes lancés par l’app Mac.
// iPadOS ne permet pas d’exécuter d’autres programmes : ces services ne sont pas proposés sur iPad
// (voir `AIProvider.availableOnThisDevice`) et ces points d’entrée échouent proprement s’ils sont atteints.

private let unavailable = ScenarioError.invalid("Ce service passe par un outil installé sur Mac. Sur iPad, connectez ChatGPT ou une clé API.")

struct ClaudeCodeAccount: Equatable, Sendable {
    let email: String?
    var label: String { email ?? "Compte Claude" }
}

@MainActor
enum ClaudeCodeClient {
    static func executable() throws -> URL { throw unavailable }
    static func account() async throws -> ClaudeCodeAccount? { nil }
    static func signIn() async throws -> ClaudeCodeAccount { throw unavailable }
    static func generate(model: String, system: String, prompt: String) async throws -> String { throw unavailable }
}

@MainActor
enum SubscriptionCLIClient {
    static func executable(provider: AIProvider) throws -> URL { throw unavailable }
    static func models(provider: AIProvider, signIn: Bool = false) async throws -> [AIModel] { throw unavailable }
    static func generate(provider: AIProvider, model: String, system: String, prompt: String) async throws -> String { throw unavailable }
}
