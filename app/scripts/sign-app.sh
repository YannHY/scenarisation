#!/bin/bash
# Construit l’app en release et la signe avec le certificat « Developer ID Application »
# (runtime renforcé, horodatage), comme l’exige la notarisation. Partagé par build-dmg.sh et build-pkg.sh.
#
# Usage : bash scripts/sign-app.sh (affiche le chemin de l’app signée)
# Variable facultative : SIGN_IDENTITY (certificat).
set -euo pipefail
cd "$(dirname "$0")/.."

identity="${SIGN_IDENTITY:-$(security find-identity -v -p codesigning | sed -n 's/.*"\(Developer ID Application: [^"]*\)".*/\1/p' | head -1)}"
if [ -z "$identity" ]; then
    echo "Aucun certificat « Developer ID Application » dans le trousseau." >&2
    echo "Créez-le dans Xcode → Réglages → Comptes → Gérer les certificats → + → Developer ID Application." >&2
    exit 1
fi

bash scripts/build-app.sh release >/dev/null
app="dist/Scénarisation.app"

# Remplace la signature locale de build-app.sh : runtime renforcé et horodatage sont exigés par la notarisation.
for bundle in "$app"/Contents/Resources/*.bundle; do
    [ -d "$bundle" ] && codesign --force --timestamp --sign "$identity" "$bundle"
done
# Sparkle : ses services et outils d’installation doivent être signés de l’intérieur vers l’extérieur.
sparkle="$app/Contents/Frameworks/Sparkle.framework/Versions/B"
codesign --force --options runtime --timestamp --sign "$identity" "$sparkle/XPCServices/Installer.xpc"
codesign --force --options runtime --timestamp --sign "$identity" --preserve-metadata=entitlements "$sparkle/XPCServices/Downloader.xpc"
codesign --force --options runtime --timestamp --sign "$identity" "$sparkle/Autoupdate"
codesign --force --options runtime --timestamp --sign "$identity" "$sparkle/Updater.app"
codesign --force --options runtime --timestamp --sign "$identity" "$app/Contents/Frameworks/Sparkle.framework"
codesign --force --options runtime --timestamp --sign "$identity" "$app/Contents/MacOS/Scenarisation"
codesign --force --options runtime --timestamp --sign "$identity" "$app"
codesign --verify --deep --strict "$app"
echo "$app"
