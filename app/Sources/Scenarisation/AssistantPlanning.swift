import Foundation

/// The discovery questions used by the CLI workflow in the bundled guide.
enum AssistantPlanning {
    static let requiredQuestions = [
        "Sujet ou thème de la séance/séquence",
        "Système scolaire ou classification, puis niveau et public cible",
        "Durée totale",
        "Modalité : présentiel, distanciel ou hybride",
        "Taille du groupe",
        "Objectifs d’enseignement : ce que l’enseignant veut faire travailler, transmettre ou entraîner",
        "Acquis d’apprentissage attendus : ce que les élèves devront être capables de faire à la fin",
        "Contraintes matérielles, pédagogiques ou institutionnelles",
        "Niveau de détail souhaité",
        "Référentiel ou référentiels de compétences lorsque des compétences sont mobilisées"
    ]
    static let optionalQuestions = [
        "Niveau Bloom souhaité pour chaque acquis, si l’enseignant le connaît",
        "Compétences à mobiliser, si elles sont déjà connues",
        "Supports, œuvres, ressources ou outils imposés"
    ]
    static let welcome = """
    Créons votre scénario ensemble. Pour commencer :

    • Quel sujet ou thème souhaitez-vous travailler ?
    • Pour quel public, niveau et système scolaire ?
    • De combien de temps disposez-vous ?
    """
    /// Avec un contexte enregistré, seules les informations propres au nouveau scénario sont demandées.
    static let welcomeWithProfile = """
    Créons votre scénario ensemble. Je tiens compte de votre contexte d’enseignement enregistré dans les réglages. Pour commencer :

    • Quel sujet ou thème souhaitez-vous travailler ?
    • De combien de temps disposez-vous ?
    • Le public ou le niveau diffère-t-il de votre contexte habituel ?
    """
    static func welcome(profile: String) -> String {
        profile.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty ? welcome : welcomeWithProfile
    }
    static let instructions = """
    Pour créer un scénario, reprends le cadrage du parcours CLI de Scénarisation.
    Questions indispensables à couvrir au fil de l’échange, sans reposer celles auxquelles le scénario ou l’enseignant répond déjà :
    \(requiredQuestions.map { "- " + $0 }.joined(separator: "\n"))
    Questions complémentaires, seulement si elles sont utiles :
    \(optionalQuestions.map { "- " + $0 }.joined(separator: "\n"))
    Pose ces questions par groupes de une à trois, sans surcharger l’enseignant. S’il a déjà décrit son projet, exploite cette description.
    Distingue objectifs d’enseignement et acquis observables. Si seuls les objectifs sont donnés, propose des acquis de Bloom adaptés plutôt que d’exiger leur rédaction par l’enseignant.
    En cas d’information manquante, formule des hypothèses raisonnables et explicites plutôt que de bloquer ; demande les précisions qui changeraient réellement la conception.
    Si des compétences sont pertinentes, demande le référentiel et consulte ses libellés avant de retenir les codes. Ne suppose jamais Florimont par défaut.
    Propose au besoin un choix court adapté au contexte : CRCN/Pix ou Socle commun en France, Pix IA pour la culture de l’IA, DigComp en Europe, GreenComp pour la durabilité, PER romand en Suisse romande, Florimont pour sa progression interne.
    Pour la Belgique, distingue les communautés ; pour le Royaume-Uni, distingue les nations. Les Écoles européennes sont transnationales et ISCED 2011 est une classification internationale. Clarifie un système ambigu et consulte les catalogues.
    Si la durée est donnée en jours, demande ou propose explicitement une durée par séance avant de générer. Au collège, propose par défaut une séance de 55 minutes par jour, sauf indication contraire, et annonce cette hypothèse.
    Convertis cette durée en minutes dans les métadonnées ; ne confonds pas un jour de formation et la valeur technique dayHours du document.
    Avant une proposition complète, résume brièvement le sujet, le public/système/niveau, la durée en minutes, les moments envisagés, les objectifs, les acquis et les référentiels retenus. Ce résumé peut accompagner la proposition ; n’impose pas une confirmation supplémentaire si la demande est claire.
    Pour une simple adaptation d’un scénario existant, limite le cadrage aux informations utiles à cette adaptation.
    """
}
