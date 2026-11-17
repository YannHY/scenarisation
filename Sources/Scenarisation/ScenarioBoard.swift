import SwiftUI

struct BoardDrag: Codable {
    let moment: String
    var activity: String?
    var text: String { (try? JSONEncoder().encode(self)).map { String(decoding: $0, as: UTF8.self) } ?? "" }
}

struct ScenarioBoard: View {
    @Binding var scenario: Scenario
    @Binding var focusMoment: String?
    let askAI: () -> Void
    let openModels: () -> Void
    @State private var deleteID: String?
    private var layout: String { scenario.meta["boardLayout"]?.string ?? "columns" }
    private var hideIntentions: Bool { scenario.fields["intentionsCollapsed"] == .bool(true) }

    var body: some View {
        Group {
            if scenario.moments.isEmpty {
                ContentUnavailableView {
                    Label("Votre scénario commence ici", systemImage: "square.stack.3d.up")
                } description: {
                    Text("Ajoutez un moment, partez d’un modèle ou construisez-le avec l’assistant.")
                } actions: {
                    HStack(spacing: 10) {
                        Button(action: addMoment) { ActionLabel(title: "Créer un moment", icon: "plus") }.studioButtons(prominent: true)
                        Button(action: openModels) { ActionLabel(title: "Modèles", icon: "layers") }.studioButtons()
                        Button(action: askAI) { ActionLabel(title: "Assistant IA", icon: "wand") }.studioButtons()
                    }.controlSize(.large)
                }.frame(maxWidth: .infinity).padding(.vertical, 40)
            } else if layout == "list" {
                LazyVStack(spacing: 20) { cards; addCard }.padding(.horizontal, 28).frame(maxWidth: 820).frame(maxWidth: .infinity)
            } else if layout == "grid" {
                LazyVGrid(columns: [GridItem(.adaptive(minimum: 430), spacing: 20, alignment: .top)], alignment: .leading, spacing: 20) {
                    cards
                    addCard
                }.padding(.horizontal, 28)
            } else {
                ScrollViewReader { proxy in
                    ScrollView(.horizontal) {
                        HStack(alignment: .top, spacing: 20) {
                            ForEach(scenario.moments.indices, id: \.self) { index in card(index).frame(width: 460) }
                            addCard.frame(width: 220)
                        }.padding(.horizontal, 28).padding(.vertical, 6).padding(.bottom, 14)
                    }
                    .scrollClipDisabled()
                    .onChange(of: focusMoment) { _, id in
                        if let id { withAnimation(.smooth) { proxy.scrollTo("moment-" + id, anchor: .leading) } }
                    }
                }
            }
        }
        .animation(.snappy, value: layout)
        .confirmationDialog("Supprimer ce moment et ses activités ?", isPresented: Binding(
            get: { deleteID != nil }, set: { if !$0 { deleteID = nil } })) {
                Button("Supprimer", role: .destructive) { scenario.moments.removeAll { $0.id == deleteID }; deleteID = nil }
            }
    }
    private var cards: some View {
        ForEach(scenario.moments.indices, id: \.self) { index in card(index) }
    }
    private func card(_ index: Int) -> some View {
        MomentCard(moment: $scenario.moments[index], hideIntentions: hideIntentions, remove: { deleteID = scenario.moments[index].id },
                   duplicate: { duplicateMoment(index) }, move: { delta in moveMoment(index, delta) },
                   canMoveUp: index > 0, canMoveDown: index + 1 < scenario.moments.count)
            .id("moment-" + scenario.moments[index].id)
            .dropDestination(for: String.self) { payloads, _ in
                guard let text = payloads.first, let drag = try? JSONDecoder().decode(BoardDrag.self, from: Data(text.utf8)) else { return false }
                return move(drag, to: scenario.moments[index].id)
            }
    }
    private var addCard: some View {
        AddMomentCard(action: addMoment)
    }
    private func addMoment() {
        let moment = Moment.blank(title: "Moment \(scenario.moments.count + 1)")
        scenario.moments.append(moment); focusMoment = moment.id
    }
    private func duplicateMoment(_ index: Int) {
        var copy = scenario.moments[index]
        copy["id"] = UUID().uuidString; copy["title"] += " — copie"
        copy.activities = copy.activities.map { activity in
            var item = activity; item["id"] = UUID().uuidString; return item
        }
        scenario.moments.insert(copy, at: index + 1)
    }
    private func moveMoment(_ index: Int, _ delta: Int) {
        let destination = index + delta
        guard scenario.moments.indices.contains(destination) else { return }
        scenario.moments.swapAt(index, destination)
    }
    private func move(_ drag: BoardDrag, to destination: String) -> Bool {
        guard let from = scenario.moments.firstIndex(where: { $0.id == drag.moment }),
              let to = scenario.moments.firstIndex(where: { $0.id == destination }), from != to else { return false }
        if let id = drag.activity {
            guard let index = scenario.moments[from].activities.firstIndex(where: { $0.id == id }) else { return false }
            let activity = scenario.moments[from].activities.remove(at: index)
            scenario.moments[to].activities.append(activity)
        } else {
            let moment = scenario.moments.remove(at: from)
            scenario.moments.insert(moment, at: to)
        }
        return true
    }
}

