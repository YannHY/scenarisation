import Foundation
import Testing
@testable import Scenarisation

/// Test de bout en bout de la collaboration : le vrai code PHP du site, servi par `php -S` sur ce Mac,
/// et le vrai client de l’app (`SiteSyncAPI`, `SiteSyncWorkspace`) pour trois comptes.
///
/// Désactivé par défaut. Pour le lancer :
///
///     SCENARISATION_SITE_DIR=/chemin/vers/learning-designer swift test --filter LiveSiteTests
///
/// La base est une base SQLite jetable, sauf si `SCENARISATION_SITE_DSN` (et `SCENARISATION_SITE_DB_USER`,
/// `SCENARISATION_SITE_DB_PASS`) désigne une base MySQL ou MariaDB de test, qui doit être vide.
/// Aucune requête ne part vers scenarisation.eu.
@Suite("Site réel en local : collaboration", .serialized,
       .enabled(if: ProcessInfo.processInfo.environment["SCENARISATION_SITE_DIR"] != nil))
@MainActor
struct LiveSiteTests {
    @Test func threeAccountsShareMergeAndLeaveThroughTheRealSite() async throws {
        let site = try await LiveSite.start()
        defer { site.stop() }
        let marie = try site.workspace(0), paul = try site.workspace(1)
        let lea = try site.api(2)

        // Marie crée un scénario dans son compte.
        let mine = try marie.addLocal(.template())
        try await marie.synchronize(mine.id)
        let remoteID = try #require(marie.record(mine.id)?.remoteID)
        #expect(marie.record(mine.id)?.status == .synced)

        // Invitations refusées : adresse inconnue, compte non membre.
        do { _ = try await marie.share(remoteID, email: "inconnu@example.test"); Issue.record("Adresse inconnue acceptée") }
        catch SiteSyncError.rejected(let message) { #expect(message.contains("Aucun compte vérifié")) }
        await #expect(throws: SiteSyncError.missing) { _ = try await paul.share(remoteID, email: "lea@example.test") }

        // Marie invite Paul (casse de l’adresse indifférente).
        let shared = try await marie.share(remoteID, email: "PAUL@example.test")
        #expect(shared.canManage && shared.members.map(\.username) == ["Marie", "Paul"])
        #expect(shared.members.allSatisfy { $0.email != nil })

        // Paul retrouve le scénario dans son compte et l’ouvre.
        try await paul.refresh()
        let summary = try #require(paul.summary(for: remoteID))
        #expect(summary.sharedWithMe && summary.owner == "Marie" && summary.shared == true)
        let theirs = try await paul.downloadToMac(remoteID)
        let paulSharing = try await paul.sharing(remoteID)
        #expect(!paulSharing.canManage && paulSharing.members.allSatisfy { $0.email == nil })

        // Présence : chacun voit l’autre et l’endroit où il travaille.
        let activityID = theirs.local.moments[0].activities[0].id
        let marieClient = UUID().uuidString.lowercased(), paulClient = UUID().uuidString.lowercased()
        _ = try await marie.presence(of: mine.id, clientID: marieClient, focus: "activity:\(activityID):instructions")
        let seenByPaul = try #require(try await paul.presence(of: theirs.id, clientID: paulClient, focus: nil))
        #expect(seenByPaul.shared && seenByPaul.participants.map(\.username) == ["Marie"])
        #expect(seenByPaul.participants.first?.focus == "activity:\(activityID):instructions")
        #expect(seenByPaul.revision == marie.record(mine.id)?.revision)
        let seenByMarie = try #require(try await marie.presence(of: mine.id, clientID: marieClient, focus: nil))
        #expect(seenByMarie.participants.map(\.username) == ["Paul"])

        // Modifications simultanées sur des champs différents, envoyées en même temps.
        try edit(marie, mine.id) {
            $0.moments[0].activities[0]["instructions"] = "Lire le document.\nRépondre aux questions."
            $0.meta["boardLayout"] = .string("grid")
        }
        var added = Activity.blank(); added["description"] = "Activité de Paul"
        try edit(paul, theirs.id) {
            $0.moments[1]["title"] = "S’entraîner à deux"
            $0.moments[2].activities.append(added)
            $0.moments[0].activities[0].duration = 20
        }
        try await together(marie, mine.id, paul, theirs.id)
        try await together(marie, mine.id, paul, theirs.id)
        try await expectConverged(site, marie, mine.id, paul, theirs.id, remoteID) { scenario in
            let activity = scenario.moments[0].activities[0]
            #expect(activity["instructions"] == "Lire le document.\nRépondre aux questions." && activity.duration == 20)
            #expect(scenario.moments[1]["title"] == "S’entraîner à deux")
            #expect(scenario.moments[2].activities.last?.id == added.id)
        }
        #expect(marie.record(mine.id)?.local.meta["boardLayout"]?.string == "grid")
        #expect(paul.record(theirs.id)?.local.meta["boardLayout"]?.string != "grid")

        // Le même texte, modifié en même temps sur deux lignes différentes.
        try edit(marie, mine.id) { $0.moments[0].activities[0]["instructions"] = "Lire attentivement le document.\nRépondre aux questions." }
        try edit(paul, theirs.id) { $0.moments[0].activities[0]["instructions"] = "Lire le document.\nRépondre aux trois questions." }
        try await together(marie, mine.id, paul, theirs.id)
        try await together(marie, mine.id, paul, theirs.id)
        try await expectConverged(site, marie, mine.id, paul, theirs.id, remoteID) { scenario in
            #expect(scenario.moments[0].activities[0]["instructions"] == "Lire attentivement le document.\nRépondre aux trois questions.")
        }

        // Une activité déplacée par Marie pendant que Paul la modifie.
        try edit(marie, mine.id) { scenario in
            let moved = scenario.moments[0].activities.removeFirst()
            scenario.moments[2].activities.insert(moved, at: 0)
        }
        try edit(paul, theirs.id) { $0.moments[0].activities[0]["notes"] = "Prévoir des loupes." }
        try await together(marie, mine.id, paul, theirs.id)
        try await together(marie, mine.id, paul, theirs.id)
        try await expectConverged(site, marie, mine.id, paul, theirs.id, remoteID) { scenario in
            #expect(scenario.moments[0].activities.isEmpty)
            #expect(scenario.moments[2].activities.first?.id == activityID)
            #expect(scenario.moments[2].activities.first?["notes"] == "Prévoir des loupes.")
        }

        // Droits : Paul ne supprime pas, Léa n’a aucun accès.
        let revision = try #require(marie.record(mine.id)?.revision)
        await #expect(throws: SiteSyncError.forbidden) { try await paul.service.delete(remoteID, expectedRevision: revision) }
        await #expect(throws: SiteSyncError.missing) { _ = try await lea.download(remoteID) }
        await #expect(throws: SiteSyncError.missing) {
            _ = try await lea.presence(SitePresenceRequest(design_id: remoteID, client_id: UUID().uuidString.lowercased(), focus: nil))
        }
        var forged = try await marie.service.download(remoteID).document
        forged.meta["name"] = .string("Écrasé par Léa")
        await #expect(throws: SiteSyncError.missing) {
            _ = try await lea.save(SiteSaveRequest(client_uuid: UUID(), design_id: remoteID, expected_revision: revision,
                                                   title: "Écrasé", document: forged))
        }

