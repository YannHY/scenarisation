import Foundation
import CryptoKit
import Combine
import Testing
@testable import Scenarisation

private final class SiteAuthProtocol: URLProtocol, @unchecked Sendable {
    nonisolated(unsafe) static var server: SiteTestServer?
    override class func canInit(with request: URLRequest) -> Bool { true }
    override class func canonicalRequest(for request: URLRequest) -> URLRequest { request }
    override func startLoading() {
        do {
            let result = try Self.server!.handle(request)
            try result.gate?.wait()
            let response = HTTPURLResponse(url: request.url!, statusCode: result.status, httpVersion: "HTTP/1.1",
                headerFields: ["Content-Type": result.mime])!
            client?.urlProtocol(self, didReceive: response, cacheStoragePolicy: .notAllowed)
            client?.urlProtocol(self, didLoad: result.data)
            client?.urlProtocolDidFinishLoading(self)
        } catch { client?.urlProtocol(self, didFailWithError: error) }
    }
    override func stopLoading() {}
}

private final class SiteTestGate: @unchecked Sendable {
    let entered: AsyncStream<Void>
    private let signal: AsyncStream<Void>.Continuation
    private let semaphore = DispatchSemaphore(value: 0)
    init() {
        let stream = AsyncStream<Void>.makeStream(); entered = stream.stream; signal = stream.continuation
    }
    func wait() throws {
        signal.yield(())
        guard semaphore.wait(timeout: .now() + 5) == .success else { throw URLError(.timedOut) }
    }
    func resume() { semaphore.signal() }
}

private final class SiteTestServer: @unchecked Sendable {
    struct Event: Sendable { let path: String; let fields: [String: String]; let authorization: String?; let cookie: String? }
    struct State {
        var events: [Event] = []
        var userID = 7
        var username = "Enseignant"
        var counter = 1
        var tokenStatus = 200
        var tokenError = "invalid_grant"
        var userStatus = 200
        var resourceStatus = 200
        var revokeStatus = 200
        var gate: SiteTestGate?
        var design = Scenario.template()
    }
    struct Response { let status: Int; let mime: String; let data: Data; var gate: SiteTestGate? = nil }
    private let lock = NSLock()
    private var state = State()
    var snapshot: State { lock.withLock { state } }
    func configure(_ body: (inout State) -> Void) { lock.withLock { body(&state) } }
    static func access(_ letter: String) -> String { "sca_" + String(repeating: letter, count: 43) }
    static func refresh(_ letter: String) -> String { "scr_" + String(repeating: letter, count: 43) }
    static func body(_ request: URLRequest) -> Data {
        if let body = request.httpBody { return body }
        guard let stream = request.httpBodyStream else { return Data() }
        stream.open(); defer { stream.close() }
        var data = Data(); var bytes = [UInt8](repeating: 0, count: 4096)
        while stream.hasBytesAvailable {
            let count = stream.read(&bytes, maxLength: bytes.count)
            if count <= 0 { break }; data.append(contentsOf: bytes.prefix(count))
        }
        return data
    }
    func handle(_ request: URLRequest) throws -> Response {
        try lock.withLock {
            let path = request.url!.lastPathComponent
            let body = Self.body(request)
            var form = URLComponents(); form.percentEncodedQuery = String(decoding: body, as: UTF8.self)
            let fields = Dictionary((form.queryItems ?? []).map { ($0.name, $0.value ?? "") }, uniquingKeysWith: { _, next in next })
            state.events.append(Event(path: path, fields: fields,
                authorization: request.value(forHTTPHeaderField: "Authorization"), cookie: request.value(forHTTPHeaderField: "Cookie")))
            func response(_ status: Int, _ object: [String: Any], gate: SiteTestGate? = nil) throws -> Response {
                Response(status: status, mime: "application/json", data: try JSONSerialization.data(withJSONObject: object), gate: gate)
            }
            switch path {
            case "oauth-token.php":
                if state.tokenStatus != 200 { return try response(state.tokenStatus, ["error": state.tokenError]) }
                state.counter += 1
                let letter = String(UnicodeScalar(96 + state.counter)!)
                return try response(200, ["access_token": Self.access(letter), "refresh_token": Self.refresh(letter),
                    "token_type": "Bearer", "expires_in": 900, "refresh_expires_in": 2_592_000,
                    "scope": "designs:read designs:write"], gate: fields["grant_type"] == "refresh_token" ? state.gate : nil)
            case "oauth-revoke.php":
                return try response(state.revokeStatus, state.revokeStatus == 200 ? ["success": true] : ["error": "server_error"])
            case "me.php":
                return try response(state.userStatus, state.userStatus == 200
                    ? ["success": true, "user": ["id": state.userID, "username": state.username, "email": "teacher@example.test"]]
                    : ["error": "invalid_token"])
            case "list_designs.php":
                if state.resourceStatus != 200 { return try response(state.resourceStatus, ["error": "invalid_token"]) }
                return try response(200, ["success": true, "items": [["id": 12, "title": state.design.name, "revision": 1, "updatedAt": "date"]]])
            case "get_design.php":
                return try response(200, ["success": true, "design": ["id": 12, "title": state.design.name,
                    "revision": 1, "updatedAt": "date", "document": JSONSerialization.jsonObject(with: state.design.encoded())]])
            default: throw URLError(.unsupportedURL)
            }
        }
    }
}

