import SwiftUI

struct BoardDrag: Hashable {
    let moment: String
    var activity: String?
}

enum BoardDropTarget: Hashable {
    case moment, activity(String), end

    func apply(_ drag: BoardDrag, to moment: String, after: Bool, in scenario: inout Scenario) -> Bool {
        var moments = scenario.moments
        guard let source = moments.firstIndex(where: { $0.id == drag.moment }),
              let target = moments.firstIndex(where: { $0.id == moment }) else { return false }
        switch (drag.activity, self) {
        case (nil, .moment):
            guard source != target else { return false }
            let insertion = target + (after ? 1 : 0) - (source < target ? 1 : 0)
            guard insertion != source else { return false }
            let item = moments.remove(at: source)
            moments.insert(item, at: insertion)
        case (let id?, .activity(let anchor)):
            // L’activité se place avant ou après une autre, dans son moment ou dans un autre.
            guard id != anchor,
                  let from = moments[source].activities.firstIndex(where: { $0.id == id }),
                  moments[target].activities.contains(where: { $0.id == anchor }) else { return false }
            let item = moments[source].activities.remove(at: from)
            let insertion = moments[target].activities.firstIndex(where: { $0.id == anchor })! + (after ? 1 : 0)
            guard source != target || insertion != from else { return false }
            moments[target].activities.insert(item, at: insertion)
        case (let id?, .end):
            guard let from = moments[source].activities.firstIndex(where: { $0.id == id }),
                  source != target || from != moments[source].activities.count - 1 else { return false }
            let item = moments[source].activities.remove(at: from)
            moments[target].activities.append(item)
        default: return false
        }
        scenario.moments = moments
        return true
    }
}

private struct BoardDropEnabled: EnvironmentKey { static let defaultValue = true }
private extension EnvironmentValues {
    var boardDropEnabled: Bool {
        get { self[BoardDropEnabled.self] }
        set { self[BoardDropEnabled.self] = newValue }
    }
}

struct DropSlot: Hashable {
    let moment: String
    let target: BoardDropTarget
    var horizontal = false
}

private struct SlotFrames: PreferenceKey {
    static var defaultValue: [DropSlot: CGRect] { [:] }
    static func reduce(value: inout [DropSlot: CGRect], nextValue: () -> [DropSlot: CGRect]) {
        value.merge(nextValue()) { _, next in next }
    }
}

@Observable final class BoardMotion {
    var drag: BoardDrag?
    var offset: CGSize = .zero
    var slot: DropSlot?
    var after = false
    /// Cadres des cibles, en coordonnées globales, tels que la mise en page les signale.
    @ObservationIgnored var frames: [DropSlot: CGRect] = [:]
    /// Cadre de l’élément saisi au début du geste : le sien suit ensuite le pointeur
    /// et ne dit plus où il se trouvait dans la liste.
    @ObservationIgnored private var origin: CGRect?

    private func isOwn(_ slot: DropSlot, _ item: BoardDrag) -> Bool {
        slot.moment == item.moment && slot.target == (item.activity.map(BoardDropTarget.activity) ?? .moment)
    }

    func update(_ item: BoardDrag, location point: CGPoint, translation: CGSize) {
        if drag != item {
            drag = item
            origin = frames.first { isOwn($0.key, item) }?.value
        }
        offset = translation
        let (target, edge) = item.activity == nil ? momentTarget(item, point) : activityTarget(item, point)
        if slot != target { slot = target }
        if after != edge { after = edge }
    }

    /// Un moment s’insère selon la position du pointeur par rapport au milieu des autres moments,
    /// le long de l’axe de la disposition (vertical en liste, horizontal en colonnes).
    private func momentTarget(_ item: BoardDrag, _ point: CGPoint) -> (DropSlot?, Bool) {
        let moments = frames.filter { $0.key.target == .moment }
        guard let horizontal = moments.keys.first?.horizontal else { return (nil, false) }
        func center(_ frame: CGRect) -> CGFloat { horizontal ? frame.midX : frame.midY }
        let others = moments.filter { !isOwn($0.key, item) }.sorted { center($0.value) < center($1.value) }
        guard let last = others.last else { return (nil, false) }
        let position = horizontal ? point.x : point.y
        let insertion = others.firstIndex { position < center($0.value) } ?? others.count
        let current = origin.map { own in others.count(where: { center($0.value) < center(own) }) }
        if insertion == current { return (nil, false) }
        return insertion < others.count ? (others[insertion].key, false) : (last.key, true)
    }

