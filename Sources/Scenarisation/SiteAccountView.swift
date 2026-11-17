import SwiftUI
import AppKit

struct DocumentSyncLabel: View {
    let identity: SyncDocumentIdentity?
    @ObservedObject var workspace: SiteSyncWorkspace
    var body: some View {
        let record = identity.flatMap { $0.accountKey == workspace.account.key ? workspace.record($0.id) : nil }
        Label(record?.status.label ?? "Synchroniser", systemImage: record?.status.symbol ?? "icloud")
    }
}

/// Checkpoints every edit. Incoming data applies only when the open editor still matches the prior checkpoint.
struct DocumentSyncObserver: View {
    @Binding var document: ScenarioDocument
    @Binding var checkpoint: Scenario
    @ObservedObject var workspace: SiteSyncWorkspace
    let onError: (String) -> Void
    var body: some View {
        Color.clear.frame(width: 0, height: 0)
            .onAppear { stage() }
            .onChange(of: document.scenario) { _, _ in stage() }
            // Chaque modification part sur le site quelques secondes après la dernière frappe ;
            // à l’ouverture, le même envoi récupère d’abord les changements faits sur le site.
            .task(id: document.scenario) {
                guard let identity = document.syncIdentity, identity.accountKey == workspace.account.key,
                      workspace.record(identity.id) != nil else { return }
                try? await Task.sleep(for: .seconds(3))
                guard !Task.isCancelled, SiteAccountModel.shared.networkAvailable else { return }
                // Un envoi n’est jamais interrompu à mi-chemin : il s’exécute hors de cette tâche annulable.
                Task { await workspace.synchronizeAutomatically(identity.id) }
            }
            .onChange(of: workspace.records) { previous, current in
                guard let identity = document.syncIdentity, identity.accountKey == workspace.account.key,
                      let before = previous.first(where: { $0.id == identity.id }),
                      let after = current.first(where: { $0.id == identity.id }),
                      before.local == document.scenario, after.local != document.scenario else { return }
                checkpoint = after.local; document.scenario = after.local
            }
    }
    private func stage() {
        guard let identity = document.syncIdentity, identity.accountKey == workspace.account.key,
              workspace.record(identity.id) != nil else { return }
        do {
            try workspace.stage(identity, scenario: document.scenario, previous: checkpoint)
            checkpoint = document.scenario
        } catch { onError(error.localizedDescription) }
    }
}

struct SiteAccountView: View {
    @ObservedObject var model: SiteAccountModel = .shared

    var body: some View {
        Group {
            if let workspace = model.workspace {
                SiteLibraryView(workspace: workspace, model: model)
            } else {
                ContentUnavailableView {
                    Label("Retrouver ses scénarios sur Mac", systemImage: "icloud")
                } description: {
                    Text("Connectez votre compte scenarisation.eu pour retrouver vos scénarios et les garder sur ce Mac.")
                } actions: {
                    SiteConnectionButton(model: model).controlSize(.large)
                }
                .toolbar { ToolbarItem(placement: .primaryAction) { SiteAccountMenu(model: model) } }
            }
        }
        .frame(minWidth: 640, minHeight: 500)
        .onAppear {
            // Avec la navigation au clavier, le premier bouton de la liste recevrait d’office le cadre de focus.
            Task { @MainActor in
                try? await Task.sleep(for: .milliseconds(250))
                let window = NSApp.windows.first { $0.identifier?.rawValue.hasPrefix("site-account") == true } ?? NSApp.keyWindow
                window?.makeFirstResponder(nil)
            }
        }
        .safeAreaInset(edge: .bottom, spacing: 0) {
            if let message = model.auth.storageMessage {
                Label(message, systemImage: "exclamationmark.triangle").font(.callout).foregroundStyle(.secondary)
                    .frame(maxWidth: .infinity, alignment: .leading).padding(.horizontal, 20).padding(.vertical, 10)
                    .background(.bar)
            }
        }
        .alert("Mon compte", isPresented: Binding(get: { model.message != nil }, set: { if !$0 { model.message = nil } })) {
            Button("Fermer") { model.message = nil }
        } message: { Text(model.message ?? "") }
    }
}

