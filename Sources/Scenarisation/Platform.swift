import SwiftUI
import UniformTypeIdentifiers
#if os(macOS)
import AppKit
#else
import UIKit
import AuthenticationServices
#endif

/// Petites différences entre le Mac et l’iPad, regroupées pour que le reste du code reste commun.
enum Platform {
#if os(macOS)
    static let deviceName = "Mac"
    /// « ce Mac » / « cet iPad », « le Mac » / « l’iPad », « du Mac » / « de l’iPad » pour les textes de l’interface.
    static let thisDevice = "ce Mac"
    static let theDevice = "le Mac"
    static let ofTheDevice = "du Mac"
#else
    static let deviceName = "iPad"
    static let thisDevice = "cet iPad"
    static let theDevice = "l’iPad"
    static let ofTheDevice = "de l’iPad"
#endif

    /// Ouvre un lien externe dans le navigateur par défaut.
    @MainActor @discardableResult
    static func open(_ url: URL) -> Bool {
#if os(macOS)
        NSWorkspace.shared.open(url)
#else
        UIApplication.shared.open(url)
        return true
#endif
    }

    /// Retire le focus clavier initial (le Mac sélectionnerait sinon le premier champ de la fenêtre).
    @MainActor static func clearInitialFocus() {
#if os(macOS)
        NSApp.keyWindow?.makeFirstResponder(nil)
#endif
    }

    /// Image de l’icône de l’application, pour l’accueil, l’aide et les crédits.
    @MainActor static var appIcon: Image {
#if os(macOS)
        let icon = Bundle.main.url(forResource: "AppIcon", withExtension: "icns")
            .flatMap { NSImage(contentsOf: $0) } ?? NSApplication.shared.applicationIconImage ?? NSImage()
        return Image(nsImage: icon)
#else
        return Image("BrandMark")
#endif
    }
}

extension Color {
#if os(macOS)
    static let platformSurface = Color(nsColor: .controlBackgroundColor)
    static let platformCanvas = Color(nsColor: .windowBackgroundColor)
    static let platformGray = Color(nsColor: .systemGray)
#else
    static let platformSurface = Color(uiColor: .secondarySystemGroupedBackground)
    static let platformCanvas = Color(uiColor: .systemGroupedBackground)
    static let platformGray = Color(uiColor: .systemGray)
#endif
}

/// Page d’autorisation OAuth (site, ChatGPT). Le retour passe toujours par `OAuthLoopback`, sur 127.0.0.1.
/// Sur Mac, la page s’ouvre dans le navigateur ; sur iPad, dans une session d’authentification
/// intégrée, qui garde l’app au premier plan et donc son écoute locale active.
@MainActor
enum AuthBrowser {
#if os(macOS)
    static func open(_ url: URL, onCancel: @escaping @MainActor () -> Void) -> Bool { NSWorkspace.shared.open(url) }
    static func close() {}
#else
    private static var session: ASWebAuthenticationSession?
    private static let anchor = PresentationAnchor()

    static func open(_ url: URL, onCancel: @escaping @MainActor () -> Void) -> Bool {
        session?.cancel()
        // Le schéma n’est jamais appelé : la page finit sur l’adresse locale, puis l’app ferme la session.
        let session = ASWebAuthenticationSession(url: url, callback: .customScheme("eu.scenarisation.loopback")) { _, error in
            if (error as? ASWebAuthenticationSessionError)?.code == .canceledLogin {
                Task { @MainActor in onCancel() }
            }
        }
        session.presentationContextProvider = anchor
        session.prefersEphemeralWebBrowserSession = false
        self.session = session
        return session.start()
    }

    static func close() {
        session?.cancel()
        session = nil
    }

    private final class PresentationAnchor: NSObject, ASWebAuthenticationPresentationContextProviding {
        func presentationAnchor(for session: ASWebAuthenticationSession) -> ASPresentationAnchor {
            MainActor.assumeIsolated {
                let scenes = UIApplication.shared.connectedScenes.compactMap { $0 as? UIWindowScene }
                let active = scenes.first { $0.activationState == .foregroundActive } ?? scenes.first
                return active?.keyWindow ?? active?.windows.first ?? ASPresentationAnchor()
            }
        }
    }
#endif
}

