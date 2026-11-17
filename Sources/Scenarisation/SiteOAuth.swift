import Foundation
import AppKit
import CryptoKit
import Security

enum SiteAuthError: LocalizedError {
    case invalidCallback, denied, invalidResponse, reconnect, unavailable, keychain, busy, identityChanged
    var errorDescription: String? {
        switch self {
        case .invalidCallback: "Le retour de connexion n’est pas valide. Recommencez depuis l’application."
        case .denied: "La connexion au compte a été annulée."
        case .invalidResponse: "Le site a renvoyé une réponse de connexion inattendue."
        case .reconnect: "Votre session du site a expiré ou a été révoquée. Reconnectez-vous ; vos copies locales sont conservées."
        case .unavailable: "La connexion au site est temporairement indisponible. Réessayez."
        case .keychain: "La session du site n’a pas pu être enregistrée dans le Trousseau. Réessayez avant de synchroniser."
        case .busy: "Une opération de connexion est déjà en cours."
        case .identityChanged: "L’identité du compte a changé pendant la connexion. Reconnectez-vous."
        }
    }
}

struct SiteOAuthConfiguration: Equatable, Sendable {
    static let production = SiteOAuthConfiguration()
    static let clientID = "scenarisation-mac"
    static let scopes: Set<String> = ["designs:read", "designs:write"]
    let issuer: URL
    init() { issuer = URL(string: "https://scenarisation.eu")! }
    init(issuer: URL) throws {
        guard issuer.scheme == "https", let host = issuer.host, !host.isEmpty,
              issuer.user == nil, issuer.password == nil, issuer.query == nil, issuer.fragment == nil else {
            throw SiteAuthError.invalidResponse
        }
        self.issuer = issuer
    }
    var authorizationURL: URL { issuer.appendingPathComponent("oauth-authorize.php") }
    var tokenURL: URL { issuer.appendingPathComponent("oauth-token.php") }
    var revocationURL: URL { issuer.appendingPathComponent("oauth-revoke.php") }
    var apiURL: URL { issuer.appendingPathComponent("api/v1") }
}

struct SiteOAuthGrant: Sendable {
    let code: String
    let verifier: String
    let redirectURI: URL
}

struct SiteSignInAttempt: Sendable {
    let configuration: SiteOAuthConfiguration
    let redirectURI: URL
    let verifier: String
    let state: String
    let expiresAt: Date

