import SwiftUI
import Charts

struct LearningSlice: Identifiable {
    let id: String
    let label: String
    let minutes: Double
    let color: Color
}

enum LearningStatistics {
    static let palette = [Color(hex: 0x477e99), Color(hex: 0x91b6a0), Color(hex: 0xb997a5), Color(hex: 0xc2c795), Color(hex: 0xaca6be), Color(hex: 0xd7bd95)]
    static func slices(_ scenario: Scenario, key: String, choices: [Choice]) -> [LearningSlice] {
        let activities = scenario.moments.flatMap(\.activities)
        return choices.enumerated().compactMap { index, choice in
            let minutes = activities.filter { $0[key] == choice.id }.reduce(0) { $0 + $1.duration }
            guard minutes > 0 else { return nil }
            return LearningSlice(id: choice.id, label: choice.label, minutes: minutes,
                                 color: key == "type" ? StudioStyle.color(choice.id) : palette[index % palette.count])
        }
    }
    static func aias(_ scenario: Scenario) -> [LearningSlice] {
        let choices = [Choice("undecided", "À définir"), Choice("not_applicable", "Non pertinent")] +
            (1...5).map { Choice(String($0), "AIAS \($0)") }
        return choices.enumerated().compactMap { index, choice in
            let minutes = scenario.moments.flatMap(\.activities).filter { $0.aiasSelection == choice.id }.reduce(0) { $0 + $1.duration }
            guard minutes > 0 else { return nil }
            return LearningSlice(id: choice.id, label: choice.label, minutes: minutes, color: palette[index % palette.count])
        }
    }
}

struct LearningAnalysis: View {
    let scenario: Scenario
    var body: some View {
        VStack(alignment: .leading, spacing: 32) {
            HStack(alignment: .firstTextBaseline) {
                SectionTitle(text: "Expérience d’apprentissage")
                Spacer()
                Label("\(scenario.duration.formatted()) min", systemImage: "clock").foregroundStyle(.secondary)
            }
            if scenario.activityCount == 0 {
                ContentUnavailableView("Rien à analyser", systemImage: "chart.pie",
                    description: Text("Ajoutez des activités pour analyser l’expérience d’apprentissage."))
            } else {
                if scenario.moments.flatMap(\.activities).contains(where: { $0["teachingMode"] == "undefined" }) {
                    Text("Certaines activités n’ont pas encore de mode d’enseignement défini.")
                        .font(.callout).foregroundStyle(.secondary)
                }
                LazyVGrid(columns: [GridItem(.adaptive(minimum: 250), spacing: 18, alignment: .top)], alignment: .leading, spacing: 18) {
                    pie("Type d’apprentissage", key: "type", choices: Pedagogy.types)
                    pie("Modalité", key: "locationMode", choices: Pedagogy.locations)
                    pie("Enseignement", key: "teachingMode", choices: Pedagogy.teaching)
                    pie("Rythme", key: "syncMode", choices: Pedagogy.rhythms)
                    pie("Évaluation", key: "evaluationMode", choices: Pedagogy.evaluations)
                }
                DistributionBar(title: "Groupe", slices: LearningStatistics.slices(scenario, key: "groupMode", choices: Pedagogy.groups))
                DistributionBar(title: "AIAS · Répartition pondérée par la durée", slices: LearningStatistics.aias(scenario))
            }
        }.padding(.vertical, 8)
    }
    private func pie(_ title: String, key: String, choices: [Choice]) -> some View {
        let rows = LearningStatistics.slices(scenario, key: key, choices: choices)
        let total = max(1, rows.reduce(0) { $0 + $1.minutes })
        return VStack(alignment: .leading, spacing: 16) {
            Text(title).font(.headline)
            Chart(rows) { row in
                SectorMark(angle: .value("Minutes", row.minutes), innerRadius: .ratio(0.62), angularInset: 1.5)
                    .cornerRadius(4).foregroundStyle(row.color)
            }.frame(height: 160).chartLegend(.hidden)
                .accessibilityLabel(title + " : " + rows.map { "\($0.label), \(($0.minutes / total * 100).formatted(.number.precision(.fractionLength(0)))) %" }.joined(separator: ", "))
            VStack(alignment: .leading, spacing: 6) {
                ForEach(rows) { row in
                    HStack(spacing: 5) {
                        Circle().fill(row.color).frame(width: 8, height: 8)
                        Text(row.label).lineLimit(2)
                        Spacer(minLength: 3)
                        Text("\((row.minutes / total * 100).formatted(.number.precision(.fractionLength(0)))) %")
                    }.font(.caption).foregroundStyle(.secondary)
                }
            }
        }.padding(16).frame(maxWidth: .infinity, minHeight: 330, alignment: .top).studioCard()
    }
}

struct DistributionBar: View {
    let title: String
    let slices: [LearningSlice]
    var body: some View {
        VStack(alignment: .leading, spacing: 14) {
            Text(title).font(.headline)
            let total = max(1, slices.reduce(0) { $0 + $1.minutes })
            GeometryReader { geometry in
                HStack(spacing: 2) {
                    ForEach(slices) { slice in
                        RoundedRectangle(cornerRadius: 4, style: .continuous).fill(slice.color)
                            .frame(width: max(2, (geometry.size.width - 2 * CGFloat(max(0, slices.count - 1))) * slice.minutes / total))
                            .help("\(slice.label) : \(slice.minutes.formatted()) min")
                    }
                }
            }.frame(height: 24)
            HStack(spacing: 18) {
                ForEach(slices) { slice in
                    HStack(spacing: 5) {
                        Circle().fill(slice.color).frame(width: 8, height: 8)
                        Text("\(slice.label) \((slice.minutes / total * 100).formatted(.number.precision(.fractionLength(0)))) %")
                    }.font(.caption).foregroundStyle(.secondary)
                }
            }
        }.padding(16).frame(maxWidth: .infinity, alignment: .leading).studioCard()
    }
}

