import Foundation

@MainActor
final class AIAccountConnections: ObservableObject {
    static let shared = AIAccountConnections()
    @Published private(set) var busy = false
    @Published private(set) var message: String?
    @Published private(set) var failure = false
    @Published private(set) var claudeAccount: ClaudeCodeAccount?
    @Published private(set) var connectedSubscriptions: Set<AIProvider> = []
    private var task: Task<Void, Never>?
    private var requestID = UUID()
    private var operationAccount: String?
    private let chatgpt: ChatGPTAuth

    init(chatgpt: ChatGPTAuth = .shared) { self.chatgpt = chatgpt }

    func prepare(_ settings: AISettingsStore) {
        if busy && operationAccount == settings.configuration.credentialAccount { return }
        cancel(); message = nil; failure = false
        let provider = settings.configuration.service
        guard provider.isAccountConnection else { return }
        if provider == .chatgpt, chatgpt.activeAccount?.canUsePlan != true { return }
        if provider == .claude, !settings.claudeEnabled { return }
        if provider.usesACP, !settings.subscriptionServices.contains(provider) { return }
        perform(settings) { try await self.loadModels(settings) }
    }

    func connect(_ settings: AISettingsStore, newChatGPTAccount: Bool = false) {
        perform(settings) {
            switch settings.configuration.service {
            case .chatgpt:
                if newChatGPTAccount || self.chatgpt.activeAccount?.canUsePlan != true {
                    try await self.chatgpt.signIn(useSavedAccount: !newChatGPTAccount,
                        reconsent: !newChatGPTAccount && self.chatgpt.activeAccount?.connected == true)
                    try Task.checkCancellation()
                    settings.resetModels()
                }
                if self.chatgpt.activeAccount?.canUsePlan == true { try await self.loadModels(settings) }
                else { self.message = "Compte connecté. Autorisez l’utilisation de votre abonnement pour activer l’assistant." }
            case .claude:
                if !settings.claudeEnabled || self.claudeAccount == nil {
                    if let existing = try? await ClaudeCodeClient.account() { self.claudeAccount = existing }
                    else { self.claudeAccount = try await ClaudeCodeClient.signIn() }
                    try Task.checkCancellation()
                    settings.enableClaude(true)
                }
                try await self.loadModels(settings)
            case .antigravity, .grok:
                try await self.loadSubscriptionModels(settings, signIn: !self.connectedSubscriptions.contains(settings.configuration.service))
            default: break
            }
        }
    }

    func selectChatGPTAccount(_ id: String, settings: AISettingsStore) {
        cancel()
        do {
            try chatgpt.selectAccount(id)
            settings.resetModels()
            prepare(settings)
        } catch { failure = true; message = error.localizedDescription }
    }

    func disconnect(_ settings: AISettingsStore) {
        let source = settings.configuration
        perform(settings) {
            if source.service == .chatgpt {
                self.message = try await self.chatgpt.signOut() ?? "Compte ChatGPT déconnecté."
                try Task.checkCancellation()
                guard source.credentialAccount == settings.configuration.credentialAccount else { return }
                settings.resetModels()
            } else if source.service == .claude {
                settings.enableClaude(false); self.claudeAccount = nil
                self.message = "Compte retiré de l’application. Votre connexion dans Claude Code est conservée."
            } else if source.service.usesACP {
                settings.enableSubscription(source.service, enabled: false)
                self.connectedSubscriptions.remove(source.service)
                self.message = "Compte retiré de l’application. Votre connexion dans \(source.service.cliName) est conservée."
            }
        }
    }

    private func loadModels(_ settings: AISettingsStore) async throws {
        let source = settings.configuration
        if source.service.usesACP {
            try await loadSubscriptionModels(settings)
            return
        }
        if source.service == .chatgpt {
            let token = try await chatgpt.bearerToken()
            let models = try await ChatGPTAPI.models(token: token)
            try Task.checkCancellation()
            settings.acceptModels(models, for: source)
            if !models.contains(where: { $0.id == settings.configuration.model }) {
                settings.selectModel(models.first?.id ?? "")
            }
            message = models.isEmpty ? "Aucun modèle disponible pour ce compte ChatGPT." : "ChatGPT est prêt. Votre abonnement est utilisé."
        } else if source.service == .claude {
            guard let account = try await ClaudeCodeClient.account() else {
                claudeAccount = nil
                throw ScenarioError.invalid("Reconnectez votre compte dans Claude Code.")
            }
            try Task.checkCancellation()
            claudeAccount = account
            settings.acceptModels([
                AIModel(id: "sonnet", name: "Sonnet"), AIModel(id: "opus", name: "Opus"), AIModel(id: "haiku", name: "Haiku")
            ], for: source)
            if settings.configuration.model.isEmpty { settings.selectModel("sonnet") }
            message = "Claude est prêt avec votre compte Claude Code."
        }
    }

    private func loadSubscriptionModels(_ settings: AISettingsStore, signIn: Bool = false) async throws {
        let source = settings.configuration
        connectedSubscriptions.remove(source.service)
        let models = try await SubscriptionCLIClient.models(provider: source.service, signIn: signIn)
        try Task.checkCancellation()
        guard settings.configuration.credentialAccount == source.credentialAccount else { return }
        settings.enableSubscription(source.service, enabled: true)
        settings.acceptModels(models, for: source)
        if !models.contains(where: { $0.id == settings.configuration.model }) { settings.selectModel(models.first?.id ?? "") }
        connectedSubscriptions.insert(source.service)
        message = "Compte connecté via \(source.service.cliName). Les modèles et quotas dépendent de votre abonnement."
    }

    private func perform(_ settings: AISettingsStore, operation: @escaping @MainActor () async throws -> Void) {
        cancel(); failure = false; message = nil; busy = true
        operationAccount = settings.configuration.credentialAccount
        let id = requestID
        task = Task {
            defer { if requestID == id { busy = false; task = nil } }
            do { try await operation() }
            catch {
                guard requestID == id, !Task.isCancelled else { return }
                chatgpt.handleAPIError(error)
                failure = true; message = error.localizedDescription
            }
        }
    }

    func cancel() { requestID = UUID(); task?.cancel(); task = nil; busy = false }
}
