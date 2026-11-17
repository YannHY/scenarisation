import Foundation
import Testing
@testable import Scenarisation

private final class SiteProtocol: URLProtocol, @unchecked Sendable {
    nonisolated(unsafe) static var handler: (@Sendable (URLRequest) throws -> (Int, String, Data))?
    override class func canInit(with request: URLRequest) -> Bool { true }
    override class func canonicalRequest(for request: URLRequest) -> URLRequest { request }
    override func startLoading() {
        do {
            let (status, type, data) = try Self.handler!(request)
            let response = HTTPURLResponse(url: request.url!, statusCode: status, httpVersion: "HTTP/1.1",
                headerFields: ["Content-Type": type])!
            client?.urlProtocol(self, didReceive: response, cacheStoragePolicy: .notAllowed)
            client?.urlProtocol(self, didLoad: data)
            client?.urlProtocolDidFinishLoading(self)
        } catch { client?.urlProtocol(self, didFailWithError: error) }
    }
    override func stopLoading() {}
}

@Suite("API native Scénarisation", .serialized)
struct SiteSyncAPITests {
    private func api(token: String = "test-only") throws -> SiteSyncAPI {
        let configuration = URLSessionConfiguration.ephemeral
        configuration.protocolClasses = [SiteProtocol.self]
        return try SiteSyncAPI(baseURL: URL(string: "https://example.org/api/v1/")!, configuration: configuration,
                               credential: { token })
    }
    private static func body(_ request: URLRequest) -> Data {
        if let body = request.httpBody { return body }
        guard let stream = request.httpBodyStream else { return Data() }
        stream.open(); defer { stream.close() }
        var data = Data(); var bytes = [UInt8](repeating: 0, count: 4096)
        while stream.hasBytesAvailable {
            let count = stream.read(&bytes, maxLength: bytes.count)
            if count <= 0 { break }
            data.append(contentsOf: bytes.prefix(count))
        }
        return data
    }

    @Test func accountAndListUseTheNativeAPIWithABearerAndNoCookies() async throws {
        SiteProtocol.handler = { request in
            #expect(request.httpMethod == "GET")
            #expect(request.value(forHTTPHeaderField: "Authorization") == "Bearer test-only")
            #expect(request.value(forHTTPHeaderField: "Cookie") == nil)
            #expect(request.value(forHTTPHeaderField: "Accept") == "application/json")
            let text = request.url?.lastPathComponent == "me.php"
                ? "{\"success\":true,\"user\":{\"id\":4,\"username\":\"Enseignant\",\"email\":\"test@example.org\"}}"
                : "{\"success\":true,\"items\":[{\"id\":7,\"title\":\"Mon scénario\",\"revision\":3,\"updatedAt\":\"2026-11-16T12:00:00Z\"}]}"
            #expect(request.url?.path.hasPrefix("/api/v1/") == true)
            return (200, "application/json", Data(text.utf8))
        }
        defer { SiteProtocol.handler = nil }
        let client = try api()
        #expect(try await client.user().id == 4)
        #expect(try await client.list().first?.revision == 3)
    }

    @Test func downloadsPreserveUnexposedWebFields() async throws {
        var expected = Scenario.template()
        expected.fields["futureField"] = .array([.string("Conserver")])
        let remote = SiteDesign(id: 7, title: expected.name, revision: 3, updatedAt: "date", document: expected)
        struct Envelope: Encodable { let success = true; let design: SiteDesign }
        let response = try JSONEncoder().encode(Envelope(design: remote))
        SiteProtocol.handler = { request in
            #expect(request.url?.path == "/api/v1/get_design.php")
            #expect(URLComponents(url: request.url!, resolvingAgainstBaseURL: false)?.queryItems?.first?.value == "7")
            return (200, "application/json", response)
        }
        defer { SiteProtocol.handler = nil }
        #expect(try await api().download(7).document == expected)
    }

    @Test func savesSendTheExpectedRevisionAndStableCreationUUIDWithoutMacMetadata() async throws {
        let id = UUID()
        var scenario = Scenario.template()
        scenario.fields["_scenarisationMac"] = .object(["accountKey": .string("local-only")])
        scenario.fields["unexposed"] = .bool(true)
        SiteProtocol.handler = { request in
            #expect(request.httpMethod == "POST")
            #expect(request.url?.path == "/api/v1/save_design.php")
            let sent = try JSONDecoder().decode(SiteSaveRequest.self, from: Self.body(request))
            #expect(sent.client_uuid == id && sent.design_id == 7 && sent.expected_revision == 3)
            #expect(sent.document.fields["unexposed"] == .bool(true))
            #expect(sent.document.fields["_scenarisationMac"] == nil)
            return (200, "application/json", Data("{\"success\":true,\"design\":{\"id\":7,\"title\":\"Scénario\",\"revision\":4,\"updatedAt\":\"date\"}}".utf8))
        }
        defer { SiteProtocol.handler = nil }
        let saved = try await api().save(SiteSaveRequest(client_uuid: id, design_id: 7, expected_revision: 3,
                                                        title: scenario.name, document: scenario))
        #expect(saved.revision == 4)
    }

