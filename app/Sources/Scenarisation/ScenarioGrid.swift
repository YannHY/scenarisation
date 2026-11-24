import SwiftUI

/// Largeurs des colonnes de la vue Grille, reprises de scenarisation.eu (`.grid-col-*`).
/// Description et consignes se partagent la place restante.
struct GridColumns {
    static let number: CGFloat = 32, type: CGFloat = 156, duration: CGFloat = 70, location: CGFloat = 104
    static let group: CGFloat = 108, sync: CGFloat = 92, teaching: CGFloat = 128, evaluation: CGFloat = 108
    static let aias: CGFloat = 150, actions: CGFloat = 100
    static let minimumDescription: CGFloat = 180, minimumInstructions: CGFloat = 200
    private static let fixed = number + type + duration + location + group + sync + teaching + evaluation + aias + actions
    static let minimumWidth = fixed + minimumDescription + minimumInstructions

    let description: CGFloat
    let instructions: CGFloat
    init(width: CGFloat) {
        let flexible = max(0, width - Self.fixed)
        description = max(Self.minimumDescription, flexible * 0.47)
        instructions = max(Self.minimumInstructions, flexible - description)
    }
}

/// Vue « Grille » : le scénario en tableau, un groupe de lignes par moment, comme sur le site.
struct ScenarioGrid: View {
    @Binding var scenario: Scenario
    let width: CGFloat
    let addMoment: () -> Void
    let duplicateMoment: (Int) -> Void
    let moveMoment: (Int, Int) -> Void
    let removeMoment: (Int) -> Void
    @State private var pendingDelete: ActivityReference?

    private struct ActivityReference: Equatable {
        let moment: String
        let activity: String
    }

    var body: some View {
        let columns = GridColumns(width: width)
        VStack(spacing: 0) {
            header(columns)
            ForEach(Array(scenario.moments.enumerated()), id: \.element.id) { index, _ in
                VStack(spacing: 0) { momentRows(index, columns: columns) }
                    .background(StudioStyle.surface)
                    .boardDrop(moment: scenario.moments[index].id, target: .moment)
            }
            Button(action: addMoment) {
                Text("+ Ajouter un moment").font(.system(size: 12))
                    .padding(.horizontal, 14).padding(.vertical, 6)
                    .overlay(RoundedRectangle(cornerRadius: 6, style: .continuous)
                        .strokeBorder(StudioStyle.line, style: StrokeStyle(lineWidth: 1, dash: [4, 3])))
                    .contentShape(Rectangle())
            }
            .buttonStyle(GridDashedButtonStyle())
            .frame(maxWidth: .infinity, alignment: .leading)
            .padding(.horizontal, 12).padding(.vertical, 10)
        }
        .frame(width: width, alignment: .topLeading)
        .background(StudioStyle.surface)
        .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
        .overlay(RoundedRectangle(cornerRadius: 14, style: .continuous).strokeBorder(Color.primary.opacity(0.07)))
        .shadow(color: .black.opacity(0.05), radius: 10, y: 3)
        .confirmationDialog("Supprimer cette activité ?", isPresented: Binding(
            get: { pendingDelete != nil }, set: { if !$0 { pendingDelete = nil } })) {
                Button("Supprimer", role: .destructive) {
                    if let target = pendingDelete, let index = scenario.moments.firstIndex(where: { $0.id == target.moment }) {
                        scenario.moments[index].activities.removeAll { $0.id == target.activity }
                    }
                    pendingDelete = nil
                }
            }
    }

    private func header(_ columns: GridColumns) -> some View {
        HStack(spacing: 0) {
            headerCell("#", GridColumns.number, center: true)
            headerCell("Type", GridColumns.type)
            headerCell("Durée", GridColumns.duration)
            headerCell("Lieu", GridColumns.location)
            headerCell("Groupe", GridColumns.group)
            headerCell("Sync", GridColumns.sync)
            headerCell("Enseignement", GridColumns.teaching)
            headerCell("Évaluation", GridColumns.evaluation)
            headerCell("AIAS", GridColumns.aias)
            headerCell("Description", columns.description)
            headerCell("Consignes pour les élèves", columns.instructions)
            headerCell("", GridColumns.actions)
        }
        .padding(.vertical, 7)
        .background(Color.primary.opacity(0.025))
        .overlay(alignment: .bottom) { Rectangle().fill(StudioStyle.line).frame(height: 1) }
    }

