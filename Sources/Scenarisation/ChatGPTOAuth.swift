import Foundation
import CryptoKit
import Security

enum ChatGPTProtocol {
    static let issuer = "https://auth.openai.com"
    static let resource = "https://api.openai.com/v1"
    static let scopes = "openid profile email offline_access resource.invoke chatgpt.tokens.use.direct"
    static let usageURL = URL(string: "https://chatgpt.com/settings/usage")!

    static func base64URL(_ data: Data) -> String {
        data.base64EncodedString().replacingOccurrences(of: "+", with: "-")
            .replacingOccurrences(of: "/", with: "_").replacingOccurrences(of: "=", with: "")
    }
    static func decode64(_ value: String) throws -> Data {
        let base = value.replacingOccurrences(of: "-", with: "+").replacingOccurrences(of: "_", with: "/")
        guard let data = Data(base64Encoded: base + String(repeating: "=", count: (4 - base.count % 4) % 4)) else {
            throw ScenarioError.invalid("La connexion a renvoyé un jeton invalide.")
        }
        return data
    }
    static func randomValue() throws -> String {
        var bytes = [UInt8](repeating: 0, count: 32)
        guard SecRandomCopyBytes(kSecRandomDefault, bytes.count, &bytes) == errSecSuccess else {
            throw ScenarioError.invalid("Impossible de préparer la connexion sécurisée.")
        }
        return base64URL(Data(bytes))
    }
    static func form(_ values: [String: String]) -> Data {
        var components = URLComponents()
        components.queryItems = values.sorted { $0.key < $1.key }.map { URLQueryItem(name: $0.key, value: $0.value) }
        // '+' is literal in URLQueryItem but represents a space in form encoding.
        return Data((components.percentEncodedQuery ?? "").replacingOccurrences(of: "+", with: "%2B").utf8)
    }
}

struct ChatGPTSignInAttempt: Sendable {
    let state: String
    let nonce: String
    let verifier: String
    let redirectURI: URL
    let hostID: String
    let clientID: String?
    let expiresAt: Date

    init(redirectURI: URL, hostID: String, clientID: String? = nil, now: Date = .now) throws {
        self.state = try ChatGPTProtocol.randomValue()
        self.nonce = try ChatGPTProtocol.randomValue()
        self.verifier = try ChatGPTProtocol.randomValue()
        self.redirectURI = redirectURI; self.hostID = hostID; self.clientID = clientID
        self.expiresAt = now.addingTimeInterval(600)
    }
    var challenge: String { ChatGPTProtocol.base64URL(Data(SHA256.hash(data: Data(verifier.utf8)))) }

    func authorizationURL(endpoint: URL, idTokenHint: String? = nil, reconsent: Bool = false) throws -> URL {
        var components = URLComponents(url: endpoint, resolvingAgainstBaseURL: false)!
        var values = [
            "client_id": clientID ?? "dynamic_agent_client", "ext_agent_host_id": hostID,
            "redirect_uri": redirectURI.absoluteString, "response_type": "code",
            "scope": ChatGPTProtocol.scopes, "resource": ChatGPTProtocol.resource,
            "state": state, "nonce": nonce, "code_challenge": challenge, "code_challenge_method": "S256"
        ]
        if clientID == nil { values["agent_name_hint"] = "Scénarisation" }
        if clientID != nil, let idTokenHint { values["id_token_hint"] = idTokenHint }
        if reconsent { values["prompt"] = "consent" }
        components.queryItems = values.sorted { $0.key < $1.key }.map { URLQueryItem(name: $0.key, value: $0.value) }
        guard let url = components.url else { throw ScenarioError.invalid("Adresse de connexion invalide.") }
        return url
    }

    func callback(_ url: URL, now: Date = .now) throws -> (code: String, clientID: String) {
        guard now < expiresAt, url.scheme == redirectURI.scheme, url.host == "127.0.0.1",
              url.port == redirectURI.port, url.path == redirectURI.path, url.fragment == nil,
              let items = URLComponents(url: url, resolvingAgainstBaseURL: false)?.queryItems else {
            throw ScenarioError.invalid("Le retour de connexion est invalide ou a expiré. Recommencez.")
        }
        let grouped = Dictionary(grouping: items, by: \.name)
        guard grouped.values.allSatisfy({ $0.count == 1 }),
              grouped["state"]?.first?.value == state else {
            throw ScenarioError.invalid("Le retour ne correspond pas à cette connexion. Recommencez.")
        }
        if grouped["error"] != nil { throw ScenarioError.invalid("La connexion ChatGPT a été refusée ou annulée.") }
        guard let code = grouped["code"]?.first?.value, !code.isEmpty,
              let issued = grouped["client_id"]?.first?.value ?? clientID, !issued.isEmpty,
              issued != "dynamic_agent_client", issued.count <= 512,
              clientID == nil || issued == clientID else {
            throw ScenarioError.invalid("ChatGPT n’a pas confirmé l’inscription de cette application.")
        }
        return (code, issued)
    }
}

