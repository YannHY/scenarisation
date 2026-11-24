import SwiftUI

struct ChatEntry: Identifiable {
    let id = UUID()
    let role: String
    let text: String
    var attachments: [String] = []
}

@MainActor
final class AssistantModel: ObservableObject {
    typealias Responder = @MainActor (Scenario, [AssistantTurn], AIConfiguration, String) async throws -> AssistantReply
    typealias Credential = @MainActor (AIConfiguration) async throws -> String
    @Published var messages: [ChatEntry] = []
    @Published var proposal: Scenario?
    @Published var baseline: Scenario?
    @Published var busy = false
    @Published var error: String?
    /// Documents joints au message en cours de rédaction.
    @Published var attachments: [AssistantDocument] = []
    private var task: Task<Void, Never>?
    private var requestID = UUID()
    private var history: [AssistantTurn] = []
    private let respond: Responder
    private let credential: Credential

    init(respond: Responder? = nil, credential: Credential? = nil) {
        self.respond = respond ?? { scenario, history, configuration, key in
            try await AssistantConversation.reply(scenario: scenario, history: history,
                profile: AISettingsStore.shared.teacherProfile, configuration: configuration, key: key)
        }
        self.credential = credential ?? { try await AICredentials.read($0) }
    }

    /// Ajoute des fichiers à la prochaine demande. Les fichiers illisibles sont signalés sans bloquer les autres.
    func attach(_ urls: [URL]) {
        var failures: [String] = []
        var used = (history.flatMap { $0.documents ?? [] } + attachments).reduce(0) { $0 + $1.text.count }
        for url in urls {
            guard attachments.count < AssistantDocumentReader.maximumFiles else {
                failures.append("\(AssistantDocumentReader.maximumFiles) documents au plus par message."); break
            }
            let access = url.startAccessingSecurityScopedResource()
            defer { if access { url.stopAccessingSecurityScopedResource() } }
            do {
                let document = try AssistantDocumentReader.read(url)
                guard used + document.text.count <= AssistantDocumentReader.totalLimit else {
                    failures.append("« \(document.name) » dépasse la quantité de texte que l’assistant peut lire dans une conversation.")
                    continue
                }
                used += document.text.count
                attachments.append(document)
            } catch { failures.append(error.localizedDescription) }
        }
        error = failures.isEmpty ? nil : failures.joined(separator: "\n")
    }
    func removeAttachment(_ document: AssistantDocument) { attachments.removeAll { $0.id == document.id } }

    @discardableResult
    func send(_ prompt: String, scenario: Scenario, configuration: AIConfiguration) -> Task<Void, Never>? {
        var prompt = prompt.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !busy, !prompt.isEmpty || !attachments.isEmpty else { return nil }
        if let baseline, baseline != scenario {
            error = "Le scénario a changé. Écartez la proposition pour repartir de sa version actuelle."
            return nil
        }
        if prompt.isEmpty { prompt = "Voici mes documents." }
        let documents = attachments
        attachments = []
        let reference = proposal ?? scenario
        let source = baseline ?? scenario
        let conversation = history + [AssistantTurn(role: .user, content: prompt, documents: documents.isEmpty ? nil : documents)]
        messages.append(ChatEntry(role: "Vous", text: prompt, attachments: documents.map(\.name)))
        error = nil; busy = true
        let id = UUID(); requestID = id
        let chatgptClientID = configuration.service == .chatgpt ? ChatGPTAuth.shared.activeAccount?.id : nil
        task = Task {
            do {
                guard configuration.isReady else { throw ScenarioError.invalid("Choisissez un modèle dans les réglages de l’assistant.") }
                let key = try await credential(configuration)
                let result = try await respond(reference, conversation, configuration, key)
                guard !Task.isCancelled, requestID == id else { return }
                if let proposed = result.proposal { proposal = proposed; baseline = source }
                history = conversation + [AssistantTurn(role: .assistant, content: result.message)]
                messages.append(ChatEntry(role: "Assistant", text: result.message))
                busy = false; task = nil
            } catch {
                guard requestID == id else { return }
                if configuration.service == .chatgpt { ChatGPTAuth.shared.handleAPIError(error, clientID: chatgptClientID) }
                busy = false; task = nil
                // Les documents restent joints pour réessayer sans les choisir de nouveau.
                if attachments.isEmpty { attachments = documents }
                if !Task.isCancelled { self.error = error.localizedDescription }
            }
        }
        return task
    }

