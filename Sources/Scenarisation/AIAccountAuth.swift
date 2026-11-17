import AppKit
import Foundation
import Network

/// A short-lived callback server bound only to IPv4 loopback. It never logs URLs.
@MainActor
final class OAuthLoopback {
    private var listener: NWListener?
    private var ready: CheckedContinuation<URL, Error>?
    private var callback: CheckedContinuation<URL, Error>?
    private var callbackResult: Result<URL, Error>?
    private var callbackAccepted = false
    private var redirectURI: URL?
    private var timer: Task<Void, Never>?
    private var connections: [UUID: NWConnection] = [:]

    func start() async throws -> URL {
        try Task.checkCancellation()
        return try await withTaskCancellationHandler {
            try await withCheckedThrowingContinuation { continuation in
                ready = continuation
                do {
                    let parameters = NWParameters.tcp
                    parameters.requiredLocalEndpoint = .hostPort(host: .ipv4(.loopback), port: .any)
                    let listener = try NWListener(using: parameters)
                    self.listener = listener
                    listener.stateUpdateHandler = { [weak self] state in
                        Task { @MainActor in
                            guard let self else { return }
                            switch state {
                            case .ready:
                                guard let port = listener.port,
                                      let uri = URL(string: "http://127.0.0.1:\(port.rawValue)/auth/callback") else {
                                    self.stop(ScenarioError.invalid("Impossible de préparer le retour de connexion.")); return
                                }
                                self.redirectURI = uri
                                self.ready?.resume(returning: uri); self.ready = nil
                            case .failed:
                                self.stop(ScenarioError.invalid("Le retour de connexion local n’est pas disponible. Réessayez."))
                            default: break
                            }
                        }
                    }
                    listener.newConnectionHandler = { [weak self] connection in
                        Task { @MainActor in self?.receive(connection) }
                    }
                    listener.start(queue: .main)
                    timer = Task { [weak self] in
                        do { try await Task.sleep(for: .seconds(600)) } catch { return }
                        self?.stop(ScenarioError.invalid("La connexion a expiré. Recommencez."))
                    }
                } catch { stop(error) }
            }
        } onCancel: { Task { @MainActor in self.stop(CancellationError()) } }
    }

    func waitForCallback() async throws -> URL {
        try Task.checkCancellation()
        return try await withTaskCancellationHandler {
            if let result = callbackResult { return try result.get() }
            return try await withCheckedThrowingContinuation { callback = $0 }
        } onCancel: { Task { @MainActor in self.stop(CancellationError()) } }
    }

    func stop(_ error: Error = CancellationError()) {
        listener?.cancel(); listener = nil
        timer?.cancel(); timer = nil
        for connection in connections.values { connection.cancel() }
        connections = [:]
        ready?.resume(throwing: error); ready = nil
        if callbackResult == nil { callbackResult = .failure(error) }
        callback?.resume(with: callbackResult!); callback = nil
    }

