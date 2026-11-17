import Combine
#if os(macOS)
import AppKit
private let didBecomeActive = NSApplication.didBecomeActiveNotification
#else
import UIKit
private let didBecomeActive = UIApplication.didBecomeActiveNotification
#endif

@MainActor
final class SiteAccountModel: ObservableObject {
    static let shared = SiteAccountModel()
    enum Action { case idle, connecting, disconnecting }
    @Published private(set) var workspace: SiteSyncWorkspace?
    @Published private(set) var action = Action.idle
    @Published var message: String?
    let auth: SiteAccountAuth
    private let defaults: UserDefaults
    private let directory: URL?
    private var cachedUser: SiteUser?
    private var authChanges: AnyCancellable?
    private var operation: Task<Void, Never>?
    private var refreshLoop: Task<Void, Never>?
    private var activation: NSObjectProtocol?
    private struct CachedIdentity: Codable { let issuer: URL; let user: SiteUser }

    init(defaults: UserDefaults = .standard, directory: URL? = nil, auth: SiteAccountAuth? = nil) {
        self.defaults = defaults; self.directory = directory
        self.auth = auth ?? SiteAccountAuth(defaults: defaults)
        if let data = defaults.data(forKey: "site.cached.identity"),
           let cached = try? JSONDecoder().decode(CachedIdentity.self, from: data),
           cached.issuer == self.auth.configuration.issuer, cached.user.id > 0 { cachedUser = cached.user }
        if let user = self.auth.user { cache(user) }
        authChanges = self.auth.objectWillChange.sink { [weak self] _ in self?.objectWillChange.send() }
        do { try activateLiveAccount() } catch { message = error.localizedDescription }
    }

    var user: SiteUser? { auth.user ?? cachedUser }
    var busy: Bool { action != .idle || workspace?.refreshing == true || !(workspace?.syncing.isEmpty ?? true) }
    var networkAvailable: Bool {
        guard action == .idle, let workspace else { return false }
        return auth.connected && auth.account?.key == workspace.account.key
    }

    private func disk() throws -> SiteSyncDisk {
        try directory.map { SiteSyncDisk(directory: $0) } ?? SiteSyncDisk.applicationSupport()
    }
    private func cache(_ user: SiteUser) {
        cachedUser = user
        if let data = try? JSONEncoder().encode(CachedIdentity(issuer: auth.configuration.issuer, user: user)) {
            defaults.set(data, forKey: "site.cached.identity")
        }
    }
    private func activateLiveAccount() throws {
        guard let user else { workspace = nil; return }
        let account = SiteSyncAccount.live(server: auth.configuration.issuer, user: user)
        let next = try SiteSyncWorkspace(account: account, service: auth.api(for: account.key), disk: disk())
        next.onRemovedFromSite = { identity, file in SiteDeletionCleanup.apply(identity, workingFile: file) }
        workspace = next
    }

    /// Relit le compte au retour dans l’app et toutes les deux minutes, pour que les scénarios
    /// supprimés sur le site disparaissent aussi de ce Mac sans action de l’utilisateur.
    func startAutomaticRefresh() {
        guard refreshLoop == nil else { return }
        refreshLoop = Task { [weak self] in
            while !Task.isCancelled {
                await self?.refreshQuietly()
                try? await Task.sleep(for: .seconds(120))
            }
        }
        activation = NotificationCenter.default.addObserver(forName: didBecomeActive,
                                                            object: nil, queue: .main) { [weak self] _ in
            Task { @MainActor in await self?.refreshQuietly() }
        }
    }

    private func refreshQuietly() async {
        guard networkAvailable, let workspace, !workspace.refreshing else { return }
        try? await workspace.refresh()
    }

    @discardableResult
    func connect() -> Task<Void, Never>? {
        guard !busy else { return nil }
        action = .connecting; message = nil
        operation = Task {
            defer { action = .idle; operation = nil }
            do {
                try await auth.signIn()
                if let user = auth.user { cache(user) }
                try activateLiveAccount()
            } catch is CancellationError {}
            catch SiteAuthError.denied {}
            catch { message = error.localizedDescription }
        }
        return operation
    }
    func cancelConnection() { if action == .connecting { operation?.cancel() } }

    @discardableResult
    func disconnect() -> Task<Void, Never>? {
        guard !busy else { return nil }
        action = .disconnecting; message = nil
        operation = Task {
            defer { action = .idle; operation = nil }
            message = await auth.signOut()
            workspace?.clearRemoteListing()
        }
        return operation
    }
}