    func cancel() {
        requestID = UUID(); task?.cancel(); task = nil; busy = false
        if proposal == nil { baseline = nil }
    }
    func discard() {
        cancel(); proposal = nil; baseline = nil; error = nil
        record("Proposition écartée. Le scénario ouvert est conservé.")
    }
    func applied() {
        cancel(); proposal = nil; baseline = nil; error = nil
        record("Proposition appliquée. Vous pouvez annuler avec ⌘Z.")
    }
    func newConversation() {
        cancel(); proposal = nil; baseline = nil; error = nil
        history = []; messages = []; attachments = []
    }
    func beginCreation() {
        guard messages.isEmpty, !busy else { return }
        record(AssistantPlanning.welcome(profile: AISettingsStore.shared.teacherProfile))
    }
    private func record(_ text: String) {
        messages.append(ChatEntry(role: "Assistant", text: text))
        history.append(AssistantTurn(role: .assistant, content: text))
    }
}

struct AssistantView: View {
    @ObservedObject var model: AssistantModel
    @Binding var scenario: Scenario
    var creationMode = false
    var onApplied: () -> Void = {}
    @ObservedObject private var settings = AISettingsStore.shared
    @ObservedObject private var chatgpt = ChatGPTAuth.shared
    @ObservedObject private var accounts = AIAccountConnections.shared
    @State private var prompt = ""
    @State private var showSettings = false
    @State private var showReview = false
    @State private var preparedCreation = false
    @State private var showAttach = false
    @FocusState private var composerFocused: Bool

    private static let documentsPrompt = "Crée un scénario pédagogique complet à partir de mes documents : public, acquis d’apprentissage, moments, activités, durées et évaluations. Pose-moi d’abord les questions indispensables auxquelles mes documents ne répondent pas."

    private var configuration: AIConfiguration { settings.configuration }
    private var trimmedPrompt: String { prompt.trimmingCharacters(in: .whitespacesAndNewlines) }
    private var ready: Bool {
        guard configuration.isReady else { return false }
        if configuration.service == .chatgpt { return !chatgpt.signingIn && chatgpt.activeAccount?.canUsePlan == true }
        if configuration.service == .claude { return settings.claudeEnabled && accounts.claudeAccount != nil }
        if configuration.service.usesACP { return settings.subscriptionServices.contains(configuration.service) && accounts.connectedSubscriptions.contains(configuration.service) }
        return true
    }
    private var modelName: String { settings.models.first { $0.id == configuration.model }?.name ?? configuration.model }
    private var canSend: Bool { (!trimmedPrompt.isEmpty || !model.attachments.isEmpty) && ready }