    private func headerCell(_ title: String, _ width: CGFloat, center: Bool = false) -> some View {
        Text(title.uppercased()).font(.system(size: 11, weight: .semibold)).tracking(0.45)
            .foregroundStyle(.secondary).lineLimit(1)
            .padding(.horizontal, 8)
            .frame(width: width, alignment: center ? .center : .leading)
    }

    @ViewBuilder private func momentRows(_ index: Int, columns: GridColumns) -> some View {
        let moment = scenario.moments[index]
        GridMomentRow(momentID: moment.id, number: index + 1, title: Binding(get: { scenario.moments[index]["title"] },
                                                        set: { scenario.moments[index]["title"] = $0 }),
                      duration: moment.duration, duplicate: { duplicateMoment(index) },
                      move: { moveMoment(index, $0) }, remove: { removeMoment(index) },
                      canMoveUp: index > 0, canMoveDown: index + 1 < scenario.moments.count)
            .id("grid-moment-" + moment.id)
        ForEach(Array(moment.activities.enumerated()), id: \.element.id) { position, _ in
            GridActivityRow(activity: $scenario.moments[index].activities[position], momentID: moment.id,
                            number: position + 1, columns: columns,
                            canMoveUp: position > 0, canMoveDown: position + 1 < moment.activities.count,
                            move: { offset in
                                let target = position + offset
                                guard scenario.moments[index].activities.indices.contains(target) else { return }
                                scenario.moments[index].activities.swapAt(position, target)
                            },
                            duplicate: {
                                var copy = scenario.moments[index].activities[position]; copy["id"] = UUID().uuidString
                                scenario.moments[index].activities.insert(copy, at: position + 1)
                            },
                            remove: { pendingDelete = ActivityReference(moment: moment.id, activity: moment.activities[position].id) })
                .boardDrop(moment: moment.id, target: .activity(moment.activities[position].id))
        }
        Button { scenario.moments[index].activities.append(.blank()) } label: {
            Text("+ Activité").font(.system(size: 11))
                .padding(.horizontal, 10).padding(.vertical, 3)
                .frame(maxWidth: .infinity, alignment: .leading)
                .overlay(RoundedRectangle(cornerRadius: 4, style: .continuous)
                    .strokeBorder(StudioStyle.line, style: StrokeStyle(lineWidth: 1, dash: [4, 3])))
                .contentShape(Rectangle())
        }
        .buttonStyle(GridDashedButtonStyle())
        .padding(.horizontal, 4).padding(.vertical, 3)
        .overlay(alignment: .bottom) { Rectangle().fill(StudioStyle.line).frame(height: 1) }
        .accessibilityLabel("Ajouter une activité au moment \(index + 1)")
        .boardDrop(moment: moment.id, target: .end)
        .help("Déposer une activité ici pour la placer à la fin de ce moment")
    }
}

/// Bouton en pointillés du tableau : gris, puis teinté au survol.
private struct GridDashedButtonStyle: ButtonStyle {
    func makeBody(configuration: Configuration) -> some View { DashedLabel(configuration: configuration) }
    private struct DashedLabel: View {
        let configuration: ButtonStyleConfiguration
        @State private var hovering = false
        var body: some View {
            configuration.label
                .foregroundStyle(hovering ? Color.accentColor : .secondary)
                .background(hovering ? Color.accentColor.opacity(0.06) : .clear, in: RoundedRectangle(cornerRadius: 5, style: .continuous))
                .opacity(configuration.isPressed ? 0.7 : 1)
                .onHover { hovering = $0 }
        }
    }
}