    /// Une activité va dans le moment survolé (ou le plus proche), entre les activités
    /// dont le milieu encadre le pointeur.
    private func activityTarget(_ item: BoardDrag, _ point: CGPoint) -> (DropSlot?, Bool) {
        func distance(_ frame: CGRect) -> CGFloat {
            hypot(max(frame.minX - point.x, 0, point.x - frame.maxX), max(frame.minY - point.y, 0, point.y - frame.maxY))
        }
        let moments = frames.filter { $0.key.target == .moment }
        guard let destination = moments.min(by: { distance($0.value) < distance($1.value) }),
              distance(destination.value) < 40 else { return (nil, false) }
        let moment = destination.key.moment
        let siblings = frames.filter { slot, _ in
            guard slot.moment == moment, case .activity(let id) = slot.target else { return false }
            return id != item.activity
        }.sorted { $0.value.midY < $1.value.midY }
        let insertion = siblings.firstIndex { point.y < $0.value.midY } ?? siblings.count
        if moment == item.moment, let own = origin, insertion == siblings.count(where: { $0.value.midY < own.midY }) {
            return (nil, false)
        }
        guard let last = siblings.last else {
            return (frames.keys.first { $0.moment == moment && $0.target == .end }, false)
        }
        return insertion < siblings.count ? (siblings[insertion].key, false) : (last.key, true)
    }

    func reset() { drag = nil; offset = .zero; slot = nil; after = false; origin = nil }
}

private struct BoardHandle: ViewModifier {
    @Environment(BoardMotion.self) var motion
    @Environment(\.boardDocument) var document
    @GestureState var dragging = false
    let item: BoardDrag

    func body(content: Content) -> some View {
        content.contentShape(Rectangle()).gesture(DragGesture(minimumDistance: 5, coordinateSpace: .global)
            .updating($dragging) { _, state, _ in state = true }
            .onChanged { motion.update(item, location: $0.location, translation: $0.translation) }
            .onEnded { value in
                motion.update(item, location: value.location, translation: value.translation)
                let slot = motion.slot, after = motion.after
                // La carte rejoint sa nouvelle place (ou revient à l’ancienne) en douceur.
                withAnimation(.snappy(duration: 0.25)) {
                    motion.reset()
                    if let document, let slot {
                        var scenario = document.wrappedValue
                        if slot.target.apply(item, to: slot.moment, after: after, in: &scenario) { document.wrappedValue = scenario }
                    }
                }
            })
            .onChange(of: dragging) { _, active in if !active && motion.drag == item { motion.reset() } }
    }
}

private extension EnvironmentValues { @Entry var boardDocument: Binding<Scenario>? = nil }

private struct BoardDropModifier: ViewModifier {
    @Environment(\.boardDropEnabled) var enabled
    @Environment(BoardMotion.self) var motion
    let slot: DropSlot