    var body: some View {
        VStack(alignment: .leading, spacing: 0) {
            header
            Divider().opacity(0.5)
            ScrollViewReader { proxy in
                ScrollView {
                    VStack(alignment: .leading, spacing: 12) {
                        if model.messages.isEmpty { emptyState }
                        ForEach(model.messages) { message in bubble(message).id(message.id) }
                        // En création, le message d’accueil remplace l’état vide : la suggestion reste proposée
                        // jusqu’à la première demande.
                        if ready, !model.busy, !model.messages.isEmpty, !model.messages.contains(where: { $0.role == "Vous" }) {
                            documentsSuggestion
                        }
                        if model.busy {
                            HStack(spacing: 8) { ProgressView().controlSize(.small); Text("Préparation de la réponse…").foregroundStyle(.secondary) }
                                .padding(.leading, 4)
                        }
                    }.padding(16).frame(maxWidth: .infinity, alignment: .leading)
                }
                .onChange(of: model.messages.count) { _, _ in
                    if let id = model.messages.last?.id { withAnimation(.smooth) { proxy.scrollTo(id, anchor: .bottom) } }
                }
            }
            footer
        }
        .fileImporter(isPresented: $showAttach, allowedContentTypes: AssistantDocumentReader.contentTypes,
                      allowsMultipleSelection: true) { result in
            switch result {
            case .success(let urls): model.attach(urls); composerFocused = true
            case .failure(let error): model.error = error.localizedDescription
            }
        }
        .sheet(isPresented: $showSettings) {
            VStack(spacing: 0) {
                AISettingsView()
                HStack { Spacer(); Button(creationMode && ready ? "Commencer" : "Terminé") { showSettings = false }.studioButtons() }
                    .padding([.horizontal, .bottom], 20)
            }
        }
        .sheet(isPresented: $showReview) {
            if let proposal = model.proposal {
                ProposalReview(current: scenario, proposed: proposal, stale: model.baseline.map { $0 != scenario } ?? false) {
                    if let baseline = model.baseline, baseline != scenario {
                        // Le scénario a changé depuis la demande (chez un collègue, par exemple) : seules les
                        // modifications proposées s’appliquent, sans effacer celles faites entre-temps.
                        scenario = ScenarioMerge.merge(base: baseline, local: proposal, remote: scenario,
                                                       keepLocalViewState: false).scenario
                    } else { scenario = proposal }
                    model.applied(); showReview = false; onApplied()
                }
            }
        }
        .task(id: configuration.service) {
            if configuration.service.isAccountConnection { accounts.prepare(settings) }
        }
        .task {
            guard creationMode, !preparedCreation else { return }
            preparedCreation = true
            // Returning to an existing chat must preserve its service choice.
            guard model.messages.isEmpty else { return }
            let available = settings.restoreForCreation(chatGPTAvailable: chatgpt.activeAccount?.canUsePlan == true)
            if available && ready { model.beginCreation(); composerFocused = true }
            if !available { showSettings = true }
        }
        .onChange(of: ready) { _, value in
            if !value && configuration.service.usesACP { model.cancel() }
            if value && creationMode { model.beginCreation(); composerFocused = true }
        }
        .onChange(of: configuration) { _, _ in model.cancel() }
        .onChange(of: chatgpt.signingIn) { _, signingIn in
            if signingIn && configuration.service == .chatgpt { model.cancel() }
        }
        .onChange(of: chatgpt.activeAccount) { previous, current in
            if configuration.service == .chatgpt && (previous?.id != current?.id || current?.connected != true) { model.cancel() }
        }
        .onChange(of: settings.claudeEnabled) { _, enabled in
            if !enabled && configuration.service == .claude { model.cancel() }
        }
    }

    private var header: some View {
        HStack(spacing: 10) {
            FA(name: "wand", size: 14).foregroundStyle(.white).frame(width: 30, height: 30)
                .background(LinearGradient(colors: [Color.accentColor, Color.accentColor.opacity(0.7)], startPoint: .top, endPoint: .bottom),
                            in: RoundedRectangle(cornerRadius: 9, style: .continuous))
            VStack(alignment: .leading, spacing: 1) {
                Text("Assistant").font(.headline)
                Menu {
                    ForEach(settings.models) { item in
                        Button { settings.selectModel(item.id) } label: {
                            if item.id == configuration.model { Label(item.name ?? item.id, systemImage: "checkmark") }
                            else { Text(item.name ?? item.id) }
                        }
                    }
                    let otherServices = settings.savedConfigurations.filter { $0.service != configuration.service }
                    if !otherServices.isEmpty {
                        Divider()
                        ForEach(otherServices, id: \.provider) { saved in
                            Button("\(saved.service.label) · \(saved.model)") { settings.selectProvider(saved.service) }
                        }
                    }
                    if !settings.models.isEmpty { Divider() }
                    Button("Choisir un service…") { showSettings = true }
                } label: {
                    Text(ready ? "\(configuration.service.label) · \(modelName)" : "Choisir un modèle…")
                        .font(.caption).lineLimit(1)
                }
                .menuStyle(.borderlessButton).fixedSize(horizontal: false, vertical: true)
                .disabled(model.busy)
            }
            Spacer()
            IconButton(title: "Nouvelle conversation", icon: "plus", size: 13) {
                model.newConversation()
                if creationMode && ready { model.beginCreation() }
                prompt = ""; composerFocused = true
            }
                .disabled(model.busy || (model.messages.isEmpty && model.attachments.isEmpty))
            IconButton(title: "Réglages de l’assistant", icon: "settings", size: 13) { showSettings = true }
        }.padding(.horizontal, 16).padding(.vertical, 12)
    }

