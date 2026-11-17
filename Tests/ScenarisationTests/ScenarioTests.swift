import Foundation
import Testing
@testable import Scenarisation

@Suite("Documents et compatibilité")
struct ScenarioTests {
    @Test func roundTripPreservesWebFields() throws {
        var scenario = Scenario.template()
        scenario.fields["futureWebFeature"] = .object(["revision": .number(42), "enabled": .bool(true)])
        scenario.meta["custom"] = .array([.string("préservé"), .null])
        scenario.meta["sliders"] = .array([.object(["id": .string("outcome-1"), "category": .string("apply"),
            "verb": .string("Expliquer"), "text": .string("un choix")])])
        scenario.moments[0].fields["futureMoment"] = .bool(true)
        scenario.moments[0].activities[0].fields["tools"] = .array([.string("competency:acquerir:1")])
        scenario.moments[0].activities[0].fields["futureActivity"] = .string("à conserver")
        let original = try scenario.encoded()
        var loaded = try Scenario.decode(original)
        loaded.meta["name"] = .string("Titre modifié")
        scenario.meta["name"] = .string("Titre modifié")
        #expect(try Scenario.decode(loaded.encoded()) == scenario)
    }

    @Test func templateIsAcceptedByStrictValidator() throws {
        let scenario = Scenario.template()
        #expect(try Scenario.decode(scenario.encoded(), strict: true) == scenario)
        #expect(scenario.activityCount == 3)
        #expect(scenario.duration == 30)
    }

    @Test func duplicateIDsAreRepairedOnImportButRejectedFromAI() throws {
        var scenario = Scenario.template()
        scenario.moments[0].activities[0]["id"] = scenario.moments[0].id
        let data = try scenario.encoded()
        #expect(throws: ScenarioError.self) { try Scenario.decode(data, strict: true) }
        let imported = try Scenario.decode(data)
        #expect(imported.moments[0].id != imported.moments[0].activities[0].id)
    }

    @Test(arguments: [0.0, -10.0, 100_001.0])
    func invalidDurationIsRejected(_ duration: Double) throws {
        var scenario = Scenario.template()
        scenario.moments[0].activities[0].duration = duration
        #expect(throws: ScenarioError.self) { try Scenario.decode(scenario.encoded()) }
    }

    @Test func invalidStructureIsRejected() {
        #expect(throws: ScenarioError.self) { try Scenario.decode(Data("{\"sessions\":[]}".utf8)) }
        #expect(throws: ScenarioError.self) { try Scenario.decode(Data("{\"meta\":{},\"sessions\":[{}]}".utf8)) }
    }

    @Test func invalidPedagogyAndAIASAreRejected() throws {
        var scenario = Scenario.template()
        scenario.moments[0].activities[0]["type"] = "invented"
        #expect(throws: ScenarioError.self) { try Scenario.decode(scenario.encoded(), strict: true) }
        scenario.moments[0].activities[0]["type"] = "practice"
        scenario.moments[0].activities[0].fields["aias"] = .object(["status": .string("specified"), "level": .number(6)])
        #expect(throws: ScenarioError.self) { try Scenario.decode(scenario.encoded(), strict: true) }
    }

    @Test func allOfflineCatalogsAreBundled() throws {
        let competencies = try CompetencyCatalog.bundled.get()
        #expect(competencies.frameworks.count == 8)
        #expect(competencies.competencies.count == 1198)
        #expect(Set(competencies.competencies.map(\.id)).count == competencies.competencies.count)
        let schools = try SchoolCatalog.bundled.get()
        #expect(schools.systems.count == 13)
        #expect(schools.levels["france"]?.contains { $0.value == "sixieme" } == true)
    }
}

@Suite("Adresses IA")
struct EndpointTests {
    @Test func supportedEndpoints() throws {
        let local = AIConfiguration(provider: "ollama", endpoint: "http://127.0.0.1:11434/", model: "local-model")
        #expect(try local.requestURL().absoluteString == "http://127.0.0.1:11434/api/chat")
        let remote = AIConfiguration(provider: "compatible", endpoint: "https://example.com/v1", model: "model")
        #expect(try remote.requestURL().absoluteString == "https://example.com/v1/chat/completions")
    }

