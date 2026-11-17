import SwiftUI

// MARK: - Palette et symboles

enum StudioStyle {
    /// Fond des cartes : blanc en clair, gris élevé en sombre (couleur système, suit le mode).
    static let surface = Color(nsColor: .controlBackgroundColor)
    /// Fond de la zone de travail.
    static let canvas = Color(nsColor: .windowBackgroundColor)
    /// Fond discret des champs de saisie.
    static let field = Color.primary.opacity(0.055)
    static let line = Color.primary.opacity(0.09)
    static let muted = Color.secondary
    static let colors: [String: Color] = ["undefined": Color(hex: 0xc4c9d1), "read": Color(hex: 0x4fd1c5),
        "investigate": Color(hex: 0xf28b88), "practice": Color(hex: 0xb98af0), "produce": Color(hex: 0x8fd16a),
        "discuss": Color(hex: 0x6aa8f2), "collaborate": Color(hex: 0xf0b242)]
    static func color(_ type: String) -> Color { colors[type] ?? colors["undefined"]! }

    /// Noms internes → symboles SF Symbols (iconographie native de macOS).
    static let symbols: [String: String] = ["plus": "plus", "layers": "square.stack.3d.up", "import": "square.and.arrow.down",
        "export": "square.and.arrow.up", "save": "tray.and.arrow.down", "folder": "folder", "eye": "eye",
        "list": "list.bullet", "columns": "rectangle.split.3x1", "grid": "square.grid.2x2", "close": "xmark",
        "copy": "plus.square.on.square", "clock": "clock", "expand": "arrow.up.left.and.arrow.down.right",
        "chevron": "chevron.down", "arrow": "arrow.right", "back": "chevron.left", "wand": "sparkles",
        "tools": "graduationcap", "undefined": "circle.dashed", "read": "book", "investigate": "magnifyingglass",
        "practice": "pencil.and.ruler", "produce": "hammer", "discuss": "bubble.left.and.bubble.right",
        "collaborate": "person.2", "whole": "person.3", "subgroups": "rectangle.3.group", "individual": "person",
        "directed": "megaphone", "guided": "signpost.right", "supported": "lifepreserver", "independent": "figure.walk",
        "sync": "dot.radiowaves.left.and.right", "async": "calendar", "onsite": "building.columns",
        "location_based": "mappin.and.ellipse", "online": "globe", "hybrid": "rectangle.on.rectangle",
        "other": "ellipsis", "none": "circle", "diagnostic": "stethoscope", "formative": "pencil.line",
        "summative": "checkmark.seal", "certificative": "rosette", "sidebar": "sidebar.leading",
        "settings": "slider.horizontal.3", "analysis": "chart.pie", "chronology": "chart.bar.xaxis",
        "board": "rectangle.split.3x1", "moment": "square.stack", "doc": "doc.text", "sliders": "slider.horizontal.3",
        "account": "person.crop.circle"]
}

extension Color {
    init(hex: UInt32) {
        self.init(red: Double((hex >> 16) & 255) / 255, green: Double((hex >> 8) & 255) / 255, blue: Double(hex & 255) / 255)
    }
}

/// Icône SF Symbol nommée par le vocabulaire interne de l’app.
struct FA: View {
    let name: String
    var size: CGFloat = 13
    var body: some View {
        Image(systemName: StudioStyle.symbols[name] ?? StudioStyle.symbols["undefined"]!)
            .font(.system(size: size, weight: .medium)).accessibilityHidden(true)
    }
}

/// Dispose les éléments de gauche à droite et revient à la ligne selon la largeur disponible.
struct FlowLayout: Layout {
    var spacing: CGFloat = 6

    func sizeThatFits(proposal: ProposedViewSize, subviews: Subviews, cache: inout ()) -> CGSize {
        arrangement(width: proposal.width, subviews: subviews).size
    }

    func placeSubviews(in bounds: CGRect, proposal: ProposedViewSize, subviews: Subviews, cache: inout ()) {
        let layout = arrangement(width: bounds.width, subviews: subviews)
        for (index, position) in layout.positions.enumerated() {
            subviews[index].place(at: CGPoint(x: bounds.minX + position.x, y: bounds.minY + position.y),
                                  anchor: .topLeading, proposal: ProposedViewSize(layout.sizes[index]))
        }
    }

