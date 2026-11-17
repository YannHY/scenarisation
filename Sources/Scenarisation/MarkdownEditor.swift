import AppKit
import SwiftUI

// MARK: - Actions de mise en forme

/// Les actions et raccourcis de la barre Markdown du site.
enum MarkdownAction: String, CaseIterable, Identifiable {
    case bold, italic, heading, list, orderedList, quote, link, math
    var id: String { rawValue }

    var title: String {
        switch self {
        case .bold: "Gras"; case .italic: "Italique"; case .heading: "Titre"; case .list: "Liste à puces"
        case .orderedList: "Liste numérotée"; case .quote: "Citation"; case .link: "Lien"; case .math: "Formule mathématique"
        }
    }
    var symbol: String {
        switch self {
        case .bold: "bold"; case .italic: "italic"; case .heading: "textformat.size"; case .list: "list.bullet"
        case .orderedList: "list.number"; case .quote: "text.quote"; case .link: "link"; case .math: "sum"
        }
    }
    /// Touche et présence de Maj, comme sur le site (⌘B, ⌘I, ⌘K, ⇧⌘H, ⇧⌘L, ⇧⌘7, ⇧⌘Q, ⇧⌘M).
    var key: (character: String, shift: Bool) {
        switch self {
        case .bold: ("b", false); case .italic: ("i", false); case .link: ("k", false); case .heading: ("h", true)
        case .list: ("l", true); case .orderedList: ("7", true); case .quote: ("q", true); case .math: ("m", true)
        }
    }
    var shortcutLabel: String { (key.shift ? "⇧⌘" : "⌘") + key.character.uppercased() }

    static func matching(_ event: NSEvent) -> MarkdownAction? {
        let flags = event.modifierFlags.intersection(.deviceIndependentFlagsMask)
        guard flags.contains(.command), !flags.contains(.option), !flags.contains(.control) else { return nil }
        let shift = flags.contains(.shift)
        let character = (event.charactersIgnoringModifiers ?? "").lowercased()
        return allCases.first { action in
            guard action.key.shift == shift else { return false }
            // Le 7 se repère à sa touche physique : sur un clavier AZERTY, Maj est nécessaire pour l’obtenir.
            if action == .orderedList { return event.keyCode == 26 || character == "7" }
            return character == action.key.character
        }
    }
}

// MARK: - Contrôleur d’édition

/// Relie la barre d’outils au champ de texte natif. Toutes les modifications passent par
/// NSTextView : elles sont annulables (⌘Z) et mettent à jour le document.
@MainActor final class MarkdownEditorController: ObservableObject {
    weak var textView: NSTextView?
    @Published var mathRequest: NSRange?

    private var string: NSString { (textView?.string ?? "") as NSString }

    func perform(_ action: MarkdownAction) {
        guard let textView else { return }
        textView.window?.makeFirstResponder(textView)
        switch action {
        case .bold: wrap("**", "**", placeholder: "texte en gras")
        case .italic: wrap("*", "*", placeholder: "texte en italique")
        case .heading: prefixLines { _ in "## " }
        case .list: prefixLines { _ in "- " }
        case .orderedList: prefixLines { "\($0 + 1). " }
        case .quote: prefixLines { _ in "> " }
        case .link: insertLink()
        case .math: mathRequest = textView.selectedRange()
        }
    }

    func replace(_ range: NSRange, with replacement: String, select selection: NSRange) {
        guard let textView, textView.shouldChangeText(in: range, replacementString: replacement) else { return }
        textView.textStorage?.replaceCharacters(in: range, with: replacement)
        textView.didChangeText()
        textView.setSelectedRange(selection)
        textView.scrollRangeToVisible(selection)
    }

    private func wrap(_ before: String, _ after: String, placeholder: String) {
        guard let range = textView?.selectedRange() else { return }
        let content = range.length > 0 ? string.substring(with: range) : placeholder
        replace(range, with: before + content + after,
                select: NSRange(location: range.location + before.utf16.count, length: content.utf16.count))
    }

