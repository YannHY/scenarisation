import Foundation
import Combine

@MainActor
final class SiteSyncWorkspace: ObservableObject {
    let account: SiteSyncAccount
    let service: any SiteSyncService
    private let disk: SiteSyncDisk?
    @Published private(set) var records: [SiteSyncRecord]
    @Published private(set) var remoteDesigns: [SiteDesignSummary] = []
    @Published private(set) var syncing: Set<UUID> = []
    @Published private(set) var refreshing = false
    private var queuedAutomatic: Set<UUID> = []

    init(account: SiteSyncAccount, service: any SiteSyncService, disk: SiteSyncDisk? = nil) throws {
        self.account = account; self.service = service; self.disk = disk
        records = try disk?.read(account) ?? []
    }

    func record(_ id: UUID) -> SiteSyncRecord? { records.first { $0.id == id } }
    func clearRemoteListing() { remoteDesigns = [] }

    private func commit(_ next: [SiteSyncRecord]) throws {
        try disk?.write(next, account: account)
        records = next
    }
    private func update(_ id: UUID, _ change: (inout SiteSyncRecord) throws -> Void) throws {
        var next = records
        guard let index = next.firstIndex(where: { $0.id == id }) else { throw SiteSyncError.missing }
        try change(&next[index]); try commit(next)
    }

    @discardableResult
    func addLocal(_ scenario: Scenario) throws -> SyncDocumentIdentity {
        let record = SiteSyncRecord(id: UUID(), local: scenario)
        try commit(records + [record])
        return SyncDocumentIdentity(id: record.id, accountKey: account.key)
    }

    func stage(_ identity: SyncDocumentIdentity, scenario: Scenario, previous: Scenario? = nil) throws {
        guard identity.accountKey == account.key else { throw SiteSyncError.wrongAccount }
        guard let existing = record(identity.id) else { throw SiteSyncError.missing }
        guard existing.local != scenario else { return }
        if let previous, existing.local != previous { throw SiteSyncError.localChanged }
        try update(identity.id) { $0.local = scenario }
    }

    func refresh() async throws {
        guard !refreshing else { return }
        refreshing = true; defer { refreshing = false }
        let designs = try await service.list()
        try Task.checkCancellation()
        remoteDesigns = designs
    }

    func downloadToMac(_ remoteID: Int) async throws -> SiteSyncRecord {
        if let existing = records.first(where: { $0.remoteID == remoteID }) { return existing }
        let remote = try await service.download(remoteID)
        try Task.checkCancellation()
        // Another window might have downloaded the same scenario while we awaited the network.
        if let existing = records.first(where: { $0.remoteID == remoteID }) { return existing }
        let record = SiteSyncRecord(id: UUID(), remoteID: remote.id, revision: remote.revision,
                                   local: remote.document, base: remote.document, lastSyncedAt: Date())
        try commit(records + [record]); return record
    }

    /// Manual synchronization. Local edits made during a request stay pending after its acknowledgement.
    func synchronize(_ id: UUID) async throws {
        guard !syncing.contains(id) else { throw SiteSyncError.busy }
        guard let original = record(id) else { throw SiteSyncError.missing }
        guard !original.remoteMissing else { throw SiteSyncError.missing }
        syncing.insert(id); defer { syncing.remove(id) }
        do {
            if let remoteID = original.remoteID {
                let remote = try await service.download(remoteID)
                try Task.checkCancellation()
                try integrate(remote, into: id)
                guard let current = record(id), current.remoteConflict == nil, current.local != current.base else { return }
                let sent = current.local
                let response = try await service.save(SiteSaveRequest(client_uuid: id, design_id: remoteID,
                    expected_revision: current.revision, title: sent.name, document: sent))
                try Task.checkCancellation()
                try update(id) {
                    $0.base = sent; $0.revision = response.revision; $0.lastSyncedAt = Date()
                }
            } else {
                let sent = original.local
                let response = try await service.save(SiteSaveRequest(client_uuid: id, design_id: nil,
                    expected_revision: nil, title: sent.name, document: sent))
                // A retried creation can refer to an earlier payload. Read its actual content before acknowledging it.
                let remote = try await service.download(response.id)
                try Task.checkCancellation()
                try update(id) {
                    $0.remoteID = remote.id; $0.revision = remote.revision
                    $0.base = remote.document; $0.lastSyncedAt = Date()
                    if remote.document != sent && $0.local != remote.document { $0.remoteConflict = remote }
                }
            }
        } catch SiteSyncError.conflict {
            guard let remoteID = record(id)?.remoteID else { throw SiteSyncError.invalidResponse }
            do {
                let remote = try await service.download(remoteID)
                try Task.checkCancellation()
                try integrate(remote, into: id)
            } catch SiteSyncError.missing {
                try update(id) { $0.remoteMissing = true }
                throw SiteSyncError.missing
            }
        } catch SiteSyncError.missing {
            // Never recreate a document that the server has deleted.
            if original.remoteID != nil { try update(id) { $0.remoteMissing = true } }
            throw SiteSyncError.missing
        }
    }

