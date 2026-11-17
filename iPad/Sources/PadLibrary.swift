import SwiftUI
import UniformTypeIdentifiers

/// Scénario de la bibliothèque : un fichier JSON du dossier Documents de l’app, visible dans Fichiers.
struct PadLibraryItem: Identifiable, Hashable {
    let url: URL
    let title: String
    let modified: Date
    let minutes: Double
    let moments: Int
    let activities: [Activity]
    let linked: Bool
    var id: URL { url }

    static func == (lhs: Self, rhs: Self) -> Bool { lhs.url == rhs.url && lhs.modified == rhs.modified }
    func hash(into hasher: inout Hasher) { hasher.combine(url) }
}

/// Bibliothèque de l’iPad : l’équivalent du dossier des scénarios du Mac.
/// Les scénarios créés, importés ou tirés d’un modèle y deviennent aussitôt des fichiers JSON enregistrés en continu.
@MainActor
final class PadLibrary: ObservableObject {
    static let shared = PadLibrary()
    @Published private(set) var items: [PadLibraryItem] = []
    /// Scénario ouvert dans l’éditeur.
    @Published private(set) var session: PadSession?
    @Published var message: String?

    var folder: URL? { ScenarioFolder.current }

    func reload() {
        guard let folder else { items = []; return }
        let urls = (try? FileManager.default.contentsOfDirectory(at: folder, includingPropertiesForKeys: [.contentModificationDateKey],
                                                                  options: [.skipsHiddenFiles])) ?? []
        items = urls.filter { $0.pathExtension.lowercased() == "json" }.compactMap { url in
            guard let data = try? Data(contentsOf: url), var scenario = try? Scenario.decode(data) else { return nil }
            let identity = ScenarioDocument.extractIdentity(from: &scenario)
            let modified = (try? url.resourceValues(forKeys: [.contentModificationDateKey]).contentModificationDate) ?? .distantPast
            return PadLibraryItem(url: url, title: scenario.name.isEmpty ? "Sans titre" : scenario.name, modified: modified,
                                  minutes: scenario.duration, moments: scenario.moments.count,
                                  activities: scenario.moments.flatMap(\.activities), linked: identity != nil)
        }
        .sorted { $0.modified > $1.modified }
    }

    // MARK: Ouverture

    func open(_ url: URL) {
        do {
            session?.saveNow()
            session = try PadSession(url: url)
        } catch { message = error.localizedDescription }
    }

    func close() {
        session?.saveNow()
        withAnimation(.snappy) { session = nil }
        reload()
    }

    /// Crée le fichier JSON du scénario dans la bibliothèque, puis l’ouvre.
    func create(_ scenario: Scenario, startWithAI: Bool = false) {
        guard let folder else { message = "La bibliothèque de l’iPad est indisponible."; return }
        do {
            let url = try ScenarioFolder.save(scenario, startWithAI: startWithAI, in: folder)
            reload()
            open(url)
        } catch { message = error.localizedDescription }
    }

    /// Un fichier partagé (JSON, Markdown, Excel, CSV, Word, HTML, LDJ) devient un nouveau scénario JSON de la bibliothèque.
    /// Le fichier d’origine n’est pas modifié.
    func importFile(_ url: URL) {
        let access = url.startAccessingSecurityScopedResource()
        defer { if access { url.stopAccessingSecurityScopedResource() } }
        do {
            // Un JSON déjà rangé dans la bibliothèque s’ouvre tel quel.
            if let folder, url.deletingLastPathComponent().standardizedFileURL == folder.standardizedFileURL,
               url.pathExtension.lowercased() == "json" {
                open(url); return
            }
            var coordinated: Result<Scenario, Error> = .failure(ScenarioError.invalid("Impossible de lire ce fichier."))
            var coordinationError: NSError?
            NSFileCoordinator().coordinate(readingItemAt: url, options: [], error: &coordinationError) { readable in
                coordinated = Result { try ScenarioImporter.read(readable) }
            }
            if let coordinationError { throw coordinationError }
            create(try coordinated.get())
        } catch { message = error.localizedDescription }
    }

    func duplicate(_ item: PadLibraryItem) {
        do {
            var scenario = try Scenario.decode(Data(contentsOf: item.url))
            _ = ScenarioDocument.extractIdentity(from: &scenario)
            _ = ScenarioFolder.extractAssistantStart(from: &scenario)
            _ = ScenarioFolder.extractPendingUpload(from: &scenario)
            scenario.meta["name"] = .string((scenario.name.isEmpty ? "Sans titre" : scenario.name) + " (copie)")
            guard let folder else { return }
            _ = try ScenarioFolder.save(scenario, in: folder)
            reload()
        } catch { message = error.localizedDescription }
    }