        // Paul ferme sa fenêtre, puis quitte le partage : il perd l’accès et sa copie.
        _ = try await paul.presence(of: theirs.id, clientID: paulClient, focus: nil, leave: true)
        #expect(try await marie.presence(of: mine.id, clientID: marieClient, focus: nil)?.participants.isEmpty == true)
        try await paul.leave(remoteID, userID: site.users[1])
        #expect(paul.record(theirs.id) == nil)
        try await paul.refresh()
        #expect(paul.summary(for: remoteID) == nil)
        await #expect(throws: SiteSyncError.missing) { _ = try await paul.service.download(remoteID) }
        #expect(try await marie.sharing(remoteID).members.map(\.username) == ["Marie"])
        #expect(try await marie.presence(of: mine.id, clientID: marieClient, focus: nil)?.shared == false)

        // Marie invite Léa en consultation : elle lit le scénario sans pouvoir l’enregistrer.
        let viewing = try await marie.share(remoteID, email: "lea@example.test", role: .viewer)
        #expect(viewing.members.map(\.role) == ["owner", "viewer"])
        let read = try await lea.download(remoteID)
        #expect(read.id == remoteID && read.role == "viewer")
        #expect(try await lea.list().first { $0.id == remoteID }?.readOnly == true)
        await #expect(throws: SiteSyncError.forbidden) {
            _ = try await lea.save(SiteSaveRequest(client_uuid: UUID(), design_id: remoteID, expected_revision: read.revision,
                                                   title: "Écrasé", document: forged))
        }
        // Marie lui donne ensuite le droit de modifier.
        #expect(try await marie.setRole(remoteID, userID: site.users[2], role: .editor).members.last?.shareRole == .editor)
        #expect(try await lea.download(remoteID).role == "editor")

        // Marie retire Léa.
        #expect(try await marie.unshare(remoteID, userID: site.users[2])?.members.count == 1)
        await #expect(throws: SiteSyncError.missing) { _ = try await lea.download(remoteID) }

        // Marie supprime le scénario.
        try await marie.delete(mine.id)
        await #expect(throws: SiteSyncError.missing) { _ = try await marie.service.download(remoteID) }
    }

    private func edit(_ workspace: SiteSyncWorkspace, _ id: UUID, _ change: (inout Scenario) -> Void) throws {
        let current = try #require(workspace.record(id)?.local)
        var next = current
        change(&next)
        try workspace.stage(SyncDocumentIdentity(id: id, accountKey: workspace.account.key), scenario: next, previous: current)
    }

    /// Les deux enseignants envoient leurs modifications au même moment.
    private func together(_ a: SiteSyncWorkspace, _ first: UUID, _ b: SiteSyncWorkspace, _ second: UUID) async throws {
        async let x: Void = a.synchronize(first)
        async let y: Void = b.synchronize(second)
        _ = try await (x, y)
    }

    private func expectConverged(_ site: LiveSite, _ marie: SiteSyncWorkspace, _ mine: UUID, _ paul: SiteSyncWorkspace,
                                 _ theirs: UUID, _ remoteID: Int, _ check: (Scenario) -> Void) async throws {
        let remote = try await marie.service.download(remoteID).document
        for (workspace, id) in [(marie, mine), (paul, theirs)] {
            let record = try #require(workspace.record(id))
            #expect(record.status == .synced)
            #expect(ScenarioMerge.sameContent(record.local, remote))
            check(record.local)
        }
        check(remote)
        _ = try Scenario.decode(remote.encoded())
    }
}

