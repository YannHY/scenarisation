import Foundation
import CryptoKit
import Security
import Testing
@testable import Scenarisation

@Suite("OAuth : retours et identité")
struct OAuthIdentityTests {
    @Test func authorizationUsesDynamicRegistrationAndPKCE() throws {
        let attempt = try ChatGPTSignInAttempt(redirectURI: URL(string: "http://127.0.0.1:45123/auth/callback")!,
            hostID: "urn:uuid:test")
        let url = try attempt.authorizationURL(endpoint: URL(string: ChatGPTProtocol.issuer + "/api/accounts/authorize")!)
        let values = Dictionary(uniqueKeysWithValues: URLComponents(url: url, resolvingAgainstBaseURL: false)!.queryItems!.map { ($0.name, $0.value!) })
        #expect(values["client_id"] == "dynamic_agent_client")
        #expect(values["agent_name_hint"] == "Scénarisation")
        #expect(values["resource"] == ChatGPTProtocol.resource)
        #expect(values["redirect_uri"] == attempt.redirectURI.absoluteString)
        #expect(values["scope"]?.contains("chatgpt.tokens.use.direct") == true)
        #expect(values["code_challenge_method"] == "S256")
        #expect(values["code_challenge"] == ChatGPTProtocol.base64URL(Data(SHA256.hash(data: Data(attempt.verifier.utf8)))))
        #expect(attempt.verifier.count == 43)
        let other = try ChatGPTSignInAttempt(redirectURI: attempt.redirectURI, hostID: attempt.hostID)
        #expect(other.state != attempt.state && other.nonce != attempt.nonce)
    }

