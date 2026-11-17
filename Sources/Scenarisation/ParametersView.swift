import SwiftUI

struct ParametersView: View {
    @Binding var scenario: Scenario
    private func text(_ key: String) -> Binding<String> {
        Binding(get: {
            if case .number(let value) = scenario.meta[key] { return value.formatted(.number.grouping(.never)) }
            return scenario.meta[key]?.string ?? ""
        }, set: { scenario.meta[key] = .string($0) })
    }
    private func number(_ key: String, fallback: Double = 0, minimum: Double = 0) -> Binding<Double> {
        Binding(get: { scenario.meta[key]?.number ?? fallback }, set: { scenario.meta[key] = .number(max(minimum, min(100_000, $0))) })
    }
    var body: some View {
        GeometryReader { geometry in
            let contentWidth = min(max(0, geometry.size.width - 56), 1240)
            let twoColumns = contentWidth >= 920
            let layout = twoColumns ? AnyLayout(HStackLayout(alignment: .top, spacing: 24)) :
                AnyLayout(VStackLayout(alignment: .leading, spacing: 24))
            ScrollView {
                VStack(alignment: .leading, spacing: 28) {
                    VStack(alignment: .leading, spacing: 10) {
                        Text("Paramètres du scénario").font(.subheadline).foregroundStyle(.secondary)
                        TextField("Titre du scénario", text: text("name"), axis: .vertical)
                            .textFieldStyle(.plain).font(.system(size: 30, weight: .bold))
                            .accessibilityLabel("Titre du scénario")
                    }
                    layout {
                        mainColumn.frame(maxWidth: .infinity, alignment: .topLeading)
                        detailsColumn.frame(width: twoColumns ? min(360, contentWidth * 0.33) : nil)
                    }
                }
                .frame(width: contentWidth, alignment: .leading)
                .padding(.vertical, 28)
                .frame(maxWidth: .infinity, alignment: .center)
            }
        }
    }

    private var mainColumn: some View {
        VStack(alignment: .leading, spacing: 24) {
            parameterCard {
                ExpandableText(label: "Description", text: text("description"))
            }
            parameterCard {
                ExpandableText(label: "Commande institutionnelle", text: text("command"))
            }
            parameterCard {
                ExpandableText(label: "Objectifs de la formation", text: text("personas"), aiPurpose: .objectives)
            }
            parameterCard("Acquis d’apprentissage") {
                BloomOutcomes(scenario: $scenario)
            }
        }
    }

    private var detailsColumn: some View {
        VStack(alignment: .leading, spacing: 24) {
            parameterCard("Durée") {
                VStack(alignment: .leading, spacing: 8) {
                    fieldLabel("Durée prévue")
                    HStack(spacing: 12) {
                        timeInput("Jours prévus", value: number("learningDays"), unit: "j")
                        timeInput("Heures prévues", value: number("learningHours"), unit: "h")
                        timeInput("Minutes prévues", value: number("learningMinutes"), unit: "min")
                    }
                }
                Divider()
                LabeledContent("Durée conçue") {
                    let dayMinutes = max(60, (scenario.meta["dayHours"]?.number ?? 7) * 60)
                    let total = scenario.duration
                    Text("\(Int(total / dayMinutes)) j  \(Int(total.truncatingRemainder(dividingBy: dayMinutes) / 60)) h  \(Int(total.truncatingRemainder(dividingBy: 60))) min")
                        .foregroundStyle(.secondary).monospacedDigit()
                }
                LabeledContent("1 jour =") {
                    timeInput("Heures par jour", value: number("dayHours", fallback: 7, minimum: 1), unit: "heures")
                }
            }
            parameterCard("Contexte") {
                select("Mode", selection: text("modeDelivery"), choices: [Choice("", "À définir"), Choice("onsite", "Présentiel"), Choice("online", "Distanciel"), Choice("hybrid", "Hybride")])
                if case .success(let catalog) = SchoolCatalog.bundled {
                    select("Système / classification", selection: Binding(get: { text("schoolSystem").wrappedValue }, set: {
                        scenario.meta["schoolSystem"] = .string($0); scenario.meta["schoolLevel"] = .string("")
                    }), choices: [Choice("", "À définir")] + catalog.systems.map(\.choice))
                    select("Niveau", selection: text("schoolLevel"), choices: [Choice("", "À définir")] +
                        (catalog.levels[text("schoolSystem").wrappedValue] ?? []).map(\.choice))
                        .disabled(text("schoolSystem").wrappedValue.isEmpty)
                }
                Divider()
                contextField("Taille du groupe", key: "sizeClass", prompt: "Ex. 24 élèves")
                contextField("Concepteur(s)", key: "designers", prompt: "Facultatif")
                contextField("Enseignant(s)", key: "trainers", prompt: "Facultatif")
            }
            parameterCard {
                ExpandableText(label: "Matériel enseignant", text: text("teacherMaterials"))
            }
        }
    }

