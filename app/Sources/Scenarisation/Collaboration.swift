import SwiftUI
import Observation

/// Présence des autres enseignants dans un scénario partagé.
///
/// Chaque fenêtre se signale au site toutes les deux secondes lorsque le scénario est partagé
/// (toutes les douze secondes sinon, pour découvrir un partage), avec l’élément sur lequel elle
/// travaille. La réponse donne les autres participants et la révision du site : une révision plus
/// récente déclenche aussitôt la synchronisation, qui fusionne les modifications de chacun.
/// L’hébergement du site ne permet pas de connexion permanente : ces échanges courts en tiennent lieu.
@MainActor @Observable
final class CollaborationSession {
    let clientID = UUID().uuidString.lowercased()
    private(set) var participants: [SiteParticipant] = []
    private(set) var shared = false
    /// Champ ouvert dans l’éditeur agrandi (`activity:<id>:<champ>`…).
    var editingField: String? { didSet { if editingField != oldValue { wake() } } }
    @ObservationIgnored private var recent: (focus: String, date: Date)?
    @ObservationIgnored private var wakeRequested = false

    /// Ce que cette fenêtre signale aux autres : le champ ouvert, sinon la dernière modification (20 s).
    var focus: String? {
        if let editingField { return editingField }
        guard let recent, Date().timeIntervalSince(recent.date) < 20 else { return nil }
        return recent.focus
    }

    /// Un participant par enseignant, même s’il a ouvert le scénario sur plusieurs appareils.
    var people: [SiteParticipant] {
        var seen = Set<Int>()
        return participants.filter { seen.insert($0.userId).inserted }
    }

    func participants(on item: String) -> [SiteParticipant] { participants.filter { $0.item == item } }
    func participants(editing field: String) -> [SiteParticipant] { participants.filter { $0.focus == field } }

    func noteLocalChange(from old: Scenario, to new: Scenario) {
        guard let focus = ScenarioMerge.changedFocus(from: old, to: new) else { return }
        let moved = recent?.focus != focus
        recent = (focus, Date())
        if moved && editingField == nil { wake() }
    }

    /// Captures d’écran (`--collaboration-demo`) : deux collègues fictifs, sans connexion au site.
    static let demo = ProcessInfo.processInfo.arguments.contains("--collaboration-demo")
    func showDemo(_ scenario: Scenario) {
        let activity = scenario.moments.flatMap(\.activities).first
        participants = [
            SiteParticipant(userId: 2, username: "Marie", clientId: UUID().uuidString.lowercased(),
                            focus: activity.map { "activity:\($0.id):instructions" }),
            SiteParticipant(userId: 3, username: "Paul", clientId: UUID().uuidString.lowercased(),
                            focus: scenario.moments.dropFirst().first.map { "moment:\($0.id):objectives" }),
        ]
        shared = true
    }

    /// Appelé après une invitation : la présence passe aussitôt au rythme rapide.
    func markShared() {
        guard !shared else { return }
        shared = true; wake()
    }

    func run(workspace: SiteSyncWorkspace, recordID: UUID) async {
        var failures = 0
        while !Task.isCancelled {
            var delay: Double = shared ? 2 : 12
            if SiteAccountModel.shared.networkAvailable {
                do {
                    if let presence = try await workspace.presence(of: recordID, clientID: clientID, focus: focus) {
                        let others = presence.participants.filter { $0.clientId != clientID }
                        if others != participants { participants = others }
                        let isShared = presence.shared || !others.isEmpty
                        if isShared != shared { shared = isShared }
                        failures = 0
                        delay = shared ? 2 : 12
                        if let record = workspace.record(recordID), presence.revision > (record.revision ?? 0) {
                            Task { await workspace.synchronizeAutomatically(recordID) }
                        }
                    }
                } catch {
                    // Site pas encore mis à jour, coupure ou session expirée : on réessaie de plus en plus tard.
                    failures += 1
                    if !participants.isEmpty { participants = [] }
                    delay = min(60, 5 * Double(failures))
                }
            }
            await pause(delay)
        }
        if !participants.isEmpty { participants = [] }
        // Les autres participants voient aussitôt cette fenêtre partir, sans attendre l’expiration de sa présence.
        if shared {
            let clientID = clientID
            Task { _ = try? await workspace.presence(of: recordID, clientID: clientID, focus: nil, leave: true) }
        }
    }

