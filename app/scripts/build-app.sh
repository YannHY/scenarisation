#!/bin/bash
set -euo pipefail
cd "$(dirname "$0")/.."

configuration="${1:-debug}"
case "$configuration" in debug|release) ;; *) echo "Usage: bash scripts/build-app.sh [debug|release]" >&2; exit 1 ;; esac
swift build -c "$configuration"
binary_dir="$(swift build -c "$configuration" --show-bin-path)"
app_dir="$PWD/dist/Scénarisation.app"
mkdir -p "$app_dir/Contents/MacOS" "$app_dir/Contents/Resources"
cp "$binary_dir/Scenarisation" "$app_dir/Contents/MacOS/Scenarisation"
# Les données de test ne font pas partie de l’app distribuée.
rm -rf "$app_dir"/Contents/Resources/*Tests.bundle
for resource in "$binary_dir"/*.bundle; do
    [ -d "$resource" ] || continue
    case "$(basename "$resource")" in *Tests.bundle) continue ;; esac
    ditto "$resource" "$app_dir/Contents/Resources/$(basename "$resource")"
done
# Sparkle (mises à jour) : framework dynamique chargé via le rpath @executable_path/../Frameworks.
rm -rf "$app_dir/Contents/Frameworks"
mkdir -p "$app_dir/Contents/Frameworks"
ditto "$binary_dir/Sparkle.framework" "$app_dir/Contents/Frameworks/Sparkle.framework"
cp packaging/Info.plist "$app_dir/Contents/Info.plist"
cp LICENSE "$app_dir/Contents/Resources/LICENSE"
swift packaging/CreateIcon.swift "$PWD/.build/AppIcon.iconset"
iconutil -c icns "$PWD/.build/AppIcon.iconset" -o "$PWD/.build/AppIcon.icns"
cp .build/AppIcon.icns "$app_dir/Contents/Resources/AppIcon.icns"
# Un nouveau nom lorsque le dessin change évite l’ancienne icône gardée par le Finder.
icon_digest="$(shasum -a 256 .build/AppIcon.icns | cut -c 1-12)"
icon_name="AppIcon-$icon_digest"
cp .build/AppIcon.icns "$app_dir/Contents/Resources/$icon_name.icns"
/usr/libexec/PlistBuddy -c "Set :CFBundleIconFile $icon_name" "$app_dir/Contents/Info.plist"
codesign --force --deep --sign - "$app_dir"
touch "$app_dir"
launch_services="/System/Library/Frameworks/CoreServices.framework/Frameworks/LaunchServices.framework/Support/lsregister"
"$launch_services" -f "$app_dir"
echo "$app_dir"