    @Test(arguments: ["http://example.com/v1", "https://user:password@example.com", "https://example.com?key=secret", "file:///tmp/server", "https://"])
    func unsafeOrInvalidEndpointIsRejected(_ endpoint: String) {
        #expect(throws: ScenarioError.self) {
            try AIConfiguration(provider: "compatible", endpoint: endpoint, model: "model").requestURL()
        }
    }
}

final class MockProtocol: URLProtocol, @unchecked Sendable {
    // Access is serialized by the test suite. URLSession invokes startLoading after setup.
    nonisolated(unsafe) static var handler: ((URLRequest) throws -> (Int, Data))?
    nonisolated(unsafe) static var contentType = "application/json"
    override class func canInit(with request: URLRequest) -> Bool { true }
    override class func canonicalRequest(for request: URLRequest) -> URLRequest { request }
    override func startLoading() {
        do {
            let (status, data) = try Self.handler!(request)
            let response = HTTPURLResponse(url: request.url!, statusCode: status, httpVersion: "HTTP/1.1", headerFields: ["Content-Type": Self.contentType])!
            client?.urlProtocol(self, didReceive: response, cacheStoragePolicy: .notAllowed)
            client?.urlProtocol(self, didLoad: data)
            client?.urlProtocolDidFinishLoading(self)
        } catch { client?.urlProtocol(self, didFailWithError: error) }
    }
    override func stopLoading() {}
}

@Suite("Protocoles IA", .serialized)
struct AITransportTests {
    private func configuration() -> URLSessionConfiguration {
        let configuration = URLSessionConfiguration.ephemeral
        configuration.protocolClasses = [MockProtocol.self]
        return configuration
    }

    @Test(arguments: AIProvider.allCases.filter { !$0.isAccountConnection }.map(\.rawValue))
    func decodesStructuredResponse(_ provider: String) async throws {
        let expected = Scenario.template()
        let text = String(decoding: try expected.encoded(), as: UTF8.self)
        MockProtocol.handler = { request in
            #expect(request.httpMethod == "POST")
            #expect(request.value(forHTTPHeaderField: "Authorization") == "Bearer test-only")
            let body: [String: Any] = provider == "ollama" ? ["message": ["content": text], "done": true] :
                ["choices": [["message": ["content": "```json\n" + text + "\n```"]]]]
            return (200, try JSONSerialization.data(withJSONObject: body))
        }
        defer { MockProtocol.handler = nil }
        let result = try await AIClient.propose(scenario: .blank(), prompt: "Un scénario", configuration:
            AIConfiguration(provider: provider, endpoint: "http://localhost:9999/v1", model: "test"),
            key: "test-only", sessionConfiguration: configuration())
        #expect(result == expected)
    }

    @Test func ollamaReplyIsStreamedWithoutReasoning() async throws {
        MockProtocol.handler = { request in
            let stream = try #require(request.httpBodyStream)
            stream.open(); defer { stream.close() }
            var bytes = [UInt8](repeating: 0, count: 65_536)
            var data = Data()
            while stream.hasBytesAvailable {
                let count = stream.read(&bytes, maxLength: bytes.count)
                if count <= 0 { break }
                data.append(contentsOf: bytes.prefix(count))
            }
            let body = try #require(JSONSerialization.jsonObject(with: data) as? [String: Any])
            #expect(body["stream"] as? Bool == true)
            #expect(body["think"] as? Bool == false)
            #expect(body["format"] as? String == "json")
            #expect((body["options"] as? [String: Any])?["num_predict"] as? Int == 12_000)
            return (200, Data("""
            {"message":{"role":"assistant","content":"","thinking":"ignoré"},"done":false}
            {"message":{"role":"assistant","content":"{\\"kind\\":"},"done":false}
            {"message":{"role":"assistant","content":"\\"message\\"}"},"done":false}
            {"message":{"role":"assistant","content":""},"done":true,"done_reason":"stop"}

            """.utf8))
        }
        defer { MockProtocol.handler = nil }
        let text = try await AIClient.generate(system: "s", user: "u", configuration:
            AIConfiguration(provider: "ollama", endpoint: "http://localhost:9999", model: "test"),
            key: "", sessionConfiguration: configuration())
        #expect(text == #"{"kind":"message"}"#)
    }