    func body(content: Content) -> some View {
        if enabled {
            let source = motion.drag
            let moving = source.map { slot.moment == $0.moment && slot.target == ($0.activity.map(BoardDropTarget.activity) ?? .moment) } ?? false
            let after = motion.after
            let alignment: Alignment = slot.horizontal ? (after ? .trailing : .leading) : (after ? .bottom : .top)
            // Le trait se place au milieu de l’espace entre deux cartes (20 pt entre moments,
            // 12 entre activités, 16 au-dessus du bouton « Activité »).
            let gap: CGFloat = (slot.target == .moment ? 10 : slot.target == .end ? 8 : 6) + 1.5
            content
                .shadow(color: moving ? .black.opacity(0.22) : .clear, radius: 14, y: 6)
                .opacity(moving ? 0.92 : 1)
                .offset(moving ? motion.offset : .zero)
                .overlay(alignment: alignment) {
                    if motion.slot == slot && !moving {
                        Capsule().fill(Color.accentColor)
                            .frame(width: slot.horizontal ? 3 : nil, height: slot.horizontal ? nil : 3)
                            .offset(x: slot.horizontal ? (after ? gap : -gap) : 0, y: slot.horizontal ? 0 : (after ? gap : -gap))
                            .allowsHitTesting(false)
                    }
                }
                .background { GeometryReader { geometry in Color.clear.preference(key: SlotFrames.self, value: [slot: geometry.frame(in: .global)]) } }
                .zIndex(moving || (slot.target == .moment && source?.moment == slot.moment) ? 1 : 0)
        } else { content }
    }
}

extension View {
    func boardDrop(moment: String, target: BoardDropTarget, horizontal: Bool = false) -> some View {
        modifier(BoardDropModifier(slot: DropSlot(moment: moment, target: target, horizontal: horizontal)))
    }
    func boardHandle(_ item: BoardDrag) -> some View { modifier(BoardHandle(item: item)) }
    /// Sur Mac, toute la carte se saisit (hors champs et menus, qui gardent la souris) ;
    /// sur iPad, seul l’en-tête, pour que le doigt puisse encore faire défiler la page.
    @ViewBuilder func boardHandle(_ item: BoardDrag, wholeCard: Bool) -> some View {
        #if os(macOS)
        if wholeCard { boardHandle(item) } else { self }
        #else
        if wholeCard { self } else { boardHandle(item) }
        #endif
    }
}

