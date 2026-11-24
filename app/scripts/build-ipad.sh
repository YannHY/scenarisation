#!/bin/bash
# Compile l’app iPad (projet iPad/ScenarisationPad.xcodeproj) pour le simulateur, et la lance si un simulateur est indiqué.
#   bash scripts/build-ipad.sh                      # compilation seule
#   bash scripts/build-ipad.sh "iPad Pro 13-inch (M5)"   # compilation, installation et lancement
set -euo pipefail
cd "$(dirname "$0")/.."

simulator="${1:-}"
derived="$PWD/.build/ipad-dd"
xcodebuild -project iPad/ScenarisationPad.xcodeproj -scheme ScenarisationPad -configuration Debug \
    -destination "platform=iOS Simulator,name=${simulator:-iPad Pro 13-inch (M5)},OS=latest" \
    -derivedDataPath "$derived" build CODE_SIGNING_ALLOWED=NO -quiet
app="$derived/Build/Products/Debug-iphonesimulator/Scenarisation.app"
echo "$app"
if [ -n "$simulator" ]; then
    xcrun simctl boot "$simulator" 2>/dev/null || true
    xcrun simctl bootstatus "$simulator" -b >/dev/null
    open -a Simulator
    xcrun simctl install "$simulator" "$app"
    xcrun simctl launch --terminate-running-process "$simulator" eu.scenarisation.ipad
fi