    @Test func returningRegistrationIsNotReplaced() throws {
        let attempt = try ChatGPTSignInAttempt(redirectURI: URL(string: "http://127.0.0.1:45123/auth/callback")!,
            hostID: "urn:uuid:test", clientID: "oaiapp_saved")
        let url = try attempt.authorizationURL(endpoint: URL(string: ChatGPTProtocol.issuer + "/api/accounts/authorize")!)
        #expect(!url.absoluteString.contains("agent_name_hint"))
        let callback = URL(string: attempt.redirectURI.absoluteString + "?code=code&state=" + attempt.state)!
        #expect(try attempt.callback(callback).clientID == "oaiapp_saved")
        #expect(throws: ScenarioError.self) {
            try attempt.callback(URL(string: callback.absoluteString + "&client_id=oaiapp_other")!)
        }
    }

    @Test(arguments: ["state", "duplicate", "denied", "host", "path", "port", "missing-client", "dynamic-client", "expired"])
    func rejectsInvalidCallbacks(_ kind: String) throws {
        let attempt = try ChatGPTSignInAttempt(redirectURI: URL(string: "http://127.0.0.1:45123/auth/callback")!,
            hostID: "urn:uuid:test")
        var text = attempt.redirectURI.absoluteString + "?code=one-use-code&state=\(attempt.state)&client_id=oaiapp_issued"
        switch kind {
        case "state": text = text.replacingOccurrences(of: attempt.state, with: "wrong")
        case "duplicate": text += "&state=" + attempt.state
        case "denied": text += "&error=access_denied"
        case "host": text = text.replacingOccurrences(of: "127.0.0.1", with: "localhost")
        case "path": text = text.replacingOccurrences(of: "/auth/callback", with: "/callback")
        case "port": text = text.replacingOccurrences(of: ":45123", with: ":45124")
        case "missing-client": text = text.replacingOccurrences(of: "&client_id=oaiapp_issued", with: "")
        case "dynamic-client": text = text.replacingOccurrences(of: "oaiapp_issued", with: "dynamic_agent_client")
        default: break
        }
        #expect(throws: ScenarioError.self) {
            try attempt.callback(URL(string: text)!, now: kind == "expired" ? attempt.expiresAt.addingTimeInterval(1) : .now)
        }
    }

    @Test func verifiesSignedIdentity() throws {
        let fixture = try signedIdentity()
        let identity = try ChatGPTIdentity.verify(fixture.token, jwks: fixture.jwks,
            issuer: ChatGPTProtocol.issuer, clientID: "oaiapp_test", nonce: "one-time-nonce")
        #expect(identity.subject == "verified-subject")
        #expect(identity.email == "teacher@example.test")
    }

    @Test(arguments: ["signature", "nonce", "aud", "iss", "exp", "sub", "alg", "kid", "nbf"])
    func rejectsForgedOrMismatchedIdentity(_ change: String) throws {
        let fixture = try signedIdentity(change: change)
        #expect(throws: ScenarioError.self) {
            try ChatGPTIdentity.verify(fixture.token, jwks: fixture.jwks,
                issuer: ChatGPTProtocol.issuer, clientID: "oaiapp_test", nonce: "one-time-nonce")
        }
    }

    private func signedIdentity(change: String = "") throws -> (token: String, jwks: Data) {
        let attributes: [String: Any] = [kSecAttrKeyType as String: kSecAttrKeyTypeRSA, kSecAttrKeySizeInBits as String: 2048]
        let privateKey = try #require(SecKeyCreateRandomKey(attributes as CFDictionary, nil))
        let publicKey = try #require(SecKeyCopyPublicKey(privateKey))
        let der = try #require(SecKeyCopyExternalRepresentation(publicKey, nil)) as Data
        var offset = 1
        func length() -> Int {
            let first = Int(der[offset]); offset += 1
            if first < 128 { return first }
            var count = 0
            for _ in 0..<(first & 127) { count = count * 256 + Int(der[offset]); offset += 1 }
            return count
        }
        _ = length()
        func integer() -> Data {
            offset += 1
            let count = length()
            let result = der.subdata(in: offset..<(offset + count)); offset += count
            return Data(result.drop(while: { $0 == 0 }))
        }
        let n = ChatGPTProtocol.base64URL(integer()), e = ChatGPTProtocol.base64URL(integer())
        let jwks = try JSONSerialization.data(withJSONObject: ["keys": [["kty": "RSA", "kid": "key", "alg": "RS256", "use": "sig", "n": n, "e": e]]])
        var header = ["alg": "RS256", "kid": "key"]
        var payload: [String: Any] = ["iss": ChatGPTProtocol.issuer, "aud": "oaiapp_test",
            "sub": "verified-subject", "email": "teacher@example.test", "nonce": "one-time-nonce",
            "exp": Date.now.timeIntervalSince1970 + 3600, "iat": Date.now.timeIntervalSince1970]
        switch change {
        case "nonce": payload["nonce"] = "wrong"
        case "aud": payload["aud"] = "oaiapp_wrong"
        case "iss": payload["iss"] = "https://example.test"
        case "exp": payload["exp"] = Date.now.timeIntervalSince1970 - 60
        case "sub": payload.removeValue(forKey: "sub")
        case "alg": header["alg"] = "none"
        case "kid": header["kid"] = "wrong"
        case "nbf": payload["nbf"] = Date.now.timeIntervalSince1970 + 3600
        default: break
        }
        let prefix = try ChatGPTProtocol.base64URL(JSONSerialization.data(withJSONObject: header)) + "."
            + ChatGPTProtocol.base64URL(JSONSerialization.data(withJSONObject: payload))
        let signature = try #require(SecKeyCreateSignature(privateKey, .rsaSignatureMessagePKCS1v15SHA256,
            Data(prefix.utf8) as CFData, nil)) as Data
        return (prefix + "." + ChatGPTProtocol.base64URL(change == "signature" ? Data(repeating: 0, count: signature.count) : signature), jwks)
    }
}

final class OAuthMockProtocol: URLProtocol, @unchecked Sendable {
    @MainActor static var handler: (@MainActor (URLRequest) throws -> (Int, String?, Data))?
    override class func canInit(with request: URLRequest) -> Bool { true }
    override class func canonicalRequest(for request: URLRequest) -> URLRequest { request }
    override func startLoading() {
        Task { @MainActor in
        do {
            let (status, type, data) = try Self.handler!(request)
            let response = HTTPURLResponse(url: request.url!, statusCode: status, httpVersion: "HTTP/1.1",
                headerFields: type.map { ["Content-Type": $0] })!
            client?.urlProtocol(self, didReceive: response, cacheStoragePolicy: .notAllowed)
            client?.urlProtocol(self, didLoad: data)
            client?.urlProtocolDidFinishLoading(self)
        } catch { client?.urlProtocol(self, didFailWithError: error) }
        }
    }
    override func stopLoading() {}
}