struct ChatGPTDiscovery: Decodable, Sendable {
    let issuer: String
    let authorization_endpoint: URL
    let token_endpoint: URL
    let jwks_uri: URL
    let revocation_endpoint: URL
    func validate() throws {
        let endpoints = [authorization_endpoint, token_endpoint, jwks_uri, revocation_endpoint]
        let validEndpoints = endpoints.allSatisfy { endpoint in
            let validOrigin = endpoint.scheme == "https" && endpoint.host == "auth.openai.com"
            let validPort = endpoint.port == nil || endpoint.port == 443
            let noCredentials = endpoint.user == nil && endpoint.password == nil
            let noQueryOrFragment = endpoint.query == nil && endpoint.fragment == nil
            return validOrigin && validPort && noCredentials && noQueryOrFragment
        }
        guard issuer == ChatGPTProtocol.issuer, validEndpoints else {
            throw ScenarioError.invalid("La configuration de connexion ChatGPT n’est pas reconnue.")
        }
    }
}

struct ChatGPTTokens: Decodable, Sendable {
    let access_token: String
    let refresh_token: String?
    let id_token: String?
    let token_type: String
    let expires_in: Double
    let scope: String
}

struct OAuthServiceError: LocalizedError, Sendable {
    let status: Int
    let code: String?
    var requiresSignIn: Bool {
        ["invalid_grant", "invalid_refresh_token", "token_expired", "refresh_token_expired",
         "refresh_token_invalidated", "refresh_token_reused", "subscription_sharing_invalid_user"].contains(code ?? "")
    }
    var errorDescription: String? {
        if requiresSignIn || status == 401 { return "Votre connexion ChatGPT a expiré. Connectez-vous à nouveau." }
        if status == 429 || ["subscription_sharing_usage_limit_exceeded", "subscription_sharing_usage_unavailable"].contains(code ?? "") {
            return "La limite d’utilisation ChatGPT est atteinte. Consultez « Gérer l’utilisation »."
        }
        if status == 403 { return "ChatGPT n’autorise pas cette demande. Vérifiez l’accès à votre abonnement dans « Gérer l’utilisation »." }
        if status == 200 { return "ChatGPT a interrompu la proposition. Réessayez ou vérifiez l’accès à votre abonnement." }
        return "ChatGPT n’a pas pu traiter la demande (HTTP \(status)). Réessayez ou reconnectez votre compte."
    }
}

@MainActor
struct ChatGPTOAuthClient {
    var sessionConfiguration: URLSessionConfiguration = .ephemeral

    func discovery() async throws -> ChatGPTDiscovery {
        let data = try await data(URLRequest(url: URL(string: ChatGPTProtocol.issuer + "/.well-known/openid-configuration")!))
        let result = try JSONDecoder().decode(ChatGPTDiscovery.self, from: data)
        try result.validate()
        return result
    }
    func exchange(_ callback: (code: String, clientID: String), attempt: ChatGPTSignInAttempt,
                  discovery: ChatGPTDiscovery) async throws -> ChatGPTTokens {
        try await tokens(endpoint: discovery.token_endpoint, values: [
            "grant_type": "authorization_code", "client_id": callback.clientID, "code": callback.code,
            "code_verifier": attempt.verifier, "redirect_uri": attempt.redirectURI.absoluteString,
            "resource": ChatGPTProtocol.resource
        ])
    }
    func refresh(clientID: String, refreshToken: String, discovery: ChatGPTDiscovery) async throws -> ChatGPTTokens {
        try await tokens(endpoint: discovery.token_endpoint, values: [
            "grant_type": "refresh_token", "client_id": clientID, "refresh_token": refreshToken,
            "resource": ChatGPTProtocol.resource
        ])
    }
    func revoke(clientID: String, refreshToken: String, discovery: ChatGPTDiscovery) async throws {
        var request = URLRequest(url: discovery.revocation_endpoint)
        request.httpMethod = "POST"
        request.setValue("application/x-www-form-urlencoded", forHTTPHeaderField: "Content-Type")
        request.httpBody = ChatGPTProtocol.form(["client_id": clientID, "token": refreshToken, "token_type_hint": "refresh_token"])
        _ = try await data(request)
    }
    func identity(token: String, clientID: String, nonce: String?, discovery: ChatGPTDiscovery) async throws -> ChatGPTIdentity {
        let jwks = try await data(URLRequest(url: discovery.jwks_uri))
        return try ChatGPTIdentity.verify(token, jwks: jwks, issuer: discovery.issuer, clientID: clientID, nonce: nonce)
    }
    private func tokens(endpoint: URL, values: [String: String]) async throws -> ChatGPTTokens {
        var request = URLRequest(url: endpoint)
        request.httpMethod = "POST"
        request.setValue("application/x-www-form-urlencoded", forHTTPHeaderField: "Content-Type")
        request.httpBody = ChatGPTProtocol.form(values)
        let tokens = try JSONDecoder().decode(ChatGPTTokens.self, from: await data(request))
        guard tokens.token_type.lowercased() == "bearer", !tokens.access_token.isEmpty,
              tokens.expires_in.isFinite, tokens.expires_in > 0 else {
            throw ScenarioError.invalid("ChatGPT a renvoyé une connexion incomplète.")
        }
        return tokens
    }
    func data(_ request: URLRequest) async throws -> Data {
        var request = request
        request.timeoutInterval = 30
        let session = URLSession(configuration: sessionConfiguration, delegate: NoRedirect(), delegateQueue: nil)
        defer { session.invalidateAndCancel() }
        let (data, response) = try await BoundedResponse.data(for: request, session: session)
        try Task.checkCancellation()
        guard let response = response as? HTTPURLResponse else { throw ScenarioError.invalid("Réponse ChatGPT invalide.") }
        guard (200..<300).contains(response.statusCode) else {
            let root = (try? JSONSerialization.jsonObject(with: data)) as? [String: Any]
            let code = (root?["error"] as? String) ?? (root?["error"] as? [String: Any])?["code"] as? String
            throw OAuthServiceError(status: response.statusCode, code: code)
        }
        guard data.count < 20_000_000 else { throw ScenarioError.invalid("La réponse ChatGPT est trop volumineuse.") }
        return data
    }
}

