import SwiftUI

struct CompetencyCatalog: Decodable, Sendable {
    struct Framework: Decodable, Identifiable, Sendable {
        let id: String
        let labelFr: String
        let labelEn: String?
        let groups: [Group]
        let sourceUrl: String
    }
    struct Group: Decodable, Identifiable, Sendable {
        let id: String
        let labelFr: String
        let labelEn: String?
        let sourceUrl: String
    }
    struct Competency: Decodable, Identifiable, Sendable {
        let id: String
        let frameworkId: String
        let groupId: String
        let code: String
        let labelFr: String
        let labelEn: String?
        let aliases: [String]?
        let descriptionFr: String
        let descriptionEn: String?
        let sourceUrl: String
    }
    struct DomainSection: Identifiable {
        let framework: Framework
        let group: Group
        let competencies: [Competency]
        var id: String { framework.id + ":" + group.id }
    }
    let frameworks: [Framework]
    let competencies: [Competency]

    func matching(frameworkID: String = "", groupID: String = "", search: String = "",
                  selectedIDs: Set<String>? = nil) -> [Competency] {
        let terms = Self.normalized(search).split(whereSeparator: \.isWhitespace)
        let frameworksByID = Dictionary(uniqueKeysWithValues: frameworks.map { ($0.id, $0) })
        return competencies.filter { item in
            guard (frameworkID.isEmpty || item.frameworkId == frameworkID),
                  (groupID.isEmpty || item.groupId == groupID),
                  selectedIDs.map({ $0.contains(item.id) }) ?? true else { return false }
            guard !terms.isEmpty else { return true }
            let framework = frameworksByID[item.frameworkId]
            let group = framework?.groups.first { $0.id == item.groupId }
            let text = Self.normalized([
                item.id, item.code, item.labelFr, item.labelEn ?? "", item.descriptionFr, item.descriptionEn ?? "",
                (item.aliases ?? []).joined(separator: " "), framework?.labelFr ?? "", framework?.labelEn ?? "",
                group?.labelFr ?? "", group?.labelEn ?? ""
            ].joined(separator: " "))
            let words = Set(text.split { !$0.isLetter && !$0.isNumber })
            return terms.allSatisfy { term in
                // Les sigles courts (IA, AI…) ne doivent pas correspondre à un fragment de mot.
                term.count <= 2 && term.allSatisfy(\.isLetter) ? words.contains(term) : text.contains(term)
            }
        }
    }

    func sections(for items: [Competency]) -> [DomainSection] {
        let byFramework = Dictionary(grouping: items, by: \.frameworkId)
        return frameworks.flatMap { framework in
            let byGroup = Dictionary(grouping: byFramework[framework.id] ?? [], by: \.groupId)
            return framework.groups.compactMap { group -> DomainSection? in
                guard let items = byGroup[group.id], !items.isEmpty else { return nil }
                return DomainSection(framework: framework, group: group, competencies: items)
            }
        }
    }

    func shortLabel(for item: Competency) -> String {
        let framework = frameworks.first { $0.id == item.frameworkId }
        // Florimont recommence sa numérotation dans chacun des trois domaines.
        let scope = item.frameworkId == "florimont" ? framework?.groups.first { $0.id == item.groupId }?.labelFr : framework?.labelFr
        return "\(scope ?? item.frameworkId) \(item.code)"
    }

    func context(for item: Competency) -> String {
        guard let framework = frameworks.first(where: { $0.id == item.frameworkId }) else { return item.frameworkId }
        return [framework.labelFr, framework.groups.first { $0.id == item.groupId }?.labelFr].compactMap { $0 }.joined(separator: " · ")
    }

    private static func normalized(_ text: String) -> String {
        text.folding(options: [.caseInsensitive, .diacriticInsensitive], locale: Locale(identifier: "fr_FR"))
            .replacingOccurrences(of: "’", with: "'")
    }
    static let bundled: Result<CompetencyCatalog, Error> = Result {
        guard let url = Bundle.module.url(forResource: "competencies", withExtension: "json") else {
            throw ScenarioError.invalid("Le catalogue de compétences est absent de l’application.")
        }
        return try JSONDecoder().decode(Self.self, from: Data(contentsOf: url))
    }
}

