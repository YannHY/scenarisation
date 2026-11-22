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
    /// Collaboration : « owner », « editor » ou « viewer », nom du propriétaire et partage actif.
    /// Absents des réponses d’un site antérieur au partage.
    var role: String? = nil
    var owner: String? = nil
    var shared: Bool? = nil
    /// Scénario d’un autre enseignant, partagé avec ce compte.
    var sharedWithMe: Bool { SiteShareRole(rawValue: role ?? "") != nil }
    /// Partagé en consultation seule.
    var readOnly: Bool { role == SiteShareRole.viewer.rawValue }
}

/// Droits d’un enseignant invité sur un scénario partagé.
enum SiteShareRole: String, Codable, CaseIterable, Identifiable, Sendable {
    case editor, viewer
    var id: String { rawValue }
    var label: String {
        switch self {
        case .editor: "Peut modifier"
        case .viewer: "Peut consulter"
        }
    }
}

// MARK: Collaboration

/// Autre fenêtre ouverte sur le même scénario, d’un collègue ou du même compte sur un autre appareil.
struct SiteParticipant: Codable, Equatable, Identifiable, Sendable {
    let userId: Int
    let username: String
    let clientId: String
    /// `activity:<id>:<champ>`, `moment:<id>:<champ>` ou `scenario::<champ>`.
    let focus: String?
    var id: String { clientId }
    /// Élément (activité ou moment) sur lequel travaille ce participant.
    var item: String? {
        guard let focus else { return nil }
        let parts = focus.split(separator: ":", maxSplits: 2, omittingEmptySubsequences: false)
        guard parts.count >= 2, parts[0] == "activity" || parts[0] == "moment", !parts[1].isEmpty else { return nil }
        return String(parts[1])
    }
}

struct SitePresence: Codable, Equatable, Sendable {
    let revision: Int
    let shared: Bool
    let participants: [SiteParticipant]
}

struct SitePresenceRequest: Codable, Equatable, Sendable {
    let design_id: Int
    let client_id: String
    let focus: String?
    var leave: Bool? = nil
}

struct SiteMember: Codable, Equatable, Identifiable, Sendable {
    let id: Int
    let username: String
    let role: String
    let email: String?
    var isOwner: Bool { role == "owner" }
    /// Droits d’un enseignant invité ; un rôle inconnu ne donne que la consultation.
    var shareRole: SiteShareRole { SiteShareRole(rawValue: role) ?? .viewer }
}

struct SiteSharing: Codable, Equatable, Sendable {
    let designId: Int
    let canManage: Bool
    let members: [SiteMember]
}

struct SiteDesign: Codable, Equatable, Sendable {
    let id: Int
    let title: String
    let revision: Int
    let updatedAt: String
    let document: Scenario
    /// « viewer » lorsque ce compte peut seulement consulter le scénario.
    var role: String? = nil
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
    /// Signale cette fenêtre aux autres participants et renvoie leur présence et la révision du site.
    func presence(_ request: SitePresenceRequest) async throws -> SitePresence
    func sharing(_ id: Int) async throws -> SiteSharing
    func share(_ id: Int, email: String, role: SiteShareRole) async throws -> SiteSharing
    /// Change les droits d’un enseignant déjà invité (propriétaire seulement).
    func setRole(_ id: Int, userID: Int, role: SiteShareRole) async throws -> SiteSharing
    /// Retire un enseignant du partage ; `nil` lorsque ce compte vient de quitter le partage.
    func unshare(_ id: Int, userID: Int) async throws -> SiteSharing?
}

extension SiteSyncService {
    func delete(_ id: Int, expectedRevision: Int) async throws { throw SiteSyncError.deletionUnavailable }
    func presence(_ request: SitePresenceRequest) async throws -> SitePresence { throw SiteSyncError.collaborationUnavailable }
    func sharing(_ id: Int) async throws -> SiteSharing { throw SiteSyncError.collaborationUnavailable }
    func share(_ id: Int, email: String, role: SiteShareRole) async throws -> SiteSharing { throw SiteSyncError.collaborationUnavailable }
    func setRole(_ id: Int, userID: Int, role: SiteShareRole) async throws -> SiteSharing { throw SiteSyncError.collaborationUnavailable }
    func unshare(_ id: Int, userID: Int) async throws -> SiteSharing? { throw SiteSyncError.collaborationUnavailable }
}

