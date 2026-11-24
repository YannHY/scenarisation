#if os(macOS) && canImport(Sparkle)
import Combine
import Sparkle
import SwiftUI

/// Mises à jour de l’app Mac par Sparkle.
/// Le flux (`SUFeedURL`) et la clé publique EdDSA (`SUPublicEDKey`) sont dans Info.plist ; Sparkle vérifie
/// une fois par jour, télécharge l’archive, contrôle sa signature, remplace l’app et la relance.
@MainActor
final class AppUpdater: ObservableObject {
    static let shared = AppUpdater()

    @Published private(set) var canCheckForUpdates = false
    private let controller: SPUStandardUpdaterController?
    private var observation: AnyCancellable?

    private init() {
        // Un binaire lancé depuis .build n’a ni Info.plist ni flux : aucune vérification n’est tentée.
        guard Bundle.main.object(forInfoDictionaryKey: "SUFeedURL") != nil else {
            controller = nil
            return
        }
        let controller = SPUStandardUpdaterController(startingUpdater: true, updaterDelegate: nil, userDriverDelegate: nil)
        self.controller = controller
        observation = controller.updater.publisher(for: \.canCheckForUpdates)
            .receive(on: DispatchQueue.main)
            .sink { [weak self] in self?.canCheckForUpdates = $0 }
    }

    /// Appelé au lancement : créer `shared` démarre Sparkle et ses vérifications quotidiennes.
    func start() {}

    func checkForUpdates() { controller?.checkForUpdates(nil) }
}

/// Keep About and updates in the same replacement group: another replacement
/// of appInfo would discard commands inserted relative to the original group.
struct AppUpdateCommands: Commands {
    @ObservedObject private var updater = AppUpdater.shared
    var body: some Commands {
        CommandGroup(replacing: .appInfo) {
            Button("À propos de Scénarisation") { AboutPanel.show() }
            Button("Rechercher les mises à jour…") { updater.checkForUpdates() }
                .disabled(!updater.canCheckForUpdates)
            Divider()
        }
    }
}
#endif
