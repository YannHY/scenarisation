#!/bin/bash
# Prépare la publication d’une mise à jour Mac pour Sparkle.
# Déplace le DMG notarisé dans dist/mac, puis génère dist/mac/appcast.xml avec la signature EdDSA
# de chaque archive (clé « scenarisation-mac » du trousseau, créée une fois par generate_keys).
# Il reste à déposer le contenu de dist/mac sur https://scenarisation.eu/mac/.
#
# Avant chaque version : augmenter CFBundleShortVersionString ET CFBundleVersion dans packaging/Info.plist
# (Sparkle compare CFBundleVersion), puis lancer scripts/build-dmg.sh.
#
# Notes de version facultatives : un fichier HTML portant le nom du DMG (Scenarisation-X.Y.Z-AppleSilicon.html)
# placé dans dist/mac est affiché dans la fenêtre de mise à jour.
#
# Usage : bash scripts/publish-update.sh [chemin du DMG] (par défaut, la version de packaging/Info.plist)
set -euo pipefail
cd "$(dirname "$0")/.."

prefix="https://scenarisation.eu/mac/"
updates="dist/mac"
if [ "$#" -gt 0 ]; then
    dmg="$1"
else
    version="$(/usr/libexec/PlistBuddy -c 'Print :CFBundleShortVersionString' packaging/Info.plist)"
    name="Scenarisation-$version-AppleSilicon.dmg"
    dmg="dist/$name"
    [ -f "$dmg" ] || dmg="$updates/$name"
fi
if [ -z "$dmg" ] || [ ! -f "$dmg" ]; then
    echo "Aucun DMG trouvé : lancez d’abord bash scripts/build-dmg.sh." >&2
    exit 1
fi
# Une archive non notarisée serait refusée par Gatekeeper après l’installation automatique.
xcrun stapler validate "$dmg" >/dev/null || { echo "$dmg n’est pas notarisé." >&2; exit 1; }

mkdir -p "$updates"
# Le DMG produit par build-dmg.sh rejoint dist/mac : un seul exemplaire, celui à déposer.
if ! [[ "$dmg" -ef "$updates/$(basename "$dmg")" ]]; then
    mv "$dmg" "$updates/"
fi
swift package resolve >/dev/null
.build/artifacts/sparkle/Sparkle/bin/generate_appcast --account scenarisation-mac \
    --download-url-prefix "$prefix" --link "https://scenarisation.eu" "$updates"
# Les deltas portent le nom de l’app, « Scénarisation », que macOS écrit « e » + accent combinant (NFD).
# Le client FTP les dépose avec un « é » précomposé (NFC) : sans cette correction, leurs URL répondent 404
# et Sparkle télécharge le DMG complet. Le flux n’est pas signé, seules les archives le sont.
sed -i '' 's/Sce%CC%81narisation/Sc%C3%A9narisation/g' "$updates/appcast.xml"

echo
echo "À déposer sur ${prefix} :"
ls -1 "$updates"