    private var emptyState: some View {
        VStack(alignment: .leading, spacing: 14) {
            if !ready {
                Text("Choisissez votre assistant.").font(.title3.weight(.semibold))
                Text(creationMode ? "Connectez le service de votre choix pour créer votre scénario." :
                    AIProvider.allCases.contains { $0.isLocal && $0.availableOnThisDevice }
                        ? "Utilisez un modèle sur votre Mac ou connectez un service en ligne." : "Connectez un service en ligne.")
                    .foregroundStyle(.secondary)
                if accounts.busy {
                    HStack(spacing: 8) { ProgressView().controlSize(.small); Text("Connexion au service…").font(.callout).foregroundStyle(.secondary) }
                }
                Button("Choisir un modèle") { showSettings = true }.studioButtons(prominent: true)
            } else {
                Text("Construisons votre scénario.").font(.title3.weight(.semibold))
                Text("Décrivez le public, les objectifs et le temps disponible. Vous pouvez aussi demander une adaptation du scénario ouvert.")
                    .foregroundStyle(.secondary)
                VStack(alignment: .leading, spacing: 8) {
                    documentsSuggestion
                    suggestion("Construire une séance de 55 minutes",
                               "Construis un scénario de 55 minutes pour des élèves de 6e sur [sujet]. Ils devront être capables de [objectif]. Prévois une évaluation formative.")
                    suggestion("Ajouter une évaluation formative",
                               "Ajoute une évaluation formative courte à la fin de chaque moment du scénario ouvert.")
                    suggestion("Clarifier les consignes",
                               "Reformule les consignes pour les élèves afin qu’elles soient plus claires et plus courtes.")
                }
                profileHint
            }
        }.padding(.top, 8)
    }
    /// Rappelle que le contexte permanent évite de répéter matière, niveau et système scolaire.
    private var profileHint: some View {
        let empty = settings.teacherProfile.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
        return Button { showSettings = true } label: {
            Label(empty ? "Enregistrer mon contexte d’enseignement (matière, niveau, système scolaire…)"
                        : "Contexte d’enseignement enregistré · Modifier",
                  systemImage: empty ? "person.text.rectangle" : "checkmark.circle")
                .font(.callout)
        }
        .buttonStyle(.plain).foregroundStyle(empty ? Color.accentColor : Color.secondary)
    }
    /// Prépare la demande et ouvre le choix des fichiers s’il n’y en a pas encore de joints.
    private var documentsSuggestion: some View {
        suggestion("Crée un scénario pédagogique complet à partir de mes documents", Self.documentsPrompt, systemImage: "doc.text") {
            if model.attachments.isEmpty { showAttach = true }
        }
    }
    private func suggestion(_ title: String, _ text: String, systemImage: String? = nil, then: @escaping () -> Void = {}) -> some View {
        Button { prompt = text; composerFocused = true; then() } label: {
            HStack {
                if let systemImage { Image(systemName: systemImage).font(.system(size: 11, weight: .semibold)).foregroundStyle(Color.accentColor) }
                else { FA(name: "wand", size: 11).foregroundStyle(Color.accentColor) }
                Text(title).multilineTextAlignment(.leading); Spacer(minLength: 0)
            }
                .padding(.horizontal, 12).padding(.vertical, 9).frame(maxWidth: .infinity, alignment: .leading)
                .background(Color.primary.opacity(0.05), in: RoundedRectangle(cornerRadius: 12, style: .continuous))
                .contentShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
        }.buttonStyle(.plain).hoverLift()
    }

    private func bubble(_ message: ChatEntry) -> some View {
        let mine = message.role == "Vous"
        return HStack {
            if mine { Spacer(minLength: 40) }
            VStack(alignment: mine ? .trailing : .leading, spacing: 6) {
                if !message.attachments.isEmpty {
                    VStack(alignment: .trailing, spacing: 4) {
                        ForEach(Array(message.attachments.enumerated()), id: \.offset) { _, name in
                            Label(name, systemImage: "doc.text").font(.caption).lineLimit(1).truncationMode(.middle)
                                .padding(.horizontal, 10).padding(.vertical, 6)
                                .background(Color.primary.opacity(0.07), in: Capsule())
                        }
                    }
                }
                Text(message.text).textSelection(.enabled)
                    .padding(.horizontal, 13).padding(.vertical, 9)
                    .foregroundStyle(mine ? Color.white : Color.primary)
                    .background(mine ? AnyShapeStyle(Color.accentColor) : AnyShapeStyle(Color.primary.opacity(0.07)),
                                in: RoundedRectangle(cornerRadius: 17, style: .continuous))
            }
            if !mine { Spacer(minLength: 40) }
        }
    }