@Suite("OAuth : transport et session", .serialized)
@MainActor
struct OAuthTransportTests {
    private func configuration() -> URLSessionConfiguration {
        let config = URLSessionConfiguration.ephemeral
        config.protocolClasses = [OAuthMockProtocol.self]
        return config
    }
    private func body(_ request: URLRequest) throws -> Data {
        if let body = request.httpBody { return body }
        let stream = try #require(request.httpBodyStream)
        stream.open(); defer { stream.close() }
        var result = Data(); var bytes = [UInt8](repeating: 0, count: 1024)
        while stream.hasBytesAvailable {
            let size = stream.read(&bytes, maxLength: bytes.count)
            if size <= 0 { break }
            result.append(contentsOf: bytes.prefix(size))
        }
        return result
    }
    private var discoveryData: Data {
        Data("""
        {"issuer":"https://auth.openai.com","authorization_endpoint":"https://auth.openai.com/api/accounts/authorize",
        "token_endpoint":"https://auth.openai.com/api/accounts/oauth/token","jwks_uri":"https://auth.openai.com/.well-known/jwks.json",
        "revocation_endpoint":"https://auth.openai.com/api/accounts/oauth/revoke"}
        """.utf8)
    }

    @Test func codeExchangeUsesIssuedClientIDAndExactRedirect() async throws {
        let discovery = try JSONDecoder().decode(ChatGPTDiscovery.self, from: discoveryData)
        let attempt = try ChatGPTSignInAttempt(redirectURI: URL(string: "http://127.0.0.1:45321/auth/callback")!, hostID: "urn:uuid:test")
        OAuthMockProtocol.handler = { request in
            #expect(request.url == discovery.token_endpoint)
            #expect(request.httpMethod == "POST")
            let form = try String(decoding: body(request), as: UTF8.self)
            let values = Dictionary(uniqueKeysWithValues: URLComponents(string: "https://example.test/?" + form)!.queryItems!.map { ($0.name, $0.value!) })
            #expect(values["client_id"] == "oaiapp_issued")
            #expect(values["code"] == "code+plus")
            #expect(values["redirect_uri"] == attempt.redirectURI.absoluteString)
            #expect(values["code_verifier"] == attempt.verifier)
            #expect(values["resource"] == ChatGPTProtocol.resource)
            #expect(values["client_secret"] == nil)
            return (200, "application/json", Data("""
                {"access_token":"test-access","refresh_token":"test-refresh","id_token":"test-id","token_type":"Bearer",
                "expires_in":3600,"scope":"openid resource.invoke chatgpt.tokens.use.direct"}
                """.utf8))
        }
        defer { OAuthMockProtocol.handler = nil }
        let tokens = try await ChatGPTOAuthClient(sessionConfiguration: configuration())
            .exchange((code: "code+plus", clientID: "oaiapp_issued"), attempt: attempt, discovery: discovery)
        #expect(tokens.access_token == "test-access")
    }

    @Test func modelsUseTheAccountCatalogAndServerOrdering() async throws {
        OAuthMockProtocol.handler = { request in
            #expect(request.url?.absoluteString == "https://api.openai.com/v1/models")
            #expect(request.value(forHTTPHeaderField: "Authorization") == "Bearer test-access")
            return (200, "application/json", Data("""
            {"models":[{"slug":"second","display_name":"Recommended","visibility":"list"},
            {"slug":"hidden","display_name":"Hidden","visibility":"hide"},
            {"slug":"first","display_name":"Other","visibility":"list"}]}
            """.utf8))
        }
        defer { OAuthMockProtocol.handler = nil }
        let models = try await ChatGPTAPI.models(token: "test-access", sessionConfiguration: configuration())
        #expect(models.map(\.id) == ["second", "first"])
        #expect(models.first?.label == "Recommended")
    }