    init(configuration: SiteOAuthConfiguration = .production, redirectURI: URL, now: Date = .now) throws {
        guard redirectURI.scheme == "http", redirectURI.host == "127.0.0.1",
              let port = redirectURI.port, (1...65535).contains(port), redirectURI.path == "/auth/callback",
              redirectURI.query == nil, redirectURI.fragment == nil, redirectURI.user == nil, redirectURI.password == nil else {
            throw SiteAuthError.invalidCallback
        }
        self.configuration = configuration; self.redirectURI = redirectURI
        verifier = try Self.random(); state = try Self.random(); expiresAt = now.addingTimeInterval(600)
    }
    private static func random() throws -> String {
        var bytes = [UInt8](repeating: 0, count: 32)
        guard SecRandomCopyBytes(kSecRandomDefault, bytes.count, &bytes) == errSecSuccess else { throw SiteAuthError.unavailable }
        return base64URL(Data(bytes))
    }
    private static func base64URL(_ data: Data) -> String {
        data.base64EncodedString().replacingOccurrences(of: "+", with: "-")
            .replacingOccurrences(of: "/", with: "_").replacingOccurrences(of: "=", with: "")
    }
    func authorizationURL() -> URL {
        var url = URLComponents(url: configuration.authorizationURL, resolvingAgainstBaseURL: false)!
        url.queryItems = [
            .init(name: "client_id", value: SiteOAuthConfiguration.clientID),
            .init(name: "response_type", value: "code"), .init(name: "redirect_uri", value: redirectURI.absoluteString),
            .init(name: "code_challenge_method", value: "S256"),
            .init(name: "code_challenge", value: Self.base64URL(Data(SHA256.hash(data: Data(verifier.utf8))))),
            .init(name: "state", value: state), .init(name: "scope", value: "designs:read designs:write"),
        ]
        return url.url!
    }
    func callback(_ url: URL, now: Date = .now) throws -> SiteOAuthGrant {
        guard now < expiresAt, url.scheme == redirectURI.scheme, url.host == redirectURI.host,
              url.port == redirectURI.port, url.path == redirectURI.path, url.user == nil,
              url.password == nil, url.fragment == nil,
              let items = URLComponents(url: url, resolvingAgainstBaseURL: false)?.queryItems else {
            throw SiteAuthError.invalidCallback
        }
        var fields: [String: String] = [:]
        for item in items {
            guard fields[item.name] == nil, let value = item.value else { throw SiteAuthError.invalidCallback }
            fields[item.name] = value
        }
        guard fields["state"] == state, fields["iss"] == configuration.issuer.absoluteString else {
            throw SiteAuthError.invalidCallback
        }
        if fields["error"] != nil {
            guard fields["code"] == nil, fields["error"] == "access_denied" else { throw SiteAuthError.invalidCallback }
            throw SiteAuthError.denied
        }
        guard let code = fields["code"], code.range(of: "^[A-Za-z0-9_-]{43}$", options: .regularExpression) != nil else {
            throw SiteAuthError.invalidCallback
        }
        return SiteOAuthGrant(code: code, verifier: verifier, redirectURI: redirectURI)
    }
}

@MainActor
enum SiteBrowserAuthorization {
    static func authorize(_ configuration: SiteOAuthConfiguration) async throws -> SiteOAuthGrant {
        let callback = OAuthLoopback()
        defer { callback.stop() }
        let redirect = try await callback.start()
        let attempt = try SiteSignInAttempt(configuration: configuration, redirectURI: redirect)
        guard NSWorkspace.shared.open(attempt.authorizationURL()) else { throw SiteAuthError.unavailable }
        let grant = try attempt.callback(await callback.waitForCallback())
        NSApp.activate()
        return grant
    }
}

struct SiteOAuthTokens: Codable, Equatable, Sendable {
    let access: String
    let refresh: String
    let accessExpiresAt: Date
    let refreshExpiresAt: Date
    let scopes: Set<String>

    var validFormat: Bool {
        access.range(of: "^sca_[A-Za-z0-9_-]{43}$", options: .regularExpression) != nil
            && refresh.range(of: "^scr_[A-Za-z0-9_-]{43}$", options: .regularExpression) != nil
            && scopes == SiteOAuthConfiguration.scopes
            && accessExpiresAt.timeIntervalSince1970.isFinite && refreshExpiresAt.timeIntervalSince1970.isFinite
    }
}

struct SiteOAuthTokenResponse: Decodable, Sendable {
    let access_token: String
    let refresh_token: String
    let token_type: String
    let expires_in: Double
    let refresh_expires_in: Double
    let scope: String

    func tokens(now: Date) throws -> SiteOAuthTokens {
        guard token_type.lowercased() == "bearer", expires_in.isFinite, expires_in > 0, expires_in <= 900,
              refresh_expires_in.isFinite, refresh_expires_in > 0, refresh_expires_in <= 2_592_000 else {
            throw SiteAuthError.invalidResponse
        }
        let result = SiteOAuthTokens(access: access_token, refresh: refresh_token,
            accessExpiresAt: now.addingTimeInterval(min(expires_in, refresh_expires_in)),
            refreshExpiresAt: now.addingTimeInterval(refresh_expires_in), scopes: Set(scope.split(separator: " ").map(String.init)))
        guard result.validFormat else { throw SiteAuthError.invalidResponse }
        return result
    }
}

