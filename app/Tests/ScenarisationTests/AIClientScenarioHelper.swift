import Foundation
@testable import Scenarisation

// Transport fixtures exercise the same generate/decode primitives as the assistant.
extension AIClient {
    static func propose(scenario: Scenario, prompt: String, configuration: AIConfiguration, key: String,
                        sessionConfiguration: URLSessionConfiguration = .ephemeral) async throws -> Scenario {
        let text = try await generate(system: AssistantPrompt.pedagogy + "\n" + AssistantPrompt.scenarioFormat,
            user: prompt + "\n" + String(decoding: try scenario.encoded(), as: UTF8.self),
            configuration: configuration, key: key, sessionConfiguration: sessionConfiguration)
        return try Self.scenario(from: text)
    }
}