    @Test func chatGPTUsesResponsesWithStorageDisabledAndRequiresCompletion() async throws {
        let expected = Scenario.template()
        let text = String(decoding: try expected.encoded(), as: UTF8.self)
        let delta = try JSONSerialization.data(withJSONObject: ["type": "response.output_text.delta", "delta": text])
        let events = "data: " + String(decoding: delta, as: UTF8.self) + "\n\ndata: {\"type\":\"response.completed\"}\n\n"
        OAuthMockProtocol.handler = { request in
            #expect(request.url?.absoluteString == "https://api.openai.com/v1/responses")
            let root = try #require(JSONSerialization.jsonObject(with: body(request)) as? [String: Any])
            #expect(root["store"] as? Bool == false)
            #expect(root["stream"] as? Bool == true)
            #expect(root["model"] as? String == "account-model")
            #expect(root["messages"] == nil)
            return (200, "text/event-stream", Data(events.utf8))
        }
        defer { OAuthMockProtocol.handler = nil }
        let result = try await AIClient.propose(scenario: .blank(), prompt: "Une séance", configuration:
            AIConfiguration(provider: "chatgpt", endpoint: AIProvider.chatgpt.defaultEndpoint, model: "account-model"),
            key: "test-access", sessionConfiguration: configuration())
        #expect(result == expected)
    }

    @Test func chatGPTAcceptsACompletedJSONResponse() async throws {
        let expected = Scenario.template()
        let text = String(decoding: try expected.encoded(), as: UTF8.self)
        let data = try JSONSerialization.data(withJSONObject: [
            "object": "response", "status": "completed",
            "output": [["type": "message", "role": "assistant", "content": [["type": "output_text", "text": text]]]]
        ])
        var calls = 0
        OAuthMockProtocol.handler = { request in
            calls += 1
            let root = try #require(JSONSerialization.jsonObject(with: body(request)) as? [String: Any])
            #expect(root["stream"] as? Bool == true)
            #expect(root["store"] as? Bool == false)
            return (200, "application/json; charset=utf-8", data)
        }
        defer { OAuthMockProtocol.handler = nil }
        let result = try await AIClient.propose(scenario: .blank(), prompt: "Une séance", configuration:
            AIConfiguration(provider: "chatgpt", endpoint: AIProvider.chatgpt.defaultEndpoint, model: "account-model"),
            key: "test-access", sessionConfiguration: configuration())
        #expect(result == expected)
        #expect(calls == 1)
    }

    @Test func chatGPTConversationSendsHistoryAndContinuesAfterALocalTool() async throws {
        var calls = 0
        OAuthMockProtocol.handler = { request in
            calls += 1
            let root = try #require(JSONSerialization.jsonObject(with: body(request)) as? [String: Any])
            #expect(root["store"] as? Bool == false && root["stream"] as? Bool == true)
            let input = try #require(root["input"] as? [[String: Any]])
            let text = try #require(input.first?["content"] as? String)
            let context = try #require(JSONSerialization.jsonObject(with: Data(text.utf8)) as? [String: Any])
            let conversation = try #require(context["conversation"] as? [[String: String]])
            #expect(conversation.map { $0["content"] } == ["Une séance sur le conte", "Quel niveau ?", "Sixième"])
            let answer: String
            if calls == 1 {
                answer = "{\"kind\":\"tool\",\"name\":\"analyze\",\"arguments\":{}}"
            } else {
                let exchanges = try #require(context["tool_exchanges"] as? [[String: Any]])
                let result = try #require(exchanges.last?["result"] as? [String: Any])
                #expect(result["minutes"] as? Double == 30)
                answer = "{\"kind\":\"message\",\"message\":\"La séance actuelle dure 30 minutes. Quelle durée visez-vous ?\"}"
            }
            let delta = try JSONSerialization.data(withJSONObject: ["type": "response.output_text.delta", "delta": answer])
            let events = "data: " + String(decoding: delta, as: UTF8.self) + "\n\ndata: {\"type\":\"response.completed\"}\n\n"
            return (200, "text/event-stream", Data(events.utf8))
        }
        defer { OAuthMockProtocol.handler = nil }
        let reply = try await AssistantConversation.reply(scenario: .template(), history: [
            .init(role: .user, content: "Une séance sur le conte"), .init(role: .assistant, content: "Quel niveau ?"),
            .init(role: .user, content: "Sixième")], configuration:
            AIConfiguration(provider: "chatgpt", endpoint: AIProvider.chatgpt.defaultEndpoint, model: "account-model"),
            key: "test-access", sessionConfiguration: configuration())
        #expect(calls == 2 && reply.proposal == nil)
        #expect(reply.message.contains("30 minutes"))
    }