struct ScenarioBoard: View {
    @State private var motion = BoardMotion()
    @Binding var scenario: Scenario
    @Binding var focusMoment: String?
    /// Disposition affichée : liste, colonnes ou grille (tableau, comme sur le site).
    let layout: String
    let askAI: () -> Void
    let openModels: () -> Void
    @State private var deleteID: String?
    @State private var viewport: CGFloat = 0
    /// Chaque disposition garde ses vues et sa géométrie, même avant son premier clic.
    @State private var mountedLayouts: Set<String> = []
    /// Disposition une fois le changement affiché : la vue masquée n’est désactivée qu’ensuite,
    /// pour que la bascule apparaisse sans attendre.
    @State private var settledLayout: String?
    @Environment(\.scenarioReadOnly) private var readOnly
    private var hideIntentions: Bool { scenario.fields["intentionsCollapsed"] == .bool(true) }
    private var grid: Bool { layout == "grid" }
    private var columns: Bool { layout != "list" && !grid }
    /// Le tableau occupe la largeur visible, et défile horizontalement s’il n’y tient pas.
    private var gridWidth: CGFloat { max(GridColumns.minimumWidth, viewport - 56) }

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
                    }.controlSize(.large).disabled(readOnly)
                }.frame(maxWidth: .infinity).padding(.vertical, 40)
            } else {
                // Les trois dispositions restent montées après leur préparation différée.
                // Liste et colonnes ont chacune leurs aperçus à la bonne largeur.
                ScrollViewReader { proxy in
                    ScrollView(.horizontal) {
                        ZStack(alignment: .topLeading) {
                            ForEach(["list", "columns"], id: \.self) { cardMode in
                                if layout == cardMode || mountedLayouts.contains(cardMode) {
                                    // En liste, les cartes prennent la largeur visible ; en colonnes, elles défilent.
                                    let width = cardMode != "list" || viewport == 0 ? nil : max(0, viewport - 56)
                                    StableLayer(key: CardsKey(moments: scenario.fields["sessions"], hideIntentions: hideIntentions, mode: cardMode, width: width)) {
                                        BoardLayout(mode: cardMode) {
                                            ForEach(Array(scenario.moments.enumerated()), id: \.element.id) { index, _ in card(index, mode: cardMode) }
                                            addCard.layoutValue(key: BoardColumnWidth.self, value: 220)
                                        }
                                        .frame(width: width, alignment: .topLeading)
                                    }
                                    .equatable()
                                    .boardLayer(visible: layout == cardMode, settled: settledLayout.map { $0 != cardMode } ?? false)
                                }
                            }
                            if grid || mountedLayouts.contains("grid") {
                                StableLayer(key: GridKey(moments: scenario.fields["sessions"], width: gridWidth)) {
                                    ScenarioGrid(scenario: $scenario, width: gridWidth, addMoment: addMoment,
                                                 duplicateMoment: duplicateMoment, moveMoment: moveMoment,
                                                 removeMoment: { deleteID = scenario.moments[$0].id })
                                }
                                .equatable()
                                .boardLayer(visible: grid, settled: settledLayout.map { $0 != "grid" } ?? false)
                            }
                        }
                        .padding(.horizontal, 28).padding(.vertical, 6).padding(.bottom, 14)
                        // Désactivé à l’intérieur du défilement : en consultation seule, les colonnes défilent encore.
                        .disabled(readOnly)
                    }
                    .scrollDisabled(!columns && !(grid && gridWidth > viewport - 56))
                    .scrollClipDisabled()
                    .onGeometryChange(for: CGFloat.self) { $0.size.width } action: { viewport = $0 }
                    .onChange(of: focusMoment) { _, id in
                        if columns, let id { withAnimation(.smooth) { proxy.scrollTo("columns-moment-" + id, anchor: .leading) } }
                    }
                }
            }
        }
        .environment(motion)
        .environment(\.boardDocument, $scenario)
        .onPreferenceChange(SlotFrames.self) { motion.frames = $0 }
        .onChange(of: layout, initial: true) { _, value in
            motion.reset()
            mountedLayouts.insert(value)
            // La disposition visible n’est jamais désactivée : un ordre en retard reste sans effet.
            Task { @MainActor in
                guard layout == value else { return }
                settledLayout = value
            }
        }
        .task(id: scenario.moments.isEmpty) {
            guard !scenario.moments.isEmpty else { return }
            // Laisse d’abord apparaître la disposition choisie, puis prépare une vue à la fois.
            // SwiftUI/WebKit doivent être créés sur le thread principal ; l’attente le libère.
            for mode in ["list", "columns", "grid"] {
                guard !mountedLayouts.contains(mode) else { continue }
                do { try await Task.sleep(for: .milliseconds(250)) }
                catch { return }
                guard !Task.isCancelled else { return }
                mountedLayouts.insert(mode)
            }
        }
        .confirmationDialog("Supprimer ce moment et ses activités ?", isPresented: Binding(
            get: { deleteID != nil }, set: { if !$0 { deleteID = nil } })) {
                Button("Supprimer", role: .destructive) { scenario.moments.removeAll { $0.id == deleteID }; deleteID = nil }
            }
    }
    private func card(_ index: Int, mode: String) -> some View {
        MomentCard(moment: $scenario.moments[index], hideIntentions: hideIntentions, remove: { deleteID = scenario.moments[index].id },
                   duplicate: { duplicateMoment(index) }, move: { delta in moveMoment(index, delta) },
                   canMoveUp: index > 0, canMoveDown: index + 1 < scenario.moments.count)
            .id(mode + "-moment-" + scenario.moments[index].id)
            .boardDrop(moment: scenario.moments[index].id, target: .moment, horizontal: mode == "columns")
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
}

/// Ce dont dépendent les cartes : les moments, l’affichage des intentions et la géométrie.
/// Le reste du document (dont la disposition enregistrée juste après un changement de vue) est ignoré.
private struct CardsKey: Equatable, Sendable {
    let moments: JSONValue?
    let hideIntentions: Bool
    let mode: String
    let width: CGFloat?
}

private struct GridKey: Equatable, Sendable {
    let moments: JSONValue?
    let width: CGFloat
}