    @Test func deletionsSendTheExpectedRevision() async throws {
        SiteProtocol.handler = { request in
            #expect(request.httpMethod == "POST" && request.url?.path == "/api/v1/delete_design.php")
            #expect(request.value(forHTTPHeaderField: "Authorization") == "Bearer test-only")
            let sent = try JSONSerialization.jsonObject(with: Self.body(request)) as? [String: Int]
            #expect(sent == ["design_id": 7, "expected_revision": 3])
            return (200, "application/json", Data("{\"success\":true,\"deleted\":{\"id\":7}}".utf8))
        }
        defer { SiteProtocol.handler = nil }
        try await api().delete(7, expectedRevision: 3)
        SiteProtocol.handler = { _ in (200, "application/json", Data("{\"success\":true,\"deleted\":{\"id\":8}}".utf8)) }
        await #expect(throws: SiteSyncError.invalidResponse) { try await api().delete(7, expectedRevision: 3) }
        SiteProtocol.handler = { _ in (409, "application/json", Data("{}".utf8)) }
        await #expect(throws: SiteSyncError.conflict) { try await api().delete(7, expectedRevision: 3) }
    }

    @Test(arguments: [401, 403, 404, 409, 500, 302])
    func httpErrorsAreReportedWithoutTreatingTheDocumentAsSaved(_ code: Int) async throws {
        SiteProtocol.handler = { _ in (code, "application/json", Data("{}".utf8)) }
        defer { SiteProtocol.handler = nil }
        do {
            _ = try await api().download(7)
            Issue.record("HTTP \(code) ne doit pas réussir.")
        } catch let error as SiteSyncError {
            switch (code, error) {
            case (401, .unauthorized), (403, .forbidden), (404, .missing), (409, .conflict),
                 (500, .invalidResponse), (302, .invalidResponse): break
            default: Issue.record("Erreur inattendue pour HTTP \(code).")
            }
        }
    }

    @Test(arguments: ["", "line\nbreak"])
    func invalidCredentialsDoNotStartARequest(_ token: String) async throws {
        SiteProtocol.handler = { _ in Issue.record("Aucun appel réseau attendu."); return (200, "application/json", Data()) }
        defer { SiteProtocol.handler = nil }
        await #expect(throws: SiteSyncError.self) { _ = try await api(token: token).list() }
    }

    @Test(arguments: [
        "{\"success\":false,\"items\":[]}",
        "{\"success\":true,\"items\":[{\"id\":1,\"title\":\"x\",\"revision\":0,\"updatedAt\":\"date\"}]}",
        "<html>Login</html>"
    ])
    func malformedPayloadsDoNotBecomeASynchronizedState(_ payload: String) async throws {
        SiteProtocol.handler = { _ in (200, "application/json", Data(payload.utf8)) }
        defer { SiteProtocol.handler = nil }
        await #expect(throws: SiteSyncError.self) { _ = try await api().list() }
    }

    @Test func htmlLoginPagesAndNonAdvancingRevisionsAreRejected() async throws {
        SiteProtocol.handler = { _ in (200, "text/html", Data("<html>Login</html>".utf8)) }
        await #expect(throws: SiteSyncError.self) { _ = try await api().list() }
        SiteProtocol.handler = { _ in (200, "application/json", Data("{\"success\":true,\"design\":{\"id\":7,\"title\":\"Scénario\",\"revision\":3,\"updatedAt\":\"date\"}}".utf8)) }
        defer { SiteProtocol.handler = nil }
        await #expect(throws: SiteSyncError.self) {
            _ = try await api().save(SiteSaveRequest(client_uuid: UUID(), design_id: 7, expected_revision: 3,
                                                    title: "Scénario", document: .template()))
        }
    }

    @Test(arguments: ["http://example.org/api/", "https://user:password@example.org/api/", "https://example.org/?token=secret", "file:///tmp/api"])
    func unsafeBaseURLsAreRejected(_ url: String) throws {
        #expect(throws: SiteSyncError.self) { _ = try SiteSyncAPI(baseURL: URL(string: url)!, credential: { "test-only" }) }
    }

    @Test func serverAndUserBothPartitionTheAccount() {
        let user = SiteUser(id: 1, username: "Compte", email: "test@example.org")
        let first = SiteSyncAccount.live(server: URL(string: "https://example.org/")!, user: user)
        let second = SiteSyncAccount.live(server: URL(string: "https://other.example.org/")!, user: user)
        let third = SiteSyncAccount.live(server: URL(string: "https://example.org/")!,
            user: SiteUser(id: 2, username: "Autre", email: "test@example.org"))
        #expect(first.key != second.key && first.key != third.key)
    }
}