    private func parameterCard<Content: View>(_ title: String? = nil, @ViewBuilder content: () -> Content) -> some View {
        VStack(alignment: .leading, spacing: 16) {
            if let title {
                Text(title).font(.headline).accessibilityAddTraits(.isHeader)
            }
            content()
        }
        .padding(20).frame(maxWidth: .infinity, alignment: .leading)
        .studioCard(shadow: false)
    }

    private func fieldLabel(_ title: String) -> some View {
        Text(title).font(.subheadline.weight(.medium)).foregroundStyle(.secondary)
    }

    private func contextField(_ title: String, key: String, prompt: String) -> some View {
        VStack(alignment: .leading, spacing: 6) {
            fieldLabel(title)
            TextField(title, text: text(key), prompt: Text(prompt), axis: .vertical)
                .textFieldStyle(.plain).accessibilityLabel(title)
                .padding(.horizontal, 10).padding(.vertical, 8)
                .frame(maxWidth: .infinity, alignment: .leading)
                .background(StudioStyle.field, in: RoundedRectangle(cornerRadius: 9, style: .continuous))
        }
    }

    private func timeInput(_ label: String, value: Binding<Double>, unit: String) -> some View {
        HStack(spacing: 4) {
            TextField(label, value: value, format: .number)
                .textFieldStyle(.roundedBorder).labelsHidden()
                .multilineTextAlignment(.trailing).frame(width: 40).accessibilityLabel(label)
            Text(unit).foregroundStyle(.secondary)
        }
    }
    private func select(_ label: String, selection: Binding<String>, choices: [Choice]) -> some View {
        VStack(alignment: .leading, spacing: 6) {
            fieldLabel(label)
            Picker(label, selection: selection) {
                if !choices.contains(where: { $0.id == selection.wrappedValue }) { Text(selection.wrappedValue).tag(selection.wrappedValue) }
                ForEach(choices) { Text($0.label).tag($0.id) }
            }.labelsHidden().frame(maxWidth: .infinity, alignment: .leading)
        }
    }
}

struct ChoicePicker: View {
    let title: String
    @Binding var selection: String
    let choices: [Choice]
    var body: some View {
        HStack {
            Text(title)
            Spacer()
            Picker(title, selection: $selection) {
                if !choices.contains(where: { $0.id == selection }) { Text(selection.isEmpty ? "À définir" : selection).tag(selection) }
                ForEach(choices) { Text($0.label).tag($0.id) }
            }.labelsHidden().frame(width: 245)
        }
    }
}

struct BloomCategory: Decodable, Identifiable, Sendable {
    let id: String
    let label: String
    let verbs: [String]
    static let bundled: Result<[BloomCategory], Error> = Result {
        guard let url = Bundle.module.url(forResource: "bloom", withExtension: "json") else { throw ScenarioError.invalid("Taxonomie de Bloom absente.") }
        return try JSONDecoder().decode([BloomCategory].self, from: Data(contentsOf: url))
    }
}