    private func integrate(_ remote: SiteDesign, into id: UUID) throws {
        try update(id) { current in
            guard remote.id == current.remoteID, remote.revision >= (current.revision ?? 0) else {
                throw SiteSyncError.invalidResponse
            }
            if remote.revision == current.revision && remote.document != current.base {
                throw SiteSyncError.invalidResponse
            }
            let localChanged = current.local != current.base
            let remoteChanged = remote.document != current.base
            if localChanged && remoteChanged && current.local != remote.document {
                current.remoteConflict = remote
                return
            }
            if !localChanged { current.local = remote.document }
            current.base = remote.document; current.revision = remote.revision
            current.remoteConflict = nil; current.remoteMissing = false; current.lastSyncedAt = Date()
        }
    }

    /// Preserve both documents locally. Uploading the independent copy remains an explicit action.
    func keepBoth(_ id: UUID) throws -> SiteSyncRecord {
        guard !syncing.contains(id), let original = record(id), let remote = original.remoteConflict else {
            throw SiteSyncError.invalidResponse
        }
        var copy = original.local
        copy.meta["name"] = .string((copy.name.isEmpty ? "Sans titre" : copy.name) + " — copie Mac")
        let duplicate = SiteSyncRecord(id: UUID(), local: copy)
        var next = records
        let index = next.firstIndex { $0.id == id }!
        next[index].local = remote.document; next[index].base = remote.document
        next[index].revision = remote.revision; next[index].remoteConflict = nil; next[index].lastSyncedAt = Date()
        try commit(next + [duplicate]); return duplicate
    }

    func copyDeleted(_ id: UUID) throws -> SiteSyncRecord {
        guard !syncing.contains(id), let original = record(id), original.remoteMissing else { throw SiteSyncError.invalidResponse }
        let copy = SiteSyncRecord(id: UUID(), local: original.local)
        try commit(records + [copy]); return copy
    }

    /// Envoi automatique après une modification. Une demande reçue pendant un envoi est reprise ensuite ;
    /// les erreurs laissent le scénario « en attente » et son état reste visible dans la fenêtre.
    func synchronizeAutomatically(_ id: UUID) async {
        guard record(id) != nil else { return }
        if syncing.contains(id) { queuedAutomatic.insert(id); return }
        repeat {
            queuedAutomatic.remove(id)
            guard let current = record(id), current.remoteConflict == nil, !current.remoteMissing else { return }
            do { try await synchronize(id) } catch { return }
        } while queuedAutomatic.contains(id)
    }

    /// Supprime le scénario du compte, puis de ce Mac. Un scénario modifié sur le site depuis
    /// la dernière synchronisation n’est pas supprimé.
    func delete(_ id: UUID) async throws {
        guard !syncing.contains(id) else { throw SiteSyncError.busy }
        guard let original = record(id) else { throw SiteSyncError.missing }
        if let remoteID = original.remoteID, !original.remoteMissing {
            guard let revision = original.revision else { throw SiteSyncError.invalidResponse }
            syncing.insert(id)
            do { try await deleteOnSite(remoteID, revision: revision) } catch { syncing.remove(id); throw error }
            syncing.remove(id)
        }
        try remove(id)
    }

    /// Supprime un scénario présent uniquement sur le site.
    func deleteRemote(_ summary: SiteDesignSummary) async throws {
        try await deleteOnSite(summary.id, revision: summary.revision)
        remoteDesigns.removeAll { $0.id == summary.id }
    }

    private func deleteOnSite(_ remoteID: Int, revision: Int) async throws {
        do { try await service.delete(remoteID, expectedRevision: revision) }
        catch SiteSyncError.conflict { throw SiteSyncError.changedBeforeDeletion }
        catch SiteSyncError.missing {
            // Une route absente du serveur répond aussi 404 : le scénario doit avoir réellement disparu.
            do { _ = try await service.download(remoteID) } catch SiteSyncError.missing { return }
            throw SiteSyncError.deletionUnavailable
        }
    }

    /// Forgets the Mac copy only.
    func remove(_ id: UUID) throws {
        guard !syncing.contains(id) else { throw SiteSyncError.busy }
        guard record(id) != nil else { throw SiteSyncError.missing }
        try commit(records.filter { $0.id != id })
        try? disk?.removeWorkingFile(id, account: account)
    }

    func workingFile(_ id: UUID) throws -> URL {
        guard let disk, let record = record(id) else { throw SiteSyncError.missing }
        return try disk.workingFile(record, account: account)
    }
    func workingFileURL(_ id: UUID) throws -> URL {
        guard let disk else { throw SiteSyncError.missing }
        return disk.workingFileURL(id, account: account)
    }
}

