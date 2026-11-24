import Foundation
import Testing
@testable import Scenarisation

@Suite("Configuration simple de l’assistant")
@MainActor
struct AISettingsTests {
    private func withSettings(_ body: @MainActor (AISettingsStore, UserDefaults) async throws -> Void) async throws {
        let suite = "ScenarisationTests.AI.\(UUID().uuidString)"
        let defaults = try #require(UserDefaults(suiteName: suite))
        defer { defaults.removePersistentDomain(forName: suite) }
        try await body(AISettingsStore(defaults: defaults), defaults)
    }

    @Test func teacherProfileIsSavedAndLimited() async throws {
        try await withSettings { settings, defaults in
            #expect(settings.teacherProfile.isEmpty)
            settings.setTeacherProfile("Mathématiques, 4e et 3e, Suisse romande")
            #expect(AISettingsStore(defaults: defaults).teacherProfile == "Mathématiques, 4e et 3e, Suisse romande")
            settings.setTeacherProfile(String(repeating: "a", count: AssistantPrompt.profileLimit + 50))
            #expect(settings.teacherProfile.count == AssistantPrompt.profileLimit)
        }
    }

    @Test func legacyAPIAndOllamaSettingsKeepTheirCredentialAccounts() async throws {
        try await withSettings { _, defaults in
            defaults.set("compatible", forKey: "ai.provider")
            defaults.set("https://example.com/v1/", forKey: "ai.compatible.endpoint")
            defaults.set("existing-model", forKey: "ai.compatible.model")
            defaults.set("local-model", forKey: "ai.ollama.model")
            let settings = AISettingsStore(defaults: defaults)
            #expect(settings.configuration.model == "existing-model")
            #expect(settings.configuration.credentialAccount == "compatible:https://example.com/v1")
            settings.selectProvider(.ollama)
            #expect(settings.configuration.model == "local-model")
            #expect(settings.configuration.credentialAccount == "ollama:http://127.0.0.1:11434")
            settings.selectProvider(.compatible)
            #expect(settings.configuration.model == "existing-model")
        }
    }

    @Test func servicesRememberIndependentPreferences() async throws {
        try await withSettings { settings, defaults in
            settings.selectModel("local:8b")
            settings.selectProvider(.mistral)
            #expect(settings.configuration.endpoint == AIProvider.mistral.defaultEndpoint)
            #expect(settings.configuration.model.isEmpty)
            settings.selectModel("cloud-model")
            settings.selectProvider(.lmstudio)
            #expect(settings.configuration.endpoint == AIProvider.lmstudio.defaultEndpoint)
            settings.selectProvider(.ollama)
            #expect(settings.configuration.model == "local:8b")
            settings.selectProvider(.mistral)
            #expect(AISettingsStore(defaults: defaults).configuration.model == "cloud-model")
        }
    }