struct AddMomentCard: View {
    let action: () -> Void
    @State private var hovering = false
    var body: some View {
        Button(action: action) {
            VStack(spacing: 10) {
                FA(name: "plus", size: 22)
                Text("Ajouter un moment").font(.callout.weight(.medium))
            }
            .foregroundStyle(hovering ? Color.accentColor : .secondary)
            .frame(maxWidth: .infinity, minHeight: 150)
            .background(hovering ? Color.accentColor.opacity(0.07) : Color.primary.opacity(0.03), in: RoundedRectangle(cornerRadius: 18, style: .continuous))
            .overlay(RoundedRectangle(cornerRadius: 18, style: .continuous)
                .strokeBorder(hovering ? Color.accentColor.opacity(0.5) : Color.primary.opacity(0.15), style: StrokeStyle(lineWidth: 1.2, dash: [6, 5])))
            .contentShape(RoundedRectangle(cornerRadius: 18, style: .continuous))
        }
        .buttonStyle(.plain).onHover { hovering = $0 }
        .animation(.easeOut(duration: 0.15), value: hovering)
    }
}

struct MomentCard: View {
    @Binding var moment: Moment
    let hideIntentions: Bool
    let remove: () -> Void
    let duplicate: () -> Void
    let move: (Int) -> Void
    let canMoveUp: Bool
    let canMoveDown: Bool
    @State private var notes = false
    @State private var deleteActivity: String?

    private func field(_ key: String) -> Binding<String> {
        Binding(get: { moment[key] }, set: { moment[key] = $0 })
    }
    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            TypeStrip(activities: moment.activities, height: 6)
            HStack(alignment: .top, spacing: 8) {
                VStack(alignment: .leading, spacing: 6) {
                    HStack(spacing: 5) {
                        Image(systemName: "line.3.horizontal").font(.system(size: 10, weight: .bold))
                        Text("MOMENT").font(.system(size: 10, weight: .bold)).tracking(0.6)
                    }.foregroundStyle(.tertiary)
                        .draggable(BoardDrag(moment: moment.id).text).help("Glisser pour déplacer le moment")
                    TextField("Titre du moment", text: field("title"), axis: .vertical)
                        .textFieldStyle(.plain).font(.title3.weight(.semibold)).lineLimit(1...4)
                }
                Spacer(minLength: 0)
                Menu { actions } label: { FA(name: "other", size: 14).frame(width: 26, height: 26).contentShape(Rectangle()) }
                    .menuStyle(.borderlessButton).menuIndicator(.hidden).fixedSize().foregroundStyle(.secondary)
                    .help("Déplacer, dupliquer ou supprimer le moment").accessibilityLabel("Actions du moment")
            }
            ExpandableText(label: "Objectifs du moment", text: field("objectives"), aiPurpose: .objectives)
            if !hideIntentions { ExpandableText(label: "Intentions pédagogiques", text: field("intentions")) }
            Divider()
            HStack {
                Text("Activités").font(.subheadline.weight(.semibold)).foregroundStyle(.secondary)
                Spacer()
                Label("\(moment.duration.formatted()) min", systemImage: "clock").font(.caption.weight(.medium)).foregroundStyle(.secondary)
            }
            VStack(spacing: 12) {
                ForEach(moment.activities.indices, id: \.self) { index in
                    ActivityCard(activity: $moment.activities[index], momentID: moment.id,
                        duplicate: { duplicateActivity(index) }, remove: { deleteActivity = moment.activities[index].id },
                        move: { offset in
                            let target = index + offset
                            if moment.activities.indices.contains(target) { withAnimation(.snappy) { moment.activities.swapAt(index, target) } }
                        }, canMoveUp: index > 0, canMoveDown: index + 1 < moment.activities.count)
                }
            }
            HStack(spacing: 8) {
                Button { withAnimation(.snappy) { moment.activities.append(.blank()) } } label: { ActionLabel(title: "Activité", icon: "plus") }
                    .studioButtons().controlSize(.small)
                Button { withAnimation(.snappy) { notes.toggle() } } label: { ActionLabel(title: "Notes", icon: "doc") }
                    .buttonStyle(.borderless).foregroundStyle(notes ? Color.accentColor : .secondary).controlSize(.small)
                Spacer()
            }
            if notes { ExpandableText(label: "Notes de l’enseignant", text: field("notes")) }
        }
        .environment(\.fieldAIMoment, moment)
        .padding(16)
        .studioCard(radius: 20)
        .contextMenu { actions }
        .confirmationDialog("Supprimer cette activité ?", isPresented: Binding(
            get: { deleteActivity != nil }, set: { if !$0 { deleteActivity = nil } })) {
                Button("Supprimer", role: .destructive) { moment.activities.removeAll { $0.id == deleteActivity }; deleteActivity = nil }
            }
    }
    @ViewBuilder private var actions: some View {
        Button("Monter") { move(-1) }.disabled(!canMoveUp)
        Button("Descendre") { move(1) }.disabled(!canMoveDown)
        Button("Dupliquer le moment", action: duplicate)
        Divider()
        Button("Supprimer le moment…", role: .destructive, action: remove)
    }
    private func duplicateActivity(_ index: Int) {
        var copy = moment.activities[index]; copy["id"] = UUID().uuidString
        withAnimation(.snappy) { moment.activities.insert(copy, at: index + 1) }
    }
}

