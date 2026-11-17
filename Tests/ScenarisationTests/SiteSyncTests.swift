import Foundation
import Testing
@testable import Scenarisation

@Suite("Synchronisation des scénarios", .serialized)
@MainActor
struct SiteSyncTests {
    private func workspace(service: (any SiteSyncService)? = nil, directory: URL? = nil,
                           account: SiteSyncAccount = SiteSyncAccount(key: "https://example.org/account/1", label: "Compte")) throws -> SiteSyncWorkspace {
        try SiteSyncWorkspace(account: account, service: service ?? FakeSiteService(),
                              disk: directory.map { SiteSyncDisk(directory: $0) })
    }

    private func temporaryDirectory() -> URL { FileManager.default.temporaryDirectory.appendingPathComponent(UUID().uuidString) }

    @Test func downloadsKeepUnknownFieldsAndLocalIdentityDoesNotLeakIntoExports() async throws {
        let ws = try workspace()
        let downloaded = try await ws.downloadToMac(1)
        #expect(downloaded.status == .synced && downloaded.revision == 1)
        let identity = SyncDocumentIdentity(id: downloaded.id, accountKey: ws.account.key)
        let file = try ScenarioDocument.data(downloaded.local, identity: identity)
        var roundTrip = try Scenario.decode(file)
        #expect(ScenarioDocument.extractIdentity(from: &roundTrip) == identity)
        #expect(roundTrip == downloaded.local)
        #expect(!String(decoding: try roundTrip.encoded(), as: UTF8.self).contains("_scenarisationMac"))
        let imported = ScenarioDocument(scenario: try Scenario.decode(file), enteredEditor: true)
        #expect(imported.syncIdentity == nil)
        #expect(imported.scenario.fields["_scenarisationMac"] == nil)
        #expect(try await ws.downloadToMac(1).id == downloaded.id)
    }