/// Serveur PHP local, sa base jetable et les jetons de trois comptes vérifiés.
@MainActor
final class LiveSite {
    let users: [Int]
    private let tokens: [String]
    private let process: Process
    private let directory: URL
    private let port: Int

    private init(users: [Int], tokens: [String], process: Process, directory: URL, port: Int) {
        self.users = users; self.tokens = tokens; self.process = process; self.directory = directory; self.port = port
    }

    static func start() async throws -> LiveSite {
        let environment = ProcessInfo.processInfo.environment
        let site = try #require(environment["SCENARISATION_SITE_DIR"])
        let php = environment["SCENARISATION_PHP"]
            ?? ["/opt/homebrew/bin/php", "/usr/local/bin/php", "/usr/bin/php"].first { FileManager.default.isExecutableFile(atPath: $0) }
        let binary = try #require(php, "PHP introuvable")
        let directory = FileManager.default.temporaryDirectory.appendingPathComponent("scenarisation-live-" + UUID().uuidString)
        try FileManager.default.createDirectory(at: directory.appendingPathComponent("sessions"), withIntermediateDirectories: true)
        let port = Int.random(in: 41_000...48_999)
        var variables = environment
        variables["APP_DB_DSN"] = environment["SCENARISATION_SITE_DSN"] ?? "sqlite:" + directory.appendingPathComponent("site.sqlite").path
        variables["APP_DB_USER"] = environment["SCENARISATION_SITE_DB_USER"] ?? ""
        variables["APP_DB_PASS"] = environment["SCENARISATION_SITE_DB_PASS"] ?? ""
        variables["APP_BASE_URL"] = "http://127.0.0.1:\(port)"
        variables["SITE_DIR"] = site

        // Comptes et jetons créés par le code OAuth du site, comme après un consentement dans le navigateur.
        let setup = directory.appendingPathComponent("setup.php")
        try Self.setupScript.write(to: setup, atomically: true, encoding: .utf8)
        let output = try run(binary, ["-d", "session.save_path=" + directory.appendingPathComponent("sessions").path, setup.path],
                             environment: variables, directory: site)
        struct Accounts: Decodable { let users: [Int]; let tokens: [String] }
        let accounts = try JSONDecoder().decode(Accounts.self, from: Data(output.utf8))

        let server = Process()
        server.executableURL = URL(fileURLWithPath: binary)
        server.arguments = ["-d", "display_errors=0", "-d", "session.save_path=" + directory.appendingPathComponent("sessions").path,
                            "-S", "127.0.0.1:\(port)", "-t", site]
        server.environment = variables
        server.currentDirectoryURL = URL(fileURLWithPath: site)
        server.standardOutput = FileHandle(forWritingAtPath: "/dev/null")
        server.standardError = FileHandle(forWritingAtPath: "/dev/null")
        try server.run()
        LocalSiteForwarder.port = port
        let live = LiveSite(users: accounts.users, tokens: accounts.tokens, process: server, directory: directory, port: port)
        // Le serveur répond 401 sans jeton dès qu’il est prêt.
        for _ in 0..<100 {
            if let (_, response) = try? await URLSession.shared.data(from: URL(string: "http://127.0.0.1:\(port)/api/v1/me.php")!),
               (response as? HTTPURLResponse)?.statusCode == 401 { return live }
            try await Task.sleep(for: .milliseconds(50))
        }
        live.stop()
        throw ScenarioError.invalid("Le serveur PHP local n’a pas démarré.")
    }