    /// Préfixe les lignes sélectionnées, ou la ligne courante ; sur une ligne vide, insère un exemple sélectionné.
    private func prefixLines(_ prefix: (Int) -> String) {
        guard let selection = textView?.selectedRange() else { return }
        let lines = string.lineRange(for: selection)
        var block = string.substring(with: lines)
        let trailingNewline = block.hasSuffix("\n")
        if trailingNewline { block.removeLast() }
        if block.trimmingCharacters(in: .whitespaces).isEmpty {
            let placeholder = prefix(0) == "## " ? "Titre" : prefix(0) == "> " ? "citation" : "élément de liste"
            let text = prefix(0) + placeholder
            let range = NSRange(location: lines.location, length: (block as NSString).length)
            replace(range, with: text, select: NSRange(location: lines.location + prefix(0).utf16.count, length: placeholder.utf16.count))
            return
        }
        let result = block.components(separatedBy: "\n").enumerated().map { prefix($0.offset) + $0.element }.joined(separator: "\n")
        let range = NSRange(location: lines.location, length: (block as NSString).length)
        replace(range, with: result, select: NSRange(location: lines.location, length: (result as NSString).length))
    }

    private func insertLink() {
        guard let range = textView?.selectedRange() else { return }
        let label = range.length > 0 ? string.substring(with: range) : "texte du lien"
        let url = "https://"
        let replacement = "[\(label)](\(url))"
        replace(range, with: replacement, select: NSRange(location: range.location + label.utf16.count + 3, length: url.utf16.count))
    }

    /// Texte sélectionné au moment d’ouvrir l’assistant, débarrassé de ses délimiteurs.
    func selectedFormula() -> (code: String, block: Bool) {
        guard let range = mathRequest, range.location + range.length <= string.length else { return ("", false) }
        let selected = string.substring(with: range).trimmingCharacters(in: .whitespacesAndNewlines)
        let block = selected.hasPrefix("$$") && selected.hasSuffix("$$") && selected.count >= 4
        var code = selected
        for delimiter in block ? ["$$"] : ["$"] where code.hasPrefix(delimiter) && code.hasSuffix(delimiter) && code.count >= 2 * delimiter.count {
            code = String(code.dropFirst(delimiter.count).dropLast(delimiter.count))
        }
        return (code.trimmingCharacters(in: .whitespacesAndNewlines), block)
    }

    func insertFormula(_ code: String, block: Bool) {
        guard let range = mathRequest, range.location + range.length <= string.length else { mathRequest = nil; return }
        let before = string.substring(to: range.location)
        let after = string.substring(from: range.location + range.length)
        let leading = block && !before.isEmpty && !before.hasSuffix("\n") ? "\n\n" : ""
        let trailing = block && !after.isEmpty && !after.hasPrefix("\n") ? "\n\n" : ""
        let replacement = block ? "\(leading)$$\n\(code)\n$$\(trailing)" : "$\(code)$"
        mathRequest = nil
        textView?.window?.makeFirstResponder(textView)
        let caret = range.location + (replacement as NSString).length - (trailing as NSString).length
        replace(range, with: replacement, select: NSRange(location: caret, length: 0))
    }

