import SwiftUI

private struct FieldAIScenarioKey: EnvironmentKey { static let defaultValue: Scenario? = nil }
private struct FieldAIMomentKey: EnvironmentKey { static let defaultValue: Moment? = nil }
private struct FieldAIActivityKey: EnvironmentKey { static let defaultValue: Activity? = nil }
extension EnvironmentValues {
    var fieldAIScenario: Scenario? {
        get { self[FieldAIScenarioKey.self] }
        set { self[FieldAIScenarioKey.self] = newValue }
    }
    var fieldAIMoment: Moment? {
        get { self[FieldAIMomentKey.self] }
        set { self[FieldAIMomentKey.self] = newValue }
    }
    var fieldAIActivity: Activity? {
        get { self[FieldAIActivityKey.self] }
        set { self[FieldAIActivityKey.self] = newValue }
    }
}

struct FieldAIButton: View {
    let label: String
    @Binding var text: String
    let purpose: FieldAIPurpose
    @Environment(\.fieldAIScenario) private var scenario
    @Environment(\.fieldAIMoment) private var moment
    @Environment(\.fieldAIActivity) private var activity
    @State private var presented = false

    var body: some View {
        Button("Aide IA") { presented = true }
            .buttonStyle(.borderless).font(.caption).foregroundStyle(.secondary)
            .help("Rédiger ou améliorer : \(label)").accessibilityLabel("Aide IA : \(label)")
            .sheet(isPresented: $presented) {
                FieldAssistantSheet(label: label, text: $text, purpose: purpose,
                    context: FieldAIContext(scenario: scenario, moment: moment, activity: activity))
            }
    }
}

struct FieldAssistantSheet: View {
    let label: String
    @Binding var text: String
    let purpose: FieldAIPurpose
    let context: FieldAIContext
    @StateObject private var model = FieldAssistantModel()
    @ObservedObject private var settings = AISettingsStore.shared
    @ObservedObject private var chatgpt = ChatGPTAuth.shared
    @ObservedObject private var accounts = AIAccountConnections.shared
    @State private var instruction = ""
    @State private var showSettings = false
    @FocusState private var instructionFocused: Bool
    @Environment(\.dismiss) private var dismiss

    private var configuration: AIConfiguration { settings.configuration }
    private var ready: Bool {
        guard configuration.isReady else { return false }
        if configuration.service == .chatgpt { return !chatgpt.signingIn && chatgpt.activeAccount?.canUsePlan == true }
        if configuration.service == .claude { return settings.claudeEnabled && accounts.claudeAccount != nil }
        if configuration.service.usesACP { return settings.subscriptionServices.contains(configuration.service) && accounts.connectedSubscriptions.contains(configuration.service) }
        return true
    }
    private var stale: Bool { model.baseline != nil && model.baseline != text }
    private func request(_ action: FieldAIAction) {
        guard ready else { return }
        model.request(FieldAIRequest(label: label, purpose: purpose, action: action, instruction: instruction,
            original: text, context: context), configuration: configuration)
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 18) {
            VStack(alignment: .leading, spacing: 4) {
                Text("Aide IA").font(.title2.bold())
                Text(label).font(.subheadline).foregroundStyle(.secondary)
            }
            if ready {
                Text("\(configuration.service.label) · \(settings.models.first { $0.id == configuration.model }?.name ?? configuration.model)")
                    .font(.caption).foregroundStyle(.secondary).lineLimit(2)
            } else {
                HStack {
                    Text("Choisissez un modèle pour commencer.").font(.callout).foregroundStyle(.secondary)
                    Spacer()
                    Button("Configurer l’IA") { showSettings = true }.studioButtons()
                }
            }
            ScrollView {
                VStack(alignment: .leading, spacing: 16) {
                    TextField(purpose.placeholder, text: $instruction, axis: .vertical)
                        .textFieldStyle(.plain).lineLimit(2...5).focused($instructionFocused)
                        .padding(12).background(StudioStyle.field, in: RoundedRectangle(cornerRadius: 10))
                        .accessibilityLabel("Votre demande")
                        .disabled(model.busy)
                        .onKeyPress(.return, phases: .down) { press in
                            guard press.modifiers.isEmpty else { return .ignored }
                            if !model.busy { request(.write) }
                            return .handled
                        }
                    HStack(spacing: 10) {
                        Button("Générer") { request(.write) }
                            .studioButtons(prominent: true)
                            .disabled(!ready || model.busy || instruction.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty)
                        if !text.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty {
                            ForEach([FieldAIAction.rephrase, .shorten, .clarify]) { action in
                                Button(action.label) { request(action) }.buttonStyle(.borderless)
                                    .disabled(!ready || model.busy)
                            }
                        }
                    }
                    if model.busy {
                        HStack(spacing: 10) {
                            ProgressView().controlSize(.small)
                            Text("Préparation de la proposition…").font(.callout).foregroundStyle(.secondary)
                            Spacer()
                            Button("Arrêter") { model.reset() }.buttonStyle(.borderless)
                        }
                    }
                    if let error = model.error { Text(error).font(.callout).foregroundStyle(.red).textSelection(.enabled) }
                    if !model.proposal.isEmpty {
                        VStack(alignment: .leading, spacing: 8) {
                            Text("Proposition").font(.headline)
                            StudioField(label: "Texte proposé", text: $model.proposal, multiline: true)
                            if stale {
                                Text("Le champ a changé. Générez une nouvelle proposition.").font(.caption).foregroundStyle(.orange)
                            }
                        }
                    }
                }.padding(.vertical, 2)
            }.frame(minHeight: 190, maxHeight: 400)
            Text(configuration.isOnThisMac ? "Texte et contexte pédagogique · traités sur ce Mac." :
                "Texte et contexte pédagogique · transmis à \(configuration.service.label).")
                .font(.caption2).foregroundStyle(.secondary)
            HStack {
                Button("Réglages") { showSettings = true }.buttonStyle(.borderless)
                Spacer()
                Button("Annuler") { dismiss() }.keyboardShortcut(.cancelAction).studioButtons()
                Button(text.isEmpty ? "Insérer" : "Remplacer") {
                    if model.apply(to: text, replace: { text = $0 }) { dismiss() }
                }.studioButtons(prominent: true).disabled(!ready || !model.canApply(to: text))
            }
        }
        .padding(24).frame(width: 540)
        .sheet(isPresented: $showSettings) {
            VStack(spacing: 0) {
                AISettingsView()
                HStack { Spacer(); Button("Terminé") { showSettings = false }.studioButtons() }
                    .padding([.horizontal, .bottom], 20)
            }
        }
        .onAppear { instructionFocused = ready }
        .task(id: configuration.service) {
            if configuration.service.isAccountConnection { accounts.prepare(settings) }
        }
        .onChange(of: instruction) { _, _ in model.reset() }
        .onChange(of: configuration) { _, _ in model.reset() }
        .onChange(of: purpose) { _, _ in model.reset() }
        .onChange(of: context.moment?.id) { _, _ in model.reset() }
        .onChange(of: context.activity?.id) { _, _ in model.reset() }
        .onChange(of: chatgpt.activeAccount) { previous, current in
            if configuration.service == .chatgpt && (previous?.id != current?.id || current?.canUsePlan != true) { model.reset() }
        }
        .onChange(of: ready) { _, value in
            if !value { model.reset() }
            else { instructionFocused = true }
        }
        .onDisappear { model.reset() }
    }
}