@Suite("Connexion au compte Scénarisation", .serialized)
@MainActor
struct SiteOAuthTests {
    private let date = Date(timeIntervalSince1970: 1_800_000_000)
    private func client() -> SiteOAuthClient {
        let configuration = URLSessionConfiguration.ephemeral
        configuration.protocolClasses = [SiteAuthProtocol.self]
        return SiteOAuthClient(sessionConfiguration: configuration)
    }
    private func grant() -> SiteOAuthGrant {
        SiteOAuthGrant(code: String(repeating: "c", count: 43), verifier: String(repeating: "v", count: 64),
                       redirectURI: URL(string: "http://127.0.0.1:45123/auth/callback")!)
    }
    private func saved(expiredAccess: Bool = true) -> StoredSiteSession {
        StoredSiteSession(issuer: SiteOAuthConfiguration.production.issuer,
            user: SiteUser(id: 7, username: "Enseignant", email: "teacher@example.test"),
            tokens: SiteOAuthTokens(access: SiteTestServer.access("a"), refresh: SiteTestServer.refresh("a"),
                accessExpiresAt: date.addingTimeInterval(expiredAccess ? -5 : 600),
                refreshExpiresAt: date.addingTimeInterval(3600), scopes: SiteOAuthConfiguration.scopes))
    }
    private func defaults() throws -> UserDefaults {
        let name = "ScenarisationTests.Site.\(UUID().uuidString)"
        return try #require(UserDefaults(suiteName: name))
    }
    private func callback(_ attempt: SiteSignInAttempt) -> URL {
        var result = URLComponents(url: attempt.redirectURI, resolvingAgainstBaseURL: false)!
        result.queryItems = [.init(name: "state", value: attempt.state),
            .init(name: "iss", value: attempt.configuration.issuer.absoluteString),
            .init(name: "code", value: String(repeating: "c", count: 43))]
        return result.url!
    }

    @Test func browserAuthorizationUsesTheFixedClientPKCEAndAnUnpredictableState() throws {
        let redirect = URL(string: "http://127.0.0.1:45123/auth/callback")!
        let attempt = try SiteSignInAttempt(redirectURI: redirect, now: date)
        let fields = Dictionary(uniqueKeysWithValues: URLComponents(url: attempt.authorizationURL(), resolvingAgainstBaseURL: false)!.queryItems!.map { ($0.name, $0.value!) })
        #expect(fields["client_id"] == "scenarisation-mac" && fields["response_type"] == "code")
        #expect(fields["redirect_uri"] == redirect.absoluteString && fields["code_challenge_method"] == "S256")
        let challenge = Data(SHA256.hash(data: Data(attempt.verifier.utf8))).base64EncodedString()
            .replacingOccurrences(of: "+", with: "-").replacingOccurrences(of: "/", with: "_").replacingOccurrences(of: "=", with: "")
        #expect(fields["code_challenge"] == challenge)
        #expect(fields["scope"] == "designs:read designs:write")
        #expect(attempt.state != (try SiteSignInAttempt(redirectURI: redirect)).state)
        #expect(try attempt.callback(callback(attempt), now: date).verifier == attempt.verifier)
    }