    private func arrangement(width: CGFloat?, subviews: Subviews) -> (size: CGSize, positions: [CGPoint], sizes: [CGSize]) {
        let available = width.flatMap { $0.isFinite ? max(0, $0) : nil } ?? .greatestFiniteMagnitude
        var positions: [CGPoint] = []
        var sizes: [CGSize] = []
        var x: CGFloat = 0
        var y: CGFloat = 0
        var rowHeight: CGFloat = 0
        var usedWidth: CGFloat = 0
        for subview in subviews {
            let ideal = subview.sizeThatFits(.unspecified)
            let size = subview.sizeThatFits(ProposedViewSize(width: min(ideal.width, available), height: nil))
            if x > 0 && x + size.width > available {
                x = 0
                y += rowHeight + spacing
                rowHeight = 0
            }
            positions.append(CGPoint(x: x, y: y))
            sizes.append(size)
            usedWidth = max(usedWidth, x + size.width)
            rowHeight = max(rowHeight, size.height)
            x += size.width + spacing
        }
        return (CGSize(width: usedWidth, height: y + rowHeight), positions, sizes)
    }
}

// MARK: - Liquid Glass et surfaces

extension View {
    /// Surface Liquid Glass sur macOS 26, matériau translucide avant.
    @ViewBuilder func glassSurface(radius: CGFloat = 18, interactive: Bool = false) -> some View {
        if #available(macOS 26, *) {
            self.glassEffect(interactive ? .regular.interactive() : .regular, in: .rect(cornerRadius: radius))
        } else {
            self.background(.regularMaterial, in: RoundedRectangle(cornerRadius: radius, style: .continuous))
                .overlay(RoundedRectangle(cornerRadius: radius, style: .continuous).strokeBorder(Color.primary.opacity(0.08)))
        }
    }
    @ViewBuilder func glassCapsule(interactive: Bool = false) -> some View {
        if #available(macOS 26, *) {
            self.glassEffect(interactive ? .regular.interactive() : .regular, in: .capsule)
        } else {
            self.background(.regularMaterial, in: Capsule()).overlay(Capsule().strokeBorder(Color.primary.opacity(0.08)))
        }
    }
    /// Boutons de commande : verre sur macOS 26, boutons bordés avant.
    @ViewBuilder func studioButtons(prominent: Bool = false) -> some View {
        if #available(macOS 26, *) {
            if prominent { self.buttonStyle(.glassProminent) } else { self.buttonStyle(.glass) }
        } else {
            if prominent { self.buttonStyle(.borderedProminent) } else { self.buttonStyle(.bordered) }
        }
    }
    /// Carte de contenu : fond système, bord à peine visible et ombre douce (le verre est réservé aux contrôles).
    func studioCard(radius: CGFloat = 16, shadow: Bool = true) -> some View {
        self.background(StudioStyle.surface, in: RoundedRectangle(cornerRadius: radius, style: .continuous))
            .overlay(RoundedRectangle(cornerRadius: radius, style: .continuous).strokeBorder(Color.primary.opacity(0.07)))
            .shadow(color: .black.opacity(shadow ? 0.07 : 0), radius: 12, y: 4)
    }
    /// Légère élévation au survol, pour les cartes cliquables.
    func hoverLift() -> some View { modifier(HoverLift()) }
}

private struct HoverLift: ViewModifier {
    @State private var hovering = false
    func body(content: Content) -> some View {
        content
            .scaleEffect(hovering ? 1.012 : 1)
            .shadow(color: .black.opacity(hovering ? 0.12 : 0), radius: 16, y: 8)
            .animation(.spring(duration: 0.28, bounce: 0.25), value: hovering)
            .onHover { hovering = $0 }
    }
}

// MARK: - Composants

struct ActionLabel: View {
    let title: String
    let icon: String
    var body: some View { Label { Text(title) } icon: { FA(name: icon, size: 12) } }
}

struct IconButton: View {
    let title: String
    let icon: String
    var size: CGFloat = 12
    let action: () -> Void
    @State private var hovering = false
    var body: some View {
        Button(action: action) {
            FA(name: icon, size: size).frame(width: 26, height: 26)
                .background(hovering ? Color.primary.opacity(0.09) : .clear, in: RoundedRectangle(cornerRadius: 7, style: .continuous))
                .contentShape(Rectangle())
        }
        .buttonStyle(.plain).foregroundStyle(.secondary).help(title).accessibilityLabel(title)
        .onHover { hovering = $0 }
        .animation(.easeOut(duration: 0.12), value: hovering)
    }
}

/// L’accueil et l’aide reprennent le même fichier que l’icône de l’application.
struct BrandMark: View {
    var body: some View {
        let icon = Bundle.main.url(forResource: "AppIcon", withExtension: "icns")
            .flatMap { NSImage(contentsOf: $0) } ?? NSApplication.shared.applicationIconImage ?? NSImage()
        Image(nsImage: icon).resizable().scaledToFit().accessibilityHidden(true)
    }
}