private struct GridMomentRow: View {
    let momentID: String
    let number: Int
    @Binding var title: String
    let duration: Double
    let duplicate: () -> Void
    let move: (Int) -> Void
    let remove: () -> Void
    let canMoveUp: Bool
    let canMoveDown: Bool
    @FocusState private var focused: Bool
    @State private var hovering = false

    var body: some View {
        HStack(spacing: 6) {
            Text("MOMENT \(number)").font(.system(size: 11, weight: .bold)).tracking(0.55).foregroundStyle(.secondary)
                .fixedSize()
                .boardHandle(BoardDrag(moment: momentID))
                .help("Glisser pour déplacer le moment")
            TextField("Titre du moment", text: $title)
                .textFieldStyle(.plain).font(.system(size: 13, weight: .semibold)).lineLimit(1).focused($focused)
                .padding(.horizontal, 4).padding(.vertical, 2)
                .frame(width: 260)
                .background(hovering || focused ? Color.primary.opacity(0.06) : .clear, in: RoundedRectangle(cornerRadius: 4, style: .continuous))
                .overlay(RoundedRectangle(cornerRadius: 4, style: .continuous).strokeBorder(Color.accentColor.opacity(focused ? 0.8 : 0), lineWidth: 2))
                .onHover { hovering = $0 }
            Text("— \(duration.formatted()) min").font(.system(size: 11)).foregroundStyle(.secondary).fixedSize()
            GridActionButton(title: "Dupliquer le moment", systemImage: "plus.square.on.square", action: duplicate)
            Spacer(minLength: 0)
        }
        .padding(.horizontal, 10).padding(.vertical, 6)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.primary.opacity(0.025))
        .overlay(alignment: .bottom) { Rectangle().fill(StudioStyle.line).frame(height: 1) }
        .contextMenu {
            Button("Monter") { move(-1) }.disabled(!canMoveUp)
            Button("Descendre") { move(1) }.disabled(!canMoveDown)
            Button("Dupliquer le moment", action: duplicate)
            Divider()
            Button("Supprimer le moment…", role: .destructive, action: remove)
        }
    }
}

private struct GridActivityRow: View {
    @Binding var activity: Activity
    let momentID: String
    let number: Int
    let columns: GridColumns
    let canMoveUp: Bool
    let canMoveDown: Bool
    let move: (Int) -> Void
    let duplicate: () -> Void
    let remove: () -> Void
    @State private var hovering = false
    private var tint: Color { StudioStyle.color(activity["type"]) }

    private func field(_ key: String) -> Binding<String> {
        Binding(get: { activity[key] }, set: { activity[key] = $0 })
    }

