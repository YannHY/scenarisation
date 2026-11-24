Fichiers de compatibilité produits le 15 novembre 2026 avec les fonctions
buildMarkdownExport, buildHtmlExportDocument, buildWordExportDocument,
buildExcelExportDocument et buildStudentInstructionsData du site :
https://github.com/YannHY/scenarisation/blob/main/js/editor/exports.js
SHA du fichier exports.js : 5fd022e4e55c2e899ce52cee8412d3908be78015.
SHA du fichier config.js : 56324c8658a3c4e027d74b1778d46f97a31db3a9.

Les libellés pédagogiques et scolaires viennent de js/editor/config.js.
Le CSV contient les cellules de la feuille Excel, sérialisées avec BOM,
guillemets et fins de ligne CRLF. site-teacher.json est le scénario source.

Les scénarios testent les accents, les caractères XML, les champs multilignes,
les durées décimales, deux séances portant le même titre, une séance vide,
les modalités pédagogiques et les trois états AIAS.

site-teacher-shared.xlsx est une variante compressée avec Python zipfile,
chaînes partagées enrichies, cellules clairsemées, valeurs numériques et feuille
de couverture. site-teacher-deflated.docx utilise DEFLATE et les descripteurs ZIP.

Licence des générateurs : CC BY-SA 4.0, Yann Houry, d’après François Jourde
et UCL Learning Designer. Voir LICENSE à la racine de l’application.
