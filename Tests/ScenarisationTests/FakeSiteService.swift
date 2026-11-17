import Foundation
@testable import Scenarisation

/// In-memory stand-in for the site. It never opens a network connection.
actor FakeSiteService: SiteSyncService {
    private struct State {
        var designs: [SiteDesign]
        var creations: [String: Int] = [:]
        var deleted: Set<Int> = []
    }
    private var state = State(designs: ["Découvrir et s’entraîner", "Préparer un débat"].enumerated().map { index, name in
        var document = Scenario.template()
        document.meta["name"] = .string(name)
        return SiteDesign(id: index + 1, title: name, revision: 1, updatedAt: "2026-11-16T12:00:00Z", document: document)
    })
    private var offline = false

    func setOffline(_ value: Bool) { offline = value }
    private func checkConnection() throws {
        try Task.checkCancellation()
        if offline { throw URLError(.notConnectedToInternet) }
    }
    func list() async throws -> [SiteDesignSummary] {
        try checkConnection()
        return state.designs.map(Self.summary)
    }
    func download(_ id: Int) async throws -> SiteDesign {
        try checkConnection()
        guard let design = state.designs.first(where: { $0.id == id }) else { throw SiteSyncError.missing }
        return design
    }
    func save(_ request: SiteSaveRequest) async throws -> SiteDesignSummary {
        try checkConnection()
        var next = state
        let id: Int
        let revision: Int
        if let remoteID = request.design_id {
            guard let existing = state.designs.first(where: { $0.id == remoteID }) else { throw SiteSyncError.missing }
            guard request.expected_revision == existing.revision else { throw SiteSyncError.conflict }
            id = remoteID; revision = existing.revision + 1
        } else {
            if let existingID = state.creations[request.client_uuid.uuidString] {
                guard let existing = state.designs.first(where: { $0.id == existingID }) else { throw SiteSyncError.missing }
                return Self.summary(existing)
            }
            id = (state.designs.map(\.id).max() ?? 0) + 1; revision = 1
            next.creations[request.client_uuid.uuidString] = id
        }
        let saved = SiteDesign(id: id, title: request.title, revision: revision,
                               updatedAt: ISO8601DateFormatter().string(from: Date()), document: request.document)
        next.designs.removeAll { $0.id == id }; next.designs.append(saved)
        state = next
        return Self.summary(saved)
    }
    func delete(_ id: Int, expectedRevision: Int) async throws {
        try checkConnection()
        guard let existing = state.designs.first(where: { $0.id == id }) else {
            if state.deleted.contains(id) { return }
            throw SiteSyncError.missing
        }
        guard existing.revision == expectedRevision else { throw SiteSyncError.conflict }
        state.designs.removeAll { $0.id == id }; state.deleted.insert(id)
    }
    func simulateChange(_ id: Int) throws {
        guard let existing = state.designs.first(where: { $0.id == id }) else { throw SiteSyncError.missing }
        var document = existing.document
        document.meta["description"] = .string("Version modifiée sur le site (révision \(existing.revision + 1)).")
        var next = state
        next.designs.removeAll { $0.id == id }
        next.designs.append(SiteDesign(id: id, title: existing.title, revision: existing.revision + 1,
                                      updatedAt: ISO8601DateFormatter().string(from: Date()), document: document))
        state = next
    }
    private static func summary(_ design: SiteDesign) -> SiteDesignSummary {
        SiteDesignSummary(id: design.id, title: design.title, revision: design.revision, updatedAt: design.updatedAt)
    }
}