    /// Entrée dans une liste : nouvelle puce ou numéro suivant ; sur un élément vide, termine la liste.
    func continueList() -> Bool {
        guard let textView, textView.selectedRange().length == 0 else { return false }
        let caret = textView.selectedRange().location
        let lineRange = string.lineRange(for: NSRange(location: caret, length: 0))
        let beforeCaret = string.substring(with: NSRange(location: lineRange.location, length: caret - lineRange.location))
        let pattern = try! NSRegularExpression(pattern: #"^(\s*)([-*+]|\d+[.)])\s+(.*)$"#)
        guard let match = pattern.firstMatch(in: beforeCaret, range: NSRange(location: 0, length: (beforeCaret as NSString).length)) else { return false }
        let ns = beforeCaret as NSString
        let indent = ns.substring(with: match.range(at: 1))
        let marker = ns.substring(with: match.range(at: 2))
        var line = string.substring(with: lineRange)
        if line.hasSuffix("\n") { line.removeLast() }
        let content = line.replacingOccurrences(of: #"^\s*([-*+]|\d+[.)])\s*"#, with: "", options: .regularExpression)
        if content.trimmingCharacters(in: .whitespaces).isEmpty {
            let range = NSRange(location: lineRange.location, length: (line as NSString).length)
            replace(range, with: indent, select: NSRange(location: lineRange.location + indent.utf16.count, length: 0))
            return true
        }
        var next = marker
        if let number = Int(marker.dropLast()) { next = "\(number + 1)\(marker.last!)" }
        let insertion = "\n\(indent)\(next) "
        replace(NSRange(location: caret, length: 0), with: insertion, select: NSRange(location: caret + insertion.utf16.count, length: 0))
        return true
    }
}

// MARK: - Champ de texte natif

final class MarkdownNSTextView: NSTextView {
    weak var controller: MarkdownEditorController?
    var placeholder = ""
    var focusOnAppear = true

    /// Le champ prend le focus dès que la feuille est affichée, pour que la saisie et les raccourcis s’y appliquent.
    override func viewDidMoveToWindow() {
        super.viewDidMoveToWindow()
        guard focusOnAppear, let window else { return }
        focusOnAppear = false
        DispatchQueue.main.async { [weak self] in
            guard let self else { return }
            window.makeFirstResponder(self)
            self.setSelectedRange(NSRange(location: (self.string as NSString).length, length: 0))
        }
    }

    override func performKeyEquivalent(with event: NSEvent) -> Bool {
        if window?.firstResponder === self, let action = MarkdownAction.matching(event), let controller {
            controller.perform(action)
            return true
        }
        return super.performKeyEquivalent(with: event)
    }

    override func draw(_ dirtyRect: NSRect) {
        super.draw(dirtyRect)
        guard string.isEmpty, !placeholder.isEmpty else { return }
        let origin = NSPoint(x: textContainerInset.width + (textContainer?.lineFragmentPadding ?? 0), y: textContainerInset.height)
        (placeholder as NSString).draw(at: origin, withAttributes: [.font: font ?? .systemFont(ofSize: 15), .foregroundColor: NSColor.placeholderTextColor])
    }
}

struct MarkdownTextView: NSViewRepresentable {
    @Binding var text: String
    let controller: MarkdownEditorController
    var placeholder = ""
    var fontSize: CGFloat = 15

    func makeCoordinator() -> Coordinator { Coordinator(self) }

    func makeNSView(context: Context) -> NSScrollView {
        let scroll = MarkdownNSTextView.scrollableTextView()
        let old = scroll.documentView as! NSTextView
        let textView = MarkdownNSTextView(frame: old.frame, textContainer: old.textContainer)
        textView.autoresizingMask = old.autoresizingMask
        textView.minSize = old.minSize
        textView.maxSize = old.maxSize
        textView.isVerticallyResizable = true
        textView.isHorizontallyResizable = false
        scroll.documentView = textView
        scroll.drawsBackground = false
        scroll.hasVerticalScroller = true
        scroll.autohidesScrollers = true
        textView.drawsBackground = false
        textView.isRichText = false
        textView.importsGraphics = false
        textView.allowsUndo = true
        textView.isAutomaticQuoteSubstitutionEnabled = false
        textView.isAutomaticDashSubstitutionEnabled = false
        textView.isAutomaticTextReplacementEnabled = false
        textView.isContinuousSpellCheckingEnabled = true
        textView.font = .systemFont(ofSize: fontSize)
        textView.textColor = .labelColor
        textView.textContainerInset = NSSize(width: 8, height: 10)
        textView.placeholder = placeholder
        textView.string = text
        textView.delegate = context.coordinator
        textView.controller = controller
        textView.setAccessibilityLabel(placeholder)
        controller.textView = textView
        return scroll
    }

    func updateNSView(_ scroll: NSScrollView, context: Context) {
        context.coordinator.parent = self
        guard let textView = scroll.documentView as? NSTextView, textView.string != text else { return }
        let selection = textView.selectedRange()
        textView.string = text
        let length = (text as NSString).length
        textView.setSelectedRange(NSRange(location: min(selection.location, length), length: 0))
    }

    @MainActor final class Coordinator: NSObject, NSTextViewDelegate {
        var parent: MarkdownTextView
        init(_ parent: MarkdownTextView) { self.parent = parent }

        func textDidChange(_ notification: Notification) {
            guard let textView = notification.object as? NSTextView else { return }
            if parent.text != textView.string { parent.text = textView.string }
        }

        func textView(_ textView: NSTextView, doCommandBy selector: Selector) -> Bool {
            if selector == #selector(NSResponder.insertNewline(_:)),
               !(NSApp.currentEvent?.modifierFlags.contains(.shift) ?? false) {
                return parent.controller.continueList()
            }
            return false
        }
    }
}

// MARK: - Barre d’outils

struct MarkdownToolbar: View {
    @ObservedObject var controller: MarkdownEditorController
    var body: some View {
        HStack(spacing: 4) {
            ForEach(MarkdownAction.allCases) { action in
                if action == .link || action == .math { Divider().frame(height: 18).padding(.horizontal, 3) }
                ToolbarKey(action: action) { controller.perform(action) }
                    .popover(isPresented: action == .math ? mathBinding : .constant(false), arrowEdge: .bottom) {
                        MathAssistant(controller: controller)
                    }
            }
        }
        .accessibilityElement(children: .contain)
        .accessibilityLabel("Barre de formatage Markdown")
    }

    private var mathBinding: Binding<Bool> {
        Binding(get: { controller.mathRequest != nil }, set: { if !$0 { controller.mathRequest = nil } })
    }
}

private struct ToolbarKey: View {
    let action: MarkdownAction
    let perform: () -> Void
    @State private var hovering = false
    var body: some View {
        Button(action: perform) {
            Image(systemName: action.symbol).font(.system(size: 13, weight: .semibold))
                .frame(width: 30, height: 26)
                .background(Color.primary.opacity(hovering ? 0.1 : 0.05), in: RoundedRectangle(cornerRadius: 7, style: .continuous))
                .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .help("\(action.title) (\(action.shortcutLabel))")
        .accessibilityLabel(action.title)
        .onHover { hovering = $0 }
    }
}

// MARK: - Assistant LaTeX

struct MathTemplate: Identifiable {
    let label: String
    let code: String
    var id: String { code }
}

enum MathPalette {
    static let categories: [(name: String, items: [MathTemplate])] = [
        ("Essentiels", [.init(label: "Fraction", code: "\\frac{a}{b}"), .init(label: "Racine carrée", code: "\\sqrt{x}"),
            .init(label: "Puissance", code: "x^{n}"), .init(label: "Indice", code: "x_{n}"),
            .init(label: "Parenthèses ajustables", code: "\\left(x\\right)"), .init(label: "Valeur absolue", code: "\\left|x\\right|")]),
        ("Opérateurs", [.init(label: "Somme", code: "\\sum_{i=1}^{n}"), .init(label: "Produit", code: "\\prod_{i=1}^{n}"),
            .init(label: "Intégrale", code: "\\int_{a}^{b} f(x)\\,dx"), .init(label: "Limite", code: "\\lim_{x \\to a} f(x)"),
            .init(label: "Dérivée", code: "\\frac{d}{dx}f(x)"), .init(label: "Dérivée partielle", code: "\\frac{\\partial f}{\\partial x}")]),
        ("Relations", [.init(label: "Inférieur ou égal", code: "a \\le b"), .init(label: "Supérieur ou égal", code: "a \\ge b"),
            .init(label: "Différent de", code: "a \\ne b"), .init(label: "Approximativement égal", code: "a \\approx b"),
            .init(label: "Équivalence", code: "a \\equiv b"), .init(label: "Implication", code: "a \\Rightarrow b")]),
        ("Ensembles", [.init(label: "Entiers naturels", code: "\\mathbb{N}"), .init(label: "Entiers relatifs", code: "\\mathbb{Z}"),
            .init(label: "Rationnels", code: "\\mathbb{Q}"), .init(label: "Réels", code: "\\mathbb{R}"),
            .init(label: "Appartient à", code: "x \\in A"), .init(label: "N’appartient pas à", code: "x \\notin A"),
            .init(label: "Est inclus dans", code: "A \\subset B"), .init(label: "Union", code: "A \\cup B"),
            .init(label: "Intersection", code: "A \\cap B"), .init(label: "Ensemble vide", code: "\\varnothing")]),
        ("Grec", ["alpha", "beta", "gamma", "delta", "theta", "lambda", "mu", "pi", "sigma", "phi", "omega"]
            .map { MathTemplate(label: $0, code: "\\" + $0) }),
        ("Structures", [.init(label: "Coefficient binomial", code: "\\binom{n}{k}"), .init(label: "Vecteur", code: "\\vec{u}"),
            .init(label: "Définition par cas", code: "\\begin{cases} y=x+1 \\\\ y=2x \\end{cases}"),
            .init(label: "Matrice", code: "\\begin{pmatrix} a & b \\\\ c & d \\end{pmatrix}"),
            .init(label: "Équations alignées", code: "\\begin{aligned} a &= b \\\\ c &= d \\end{aligned}"),
            .init(label: "Segment", code: "\\overline{AB}")])
    ]
}

struct MathAssistant: View {
    @ObservedObject var controller: MarkdownEditorController
    @State private var code = ""
    @State private var block = false
    @State private var category = 0
    @FocusState private var focused: Bool

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            VStack(alignment: .leading, spacing: 2) {
                Text("Insérer une formule").font(.headline)
                Text("Saisissez du LaTeX ou partez d’un modèle.").font(.callout).foregroundStyle(.secondary)
            }
            Picker("Position", selection: $block) {
                Text("Dans le texte").tag(false)
                Text("Centrée").tag(true)
            }.pickerStyle(.segmented).labelsHidden()
            VStack(alignment: .leading, spacing: 4) {
                Text("LaTeX").font(.caption.weight(.semibold)).foregroundStyle(.secondary)
                TextField("Ex. : \\frac{a}{b}", text: $code, axis: .vertical)
                    .lineLimit(2...6).font(.system(.body, design: .monospaced)).textFieldStyle(.plain)
                    .padding(8).background(StudioStyle.field, in: RoundedRectangle(cornerRadius: 8, style: .continuous))
                    .focused($focused)
            }
            VStack(alignment: .leading, spacing: 4) {
                Text("Aperçu").font(.caption.weight(.semibold)).foregroundStyle(.secondary)
                MarkdownPreview(text: code.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty ? "" : (block ? "$$\n\(code)\n$$" : "$\(code)$"),
                                fontSize: 16, placeholder: "La formule apparaîtra ici.")
                    .frame(minHeight: 34).padding(8).frame(maxWidth: .infinity, alignment: .leading)
                    .background(StudioStyle.field.opacity(0.6), in: RoundedRectangle(cornerRadius: 8, style: .continuous))
            }
            VStack(alignment: .leading, spacing: 6) {
                Text("Modèles").font(.caption.weight(.semibold)).foregroundStyle(.secondary)
                Picker("Catégorie", selection: $category) {
                    ForEach(MathPalette.categories.indices, id: \.self) { Text(MathPalette.categories[$0].name).tag($0) }
                }.pickerStyle(.segmented).labelsHidden().controlSize(.small)
                LazyVGrid(columns: [GridItem(.adaptive(minimum: 160), spacing: 6)], spacing: 6) {
                    ForEach(MathPalette.categories[category].items) { item in
                        Button { insertTemplate(item.code) } label: {
                            VStack(alignment: .leading, spacing: 1) {
                                Text(item.label).font(.caption.weight(.medium)).lineLimit(1)
                                Text(item.code).font(.system(size: 10, design: .monospaced)).foregroundStyle(.secondary).lineLimit(1)
                            }
                            .frame(maxWidth: .infinity, alignment: .leading).padding(.horizontal, 8).padding(.vertical, 5)
                            .background(Color.primary.opacity(0.05), in: RoundedRectangle(cornerRadius: 7, style: .continuous))
                            .contentShape(Rectangle())
                        }
                        .buttonStyle(.plain).help("Insérer : \(item.label)")
                    }
                }
            }
            HStack {
                Text("$…$ dans le texte · $$…$$ centrée").font(.caption).foregroundStyle(.tertiary)
                Spacer()
                Button("Annuler") { controller.mathRequest = nil }.keyboardShortcut(.cancelAction)
                Button("Insérer") { controller.insertFormula(code.trimmingCharacters(in: .whitespacesAndNewlines), block: block) }
                    .keyboardShortcut(.defaultAction).buttonStyle(.borderedProminent)
                    .disabled(code.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty)
            }
        }
        .padding(18).frame(width: 580)
        .onAppear {
            let selection = controller.selectedFormula()
            code = selection.code; block = selection.block; focused = true
        }
    }

    private func insertTemplate(_ template: String) {
        if code.isEmpty || code.hasSuffix(" ") { code += template } else { code += " " + template }
        focused = true
    }
}

// MARK: - Éditeur agrandi

enum EditorLayout: String, CaseIterable, Identifiable {
    case write, split, preview
    var id: String { rawValue }
    var title: String { switch self { case .write: "Écrire"; case .split: "Côte à côte"; case .preview: "Aperçu" } }
    var symbol: String { switch self { case .write: "square.and.pencil"; case .split: "rectangle.split.2x1"; case .preview: "eye" } }
}

/// Éditeur plein format d’un champ long : barre Markdown, assistant LaTeX et aperçu en direct.
struct MarkdownEditorSheet: View {
    let label: String
    @Binding var text: String
    var aiPurpose: FieldAIPurpose? = nil
    var onDone: () -> Void
    @StateObject private var controller = MarkdownEditorController()
    @AppStorage("markdownEditorLayout") private var layout: EditorLayout = .split
    @Environment(\.openWindow) private var openWindow

