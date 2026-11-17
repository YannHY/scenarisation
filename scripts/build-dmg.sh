#!/bin/bash
# Construit l’app en release, la signe avec un certificat Developer ID (runtime renforcé),
# crée le DMG, le fait notariser par Apple puis agrafe le ticket.
#
# Prérequis :
#   - un certificat « Developer ID Application » dans le trousseau ;
#   - un profil notarytool enregistré une fois pour toutes :
#       xcrun notarytool store-credentials scenarisation-notary \
#         --apple-id <identifiant Apple> --team-id <Team ID>
#
# Usage : bash scripts/build-dmg.sh
# Variables facultatives : SIGN_IDENTITY (certificat), NOTARY_PROFILE (profil, par défaut scenarisation-notary).
set -euo pipefail
cd "$(dirname "$0")/.."

profile="${NOTARY_PROFILE:-scenarisation-notary}"
identity="${SIGN_IDENTITY:-$(security find-identity -v -p codesigning | sed -n 's/.*"\(Developer ID Application: [^"]*\)".*/\1/p' | head -1)}"
if [ -z "$identity" ]; then
    echo "Aucun certificat « Developer ID Application » dans le trousseau." >&2
    echo "Créez-le dans Xcode → Réglages → Comptes → Gérer les certificats → + → Developer ID Application." >&2
    exit 1
fi

bash scripts/build-app.sh release >/dev/null
app="dist/Scénarisation.app"
version="$(/usr/libexec/PlistBuddy -c "Print :CFBundleShortVersionString" "$app/Contents/Info.plist")"
dmg="dist/Scenarisation-$version-AppleSilicon.dmg"

# Remplace la signature locale de build-app.sh : runtime renforcé et horodatage sont exigés par la notarisation.
for bundle in "$app"/Contents/Resources/*.bundle; do
    [ -d "$bundle" ] && codesign --force --timestamp --sign "$identity" "$bundle"
done
codesign --force --options runtime --timestamp --sign "$identity" "$app/Contents/MacOS/Scenarisation"
codesign --force --options runtime --timestamp --sign "$identity" "$app"
codesign --verify --deep --strict "$app"

stage="$(mktemp -d)"
trap 'rm -rf "$stage"' EXIT
ditto "$app" "$stage/Scénarisation.app"
ln -s /Applications "$stage/Applications"
hdiutil create -volname "Scénarisation" -srcfolder "$stage" -fs HFS+ -format UDZO \
    -imagekey zlib-level=9 -ov "$dmg" >/dev/null
codesign --force --timestamp --sign "$identity" "$dmg"

xcrun notarytool submit "$dmg" --keychain-profile "$profile" --wait
xcrun stapler staple "$dmg"
spctl --assess --type open --context context:primary-signature --verbose=2 "$dmg"
echo "$dmg"