struct StudioField: View {
    let label: String
    @Binding var text: String
    var multiline = false
    var focusOnAppear = false
    var onFocusChange: ((Bool) -> Void)? = nil
    @FocusState private var focused: Bool
    var body: some View {
        TextField(label, text: $text, axis: multiline ? .vertical : .horizontal)
            .lineLimit(multiline ? 2...20 : 1...1)
            .textFieldStyle(.plain).multilineTextAlignment(.leading).focused($focused)
            .padding(.horizontal, 10).padding(.vertical, 8).frame(maxWidth: .infinity, alignment: .leading)
            .background(StudioStyle.field, in: RoundedRectangle(cornerRadius: 9, style: .continuous))
            .overlay(RoundedRectangle(cornerRadius: 9, style: .continuous)
                .strokeBorder(Color.accentColor.opacity(focused ? 0.65 : 0), lineWidth: 2))
            .animation(.easeOut(duration: 0.15), value: focused)
            .accessibilityLabel(label)
            .onAppear { if focusOnAppear { focused = true } }
            .onChange(of: focused) { _, value in onFocusChange?(value) }
    }
}

struct ExpandableText: View {
    let label: String
    @Binding var text: String
    var showLabel = true
    var aiPurpose: FieldAIPurpose? = nil
    @State private var expanded = false
    @State private var preview = true
    var body: some View {
        VStack(alignment: .leading, spacing: 6) {
            if showLabel {
                HStack(spacing: 4) {
                    Text(label).font(.subheadline.weight(.medium)).foregroundStyle(.secondary)
                    Spacer()
                    Button(preview ? "Modifier" : "Aperçu") { withAnimation(.snappy) { preview.toggle() } }
                        .buttonStyle(.borderless).font(.caption).foregroundStyle(.secondary)
                        .help(preview ? "Revenir à la saisie" : "Afficher le rendu Markdown et LaTeX")
                    if let aiPurpose { FieldAIButton(label: label, text: $text, purpose: aiPurpose) }
                    IconButton(title: "Agrandir \(label)", icon: "expand", size: 10) { expanded = true }
                }
            }
            if preview && !text.isEmpty {
                MarkdownPreview(text: text)
                    .padding(10).frame(maxWidth: .infinity, alignment: .leading)
                    .background(StudioStyle.field.opacity(0.6), in: RoundedRectangle(cornerRadius: 9, style: .continuous))
                    .contentShape(Rectangle())
                    .onTapGesture(count: 2) { withAnimation(.snappy) { preview = false } }
            } else {
                StudioField(label: label, text: $text, multiline: true, focusOnAppear: !preview,
                            onFocusChange: { preview = !$0 })
                    .overlay(alignment: .topTrailing) {
                        if !showLabel {
                            IconButton(title: "Agrandir \(label)", icon: "expand", size: 10) { expanded = true }.padding(3)
                        }
                    }
            }
        }
        .sheet(isPresented: $expanded, onDismiss: { preview = true }) {
            MarkdownEditorSheet(label: label, text: $text, aiPurpose: aiPurpose) { expanded = false }
        }
    }
}

/// Bande de proportions des types d’activité, arrondie.
struct TypeStrip: View {
    let activities: [Activity]
    var height: CGFloat = 6
    var body: some View {
        GeometryReader { geometry in
            HStack(spacing: 2) {
                if activities.isEmpty { Capsule().fill(StudioStyle.color("undefined").opacity(0.5)) }
                else {
                    let total = max(1, activities.reduce(0) { $0 + $1.duration })
                    let available = max(0, geometry.size.width - 2 * CGFloat(activities.count - 1))
                    ForEach(activities) { activity in
                        Capsule().fill(StudioStyle.color(activity["type"]))
                            .frame(width: max(2, available * activity.duration / total))
                    }
                }
            }
        }.frame(height: height).accessibilityHidden(true)
    }
}

/// Pastille d’un type d’activité : symbole sur fond teinté.
struct TypeBadge: View {
    let type: String
    var size: CGFloat = 26
    var body: some View {
        FA(name: type, size: size * 0.46).foregroundStyle(.white)
            .frame(width: size, height: size)
            .background(LinearGradient(colors: [StudioStyle.color(type), StudioStyle.color(type).opacity(0.78)],
                                       startPoint: .top, endPoint: .bottom), in: RoundedRectangle(cornerRadius: size * 0.28, style: .continuous))
    }
}

/// Titre de section de contenu, dans le style des réglages macOS.
struct SectionTitle: View {
    let text: String
    var body: some View { Text(text).font(.title2.weight(.semibold)) }
}