private struct SiteConnectionButton: View {
    @ObservedObject var model: SiteAccountModel
    var body: some View {
        HStack(spacing: 12) {
            if model.action == .connecting {
                ProgressView().controlSize(.small)
                Text("Connexion dans le navigateur…").font(.callout).foregroundStyle(.secondary)
                Button("Annuler") { model.cancelConnection() }
            } else if model.action == .disconnecting {
                ProgressView().controlSize(.small)
                Text("Déconnexion…").foregroundStyle(.secondary)
            } else {
                Button(model.user == nil ? "Se connecter à Scénarisation" : "Reconnecter mon compte") { model.connect() }
                    .studioButtons(prominent: true).disabled(model.busy)
            }
        }
    }
}

/// Menu « Compte » de la barre d’outils : les actions suivent l’état de la session.
private struct SiteAccountMenu: View {
    @ObservedObject var model: SiteAccountModel
    var locked = false
    var body: some View {
        let unavailable = model.busy || locked
        Menu {
            Link("Ouvrir scenarisation.eu", destination: URL(string: "https://scenarisation.eu/profile.php")!)
            Divider()
            if model.workspace != nil && model.auth.connected {
                Button("Se déconnecter") { model.disconnect() }.disabled(unavailable)
            } else {
                Button(model.user == nil ? "Se connecter à Scénarisation" : "Reconnecter mon compte") { model.connect() }
                    .disabled(unavailable)
            }
        } label: { Label("Compte", systemImage: "person.crop.circle") }
            .help("Compte")
    }
}

private struct SiteLibraryRefreshID: Hashable {
    let workspace: ObjectIdentifier
    let available: Bool
}

/// Pastille d’une ligne de la bibliothèque, dans l’esprit des Réglages de macOS.
private struct SiteRowGlyph: View {
    let symbol: String
    let tint: Color
    var body: some View {
        Image(systemName: symbol).font(.system(size: 14, weight: .medium)).foregroundStyle(.white)
            .frame(width: 30, height: 30)
            .background(LinearGradient(colors: [tint, tint.opacity(0.78)], startPoint: .top, endPoint: .bottom),
                        in: RoundedRectangle(cornerRadius: 8, style: .continuous))
            .accessibilityHidden(true)
    }
}

private struct SiteLibraryView: View {
    @ObservedObject var workspace: SiteSyncWorkspace
    @ObservedObject var model: SiteAccountModel
    @State private var search = ""
    /// Identifiant distant du scénario en cours de téléchargement.
    @State private var opening: Int?
    @State private var examining: UUID?
    @State private var removing: SiteSyncRecord?
    @State private var removingRemote: SiteDesignSummary?
    @State private var deleting: Set<String> = []

    private var name: String { model.user?.username ?? workspace.account.label }
    private var locals: [SiteSyncRecord] {
        workspace.records.filter { matches($0.local.name) }
    }
    private var remotes: [SiteDesignSummary] {
        workspace.remoteDesigns.filter { summary in
            !workspace.records.contains { $0.remoteID == summary.id } && matches(summary.title)
        }
    }
    private func matches(_ title: String) -> Bool { search.isEmpty || title.localizedStandardContains(search) }