    @Test func offlineChangesSurviveRelaunchAndSynchronizeOnExplicitRetry() async throws {
        let directory = temporaryDirectory()
        defer { try? FileManager.default.removeItem(at: directory) }
        let service = FakeSiteService()
        let ws = try workspace(service: service, directory: directory)
        let remote = try await ws.downloadToMac(1)
        let identity = SyncDocumentIdentity(id: remote.id, accountKey: ws.account.key)
        var edited = remote.local
        edited.meta["name"] = .string("Travail hors ligne")
        edited.fields["futureWebField"] = .object(["keep": .bool(true)])
        try ws.stage(identity, scenario: edited, previous: remote.local)
        await service.setOffline(true)
        await #expect(throws: URLError.self) { try await ws.synchronize(remote.id) }
        #expect(ws.record(remote.id)?.status == .pending)
        let relaunched = try workspace(service: service, directory: directory)
        #expect(relaunched.record(remote.id)?.local == edited)
        await service.setOffline(false)
        try await relaunched.synchronize(remote.id)
        #expect(relaunched.record(remote.id)?.status == .synced)
        #expect(try await service.download(1).document == edited)
        #expect(try workspace(service: service, directory: directory).record(remote.id)?.revision == 2)
        #expect((try FileManager.default.attributesOfItem(atPath: directory
            .appendingPathComponent(SiteSyncAPI.accountKey(ws.account.key)).appendingPathComponent("state.json").path)[.posixPermissions] as? NSNumber)?.intValue == 0o600)
    }

    @Test func remoteOnlyChangesUpdateTheLocalCopyWithoutWritingBack() async throws {
        let service = FakeSiteService()
        let ws = try workspace(service: service)
        let record = try await ws.downloadToMac(1)
        try await service.simulateChange(1)
        let expected = try await service.download(1)
        try await ws.synchronize(record.id)
        #expect(ws.record(record.id)?.local == expected.document)
        #expect(ws.record(record.id)?.revision == 2)
        #expect(try await service.download(1).revision == 2)
    }

    @Test func concurrentChangesPreserveBothVersionsWithoutOverwritingTheSite() async throws {
        let service = FakeSiteService()
        let ws = try workspace(service: service)
        let record = try await ws.downloadToMac(1)
        let identity = SyncDocumentIdentity(id: record.id, accountKey: ws.account.key)
        var local = record.local; local.meta["name"] = .string("Version du Mac")
        try ws.stage(identity, scenario: local, previous: record.local)
        try await service.simulateChange(1)
        let remote = try await service.download(1)
        try await ws.synchronize(record.id)
        #expect(ws.record(record.id)?.status == .conflict)
        #expect(ws.record(record.id)?.local == local)
        #expect(ws.record(record.id)?.remoteConflict?.document == remote.document)
        #expect(try await service.download(1) == remote)
        let copy = try ws.keepBoth(record.id)
        #expect(copy.id != record.id && copy.remoteID == nil && copy.status == .pending)
        #expect(copy.local.name == "Version du Mac — copie Mac")
        #expect(ws.record(record.id)?.local == remote.document)
        #expect(try await service.list().count == 2)
        try await ws.synchronize(copy.id)
        #expect(try await service.list().count == 3)
        #expect(try await service.download(1) == remote)
    }

    @Test func editsDuringAnUploadRemainPendingAfterAcknowledgement() async throws {
        let service = try PausedSiteService()
        let ws = try workspace(service: service)
        let identity = try ws.addLocal(.template())
        let sent = try #require(ws.record(identity.id)?.local)
        let task = Task { try await ws.synchronize(identity.id) }
        for await _ in service.entered { break }
        var newer = sent; newer.meta["name"] = .string("Modification pendant l’envoi")
        try ws.stage(identity, scenario: newer, previous: sent)
        await service.resume()
        try await task.value
        #expect(ws.record(identity.id)?.base == sent)
        #expect(ws.record(identity.id)?.local == newer)
        #expect(ws.record(identity.id)?.status == .pending)
        try await ws.synchronize(identity.id)
        #expect(ws.record(identity.id)?.status == .synced)
        let id = try #require(ws.record(identity.id)?.remoteID)
        #expect(try await service.download(id).document == newer)
    }

    @Test func aLostCreationAcknowledgementDoesNotDuplicateOrAcknowledgeUnsentEdits() async throws {
        let directory = temporaryDirectory()
        defer { try? FileManager.default.removeItem(at: directory) }
        let service = try LostAcknowledgementService()
        let ws = try workspace(service: service, directory: directory)
        let identity = try ws.addLocal(.template())
        await #expect(throws: URLError.self) { try await ws.synchronize(identity.id) }
        #expect(ws.record(identity.id)?.remoteID == nil)
        let restored = try workspace(service: service, directory: directory)
        var next = try #require(restored.record(identity.id)?.local)
        next.meta["name"] = .string("Modification après coupure")
        try restored.stage(identity, scenario: next)
        try await restored.synchronize(identity.id)
        #expect(try await service.list().count == 3)
        #expect(restored.record(identity.id)?.local == next)
        #expect(restored.record(identity.id)?.status == .conflict)
        #expect(restored.record(identity.id)?.base != next)
    }

    @Test func aConflictBetweenReadAndWriteIsFetchedAndPreserved() async throws {
        let service = try RacingSiteService()
        let ws = try workspace(service: service)
        let record = try await ws.downloadToMac(1)
        let identity = SyncDocumentIdentity(id: record.id, accountKey: ws.account.key)
        var local = record.local; local.meta["name"] = .string("Modification concurrente")
        try ws.stage(identity, scenario: local)
        try await ws.synchronize(record.id)
        #expect(ws.record(record.id)?.status == .conflict)
        #expect(ws.record(record.id)?.local == local)
        #expect(try await service.download(1).revision == 2)
    }

    @Test func missingRemoteDocumentsWithUnsentEditsAreNotRecreatedAndCanBeCopied() async throws {
        let service = try MissingSiteService()
        let ws = try workspace(service: service)
        let downloaded = try await ws.downloadToMac(1)
        var edited = downloaded.local; edited.meta["name"] = .string("Pas encore envoyé")
        try ws.stage(SyncDocumentIdentity(id: downloaded.id, accountKey: ws.account.key), scenario: edited)
        let record = try #require(ws.record(downloaded.id))
        await service.remove()
        await #expect(throws: SiteSyncError.self) { try await ws.synchronize(record.id) }
        #expect(ws.record(record.id)?.status == .missing)
        await #expect(throws: SiteSyncError.self) { try await ws.synchronize(record.id) }
        #expect(await service.saves == 0)
        let copy = try ws.copyDeleted(record.id)
        #expect(copy.local == record.local && copy.remoteID == nil)
        #expect(ws.record(record.id)?.local == record.local)
    }

    @Test func scenariosDeletedOnTheSiteDisappearFromTheMacAtTheNextRefresh() async throws {
        let directory = temporaryDirectory()
        defer { try? FileManager.default.removeItem(at: directory) }
        let service = FakeSiteService()
        let ws = try workspace(service: service, directory: directory)
        let deleted = try await ws.downloadToMac(1)
        let kept = try await ws.downloadToMac(2)
        let file = try ws.workingFile(deleted.id)
        var notified: [(SyncDocumentIdentity, URL?)] = []
        ws.onRemovedFromSite = { notified.append(($0, $1)) }
        try await service.delete(1, expectedRevision: 1)
        try await ws.refresh()
        #expect(ws.records.map(\.id) == [kept.id])
        #expect(!FileManager.default.fileExists(atPath: file.path))
        #expect(notified.map(\.0) == [SyncDocumentIdentity(id: deleted.id, accountKey: ws.account.key)])
        #expect(notified.first?.1 == file)
        #expect(try workspace(service: service, directory: directory).records.map(\.id) == [kept.id])
    }

    @Test func aScenarioDeletedOnTheSiteKeepsUnsentEditsOnTheMac() async throws {
        let service = FakeSiteService()
        let ws = try workspace(service: service)
        let record = try await ws.downloadToMac(1)
        var edited = record.local; edited.meta["name"] = .string("Modifié hors ligne")
        try ws.stage(SyncDocumentIdentity(id: record.id, accountKey: ws.account.key), scenario: edited)
        try await service.delete(1, expectedRevision: 1)
        try await ws.refresh()
        #expect(ws.record(record.id)?.status == .missing)
        #expect(ws.record(record.id)?.local == edited)
    }

    @Test func anIncompleteListOrAnUnconfirmedDeletionNeverRemovesAScenario() async throws {
        let service = try IncompleteListService()
        let ws = try workspace(service: service)
        let record = try await ws.downloadToMac(1)
        try await ws.refresh()
        #expect(ws.record(record.id)?.status == .synced)
        await service.setDownloadsFail(true)
        try await ws.refresh()
        #expect(ws.record(record.id)?.status == .synced)
    }

    @Test func synchronizingAScenarioDeletedOnTheSiteRemovesItWithoutError() async throws {
        let service = FakeSiteService()
        let ws = try workspace(service: service)
        let record = try await ws.downloadToMac(1)
        var removed: [UUID] = []
        ws.onRemovedFromSite = { identity, _ in removed.append(identity.id) }
        try await service.delete(1, expectedRevision: 1)
        try await ws.synchronize(record.id)
        #expect(ws.record(record.id) == nil)
        #expect(removed == [record.id])
        #expect(try await service.list().map(\.id) == [2])
    }

    @Test func deletingAScenarioRemovesItFromTheSiteAndTheMac() async throws {
        let directory = temporaryDirectory()
        defer { try? FileManager.default.removeItem(at: directory) }
        let service = FakeSiteService()
        let ws = try workspace(service: service, directory: directory)
        let record = try await ws.downloadToMac(1)
        let kept = try await ws.downloadToMac(2)
        let file = try ws.workingFile(record.id)
        try await ws.delete(record.id)
        #expect(ws.records.map(\.id) == [kept.id])
        #expect(!FileManager.default.fileExists(atPath: file.path))
        #expect(try workspace(service: service, directory: directory).records.map(\.id) == [kept.id])
        #expect(try await service.list().map(\.id) == [2])
        await #expect(throws: SiteSyncError.self) { try await ws.delete(record.id) }
        let local = try ws.addLocal(.template())
        try await ws.delete(local.id)
        #expect(ws.record(local.id) == nil)
    }

    @Test func aScenarioChangedOnTheSiteIsNotDeleted() async throws {
        let service = FakeSiteService()
        let ws = try workspace(service: service)
        let record = try await ws.downloadToMac(1)
        try await service.simulateChange(1)
        await #expect(throws: SiteSyncError.changedBeforeDeletion) { try await ws.delete(record.id) }
        #expect(ws.record(record.id) != nil)
        #expect(try await service.list().map(\.id).contains(1))
        try await ws.synchronize(record.id)
        try await ws.delete(record.id)
        #expect(try await service.list().map(\.id) == [2])
    }

    @Test func aMissingDeletionRouteNeverForgetsTheLocalCopy() async throws {
        let ws = try workspace(service: NoDeletionRouteService())
        let record = try await ws.downloadToMac(1)
        await #expect(throws: SiteSyncError.deletionUnavailable) { try await ws.delete(record.id) }
        #expect(ws.record(record.id) != nil)
    }

    @Test func remoteOnlyScenariosCanBeDeletedAndOfflineDeletionsKeepTheCopy() async throws {
        let service = FakeSiteService()
        let ws = try workspace(service: service)
        try await ws.refresh()
        let summary = try #require(ws.remoteDesigns.first { $0.id == 2 })
        try await ws.deleteRemote(summary)
        #expect(!ws.remoteDesigns.contains { $0.id == 2 })
        #expect(try await service.list().map(\.id) == [1])
        let record = try await ws.downloadToMac(1)
        await service.setOffline(true)
        await #expect(throws: (any Error).self) { try await ws.delete(record.id) }
        #expect(ws.record(record.id) != nil && !ws.syncing.contains(record.id))
    }

    @Test func automaticSynchronizationSendsEditsAndCatchesUpAfterABusyUpload() async throws {
        let service = FakeSiteService()
        let ws = try workspace(service: service)
        let record = try await ws.downloadToMac(1)
        let identity = SyncDocumentIdentity(id: record.id, accountKey: ws.account.key)
        var edited = record.local; edited.meta["name"] = .string("Modifié sur le Mac")
        try ws.stage(identity, scenario: edited)
        await ws.synchronizeAutomatically(record.id)
        #expect(ws.record(record.id)?.status == .synced)
        #expect(try await service.download(1).document.name == "Modifié sur le Mac")
        try await service.simulateChange(1)
        await ws.synchronizeAutomatically(record.id)
        #expect(ws.record(record.id)?.local == (try await service.download(1).document))
    }

    @Test func accountsAreIsolatedAndStaleWindowsCannotOverwriteACheckpoint() throws {
        let directory = temporaryDirectory()
        defer { try? FileManager.default.removeItem(at: directory) }
        let first = try workspace(directory: directory)
        let identity = try first.addLocal(.template())
        let initial = try #require(first.record(identity.id)?.local)
        var changed = initial; changed.meta["name"] = .string("Autre fenêtre")
        try first.stage(identity, scenario: changed, previous: initial)
        var stale = initial; stale.meta["name"] = .string("Ancienne fenêtre")
        #expect(throws: SiteSyncError.self) { try first.stage(identity, scenario: stale, previous: initial) }
        #expect(first.record(identity.id)?.local == changed)
        let other = try workspace(directory: directory,
            account: SiteSyncAccount(key: "https://example.org/account/2", label: "Autre compte"))
        #expect(other.records.isEmpty)
        #expect(throws: SiteSyncError.self) { try other.stage(identity, scenario: changed) }
    }

    @Test func unreadableArchivesAreReportedRatherThanReplaced() throws {
        let directory = temporaryDirectory()
        defer { try? FileManager.default.removeItem(at: directory) }
        let ws = try workspace(directory: directory)
        _ = try ws.addLocal(.template())
        let path = directory.appendingPathComponent(SiteSyncAPI.accountKey(ws.account.key)).appendingPathComponent("state.json")
        try Data("corrupted checkpoint".utf8).write(to: path)
        #expect(throws: (any Error).self) { _ = try workspace(directory: directory) }
        #expect(try String(contentsOf: path, encoding: .utf8) == "corrupted checkpoint")
    }

    @Test func renamedAccountsKeepTheirLocalCopies() throws {
        let directory = temporaryDirectory()
        defer { try? FileManager.default.removeItem(at: directory) }
        let original = SiteSyncAccount(key: "https://example.org/account/7", label: "Ancien nom")
        let first = try workspace(directory: directory, account: original)
        let identity = try first.addLocal(.template())
        let renamed = SiteSyncAccount(key: original.key, label: "Nouveau nom")
        let restored = try workspace(directory: directory, account: renamed)
        #expect(restored.account.label == "Nouveau nom")
        #expect(restored.record(identity.id)?.local == first.record(identity.id)?.local)
    }

    @Test func failedCheckpointsPreventAttachingADocument() throws {
        let path = temporaryDirectory()
        try Data("file instead of directory".utf8).write(to: path)
        defer { try? FileManager.default.removeItem(at: path) }
        let ws = try workspace(directory: path)
        #expect(throws: (any Error).self) { _ = try ws.addLocal(.template()) }
        #expect(ws.records.isEmpty)
    }
}