struct SchoolCatalog: Decodable, Sendable {
    struct Option: Decodable, Sendable {
        let value: String
        let labels: [String: String]
        var choice: Choice { Choice(value, labels["fr"] ?? value) }
    }
    let systems: [Option]
    let levels: [String: [Option]]
    static let bundled: Result<SchoolCatalog, Error> = Result {
        guard let url = Bundle.module.url(forResource: "schools", withExtension: "json") else {
            throw ScenarioError.invalid("Le catalogue des niveaux scolaires est absent de l’application.")
        }
        return try JSONDecoder().decode(Self.self, from: Data(contentsOf: url))
    }
}

struct SchoolPicker: View {
    @Binding var scenario: Scenario
    private func field(_ key: String) -> Binding<String> {
        Binding(get: { scenario.meta[key]?.string ?? "" }, set: {
            scenario.meta[key] = .string($0)
            if key == "schoolSystem" { scenario.meta["schoolLevel"] = .string("") }
        })
    }
    var body: some View {
        switch SchoolCatalog.bundled {
        case .failure(let error): Text(error.localizedDescription).foregroundStyle(.red)
        case .success(let catalog):
            ChoicePicker(title: "Système scolaire", selection: field("schoolSystem"),
                         choices: [Choice("", "À définir")] + catalog.systems.map(\.choice))
            ChoicePicker(title: "Niveau", selection: field("schoolLevel"), choices: [Choice("", "À définir")] +
                         (catalog.levels[field("schoolSystem").wrappedValue] ?? []).map(\.choice))
                .disabled(field("schoolSystem").wrappedValue.isEmpty)
        }
    }
}

struct CompetencyPicker: View {
    @Binding var activity: Activity
    @State private var showPicker = false
    private var selected: [String] { (activity.fields["tools"]?.array ?? []).map(\.string) }
    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack {
                Text("Compétences").font(.headline)
                Spacer()
                Button("Choisir") { showPicker = true }
            }
            CompetencyTags(activity: $activity)
            if selected.isEmpty { Text("Aucune compétence sélectionnée.").foregroundStyle(.secondary).font(.callout) }
        }
        .sheet(isPresented: $showPicker) { CompetencySheet(activity: $activity) }
    }
}

struct CompetencySheet: View {
    @Binding var activity: Activity
    @Environment(\.dismiss) private var dismiss
    @State private var framework = ""
    @State private var domain = ""
    @State private var search = ""
    @State private var selectionOnly = false
    private var selectedIDs: Set<String> { Set((activity.fields["tools"]?.array ?? []).map(\.string)) }

    var body: some View {
        VStack(alignment: .leading, spacing: 14) {
            Text("Choisir les compétences").font(.title2.bold())
            switch CompetencyCatalog.bundled {
            case .failure(let error): Text(error.localizedDescription).foregroundStyle(.red)
            case .success(let catalog):
                filters(catalog)
                HStack(spacing: 6) {
                    FA(name: "investigate").foregroundStyle(.secondary)
                    TextField("Rechercher par mot-clé, domaine ou code", text: $search).textFieldStyle(.plain)
                        .accessibilityLabel("Rechercher une compétence par mot-clé, domaine ou code")
                    if !search.isEmpty {
                        Button { search = "" } label: { FA(name: "close").foregroundStyle(.secondary) }
                            .buttonStyle(.plain).accessibilityLabel("Effacer la recherche")
                    }
                }.padding(.horizontal, 10).padding(.vertical, 7)
                    .background(StudioStyle.field, in: RoundedRectangle(cornerRadius: 9, style: .continuous))
                results(catalog)
            }
            HStack {
                Text("\(selectedIDs.count) compétence(s) sélectionnée(s)").font(.callout).foregroundStyle(.secondary)
                Spacer()
                Button("Terminé") { dismiss() }.keyboardShortcut(.defaultAction).studioButtons(prominent: true)
            }
        }.padding(24).frame(minWidth: 720, idealWidth: 800, minHeight: 580, idealHeight: 680)
            .onChange(of: framework) { _, _ in domain = "" }
    }