    private func wake() { if shared { wakeRequested = true } }

    private func pause(_ seconds: Double) async {
        var remaining = seconds
        while remaining > 0, !wakeRequested, !Task.isCancelled {
            try? await Task.sleep(for: .milliseconds(250))
            remaining -= 0.25
        }
        wakeRequested = false
    }
}

extension EnvironmentValues {
    /// Scénario partagé avec ce compte en consultation seule : les vues d’édition se désactivent.
    @Entry var scenarioReadOnly = false
}

extension SiteParticipant {
    var color: Color { CollaborationStyle.color(for: userId) }
    var initial: String { username.first.map { String($0).uppercased() } ?? "?" }
    /// « Modifie les consignes d’une activité », pour les infobulles et VoiceOver.
    var activityDescription: String {
        guard let focus else { return "Consulte le scénario" }
        let parts = focus.split(separator: ":", maxSplits: 2, omittingEmptySubsequences: false).map(String.init)
        let field = parts.count == 3 ? CollaborationStyle.fieldNames[parts[2]] : nil
        switch parts.first {
        case "activity": return "Modifie " + (field.map { "\($0) d’une activité" } ?? "une activité")
        case "moment": return "Modifie " + (field.map { "\($0) d’un moment" } ?? "un moment")
        default: return "Modifie " + (field ?? "les paramètres du scénario")
        }
    }
}

enum CollaborationStyle {
    static let palette: [Color] = [.orange, .pink, .purple, .teal, .green, .indigo, .red, .mint]
    static func color(for userID: Int) -> Color { palette[abs(userID) % palette.count] }
    static let fieldNames = [
        "description": "la description", "instructions": "les consignes", "notes": "les notes", "objectives": "les objectifs",
        "intentions": "les intentions", "title": "le titre", "duration": "la durée", "type": "le type", "tools": "les compétences",
        "name": "le titre", "command": "la commande institutionnelle", "personas": "les objectifs de la formation",
        "teacherMaterials": "le matériel enseignant",
    ]
    static func names(_ participants: [SiteParticipant]) -> String {
        var seen = Set<Int>()
        let names = participants.filter { seen.insert($0.userId).inserted }.map(\.username)
        return names.count <= 1 ? names.first ?? "" : names.dropLast().joined(separator: ", ") + " et " + names.last!
    }
}

struct ParticipantAvatar: View {
    let participant: SiteParticipant
    var size: CGFloat = 22
    var body: some View {
        Text(participant.initial)
            .font(.system(size: size * 0.5, weight: .semibold, design: .rounded)).foregroundStyle(.white)
            .frame(width: size, height: size)
            .background(participant.color, in: Circle())
            .overlay(Circle().strokeBorder(StudioStyle.surface, lineWidth: 1.5))
            .accessibilityHidden(true)
    }
}

/// Bouton de partage de la barre d’outils : les collègues présents, sinon « Partager ».
/// Vue distincte, pour que l’arrivée d’un collègue ne réévalue que ce bouton.
struct CollaborationToolbarButton: View {
    let session: CollaborationSession
    let action: () -> Void
    var body: some View {
        Button(action: action) { CollaborationToolbarLabel(session: session) }
            .help(CollaborationToolbarLabel.help(session))
    }
}