    var body: some View {
        HStack(spacing: 0) {
            Text("\(number)").font(.system(size: 11, weight: .semibold)).foregroundStyle(.secondary)
                .frame(width: GridColumns.number)
                .boardHandle(BoardDrag(moment: momentID, activity: activity.id))
                .help("Glisser pour déplacer l’activité")
            cell(GridColumns.type) {
                HStack(spacing: 5) {
                    Circle().fill(tint).frame(width: 8, height: 8)
                    GridPicker(title: "Type d’apprentissage", selection: field("type"), choices: Pedagogy.types)
                }
            }
            cell(GridColumns.duration) { duration }
            cell(GridColumns.location) { GridPicker(title: "Lieu", selection: field("locationMode"), choices: Pedagogy.locations) }
            cell(GridColumns.group) { GridPicker(title: "Groupe", selection: field("groupMode"), choices: Pedagogy.groups) }
            cell(GridColumns.sync) { GridPicker(title: "Rythme", selection: field("syncMode"), choices: Pedagogy.rhythms) }
            cell(GridColumns.teaching) { GridPicker(title: "Enseignement", selection: field("teachingMode"), choices: Pedagogy.teaching) }
            cell(GridColumns.evaluation) { GridPicker(title: "Évaluation", selection: field("evaluationMode"), choices: Pedagogy.evaluations) }
            cell(GridColumns.aias) { GridAIASPicker(selection: $activity.aiasSelection) }
            cell(columns.description) { GridTextCell(label: "Description de l’activité", text: field("description")) }
            cell(columns.instructions) { GridTextCell(label: "Consignes pour les élèves", text: field("instructions")) }
            cell(GridColumns.actions) {
                HStack(spacing: 2) {
                    GridActionButton(title: "Monter", systemImage: "arrow.up") { move(-1) }.disabled(!canMoveUp)
                    GridActionButton(title: "Descendre", systemImage: "arrow.down") { move(1) }.disabled(!canMoveDown)
                    GridActionButton(title: "Dupliquer l’activité", systemImage: "plus.square.on.square", action: duplicate)
                    GridActionButton(title: "Supprimer l’activité", systemImage: "xmark", destructive: true, action: remove)
                }.frame(maxWidth: .infinity)
            }
        }
        .padding(.vertical, 3)
        .frame(minHeight: 30)
        .background(hovering ? Color.primary.opacity(0.035) : .clear).background(StudioStyle.surface)
        .overlay(alignment: .leading) { Rectangle().fill(tint).frame(width: 3) }
        .overlay(alignment: .bottom) { Rectangle().fill(StudioStyle.line).frame(height: 1) }
        .onHover { hovering = $0 }
        .environment(\.fieldAIActivity, activity)
        .contextMenu {
            Button("Monter") { move(-1) }.disabled(!canMoveUp)
            Button("Descendre") { move(1) }.disabled(!canMoveDown)
            Button("Dupliquer", action: duplicate)
            Divider()
            Button("Supprimer…", role: .destructive, action: remove)
        }
    }

    private func cell<Content: View>(_ width: CGFloat, @ViewBuilder _ content: () -> Content) -> some View {
        content().padding(.horizontal, 4).frame(width: width, alignment: .leading)
    }

    private var duration: some View {
        HStack(spacing: 3) {
            TextField("Minutes", value: $activity.duration, format: .number)
                .labelsHidden().textFieldStyle(.plain).multilineTextAlignment(.trailing)
                .font(.system(size: 12)).frame(width: 34)
                .padding(.horizontal, 3).padding(.vertical, 3)
                .background(Color.primary.opacity(0.045), in: RoundedRectangle(cornerRadius: 4, style: .continuous))
                .onChange(of: activity.duration) { _, value in activity.duration = min(100_000, max(1, value.isFinite ? value : 1)) }
                .help("Durée en minutes")
            Text("min").font(.system(size: 11)).foregroundStyle(.secondary)
        }
    }
}

/// Menu déroulant d’une cellule : libellé simple et double chevron, comme un `select` du site.
private struct GridPicker: View {
    let title: String
    @Binding var selection: String
    let choices: [Choice]
    var body: some View {
        let label = Pedagogy.label(selection, in: choices)
        Menu {
            Picker(title, selection: $selection) {
                ForEach(choices) { choice in
                    Label(choice.label, systemImage: StudioStyle.symbols[choice.id] ?? "circle").tag(choice.id)
                }
            }.pickerStyle(.inline)
        } label: {
            GridMenuLabel(text: label)
        }
        .menuStyle(.button).buttonStyle(.plain).menuIndicator(.hidden)
        .help(title + " : " + label)
        .accessibilityLabel(title + " : " + label)
    }
}

private struct GridMenuLabel: View {
    let text: String
    var fill: Color? = nil
    @State private var hovering = false
    var body: some View {
        HStack(spacing: 4) {
            Text(text).lineLimit(1).truncationMode(.tail)
            Spacer(minLength: 2)
            Image(systemName: "chevron.up.chevron.down").font(.system(size: 8, weight: .semibold))
                .foregroundStyle(fill == nil ? AnyShapeStyle(.secondary) : AnyShapeStyle(Color(hex: 0x111827).opacity(0.7)))
        }
        .font(.system(size: fill == nil ? 12 : 11, weight: fill == nil ? .regular : .semibold))
        .foregroundStyle(fill == nil ? AnyShapeStyle(.primary) : AnyShapeStyle(Color(hex: 0x111827)))
        .padding(.horizontal, 6).frame(height: 22)
        .background {
            RoundedRectangle(cornerRadius: 4, style: .continuous)
                .fill(fill ?? (hovering ? Color.primary.opacity(0.06) : .clear))
                .brightness(fill != nil && hovering ? -0.03 : 0)
        }
        .overlay {
            RoundedRectangle(cornerRadius: 4, style: .continuous)
                .strokeBorder(fill != nil ? Color(hex: 0x111827).opacity(hovering ? 0.9 : 0.35)
                              : (hovering ? StudioStyle.line : .clear))
        }
        .contentShape(Rectangle())
        .onHover { hovering = $0 }
    }
}