    private func receive(_ connection: NWConnection) {
        guard connections.count < 8 else { connection.cancel(); return }
        let id = UUID(); connections[id] = connection
        connection.start(queue: .main)
        read(connection, id: id, accumulated: Data())
        Task { [weak self] in
            do { try await Task.sleep(for: .seconds(10)) } catch { return }
            guard let self, let connection = self.connections.removeValue(forKey: id) else { return }
            connection.cancel()
        }
    }
    private func read(_ connection: NWConnection, id: UUID, accumulated: Data) {
        connection.receive(minimumIncompleteLength: 1, maximumLength: 16_384) { [weak self] data, _, complete, error in
            Task { @MainActor in
                guard let self, self.connections[id] != nil else { return }
                let bytes = accumulated + (data ?? Data())
                guard bytes.count <= 16_384, error == nil else {
                    self.connections.removeValue(forKey: id)?.cancel(); return
                }
                if let text = String(data: bytes, encoding: .utf8), text.contains("\r\n\r\n") {
                    self.handle(text, connection: connection, id: id)
                } else if complete {
                    self.connections.removeValue(forKey: id)?.cancel()
                } else { self.read(connection, id: id, accumulated: bytes) }
            }
        }
    }
    private func handle(_ request: String, connection: NWConnection, id: UUID) {
        let line = request.components(separatedBy: "\r\n").first?.split(separator: " ").map(String.init) ?? []
        guard let redirectURI, line.count == 3, line[0] == "GET", line[1].hasPrefix("/auth/callback?"),
              let url = URL(string: "http://127.0.0.1:\(redirectURI.port!)" + line[1]),
              url.path == "/auth/callback" else {
            reply("Retour non reconnu.", status: "404 Not Found", connection: connection, id: id)
            return
        }
        guard callbackResult == nil, !callbackAccepted else {
            reply("Ce retour a déjà été reçu. Revenez à Scénarisation.", status: "409 Conflict", connection: connection, id: id)
            return
        }
        callbackAccepted = true
        listener?.cancel(); listener = nil
        timer?.cancel(); timer = nil
        // Finish the browser response before the authorization scope closes this server.
        // State, issuer and client validation still precede any code exchange.
        reply("Retour reçu. Vous pouvez revenir à Scénarisation pour terminer la connexion.",
              status: "200 OK", connection: connection, id: id) { [weak self] in
            guard let self, self.callbackResult == nil else { return }
            self.callbackResult = .success(url)
            self.callback?.resume(returning: url); self.callback = nil
        }
    }
    private func reply(_ text: String, status: String, connection: NWConnection, id: UUID,
                       completion: (@MainActor () -> Void)? = nil) {
        let body = Data(text.utf8)
        let headers = "HTTP/1.1 \(status)\r\nContent-Type: text/plain; charset=utf-8\r\nCache-Control: no-store\r\nReferrer-Policy: no-referrer\r\nX-Content-Type-Options: nosniff\r\nConnection: close\r\nContent-Length: \(body.count)\r\n\r\n"
        connection.send(content: Data(headers.utf8) + body, completion: .contentProcessed { [weak self] _ in
            connection.cancel()
            Task { @MainActor in
                self?.connections.removeValue(forKey: id)
                completion?()
            }
        })
    }
}

struct ChatGPTAccountInfo: Identifiable, Equatable {
    let id: String
    let email: String?
    let connected: Bool
    let canUsePlan: Bool
    var label: String { email ?? "Compte ChatGPT" }
}

struct ChatGPTCredential: Codable, Sendable {
    let clientID: String
    let subject: String
    let email: String?
    var accessToken: String?
    var refreshToken: String?
    var idToken: String?
    var scopes: [String]
    var expiresAt: Date

    var info: ChatGPTAccountInfo {
        ChatGPTAccountInfo(id: clientID, email: email, connected: !(accessToken ?? "").isEmpty,
            canUsePlan: !(accessToken ?? "").isEmpty && Set(scopes).isSuperset(of: ["chatgpt.tokens.use.direct", "resource.invoke"]))
    }
    mutating func clearTokens() {
        accessToken = nil; refreshToken = nil; idToken = nil; scopes = []; expiresAt = .distantPast
    }
}

private struct ChatGPTVault: Codable {
    var registrations: [ChatGPTCredential] = []
    var selectedClientID: String?
    var pendingClientID: String?
}

/// Only public identity/connection information is observable; tokens stay in the Keychain.
@MainActor
final class ChatGPTAuth: ObservableObject {
    static let shared = ChatGPTAuth()
    @Published private(set) var accounts: [ChatGPTAccountInfo] = []
    @Published private(set) var activeAccount: ChatGPTAccountInfo?
    @Published private(set) var signingIn = false
    @Published private(set) var storageError: String?
    private var vault = ChatGPTVault()
    private let defaults: UserDefaults
    private let read: (String) throws -> String
    private let write: (String, String) throws -> Void
    private let client: ChatGPTOAuthClient
    private let account = "chatgpt.oauth.registrations"
    private var refreshTask: Task<ChatGPTCredential, Error>?
    private var sessionVersion = UUID()

