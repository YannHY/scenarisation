import Foundation
import Testing
@testable import Scenarisation

@Suite("Abonnements Antigravity et Grok")
@MainActor
struct SubscriptionCLITests {
    @Test func accountProvidersNeverUseHTTPOrAPIKeys() async throws {
        for provider in [AIProvider.antigravity, .grok] {
            let configuration = AIConfiguration(provider: provider.rawValue, endpoint: provider.defaultEndpoint, model: "model")
            #expect(provider.isAccountConnection)
            #expect(!provider.requiresKey)
            #expect(configuration.isReady)
            #expect(throws: (any Error).self) { try configuration.requestURL() }
            #expect(throws: (any Error).self) { try configuration.modelsURL() }
            #expect(try await AICredentials.read(configuration) == "")
        }
        let environment = SubscriptionCLIClient.environment(["HOME": "/Users/test", "PATH": "/usr/bin",
            "GEMINI_API_KEY": "secret", "XAI_API_KEY": "secret", "GOOGLE_APPLICATION_CREDENTIALS": "key.json",
            "GROK_MODELS_BASE_URL": "https://other.test", "NODE_OPTIONS": "--require injected.js"])
        #expect(environment["HOME"] == "/Users/test")
        for key in ["GEMINI_API_KEY", "XAI_API_KEY", "GOOGLE_APPLICATION_CREDENTIALS", "GROK_MODELS_BASE_URL", "NODE_OPTIONS"] {
            #expect(environment[key] == nil)
        }
    }

    @Test func modelsSupportGroupedOptionsAndLegacyCatalogs() throws {
        let selection = try ACPModelSelection(session: ["configOptions": [["id": "model-id", "category": "model",
            "options": [["group": "Google", "options": [["value": "gemini", "name": "Gemini"]]], ["value": "gemini"]]]]])
        #expect(selection.configID == "model-id")
        #expect(selection.models == [AIModel(id: "gemini", name: "Gemini")])
        let legacy = try ACPModelSelection(session: ["models": ["availableModels": [["modelId": "grok", "name": "Grok"]]]])
        #expect(legacy.configID == nil)
        #expect(legacy.models.first?.id == "grok")
        #expect(throws: (any Error).self) { try ACPModelSelection(session: [:]) }
    }

    @Test func subscriptionsPersistIndependentlyAndDisconnectClearsModels() throws {
        let suite = "ScenarisationTests.Subscription.\(UUID().uuidString)"
        let defaults = try #require(UserDefaults(suiteName: suite))
        defer { defaults.removePersistentDomain(forName: suite) }
        let settings = AISettingsStore(defaults: defaults)
        settings.selectProvider(.antigravity)
        settings.enableSubscription(.antigravity, enabled: true)
        settings.selectModel("gemini")
        settings.selectProvider(.grok)
        settings.enableSubscription(.grok, enabled: true)
        settings.acceptModels([AIModel(id: "grok")], for: settings.configuration)
        #expect(AISettingsStore(defaults: defaults).subscriptionServices == [.antigravity, .grok])
        settings.enableSubscription(.grok, enabled: false)
        #expect(settings.models.isEmpty)
        #expect(settings.configuration.model.isEmpty)
        #expect(settings.restoreForCreation(chatGPTAvailable: false))
        #expect(settings.configuration.service == .antigravity)
    }