    private func filters(_ catalog: CompetencyCatalog) -> some View {
        VStack(alignment: .leading, spacing: 10) {
            Picker("Référentiel", selection: $framework) {
                Text("Tous les référentiels").tag("")
                ForEach(catalog.frameworks) { Text($0.labelFr).tag($0.id) }
            }
            Picker("Domaine", selection: $domain) {
                Text("Tous les domaines").tag("")
                ForEach(catalog.frameworks.first { $0.id == framework }?.groups ?? []) { group in
                    Text(group.labelFr).tag(group.id)
                }
            }.disabled(framework.isEmpty)
        }
    }

    private func results(_ catalog: CompetencyCatalog) -> some View {
        let items = catalog.matching(frameworkID: framework, groupID: domain, search: search,
                                     selectedIDs: selectionOnly ? selectedIDs : nil)
        let sections = catalog.sections(for: items)
        return VStack(alignment: .leading, spacing: 10) {
            HStack {
                Text("\(items.count) résultat(s)").foregroundStyle(.secondary)
                Spacer()
                Toggle("Afficher la sélection", isOn: $selectionOnly).toggleStyle(.checkbox)
                if !framework.isEmpty || !domain.isEmpty || !search.isEmpty || selectionOnly {
                    Button("Réinitialiser") { framework = ""; domain = ""; search = ""; selectionOnly = false }
                }
            }.font(.callout)
            List {
                ForEach(sections) { section in
                    Section {
                        ForEach(section.competencies) { item in competencyRow(item, catalog: catalog) }
                    } header: {
                        VStack(alignment: .leading, spacing: 3) {
                            if framework.isEmpty { Text(section.framework.labelFr).font(.caption).foregroundStyle(.secondary) }
                            Text(section.group.labelFr).font(.callout.weight(.semibold)).foregroundStyle(.primary)
                        }.textCase(nil).padding(.vertical, 6)
                    }
                }
            }
            .listStyle(.inset(alternatesRowBackgrounds: false))
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
            .overlay {
                if items.isEmpty {
                    ContentUnavailableView {
                        Label("Aucune compétence trouvée", systemImage: "magnifyingglass")
                    } description: {
                        Text(selectionOnly ? "Modifiez les filtres ou désactivez « Afficher la sélection »." : "Essayez un autre mot-clé ou élargissez les filtres.")
                    }
                }
            }
        }
    }

    private func competencyRow(_ item: CompetencyCatalog.Competency, catalog: CompetencyCatalog) -> some View {
        Toggle(isOn: Binding(get: { selectedIDs.contains(item.id) }, set: { enabled in
            var tools = activity.fields["tools"]?.array ?? []
            tools.removeAll { $0 == .string(item.id) }
            if enabled { tools.append(.string(item.id)) }
            activity.fields["tools"] = .array(tools)
        })) {
            VStack(alignment: .leading, spacing: 4) {
                Text(item.code).font(.caption.monospaced()).foregroundStyle(.secondary)
                Text(item.labelFr).fixedSize(horizontal: false, vertical: true)
                if !item.descriptionFr.isEmpty {
                    Text(item.descriptionFr).font(.caption).foregroundStyle(.secondary).lineLimit(3)
                        .help(item.descriptionFr)
                }
            }.padding(.vertical, 6).frame(maxWidth: .infinity, alignment: .leading)
        }
        .toggleStyle(.checkbox)
        .accessibilityLabel("\(catalog.context(for: item)), \(item.code), \(item.labelFr)")
    }
}
