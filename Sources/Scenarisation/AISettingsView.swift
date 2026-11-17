import SwiftUI

struct AISettingsView: View {
    @ObservedObject private var settings = AISettingsStore.shared
    @ObservedObject private var accounts = AIAccountConnections.shared
    @StateObject private var setup = AISetupModel()
    @State private var advanced = false
    @State private var manualModel = false

    private var configuration: AIConfiguration { settings.configuration }
    private var service: AIProvider { configuration.service }
    private var connectionTitle: String {
        if manualModel { return "Vérifier la connexion" }
        if setup.failure { return "Réessayer" }
        return settings.models.isEmpty && setup.message == nil ? "Connecter" : "Actualiser les modèles"
    }

    var body: some View {
        Form {
#if os(macOS)
            ScenarioFolderSection()
#endif
            Section {
                Picker("Service", selection: Binding(get: { service }, set: { settings.selectProvider($0) })) {
                    Section("Avec votre compte") {
                        ForEach([AIProvider.chatgpt, .claude, .antigravity, .grok].filter(\.availableOnThisDevice)) { Text($0.label).tag($0) }
                    }
                    if AIProvider.allCases.contains(where: { $0.isLocal && $0.availableOnThisDevice }) {
                        Section("Sur ce Mac") {
                            ForEach(AIProvider.allCases.filter(\.isLocal)) { Text($0.label).tag($0) }
                        }
                    }
                    Section("Avec une clé API") {
                        ForEach([AIProvider.openai, .mistral]) { Text($0.label).tag($0) }
                    }
                    Text(AIProvider.compatible.label).tag(AIProvider.compatible)
                }
                .disabled(accounts.busy)
                if service.isAccountConnection {
                    Text(service.usesACP ? "Utilisez votre compte avec \(service.cliName), sans clé API."
                         : service == .chatgpt ? "Utilisez votre abonnement ChatGPT, sans clé API."
                         : "Connectez votre compte avec Claude Code. Il gère lui-même votre connexion.")
                        .font(.callout).foregroundStyle(.secondary)
                    if service == .claude, (try? ClaudeCodeClient.executable()) == nil, let url = service.setupURL {
                        Link("Installer Claude Code", destination: url)
                    }
                    if service.usesACP, (try? SubscriptionCLIClient.executable(provider: service)) == nil, let url = service.setupURL {
                        Link("Installer \(service.cliName)", destination: url)
                    }
                } else if service.isLocal {
                    Text(service == .ollama
                         ? "Ouvrez Ollama avec un modèle téléchargé. Ses modèles sont détectés automatiquement."
                         : "Chargez un modèle dans LM Studio et activez son serveur local.")
                        .font(.callout).foregroundStyle(.secondary)
                    if let url = service.setupURL {
                        Link("Installer \(service.label)", destination: url).font(.callout)
                    }
                } else {
                    if service == .compatible { endpointField }
                    SecureField(service.requiresKey ? "Clé API" : "Clé API (si nécessaire)", text: $setup.key)
                        .autocorrectionDisabled()
                    if let url = service.setupURL {
                        Link("Obtenir une clé API", destination: url).font(.callout)
                    }
                }
            } header: {
                Text("Assistant IA")
            } footer: {
                if !service.isLocal && !service.isAccountConnection {
                    Text("La clé est enregistrée dans le Trousseau lors de la connexion.")
                }
            }

            if service.isAccountConnection {
                AIAccountSettingsPanel(settings: settings)
            } else {
                Section {
                    if !manualModel && (!settings.models.isEmpty || !configuration.model.isEmpty) {
                        Picker("Modèle", selection: Binding(get: { configuration.model }, set: { settings.selectModel($0) })) {
                            Text("Choisir un modèle…").tag("")
                            if !configuration.model.isEmpty && !settings.models.contains(where: { $0.id == configuration.model }) {
                                Text(configuration.model).tag(configuration.model)
                            }
                            ForEach(settings.models) { Text($0.label).tag($0.id) }
                        }
                        .disabled(setup.busy)
                    } else if !manualModel && setup.message == nil {
                        Text("Connectez le service pour afficher ses modèles.").foregroundStyle(.secondary)
                    }
                    HStack(spacing: 10) {
                        if setup.busy {
                            ProgressView().controlSize(.small)
                            Text("Connexion…").font(.callout).foregroundStyle(.secondary)
                            Spacer()
                            Button("Annuler") { setup.cancel() }
                        } else {
                            Spacer()
                            Button(connectionTitle) {
                                setup.connect(settings, manually: manualModel)
                            }
                            .disabled(service.requiresKey && setup.key.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty)
                            .keyboardShortcut(.defaultAction)
                            .studioButtons(prominent: true)
                        }
                    }
                    if let message = setup.message {
                        Label(message, systemImage: setup.failure ? "exclamationmark.triangle" : "info.circle")
                            .font(.callout).foregroundStyle(setup.failure ? Color.red : Color.secondary)
                            .textSelection(.enabled)
                    }
                } header: {
                    Text("Modèle")
                } footer: {
                    Text(manualModel
                         ? "Le test envoie un bref message, sans votre scénario. Le service peut le facturer."
                         : "La détection récupère la liste des modèles sans envoyer votre scénario.")
                }

                Section {
                    DisclosureGroup("Options avancées", isExpanded: $advanced) {
                        if service != .compatible { endpointField }
                        if service == .lmstudio {
                            SecureField("Clé API (si nécessaire)", text: $setup.key).autocorrectionDisabled()
                        }
                        Toggle("Saisir le modèle manuellement", isOn: $manualModel)
                            .onChange(of: manualModel) { _, _ in setup.cancel() }
                        if manualModel {
                            TextField("Nom du modèle", text: Binding(get: { configuration.model }, set: { settings.selectModel($0) }))
                                .autocorrectionDisabled().disabled(setup.busy)
                        }
                        if !configuration.usesOllama && !setup.key.isEmpty {
                            Button("Supprimer la clé enregistrée", role: .destructive) { setup.removeKey(settings) }
                        }
                        if configuration.endpoint != service.defaultEndpoint {
                            Button("Rétablir l’adresse par défaut") { settings.setEndpoint(service.defaultEndpoint) }
                        }
                    }
                } footer: {
                    Text(configuration.isOnThisMac
                         ? "Les demandes et votre scénario restent sur ce Mac."
                         : "Votre scénario sera transmis à ce service uniquement lorsque vous solliciterez l’assistant.")
                }
            }
        }
        .formStyle(.grouped)
        .frame(width: 540, height: 640)
        .onAppear { prepare() }
        .onChange(of: configuration.credentialAccount) { previous, current in
            manualModel = false
            prepare(detectLocal: previous.split(separator: ":").first != current.split(separator: ":").first)
        }
        .onDisappear { setup.cancel(); accounts.cancel() }
    }