struct ActivityCard: View {
    @Binding var activity: Activity
    let momentID: String
    let duplicate: () -> Void
    let remove: () -> Void
    let move: (Int) -> Void
    let canMoveUp: Bool
    let canMoveDown: Bool
    @State private var showCompetencies = false
    private var tint: Color { StudioStyle.color(activity["type"]) }
    private func field(_ key: String) -> Binding<String> {
        Binding(get: { activity[key] }, set: { activity[key] = $0 })
    }
    var body: some View {
        VStack(alignment: .leading, spacing: 0) {
            HStack(spacing: 8) {
                typeMenu
                HStack(spacing: 4) {
                    FA(name: "clock", size: 10).foregroundStyle(.secondary)
                    TextField("Minutes", value: $activity.duration, format: .number)
                        .labelsHidden().textFieldStyle(.plain).multilineTextAlignment(.trailing).frame(width: 30).font(.system(size: 12, weight: .medium))
                        .onChange(of: activity.duration) { _, value in activity.duration = min(100_000, max(1, value.isFinite ? value : 1)) }
                    Text("min").font(.caption).foregroundStyle(.secondary)
                }.padding(.horizontal, 7).frame(height: 26)
                    .background(Color.primary.opacity(0.06), in: RoundedRectangle(cornerRadius: 8, style: .continuous)).help("Durée en minutes")
                mode("groupMode", title: "Groupe", choices: Pedagogy.groups)
                mode("teachingMode", title: "Enseignement", choices: Pedagogy.teaching)
                mode("syncMode", title: "Rythme", choices: Pedagogy.rhythms)
                mode("locationMode", title: "Modalité", choices: Pedagogy.locations)
                mode("evaluationMode", title: "Évaluation", choices: Pedagogy.evaluations)
                aiasMenu
                Button { showCompetencies = true } label: { chipIcon(FA(name: "tools", size: 12)) }
                    .buttonStyle(.plain).help("Choisir les compétences").accessibilityLabel("Choisir les compétences")
                Spacer(minLength: 0)
                Menu { actions } label: { FA(name: "other", size: 13).frame(width: 22, height: 26).contentShape(Rectangle()) }
                    .menuStyle(.borderlessButton).menuIndicator(.hidden).fixedSize().foregroundStyle(.secondary)
                    .help("Déplacer, dupliquer ou supprimer l’activité").accessibilityLabel("Actions de l’activité")
            }.padding(.horizontal, 9).padding(.vertical, 8)
                .background(tint.opacity(0.21))
                .draggable(BoardDrag(moment: momentID, activity: activity.id).text)
            VStack(alignment: .leading, spacing: 12) {
                if case .array(let tools) = activity.fields["tools"], !tools.isEmpty {
                    CompetencyTags(activity: $activity)
                }
                ExpandableText(label: "Description de l’activité", text: field("description"), aiPurpose: .activityDescription)
                ExpandableText(label: "Consignes pour les élèves", text: field("instructions"), aiPurpose: .instructions)
                if !activity["notes"].isEmpty { ExpandableText(label: "Notes de l’activité", text: field("notes")) }
            }.padding(12)
        }
        .environment(\.fieldAIActivity, activity)
        .background(StudioStyle.surface)
        .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
        .overlay(RoundedRectangle(cornerRadius: 14, style: .continuous).strokeBorder(tint.opacity(0.6), lineWidth: 1))
        .animation(.easeOut(duration: 0.2), value: activity["type"])
        .contextMenu { actions }
        .sheet(isPresented: $showCompetencies) { CompetencySheet(activity: $activity) }
    }
    @ViewBuilder private var actions: some View {
        Button("Monter") { move(-1) }.disabled(!canMoveUp)
        Button("Descendre") { move(1) }.disabled(!canMoveDown)
        Button("Dupliquer", action: duplicate)
        Divider()
        Button("Supprimer…", role: .destructive, action: remove)
    }
    private func chipIcon<Content: View>(_ content: Content) -> some View {
        content.foregroundStyle(.primary.opacity(0.75)).frame(width: 26, height: 26)
            .background(Color.primary.opacity(0.06), in: RoundedRectangle(cornerRadius: 8, style: .continuous))
            .contentShape(Rectangle())
    }
    private var typeMenu: some View {
        Menu {
            Picker("Type d’apprentissage", selection: field("type")) {
                ForEach(Pedagogy.types) { choice in Label(choice.label, systemImage: StudioStyle.symbols[choice.id] ?? "circle").tag(choice.id) }
            }.pickerStyle(.inline)
        } label: { TypeBadge(type: activity["type"], size: 26) }
            .menuStyle(.borderlessButton).menuIndicator(.hidden).fixedSize()
            .help("Type d’apprentissage : " + Pedagogy.label(activity["type"], in: Pedagogy.types))
            .accessibilityLabel("Type d’apprentissage : " + Pedagogy.label(activity["type"], in: Pedagogy.types))
    }
    private var aiasMenu: some View {
        Menu {
            Picker("AIAS", selection: $activity.aiasSelection) {
                Text("À définir").tag("undecided")
                Text("Non pertinent").tag("not_applicable")
                Text("1 — Sans IA").tag("1")
                Text("2 — Planification").tag("2")
                Text("3 — Collaboration").tag("3")
                Text("4 — IA intégrale").tag("4")
                Text("5 — Exploration").tag("5")
            }.pickerStyle(.inline)
        } label: {
            Group {
                if Int(activity.aiasSelection) != nil { Text(activity.aiasSelection).font(.system(size: 12, weight: .bold)) }
                else { FA(name: "wand", size: 12) }
            }.foregroundStyle(.primary.opacity(0.75)).frame(width: 26, height: 26)
                .background(Color.primary.opacity(0.06), in: RoundedRectangle(cornerRadius: 8, style: .continuous))
        }.menuStyle(.borderlessButton).menuIndicator(.hidden).fixedSize().help("Place de l’IA · AIAS 2.1").accessibilityLabel("AIAS")
    }
    private func mode(_ key: String, title: String, choices: [Choice]) -> some View {
        Menu {
            Picker(title, selection: field(key)) {
                ForEach(choices) { choice in
                    Label(choice.label, systemImage: StudioStyle.symbols[choice.id] ?? "circle").tag(choice.id)
                }
            }.pickerStyle(.inline)
        } label: { chipIcon(FA(name: activity[key], size: 12)) }
        .menuStyle(.borderlessButton).menuIndicator(.hidden).fixedSize()
        .help(title + " : " + Pedagogy.label(activity[key], in: choices))
        .accessibilityLabel(title + " : " + Pedagogy.label(activity[key], in: choices))
    }
}