extension AIProvider {
    /// Les services qui passent par un outil en ligne de commande ou un modèle local n’existent que sur Mac.
    var availableOnThisDevice: Bool {
#if os(macOS)
        true
#else
        !(self == .claude || usesACP || isLocal)
#endif
    }

    static var platformDefault: AIProvider {
#if os(macOS)
        .ollama
#else
        .chatgpt
#endif
    }
}

extension View {
    /// Bouton présenté comme un lien : style natif sur Mac, bouton sans bordure sur iPad.
    @ViewBuilder func linkButton() -> some View {
#if os(macOS)
        self.buttonStyle(.link)
#else
        self.buttonStyle(.borderless)
#endif
    }
}

/// Fenêtres auxiliaires (accueil, aide, licence, compte) : de vraies fenêtres sur Mac ;
/// sur iPad, l’accueil est la bibliothèque et les autres s’affichent par-dessus l’écran courant.
@MainActor func openStudioWindow(_ id: String, _ openWindow: OpenWindowAction) {
#if os(macOS)
    openWindow(id: id)
#else
    PadPresenter.open(id)
#endif
}

extension View {
    /// Dimensions minimales d’une fenêtre ou d’une feuille sur Mac. Sur iPad, la feuille prend la taille
    /// fixée par le système : seule la largeur maximale du contenu est conservée.
    @ViewBuilder func desktopFrame(minWidth: CGFloat? = nil, idealWidth: CGFloat? = nil, maxWidth: CGFloat? = nil,
                                   minHeight: CGFloat? = nil, idealHeight: CGFloat? = nil) -> some View {
#if os(macOS)
        self.frame(minWidth: minWidth, idealWidth: idealWidth, maxWidth: maxWidth, minHeight: minHeight, idealHeight: idealHeight)
#else
        if let maxWidth { self.frame(maxWidth: maxWidth) } else { self }
#endif
    }
}

/// Deux vues côte à côte : séparateur redimensionnable sur Mac, colonnes égales sur iPad.
struct SplitPair<Leading: View, Trailing: View>: View {
    let leading: Leading
    let trailing: Trailing
    init(@ViewBuilder _ content: () -> TupleView<(Leading, Trailing)>) {
        (leading, trailing) = content().value
    }
    var body: some View {
#if os(macOS)
        HSplitView { leading; trailing }
#else
        HStack(alignment: .top, spacing: 0) {
            leading.frame(maxWidth: .infinity)
            Divider()
            trailing.frame(maxWidth: .infinity)
        }
#endif
    }
}

extension View {
    /// Case à cocher : native sur Mac, coche ronde à toucher sur iPad.
    @ViewBuilder func checkboxStyle() -> some View {
#if os(macOS)
        self.toggleStyle(.checkbox)
#else
        self.toggleStyle(CheckmarkToggleStyle())
#endif
    }

    /// Liste encadrée sans alternance de couleur des lignes.
    @ViewBuilder func plainInsetList() -> some View {
#if os(macOS)
        self.listStyle(.inset(alternatesRowBackgrounds: false))
#else
        self.listStyle(.inset)
#endif
    }
}

#if !os(macOS)
struct CheckmarkToggleStyle: ToggleStyle {
    func makeBody(configuration: Configuration) -> some View {
        Button { configuration.isOn.toggle() } label: {
            HStack(alignment: .firstTextBaseline, spacing: 10) {
                Image(systemName: configuration.isOn ? "checkmark.circle.fill" : "circle")
                    .foregroundStyle(configuration.isOn ? Color.accentColor : .secondary)
                    .imageScale(.large)
                configuration.label.foregroundStyle(.primary)
            }
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityAddTraits(configuration.isOn ? [.isSelected] : [])
    }
}
#endif

/// Contenu d’un export, enregistré par le sélecteur de fichiers du système.
struct ExportedFile: FileDocument {
    static var readableContentTypes: [UTType] { [.data] }
    let data: Data
    let type: UTType
    let name: String
    init(data: Data, type: UTType, name: String) { self.data = data; self.type = type; self.name = name }
    init(configuration: ReadConfiguration) throws {
        data = configuration.file.regularFileContents ?? Data(); type = configuration.contentType; name = "scenario"
    }
    func fileWrapper(configuration: WriteConfiguration) throws -> FileWrapper { FileWrapper(regularFileWithContents: data) }
}