struct SiteOAuthClient: Sendable {
    let configuration: SiteOAuthConfiguration
    private let session: URLSession
    private let sessionConfiguration: URLSessionConfiguration

    init(configuration: SiteOAuthConfiguration = .production, sessionConfiguration: URLSessionConfiguration = .ephemeral) {
        self.configuration = configuration
        let settings = sessionConfiguration.copy() as! URLSessionConfiguration
        settings.httpCookieStorage = nil; settings.urlCache = nil
        settings.timeoutIntervalForRequest = 30; settings.timeoutIntervalForResource = 60
        self.sessionConfiguration = settings
        session = URLSession(configuration: settings, delegate: NoRedirect(), delegateQueue: nil)
    }
    func exchange(_ grant: SiteOAuthGrant) async throws -> SiteOAuthTokenResponse {
        let data = try await post(configuration.tokenURL, fields: ["grant_type": "authorization_code",
            "client_id": SiteOAuthConfiguration.clientID, "code": grant.code, "code_verifier": grant.verifier,
            "redirect_uri": grant.redirectURI.absoluteString])
        return try decode(data)
    }
    func refresh(_ token: String) async throws -> SiteOAuthTokenResponse {
        try decode(await post(configuration.tokenURL, fields: ["grant_type": "refresh_token",
            "client_id": SiteOAuthConfiguration.clientID, "refresh_token": token]))
    }
    func revoke(_ token: String) async throws {
        let data = try await post(configuration.revocationURL, fields: ["client_id": SiteOAuthConfiguration.clientID, "token": token])
        struct Response: Decodable { let success: Bool }
        guard (try? JSONDecoder().decode(Response.self, from: data).success) == true else { throw SiteAuthError.invalidResponse }
    }
    func user(access: String) async throws -> SiteUser {
        try await api(credential: { access }).user()
    }
    func api(credential: @escaping @Sendable () async throws -> String,
             unauthorized: @escaping @Sendable (String) async -> Void = { _ in }) throws -> SiteSyncAPI {
        try SiteSyncAPI(baseURL: configuration.apiURL, configuration: sessionConfiguration,
                        credential: credential, unauthorized: unauthorized)
    }
    private func decode(_ data: Data) throws -> SiteOAuthTokenResponse {
        do { return try JSONDecoder().decode(SiteOAuthTokenResponse.self, from: data) }
        catch { throw SiteAuthError.invalidResponse }
    }
    private func post(_ url: URL, fields: [String: String]) async throws -> Data {
        let allowed = CharacterSet.alphanumerics.union(CharacterSet(charactersIn: "-._~"))
        let body = fields.sorted { $0.key < $1.key }.map {
            $0.key.addingPercentEncoding(withAllowedCharacters: allowed)! + "=" + $0.value.addingPercentEncoding(withAllowedCharacters: allowed)!
        }.joined(separator: "&")
        var request = URLRequest(url: url)
        request.httpMethod = "POST"; request.httpBody = Data(body.utf8)
        request.setValue("application/x-www-form-urlencoded", forHTTPHeaderField: "Content-Type")
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        request.cachePolicy = .reloadIgnoringLocalCacheData
        let (data, raw) = try await session.data(for: request)
        guard let response = raw as? HTTPURLResponse, response.mimeType == "application/json", data.count <= 65_536 else {
            throw SiteAuthError.invalidResponse
        }
        if !(200...299).contains(response.statusCode) {
            struct ErrorResponse: Decodable { let error: String }
            let code = (try? JSONDecoder().decode(ErrorResponse.self, from: data))?.error
            if ["invalid_grant", "invalid_client", "invalid_token", "invalid_scope"].contains(code) { throw SiteAuthError.reconnect }
            if response.statusCode >= 500 { throw SiteAuthError.unavailable }
            throw SiteAuthError.invalidResponse
        }
        return data
    }
}
