import SwiftUI
#if os(macOS)
import AppKit
#endif

/// Dossier du Finder où les scénarios créés ou importés sont enregistrés d’office, en JSON.
enum ScenarioFolder {
    static let defaultsKey = "scenarioFolderPath"
    /// Marque de fichier, jamais conservée dans le scénario : le document s’ouvre sur l’assistant.
    static let assistantMarker = "_scenarisationStart"
    /// Marque de fichier, jamais conservée dans le scénario : le scénario attend d’être envoyé au compte.
    static let uploadMarker = "_scenarisationUpload"

#if os(macOS)
    static var current: URL? {
        guard let path = UserDefaults.standard.string(forKey: defaultsKey) else { return nil }
        var isDirectory: ObjCBool = false
        guard FileManager.default.fileExists(atPath: path, isDirectory: &isDirectory), isDirectory.boolValue else { return nil }
        return URL(fileURLWithPath: path, isDirectory: true)
    }

    @MainActor @discardableResult
    static func choose() -> URL? {
        let panel = NSOpenPanel()
        panel.title = "Dossier des scénarios"
        panel.message = "Choisissez le dossier où vos scénarios seront enregistrés automatiquement."
        panel.prompt = "Choisir"
        panel.canChooseDirectories = true; panel.canChooseFiles = false; panel.canCreateDirectories = true
        panel.directoryURL = current ?? FileManager.default.urls(for: .documentDirectory, in: .userDomainMask).first
        guard panel.runModal() == .OK, let url = panel.url else { return nil }
        UserDefaults.standard.set(url.path, forKey: defaultsKey)
        return url
    }
#else
    /// Sur iPad, les scénarios vivent dans le dossier Documents de l’app, visible dans Fichiers.
    static var current: URL? {
        let url = FileManager.default.urls(for: .documentDirectory, in: .userDomainMask).first
        if let url { try? FileManager.default.createDirectory(at: url, withIntermediateDirectories: true) }
        return url
    }

    @MainActor @discardableResult
    static func choose() -> URL? { current }
#endif

    static func fileName(_ scenario: Scenario) -> String { fileName(title: scenario.name) }

    static func fileName(title: String) -> String {
        let forbidden = CharacterSet(charactersIn: "/:\\").union(.newlines).union(.controlCharacters)
        var name = title.components(separatedBy: forbidden).joined(separator: "-")
        name = String(name.trimmingCharacters(in: .whitespaces).prefix(80)).trimmingCharacters(in: .whitespaces)
        while name.hasPrefix(".") { name.removeFirst() }
        return name.isEmpty ? "Sans titre" : name
    }

    /// Premier nom libre du dossier : « Titre.json », puis « Titre 2.json »…
    static func availableURL(_ base: String, in folder: URL, excluding current: URL? = nil) -> URL {
        var url = folder.appendingPathComponent(base + ".json")
        var number = 2
        while url.standardizedFileURL != current?.standardizedFileURL && FileManager.default.fileExists(atPath: url.path) {
            url = folder.appendingPathComponent("\(base) \(number).json"); number += 1
        }
        return url
    }

    /// Écrit un nouveau fichier JSON sans jamais remplacer un fichier existant.
    static func save(_ scenario: Scenario, startWithAI: Bool = false, in folder: URL) throws -> URL {
        var file = scenario
        if startWithAI { file.fields[assistantMarker] = .string("assistant") }
        file.fields[uploadMarker] = .bool(true)
        return try write(file.encoded(), named: fileName(scenario), in: folder)
    }

    static func write(_ data: Data, named base: String, in folder: URL) throws -> URL {
        let url = availableURL(base, in: folder)
        try data.write(to: url, options: .withoutOverwriting)
        hideExtension(url)
        return url
    }

    /// La fenêtre et le Finder affichent le titre sans « .json ».
    static func hideExtension(_ url: URL) {
        try? (url as NSURL).setResourceValue(true, forKey: .hasHiddenExtensionKey)
    }