    func api(_ index: Int) throws -> SiteSyncAPI {
        let configuration = URLSessionConfiguration.ephemeral
        configuration.protocolClasses = [LocalSiteForwarder.self]
        let token = tokens[index]
        return try SiteSyncAPI(baseURL: URL(string: "https://scenarisation.test/api/v1/")!, configuration: configuration,
                               credential: { token })
    }

    func workspace(_ index: Int) throws -> SiteSyncWorkspace {
        try SiteSyncWorkspace(account: SiteSyncAccount(key: "live-\(users[index])", label: "Compte \(users[index])"),
                              service: api(index))
    }

    func stop() {
        if process.isRunning { process.terminate(); process.waitUntilExit() }
        try? FileManager.default.removeItem(at: directory)
    }

    private static func run(_ binary: String, _ arguments: [String], environment: [String: String], directory: String) throws -> String {
        let process = Process()
        process.executableURL = URL(fileURLWithPath: binary)
        process.arguments = arguments
        process.environment = environment
        process.currentDirectoryURL = URL(fileURLWithPath: directory)
        let pipe = Pipe(), errors = Pipe()
        process.standardOutput = pipe; process.standardError = errors
        try process.run()
        let data = pipe.fileHandleForReading.readDataToEndOfFile()
        let problems = errors.fileHandleForReading.readDataToEndOfFile()
        process.waitUntilExit()
        guard process.terminationStatus == 0 else {
            throw ScenarioError.invalid("Préparation du site : " + String(decoding: data + problems, as: UTF8.self))
        }
        return String(decoding: data, as: UTF8.self)
    }

