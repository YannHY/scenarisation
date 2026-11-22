import Foundation
import Testing
@testable import Scenarisation

private final class BoundedProtocol: URLProtocol, @unchecked Sendable {
    override class func canInit(with request: URLRequest) -> Bool { true }
    override class func canonicalRequest(for request: URLRequest) -> URLRequest { request }
    override func startLoading() {
        let text = request.url!.path == "/lines" ? "a\r\n\nb\n" : String(repeating: "x", count: 65)
        let response = HTTPURLResponse(url: request.url!, statusCode: 200, httpVersion: "HTTP/1.1", headerFields: [:])!
        client?.urlProtocol(self, didReceive: response, cacheStoragePolicy: .notAllowed)
        // No Content-Length: the limit must be enforced against actual received bytes.
        for byte in text.utf8 { client?.urlProtocol(self, didLoad: Data([byte])) }
        client?.urlProtocolDidFinishLoading(self)
    }
    override func stopLoading() {}
}

@Suite struct BoundedResponseTests {
    private func session() -> URLSession {
        let configuration = URLSessionConfiguration.ephemeral
        configuration.protocolClasses = [BoundedProtocol.self]
        return URLSession(configuration: configuration)
    }

    @Test func rejectsOversizedBodyWithoutContentLength() async throws {
        let session = session(); defer { session.invalidateAndCancel() }
        do {
            _ = try await BoundedResponse.data(for: URLRequest(url: URL(string: "https://example.org/body")!), session: session, limit: 64)
            Issue.record("Le corps excessif doit être refusé pendant sa réception.")
        } catch { #expect(error is ScenarioError) }
    }

    @Test func rejectsUnterminatedStreamingLine() async throws {
        let session = session(); defer { session.invalidateAndCancel() }
        let (bytes, _) = try await session.bytes(from: URL(string: "https://example.org/body")!)
        do {
            for try await _ in BoundedResponse.Lines(bytes: bytes, limit: 64) {}
            Issue.record("Une ligne sans fin doit respecter le budget d’octets.")
        } catch { #expect(error is ScenarioError) }
    }

    @Test func preservesEmptyLinesAndCountsDelimiters() async throws {
        let session = session(); defer { session.invalidateAndCancel() }
        let (bytes, _) = try await session.bytes(from: URL(string: "https://example.org/lines")!)
        var lines: [String] = []
        for try await line in BoundedResponse.Lines(bytes: bytes, limit: 6) { lines.append(line) }
        #expect(lines == ["a", "", "b"])
        let (limited, _) = try await session.bytes(from: URL(string: "https://example.org/lines")!)
        do {
            for try await _ in BoundedResponse.Lines(bytes: limited, limit: 5) {}
            Issue.record("Les séparateurs doivent compter dans la limite totale.")
        } catch { #expect(error is ScenarioError) }
    }
}