struct CollaborationToolbarLabel: View {
    let session: CollaborationSession
    var body: some View {
        let people = session.people
        if people.isEmpty {
            Label("Partager", systemImage: session.shared ? "person.2.fill" : "person.badge.plus")
        } else {
            HStack(spacing: -6) {
                ForEach(people.prefix(3)) { ParticipantAvatar(participant: $0, size: 22) }
                if people.count > 3 {
                    Text("+\(people.count - 3)").font(.caption2.weight(.semibold)).padding(.leading, 9)
                }
            }
            .accessibilityElement(children: .ignore)
            .accessibilityLabel("Partager. Présents : \(CollaborationStyle.names(people))")
        }
    }
    static func help(_ session: CollaborationSession) -> String {
        let people = session.people
        guard !people.isEmpty else {
            return session.shared ? "Scénario partagé · gérer les enseignants invités" : "Partager ce scénario avec des collègues"
        }
        return people.map { "\($0.username) · \($0.activityDescription.lowercased())" }.joined(separator: "\n")
    }
}

/// Contour coloré et prénom d’un collègue qui travaille sur cette carte.
struct PresenceOutline: View {
    let item: String
    var cornerRadius: CGFloat = 14
    @Environment(CollaborationSession.self) private var session: CollaborationSession?
    var body: some View {
        let others = session?.participants(on: item) ?? []
        ZStack(alignment: .topTrailing) {
            if let first = others.first {
                RoundedRectangle(cornerRadius: cornerRadius, style: .continuous).strokeBorder(first.color, lineWidth: 2)
                PresenceNames(participants: others).offset(x: -12, y: -9)
            }
        }
        .allowsHitTesting(false)
        .animation(.easeOut(duration: 0.2), value: others.map(\.id))
    }
}

struct PresenceNames: View {
    let participants: [SiteParticipant]
    var body: some View {
        HStack(spacing: 4) {
            ForEach(Self.unique(participants)) { participant in
                Text(participant.username).font(.caption2.weight(.semibold)).foregroundStyle(.white).lineLimit(1)
                    .padding(.horizontal, 7).padding(.vertical, 2)
                    .background(participant.color, in: Capsule())
            }
        }
        .accessibilityElement(children: .ignore)
        .accessibilityLabel("En cours de modification par \(CollaborationStyle.names(participants))")
    }
    static func unique(_ participants: [SiteParticipant]) -> [SiteParticipant] {
        var seen = Set<Int>()
        return participants.filter { seen.insert($0.userId).inserted }
    }
}

/// Pastille à côté du libellé d’un champ qu’un collègue est en train de modifier.
struct FieldPresence: View {
    let field: String
    @Environment(CollaborationSession.self) private var session: CollaborationSession?
    var body: some View {
        let others = session?.participants(editing: field) ?? []
        if let first = others.first {
            HStack(spacing: 4) {
                Circle().fill(first.color).frame(width: 7, height: 7)
                Text("\(CollaborationStyle.names(others)) écrit…").font(.caption).foregroundStyle(first.color).lineLimit(1)
            }
            .accessibilityElement(children: .combine)
        }
    }
}

/// Bandeau de l’éditeur agrandi lorsqu’un collègue modifie le même texte.
struct CollaborationFieldBanner: View {
    let field: String
    @Environment(CollaborationSession.self) private var session: CollaborationSession?
    var body: some View {
        let others = session?.participants(editing: field) ?? []
        if let first = others.first {
            HStack(alignment: .top, spacing: 10) {
                HStack(spacing: -6) { ForEach(PresenceNames.unique(others)) { ParticipantAvatar(participant: $0, size: 22) } }
                Text("\(CollaborationStyle.names(others)) \(PresenceNames.unique(others).count > 1 ? "modifient" : "modifie") aussi ce texte. Les modifications de chacun sont fusionnées\u{00A0}; sur une même phrase, la dernière enregistrée l’emporte.")
                    .font(.callout).fixedSize(horizontal: false, vertical: true)
            }
            .padding(10).frame(maxWidth: .infinity, alignment: .leading)
            .background(first.color.opacity(0.12), in: RoundedRectangle(cornerRadius: 10, style: .continuous))
        }
    }
}