/// Place de l’IA : pastille colorée selon le niveau AIAS 2.1, aux couleurs du site.
private struct GridAIASPicker: View {
    @Binding var selection: String
    private static let levels: [(id: String, label: String, color: UInt32)] = [
        ("1", "Sans IA", 0x64deff), ("2", "Planification", 0xc1ffd2), ("3", "Collaboration", 0xcfdeff),
        ("4", "IA intégrale", 0xfffecb), ("5", "Exploration", 0xffc1ee)]
    var body: some View {
        let level = Self.levels.first { $0.id == selection }
        let text = level.map { "AIAS \($0.id) · \($0.label)" } ?? (selection == "not_applicable" ? "Non pertinent" : "À définir")
        Menu {
            Picker("AIAS", selection: $selection) {
                Text("À définir").tag("undecided")
                Text("Non pertinent").tag("not_applicable")
                ForEach(Self.levels, id: \.id) { level in Text("\(level.id) — \(level.label)").tag(level.id) }
            }.pickerStyle(.inline)
        } label: {
            GridMenuLabel(text: text, fill: level.map { Color(hex: $0.color) })
        }
        .menuStyle(.button).buttonStyle(.plain).menuIndicator(.hidden)
        .help("Place de l’IA · AIAS 2.1 : " + text)
        .accessibilityLabel("AIAS : " + text)
    }
}

/// Texte long d’une cellule : une ligne au repos, plusieurs pendant la saisie.
private struct GridTextCell: View {
    let label: String
    @Binding var text: String
    @FocusState private var focused: Bool
    @State private var hovering = false
    var body: some View {
        TextField(label, text: $text, axis: .vertical)
            .lineLimit(focused ? 1...8 : 1...1)
            .textFieldStyle(.plain).font(.system(size: 12)).focused($focused)
            .padding(.horizontal, 6).padding(.vertical, 4)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(hovering || focused ? Color.primary.opacity(0.05) : .clear, in: RoundedRectangle(cornerRadius: 4, style: .continuous))
            .overlay(RoundedRectangle(cornerRadius: 4, style: .continuous)
                .strokeBorder(focused ? Color.accentColor.opacity(0.8) : (hovering ? StudioStyle.line : .clear), lineWidth: focused ? 2 : 1))
            .onHover { hovering = $0 }
            .help(text.isEmpty ? label : text)
            .accessibilityLabel(label)
    }
}

private struct GridActionButton: View {
    let title: String
    let systemImage: String
    var destructive = false
    let action: () -> Void
    @Environment(\.isEnabled) private var enabled
    @State private var hovering = false
    var body: some View {
        Button(action: action) {
            Image(systemName: systemImage).font(.system(size: 10, weight: .semibold))
                .frame(width: 21, height: 21)
                .foregroundStyle(hovering && destructive ? Color.red : (hovering ? Color.primary : Color.secondary))
                .background(hovering ? (destructive ? Color.red.opacity(0.12) : Color.primary.opacity(0.07)) : .clear,
                            in: RoundedRectangle(cornerRadius: 4, style: .continuous))
                .contentShape(Rectangle())
        }
        .buttonStyle(.plain).opacity(enabled ? 1 : 0.35)
        .help(title).accessibilityLabel(title)
        .onHover { hovering = $0 && enabled }
    }
}
