import Foundation

// The native API contract is documented in docs/site-sync-api.md.
struct SiteUser: Codable, Equatable, Sendable {
    let id: Int
    let username: String
    let email: String
}

struct SiteDesignSummary: Codable, Equatable, Identifiable, Sendable {
    let id: Int
    let title: String
    let revision: Int
    let updatedAt: String
}

struct SiteDesign: Codable, Equatable, Sendable {
    let id: Int
    let title: String
    let revision: Int
    let updatedAt: String
    let document: Scenario
}

struct SiteSaveRequest: Codable, Sendable {
    let client_uuid: UUID
    let design_id: Int?
    let expected_revision: Int?
    let title: String
    let document: Scenario

    init(client_uuid: UUID, design_id: Int?, expected_revision: Int?, title: String, document: Scenario) {
        self.client_uuid = client_uuid; self.design_id = design_id
        self.expected_revision = expected_revision; self.title = title
        var payload = document
        payload.fields.removeValue(forKey: "_scenarisationMac")
        self.document = payload
    }
}

protocol SiteSyncService: Sendable {
    func list() async throws -> [SiteDesignSummary]
    func download(_ id: Int) async throws -> SiteDesign
    func save(_ request: SiteSaveRequest) async throws -> SiteDesignSummary
    /// Supprime le scénario du compte s’il est encore à cette révision.
    func delete(_ id: Int, expectedRevision: Int) async throws
}

extension SiteSyncService {
    func delete(_ id: Int, expectedRevision: Int) async throws { throw SiteSyncError.deletionUnavailable }
}

enum SiteSyncError: LocalizedError {
    case unavailable, unauthorized, forbidden, conflict, missing, invalidResponse, wrongAccount, busy, localChanged
    case changedBeforeDeletion, deletionUnavailable
    var errorDescription: String? {
        switch self {
        case .unavailable: "La connexion au site est temporairement indisponible. Réessayez."
        case .unauthorized: "Votre session a expiré. Reconnectez votre compte avant de synchroniser."
        case .forbidden: "Votre compte n’autorise pas cette opération. Vos changements restent sur \(Platform.thisDevice)."
        case .conflict: "Le scénario a aussi été modifié sur le site. Vos changements sont conservés."
        case .missing: "Ce scénario n’est plus disponible sur le site. Votre copie locale est conservée."
        case .invalidResponse: "Le site a renvoyé une réponse inattendue. Votre copie locale est conservée."
        case .wrongAccount: "Ce document est associé à un autre compte. Ouvrez ce compte pour le synchroniser."
        case .busy: "Une synchronisation est déjà en cours."
        case .localChanged: "Une autre fenêtre a modifié la copie locale. Enregistrez votre document avant de poursuivre."
        case .changedBeforeDeletion: "Ce scénario a été modifié sur le site depuis la dernière synchronisation. Il n’a pas été supprimé : synchronisez-le pour voir ces changements, puis supprimez-le si vous le souhaitez."
        case .deletionUnavailable: "Le site ne permet pas encore de supprimer un scénario depuis l’app. Rien n’a été supprimé."
        }
    }
}

/// All state belongs to one server/account pair; its key is never an authorization credential.
struct SiteSyncAccount: Codable, Equatable, Sendable {
    let key: String
    let label: String
    static func live(server: URL, user: SiteUser) -> SiteSyncAccount {
        SiteSyncAccount(key: SiteSyncAPI.accountKey(server.absoluteString + "#user:" + String(user.id)),
                        label: user.username)
    }
}

struct SyncDocumentIdentity: Codable, Equatable, Sendable {
    let id: UUID
    let accountKey: String
}

