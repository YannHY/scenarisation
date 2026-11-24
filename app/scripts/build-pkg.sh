#!/bin/bash
# Construit un paquet d’installation (.pkg) pour un déploiement géré (Jamf Pro, Munki, etc.).
# L’app est signée Developer ID, notarisée et agrafée avant d’être empaquetée : elle reste valide
# même copiée hors du paquet. Le paquet l’installe dans /Applications.
#
# Si un certificat « Developer ID Installer » est dans le trousseau, le paquet est signé, notarisé
# et agrafé à son tour (nécessaire pour un déploiement par commande MDM ou une installation à la main).
# Sinon, il reste non signé, ce que les règles (policies) Jamf Pro acceptent.
#
# Prérequis : ceux de build-dmg.sh (certificat Developer ID Application, profil notarytool).
# Usage : bash scripts/build-pkg.sh
# Variables facultatives : SIGN_IDENTITY, INSTALLER_IDENTITY, NOTARY_PROFILE (par défaut scenarisation-notary).
set -euo pipefail
cd "$(dirname "$0")/.."

profile="${NOTARY_PROFILE:-scenarisation-notary}"
installer_identity="${INSTALLER_IDENTITY:-$(security find-identity -v | sed -n 's/.*"\(Developer ID Installer: [^"]*\)".*/\1/p' | head -1)}"

app="$(bash scripts/sign-app.sh | tail -1)"
identifier="$(/usr/libexec/PlistBuddy -c "Print :CFBundleIdentifier" "$app/Contents/Info.plist")"
version="$(/usr/libexec/PlistBuddy -c "Print :CFBundleShortVersionString" "$app/Contents/Info.plist")"
pkg="dist/Scenarisation-$version-AppleSilicon.pkg"

stage="$(mktemp -d)"
trap 'rm -rf "$stage"' EXIT

# Notarisation de l’app elle-même : le ticket agrafé l’accompagne dans le paquet.
ditto -c -k --keepParent "$app" "$stage/app.zip"
xcrun notarytool submit "$stage/app.zip" --keychain-profile "$profile" --wait
xcrun stapler staple "$app"

# pkgbuild perd le contenu d’un bundle dont le nom accentué est en forme composée (NFC, celle de
# build-app.sh) : le paquet ne contiendrait que « . ». On empaquette une copie nommée en forme
# décomposée (NFD), la forme historique de macOS ; APFS la confond avec l’autre à l’installation.
mkdir -p "$stage/root"
nfd_app="$stage/root/$(printf 'Sce\xcc\x81narisation.app')"
ditto "$app" "$nfd_app"
sign_args=()
[ -n "$installer_identity" ] && sign_args=(--sign "$installer_identity" --timestamp)
pkgbuild --component "$nfd_app" --identifier "$identifier" --version "$version" \
    --install-location /Applications ${sign_args[@]+"${sign_args[@]}"} "$pkg" >/dev/null

# Un paquet « relocalisable » mettrait à jour une copie trouvée ailleurs (Téléchargements, dist…)
# au lieu d’installer dans /Applications. pkgbuild l’évite par défaut depuis macOS 27 : on le vérifie.
pkgutil --expand "$pkg" "$stage/expanded"
if ! grep -q '<relocate/>' "$stage/expanded/PackageInfo"; then
    echo "Le paquet est relocalisable : construisez-le sur macOS 27 ou plus récent." >&2
    rm -f "$pkg"
    exit 1
fi
pkgutil --payload-files "$pkg" >"$stage/payload.txt"
if ! grep -q 'Contents/MacOS/Scenarisation$' "$stage/payload.txt"; then
    echo "Le paquet ne contient pas l’app." >&2
    rm -f "$pkg"
    exit 1
fi

if [ -n "$installer_identity" ]; then
    xcrun notarytool submit "$pkg" --keychain-profile "$profile" --wait
    xcrun stapler staple "$pkg"
    spctl --assess --type install --verbose=2 "$pkg"
else
    echo "Paquet non signé (aucun certificat « Developer ID Installer ») : convient aux règles Jamf Pro." >&2
fi
echo "$pkg"
