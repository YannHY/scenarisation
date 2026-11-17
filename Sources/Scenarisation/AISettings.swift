import Foundation
import Combine

struct AIModel: Codable, Equatable, Identifiable, Sendable {
    let id: String
    var size: Int64? = nil
    var name: String? = nil

    var label: String {
        guard let size, size > 0 else { return name ?? id }
        return "\(name ?? id) · \(ByteCountFormatter.string(fromByteCount: size, countStyle: .file))"
    }

    // /models also lists audio, image and other specialised models. Unknown IDs
    // remain usable through manual entry; no default cloud model is imposed.
    static func isOpenAIChatModel(_ id: String) -> Bool {
        let name = id.lowercased().split(separator: ":").last.map(String.init) ?? id.lowercased()
        let chatFamily = ["gpt-", "chatgpt-", "o1", "o3", "o4"].contains { name.hasPrefix($0) }
        let specialised = ["audio", "realtime", "transcrib", "tts", "image", "search", "instruct", "codex", "deep-research", "pro"]
            .contains { name.contains($0) }
        return chatFamily && !specialised
    }
}

/// Settings and document windows share preferences; legacy keys are preserved.
@MainActor
final class AISettingsStore: ObservableObject {
    static let shared = AISettingsStore()
    @Published private(set) var configuration: AIConfiguration
    @Published private(set) var models: [AIModel] = []
    @Published private(set) var claudeEnabled: Bool
    @Published private(set) var subscriptionServices: Set<AIProvider>
    private let defaults: UserDefaults

    init(defaults: UserDefaults = .standard) {
        self.defaults = defaults
        claudeEnabled = defaults.bool(forKey: "ai.claude.enabled")
        subscriptionServices = Set(AIProvider.allCases.filter { $0.usesACP && defaults.bool(forKey: "ai.\($0.rawValue).enabled") })
        let provider = AIProvider(rawValue: defaults.string(forKey: "ai.provider") ?? "ollama") ?? .ollama
        configuration = Self.load(provider, defaults: defaults)
        models = Self.loadModels(configuration, defaults: defaults)
    }

    private static func load(_ provider: AIProvider, defaults: UserDefaults) -> AIConfiguration {
        AIConfiguration(provider: provider.rawValue,
            endpoint: defaults.string(forKey: "ai.\(provider.rawValue).endpoint") ?? provider.defaultEndpoint,
            model: defaults.string(forKey: "ai.\(provider.rawValue).model") ?? "")
    }

    private static func loadModels(_ configuration: AIConfiguration, defaults: UserDefaults) -> [AIModel] {
        let prefix = "ai.\(configuration.provider).models"
        guard defaults.string(forKey: prefix + ".account") == configuration.credentialAccount,
              let data = defaults.data(forKey: prefix), let models = try? JSONDecoder().decode([AIModel].self, from: data) else { return [] }
        return models
    }

    var savedConfigurations: [AIConfiguration] {
        ([configuration] + AIProvider.allCases.filter { $0 != configuration.service }.map { Self.load($0, defaults: defaults) })
            .filter(\.isReady)
    }

    /// Reuse the selected service when possible, then the other saved choices.
    /// No inference or network probe is needed to enter the creation chat.
    @discardableResult
    func restoreForCreation(chatGPTAvailable: Bool,
                            readKey: (String) throws -> String = { try CredentialStore.read(account: $0) }) -> Bool {
        for candidate in savedConfigurations {
            let available: Bool
            switch candidate.service {
            case .chatgpt: available = chatGPTAvailable
            case .claude: available = claudeEnabled
            case .antigravity, .grok: available = subscriptionServices.contains(candidate.service)
            default:
                available = !candidate.service.requiresKey || !((try? readKey(candidate.credentialAccount)) ?? "").isEmpty
            }
            if available { selectProvider(candidate.service); return true }
        }
        return false
    }

    func selectProvider(_ provider: AIProvider) {
        guard configuration.service != provider else { return }
        defaults.set(provider.rawValue, forKey: "ai.provider")
        configuration = Self.load(provider, defaults: defaults)
        models = Self.loadModels(configuration, defaults: defaults)
    }

    func setEndpoint(_ endpoint: String) {
        guard endpoint != configuration.endpoint else { return }
        models = []
        update(endpoint: endpoint, model: "")
    }

    func selectModel(_ model: String) {
        update(endpoint: configuration.endpoint, model: model.trimmingCharacters(in: .whitespacesAndNewlines))
    }

    func resetModels() {
        models = []; defaults.removeObject(forKey: "ai.\(configuration.provider).models")
        defaults.removeObject(forKey: "ai.\(configuration.provider).models.account"); selectModel("")
    }
    func enableClaude(_ enabled: Bool) {
        defaults.set(enabled, forKey: "ai.claude.enabled"); claudeEnabled = enabled
        if !enabled && configuration.service == .claude { resetModels() }
    }