    init(defaults: UserDefaults = .standard, client: ChatGPTOAuthClient = ChatGPTOAuthClient(),
         read: @escaping (String) throws -> String = { try CredentialStore.read(account: $0) },
         write: @escaping (String, String) throws -> Void = { try CredentialStore.save($0, account: $1) }) {
        self.defaults = defaults; self.client = client; self.read = read; self.write = write
        do {
            let saved = try read(account)
            if !saved.isEmpty { vault = try JSONDecoder().decode(ChatGPTVault.self, from: Data(saved.utf8)) }
            publish()
        } catch { storageError = "La connexion ChatGPT enregistrée n’a pas pu être lue dans le Trousseau." }
    }

    var hostID: String {
        if let saved = defaults.string(forKey: "ai.chatgpt.hostID"), saved.hasPrefix("urn:uuid:") { return saved }
        let value = "urn:uuid:" + UUID().uuidString.lowercased()
        defaults.set(value, forKey: "ai.chatgpt.hostID")
        return value
    }

    func signIn(useSavedAccount: Bool = true, reconsent: Bool = false) async throws {
        guard !signingIn else { throw ScenarioError.invalid("Une connexion ChatGPT est déjà en cours.") }
        signingIn = true
        defer { signingIn = false }
        let selected = useSavedAccount ? currentCredential : nil
        let pendingID = useSavedAccount ? vault.pendingClientID : nil
        let configuration = try await client.discovery()
        let callback = OAuthLoopback()
        defer { callback.stop() }
        let redirect = try await callback.start()
        let attempt = try ChatGPTSignInAttempt(redirectURI: redirect, hostID: hostID,
            clientID: selected?.clientID ?? pendingID)
        let url = try attempt.authorizationURL(endpoint: configuration.authorization_endpoint,
            idTokenHint: selected?.idToken, reconsent: reconsent)
        try Task.checkCancellation()
        guard NSWorkspace.shared.open(url) else { throw ScenarioError.invalid("Impossible d’ouvrir le navigateur pour connecter ChatGPT.") }
        let result = try attempt.callback(await callback.waitForCallback())
        if selected == nil {
            vault.pendingClientID = result.clientID
            try persist() // Preserve registration even when a one-use code expires.
        }
        let tokens = try await client.exchange(result, attempt: attempt, discovery: configuration)
        guard let idToken = tokens.id_token else { throw ScenarioError.invalid("ChatGPT n’a pas renvoyé d’identité vérifiable.") }
        let identity = try await client.identity(token: idToken, clientID: result.clientID, nonce: attempt.nonce, discovery: configuration)
        if let selected, identity.subject != selected.subject {
            throw ScenarioError.invalid("Ce compte ne correspond pas à la connexion choisie. Utilisez « Autre compte ».")
        }
        try Task.checkCancellation()
        let credential = ChatGPTCredential(clientID: result.clientID, subject: identity.subject, email: identity.email,
            accessToken: tokens.access_token, refreshToken: tokens.refresh_token, idToken: idToken,
            scopes: tokens.scope.split(separator: " ").map(String.init),
            expiresAt: .now.addingTimeInterval(tokens.expires_in))
        var updated = vault
        updated.registrations.removeAll { $0.clientID == credential.clientID }
        updated.registrations.append(credential)
        updated.selectedClientID = credential.clientID; updated.pendingClientID = nil
        try persist(updated)
        refreshTask?.cancel(); refreshTask = nil; sessionVersion = UUID()
        NSApplication.shared.activate(ignoringOtherApps: true)
    }

    func selectAccount(_ id: String) throws {
        guard !signingIn else { throw ScenarioError.invalid("Attendez la fin de la connexion ChatGPT en cours.") }
        guard vault.registrations.contains(where: { $0.clientID == id }) else { return }
        refreshTask?.cancel(); refreshTask = nil; sessionVersion = UUID()
        var updated = vault; updated.selectedClientID = id
        try persist(updated)
    }