    private func prepare(detectLocal: Bool = true) {
        if service.isAccountConnection { setup.cancel(); accounts.prepare(settings) }
        else { accounts.cancel(); setup.prepare(settings, detectLocal: detectLocal) }
    }

    private var endpointField: some View {
        TextField("Adresse du serveur", text: Binding(get: { configuration.endpoint }, set: { settings.setEndpoint($0) }))
            .autocorrectionDisabled()
    }
}

struct AIAccountSettingsPanel: View {
    @ObservedObject var settings: AISettingsStore
    @ObservedObject private var chatgpt = ChatGPTAuth.shared
    @ObservedObject private var accounts = AIAccountConnections.shared
    private var service: AIProvider { settings.configuration.service }
    private var ready: Bool {
        if service.usesACP { return settings.subscriptionServices.contains(service) && accounts.connectedSubscriptions.contains(service) }
        return service == .chatgpt ? chatgpt.activeAccount?.canUsePlan == true
            : settings.claudeEnabled && accounts.claudeAccount != nil
    }
    private var title: String {
        if service.usesACP { return ready ? "Actualiser" : "Continuer avec \(service == .grok ? "Grok" : "Google")" }
        if service == .claude { return ready ? "Actualiser" : "Continuer avec Claude" }
        if ready { return "Actualiser les modèles" }
        return chatgpt.activeAccount?.connected == true ? "Autoriser mon abonnement" : "Continuer avec ChatGPT"
    }