struct BloomOutcomes: View {
    @Binding var scenario: Scenario
    @State private var editing: Int?
    @State private var showEditor = false
    private var outcomes: [JSONValue] { scenario.meta["sliders"]?.array ?? [] }
    private func outcomeText(_ index: Int) -> Binding<String> {
        Binding(get: { outcomes.indices.contains(index) ? outcomes[index].object["text"]?.string ?? "" : "" }, set: { text in
            var values = outcomes
            guard values.indices.contains(index) else { return }
            var object = values[index].object; object["text"] = .string(text)
            values[index] = .object(object); scenario.meta["sliders"] = .array(values)
        })
    }
    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            if outcomes.isEmpty {
                Text("Formulez un acquis observable à partir de la taxonomie de Bloom.").font(.callout).foregroundStyle(.secondary)
            }
            ForEach(outcomes.indices, id: \.self) { index in
                HStack(alignment: .firstTextBaseline, spacing: 10) {
                    Button {
                        editing = index; showEditor = true
                    } label: {
                        let verb = outcomes[index].object["verb"]?.string ?? ""
                        Text(verb.isEmpty ? "Bloom" : verb).font(.system(size: 11, weight: .semibold))
                            .padding(.horizontal, 10).padding(.vertical, 4)
                            .background(Color.accentColor.opacity(0.14), in: Capsule()).foregroundStyle(Color.accentColor)
                    }.buttonStyle(.plain).help("Modifier le niveau de Bloom et le verbe")
                    TextField("L’élève sera capable de…", text: outcomeText(index), axis: .vertical)
                        .textFieldStyle(.plain).multilineTextAlignment(.leading)
                    FieldAIButton(label: "Acquis d’apprentissage", text: outcomeText(index), purpose: .outcome(
                        category: outcomes[index].object["categoryLabel"]?.string ?? "",
                        verb: outcomes[index].object["verb"]?.string ?? ""))
                    IconButton(title: "Retirer l’acquis", icon: "close", size: 10) {
                        var values = outcomes; values.remove(at: index); scenario.meta["sliders"] = .array(values)
                    }
                }
            }
            Button { editing = nil; showEditor = true } label: { ActionLabel(title: "Ajouter un acquis", icon: "plus") }
                .buttonStyle(.borderless)
        }
        .sheet(isPresented: $showEditor) {
            BloomEditor(original: editing.flatMap { outcomes.indices.contains($0) ? outcomes[$0].object : nil }) { outcome in
                var values = outcomes
                if let editing, values.indices.contains(editing) { values[editing] = .object(outcome) }
                else { values.append(.object(outcome)) }
                scenario.meta["sliders"] = .array(values); showEditor = false
            }
        }
    }
}

struct BloomEditor: View {
    let original: [String: JSONValue]?
    let save: ([String: JSONValue]) -> Void
    @Environment(\.dismiss) private var dismiss
    @State private var category: String
    @State private var verb: String
    @State private var text: String
    init(original: [String: JSONValue]?, save: @escaping ([String: JSONValue]) -> Void) {
        self.original = original; self.save = save
        _category = State(initialValue: original?["category"]?.string ?? "comprendre")
        _verb = State(initialValue: original?["verb"]?.string ?? "")
        _text = State(initialValue: original?["text"]?.string ?? "")
    }
    var body: some View {
        VStack(alignment: .leading, spacing: 18) {
            Text("Acquis d’apprentissage").font(.title2.bold())
            if case .success(let categories) = BloomCategory.bundled {
                Picker("Niveau de Bloom", selection: $category) {
                    ForEach(categories) { Text($0.label).tag($0.id) }
                }.pickerStyle(.segmented).labelsHidden()
                Text("Choisir un verbe").font(.headline)
                let verbs = categories.first { $0.id == category }?.verbs ?? []
                LazyVGrid(columns: [GridItem(.adaptive(minimum: 124), spacing: 8)], alignment: .leading, spacing: 8) {
                    ForEach(verbs, id: \.self) { value in
                        Button { verb = value } label: {
                            Text(value).frame(maxWidth: .infinity, alignment: .leading).padding(.horizontal, 10).padding(.vertical, 7)
                                .background(verb == value ? Color.accentColor.opacity(0.18) : Color.primary.opacity(0.05),
                                            in: RoundedRectangle(cornerRadius: 8, style: .continuous))
                                .overlay(RoundedRectangle(cornerRadius: 8, style: .continuous)
                                    .strokeBorder(verb == value ? Color.accentColor : .clear, lineWidth: 1.5))
                        }.buttonStyle(.plain)
                    }
                }
                StudioField(label: "Verbe", text: $verb)
                ExpandableText(label: "L’élève sera capable de…", text: $text, aiPurpose: .outcome(
                    category: categories.first { $0.id == category }?.label ?? category, verb: verb))
                HStack {
                    Button("Annuler") { dismiss() }.keyboardShortcut(.cancelAction).studioButtons()
                    Spacer()
                    Button("Enregistrer") {
                        var object = original ?? ["id": .string(UUID().uuidString)]
                        object["category"] = .string(category)
                        object["categoryLabel"] = .string(categories.first { $0.id == category }?.label ?? "")
                        object["verb"] = .string(verb); object["text"] = .string(text)
                        save(object)
                    }.disabled(verb.isEmpty || text.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty)
                        .keyboardShortcut(.defaultAction).studioButtons(prominent: true)
                }
            } else { Text("Impossible de charger la taxonomie de Bloom.").foregroundStyle(.red) }
        }.padding(26).frame(width: 650)
    }
}
