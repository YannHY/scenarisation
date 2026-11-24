import SwiftUI
import UIKit

/// Champ Markdown de l’éditeur agrandi, version UIKit : mêmes actions, raccourcis clavier et continuation des listes que sur Mac.
final class MarkdownUITextView: UITextView {
    weak var controller: MarkdownEditorController?
    var placeholder = "" { didSet { placeholderLabel.text = placeholder } }
    var focusOnAppear = true
    private let placeholderLabel = UILabel()

    override init(frame: CGRect, textContainer: NSTextContainer?) {
        super.init(frame: frame, textContainer: textContainer)
        placeholderLabel.textColor = .placeholderText
        placeholderLabel.numberOfLines = 0
        addSubview(placeholderLabel)
    }
    required init?(coder: NSCoder) { fatalError("init(coder:) n’est pas utilisé") }

    override func layoutSubviews() {
        super.layoutSubviews()
        placeholderLabel.font = font
        let inset = textContainerInset
        let padding = textContainer.lineFragmentPadding
        placeholderLabel.frame = CGRect(x: inset.left + padding, y: inset.top,
                                        width: bounds.width - inset.left - inset.right - 2 * padding, height: placeholderLabel.font.lineHeight)
        placeholderLabel.isHidden = !text.isEmpty
    }

    override func didMoveToWindow() {
        super.didMoveToWindow()
        guard focusOnAppear, window != nil else { return }
        focusOnAppear = false
        DispatchQueue.main.async { [weak self] in
            guard let self else { return }
            self.becomeFirstResponder()
            self.selectedRange = NSRange(location: (self.text as NSString).length, length: 0)
        }
    }

    /// Raccourcis du site avec un clavier physique (⌘B, ⌘I, ⌘K, ⇧⌘H, ⇧⌘L, ⇧⌘7, ⇧⌘Q, ⇧⌘M).
    override var keyCommands: [UIKeyCommand]? {
        MarkdownAction.allCases.map { action in
            let command = UIKeyCommand(title: action.title, action: #selector(performMarkdownCommand(_:)),
                                       input: action.key.character, modifierFlags: action.key.shift ? [.command, .shift] : .command,
                                       propertyList: action.rawValue)
            command.wantsPriorityOverSystemBehavior = true
            return command
        }
    }

    @objc private func performMarkdownCommand(_ command: UIKeyCommand) {
        guard let raw = command.propertyList as? String, let action = MarkdownAction(rawValue: raw) else { return }
        controller?.perform(action)
    }
}

struct MarkdownTextView: UIViewRepresentable {
    @Binding var text: String
    let controller: MarkdownEditorController
    var placeholder = ""
    var fontSize: CGFloat = 17

    func makeCoordinator() -> Coordinator { Coordinator(self) }

    func makeUIView(context: Context) -> MarkdownUITextView {
        let view = MarkdownUITextView(frame: .zero, textContainer: nil)
        view.backgroundColor = .clear
        view.font = .systemFont(ofSize: fontSize)
        view.textColor = .label
        view.textContainerInset = UIEdgeInsets(top: 12, left: 8, bottom: 12, right: 8)
        view.autocorrectionType = .default
        view.smartQuotesType = .no
        view.smartDashesType = .no
        view.smartInsertDeleteType = .no
        view.alwaysBounceVertical = true
        view.keyboardDismissMode = .interactive
        view.placeholder = placeholder
        view.accessibilityLabel = placeholder
        view.text = text
        view.delegate = context.coordinator
        view.controller = controller
        controller.textView = view
        controller.onProgrammaticChange = { [weak coordinator = context.coordinator] value in coordinator?.push(value) }
        return view
    }

    func updateUIView(_ view: MarkdownUITextView, context: Context) {
        context.coordinator.parent = self
        guard view.text != text else { return }
        let selection = view.selectedRange
        let previous = view.text ?? ""
        view.text = text
        // Une modification venue d’un collègue ne déplace pas le curseur dans le texte.
        view.selectedRange = TextSelectionShift.adjusted(selection, from: previous, to: text)
        view.setNeedsLayout()
    }

    @MainActor final class Coordinator: NSObject, UITextViewDelegate {
        var parent: MarkdownTextView
        init(_ parent: MarkdownTextView) { self.parent = parent }

        func push(_ value: String) { if parent.text != value { parent.text = value } }

        func textViewDidChange(_ textView: UITextView) {
            push(textView.text)
            textView.setNeedsLayout()
        }

        func textView(_ textView: UITextView, shouldChangeTextIn range: NSRange, replacementText text: String) -> Bool {
            // Entrée dans une liste : nouvelle puce ou numéro suivant ; sur un élément vide, la liste se termine.
            if text == "\n", range.length == 0 { return !parent.controller.continueList() }
            return true
        }
    }
}