    /// Supprime le fichier de l’iPad. Un scénario lié au compte reste sur le site.
    func delete(_ item: PadLibraryItem) {
        if session?.url == item.url { session = nil }
        do {
            try FileManager.default.removeItem(at: item.url)
            reload()
        } catch { message = error.localizedDescription }
    }

    /// Ferme et met de côté les fichiers d’un scénario supprimé sur le site (voir `SiteDeletionCleanup`).
    func removeForSiteDeletion(_ files: Set<URL>, workingFile: URL?) {
        let working = workingFile?.standardizedFileURL
        if let current = session?.url.standardizedFileURL, current == working || files.contains(current) { session = nil }
        guard let folder else { return }
        let bin = folder.appendingPathComponent("Supprimés sur le site", isDirectory: true)
        for url in files where url != working {
            try? FileManager.default.createDirectory(at: bin, withIntermediateDirectories: true)
            var target = bin.appendingPathComponent(url.lastPathComponent)
            var number = 2
            while FileManager.default.fileExists(atPath: target.path) {
                target = bin.appendingPathComponent(url.deletingPathExtension().lastPathComponent + " \(number).json"); number += 1
            }
            try? FileManager.default.moveItem(at: url, to: target)
        }
        reload()
    }
}

/// Scénario ouvert : chaque modification est enregistrée peu après la dernière saisie, comme l’enregistrement
/// automatique du Mac. Le fichier reste lisible par Fichiers et par les autres apps pendant l’édition.
@MainActor
final class PadSession: ObservableObject, Identifiable {
    let url: URL
    @Published var document: ScenarioDocument {
        didSet { if document.scenario != oldValue.scenario || document.syncIdentity != oldValue.syncIdentity
                    || document.pendingUpload != oldValue.pendingUpload { scheduleSave() } }
    }
    @Published private(set) var saveError: String?
    private var pending: Task<Void, Never>?
    nonisolated var id: URL { url }

    init(url: URL) throws {
        self.url = url
        var data = Data()
        var readError: Error?
        var coordinationError: NSError?
        NSFileCoordinator().coordinate(readingItemAt: url, options: [], error: &coordinationError) { readable in
            do { data = try Data(contentsOf: readable) } catch { readError = error }
        }
        if let error = coordinationError ?? readError { throw error }
        document = try ScenarioDocument(data: data, filename: url.lastPathComponent)
    }

    private func scheduleSave() {
        pending?.cancel()
        pending = Task { [weak self] in
            try? await Task.sleep(for: .milliseconds(800))
            guard !Task.isCancelled else { return }
            self?.saveNow()
        }
    }

    func saveNow() {
        pending?.cancel(); pending = nil
        do {
            let data = try ScenarioDocument.data(document.scenario, identity: document.syncIdentity,
                                                 pendingUpload: document.pendingUpload && document.syncIdentity == nil)
            var writeError: Error?
            var coordinationError: NSError?
            NSFileCoordinator().coordinate(writingItemAt: url, options: .forReplacing, error: &coordinationError) { writable in
                do { try data.write(to: writable, options: .atomic) } catch { writeError = error }
            }
            if let error = coordinationError ?? writeError { throw error }
            saveError = nil
        } catch { saveError = error.localizedDescription }
    }
}

// MARK: - Compte : ouverture et suppression des scénarios du site

@MainActor
enum SiteDocumentOpener {
    static func open(_ id: UUID, workspace: SiteSyncWorkspace) async throws {
        let file = try workspace.workingFile(id)
        PadPresenter.dismissAll()
        PadLibrary.shared.open(file)
    }
}

/// Un scénario supprimé sur le site disparaît aussi de l’iPad : son éditeur se ferme et ses fichiers
/// de la bibliothèque rejoignent le dossier « Supprimés sur le site », d’où ils restent récupérables.
@MainActor
enum SiteDeletionCleanup {
    static func apply(_ identity: SyncDocumentIdentity, workingFile: URL?) {
        var files = Set((ScenarioFolder.current.map { ScenarioFolder.files(linkedTo: identity, in: $0) } ?? [])
            .map(\.standardizedFileURL))
        if let open = PadLibrary.shared.session?.url, ScenarioFolder.identity(of: open) == identity {
            files.insert(open.standardizedFileURL)
        }
        PadLibrary.shared.removeForSiteDeletion(files, workingFile: workingFile)
    }
}