/// Bandeau d’un scénario partagé avec ce compte en consultation seule.
struct ReadOnlyBanner: View {
    var body: some View {
        HStack(spacing: 10) {
            Image(systemName: "eye").foregroundStyle(Color.accentColor).accessibilityHidden(true)
            Text("\(Text("Consultation seule.").fontWeight(.semibold)) Ce scénario vous est partagé en lecture : vous pouvez le parcourir et l’exporter, mais pas le modifier.")
                .font(.callout).fixedSize(horizontal: false, vertical: true)
            Spacer(minLength: 0)
        }
        .padding(.horizontal, 28).padding(.vertical, 9)
        .background(Color.accentColor.opacity(0.08))
        .overlay(alignment: .bottom) { Divider() }
        .accessibilityElement(children: .combine)
    }
}

// MARK: Partage

struct ScenarioShareSheet: View {
    @Binding var document: ScenarioDocument
    @ObservedObject var workspace: SiteSyncWorkspace
    @ObservedObject var accountModel: SiteAccountModel = .shared
    let session: CollaborationSession
    @Environment(\.dismiss) private var dismiss
    @State private var sharing: SiteSharing?
    @State private var email = ""
    @State private var inviteRole: SiteShareRole = .editor
    @State private var loading = false
    @State private var busy = false
    @State private var message: String?
    @State private var removing: SiteMember?
    @State private var confirmLeave = false