    /// Nouvel emplacement d’un fichier qui suit le titre du scénario : il porte encore « Sans titre »
    /// ou l’ancien titre (éventuellement numéroté). Un fichier renommé par l’enseignant garde son nom.
    static func renamedURL(for url: URL, previousTitle: String, title: String) -> URL? {
        guard url.pathExtension.lowercased() == "json" else { return nil }
        let name = url.deletingPathExtension().lastPathComponent
        let unnumbered = name.replacingOccurrences(of: #" \d+$"#, with: "", options: .regularExpression)
        let target = fileName(title: title)
        let follows = [name, unnumbered].contains { $0 == "Sans titre" || $0 == fileName(title: previousTitle) }
        guard follows, target != "Sans titre", target != name, target != unnumbered else { return nil }
        return availableURL(target, in: url.deletingLastPathComponent(), excluding: url)
    }

    static func extractPendingUpload(from scenario: inout Scenario) -> Bool {
        scenario.fields.removeValue(forKey: uploadMarker) == .bool(true)
    }

    /// Un scénario vide, juste créé, n’est pas encore envoyé : le compte ne reçoit pas de brouillons abandonnés.
    static func hasContent(_ scenario: Scenario) -> Bool {
        !scenario.moments.isEmpty || !["", "Sans titre"].contains(scenario.name.trimmingCharacters(in: .whitespaces))
    }

    static func extractAssistantStart(from scenario: inout Scenario) -> Bool {
        scenario.fields.removeValue(forKey: assistantMarker) == .string("assistant")
    }

    /// Identité de synchronisation enregistrée dans un fichier JSON, s’il est lié à un compte.
    static func identity(of url: URL) -> SyncDocumentIdentity? {
        guard url.pathExtension.lowercased() == "json", let data = try? Data(contentsOf: url),
              var scenario = try? Scenario.decode(data) else { return nil }
        return ScenarioDocument.extractIdentity(from: &scenario)
    }

    /// Fichiers JSON du dossier liés à ce scénario du compte.
    static func files(linkedTo identity: SyncDocumentIdentity, in folder: URL) -> [URL] {
        let contents = (try? FileManager.default.contentsOfDirectory(at: folder, includingPropertiesForKeys: nil,
                                                                      options: [.skipsHiddenFiles])) ?? []
        return contents.filter { self.identity(of: $0) == identity }
    }
}

#if os(macOS)
/// Un scénario supprimé sur le site disparaît aussi du Mac : sa fenêtre se ferme
/// et son fichier du dossier des scénarios part à la Corbeille, d’où il reste récupérable.
@MainActor
enum SiteDeletionCleanup {
    static func apply(_ identity: SyncDocumentIdentity, workingFile: URL?) {
        let documents = NSDocumentController.shared.documents
        var files = Set((ScenarioFolder.current.map { ScenarioFolder.files(linkedTo: identity, in: $0) } ?? [])
            .map(\.standardizedFileURL))
        // Un fichier lié peut aussi avoir été ouvert depuis un autre dossier.
        for url in documents.compactMap(\.fileURL) where ScenarioFolder.identity(of: url) == identity {
            files.insert(url.standardizedFileURL)
        }
        let working = workingFile?.standardizedFileURL
        for document in documents {
            guard let url = document.fileURL?.standardizedFileURL, url == working || files.contains(url) else { continue }
            document.close()
        }
        for url in files where url != working {
            try? FileManager.default.trashItem(at: url, resultingItemURL: nil)
        }
    }
}

struct ScenarioFolderSection: View {
    @AppStorage(ScenarioFolder.defaultsKey) private var path = ""
    var body: some View {
        Section {
            LabeledContent("Dossier") {
                Text(path.isEmpty ? "Non choisi" : (path as NSString).abbreviatingWithTildeInPath)
                    .foregroundStyle(.secondary).lineLimit(1).truncationMode(.middle)
            }
            HStack {
                Spacer()
                if !path.isEmpty, let url = ScenarioFolder.current {
                    Button("Afficher dans le Finder") { NSWorkspace.shared.open(url) }
                }
                Button(path.isEmpty ? "Choisir…" : "Modifier…") { ScenarioFolder.choose() }
            }
        } header: {
            Text("Scénarios")
        } footer: {
            Text("Les scénarios créés ou importés depuis l’accueil y sont enregistrés automatiquement au format JSON.")
        }
    }
}
#endif