private actor PausedSiteService: SiteSyncService {
    let server: FakeSiteService
    nonisolated let entered: AsyncStream<Void>
    private let signal: AsyncStream<Void>.Continuation
    private var continuation: CheckedContinuation<Void, Never>?
    private var pause = true
    init() throws {
        server = FakeSiteService()
        let stream = AsyncStream<Void>.makeStream(); entered = stream.stream; signal = stream.continuation
    }
    func list() async throws -> [SiteDesignSummary] { try await server.list() }
    func download(_ id: Int) async throws -> SiteDesign { try await server.download(id) }
    func save(_ request: SiteSaveRequest) async throws -> SiteDesignSummary {
        if pause {
            pause = false
            await withCheckedContinuation { continuation = $0; signal.yield(()) }
        }
        return try await server.save(request)
    }
    func resume() { continuation?.resume(); continuation = nil }
}

private actor LostAcknowledgementService: SiteSyncService {
    let server: FakeSiteService
    private var first = true
    init() throws { server = FakeSiteService() }
    func list() async throws -> [SiteDesignSummary] { try await server.list() }
    func download(_ id: Int) async throws -> SiteDesign { try await server.download(id) }
    func save(_ request: SiteSaveRequest) async throws -> SiteDesignSummary {
        let result = try await server.save(request)
        if first { first = false; throw URLError(.networkConnectionLost) }
        return result
    }
}