    @Test(arguments: [
        #"{"message":{"content":"{\"kind\":"},"done":false}"#,
        #"{"message":{"content":"{\"kind\":"},"done":true,"done_reason":"length"}"#,
        #"{"error":"secret-key-private-document"}"#,
        (0..<50).map { _ in #"{"message":{"content":"          "},"done":false}"# }.joined(separator: "\n")
    ])
    func interruptedOllamaReplyIsNeverUsed(_ stream: String) async throws {
        MockProtocol.handler = { _ in (200, Data((stream + "\n").utf8)) }
        defer { MockProtocol.handler = nil }
        do {
            _ = try await AIClient.generate(system: "s", user: "u", configuration:
                AIConfiguration(provider: "ollama", endpoint: "http://localhost:9999", model: "test"),
                key: "", sessionConfiguration: configuration())
            Issue.record("Une réponse interrompue aurait dû produire une erreur.")
        } catch {
            #expect(error is ScenarioError)
            #expect(!error.localizedDescription.contains("secret-key"))
        }
    }

    @Test func lmStudioReplyIsStreamedAndReasoningIsDropped() async throws {
        MockProtocol.contentType = "text/event-stream"
        MockProtocol.handler = { request in
            let stream = try #require(request.httpBodyStream)
            stream.open(); defer { stream.close() }
            var bytes = [UInt8](repeating: 0, count: 65_536)
            var data = Data()
            while stream.hasBytesAvailable {
                let count = stream.read(&bytes, maxLength: bytes.count)
                if count <= 0 { break }
                data.append(contentsOf: bytes.prefix(count))
            }
            let body = try #require(JSONSerialization.jsonObject(with: data) as? [String: Any])
            #expect(body["stream"] as? Bool == true)
            #expect(body["max_tokens"] as? Int == 12_000)
            #expect(body["think"] == nil && body["format"] == nil)
            return (200, Data("""
            data: {"choices":[{"delta":{"role":"assistant","reasoning_content":"ignoré"},"finish_reason":null}]}

            data: {"choices":[{"delta":{"content":"<think>ignoré aussi</think>\\n{\\"kind\\":"},"finish_reason":null}]}

            data: {"choices":[{"delta":{"content":"\\"message\\"}"},"finish_reason":null}]}

            data: {"choices":[{"delta":{},"finish_reason":"stop"}]}

            data: [DONE]

            """.utf8))
        }
        defer { MockProtocol.handler = nil; MockProtocol.contentType = "application/json" }
        let text = try await AIClient.generate(system: "s", user: "u", configuration:
            AIConfiguration(provider: "lmstudio", endpoint: "http://localhost:9999/v1", model: "test"),
            key: "", sessionConfiguration: configuration())
        #expect(text.trimmingCharacters(in: .whitespacesAndNewlines) == #"{"kind":"message"}"#)
    }

    @Test(arguments: [
        #"data: {"choices":[{"delta":{"content":"{\"kind\":"},"finish_reason":null}]}"#,
        #"data: {"choices":[{"delta":{"content":"{\"kind\":"},"finish_reason":"length"}]}"#,
        #"data: {"error":{"message":"secret-key-private-document"}}"#,
        "data: [DONE]"
    ])
    func interruptedLMStudioReplyIsNeverUsed(_ stream: String) async throws {
        MockProtocol.contentType = "text/event-stream"
        MockProtocol.handler = { _ in (200, Data((stream + "\n\n").utf8)) }
        defer { MockProtocol.handler = nil; MockProtocol.contentType = "application/json" }
        do {
            _ = try await AIClient.generate(system: "s", user: "u", configuration:
                AIConfiguration(provider: "lmstudio", endpoint: "http://localhost:9999/v1", model: "test"),
                key: "", sessionConfiguration: configuration())
            Issue.record("Une réponse interrompue aurait dû produire une erreur.")
        } catch {
            #expect(error is ScenarioError)
            #expect(!error.localizedDescription.contains("secret-key"))
        }
    }

    @Test func serverErrorDoesNotEchoSensitiveResponse() async throws {
        MockProtocol.handler = { _ in (401, Data("secret-key-private-document".utf8)) }
        defer { MockProtocol.handler = nil }
        do {
            _ = try await AIClient.propose(scenario: .blank(), prompt: "test", configuration:
                AIConfiguration(provider: "ollama", endpoint: "http://localhost:9999", model: "test"), key: "", sessionConfiguration: configuration())
            Issue.record("Une erreur HTTP était attendue.")
        } catch {
            #expect(error.localizedDescription.contains("401"))
            #expect(!error.localizedDescription.contains("secret-key"))
        }
    }

    @Test func invalidAIContentIsRejected() async throws {
        MockProtocol.handler = { _ in (200, Data("{\"message\":{\"content\":\"{}\"}}".utf8)) }
        defer { MockProtocol.handler = nil }
        await #expect(throws: ScenarioError.self) {
            try await AIClient.propose(scenario: .blank(), prompt: "test", configuration:
                AIConfiguration(provider: "ollama", endpoint: "http://localhost:9999", model: "test"), key: "", sessionConfiguration: configuration())
        }
    }