    private var record: SiteSyncRecord? {
        document.syncIdentity.flatMap { $0.accountKey == workspace.account.key ? workspace.record($0.id) : nil }
    }
    private var remoteID: Int? { record.flatMap { $0.remoteMissing ? nil : $0.remoteID } }
    private var me: Int? { accountModel.user?.id }
    private var validEmail: Bool {
        let value = email.trimmingCharacters(in: .whitespacesAndNewlines)
        return value.count <= 190 && value.firstMatch(of: /^[^@\s]+@[^@\s]+\.[^@\s]+$/) != nil
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 20) {
            HStack(alignment: .firstTextBaseline) {
                VStack(alignment: .leading, spacing: 4) {
                    Text("Partager le scénario").font(.title2.bold())
                    Text(document.scenario.name.isEmpty ? "Sans titre" : document.scenario.name).foregroundStyle(.secondary).lineLimit(1)
                }
                Spacer()
                Button("Fermer") { dismiss() }.keyboardShortcut(.cancelAction)
            }
            if remoteID == nil {
                Text("Ce scénario doit d’abord rejoindre votre compte. Synchronisez-le, puis revenez ici pour inviter des collègues.")
                    .foregroundStyle(.secondary).fixedSize(horizontal: false, vertical: true)
            } else if !accountModel.networkAvailable {
                Text("Reconnectez votre compte pour gérer le partage.").foregroundStyle(.secondary)
            } else if let sharing {
                members(sharing)
                if sharing.canManage { invite } else { leaveButton(sharing) }
            } else if loading {
                HStack { ProgressView().controlSize(.small); Text("Chargement…").foregroundStyle(.secondary) }
            }
            if let message {
                Text(message).font(.callout).foregroundStyle(.red).textSelection(.enabled).fixedSize(horizontal: false, vertical: true)
            }
            Divider()
            Text("Les enseignants invités doivent avoir un compte vérifié sur scenarisation.eu. Ils retrouvent le scénario dans **Mon compte et mes scénarios**, sur Mac ou sur iPad. Les modifications de chacun apparaissent chez les autres en quelques secondes et sont fusionnées champ par champ. Le site web proposera cette collaboration plus tard.")
                .font(.caption).foregroundStyle(.secondary).fixedSize(horizontal: false, vertical: true)
        }
        .padding(28).desktopFrame(minWidth: 520, maxWidth: 680)
        .disabled(busy)
        .clearsInitialFocus()
        .task(id: remoteID) { await load() }
        .confirmationDialog("Retirer cet enseignant du partage ?", isPresented: Binding(
            get: { removing != nil }, set: { if !$0 { removing = nil } }), presenting: removing) { member in
                Button("Retirer", role: .destructive) { remove(member) }
            } message: { member in Text("\(member.username) ne pourra plus ouvrir ce scénario.") }
        .confirmationDialog("Quitter ce scénario partagé ?", isPresented: $confirmLeave) {
            Button("Quitter le partage", role: .destructive, action: leave)
        } message: {
            Text("Il disparaîtra de votre compte et de \(Platform.thisDevice). Les modifications pas encore envoyées seront perdues ; le propriétaire garde le scénario.")
        }
    }

    private func members(_ sharing: SiteSharing) -> some View {
        VStack(alignment: .leading, spacing: 10) {
            Text("Enseignants").font(.headline)
            ForEach(sharing.members) { member in
                HStack(spacing: 12) {
                    Text(member.username.first.map { String($0).uppercased() } ?? "?")
                        .font(.system(size: 14, weight: .semibold, design: .rounded)).foregroundStyle(.white)
                        .frame(width: 30, height: 30).background(CollaborationStyle.color(for: member.id), in: Circle())
                        .accessibilityHidden(true)
                    VStack(alignment: .leading, spacing: 1) {
                        HStack(spacing: 6) {
                            Text(member.username + (member.id == me ? " (vous)" : "")).fontWeight(.medium).lineLimit(1)
                            if session.participants.contains(where: { $0.userId == member.id }) {
                                Label("présent", systemImage: "circle.fill").labelStyle(.titleAndIcon)
                                    .font(.caption).foregroundStyle(.green).imageScale(.small)
                            }
                        }
                        if let email = member.email { Text(email).font(.caption).foregroundStyle(.secondary).lineLimit(1) }
                    }
                    Spacer(minLength: 12)
                    if member.isOwner {
                        Text("Propriétaire").font(.callout).foregroundStyle(.secondary)
                    } else if sharing.canManage {
                        RoleMenu(role: Binding(get: { member.shareRole }, set: { changeRole(member, $0) }))
                            .help("Droits de \(member.username) sur ce scénario")
                            .accessibilityLabel("Droits de \(member.username)")
                    } else {
                        Text(member.shareRole.label).font(.callout).foregroundStyle(.secondary)
                    }
                    if sharing.canManage && !member.isOwner {
                        Button(role: .destructive) { removing = member } label: { Label("Retirer", systemImage: "xmark.circle") }
                            .labelStyle(.iconOnly).buttonStyle(.borderless).help("Retirer \(member.username) du partage")
                    }
                }
            }
            if sharing.members.count == 1 {
                Text("Personne d’autre n’a encore accès à ce scénario.").font(.callout).foregroundStyle(.secondary)
            }
        }
    }

    private var invite: some View {
        VStack(alignment: .leading, spacing: 8) {
            Text("Inviter un enseignant").font(.headline)
            HStack(spacing: 10) {
                TextField("Adresse e-mail de son compte", text: $email)
                    .textFieldStyle(.roundedBorder).autocorrectionDisabled()
#if os(iOS)
                    .textContentType(.emailAddress).keyboardType(.emailAddress).textInputAutocapitalization(.never)
#endif
                    .onSubmit { if validEmail { invite(email) } }
                RoleMenu(role: $inviteRole).help("Droits de l’enseignant invité")
                if busy { ProgressView().controlSize(.small) }
                Button("Inviter") { invite(email) }.studioButtons(prominent: true).disabled(!validEmail)
            }
            Text(inviteRole == .editor
                 ? "L’enseignant pourra modifier le scénario avec vous. Vous seul pouvez inviter, changer les droits, retirer ou supprimer."
                 : "L’enseignant pourra parcourir et exporter le scénario, sans le modifier. Vous seul pouvez inviter, changer les droits, retirer ou supprimer.")
                .font(.caption).foregroundStyle(.secondary).fixedSize(horizontal: false, vertical: true)
        }
    }

    private func leaveButton(_ sharing: SiteSharing) -> some View {
        let role = sharing.members.first { $0.id == me }?.shareRole
        return HStack {
            Text(role == .viewer ? "Scénario partagé avec vous en consultation par son propriétaire."
                                 : "Scénario partagé avec vous par son propriétaire.")
                .font(.callout).foregroundStyle(.secondary).fixedSize(horizontal: false, vertical: true)
            Spacer()
            Button("Quitter le partage…", role: .destructive) { confirmLeave = true }
        }
    }

    private func load() async {
        guard let remoteID, accountModel.networkAvailable else { return }
        loading = true; defer { loading = false }
        do { sharing = try await workspace.sharing(remoteID); message = nil }
        catch is CancellationError {}
        catch { message = describe(error) }
    }

    private func invite(_ address: String) {
        guard let remoteID else { return }
        let value = address.trimmingCharacters(in: .whitespacesAndNewlines)
        busy = true; message = nil
        Task {
            defer { busy = false }
            do {
                sharing = try await workspace.share(remoteID, email: value, role: inviteRole)
                email = ""
                session.markShared()
            } catch { message = describe(error) }
        }
    }

    private func changeRole(_ member: SiteMember, _ role: SiteShareRole) {
        guard let remoteID, role != member.shareRole else { return }
        busy = true; message = nil
        Task {
            defer { busy = false }
            do { sharing = try await workspace.setRole(remoteID, userID: member.id, role: role) }
            catch { message = describe(error) }
        }
    }

    private func remove(_ member: SiteMember) {
        guard let remoteID else { return }
        busy = true; message = nil
        Task {
            defer { busy = false }
            do { sharing = try await workspace.unshare(remoteID, userID: member.id) }
            catch { message = describe(error) }
        }
    }

    private func leave() {
        guard let remoteID, let me else { return }
        busy = true; message = nil
        Task {
            defer { busy = false }
            do {
                try await workspace.leave(remoteID, userID: me)
                dismiss()
            } catch { message = describe(error) }
        }
    }

    private func describe(_ error: Error) -> String {
        if case SiteSyncError.missing = error {
            return "Ce scénario n’est pas disponible pour le partage, ou le site n’a pas encore été mis à jour."
        }
        return error.localizedDescription
    }
}

