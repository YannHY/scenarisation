import Foundation
import Combine
import Security

struct StoredSiteSession: Codable, Equatable, Sendable {
    let issuer: URL
    var user: SiteUser
    var tokens: SiteOAuthTokens?
    var account: SiteSyncAccount { .live(server: issuer, user: user) }
}

/// A separate Keychain service prevents site sessions from sharing AI credentials.
enum SiteCredentialVault {
    private static let service = "eu.scenarisation.mac.site"
    private static let account = "scenarisation.oauth.session"
    static func read() throws -> Data? {
        let query: [String: Any] = [kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: service, kSecAttrAccount as String: account,
            kSecReturnData as String: true, kSecMatchLimit as String: kSecMatchLimitOne]
        var result: CFTypeRef?
        let status = SecItemCopyMatching(query as CFDictionary, &result)
        if status == errSecItemNotFound { return nil }
        guard status == errSecSuccess, let data = result as? Data else { throw SiteAuthError.keychain }
        return data
    }
    static func write(_ data: Data) throws {
        let query: [String: Any] = [kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: service, kSecAttrAccount as String: account]
        var status = SecItemUpdate(query as CFDictionary, [kSecValueData as String: data] as CFDictionary)
        if status == errSecItemNotFound {
            var attributes = query
            attributes[kSecValueData as String] = data
            attributes[kSecAttrAccessible as String] = kSecAttrAccessibleWhenUnlockedThisDeviceOnly
            status = SecItemAdd(attributes as CFDictionary, nil)
        }
        guard status == errSecSuccess else { throw SiteAuthError.keychain }
    }
}

@MainActor
final class SiteAccountAuth: ObservableObject {
    @Published private(set) var user: SiteUser?
    @Published private(set) var connected = false
    @Published private(set) var storageMessage: String?
    private var session: StoredSiteSession?
    private let client: SiteOAuthClient
    private let defaults: UserDefaults
    private let write: (Data) throws -> Void
    private let authorize: @MainActor (SiteOAuthConfiguration) async throws -> SiteOAuthGrant
    private let now: () -> Date
    private var epoch = UUID()
    private var signingIn = false
    private var signingOut = false
    private var needsPersistence = false
    private struct RefreshFlight {
        let id: UUID
        let task: Task<StoredSiteSession, Error>
    }
    private var refreshFlight: RefreshFlight?
    private let blockedKey = "site.session.requiresReconnection"

    init(client: SiteOAuthClient = SiteOAuthClient(), defaults: UserDefaults = .standard,
         read: () throws -> Data? = { try SiteCredentialVault.read() },
         write: @escaping (Data) throws -> Void = { try SiteCredentialVault.write($0) },
         authorize: @escaping @MainActor (SiteOAuthConfiguration) async throws -> SiteOAuthGrant = { try await SiteBrowserAuthorization.authorize($0) },
         now: @escaping () -> Date = { .now }) {
        self.client = client; self.defaults = defaults; self.write = write; self.authorize = authorize; self.now = now
        do {
            if let data = try read() {
                var saved = try JSONDecoder().decode(StoredSiteSession.self, from: data)
                guard saved.issuer == client.configuration.issuer, saved.user.id > 0,
                      saved.tokens?.validFormat != false else { throw SiteAuthError.invalidResponse }
                if defaults.bool(forKey: blockedKey) || (saved.tokens?.refreshExpiresAt ?? .distantPast) <= now() { saved.tokens = nil }
                session = saved
            }
        } catch { storageMessage = "La session du site n’a pas pu être lue dans le Trousseau. Reconnectez-vous." }
        publish()
    }

    var account: SiteSyncAccount? { session?.account }
    var configuration: SiteOAuthConfiguration { client.configuration }

    private func publish() {
        user = session?.user
        connected = session?.tokens != nil && (session?.tokens?.refreshExpiresAt ?? .distantPast) > now()
    }
    private func persist(_ value: StoredSiteSession) throws {
        do { try write(JSONEncoder().encode(value)) }
        catch {
            defaults.set(true, forKey: blockedKey)
            storageMessage = SiteAuthError.keychain.localizedDescription
            throw SiteAuthError.keychain
        }
        defaults.set(false, forKey: blockedKey); storageMessage = nil
    }