    var body: some View {
        Section {
            if service == .chatgpt, let account = chatgpt.activeAccount {
                if chatgpt.accounts.count > 1 {
                    Picker("Compte", selection: Binding(get: { account.id }, set: { accounts.selectChatGPTAccount($0, settings: settings) })) {
                        ForEach(chatgpt.accounts) { item in
                            Text("\(item.label) · \(item.id.suffix(6))").tag(item.id)
                        }
                    }.disabled(accounts.busy)
                } else { LabeledContent("Compte", value: account.label) }
                if account.connected {
                    Label(account.canUsePlan ? "Abonnement ChatGPT activé" : "Autorisation de l’abonnement nécessaire",
                          systemImage: account.canUsePlan ? "checkmark.circle" : "info.circle")
                        .font(.callout).foregroundStyle(.secondary)
                    Link("Gérer l’utilisation", destination: ChatGPTProtocol.usageURL)
                }
            } else if service == .claude, ready, let account = accounts.claudeAccount {
                LabeledContent("Compte", value: account.label)
            } else if service.usesACP, ready {
                LabeledContent("Connexion", value: service.cliName)
            }
            if ready && !settings.models.isEmpty {
                Picker("Modèle", selection: Binding(get: { settings.configuration.model }, set: { settings.selectModel($0) })) {
                    Text("Choisir un modèle…").tag("")
                    ForEach(settings.models) { Text($0.label).tag($0.id) }
                }.disabled(accounts.busy)
            }
            HStack(spacing: 10) {
                if accounts.busy {
                    ProgressView().controlSize(.small)
                    Text("Connexion en cours…").font(.callout).foregroundStyle(.secondary)
                    Spacer()
                    Button("Annuler") { accounts.cancel() }
                } else {
                    Spacer()
                    Button(title) { accounts.connect(settings) }
                        .keyboardShortcut(.defaultAction).studioButtons(prominent: true)
                }
            }
            if service == .chatgpt {
                HStack {
                    Button("Autre compte") { accounts.connect(settings, newChatGPTAccount: true) }
                    Spacer()
                    if chatgpt.activeAccount?.connected == true {
                        Button("Se déconnecter") { accounts.disconnect(settings) }
                    }
                }.font(.callout).disabled(accounts.busy)
            } else if ready || (service.usesACP && settings.subscriptionServices.contains(service)) {
                Button("Retirer ce compte de l’application") { accounts.disconnect(settings) }
                    .font(.callout).disabled(accounts.busy)
            }
            if let message = accounts.message ?? chatgpt.storageError {
                Label(message, systemImage: accounts.failure ? "exclamationmark.triangle" : "info.circle")
                    .font(.callout).foregroundStyle(accounts.failure ? Color.red : Color.secondary).textSelection(.enabled)
            }
        } header: {
            Text("Compte et modèle")
        } footer: {
            Text(service.usesACP
                 ? "Installez \(service.cliName), puis connectez votre compte dans le navigateur. Les droits et quotas de votre abonnement s’appliquent."
                 : service == .chatgpt
                 ? "La connexion s’ouvre dans votre navigateur. L’accès à l’abonnement dépend de votre compte et de votre autorisation."
                 : "Une version récente de Claude Code est nécessaire. L’application ne lit ni ne conserve ses jetons.")
        }
        Section {
            Text("Votre scénario est transmis au service choisi lorsque vous sollicitez l’assistant.")
                .font(.caption).foregroundStyle(.secondary)
        }
    }
}
