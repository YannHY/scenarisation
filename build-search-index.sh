#!/bin/sh
# Génère deux index Pagefind à partir du contenu réellement affiché en français
# et en anglais. Les pages source ne sont pas dupliquées : Chrome applique leurs
# traductions, puis les rendus temporaires sont supprimés à la fin.
set -eu

PROJECT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
STAGING_DIR="$PROJECT_DIR/.pagefind-site"
TEMP_OUTPUT_DIR="$PROJECT_DIR/.pagefind-output"
OUTPUT_DIR="$PROJECT_DIR/pagefind"

cleanup() {
    rm -rf "$STAGING_DIR" "$TEMP_OUTPUT_DIR"
}
trap cleanup EXIT HUP INT TERM

command -v node >/dev/null 2>&1 || {
    echo "Erreur : Node.js est nécessaire pour préparer les pages à indexer." >&2
    exit 1
}
command -v npx >/dev/null 2>&1 || {
    echo "Erreur : Node.js et npx sont nécessaires pour lancer Pagefind." >&2
    exit 1
}

cd "$PROJECT_DIR"
rm -rf "$STAGING_DIR" "$TEMP_OUTPUT_DIR"
node "$PROJECT_DIR/scripts/render-search-pages.mjs" "$PROJECT_DIR" "$STAGING_DIR"

PAGEFIND_SITE="$STAGING_DIR/fr" \
PAGEFIND_OUTPUT_PATH="$TEMP_OUTPUT_DIR/fr" \
PAGEFIND_FORCE_LANGUAGE="fr" \
npx -y pagefind@1.5.2

PAGEFIND_SITE="$STAGING_DIR/en" \
PAGEFIND_OUTPUT_PATH="$TEMP_OUTPUT_DIR/en" \
PAGEFIND_FORCE_LANGUAGE="en" \
npx -y pagefind@1.5.2

rm -rf "$OUTPUT_DIR"
mv "$TEMP_OUTPUT_DIR" "$OUTPUT_DIR"
echo "Index Pagefind français et anglais générés dans $OUTPUT_DIR"