    func signIn() async throws {
        guard !signingIn, !signingOut, refreshFlight == nil else { throw SiteAuthError.busy }
        signingIn = true; defer { signingIn = false }
        let version = epoch
        var issued: SiteOAuthTokens?
        do {
            let grant = try await authorize(client.configuration)
            try Task.checkCancellation()
            let response = try await client.exchange(grant)
            let tokens = try response.tokens(now: now()); issued = tokens
            let user = try await client.user(access: tokens.access)
            try Task.checkCancellation()
            guard version == epoch else { throw CancellationError() }
            let next = StoredSiteSession(issuer: client.configuration.issuer, user: user, tokens: tokens)
            // A new login is not activated until both tokens have been stored together.
            let previous = session
            try persist(next)
            epoch = UUID(); session = next; needsPersistence = false; issued = nil; publish()
            if let previousToken = previous?.tokens?.refresh, previousToken != tokens.refresh {
                try? await client.revoke(previousToken)
            }
        } catch {
            if let issued { try? await client.revoke(issued.refresh) }
            throw error
        }
    }

    func api(for accountKey: String) throws -> SiteSyncAPI {
        try client.api(credential: { [weak self] in
            guard let self else { throw SiteAuthError.reconnect }
            return try await self.bearerToken(for: accountKey)
        }, unauthorized: { [weak self] token in
            await self?.handleUnauthorized(token: token, accountKey: accountKey)
        })
    }

    func bearerToken(for accountKey: String) async throws -> String {
        guard !signingOut, let current = session, current.account.key == accountKey else { throw SiteSyncError.wrongAccount }
        guard let tokens = current.tokens, tokens.refreshExpiresAt > now() else {
            invalidate(); throw SiteAuthError.reconnect
        }
        if needsPersistence { try persist(current); needsPersistence = false }
        if tokens.accessExpiresAt.timeIntervalSince(now()) > 60 { return tokens.access }
        let version = epoch
        if refreshFlight == nil {
            let client = client
            refreshFlight = RefreshFlight(id: UUID(), task: Task {
                let response = try await client.refresh(tokens.refresh)
                // A successful refresh consumes the previous token, even if its response is malformed.
                let rotated: SiteOAuthTokens
                do { rotated = try response.tokens(now: self.now()) }
                catch { throw SiteAuthError.reconnect }
                do {
                    let user = try await client.user(access: rotated.access)
                    guard user.id == current.user.id else { throw SiteAuthError.identityChanged }
                    return StoredSiteSession(issuer: current.issuer, user: user, tokens: rotated)
                } catch {
                    try? await client.revoke(rotated.refresh)
                    if case SiteAuthError.identityChanged = error { throw error }
                    // Once the replacement has been revoked, the old pair must never be retried.
                    throw SiteAuthError.reconnect
                }
            })
        }
        let flight = refreshFlight!
        do {
            let refreshed = try await flight.task.value
            guard version == epoch, !signingOut else { throw CancellationError() }
            // Other waiters may already have applied this rotation or even started another one.
            if session?.tokens?.refresh == tokens.refresh {
                session = refreshed; needsPersistence = true; publish()
            }
            if needsPersistence, let current = session { try persist(current); needsPersistence = false }
            if refreshFlight?.id == flight.id { refreshFlight = nil }
            try Task.checkCancellation()
            guard let access = session?.tokens?.access else { throw SiteAuthError.reconnect }
            return access
        } catch {
            if version == epoch {
                if refreshFlight?.id == flight.id { refreshFlight = nil }
                if let failure = error as? SiteAuthError {
                    switch failure {
                    case .reconnect, .identityChanged: invalidate()
                    default: break
                    }
                }
                if case SiteSyncError.unauthorized = error { invalidate() }
            }
            throw error
        }
    }

    func handleUnauthorized(token: String, accountKey: String) {
        guard session?.account.key == accountKey, session?.tokens?.access == token else { return }
        if refreshFlight != nil { return }
        invalidate()
    }
    private func invalidate() {
        epoch = UUID(); refreshFlight = nil; session?.tokens = nil; needsPersistence = false
        defaults.set(true, forKey: blockedKey)
        if let session { try? persist(session) }
        publish()
    }

    /// Local tokens are disabled before awaiting the network; a rotating token is revoked after it settles.
    func signOut() async -> String? {
        guard !signingIn, !signingOut else { return SiteAuthError.busy.localizedDescription }
        signingOut = true; defer { signingOut = false }
        let pending = refreshFlight
        let previous = session
        epoch = UUID(); refreshFlight = nil; session?.tokens = nil; needsPersistence = false
        defaults.set(true, forKey: blockedKey)
        var warning: String?
        if let session {
            do { try persist(session) }
            catch { warning = "Les jetons sont désactivés sur ce Mac, mais le Trousseau n’a pas pu être mis à jour." }
        }
        publish()
        var revoke = previous?.tokens?.refresh
        if let pending, let refreshed = try? await pending.task.value { revoke = refreshed.tokens?.refresh }
        if let revoke {
            do { try await client.revoke(revoke) }
            catch { warning = "Déconnexion effectuée sur ce Mac. La révocation distante n’a pas pu être confirmée ; vous pouvez retirer l’accès depuis votre profil sur le site." }
        }
        return warning
    }
}
