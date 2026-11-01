# Préférences de collaboration

- Dans la réponse finale après toute modification de fichiers, lister systématiquement les fichiers modifiés au cours de la tâche, avec un lien cliquable vers chacun.
- Ne pas inclure dans cette liste les modifications préexistantes qui n'ont pas été effectuées au cours de la tâche.

# Préférences visuelles

- Tous les styles doivent être placés dans des fichiers du dossier `css/`. Ne pas ajouter de blocs `<style>` ni d’attributs `style` dans les fichiers PHP ou HTML. Lors d’une modification de styles intégrés existants, les déplacer dans un fichier CSS et charger celui-ci depuis la page.
- Ne jamais ajouter de fond bleuté dans l’interface, y compris sur les pages, cartes, encarts, liens ou boutons secondaires et leurs états de survol ou de focus. Utiliser des arrière-plans neutres adaptés au thème clair ou sombre. Cette préférence est impérative, sauf demande explicite contraire de l’utilisateur.
- Pour les encarts d’icônes, utiliser des icônes noires sur fond blanc ou gris neutre en mode clair, et des icônes blanches sur fond noir en mode sombre.
- Éviter les cadres imbriqués (« cadre dans un cadre »). Limiter les bordures aux conteneurs structurels ou interactifs qui en ont réellement besoin et organiser les sous-sections avec l’espacement, l’alignement ou des séparateurs simples. Une zone de dépôt, un champ ou une carte autonome peut conserver sa propre bordure lorsqu’elle clarifie l’interaction.
- Toute interface, et particulièrement une fenêtre modale dense, doit présenter une hiérarchie visuelle immédiatement perceptible : un titre principal dominant, des titres de section secondaires et sobres, des groupes fonctionnels clairement séparés et des actions dont l’importance est identifiable. Éviter que plusieurs niveaux de titres, champs et boutons aient un poids visuel comparable.
- Préserver une respiration suffisante entre les sections, les libellés et les contrôles. Ne pas compenser un manque de hiérarchie par l’ajout de cadres, de grands aplats ou de boutons surdimensionnés ; privilégier les espaces, l’alignement et un séparateur neutre unique lorsque nécessaire.
- Sur les pages de contenu, faire occuper aux textes toute la largeur utile de leur conteneur. Ne pas imposer de largeur maximale arbitraire ni réserver une colonne latérale qui rétrécit durablement le contenu, sauf lorsque la mise en page ou la lisibilité l’exige explicitement.
- Les libellés des boutons doivent rester courts et directs. Éviter d’y répéter le contexte déjà donné par la page ou la section ; utiliser une icône accompagnée d’un verbe bref lorsque cela suffit à rendre l’action compréhensible.
- Toujours styliser explicitement les chevrons des menus déroulants et prévoir un espacement intérieur suffisant à droite afin qu’ils ne soient jamais collés à la bordure du contrôle.
- Avant de considérer une retouche d’interface terminée, vérifier visuellement l’ensemble de la composition dans ses états principaux, notamment les états vide, chargé et en erreur. Une modification à peine perceptible ne répond pas à une critique portant sur la densité, la confusion ou le manque de hiérarchie.
- Le fil d’Ariane doit être correctement positionné et aligné horizontalement sur le contenu principal situé juste en dessous. Par exemple, lorsqu’une page affiche un cadre de contenu, le début du fil d’Ariane doit s’aligner avec le bord gauche de ce cadre, comme sur `conditions-utilisation.php`.