    @Test(arguments: [nil, "application/json", "application/octet-stream", "text/event-stream; charset=utf-8"] as [String?])
    func chatGPTReadsEventsDespiteMissingOrIncorrectContentType(_ contentType: String?) async throws {
        let events = ": keepalive\r\nevent: response.output_text.delta\r\ndata: {\"delta\":\"évaluation\"}\r\n\r\n"
            + "data: {\"type\":\"response.completed\"}"
        OAuthMockProtocol.handler = { _ in (200, contentType, Data(events.utf8)) }
        defer { OAuthMockProtocol.handler = nil }
        let text = try await ChatGPTAPI.response(model: "account-model", system: "", prompt: "", token: "test-access",
            sessionConfiguration: configuration())
        #expect(text == "évaluation")
    }

    @Test(arguments: [200, 401, 403, 429])
    func chatGPTPreservesStructuredJSONErrors(_ status: Int) async throws {
        OAuthMockProtocol.handler = { _ in
            (status, "application/json", Data("{\"error\":{\"code\":\"subscription_sharing_usage_limit_exceeded\"}}".utf8))
        }
        defer { OAuthMockProtocol.handler = nil }
        do {
            _ = try await ChatGPTAPI.response(model: "account-model", system: "", prompt: "", token: "test-access",
                sessionConfiguration: configuration())
            Issue.record("Une erreur JSON ne doit pas produire de proposition.")
        } catch let error as OAuthServiceError {
            #expect(error.status == status)
            #expect(error.code == "subscription_sharing_usage_limit_exceeded")
        }
    }

    @Test(arguments: [
        "{\"object\":\"response\",\"status\":\"incomplete\",\"output\":[]}",
        "{\"object\":\"response\",\"status\":\"in_progress\",\"output\":[]}",
        "{\"object\":\"response\",\"status\":\"completed\",\"output\":[]}",
        "{\"status\":\"completed\",\"output\":[{\"content\":[{\"type\":\"output_text\",\"text\":\"{}\"}]}]}",
        "data: {\"type\":\"response.output_text.delta\",\"delta\":\"{}\"}\n\ndata: [DONE]\n\n",
        "<html>Une page réseau contenant des informations privées</html>"
    ])
    func chatGPTRejectsIncompleteOrUnexpectedBodies(_ body: String) async throws {
        OAuthMockProtocol.handler = { _ in (200, "application/json", Data(body.utf8)) }
        defer { OAuthMockProtocol.handler = nil }
        do {
            _ = try await ChatGPTAPI.response(model: "account-model", system: "", prompt: "", token: "test-access",
                sessionConfiguration: configuration())
            Issue.record("Une réponse incomplète ou inattendue ne doit pas produire de proposition.")
        } catch let error as ScenarioError {
            #expect(!error.localizedDescription.contains("informations privées"))
        }
    }

    @Test func refreshIsSerializedAndRotatedTokensAreSavedTogether() async throws {
        var refreshCalls = 0
        OAuthMockProtocol.handler = { request in
            if request.url?.path.contains("openid-configuration") == true { return (200, "application/json", discoveryData) }
            refreshCalls += 1
            let form = try String(decoding: body(request), as: UTF8.self)
            #expect(form.contains("refresh_token=old-refresh"))
            #expect(!form.contains("dynamic_agent_client"))
            #expect(!form.contains("scope="))
            return (200, "application/json", Data("""
            {"access_token":"new-access","refresh_token":"new-refresh","token_type":"Bearer","expires_in":3600,
            "scope":"openid resource.invoke chatgpt.tokens.use.direct"}
            """.utf8))
        }
        defer { OAuthMockProtocol.handler = nil }
        try await withAuth(expired: true) { auth, saved, _ in
            async let first = auth.bearerToken()
            async let second = auth.bearerToken()
            let values = try await (first, second)
            #expect(values.0 == "new-access" && values.1 == "new-access")
            #expect(refreshCalls == 1)
            #expect(saved.value.contains("new-access") && saved.value.contains("new-refresh"))
            #expect(!saved.value.contains("old-refresh"))
        }
    }

