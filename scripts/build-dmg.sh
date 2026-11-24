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
export SIGN_IDENTITY="$identity"

app="$(bash scripts/sign-app.sh | tail -1)"
name="Scénarisation"
prefix="Scenarisation"
version="$(/usr/libexec/PlistBuddy -c "Print :CFBundleShortVersionString" "$app/Contents/Info.plist")"
dmg="dist/$prefix-$version-AppleSilicon.dmg"

stage="$(mktemp -d)"
trap 'rm -rf "$stage"' EXIT
ditto "$app" "$stage/$name.app"
ln -s /Applications "$stage/Applications"
hdiutil create -volname "$name" -srcfolder "$stage" -fs HFS+ -format UDZO \
    -imagekey zlib-level=9 -ov "$dmg" >/dev/null
codesign --force --timestamp --sign "$identity" "$dmg"

xcrun notarytool submit "$dmg" --keychain-profile "$profile" --wait
xcrun stapler staple "$dmg"
spctl --assess --type open --context context:primary-signature --verbose=2 "$dmg"
echo "$dmg"