    var body: some View {
        Form {
            Section { account }
            Section("Sur ce Mac") {
                ForEach(locals) { localRow($0) }
                if locals.isEmpty {
                    Text(!search.isEmpty ? "Aucun scénario sur ce Mac ne correspond à « \(search) »."
                         : "Les scénarios ouverts depuis votre compte resteront disponibles ici.")
                        .foregroundStyle(.secondary)
                }
            }
            Section("Sur le site") {
                ForEach(remotes) { remoteRow($0) }
                if remotes.isEmpty && !workspace.refreshing {
                    Text(!search.isEmpty && !workspace.remoteDesigns.isEmpty ? "Aucun scénario du site ne correspond à « \(search) »."
                         : !model.networkAvailable ? "Reconnectez votre compte pour afficher les scénarios du site."
                         : "Aucun autre scénario à télécharger. Actualisez pour vérifier votre compte.")
                        .foregroundStyle(.secondary)
                }
            }
        }
        .formStyle(.grouped)
        .searchable(text: $search, prompt: "Rechercher un scénario")
        .toolbar {
            ToolbarItem(placement: .primaryAction) {
                Button {
                    Task { do { try await workspace.refresh() } catch { model.message = error.localizedDescription } }
                } label: {
                    if workspace.refreshing { ProgressView().controlSize(.small) }
                    else { Label("Actualiser", systemImage: "arrow.clockwise") }
                }
                .help("Actualiser la liste des scénarios").keyboardShortcut("r")
                .disabled(workspace.refreshing || !model.networkAvailable)
            }
            ToolbarItem(placement: .primaryAction) { SiteAccountMenu(model: model, locked: opening != nil) }
        }
        .task(id: SiteLibraryRefreshID(workspace: ObjectIdentifier(workspace), available: model.networkAvailable)) {
            guard model.networkAvailable else { return }
            do { try await workspace.refresh() }
            catch is CancellationError {}
            catch { model.message = error.localizedDescription }
        }
        .sheet(isPresented: Binding(get: { examining != nil }, set: { if !$0 { examining = nil } })) {
            if let id = examining { SiteConflictView(workspace: workspace, recordID: id) }
        }
        .confirmationDialog("Supprimer ce scénario ?", isPresented: Binding(
            get: { removing != nil }, set: { if !$0 { removing = nil } }), presenting: removing) { record in
                Button("Supprimer", role: .destructive) { remove(record) }
            } message: { record in Text(removalWarning(record)) }
        .confirmationDialog("Supprimer ce scénario ?", isPresented: Binding(
            get: { removingRemote != nil }, set: { if !$0 { removingRemote = nil } }), presenting: removingRemote) { summary in
                Button("Supprimer", role: .destructive) { removeRemote(summary) }
            } message: { _ in Text("Il sera définitivement supprimé de votre compte scenarisation.eu.") }
    }

    private func removalWarning(_ record: SiteSyncRecord) -> String {
        if record.remoteID == nil || record.remoteMissing { return "Il sera définitivement supprimé de ce Mac." }
        return "Il sera définitivement supprimé de ce Mac et de votre compte scenarisation.eu."
    }
    private func remove(_ record: SiteSyncRecord) {
        let key = record.id.uuidString
        deleting.insert(key)
        Task {
            defer { deleting.remove(key) }
            do {
                let url = try workspace.workingFileURL(record.id)
                try await workspace.delete(record.id)
                // Une fenêtre encore ouverte n’aurait plus de copie à tenir à jour.
                NSDocumentController.shared.documents.first { $0.fileURL == url }?.close()
            } catch { model.message = error.localizedDescription }
        }
    }
    private func removeRemote(_ summary: SiteDesignSummary) {
        let key = "remote-\(summary.id)"
        deleting.insert(key)
        Task {
            defer { deleting.remove(key) }
            do { try await workspace.deleteRemote(summary) } catch { model.message = error.localizedDescription }
        }
    }

    // MARK: Compte

    private var account: some View {
        HStack(spacing: 14) {
            Text(name.prefix(1).uppercased()).font(.system(size: 21, weight: .semibold, design: .rounded))
                .foregroundStyle(.white).frame(width: 46, height: 46)
            .background(LinearGradient(colors: [Color.accentColor, Color.accentColor.opacity(0.72)],
                                       startPoint: .top, endPoint: .bottom), in: Circle())
            .accessibilityHidden(true)
            VStack(alignment: .leading, spacing: 2) {
                Text(name).font(.title3.weight(.semibold)).lineLimit(1)
                Text(model.user?.email ?? "scenarisation.eu").font(.callout).foregroundStyle(.secondary).lineLimit(1)
                if !model.auth.connected {
                    Text("Session déconnectée · copies locales disponibles").font(.caption).foregroundStyle(.secondary)
                }
            }
            Spacer(minLength: 12)
            if model.auth.connected && model.action == .idle {
                Label { Text("Connecté") } icon: { Image(systemName: "checkmark.circle.fill").foregroundStyle(.green) }
                    .font(.callout).foregroundStyle(.secondary)
            } else { SiteConnectionButton(model: model) }
        }.padding(.vertical, 4)
    }