    @Test func declinedPlanPermissionPreventsAnyInference() async throws {
        OAuthMockProtocol.handler = { _ in Issue.record("Aucun appel réseau ne doit partir sans permission."); return (500, "application/json", Data()) }
        defer { OAuthMockProtocol.handler = nil }
        try await withAuth(scopes: ["openid"]) { auth, _, _ in
            #expect(auth.activeAccount?.connected == true)
            #expect(auth.activeAccount?.canUsePlan == false)
            await #expect(throws: ScenarioError.self) { try await auth.bearerToken() }
        }
    }

    @Test(arguments: [400, 503])
    func refreshFailurePreservesTemporarySessionsAndClearsRevokedOnes(_ status: Int) async throws {
        OAuthMockProtocol.handler = { request in
            if request.url?.path.contains("openid-configuration") == true { return (200, "application/json", discoveryData) }
            return (status, "application/json", Data(status == 400 ? "{\"error\":\"invalid_grant\"}".utf8 : "{\"error\":\"temporary\"}".utf8))
        }
        defer { OAuthMockProtocol.handler = nil }
        try await withAuth(expired: true) { auth, saved, _ in
            await #expect(throws: OAuthServiceError.self) { try await auth.bearerToken() }
            #expect(auth.activeAccount?.connected == (status == 503))
            #expect(saved.value.contains("old-refresh") == (status == 503))
        }
    }

    @Test(arguments: [200, 503])
    func signOutRevokesAndClearsTokensWhileRetainingRegistration(_ status: Int) async throws {
        OAuthMockProtocol.handler = { request in
            if request.url?.path.contains("openid-configuration") == true { return (200, "application/json", discoveryData) }
            #expect(request.url?.path == "/api/accounts/oauth/revoke")
            let form = try String(decoding: body(request), as: UTF8.self)
            #expect(form.contains("token_type_hint=refresh_token"))
            #expect(form.contains("token=old-refresh"))
            return (status, "application/json", Data())
        }
        defer { OAuthMockProtocol.handler = nil }
        try await withAuth { auth, saved, _ in
            let warning = try await auth.signOut()
            #expect((warning != nil) == (status != 200))
            #expect(auth.activeAccount?.connected == false)
            #expect(saved.value.contains("oaiapp_test"))
            #expect(!saved.value.contains("old-refresh") && !saved.value.contains("old-access"))
        }
    }

    private final class Saved { var value = "" }
    private func withAuth(expired: Bool = false, scopes: [String] = ["openid", "resource.invoke", "chatgpt.tokens.use.direct"],
                          _ body: @MainActor (ChatGPTAuth, Saved, UserDefaults) async throws -> Void) async throws {
        let name = "ScenarisationTests.OAuth." + UUID().uuidString
        let defaults = try #require(UserDefaults(suiteName: name))
        defer { defaults.removePersistentDomain(forName: name) }
        let credential = ChatGPTCredential(clientID: "oaiapp_test", subject: "test-subject", email: "teacher@example.test",
            accessToken: "old-access", refreshToken: "old-refresh", idToken: "old-id", scopes: scopes,
            expiresAt: .now.addingTimeInterval(expired ? -60 : 3600))
        let root: [String: Any] = ["registrations": try JSONSerialization.jsonObject(with: JSONEncoder().encode([credential])),
                                 "selectedClientID": "oaiapp_test"]
        let saved = Saved()
        saved.value = String(decoding: try JSONSerialization.data(withJSONObject: root), as: UTF8.self)
        let auth = ChatGPTAuth(defaults: defaults, client: ChatGPTOAuthClient(sessionConfiguration: configuration()),
            read: { _ in saved.value }, write: { value, _ in saved.value = value })
        try await body(auth, saved, defaults)
        #expect(!String(describing: defaults.dictionaryRepresentation()).contains("old-access"))
    }
}

@Suite("Réponses interrompues et Claude Code")
@MainActor
struct AccountAdapterTests {
    @Test func callbackListenerUsesLoopbackAndIgnoresUnrelatedPaths() async throws {
        let listener = OAuthLoopback()
        defer { listener.stop() }
        let redirect = try await listener.start()
        #expect(redirect.host == "127.0.0.1" && redirect.path == "/auth/callback")
        let (_, invalidResponse) = try await URLSession.shared.data(from: URL(string: "http://127.0.0.1:\(redirect.port!)/favicon.ico")!)
        #expect((invalidResponse as? HTTPURLResponse)?.statusCode == 404)
        let expected = URL(string: redirect.absoluteString + "?code=test-code&state=test-state&client_id=test-client")!
        let (_, validResponse) = try await URLSession.shared.data(from: expected)
        #expect((validResponse as? HTTPURLResponse)?.statusCode == 200)
        #expect(try await listener.waitForCallback() == expected)
    }