/// Ne réévalue son contenu que si sa clé change. Les fermetures et liaisons passées aux cartes
/// et au tableau ne sont jamais égales pour SwiftUI : sans cette clé, changer de vue recalculerait
/// aussi toute la disposition masquée.
private struct StableLayer<Key: Equatable & Sendable, Content: View>: View, Equatable {
    /// Hors de l’acteur principal : SwiftUI compare les clés depuis n’importe quel contexte.
    nonisolated let key: Key
    @ViewBuilder let content: () -> Content
    var body: some View { content() }
    nonisolated static func == (lhs: Self, rhs: Self) -> Bool { lhs.key == rhs.key }
}

private extension View {
    /// Une disposition masquée reste montée mais n’occupe aucune place, ne s’affiche pas
    /// et ne reçoit ni clics, ni VoiceOver ; une fois la bascule affichée (`settled`), elle est
    /// aussi désactivée pour que la touche Tab ne mène jamais à un champ invisible.
    func boardLayer(visible: Bool, settled: Bool) -> some View {
        self.disabled(!visible && settled)
            .environment(\.boardDropEnabled, visible)
            .opacity(visible ? 1 : 0)
            .allowsHitTesting(visible)
            .accessibilityHidden(!visible)
            .fixedSize(horizontal: true, vertical: true)
            .frame(width: visible ? nil : 0, height: visible ? nil : 0, alignment: .topLeading)
    }
}

/// Largeur d’une carte en disposition « colonnes ».
private struct BoardColumnWidth: LayoutValueKey {
    static let defaultValue: CGFloat = 460
}

/// Place les cartes du tableau en liste centrée ou en colonnes défilantes.
struct BoardLayout: Layout {
    var mode: String
    var spacing: CGFloat = 20
    /// Largeur maximale d’une carte en liste.
    private let listWidth: CGFloat = 764

    func sizeThatFits(proposal: ProposedViewSize, subviews: Subviews, cache: inout ()) -> CGSize {
        arrange(width: proposal.width, subviews: subviews).size
    }

    func placeSubviews(in bounds: CGRect, proposal: ProposedViewSize, subviews: Subviews, cache: inout ()) {
        let frames = arrange(width: bounds.width, subviews: subviews).frames
        for (subview, frame) in zip(subviews, frames) {
            subview.place(at: CGPoint(x: bounds.minX + frame.minX, y: bounds.minY + frame.minY),
                          proposal: ProposedViewSize(width: frame.width, height: frame.height))
        }
    }