    var body: some View {
        VStack(alignment: .leading, spacing: 14) {
            HStack(alignment: .firstTextBaseline) {
                Text(label).font(.title2.bold())
                Spacer()
                if let aiPurpose { FieldAIButton(label: label, text: $text, purpose: aiPurpose) }
                Picker("Affichage", selection: $layout) {
                    ForEach(EditorLayout.allCases) { Label($0.title, systemImage: $0.symbol).tag($0) }
                }.pickerStyle(.segmented).labelsHidden().fixedSize()
            }
            if layout != .preview { MarkdownToolbar(controller: controller) }
            HStack(spacing: 12) {
                if layout != .preview {
                    MarkdownTextView(text: $text, controller: controller, placeholder: label)
                        .background(StudioStyle.surface, in: RoundedRectangle(cornerRadius: 12, style: .continuous))
                        .overlay(RoundedRectangle(cornerRadius: 12, style: .continuous).strokeBorder(StudioStyle.line))
                }
                if layout != .write {
                    ScrollView {
                        MarkdownPreview(text: text, fontSize: 14.5, placeholder: "L’aperçu apparaîtra ici.")
                            .padding(14).frame(maxWidth: .infinity, alignment: .leading)
                    }
                    .background(StudioStyle.field.opacity(0.5), in: RoundedRectangle(cornerRadius: 12, style: .continuous))
                    .overlay(alignment: .topTrailing) {
                        Text("Aperçu").font(.caption2.weight(.semibold)).foregroundStyle(.tertiary).padding(8)
                    }
                }
            }
            HStack(spacing: 10) {
                Image(systemName: "info.circle").foregroundStyle(.secondary)
                Text(verbatim: "Markdown : **gras**, *italique*, ## titre, - liste, > citation, [lien](https://…). LaTeX : $…$ ou $$…$$.")
                    .font(.caption).foregroundStyle(.secondary).lineLimit(2)
                Button("Aide") { HelpCenter.shared.show(.formatting, openWindow) }.buttonStyle(.link).font(.caption)
                Spacer()
                Button("Terminé", action: onDone).keyboardShortcut(.defaultAction).studioButtons(prominent: true)
            }
        }
        .padding(24)
        .frame(minWidth: 760, idealWidth: 1000, minHeight: 480, idealHeight: 640)
    }
}