    // A real subprocess tests framing, authentication choice, callbacks and termination.
    // It needs no installed provider CLI, network connection or personal account.
    private func connection(_ behavior: String = "normal", provider: AIProvider = .antigravity) throws -> ACPConnection {
        let script = #"""
import sys, json, time
behavior = sys.argv[1]
provider = sys.argv[2]
prompt_id = None
def emit(obj):
    raw = json.dumps(obj, ensure_ascii=False).encode() + b'\n'
    # Deliberately fragment messages, including UTF-8 bytes.
    for n in range(0, len(raw), 7):
        sys.stdout.buffer.write(raw[n:n+7])
        sys.stdout.buffer.flush()
def result(req, value):
    emit({'jsonrpc':'2.0', 'id':req['id'], 'result':value})
for line in sys.stdin:
    req = json.loads(line)
    method = req.get('method')
    params = req.get('params', {})
    if method == 'initialize':
        auth = 'oauth-personal' if provider == 'antigravity' else 'cached_token'
        if behavior == 'key-only': auth = 'gemini-api-key'
        result(req, {'protocolVersion':1, 'authMethods':[{'id':auth}]})
    elif method == 'authenticate':
        expected = 'oauth-personal' if provider == 'antigravity' else 'cached_token'
        assert params['methodId'] == expected
        if behavior == 'auth-error':
            emit({'jsonrpc':'2.0','id':req['id'],'error':{'code':-32000,'message':'secret must not be exposed'}})
        else: result(req, {})
    elif method == 'session/new':
        assert params['mcpServers'] == []
        result(req, {'sessionId':'test', 'models':{'availableModels':[{'modelId':'example','name':'Example'}]}})
    elif method == 'session/prompt':
        if behavior == 'exit': sys.exit(1)
        if behavior == 'hang':
            time.sleep(20)
            continue
        prompt_id = req['id']
        emit({'jsonrpc':'2.0','method':'session/update','params':{'sessionId':'test','update':{'sessionUpdate':'agent_thought_chunk','content':{'type':'text','text':'private reasoning'}}}})
        emit({'jsonrpc':'2.0','method':'session/update','params':{'sessionId':'wrong','update':{'sessionUpdate':'agent_message_chunk','content':{'type':'text','text':'wrong session'}}}})
        emit({'jsonrpc':'2.0','id':'permission','method':'session/request_permission','params':{'sessionId':'test','options':[{'optionId':'yes','kind':'allow_always'}]}})
    elif req.get('id') == 'permission':
        assert req['result']['outcome']['outcome'] == 'cancelled'
        emit({'jsonrpc':'2.0','id':'file','method':'fs/read_text_file','params':{'path':'/secret'}})
    elif req.get('id') == 'file':
        assert req['error']['code'] == -32601
        emit({'jsonrpc':'2.0','method':'session/update','params':{'sessionId':'test','update':{'sessionUpdate':'agent_message_chunk','content':{'type':'text','text':'Réponse complète.'}}}})
        emit({'jsonrpc':'2.0','id':prompt_id,'result':{'stopReason':'end_turn'}})
    else: result(req, {})
"""#
        return try ACPConnection(provider: provider, executable: URL(fileURLWithPath: "/usr/bin/python3"),
            arguments: ["-u", "-c", script, behavior, provider.rawValue])
    }

    @Test func exchangeUsesAccountAuthAndRejectsTools() async throws {
        for provider in [AIProvider.antigravity, .grok] {
            let client = try connection(provider: provider)
            defer { client.close() }
            let session = try await client.openSession()
            #expect(try ACPModelSelection(session: session).models.first?.id == "example")
            let result = try await client.request("session/prompt", ["sessionId": client.sessionID, "prompt": []])
            #expect(result["stopReason"] as? String == "end_turn")
            #expect(client.answer == "Réponse complète.")
        }
    }

    @Test func refusesAPIOnlyAndExpiredAccounts() async throws {
        for behavior in ["key-only", "auth-error"] {
            let client = try connection(behavior)
            defer { client.close() }
            do {
                _ = try await client.openSession()
                Issue.record("An account-only connection must fail here")
            } catch { #expect(!error.localizedDescription.contains("secret must not")) }
        }
    }

    @Test func processExitAndTimeoutFailPendingRequest() async throws {
        for behavior in ["exit", "hang"] {
            let client = try connection(behavior)
            defer { client.close() }
            _ = try await client.openSession()
            do {
                _ = try await client.request("session/prompt", [:], timeout: 0.15)
                Issue.record("Expected process failure or timeout")
            } catch { #expect(!error.localizedDescription.isEmpty) }
        }
    }

    @Test func cancellationStopsOutstandingRequest() async throws {
        let client = try connection("hang")
        defer { client.close() }
        _ = try await client.openSession()
        let task = Task { _ = try await client.request("session/prompt", [:]); return true }
        try await Task.sleep(for: .milliseconds(50))
        task.cancel()
        do { _ = try await task.value; Issue.record("Expected cancellation") }
        catch { #expect(error is CancellationError) }
    }
}