    @Test func callbackResponseSurvivesTheAuthorizationScopeClosing() async throws {
        let listener = OAuthLoopback()
        defer { listener.stop() }
        let redirect = try await listener.start()
        let expected = URL(string: redirect.absoluteString + "?code=test-code&state=test-state")!
        let authorization = Task {
            defer { listener.stop() }
            return try await listener.waitForCallback()
        }
        let (data, response) = try await URLSession.shared.data(from: expected)
        #expect((response as? HTTPURLResponse)?.statusCode == 200)
        #expect(String(decoding: data, as: UTF8.self).contains("Retour reçu"))
        #expect(try await authorization.value == expected)
    }

    @Test func incompleteStreamCannotBecomeAProposal() throws {
        var stream = ChatGPTEventStream()
        try stream.consume("data: {\"type\":\"response.output_text.delta\",\"delta\":\"{}\"}")
        try stream.consume("")
        #expect(throws: ScenarioError.self) { try stream.result() }
        try stream.consume("data: [DONE]"); try stream.consume("")
        #expect(throws: ScenarioError.self) { try stream.result() }
    }
    @Test func lateUsageLimitRejectsAlreadyStreamedText() throws {
        var stream = ChatGPTEventStream()
        try stream.consume("data: {\"type\":\"response.output_text.delta\",\"delta\":\"{}\"}"); try stream.consume("")
        try stream.consume("data: {\"type\":\"response.failed\",\"response\":{\"error\":{\"code\":\"subscription_sharing_usage_limit_exceeded\"}}}")
        #expect(throws: OAuthServiceError.self) { try stream.consume("") }
    }
    @Test func APIKeysAndUnrecognisedEndpointsCannotReplaceAccountAuthentication() throws {
        let apiStatus = Data("{\"loggedIn\":true,\"authMethod\":\"api_key\"}".utf8)
        #expect(throws: ScenarioError.self) { try ClaudeCodeClient.account(from: NativeProcessResult(status: 0, output: apiStatus)) }
        let accountStatus = Data("{\"loggedIn\":true,\"authMethod\":\"claude.ai\",\"email\":\"teacher@example.test\"}".utf8)
        #expect(try ClaudeCodeClient.account(from: NativeProcessResult(status: 0, output: accountStatus))?.email == "teacher@example.test")
        #expect(throws: ScenarioError.self) {
            try AIConfiguration(provider: "chatgpt", endpoint: "https://example.test/v1", model: "test").requestURL()
        }
    }
    @Test func claudeProcessDisablesToolsAndRemovesBillingOverrides() {
        let args = ClaudeCodeClient.generationArguments(model: "sonnet", system: "Public system instructions")
        let tools = args.firstIndex(of: "--tools")!
        #expect(args[tools + 1] == "")
        #expect(args.contains("--safe-mode") && args.contains("--no-session-persistence"))
        #expect(args.contains("--strict-mcp-config") && args.contains("mcp__*"))
        let environment = ClaudeCodeClient.environment(["ANTHROPIC_API_KEY": "secret", "CLAUDE_CODE_OAUTH_TOKEN": "secret", "PATH": "/usr/bin"])
        #expect(environment["ANTHROPIC_API_KEY"] == nil && environment["CLAUDE_CODE_OAUTH_TOKEN"] == nil)
    }
    @Test func largeScenarioTravelsThroughStdin() async throws {
        let input = Data(String(repeating: "scenario-data\n", count: 10_000).utf8)
        let result = try await NativeProcessRun.run(executable: URL(fileURLWithPath: "/bin/cat"), arguments: [], input: input)
        #expect(result.status == 0 && result.output == input)
    }
    @Test func processCancellationStopsTheOwnedChild() async throws {
        let task = Task { try await NativeProcessRun.run(executable: URL(fileURLWithPath: "/bin/sleep"), arguments: ["20"]) }
        try await Task.sleep(for: .milliseconds(100))
        task.cancel()
        await #expect(throws: CancellationError.self) { try await task.value }
    }
}