enum SiteSyncError: LocalizedError, Equatable {
    case unavailable, unauthorized, forbidden, conflict, missing, invalidResponse, wrongAccount, busy, localChanged
    case changedBeforeDeletion, deletionUnavailable, collaborationUnavailable
    /// Refus expliqué par le site (adresse inconnue, invitation impossible…).
    case rejected(String)
    var errorDescription: String? {
        switch self {
        case .collaborationUnavailable: "Le partage n’est pas encore disponible sur le site. Réessayez après sa mise à jour."
        case .rejected(let message): message
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
    /// Scénario partagé en consultation : rien n’est envoyé, la copie suit le site.
    var readOnly: Bool? = nil
    var isReadOnly: Bool { readOnly == true }

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
    var generation: UUID? = nil
}

@MainActor
final class SiteSyncDisk {
    let directory: URL
    private var generations: [String: UUID] = [:]
    init(directory: URL) { self.directory = directory }

    private struct Checkpoint: Codable {
        let generation: UUID
        let record: SiteSyncRecord
    }

    private func checkpointURL(_ id: UUID, account: SiteSyncAccount) -> URL {
        folder(account).appendingPathComponent("Checkpoints", isDirectory: true).appendingPathComponent(id.uuidString + ".json")
    }

    /// Frequent edits only encode the changed document. A new archive generation
    /// invalidates all older checkpoints atomically, even if cleanup is interrupted.
    func checkpoint(_ record: SiteSyncRecord, account: SiteSyncAccount) throws -> Bool {
        guard let generation = generations[account.key] else { return false }
        let url = checkpointURL(record.id, account: account)
        try FileManager.default.createDirectory(at: url.deletingLastPathComponent(), withIntermediateDirectories: true,
                                                attributes: [.posixPermissions: 0o700])
        let data = try JSONEncoder().encode(Checkpoint(generation: generation, record: record))
        try data.write(to: url, options: .atomic)
        return true
    }

    static func applicationSupport() throws -> SiteSyncDisk {
        let root = try FileManager.default.url(for: .applicationSupportDirectory, in: .userDomainMask,
                                               appropriateFor: nil, create: true)
        let directory = "Scenarisation/SiteSync"
        return SiteSyncDisk(directory: root.appendingPathComponent(directory, isDirectory: true))
    }

    private func folder(_ account: SiteSyncAccount) -> URL {
        // Hash rather than interpolate server/account strings into a filesystem path.
        directory.appendingPathComponent(SiteSyncAPI.accountKey(account.key), isDirectory: true)
    }

    func read(_ account: SiteSyncAccount) throws -> [SiteSyncRecord] {
        let url = folder(account).appendingPathComponent("state.json")
        guard FileManager.default.fileExists(atPath: url.path) else { return [] }
        let archive = try JSONDecoder().decode(SiteSyncArchive.self, from: Data(contentsOf: url))
        guard [1, 2].contains(archive.version), (archive.version == 1 || archive.generation != nil), archive.account.key == account.key,
              Set(archive.records.map(\.id)).count == archive.records.count else { throw SiteSyncError.invalidResponse }
        if let generation = archive.generation {
            generations[account.key] = generation
            return try archive.records.map { record in
                let checkpointURL = checkpointURL(record.id, account: account)
                guard FileManager.default.fileExists(atPath: checkpointURL.path) else { return record }
                let checkpoint = try JSONDecoder().decode(Checkpoint.self, from: Data(contentsOf: checkpointURL))
                guard checkpoint.generation == generation else { return record }
                guard checkpoint.record.id == record.id else { throw SiteSyncError.invalidResponse }
                return checkpoint.record
            }
        }
        generations.removeValue(forKey: account.key)
        return archive.records
    }

    func write(_ records: [SiteSyncRecord], account: SiteSyncAccount) throws {
        let folder = folder(account)
        try FileManager.default.createDirectory(at: folder, withIntermediateDirectories: true,
                                                attributes: [.posixPermissions: 0o700])
        let url = folder.appendingPathComponent("state.json")
        let generation = UUID()
        let data = try JSONEncoder().encode(SiteSyncArchive(version: 2, account: account, records: records, generation: generation))
        try data.write(to: url, options: [.atomic])
        generations[account.key] = generation
        try FileManager.default.setAttributes([.posixPermissions: 0o600], ofItemAtPath: url.path)
        try? FileManager.default.removeItem(at: folder.appendingPathComponent("Checkpoints", isDirectory: true))
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