    private func arrange(width: CGFloat?, subviews: Subviews) -> (frames: [CGRect], size: CGSize) {
        var frames: [CGRect] = []
        switch mode {
        case "list":
            let available = width.map { $0.isFinite ? $0 : listWidth } ?? listWidth
            let column = min(available, listWidth)
            let x = (available - column) / 2
            var y: CGFloat = 0
            for subview in subviews {
                let height = subview.sizeThatFits(ProposedViewSize(width: column, height: nil)).height
                frames.append(CGRect(x: x, y: y, width: column, height: height))
                y += height + spacing
            }
            return (frames, CGSize(width: available, height: max(0, y - spacing)))
        default:
            var x: CGFloat = 0, height: CGFloat = 0
            for subview in subviews {
                let column = subview[BoardColumnWidth.self]
                let size = subview.sizeThatFits(ProposedViewSize(width: column, height: nil))
                frames.append(CGRect(x: x, y: 0, width: column, height: size.height))
                x += column + spacing
                height = max(height, size.height)
            }
            return (frames, CGSize(width: max(0, x - spacing), height: height))
        }
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
                        .padding(.vertical, 3).contentShape(Rectangle())
                    TextField("Titre du moment", text: field("title"), axis: .vertical)
                        .textFieldStyle(.plain).font(.title3.weight(.semibold)).lineLimit(1...4)
                }
                Spacer(minLength: 0)
                Menu { actions } label: { FA(name: "other", size: 14).frame(width: 26, height: 26).contentShape(Rectangle()) }
                    .menuStyle(.borderlessButton).menuIndicator(.hidden).fixedSize().foregroundStyle(.secondary)
                    .help("Déplacer, dupliquer ou supprimer le moment").accessibilityLabel("Actions du moment")
            }
            .boardHandle(BoardDrag(moment: moment.id), wholeCard: false).help("Glisser pour déplacer le moment")
            ExpandableText(label: "Objectifs du moment", text: field("objectives"), aiPurpose: .objectives,
                           focus: "moment:\(moment.id):objectives")
            if !hideIntentions {
                ExpandableText(label: "Intentions pédagogiques", text: field("intentions"), focus: "moment:\(moment.id):intentions")
            }
            Divider()
            HStack {
                Text("Activités").font(.subheadline.weight(.semibold)).foregroundStyle(.secondary)
                Spacer()
                Label("\(moment.duration.formatted()) min", systemImage: "clock").font(.caption.weight(.medium)).foregroundStyle(.secondary)
            }
            VStack(spacing: 12) {
                ForEach(Array(moment.activities.enumerated()), id: \.element.id) { index, _ in
                    ActivityCard(activity: $moment.activities[index], momentID: moment.id,
                        duplicate: { duplicateActivity(index) }, remove: { deleteActivity = moment.activities[index].id },
                        move: { offset in
                            let target = index + offset
                            if moment.activities.indices.contains(target) { withAnimation(.snappy) { moment.activities.swapAt(index, target) } }
                        }, canMoveUp: index > 0, canMoveDown: index + 1 < moment.activities.count)
                        .boardDrop(moment: moment.id, target: .activity(moment.activities[index].id))
                }
            }
            HStack(spacing: 8) {
                Button { withAnimation(.snappy) { moment.activities.append(.blank()) } } label: { ActionLabel(title: "Activité", icon: "plus") }
                    .studioButtons().controlSize(.small)
                    .boardDrop(moment: moment.id, target: .end)
                    .help("Déposer une activité ici pour la placer à la fin de ce moment")
                Button { withAnimation(.snappy) { notes.toggle() } } label: { ActionLabel(title: "Notes", icon: "doc") }
                    .buttonStyle(.borderless).foregroundStyle(notes ? Color.accentColor : .secondary).controlSize(.small)
                Spacer()
            }
            if notes { ExpandableText(label: "Notes de l’enseignant", text: field("notes"), focus: "moment:\(moment.id):notes") }
        }
        .environment(\.fieldAIMoment, moment)
        .padding(16)
        .studioCard(radius: 20)
        .boardHandle(BoardDrag(moment: moment.id), wholeCard: true)
        .overlay { PresenceOutline(item: moment.id, cornerRadius: 20) }
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
                Image(systemName: "line.3.horizontal").foregroundStyle(.secondary)
                    .help("Glisser pour déplacer l’activité")
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
                .boardHandle(BoardDrag(moment: momentID, activity: activity.id), wholeCard: false)
            VStack(alignment: .leading, spacing: 12) {
                if case .array(let tools) = activity.fields["tools"], !tools.isEmpty {
                    CompetencyTags(activity: $activity)
                }
                ExpandableText(label: "Description de l’activité", text: field("description"), aiPurpose: .activityDescription,
                               focus: "activity:\(activity.id):description")
                ExpandableText(label: "Consignes pour les élèves", text: field("instructions"), aiPurpose: .instructions,
                               focus: "activity:\(activity.id):instructions")
                if !activity["notes"].isEmpty {
                    ExpandableText(label: "Notes de l’activité", text: field("notes"), focus: "activity:\(activity.id):notes")
                }
            }.padding(12)
        }
        .environment(\.fieldAIActivity, activity)
        .background(StudioStyle.surface)
        .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
        .overlay(RoundedRectangle(cornerRadius: 14, style: .continuous).strokeBorder(tint.opacity(0.6), lineWidth: 1))
        .overlay { PresenceOutline(item: activity.id, cornerRadius: 14) }
        .boardHandle(BoardDrag(moment: momentID, activity: activity.id), wholeCard: true)
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