    @Test(arguments: AIProvider.allCases.filter { !$0.isAccountConnection })
    func discoversModelsWithoutACompletionOrScenario(_ provider: AIProvider) async throws {
        MockProtocol.handler = { request in
            if provider == .ollama && request.url?.path == "/api/show" {
                #expect(request.httpMethod == "POST")
                return (200, Data("{\"capabilities\":[\"completion\"]}".utf8))
            }
            #expect(request.httpMethod == "GET")
            #expect(request.httpBody == nil)
            #expect(request.httpBodyStream == nil)
            #expect(request.url?.path == (provider == .ollama ? "/api/tags" : "/models"))
            #expect(request.value(forHTTPHeaderField: "Authorization") == "Bearer test-only")
            let entries: [[String: Any]] = provider == .ollama
                ? [["name": "local:8b", "size": 5_000_000_000], ["name": "local:3b"], ["name": "local:8b"], ["name": " "]]
                : [["id": "gpt-test-mini"], ["id": "gpt-test"], ["id": "gpt-test-mini"], ["id": "embedding-test", "capabilities": ["completion_chat": false]]]
            return (200, try JSONSerialization.data(withJSONObject: [provider == .ollama ? "models" : "data": entries]))
        }
        defer { MockProtocol.handler = nil }
        let models = try await AIClient.models(configuration:
            AIConfiguration(provider: provider.rawValue, endpoint: "http://localhost:9999", model: ""),
            key: "test-only", sessionConfiguration: configuration())
        #expect(models.map(\.id) == (provider == .ollama ? ["local:3b", "local:8b"] : ["gpt-test", "gpt-test-mini"]))
        if provider == .ollama { #expect(models.last?.size == 5_000_000_000) }
    }

    @Test func ollamaDiscoveryExcludesEmbeddingOnlyModels() async throws {
        MockProtocol.handler = { request in
            if request.url?.path == "/api/tags" {
                return (200, Data("{\"models\":[{\"name\":\"embedding\"},{\"name\":\"chat\"}]}".utf8))
            }
            #expect(request.url?.path == "/api/show")
            // Inspect metadata requests without invoking /api/chat.
            let stream = try #require(request.httpBodyStream)
            stream.open(); defer { stream.close() }
            var bytes = [UInt8](repeating: 0, count: 1024)
            let count = stream.read(&bytes, maxLength: bytes.count)
            let text = String(decoding: bytes.prefix(max(0, count)), as: UTF8.self)
            let capabilities = text.contains("embedding") ? ["embedding"] : ["completion", "vision"]
            return (200, try JSONSerialization.data(withJSONObject: ["capabilities": capabilities]))
        }
        defer { MockProtocol.handler = nil }
        let models = try await AIClient.models(configuration:
            AIConfiguration(provider: "ollama", endpoint: "http://localhost:11434", model: ""),
            key: "", sessionConfiguration: configuration())
        #expect(models.map(\.id) == ["chat"])
    }

    @Test func openAIListExcludesSpecialisedModels() async throws {
        MockProtocol.handler = { _ in
            let ids = ["gpt-test", "gpt-test-mini", "o3", "gpt-test-audio", "gpt-image-test", "text-embedding-test", "gpt-test-codex", "o3-deep-research"]
            return (200, try JSONSerialization.data(withJSONObject: ["data": ids.map { ["id": $0] }]))
        }
        defer { MockProtocol.handler = nil }
        let models = try await AIClient.models(configuration:
            AIConfiguration(provider: "openai", endpoint: "https://example.com/v1", model: ""),
            key: "", sessionConfiguration: configuration())
        #expect(models.map(\.id) == ["gpt-test", "gpt-test-mini", "o3"])
    }

    @Test(arguments: ["{}", "{\"data\":{}}"])
    func missingModelListHasAnActionableError(_ response: String) async throws {
        MockProtocol.handler = { _ in (200, Data(response.utf8)) }
        defer { MockProtocol.handler = nil }
        do {
            _ = try await AIClient.models(configuration:
                AIConfiguration(provider: "compatible", endpoint: "https://example.com/v1", model: ""),
                key: "", sessionConfiguration: configuration())
            Issue.record("La liste invalide aurait dû être rejetée.")
        } catch { #expect(error.localizedDescription.contains("options avancées")) }
    }

    @Test(arguments: [401, 403, 404, 429, 302, 500])
    func discoveryErrorsDoNotExposeCredentials(_ status: Int) async throws {
        MockProtocol.handler = { _ in (status, Data("secret-key-private-document".utf8)) }
        defer { MockProtocol.handler = nil }
        do {
            _ = try await AIClient.models(configuration:
                AIConfiguration(provider: "compatible", endpoint: "https://example.com/v1", model: ""),
                key: "test-only", sessionConfiguration: configuration())
            Issue.record("Une erreur était attendue.")
        } catch {
            #expect(error.localizedDescription.contains(String(status)))
            #expect(!error.localizedDescription.contains("secret-key"))
        }
    }

    @Test(arguments: ["ollama", "compatible"])
    func manualConnectionProbeDoesNotSendTheScenario(_ provider: String) async throws {
        MockProtocol.handler = { request in
            #expect(request.httpMethod == "POST")
            // URLProtocol exposes the uploaded JSON as a stream.
            let data: Data
            if let body = request.httpBody { data = body }
            else {
                let stream = try #require(request.httpBodyStream)
                stream.open(); defer { stream.close() }
                var bytes = [UInt8](repeating: 0, count: 1024)
                var collected = Data()
                while stream.hasBytesAvailable {
                    let count = stream.read(&bytes, maxLength: bytes.count)
                    if count <= 0 { break }
                    collected.append(contentsOf: bytes.prefix(count))
                }
                data = collected
            }
            let body = try #require(JSONSerialization.jsonObject(with: data) as? [String: Any])
            #expect(body["model"] as? String == "manual-model")
            let messages = try #require(body["messages"] as? [[String: String]])
            #expect(messages == [["role": "user", "content": "Réponds uniquement par OK."]])
            let response: [String: Any] = provider == "ollama" ? ["message": ["content": "OK"]]
                : ["choices": [["message": ["content": "OK"]]]]
            return (200, try JSONSerialization.data(withJSONObject: response))
        }
        defer { MockProtocol.handler = nil }
        try await AIClient.checkConnection(configuration:
            AIConfiguration(provider: provider, endpoint: "http://localhost:9999", model: "manual-model"),
            key: "", sessionConfiguration: configuration())
    }

    @Test func stoppedLocalServerHasAnActionableError() async throws {
        MockProtocol.handler = { _ in throw URLError(.cannotConnectToHost) }
        defer { MockProtocol.handler = nil }
        do {
            _ = try await AIClient.models(configuration:
                AIConfiguration(provider: "ollama", endpoint: "http://localhost:11434", model: ""),
                key: "", sessionConfiguration: configuration())
            Issue.record("Le serveur arrêté aurait dû produire une erreur.")
        } catch { #expect(error.localizedDescription.contains("Ouvrez Ollama")) }
    }
}