    func bearerToken() async throws -> String {
        guard let credential = currentCredential, credential.info.canUsePlan, let access = credential.accessToken else {
            throw ScenarioError.invalid("Connectez ChatGPT et autorisez l’utilisation de votre abonnement dans les réglages.")
        }
        if credential.expiresAt.timeIntervalSinceNow > 60 { return access }
        let version = sessionVersion
        if refreshTask == nil {
            guard let refresh = credential.refreshToken else {
                try clearCurrentTokens()
                throw ScenarioError.invalid("Votre connexion ChatGPT a expiré. Connectez-vous à nouveau.")
            }
            refreshTask = Task {
                let discovery = try await client.discovery()
                let tokens = try await client.refresh(clientID: credential.clientID, refreshToken: refresh, discovery: discovery)
                var updated = credential
                if let idToken = tokens.id_token {
                    let identity = try await client.identity(token: idToken, clientID: credential.clientID, nonce: nil, discovery: discovery)
                    guard identity.subject == credential.subject else {
                        throw ScenarioError.invalid("L’identité du compte ChatGPT a changé. Reconnectez-vous.")
                    }
                    updated.idToken = idToken
                }
                updated.accessToken = tokens.access_token
                updated.refreshToken = tokens.refresh_token ?? credential.refreshToken
                updated.scopes = tokens.scope.split(separator: " ").map(String.init)
                updated.expiresAt = .now.addingTimeInterval(tokens.expires_in)
                try Task.checkCancellation()
                return updated
            }
        }
        do {
            let updated = try await refreshTask!.value
            guard version == sessionVersion else { throw CancellationError() }
            try Task.checkCancellation()
            var next = vault
            next.registrations.removeAll { $0.clientID == updated.clientID }
            next.registrations.append(updated)
            try persist(next)
            refreshTask = nil
            guard updated.info.canUsePlan, let token = updated.accessToken else {
                throw ScenarioError.invalid("L’utilisation de votre abonnement ChatGPT n’est pas autorisée. Reconnectez-vous pour l’activer.")
            }
            return token
        } catch {
            if version == sessionVersion {
                refreshTask = nil
                if let error = error as? OAuthServiceError, error.requiresSignIn { try clearCurrentTokens() }
            }
            throw error
        }
    }

    /// Local sign-out always clears tokens, even when remote revocation is unavailable.
    func signOut() async throws -> String? {
        guard !signingIn else { throw ScenarioError.invalid("Une connexion ChatGPT est déjà en cours.") }
        signingIn = true
        defer { signingIn = false }
        let pendingRefresh = refreshTask
        sessionVersion = UUID(); refreshTask = nil
        // Wait for a rotating token already in flight before revoking this session.
        var selected = currentCredential
        if let pendingRefresh, let refreshed = try? await pendingRefresh.value { selected = refreshed }
        var warning: String?
        if let selected, let refresh = selected.refreshToken {
            do {
                let configuration = try await client.discovery()
                try await client.revoke(clientID: selected.clientID, refreshToken: refresh, discovery: configuration)
            } catch {
                warning = "Déconnexion locale effectuée. La révocation distante n’a pas été confirmée ; vous pouvez retirer l’accès dans les réglages ChatGPT."
            }
        }
        if let id = selected?.clientID {
            var next = vault
            if let index = next.registrations.firstIndex(where: { $0.clientID == id }) { next.registrations[index].clearTokens() }
            try persistCleared(next)
        }
        return warning
    }

    func handleAPIError(_ error: Error, clientID: String? = nil) {
        if let clientID, clientID != vault.selectedClientID { return }
        if let error = error as? OAuthServiceError, error.status == 401 || error.requiresSignIn { try? clearCurrentTokens() }
    }
    private var currentCredential: ChatGPTCredential? {
        vault.registrations.first { $0.clientID == vault.selectedClientID }
    }
    private func clearCurrentTokens() throws {
        guard let index = vault.registrations.firstIndex(where: { $0.clientID == vault.selectedClientID }) else { return }
        var next = vault; next.registrations[index].clearTokens()
        try persistCleared(next)
    }
    private func persistCleared(_ next: ChatGPTVault) throws {
        do { try persist(next) }
        catch {
            vault = next; publish()
            storageError = "Les jetons ont été retirés de la session, mais le Trousseau n’a pas pu être mis à jour."
            throw error
        }
    }
    private func persist(_ updated: ChatGPTVault? = nil) throws {
        let next = updated ?? vault
        try write(String(decoding: JSONEncoder().encode(next), as: UTF8.self), account)
        vault = next; storageError = nil; publish()
    }
    private func publish() {
        accounts = vault.registrations.map(\.info)
        activeAccount = currentCredential?.info
    }
}