    private func attachmentChip(_ document: AssistantDocument) -> some View {
        HStack(spacing: 6) {
            Image(systemName: "doc.text").foregroundStyle(Color.accentColor)
            Text(document.name).lineLimit(1).truncationMode(.middle)
            if document.truncated {
                Image(systemName: "scissors").foregroundStyle(.secondary)
                    .help("Document long : seul le début de son texte sera transmis.")
            }
            Button { model.removeAttachment(document) } label: { Image(systemName: "xmark.circle.fill").foregroundStyle(.secondary) }
                .buttonStyle(.plain).disabled(model.busy)
                .help("Retirer").accessibilityLabel("Retirer \(document.name)")
        }
        .font(.caption).padding(.leading, 9).padding(.trailing, 6).padding(.vertical, 5)
        .background(Color.primary.opacity(0.07), in: Capsule())
    }

    private var footer: some View {
        VStack(alignment: .leading, spacing: 10) {
            if let error = model.error {
                Label { Text(error).textSelection(.enabled) } icon: { Image(systemName: "exclamationmark.triangle.fill") }
                    .font(.callout).foregroundStyle(.red)
                Button("Réglages de l’assistant") { showSettings = true }.font(.callout)
            }
            if let proposal = model.proposal {
                VStack(alignment: .leading, spacing: 10) {
                    Label("Proposition prête : \(proposal.name.isEmpty ? "sans titre" : proposal.name)", systemImage: "checkmark.circle.fill")
                        .font(.callout.weight(.medium)).foregroundStyle(.green)
                    HStack {
                        Button("Aperçu") { showReview = true }.disabled(model.busy).studioButtons(prominent: true)
                        Button("Écarter") { model.discard() }.studioButtons()
                    }
                }.padding(12).frame(maxWidth: .infinity, alignment: .leading)
                    .background(Color.green.opacity(0.10), in: RoundedRectangle(cornerRadius: 14, style: .continuous))
            }
            VStack(alignment: .leading, spacing: 8) {
                if !model.attachments.isEmpty {
                    ScrollView(.horizontal, showsIndicators: false) {
                        HStack(spacing: 6) { ForEach(model.attachments) { attachmentChip($0) } }
                    }
                }
                HStack(alignment: .bottom, spacing: 8) {
                    Button { showAttach = true } label: {
                        Image(systemName: "plus").font(.system(size: 13, weight: .semibold)).foregroundStyle(.secondary)
                            .frame(width: 28, height: 28)
                            .background(Color.primary.opacity(0.07), in: Circle())
                            .contentShape(Circle())
                    }
                    .buttonStyle(.plain).disabled(model.busy)
                    .help("Ajouter des fichiers (PDF, Word, PowerPoint, Excel, texte)").accessibilityLabel("Ajouter des fichiers")
                    TextField(model.attachments.isEmpty ? "Décrivez ce que vous souhaitez…" : "Ajoutez une consigne (facultatif)…",
                              text: $prompt, axis: .vertical)
                        .textFieldStyle(.plain).lineLimit(1...8).focused($composerFocused)
                        .onKeyPress(.return, phases: .down) { press in
                            guard press.modifiers.isEmpty else { return .ignored }
                            if !model.busy { submit() }
                            return .handled
                        }
                        .help("Retour pour envoyer ; Maj+Retour pour insérer une nouvelle ligne.")
                        .disabled(model.busy).padding(.vertical, 5).padding(.leading, 4)
                    Button(action: submit) {
                        Image(systemName: model.busy ? "stop.fill" : "arrow.up").font(.system(size: 12, weight: .bold)).foregroundStyle(.white)
                            .frame(width: 28, height: 28)
                            .background(model.busy || canSend ? Color.accentColor : Color.secondary.opacity(0.4), in: Circle())
                    }
                    .buttonStyle(.plain).keyboardShortcut(.return, modifiers: [.command])
                    .disabled(!model.busy && !canSend)
                    .help(model.busy ? "Arrêter" : "Envoyer (↩ ou ⌘↩)").accessibilityLabel(model.busy ? "Arrêter" : "Envoyer")
                }
            }
            .padding(8).glassSurface(radius: 22)
            .dropDestination(for: URL.self) { urls, _ in
                let files = urls.filter(\.isFileURL)
                guard !model.busy, !files.isEmpty else { return false }
                model.attach(files); composerFocused = true
                return true
            }
            if !ready && !model.messages.isEmpty {
                Button("Connecter un modèle pour envoyer votre demande") { showSettings = true }
                    .buttonStyle(.plain).foregroundStyle(Color.accentColor)
                    .font(.caption).padding(.horizontal, 6)
            }
            if ready {
                if configuration.service == .chatgpt {
                    HStack {
                        Text("Abonnement ChatGPT utilisé").foregroundStyle(.secondary)
                        Spacer()
                        Link("Gérer l’utilisation", destination: ChatGPTProtocol.usageURL)
                    }.font(.caption2).padding(.horizontal, 6)
                }
                let sent = settings.teacherProfile.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
                    ? "Scénario, échanges et documents joints" : "Contexte, scénario, échanges et documents joints"
                Text(configuration.isOnThisMac ? "\(sent) · traités sur ce Mac." : "\(sent) · transmis à \(configuration.service.label).")
                    .font(.caption2).foregroundStyle(.tertiary).padding(.horizontal, 6)
            }
        }.padding(12)
    }

