import SwiftUI
import UIKit

/// Sur Mac, l’aide, la licence et le compte sont des fenêtres. Sur iPad, ils s’affichent en feuille
/// par-dessus l’écran courant, y compris depuis une autre feuille (l’éditeur agrandi, par exemple).
@MainActor
enum PadPresenter {
    private static var presented: [UIViewController] = []

    static func open(_ id: String) {
        switch id {
        case "welcome": dismissAll(); PadLibrary.shared.close()
        case "help": present(title: "Aide de Scénarisation", style: .pageSheet, largest: true) { HelpWindow() }
        case "license": present(title: "Licence et crédits", style: .formSheet) { LicenseWindow() }
        case "site-account": present(title: "Mon compte et mes scénarios", style: .pageSheet) { SiteAccountView() }
        case "settings": present(title: "Réglages de l’assistant", style: .formSheet) { AISettingsView() }
        default: break
        }
    }

    static func dismissAll() {
        guard let first = presented.first(where: { $0.presentingViewController != nil }) else { presented = []; return }
        first.presentingViewController?.dismiss(animated: true)
        presented = []
    }

    private static func present<Content: View>(title: String, style: UIModalPresentationStyle, largest: Bool = false,
                                               @ViewBuilder content: () -> Content) {
        guard let top = topViewController() else { return }
        // La même feuille n’est pas empilée deux fois : elle passe simplement au premier plan.
        if let existing = presented.last(where: { $0.title == title && $0.presentingViewController != nil }) {
            if existing === top { return }
            existing.dismiss(animated: false)
        }
        let box = DismissBox()
        let host = UIHostingController(rootView: PadSheet(title: title, close: { box.close() }, content: content()))
        host.title = title
        host.modalPresentationStyle = style
        if largest, let sheet = host.sheetPresentationController {
            sheet.prefersGrabberVisible = false
            sheet.detents = [.large()]
        }
        box.controller = host
        presented.removeAll { $0.presentingViewController == nil }
        presented.append(host)
        (topViewController() ?? top).present(host, animated: true)
    }

    private static func topViewController() -> UIViewController? {
        let scenes = UIApplication.shared.connectedScenes.compactMap { $0 as? UIWindowScene }
        let scene = scenes.first { $0.activationState == .foregroundActive } ?? scenes.first
        var top = scene?.keyWindow?.rootViewController ?? scene?.windows.first?.rootViewController
        while let next = top?.presentedViewController, !next.isBeingDismissed { top = next }
        return top
    }

    @MainActor private final class DismissBox {
        weak var controller: UIViewController?
        func close() { controller?.dismiss(animated: true) }
    }
}

/// Habillage d’une feuille : titre et bouton Fermer, contenu inchangé.
private struct PadSheet<Content: View>: View {
    let title: String
    let close: () -> Void
    let content: Content
    var body: some View {
        content
            .safeAreaInset(edge: .top, spacing: 0) {
                HStack {
                    Text(title).font(.headline)
                    Spacer()
                    Button("Fermer", action: close).buttonStyle(.bordered).keyboardShortcut(.cancelAction)
                }
                .padding(.horizontal, 20).padding(.vertical, 12)
                .background(.bar)
            }
    }
}