private actor RacingSiteService: SiteSyncService {
    let server: FakeSiteService
    init() throws { server = FakeSiteService() }
    func list() async throws -> [SiteDesignSummary] { try await server.list() }
    func download(_ id: Int) async throws -> SiteDesign { try await server.download(id) }
    func save(_ request: SiteSaveRequest) async throws -> SiteDesignSummary {
        if let id = request.design_id { try await server.simulateChange(id) }
        return try await server.save(request)
    }
}

/// Simule un site où la route de suppression n’est pas encore déployée : elle répond 404.
private actor NoDeletionRouteService: SiteSyncService {
    let server = FakeSiteService()
    func list() async throws -> [SiteDesignSummary] { try await server.list() }
    func download(_ id: Int) async throws -> SiteDesign { try await server.download(id) }
    func save(_ request: SiteSaveRequest) async throws -> SiteDesignSummary { try await server.save(request) }
    func delete(_ id: Int, expectedRevision: Int) async throws { throw SiteSyncError.missing }
}

/// Liste vide alors que les scénarios existent encore : une liste seule ne prouve pas une suppression.
private actor IncompleteListService: SiteSyncService {
    let server = FakeSiteService()
    private var downloadsFail = false
    init() throws {}
    func setDownloadsFail(_ value: Bool) { downloadsFail = value }
    func list() async throws -> [SiteDesignSummary] { [] }
    func download(_ id: Int) async throws -> SiteDesign {
        if downloadsFail { throw URLError(.notConnectedToInternet) }
        return try await server.download(id)
    }
    func save(_ request: SiteSaveRequest) async throws -> SiteDesignSummary { try await server.save(request) }
}

private actor MissingSiteService: SiteSyncService {
    let server: FakeSiteService
    private var missing = false
    private(set) var saves = 0
    init() throws { server = FakeSiteService() }
    func remove() { missing = true }
    func list() async throws -> [SiteDesignSummary] { try await server.list() }
    func download(_ id: Int) async throws -> SiteDesign {
        if missing { throw SiteSyncError.missing }
        return try await server.download(id)
    }
    func save(_ request: SiteSaveRequest) async throws -> SiteDesignSummary { saves += 1; return try await server.save(request) }
}