    private func submit() {
        if model.busy { model.cancel(); return }
        guard canSend else { return }
        model.send(trimmedPrompt, scenario: scenario, configuration: configuration)
        prompt = ""
    }
}

struct ProposalReview: View {
    let current: Scenario
    let proposed: Scenario
    let stale: Bool
    let apply: () -> Void
    @Environment(\.dismiss) private var dismiss

    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            VStack(alignment: .leading, spacing: 4) {
                Text("Aperçu de la proposition").font(.title.bold())
                Text("La proposition remplacera le scénario ouvert. Cette opération pourra être annulée.").foregroundStyle(.secondary)
            }
            SplitPair {
                ScenarioSummary(title: "Version actuelle", scenario: current).padding(.trailing, 8)
                ScenarioSummary(title: "Proposition", scenario: proposed).padding(.leading, 8)
            }
            if stale {
                Label("Le scénario a été modifié depuis la demande. Seules les modifications proposées seront appliquées\u{00A0}; les changements faits entre-temps sont conservés.",
                      systemImage: "arrow.triangle.merge").foregroundStyle(.orange)
            }
            HStack {
                Spacer()
                Button("Fermer") { dismiss() }.keyboardShortcut(.cancelAction).studioButtons()
                Button("Appliquer", action: apply).keyboardShortcut(.defaultAction).studioButtons(prominent: true)
            }
        }.padding(24).desktopFrame(minWidth: 860, idealWidth: 1000, minHeight: 650)
    }
}

struct ScenarioSummary: View {
    let title: String
    let scenario: Scenario
    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 18) {
                Text(title.uppercased()).font(.caption.weight(.bold)).tracking(0.6).foregroundStyle(.secondary)
                Text(scenario.name).font(.title2.bold())
                Label("\(scenario.duration.formatted()) min · \(scenario.activityCount) activités", systemImage: "clock").foregroundStyle(.secondary)
                if let text = scenario.meta["description"]?.string, !text.isEmpty { Text(text) }
                ForEach(scenario.moments) { moment in
                    VStack(alignment: .leading, spacing: 12) {
                        Divider()
                        Text(moment["title"]).font(.headline)
                        if !moment["objectives"].isEmpty { Text(moment["objectives"]).foregroundStyle(.secondary) }
                        ForEach(moment.activities) { activity in
                            HStack(alignment: .top, spacing: 10) {
                                TypeBadge(type: activity["type"], size: 22)
                                VStack(alignment: .leading, spacing: 5) {
                                    Text("\(activity["description"]) · \(activity.duration.formatted()) min").fontWeight(.medium)
                                    Text(activity["instructions"])
                                    Text([Pedagogy.label(activity["type"], in: Pedagogy.types),
                                        Pedagogy.label(activity["groupMode"], in: Pedagogy.groups),
                                        Pedagogy.label(activity["teachingMode"], in: Pedagogy.teaching),
                                        Pedagogy.label(activity["evaluationMode"], in: Pedagogy.evaluations)].joined(separator: " · "))
                                        .font(.caption).foregroundStyle(.secondary)
                                }
                            }
                        }
                    }
                }
                DisclosureGroup("Document complet (JSON)") {
                    Text((try? scenario.encoded()).map { String(decoding: $0, as: UTF8.self) } ?? "")
                        .font(.system(.caption, design: .monospaced)).textSelection(.enabled)
                }
                // La feuille donne le focus initial à ce triangle : SwiftUI dessine alors un anneau mal placé.
                .focusEffectDisabled()
            }.padding(16).frame(maxWidth: .infinity, alignment: .leading)
        }.frame(minWidth: 350)
    }
}