enum SiteSyncStatus: String, Codable, Sendable {
    case local, pending, synced, conflict, missing
    var label: String {
        switch self {
        case .local: "Sur \(Platform.thisDevice)"
        case .pending: "Modifications en attente"
        case .synced: "Synchronisé"
        case .conflict: "Conflit à examiner"
        case .missing: "Supprimé sur le site"
        }
    }
    var symbol: String {
        switch self {
        case .local: "internaldrive"
        case .pending: "arrow.triangle.2.circlepath"
        case .synced: "checkmark.icloud"
        case .conflict: "exclamationmark.arrow.triangle.2.circlepath"
        case .missing: "icloud.slash"
        }
    }
}

struct SiteSyncRecord: Codable, Equatable, Identifiable, Sendable {
    let id: UUID
    var remoteID: Int?
    var revision: Int?
    var local: Scenario
    var base: Scenario?
    var remoteConflict: SiteDesign?
    var remoteMissing = false
    var lastSyncedAt: Date?

    var status: SiteSyncStatus {
        if remoteMissing { return .missing }
        if remoteConflict != nil { return .conflict }
        if base == nil || local != base { return .pending }
        return .synced
    }
}

/// Persistent checkpoints are independent of Finder files and never contain tokens.
struct SiteSyncArchive: Codable {
    let version: Int
    let account: SiteSyncAccount
    var records: [SiteSyncRecord]
}

struct SiteSyncDisk {
    let directory: URL

    static func applicationSupport() throws -> SiteSyncDisk {
        let root = try FileManager.default.url(for: .applicationSupportDirectory, in: .userDomainMask,
                                               appropriateFor: nil, create: true)
        return SiteSyncDisk(directory: root.appendingPathComponent("Scenarisation/SiteSync", isDirectory: true))
    }

    private func folder(_ account: SiteSyncAccount) -> URL {
        // Hash rather than interpolate server/account strings into a filesystem path.
        directory.appendingPathComponent(SiteSyncAPI.accountKey(account.key), isDirectory: true)
    }

    func read(_ account: SiteSyncAccount) throws -> [SiteSyncRecord] {
        let url = folder(account).appendingPathComponent("state.json")
        guard FileManager.default.fileExists(atPath: url.path) else { return [] }
        let archive = try JSONDecoder().decode(SiteSyncArchive.self, from: Data(contentsOf: url))
        guard archive.version == 1, archive.account.key == account.key,
              Set(archive.records.map(\.id)).count == archive.records.count else { throw SiteSyncError.invalidResponse }
        return archive.records
    }

    func write(_ records: [SiteSyncRecord], account: SiteSyncAccount) throws {
        let folder = folder(account)
        try FileManager.default.createDirectory(at: folder, withIntermediateDirectories: true,
                                                attributes: [.posixPermissions: 0o700])
        let url = folder.appendingPathComponent("state.json")
        let data = try JSONEncoder().encode(SiteSyncArchive(version: 1, account: account, records: records))
        try data.write(to: url, options: [.atomic])
        try FileManager.default.setAttributes([.posixPermissions: 0o600], ofItemAtPath: url.path)
    }

    func workingFileURL(_ id: UUID, account: SiteSyncAccount) -> URL {
        folder(account).appendingPathComponent("Documents", isDirectory: true).appendingPathComponent(id.uuidString + ".json")
    }

    func removeWorkingFile(_ id: UUID, account: SiteSyncAccount) throws {
        let url = workingFileURL(id, account: account)
        if FileManager.default.fileExists(atPath: url.path) { try FileManager.default.removeItem(at: url) }
    }

    /// Stable paths let NSDocumentController reuse an already-open document window.
    func workingFile(_ record: SiteSyncRecord, account: SiteSyncAccount) throws -> URL {
        let url = workingFileURL(record.id, account: account)
        let folder = url.deletingLastPathComponent()
        try FileManager.default.createDirectory(at: folder, withIntermediateDirectories: true,
                                                attributes: [.posixPermissions: 0o700])
        let identity = SyncDocumentIdentity(id: record.id, accountKey: account.key)
        try ScenarioDocument.data(record.local, identity: identity).write(to: url, options: .atomic)
        try FileManager.default.setAttributes([.posixPermissions: 0o600], ofItemAtPath: url.path)
        return url
    }
}