    @Test(arguments: ["state", "issuer", "missing-issuer", "duplicate", "code-and-error", "host", "port", "path", "fragment", "expired", "code"])
    func rejectsUnboundOrMalformedCallbacks(_ kind: String) throws {
        let attempt = try SiteSignInAttempt(redirectURI: URL(string: "http://127.0.0.1:45123/auth/callback")!, now: date)
        var text = callback(attempt).absoluteString
        switch kind {
        case "state": text = text.replacingOccurrences(of: attempt.state, with: "wrong")
        case "issuer": text = text.replacingOccurrences(of: "scenarisation.eu", with: "attacker.test")
        case "missing-issuer": text = text.replacingOccurrences(of: "iss=", with: "other=")
        case "duplicate": text += "&state=" + attempt.state
        case "code-and-error": text += "&error=access_denied"
        case "host": text = text.replacingOccurrences(of: "127.0.0.1", with: "localhost")
        case "port": text = text.replacingOccurrences(of: ":45123", with: ":45124")
        case "path": text = text.replacingOccurrences(of: "/auth/callback", with: "/wrong")
        case "fragment": text += "#fragment"
        case "code": text = text.replacingOccurrences(of: String(repeating: "c", count: 43), with: "invalid")
        default: break
        }
        #expect(throws: SiteAuthError.self) { _ = try attempt.callback(URL(string: text)!, now: kind == "expired" ? attempt.expiresAt : date) }
    }

    @Test func exchangesCodesWithoutCookiesOrCredentialsInTheURL() async throws {
        let server = SiteTestServer(); SiteAuthProtocol.server = server
        defer { SiteAuthProtocol.server = nil }
        let response = try await client().exchange(grant())
        #expect(try response.tokens(now: date).validFormat)
        let event = try #require(server.snapshot.events.first)
        #expect(event.path == "oauth-token.php" && event.fields["grant_type"] == "authorization_code")
        #expect(event.fields["client_id"] == "scenarisation-mac" && event.fields["code_verifier"] == grant().verifier)
        #expect(event.fields["redirect_uri"] == grant().redirectURI.absoluteString)
        #expect(event.cookie == nil && event.authorization == nil)
    }

    @Test(arguments: ["access", "refresh", "type", "access-ttl", "refresh-ttl", "zero", "scope"])
    func rejectsUnsafeTokenResponses(_ kind: String) throws {
        let response = SiteOAuthTokenResponse(
            access_token: kind == "access" ? "unsafe" : SiteTestServer.access("a"),
            refresh_token: kind == "refresh" ? "unsafe" : SiteTestServer.refresh("a"),
            token_type: kind == "type" ? "Other" : "Bearer",
            expires_in: kind == "access-ttl" ? 901 : (kind == "zero" ? 0 : 900),
            refresh_expires_in: kind == "refresh-ttl" ? 2_592_001 : 3600,
            scope: kind == "scope" ? "designs:read" : "designs:read designs:write")
        #expect(throws: SiteAuthError.self) { _ = try response.tokens(now: date) }
    }

    @Test func accessExpiryCannotExceedTheRemainingSessionLifetime() throws {
        let response = SiteOAuthTokenResponse(access_token: SiteTestServer.access("a"),
            refresh_token: SiteTestServer.refresh("a"), token_type: "Bearer", expires_in: 900,
            refresh_expires_in: 20, scope: "designs:read designs:write")
        let tokens = try response.tokens(now: date)
        #expect(tokens.accessExpiresAt == date.addingTimeInterval(20))
        #expect(tokens.accessExpiresAt == tokens.refreshExpiresAt)
    }