struct CompetencyTags: View {
    @Binding var activity: Activity
    var body: some View {
        let selected = activity.fields["tools"]?.array ?? []
        let catalog = try? CompetencyCatalog.bundled.get()
        FlowLayout(spacing: 6) {
            ForEach(Array(selected.enumerated()), id: \.offset) { _, value in
                let item = catalog?.competencies.first { $0.id == value.string }
                HStack(spacing: 5) {
                    // La bulle couvre toute la pastille, pas seulement les lettres.
                    HStack(spacing: 5) {
                        FA(name: "tools", size: 9).foregroundStyle(.secondary)
                        Text(item.flatMap { catalog?.shortLabel(for: $0) } ?? value.string).lineLimit(1)
                    }
                    .padding(.leading, 9).padding(.vertical, 4).contentShape(Rectangle())
                    .help(item.map { "\(catalog?.context(for: $0) ?? $0.frameworkId)\n\($0.code) · \($0.labelFr)" } ?? value.string)
                    Button { activity.fields["tools"] = .array(selected.filter { $0 != value }) } label: {
                        FA(name: "close", size: 8).padding(.trailing, 9).padding(.vertical, 4).contentShape(Rectangle())
                    }
                    .buttonStyle(.plain).foregroundStyle(.secondary)
                    .help("Retirer cette compétence").accessibilityLabel("Retirer \(item?.labelFr ?? value.string)")
                }
                .font(.system(size: 11, weight: .medium))
                .background(Color.primary.opacity(0.06), in: Capsule())
                .accessibilityElement(children: .contain)
                .accessibilityLabel(item?.labelFr ?? value.string)
            }
        }
    }
}