    private static let setupScript = #"""
    <?php
    declare(strict_types=1);
    $_SERVER['HTTPS'] = 'on';
    require_once getenv('SITE_DIR') . '/lib/native-api.php';
    $db = app_db();
    if ((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 0) {
        fwrite(STDERR, "La base de test doit être vide.\n"); exit(1);
    }
    $insert = $db->prepare("INSERT INTO users (username, email, password_hash, role, status, email_verified_at)
        VALUES (?, ?, 'test', 'designer', 'active', CURRENT_TIMESTAMP)");
    $users = []; $tokens = [];
    foreach ([['Marie', 'marie@example.test'], ['Paul', 'paul@example.test'], ['Léa', 'lea@example.test']] as $account) {
        $insert->execute($account);
        $id = (int)$db->lastInsertId();
        $request = native_validate_authorization([
            'client_id' => NATIVE_CLIENT_ID, 'response_type' => 'code', 'redirect_uri' => 'http://127.0.0.1:54123/auth/callback',
            'code_challenge_method' => 'S256', 'code_challenge' => native_pkce(str_repeat('v', 64)),
            'state' => str_repeat('s', 32), 'scope' => 'designs:read designs:write',
        ]);
        $grant = native_token_grant($db, [
            'client_id' => NATIVE_CLIENT_ID, 'grant_type' => 'authorization_code', 'code' => native_issue_code($db, $id, $request),
            'code_verifier' => str_repeat('v', 64), 'redirect_uri' => $request['redirect_uri'],
        ]);
        $users[] = $id; $tokens[] = $grant['access_token'];
    }
    echo json_encode(['users' => $users, 'tokens' => $tokens]);
    """#
}

/// Redirige les requêtes HTTPS de l’app vers le serveur PHP local, sans toucher au client testé.
final class LocalSiteForwarder: URLProtocol, @unchecked Sendable {
    nonisolated(unsafe) static var port = 0
    private var forwarding: URLSessionDataTask?

    override class func canInit(with request: URLRequest) -> Bool { request.url?.host == "scenarisation.test" }
    override class func canonicalRequest(for request: URLRequest) -> URLRequest { request }

    override func startLoading() {
        var components = URLComponents(url: request.url!, resolvingAgainstBaseURL: false)!
        components.scheme = "http"; components.host = "127.0.0.1"; components.port = Self.port
        var forwarded = URLRequest(url: components.url!)
        forwarded.httpMethod = request.httpMethod
        forwarded.allHTTPHeaderFields = request.allHTTPHeaderFields
        forwarded.httpBody = request.httpBody ?? Self.read(request.httpBodyStream)
        let original = request.url!
        forwarding = URLSession(configuration: .ephemeral).dataTask(with: forwarded) { [weak self] data, response, error in
            guard let self else { return }
            if let error { client?.urlProtocol(self, didFailWithError: error); return }
            guard let http = response as? HTTPURLResponse else { client?.urlProtocol(self, didFailWithError: URLError(.badServerResponse)); return }
            let headers = http.allHeaderFields.reduce(into: [String: String]()) { $0["\($1.key)"] = "\($1.value)" }
            let reply = HTTPURLResponse(url: original, statusCode: http.statusCode, httpVersion: "HTTP/1.1", headerFields: headers)!
            client?.urlProtocol(self, didReceive: reply, cacheStoragePolicy: .notAllowed)
            if let data { client?.urlProtocol(self, didLoad: data) }
            client?.urlProtocolDidFinishLoading(self)
        }
        forwarding?.resume()
    }

    override func stopLoading() { forwarding?.cancel() }

    private static func read(_ stream: InputStream?) -> Data? {
        guard let stream else { return nil }
        stream.open(); defer { stream.close() }
        var data = Data(), buffer = [UInt8](repeating: 0, count: 16_384)
        while stream.hasBytesAvailable {
            let count = stream.read(&buffer, maxLength: buffer.count)
            if count <= 0 { break }
            data.append(contentsOf: buffer.prefix(count))
        }
        return data
    }
}