    // MARK: Lignes

    private func localRow(_ record: SiteSyncRecord) -> some View {
        HStack(spacing: 12) {
            SiteRowGlyph(symbol: "doc.text", tint: .accentColor)
            VStack(alignment: .leading, spacing: 2) {
                Text(record.local.name.isEmpty ? "Sans titre" : record.local.name).fontWeight(.medium).lineLimit(1)
                HStack(spacing: 4) {
                    Image(systemName: record.status.symbol).foregroundStyle(tint(record.status)).accessibilityHidden(true)
                    Text(record.status.label)
                }.font(.caption).foregroundStyle(.secondary)
            }
            Spacer(minLength: 12)
            if workspace.syncing.contains(record.id) { ProgressView().controlSize(.small) }
            else if record.status == .conflict {
                Button("Examiner") { examining = record.id }
            } else if record.status == .missing {
                Button("Conserver une copie") {
                    do { open(try workspace.copyDeleted(record.id).id) }
                    catch { model.message = error.localizedDescription }
                }
            } else {
                Button { synchronize(record.id) } label: {
                    Label("Synchroniser", systemImage: "arrow.triangle.2.circlepath")
                }.labelStyle(.iconOnly).buttonStyle(.borderless).help("Synchroniser ce scénario").disabled(!model.networkAvailable)
            }
            Button { open(record.id) } label: { Label("Ouvrir", systemImage: "arrow.up.forward.square") }
                .labelStyle(.iconOnly).buttonStyle(.borderless).help("Ouvrir ce scénario")
            Button(role: .destructive) { removing = record } label: { Label("Supprimer", systemImage: "trash") }
                .labelStyle(.iconOnly).buttonStyle(.borderless).help("Supprimer ce scénario")
                .disabled(workspace.syncing.contains(record.id) || deleting.contains(record.id.uuidString)
                          || (record.remoteID != nil && !record.remoteMissing && !model.networkAvailable))
        }
        .padding(.vertical, 2).contentShape(Rectangle())
        .onTapGesture(count: 2) { open(record.id) }
        .contextMenu {
            Button("Ouvrir") { open(record.id) }
            Divider()
            Button("Supprimer…", role: .destructive) { removing = record }
                .disabled(workspace.syncing.contains(record.id) || (record.remoteID != nil && !record.remoteMissing && !model.networkAvailable))
        }
    }

    private func remoteRow(_ summary: SiteDesignSummary) -> some View {
        HStack(spacing: 12) {
            SiteRowGlyph(symbol: "icloud", tint: Color(nsColor: .systemGray))
            VStack(alignment: .leading, spacing: 2) {
                Text(summary.title.isEmpty ? "Sans titre" : summary.title).fontWeight(.medium).lineLimit(1)
                Text(SiteLibraryDate.caption(summary)).font(.caption).foregroundStyle(.secondary)
            }
            Spacer(minLength: 12)
            if opening == summary.id { ProgressView().controlSize(.small) }
            Button { download(summary.id) } label: { Label("Ouvrir", systemImage: "icloud.and.arrow.down") }
                .labelStyle(.iconOnly).buttonStyle(.borderless).help("Ouvrir sur ce Mac")
                .disabled(opening != nil || !model.networkAvailable)
            Button(role: .destructive) { removingRemote = summary } label: { Label("Supprimer", systemImage: "trash") }
                .labelStyle(.iconOnly).buttonStyle(.borderless).help("Supprimer ce scénario de votre compte")
                .disabled(opening == summary.id || deleting.contains("remote-\(summary.id)") || !model.networkAvailable)
        }
        .padding(.vertical, 2).contentShape(Rectangle())
        .onTapGesture(count: 2) { if opening == nil && model.networkAvailable { download(summary.id) } }
    }

    private func tint(_ status: SiteSyncStatus) -> Color {
        switch status {
        case .synced: .green
        case .conflict: .orange
        case .missing: .red
        case .local, .pending: .secondary
        }
    }