    @Test func loginStoresOneSessionAndNeverWritesTokensToPreferences() async throws {
        let server = SiteTestServer(); SiteAuthProtocol.server = server
        defer { SiteAuthProtocol.server = nil }
        let preferences = try defaults(); var stored: Data?
        let auth = SiteAccountAuth(client: client(), defaults: preferences, read: { nil }, write: { stored = $0 },
                                   authorize: { _ in grant() }, now: { date })
        try await auth.signIn()
        #expect(auth.connected && auth.user?.id == 7)
        let decoded = try JSONDecoder().decode(StoredSiteSession.self, from: #require(stored))
        #expect(decoded.tokens?.access == SiteTestServer.access("b"))
        #expect(!String(describing: preferences.dictionaryRepresentation()).contains("sca_"))
        #expect(server.snapshot.events.map(\.path) == ["oauth-token.php", "me.php"])
    }

    @Test func simultaneousRequestsShareOneRotationAndPersistThePair() async throws {
        let server = SiteTestServer(); SiteAuthProtocol.server = server
        defer { SiteAuthProtocol.server = nil }
        let initial = saved(); var stored = try JSONEncoder().encode(initial)
        let auth = SiteAccountAuth(client: client(), defaults: try defaults(), read: { stored }, write: { stored = $0 }, now: { date })
        let first = Task { try await auth.bearerToken(for: initial.account.key) }
        let second = Task { try await auth.bearerToken(for: initial.account.key) }
        #expect(try await first.value == SiteTestServer.access("b"))
        #expect(try await second.value == SiteTestServer.access("b"))
        #expect(server.snapshot.events.filter { $0.fields["grant_type"] == "refresh_token" }.count == 1)
        let decoded = try JSONDecoder().decode(StoredSiteSession.self, from: stored)
        #expect(decoded.tokens?.refresh == SiteTestServer.refresh("b"))
        #expect(decoded.tokens?.access == SiteTestServer.access("b"))
    }

    @Test func temporaryFailuresKeepTheSessionButRevocationRequiresSignIn() async throws {
        let server = SiteTestServer(); SiteAuthProtocol.server = server
        defer { SiteAuthProtocol.server = nil }
        let initial = saved(); var stored = try JSONEncoder().encode(initial)
        let auth = SiteAccountAuth(client: client(), defaults: try defaults(), read: { stored }, write: { stored = $0 }, now: { date })
        server.configure { $0.tokenStatus = 503; $0.tokenError = "server_error" }
        await #expect(throws: SiteAuthError.self) { _ = try await auth.bearerToken(for: initial.account.key) }
        let retainedSession = try JSONDecoder().decode(StoredSiteSession.self, from: stored)
        #expect(auth.connected)
        #expect(retainedSession.tokens == initial.tokens)
        server.configure { $0.tokenStatus = 400; $0.tokenError = "invalid_grant" }
        await #expect(throws: SiteAuthError.self) { _ = try await auth.bearerToken(for: initial.account.key) }
        #expect(!auth.connected && auth.user?.id == 7)
        #expect(try JSONDecoder().decode(StoredSiteSession.self, from: stored).tokens == nil)
    }

    @Test func aRotationCannotChangeTheAccountIdentity() async throws {
        let server = SiteTestServer(); SiteAuthProtocol.server = server
        defer { SiteAuthProtocol.server = nil }
        server.configure { $0.userID = 9 }
        let initial = saved(); var stored = try JSONEncoder().encode(initial)
        let auth = SiteAccountAuth(client: client(), defaults: try defaults(), read: { stored }, write: { stored = $0 }, now: { date })
        await #expect(throws: SiteAuthError.self) { _ = try await auth.bearerToken(for: initial.account.key) }
        #expect(!auth.connected && auth.user?.id == 7)
        #expect(server.snapshot.events.last?.path == "oauth-revoke.php")
    }

    @Test func aFailedIdentityLookupNeverReplaysTheConsumedRefreshToken() async throws {
        let server = SiteTestServer(); SiteAuthProtocol.server = server
        defer { SiteAuthProtocol.server = nil }
        server.configure { $0.userStatus = 503 }
        let initial = saved(); var stored = try JSONEncoder().encode(initial)
        let auth = SiteAccountAuth(client: client(), defaults: try defaults(), read: { stored }, write: { stored = $0 }, now: { date })
        await #expect(throws: SiteAuthError.self) { _ = try await auth.bearerToken(for: initial.account.key) }
        #expect(!auth.connected)
        #expect(try JSONDecoder().decode(StoredSiteSession.self, from: stored).tokens == nil)
        #expect(server.snapshot.events.last?.fields["token"] == SiteTestServer.refresh("b"))
        server.configure { $0.userStatus = 200 }
        await #expect(throws: SiteAuthError.self) { _ = try await auth.bearerToken(for: initial.account.key) }
        #expect(server.snapshot.events.filter { $0.fields["grant_type"] == "refresh_token" }.count == 1)
    }

    @Test func failedKeychainWritesDoNotReplayTheConsumedRefreshToken() async throws {
        let server = SiteTestServer(); SiteAuthProtocol.server = server
        defer { SiteAuthProtocol.server = nil }
        let initial = saved(); var stored = try JSONEncoder().encode(initial); var fail = true
        let preferences = try defaults()
        let auth = SiteAccountAuth(client: client(), defaults: preferences, read: { stored }, write: {
            if fail { throw SiteAuthError.keychain }; stored = $0
        }, now: { date })
        await #expect(throws: SiteAuthError.self) { _ = try await auth.bearerToken(for: initial.account.key) }
        let relaunched = SiteAccountAuth(client: client(), defaults: preferences, read: { stored }, write: { _ in }, now: { date })
        #expect(!relaunched.connected && relaunched.user?.id == 7)
        await #expect(throws: SiteAuthError.self) { _ = try await relaunched.bearerToken(for: initial.account.key) }
        fail = false
        #expect(try await auth.bearerToken(for: initial.account.key) == SiteTestServer.access("b"))
        #expect(server.snapshot.events.filter { $0.fields["grant_type"] == "refresh_token" }.count == 1)
        #expect(try JSONDecoder().decode(StoredSiteSession.self, from: stored).tokens?.refresh == SiteTestServer.refresh("b"))
    }

    @Test func signOutDuringRotationRevokesTheNewTokenAndCannotRestoreIt() async throws {
        let server = SiteTestServer(); SiteAuthProtocol.server = server
        defer { SiteAuthProtocol.server = nil }
        let gate = SiteTestGate(); server.configure { $0.gate = gate }
        let initial = saved(); var stored = try JSONEncoder().encode(initial)
        let auth = SiteAccountAuth(client: client(), defaults: try defaults(), read: { stored }, write: { stored = $0 }, now: { date })
        let request = Task { try await auth.bearerToken(for: initial.account.key) }
        for await _ in gate.entered { break }
        let (disconnected, signal) = AsyncStream<Void>.makeStream()
        let subscription = auth.$connected.sink { if !$0 { signal.yield(()) } }
        let logout = Task { await auth.signOut() }
        for await _ in disconnected { break }
        let clearedSession = try JSONDecoder().decode(StoredSiteSession.self, from: stored)
        #expect(!auth.connected)
        #expect(clearedSession.tokens == nil)
        gate.resume()
        #expect(await logout.value == nil)
        await #expect(throws: CancellationError.self) { _ = try await request.value }
        #expect(server.snapshot.events.last?.fields["token"] == SiteTestServer.refresh("b"))
        #expect(try JSONDecoder().decode(StoredSiteSession.self, from: stored).tokens == nil)
        withExtendedLifetime(subscription) {}
    }

    @Test func cancelledWaitersStillSaveTheRotatedTokens() async throws {
        let server = SiteTestServer(); SiteAuthProtocol.server = server
        defer { SiteAuthProtocol.server = nil }
        let gate = SiteTestGate(); server.configure { $0.gate = gate }
        let initial = saved(); var stored = try JSONEncoder().encode(initial)
        let auth = SiteAccountAuth(client: client(), defaults: try defaults(), read: { stored }, write: { stored = $0 }, now: { date })
        let request = Task { try await auth.bearerToken(for: initial.account.key) }
        for await _ in gate.entered { break }
        request.cancel(); gate.resume()
        await #expect(throws: CancellationError.self) { _ = try await request.value }
        #expect(try JSONDecoder().decode(StoredSiteSession.self, from: stored).tokens?.refresh == SiteTestServer.refresh("b"))
        #expect(auth.connected)
    }

    @Test func aLateUnauthorizedResponseCannotClearANewerToken() async throws {
        let server = SiteTestServer(); SiteAuthProtocol.server = server
        defer { SiteAuthProtocol.server = nil }
        let initial = saved(); var stored = try JSONEncoder().encode(initial)
        let auth = SiteAccountAuth(client: client(), defaults: try defaults(), read: { stored }, write: { stored = $0 }, now: { date })
        _ = try await auth.bearerToken(for: initial.account.key)
        auth.handleUnauthorized(token: SiteTestServer.access("a"), accountKey: initial.account.key)
        #expect(auth.connected)
        server.configure { $0.resourceStatus = 403 }
        await #expect(throws: SiteSyncError.self) { _ = try await auth.api(for: initial.account.key).list() }
        #expect(auth.connected)
        server.configure { $0.resourceStatus = 401 }
        await #expect(throws: SiteSyncError.self) { _ = try await auth.api(for: initial.account.key).list() }
        #expect(!auth.connected)
    }

    @Test func signOutDisablesLocalCredentialsEvenIfStorageAndRevocationFail() async throws {
        let server = SiteTestServer(); SiteAuthProtocol.server = server
        defer { SiteAuthProtocol.server = nil }
        server.configure { $0.revokeStatus = 503 }
        let initial = saved(expiredAccess: false); let stored = try JSONEncoder().encode(initial)
        let preferences = try defaults()
        let auth = SiteAccountAuth(client: client(), defaults: preferences, read: { stored }, write: { _ in throw SiteAuthError.keychain }, now: { date })
        #expect(await auth.signOut() != nil)
        #expect(!auth.connected)
        let relaunched = SiteAccountAuth(client: client(), defaults: preferences, read: { stored }, write: { _ in }, now: { date })
        #expect(!relaunched.connected)
    }

    @Test func cancellingBrowserLoginKeepsTheExistingSession() async throws {
        let server = SiteTestServer(); SiteAuthProtocol.server = server
        defer { SiteAuthProtocol.server = nil }
        let initial = saved(expiredAccess: false); var stored = try JSONEncoder().encode(initial)
        var continuation: CheckedContinuation<SiteOAuthGrant, Never>?
        let (entered, signal) = AsyncStream<Void>.makeStream()
        let auth = SiteAccountAuth(client: client(), defaults: try defaults(), read: { stored }, write: { stored = $0 }, authorize: { _ in
            await withCheckedContinuation { continuation = $0; signal.yield(()) }
        }, now: { date })
        let login = Task { try await auth.signIn() }
        for await _ in entered { break }
        login.cancel(); continuation?.resume(returning: grant())
        await #expect(throws: CancellationError.self) { try await login.value }
        #expect(auth.connected && server.snapshot.events.isEmpty)
        #expect(try JSONDecoder().decode(StoredSiteSession.self, from: stored) == initial)
    }

    @Test func accountCopiesRemainSeparateAcrossSignOutAndAccountSwitch() async throws {
        let server = SiteTestServer(); SiteAuthProtocol.server = server
        defer { SiteAuthProtocol.server = nil }
        let folder = FileManager.default.temporaryDirectory.appendingPathComponent(UUID().uuidString)
        defer { try? FileManager.default.removeItem(at: folder) }
        let initial = saved(expiredAccess: false); var stored = try JSONEncoder().encode(initial)
        let preferences = try defaults()
        let auth = SiteAccountAuth(client: client(), defaults: preferences, read: { stored }, write: { stored = $0 },
                                   authorize: { _ in grant() }, now: { date })
        let model = SiteAccountModel(defaults: preferences, directory: folder, auth: auth)
        let first = try #require(model.workspace)
        #expect(model.networkAvailable)
        try await first.refresh()
        let record = try await first.downloadToMac(12)
        await model.disconnect()?.value
        #expect(!model.networkAvailable && model.workspace?.record(record.id)?.local == record.local)
        server.configure { $0.userID = 9; $0.username = "Autre compte" }
        await model.connect()?.value
        #expect(model.workspace?.account.key != first.account.key && model.workspace?.records.isEmpty == true)
        let before = server.snapshot.events.count
        await #expect(throws: SiteSyncError.self) { try await first.refresh() }
        #expect(server.snapshot.events.count == before)
        #expect(!String(describing: preferences.dictionaryRepresentation()).contains("sca_"))
    }
}