struct ChatGPTIdentity: Sendable {
    let subject: String
    let email: String?
    let name: String?

    static func verify(_ token: String, jwks: Data, issuer: String, clientID: String,
                       nonce: String?, now: Date = .now) throws -> ChatGPTIdentity {
        func invalid() -> ScenarioError { .invalid("L’identité ChatGPT n’a pas pu être vérifiée. Recommencez la connexion.") }
        guard token.utf8.count < 128_000 else { throw invalid() }
        let parts = token.split(separator: ".", omittingEmptySubsequences: false).map(String.init)
        guard parts.count == 3,
              let header = try JSONSerialization.jsonObject(with: ChatGPTProtocol.decode64(parts[0])) as? [String: Any],
              header["alg"] as? String == "RS256", let kid = header["kid"] as? String,
              let root = try JSONSerialization.jsonObject(with: jwks) as? [String: Any],
              let keys = root["keys"] as? [[String: Any]],
              let key = keys.first(where: { $0["kid"] as? String == kid }),
              key["kty"] as? String == "RSA", key["use"] == nil || key["use"] as? String == "sig",
              key["alg"] == nil || key["alg"] as? String == "RS256",
              let n = key["n"] as? String, let e = key["e"] as? String else { throw invalid() }
        let modulus = try ChatGPTProtocol.decode64(n)
        guard modulus.count >= 256 else { throw invalid() }
        let der = sequence(integer(modulus) + integer(try ChatGPTProtocol.decode64(e)))
        let attributes: [String: Any] = [kSecAttrKeyType as String: kSecAttrKeyTypeRSA,
                                       kSecAttrKeyClass as String: kSecAttrKeyClassPublic]
        guard let publicKey = SecKeyCreateWithData(der as CFData, attributes as CFDictionary, nil),
              SecKeyVerifySignature(publicKey, .rsaSignatureMessagePKCS1v15SHA256,
                  Data((parts[0] + "." + parts[1]).utf8) as CFData,
                  try ChatGPTProtocol.decode64(parts[2]) as CFData, nil),
              let claims = try JSONSerialization.jsonObject(with: ChatGPTProtocol.decode64(parts[1])) as? [String: Any],
              claims["iss"] as? String == issuer,
              let subject = claims["sub"] as? String, !subject.isEmpty,
              let expiry = claims["exp"] as? Double, expiry.isFinite, expiry > now.timeIntervalSince1970,
              (claims["iat"] as? Double ?? 0) <= now.timeIntervalSince1970 + 60,
              (claims["nbf"] as? Double ?? 0) <= now.timeIntervalSince1970 + 30 else { throw invalid() }
        let audience = (claims["aud"] as? [String]) ?? (claims["aud"] as? String).map { [$0] } ?? []
        guard audience.contains(clientID),
              audience.count == 1 || claims["azp"] as? String == clientID,
              nonce == nil || claims["nonce"] as? String == nonce else { throw invalid() }
        return ChatGPTIdentity(subject: subject, email: claims["email"] as? String, name: claims["name"] as? String)
    }
    private static func length(_ value: Int) -> Data {
        if value < 128 { return Data([UInt8(value)]) }
        var size = value; var bytes: [UInt8] = []
        while size > 0 { bytes.insert(UInt8(size & 255), at: 0); size >>= 8 }
        return Data([0x80 | UInt8(bytes.count)] + bytes)
    }
    private static func integer(_ value: Data) -> Data {
        var bytes = Data(value.drop(while: { $0 == 0 }))
        if bytes.isEmpty { bytes = Data([0]) }
        if bytes[bytes.startIndex] & 0x80 != 0 { bytes.insert(0, at: bytes.startIndex) }
        return Data([0x02]) + length(bytes.count) + bytes
    }
    private static func sequence(_ value: Data) -> Data { Data([0x30]) + length(value.count) + value }
}