    private func open(_ id: UUID) {
        Task { do { try await SiteDocumentOpener.open(id, workspace: workspace) } catch { model.message = error.localizedDescription } }
    }
    private func download(_ remoteID: Int) {
        opening = remoteID
        Task {
            defer { opening = nil }
            do {
                let record = try await workspace.downloadToMac(remoteID)
                try await SiteDocumentOpener.open(record.id, workspace: workspace)
            } catch { model.message = error.localizedDescription }
        }
    }
    private func synchronize(_ id: UUID) {
        Task {
            do {
                try await workspace.synchronize(id)
                if workspace.record(id)?.status == .conflict { examining = id }
            } catch { model.message = error.localizedDescription }
        }
    }
}

/// Date de dernière modification renvoyée par le site, affichée lorsqu’elle est lisible.
enum SiteLibraryDate {
    static func parse(_ text: String) -> Date? {
        let iso = ISO8601DateFormatter()
        if let date = iso.date(from: text) { return date }
        iso.formatOptions.insert(.withFractionalSeconds)
        if let date = iso.date(from: text) { return date }
        let plain = DateFormatter()
        plain.locale = Locale(identifier: "en_US_POSIX"); plain.dateFormat = "yyyy-MM-dd HH:mm:ss"
        return plain.date(from: text)
    }
    static func caption(_ summary: SiteDesignSummary) -> String {
        guard let date = parse(summary.updatedAt) else { return "Révision \(summary.revision)" }
        return "Modifié le \(date.formatted(date: .abbreviated, time: .omitted))"
    }
}

@MainActor
enum SiteDocumentOpener {
    static func open(_ id: UUID, workspace: SiteSyncWorkspace) async throws {
        let url = try workspace.workingFileURL(id)
        if let document = NSDocumentController.shared.documents.first(where: { $0.fileURL == url }) {
            document.showWindows()
            document.windowControllers.first?.window?.makeKeyAndOrderFront(nil)
            return
        }
        let file = try workspace.workingFile(id)
        _ = try await NSDocumentController.shared.openDocument(withContentsOf: file, display: true)
    }
}

struct ScenarioSyncSheet: View {
    @Binding var document: ScenarioDocument
    @ObservedObject var workspace: SiteSyncWorkspace
    @ObservedObject var accountModel: SiteAccountModel = .shared
    @Environment(\.dismiss) private var dismiss
    @Environment(\.openWindow) private var openWindow
    @State private var message: String?
    @State private var busy = false

    private var record: SiteSyncRecord? { document.syncIdentity.flatMap { workspace.record($0.id) } }

    var body: some View {
        VStack(alignment: .leading, spacing: 20) {
            HStack {
                Text("Synchroniser le scénario").font(.title2.bold())
                Spacer()
                Button("Fermer") { dismiss() }.keyboardShortcut(.cancelAction)
            }
            Label(workspace.account.label, systemImage: "person.crop.circle").foregroundStyle(.secondary)
            Text(document.scenario.name.isEmpty ? "Sans titre" : document.scenario.name).font(.headline)
            if let record {
                Label(record.status.label, systemImage: record.status.symbol)
                if record.remoteConflict != nil {
                    SiteConflictContent(record: record) { duplicate in
                        // Keep the original file's identity stable; the independent copy opens in its own file.
                        if let original = workspace.record(record.id) { document.scenario = original.local }
                        Task {
                            do { try await SiteDocumentOpener.open(duplicate.id, workspace: workspace); dismiss() }
                            catch { message = error.localizedDescription }
                        }
                    }.environmentObject(workspace)
                } else if record.remoteMissing {
                    Text("Votre travail reste sur ce Mac. Vous pouvez en faire une nouvelle copie indépendante.").foregroundStyle(.secondary)
                    Button("Conserver une copie") {
                        do {
                            let duplicate = try workspace.copyDeleted(record.id)
                            Task {
                                do { try await SiteDocumentOpener.open(duplicate.id, workspace: workspace); dismiss() }
                                catch { message = error.localizedDescription }
                            }
                        } catch { message = error.localizedDescription }
                    }
                } else {
                    if let date = record.lastSyncedAt {
                        Text("Dernière synchronisation : \(date.formatted(date: .abbreviated, time: .shortened))")
                            .font(.caption).foregroundStyle(.secondary)
                    }
                    syncButton
                }
            } else {
                Text("Ce scénario sera enregistré dans votre compte sur scenarisation.eu.").foregroundStyle(.secondary)
                syncButton
            }
            if !accountModel.networkAvailable {
                HStack {
                    Text("Reconnectez votre compte pour synchroniser.").font(.callout).foregroundStyle(.secondary)
                    Spacer()
                    Button("Mon compte") { dismiss(); openWindow(id: "site-account") }
                }
            }
            if let message { Text(message).font(.callout).foregroundStyle(.red).textSelection(.enabled) }
        }
        .padding(28).frame(minWidth: 580, maxWidth: 900).disabled(busy)
    }