    @Test func creationRestoresASavedAccountEvenWhenTheSelectedServiceIsNotConfigured() async throws {
        try await withSettings { settings, _ in
            settings.selectProvider(.chatgpt); settings.selectModel("account-model")
            settings.selectProvider(.ollama)
            #expect(!settings.configuration.isReady)
            #expect(settings.restoreForCreation(chatGPTAvailable: true, readKey: { _ in
                Issue.record("Une connexion par compte ne doit pas lire de clé API."); return ""
            }))
            #expect(settings.configuration.service == .chatgpt && settings.configuration.model == "account-model")
        }
    }

    @Test func creationKeepsTheSelectedModelAndRequiresCredentialsForCloudAPIs() async throws {
        try await withSettings { settings, _ in
            settings.selectModel("local-model")
            settings.selectProvider(.mistral); settings.selectModel("cloud-model")
            #expect(settings.restoreForCreation(chatGPTAvailable: false, readKey: { _ in "test-key" }))
            #expect(settings.configuration.service == .mistral)
            #expect(settings.restoreForCreation(chatGPTAvailable: false, readKey: { _ in "" }))
            #expect(settings.configuration.service == .ollama)
        }
    }

    @Test func creationInvitesConnectionWhenNoUsableModelRemains() async throws {
        try await withSettings { settings, _ in
            #expect(!settings.restoreForCreation(chatGPTAvailable: false, readKey: { _ in "" }))
            settings.selectProvider(.chatgpt); settings.selectModel("revoked-account-model")
            #expect(!settings.restoreForCreation(chatGPTAvailable: false, readKey: { _ in "" }))
            settings.selectProvider(.claude); settings.selectModel("sonnet")
            #expect(!settings.restoreForCreation(chatGPTAvailable: false, readKey: { _ in "" }))
        }
    }

    @Test func cachedModelChoicesSurviveRelaunchAndStaySeparateByServiceAndAddress() async throws {
        try await withSettings { settings, defaults in
            let local = [AIModel(id: "local-small"), AIModel(id: "local-large")]
            settings.acceptModels(local, for: settings.configuration); settings.selectModel("local-large")
            settings.selectProvider(.mistral)
            settings.acceptModels([AIModel(id: "cloud", name: "Cloud")], for: settings.configuration)
            let restored = AISettingsStore(defaults: defaults)
            #expect(restored.models.map(\.id) == ["cloud"])
            restored.selectProvider(.ollama)
            #expect(restored.models == local && restored.configuration.model == "local-large")
            restored.setEndpoint("http://localhost:11435")
            restored.selectProvider(.mistral); restored.selectProvider(.ollama)
            #expect(restored.models.isEmpty)
            restored.resetModels()
            #expect(AISettingsStore(defaults: defaults).models.isEmpty)
        }
    }

    @Test func discoveryRequiresNoModelAndPrivacyFollowsTheActualAddress() throws {
        for provider in AIProvider.allCases where provider != .compatible && !provider.isAccountConnection {
            let configuration = AIConfiguration(provider: provider.rawValue, endpoint: provider.defaultEndpoint, model: "")
            #expect(try configuration.modelsURL().path == (provider == .ollama ? "/api/tags" : "/v1/models"))
            #expect(!configuration.isReady)
            #expect(configuration.isOnThisMac == provider.isLocal)
        }
        #expect(!AIConfiguration(provider: "ollama", endpoint: "https://example.com", model: "test").isOnThisMac)
        #expect(AIConfiguration(provider: "compatible", endpoint: "http://localhost:8080/v1", model: "test").isOnThisMac)
    }

    @Test func oneModelIsSelectedButMultipleModelsRequireAChoice() async throws {
        try await withSettings { settings, _ in
            let source = settings.configuration
            settings.acceptModels([AIModel(id: "small"), AIModel(id: "large")], for: source)
            #expect(settings.configuration.model.isEmpty)
            settings.acceptModels([AIModel(id: "only")], for: source)
            #expect(settings.configuration.model == "only")
            settings.acceptModels([AIModel(id: "only"), AIModel(id: "another")], for: source)
            #expect(settings.configuration.model == "only")
            settings.selectModel("embedding-only")
            settings.acceptModels([], for: source)
            #expect(!settings.configuration.isReady)
            settings.setEndpoint("http://localhost:11435")
            #expect(!settings.configuration.isReady)
            #expect(settings.models.isEmpty)
            settings.acceptModels([AIModel(id: "stale")], for: source)
            #expect(settings.models.isEmpty)
        }
    }

    @Test func connectionSavesTheKeyAndSelectsTheDetectedModelInOneStep() async throws {
        try await withSettings { settings, defaults in
            settings.selectProvider(.mistral)
            var saved: [String: String] = [:]
            let setup = AISetupModel(readKey: { _ in "" }, saveKey: { saved[$1] = $0 }, discover: { configuration, key in
                #expect(configuration.service == .mistral)
                #expect(key == "test-only")
                return [AIModel(id: "detected-model")]
            })
            setup.key = " test-only\n"
            await setup.connect(settings)?.value
            #expect(!setup.busy && !setup.failure)
            #expect(settings.configuration.model == "detected-model")
            #expect(saved[settings.configuration.credentialAccount] == "test-only")
            #expect(!String(describing: defaults.dictionaryRepresentation()).contains("test-only"))
        }
    }

    @Test func failedConnectionDoesNotReplaceTheSavedKey() async throws {
        try await withSettings { settings, _ in
            settings.selectProvider(.mistral)
            var saved = false
            let setup = AISetupModel(readKey: { _ in "previous-key" }, saveKey: { _, _ in saved = true }, discover: { _, _ in
                throw ScenarioError.invalid("Connexion refusée.")
            })
            setup.key = "wrong-key"
            await setup.connect(settings)?.value
            #expect(setup.failure && !setup.busy)
            #expect(!saved)
            #expect(settings.models.isEmpty)
        }
    }

    @Test func changingServiceDuringDiscoveryCannotSaveAKeyOrApplyStaleModels() async throws {
        try await withSettings { settings, _ in
            settings.selectProvider(.mistral)
            var saved = false
            var response: CheckedContinuation<[AIModel], Never>?
            let (started, signal) = AsyncStream<Void>.makeStream()
            let setup = AISetupModel(readKey: { _ in "" }, saveKey: { _, _ in saved = true }, discover: { _, _ in
                await withCheckedContinuation { continuation in
                    response = continuation; signal.yield(())
                }
            })
            setup.key = "test-only"
            let request = setup.connect(settings)
            for await _ in started { break }
            settings.selectProvider(.openai)
            setup.prepare(settings)
            response?.resume(returning: [AIModel(id: "stale-model")])
            await request?.value
            #expect(!saved && !setup.busy)
            #expect(settings.models.isEmpty)
            #expect(settings.configuration.model.isEmpty)
            #expect(setup.key.isEmpty)
        }
    }
}