/// « Peut modifier ▾ » / « Peut consulter ▾ » : droits d’un enseignant, choisis dans un menu discret.
private struct RoleMenu: View {
    @Binding var role: SiteShareRole
    var body: some View {
        Menu {
            Picker("Droits", selection: $role) {
                ForEach(SiteShareRole.allCases) { Text($0.label).tag($0) }
            }.pickerStyle(.inline).labelsHidden()
        } label: {
            Text(role.label).font(.callout)
        }
        .menuStyle(.button).buttonStyle(.borderless).menuIndicator(.visible).fixedSize()
    }
}

/// Garde le curseur au même endroit du texte lorsqu’un collègue modifie le champ ouvert.
enum TextSelectionShift {
    static func adjusted(_ selection: NSRange, from old: String, to new: String) -> NSRange {
        let a = old as NSString, b = new as NSString
        let shortest = min(a.length, b.length)
        var prefix = 0
        while prefix < shortest && a.character(at: prefix) == b.character(at: prefix) { prefix += 1 }
        var suffix = 0
        while suffix < shortest - prefix && a.character(at: a.length - 1 - suffix) == b.character(at: b.length - 1 - suffix) { suffix += 1 }
        let oldEnd = a.length - suffix, newEnd = b.length - suffix
        func map(_ location: Int) -> Int {
            if location <= prefix { return min(location, b.length) }
            if location >= oldEnd { return min(b.length, max(0, location + b.length - a.length)) }
            return newEnd   // dans le passage remplacé : après le nouveau texte
        }
        let start = map(selection.location), end = max(start, map(selection.location + selection.length))
        return NSRange(location: start, length: end - start)
    }
}