struct TimelineLane: Identifiable {
    let key: String
    let value: String
    let label: String
    var id: String { key + ":" + value }
}

struct ChronologyView: View {
    @Binding var scenario: Scenario
    let selectMoment: (String) -> Void
    @State private var configure = false
    private var lanes: [TimelineLane] {
        let stored = scenario.fields["partitionLineConfig"]?.array
        if let stored {
            return stored.compactMap { value in
                let object = value.object
                guard object["visible"] != .bool(false), let key = object["type"]?.string, let token = object["value"]?.string else { return nil }
                return TimelineLane(key: key, value: token, label: object["label"]?.string ?? token)
            }
        }
        return Pedagogy.locations.map { TimelineLane(key: "locationMode", value: $0.id, label: $0.label) }
    }
    var body: some View {
        VStack(alignment: .leading, spacing: 28) {
            HStack(alignment: .firstTextBaseline) {
                SectionTitle(text: "Chronologie des activités")
                Spacer()
                Button { configure = true } label: { ActionLabel(title: "Configurer les lignes", icon: "settings") }
                    .studioButtons()
            }
            if scenario.activityCount == 0 {
                ContentUnavailableView("Aucune activité", systemImage: "chart.bar.xaxis",
                    description: Text("Ajoutez des activités pour afficher leur chronologie."))
            }
            ForEach(scenario.moments) { moment in
                VStack(alignment: .leading, spacing: 8) {
                    HStack {
                        Text(moment["title"].isEmpty ? "Sans titre" : moment["title"]).font(.headline)
                        Spacer()
                        Text("\(moment.duration.formatted()) min").font(.caption.weight(.medium)).foregroundStyle(.secondary)
                    }.padding(.bottom, 6)
                    ForEach(lanes) { lane in
                        HStack(spacing: 12) {
                            Text(lane.label).font(.caption).foregroundStyle(.secondary)
                                .frame(width: 110, alignment: .trailing)
                            GeometryReader { geometry in
                                HStack(spacing: 0) {
                                    ForEach(moment.activities) { activity in
                                        let width = geometry.size.width * activity.duration / max(1, moment.duration)
                                        if activity[lane.key] == lane.value {
                                            Button { selectMoment(moment.id) } label: {
                                                Text("\(Pedagogy.label(activity["type"], in: Pedagogy.types).prefix(3).uppercased()) \(activity.duration.formatted())m")
                                                    .font(.system(size: 10, weight: .semibold)).lineLimit(1)
                                                    .frame(width: max(0, width - 2), height: 28).foregroundStyle(.black.opacity(0.75))
                                                    .background(StudioStyle.color(activity["type"]), in: RoundedRectangle(cornerRadius: 7, style: .continuous))
                                                    .frame(width: width)
                                            }.buttonStyle(.plain).help(activity["description"])
                                        } else { Color.clear.frame(width: width, height: 28) }
                                    }
                                }
                            }.frame(height: 28).overlay(alignment: .bottom) { Rectangle().fill(StudioStyle.line).frame(height: 1) }
                        }
                    }
                    HStack { Text("0 %"); Spacer(); Text("25 %"); Spacer(); Text("50 %"); Spacer(); Text("75 %"); Spacer(); Text("100 %") }
                        .font(.caption2).foregroundStyle(.tertiary).padding(.leading, 122)
                }.padding(16).frame(maxWidth: .infinity, alignment: .leading).studioCard()
            }
        }
        .sheet(isPresented: $configure) { TimelineConfiguration(scenario: $scenario) }
    }
}

struct TimelineConfiguration: View {
    @Binding var scenario: Scenario
    @Environment(\.dismiss) private var dismiss
    @State private var category = "locationMode"
    private var groups: [(String, String, [Choice])] {
        [("locationMode", "Modalité", Pedagogy.locations), ("groupMode", "Groupe", Pedagogy.groups),
         ("teachingMode", "Enseignement", Pedagogy.teaching), ("syncMode", "Rythme", Pedagogy.rhythms),
         ("evaluationMode", "Évaluation", Pedagogy.evaluations)]
    }
    private var stored: [JSONValue] {
        scenario.fields["partitionLineConfig"]?.array ?? Pedagogy.locations.map {
            .object(["type": .string("locationMode"), "value": .string($0.id), "label": .string($0.label), "visible": .bool(true)])
        }
    }
    var body: some View {
        VStack(spacing: 0) {
            Form {
                Picker("Dimension", selection: $category) {
                    ForEach(groups, id: \.0) { group in Text(group.1).tag(group.0) }
                }
                Section("Lignes affichées") {
                    ForEach(groups.first { $0.0 == category }?.2 ?? []) { choice in
                        Toggle(choice.label, isOn: Binding(get: {
                            stored.contains { $0.object["type"]?.string == category && $0.object["value"]?.string == choice.id && $0.object["visible"] != .bool(false) }
                        }, set: { enabled in
                            var lines = stored.filter { !($0.object["type"]?.string == category && $0.object["value"]?.string == choice.id) }
                            lines.append(.object(["type": .string(category), "value": .string(choice.id), "label": .string(choice.label), "visible": .bool(enabled)]))
                            scenario.fields["partitionLineConfig"] = .array(lines)
                        }))
                    }
                }
            }.formStyle(.grouped)
            HStack { Spacer(); Button("Terminé") { dismiss() }.keyboardShortcut(.defaultAction).studioButtons(prominent: true) }.padding([.horizontal, .bottom], 20)
        }.frame(width: 460, height: 460)
    }
}