    private var syncButton: some View {
        HStack {
            Spacer()
            if busy { ProgressView().controlSize(.small) }
            Button("Synchroniser") {
                busy = true; message = nil
                Task {
                    defer { busy = false }
                    do {
                        if document.syncIdentity.flatMap({ workspace.record($0.id) }) == nil {
                            document.syncIdentity = try workspace.addLocal(document.scenario)
                        }
                        guard let identity = document.syncIdentity else { return }
                        try workspace.stage(identity, scenario: document.scenario, previous: document.scenario)
                        let before = document.scenario
                        try await workspace.synchronize(identity.id)
                        if document.syncIdentity == identity, document.scenario == before,
                           let updated = workspace.record(identity.id), updated.remoteConflict == nil {
                            document.scenario = updated.local
                        }
                    } catch { message = error.localizedDescription }
                }
            }.studioButtons(prominent: true).keyboardShortcut(.defaultAction)
                .disabled(!accountModel.networkAvailable || record.map { workspace.syncing.contains($0.id) } == true)
        }
    }
}

private struct SiteConflictContent: View {
    let record: SiteSyncRecord
    let onKept: (SiteSyncRecord) -> Void
    @EnvironmentObject private var workspace: SiteSyncWorkspace
    @State private var error: String?
    @State private var showJSON = false

    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            Text("Le scénario a changé sur le Mac et sur le site. Conservez les deux versions pour poursuivre votre travail.")
                .foregroundStyle(.secondary)
            DisclosureGroup("Examiner les versions", isExpanded: $showJSON) {
                HStack(alignment: .top, spacing: 16) {
                    version("Version du Mac", record.local)
                    if let remote = record.remoteConflict { version("Version du site · révision \(remote.revision)", remote.document) }
                }.padding(.top, 8)
            }
            Button("Conserver les deux versions") {
                do { onKept(try workspace.keepBoth(record.id)) }
                catch { self.error = error.localizedDescription }
            }.studioButtons(prominent: true)
            Text("Votre version devient une copie Mac indépendante, en attente de synchronisation.")
                .font(.caption).foregroundStyle(.secondary)
            if let error { Text(error).foregroundStyle(.red) }
        }
    }
    private func version(_ title: String, _ scenario: Scenario) -> some View {
        VStack(alignment: .leading, spacing: 8) {
            Text(title).font(.headline)
            ScrollView([.horizontal, .vertical]) {
                Text(String(decoding: (try? scenario.encoded()) ?? Data(), as: UTF8.self))
                    .font(.system(.caption, design: .monospaced)).textSelection(.enabled).padding(12)
            }.frame(height: 240).background(StudioStyle.field, in: RoundedRectangle(cornerRadius: 8))
        }.frame(maxWidth: .infinity)
    }
}

private struct SiteConflictView: View {
    @ObservedObject var workspace: SiteSyncWorkspace
    let recordID: UUID
    @Environment(\.dismiss) private var dismiss
    var body: some View {
        VStack(alignment: .leading, spacing: 20) {
            HStack {
                Text("Examiner le conflit").font(.title2.bold())
                Spacer()
                Button("Fermer") { dismiss() }.keyboardShortcut(.cancelAction)
            }
            if let record = workspace.record(recordID), record.remoteConflict != nil {
                SiteConflictContent(record: record) { _ in dismiss() }.environmentObject(workspace)
            }
        }.padding(28).frame(minWidth: 640, maxWidth: 900)
    }
}