    func enableSubscription(_ provider: AIProvider, enabled: Bool) {
        guard provider.usesACP else { return }
        defaults.set(enabled, forKey: "ai.\(provider.rawValue).enabled")
        if enabled { subscriptionServices.insert(provider) } else { subscriptionServices.remove(provider) }
        if !enabled && configuration.service == provider { resetModels() }
    }

    func acceptModels(_ models: [AIModel], for source: AIConfiguration) {
        guard source.credentialAccount == configuration.credentialAccount else { return }
        self.models = models
        defaults.set(try? JSONEncoder().encode(models), forKey: "ai.\(configuration.provider).models")
        defaults.set(configuration.credentialAccount, forKey: "ai.\(configuration.provider).models.account")
        if configuration.usesOllama && !configuration.model.isEmpty && !models.contains(where: { $0.id == configuration.model }) {
            selectModel("")
        }
        if configuration.model.isEmpty && models.count == 1 { selectModel(models[0].id) }
    }

    private func update(endpoint: String, model: String) {
        let provider = configuration.provider
        defaults.set(endpoint, forKey: "ai.\(provider).endpoint")
        defaults.set(model, forKey: "ai.\(provider).model")
        configuration = AIConfiguration(provider: provider, endpoint: endpoint, model: model)
    }
}

@MainActor
final class AISetupModel: ObservableObject {
    @Published var key = "" {
        didSet { if key != oldValue { cancel(); message = nil } }
    }
    @Published private(set) var busy = false
    @Published private(set) var message: String?
    @Published private(set) var failure = false
    private var task: Task<Void, Never>?
    private var requestID = UUID()

    private let readKey: @MainActor (String) throws -> String
    private let saveKey: @MainActor (String, String) throws -> Void
    private let discover: @MainActor (AIConfiguration, String) async throws -> [AIModel]
    private let check: @MainActor (AIConfiguration, String) async throws -> Void

    init(readKey: @escaping @MainActor (String) throws -> String = { try CredentialStore.read(account: $0) },
         saveKey: @escaping @MainActor (String, String) throws -> Void = { try CredentialStore.save($0, account: $1) },
         discover: @escaping @MainActor (AIConfiguration, String) async throws -> [AIModel] = { try await AIClient.models(configuration: $0, key: $1) },
         check: @escaping @MainActor (AIConfiguration, String) async throws -> Void = { try await AIClient.checkConnection(configuration: $0, key: $1) }) {
        self.readKey = readKey; self.saveKey = saveKey; self.discover = discover; self.check = check
    }

    func prepare(_ settings: AISettingsStore, detectLocal: Bool = true) {
        cancel(); message = nil; failure = false; key = ""
        let configuration = settings.configuration
        guard !configuration.service.isAccountConnection else { return }
        guard (try? configuration.baseURL()) != nil else { return }
        do {
            if !configuration.usesOllama { key = try readKey(configuration.credentialAccount) }
            if detectLocal && configuration.service.isLocal && configuration.isOnThisMac { connect(settings) }
        } catch { message = error.localizedDescription; failure = true }
    }

    @discardableResult
    func connect(_ settings: AISettingsStore, manually: Bool = false) -> Task<Void, Never>? {
        cancel(); message = nil; failure = false
        let configuration = settings.configuration
        guard !configuration.service.isAccountConnection else { return nil }
        let credential = key.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !configuration.service.requiresKey || !credential.isEmpty else {
            message = "Collez votre clé API pour connecter ce service."; failure = true; return nil
        }
        busy = true
        let id = requestID
        task = Task {
            defer { if id == requestID { busy = false; task = nil } }
            do {
                let models: [AIModel]
                if manually {
                    try await check(configuration, credential)
                    models = []
                } else {
                    models = try await discover(configuration, credential)
                }
                try Task.checkCancellation()
                guard id == requestID, configuration.credentialAccount == settings.configuration.credentialAccount,
                      !manually || configuration.model == settings.configuration.model else { return }
                if !configuration.usesOllama {
                    try saveKey(credential, configuration.credentialAccount)
                }
                if !manually { settings.acceptModels(models, for: configuration) }
                if manually {
                    message = "Connexion vérifiée. Votre modèle est prêt."
                } else if models.isEmpty {
                    message = configuration.service.isLocal
                        ? "Aucun modèle disponible. Téléchargez et chargez un modèle dans \(configuration.service.label), puis actualisez."
                        : "Aucun modèle de conversation disponible. Vérifiez les accès de votre compte ou saisissez un modèle dans les options avancées."
                } else {
                    message = settings.configuration.model.isEmpty ? "Service connecté. Choisissez votre modèle." : "Service connecté. Votre modèle est sélectionné."
                }
            } catch {
                guard id == requestID, !Task.isCancelled else { return }
                failure = true; message = error.localizedDescription
            }
        }
        return task
    }

    func removeKey(_ settings: AISettingsStore) {
        cancel()
        do {
            try saveKey("", settings.configuration.credentialAccount)
            key = ""; message = "Clé supprimée du Trousseau."; failure = false
        } catch { message = error.localizedDescription; failure = true }
    }

    func cancel() {
        requestID = UUID(); task?.cancel(); task = nil; busy = false
    }
}
