#!/usr/bin/env sh
set -eu

# Accept legacy environment overrides during the rename.
SCENARISATION_CLAUDE_SKILL_DIR="${SCENARISATION_CLAUDE_SKILL_DIR:-${LEARNING_CLAUDE_SKILL_DIR:-}}"
SCENARISATION_CODEX_SKILL_DIR="${SCENARISATION_CODEX_SKILL_DIR:-${LEARNING_CODEX_SKILL_DIR:-}}"
SCENARISATION_REF="${SCENARISATION_REF:-${LEARNING_DESIGNER_REF:-}}"
SCENARISATION_REPO="${SCENARISATION_REPO:-${LEARNING_DESIGNER_REPO:-}}"
SCENARISATION_SOURCE_DIR="${SCENARISATION_SOURCE_DIR:-${LEARNING_DESIGNER_SOURCE_DIR:-}}"
SCENARISATION_INSTALL_DIR="${SCENARISATION_INSTALL_DIR:-${LEARNING_INSTALL_DIR:-}}"
SCENARISATION_INSTALL_NONINTERACTIVE="${SCENARISATION_INSTALL_NONINTERACTIVE:-${LEARNING_INSTALL_NONINTERACTIVE:-}}"
SCENARISATION_INSTALL_QUIET="${SCENARISATION_INSTALL_QUIET:-${LEARNING_INSTALL_QUIET:-}}"

REPO="${SCENARISATION_REPO:-YannHY/scenarisation}"
REF="${SCENARISATION_REF:-main}"
SOURCE_DIR="${SCENARISATION_SOURCE_DIR:-}"
CLAUDE_SKILL_DIR="${SCENARISATION_CLAUDE_SKILL_DIR:-$PWD/.claude/skills/scenarisation}"
CODEX_SKILL_DIR="${SCENARISATION_CODEX_SKILL_DIR:-$PWD/.agents/skills/scenarisation}"
TMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/scenarisation-skill.XXXXXX")"

cleanup() {
  rm -rf "$TMP_DIR"
}
trap cleanup EXIT

download() {
  source_url="$1"
  destination="$2"
  if command -v curl >/dev/null 2>&1; then
    curl -fsSL "$source_url" -o "$destination"
  elif command -v wget >/dev/null 2>&1; then
    wget -qO "$destination" "$source_url"
  else
    echo "Installation impossible : curl ou wget est requis." >&2
    exit 1
  fi
}

SKILL_FILE="$TMP_DIR/SKILL.md"
if [ -n "$SOURCE_DIR" ]; then
  cp "$SOURCE_DIR/skills/scenarisation/SKILL.md" "$SKILL_FILE"
else
  download "https://raw.githubusercontent.com/$REPO/$REF/skills/scenarisation/SKILL.md" "$SKILL_FILE"
fi

if [ -n "$SOURCE_DIR" ]; then
  mkdir -p "$TMP_DIR/bin"
  cp "$SOURCE_DIR/install.sh" "$TMP_DIR/install.sh"
  cp "$SOURCE_DIR/bin/scenarisation" "$TMP_DIR/bin/scenarisation"
else
  download "https://raw.githubusercontent.com/$REPO/$REF/install.sh" "$TMP_DIR/install.sh"
fi

(
  cd "$TMP_DIR"
  SCENARISATION_REPO="$REPO" \
    SCENARISATION_REF="$REF" \
    SCENARISATION_INSTALL_NONINTERACTIVE=1 \
    SCENARISATION_INSTALL_QUIET=1 \
    sh ./install.sh
)

if [ -n "${SCENARISATION_INSTALL_DIR:-}" ]; then
  SCENARISATION_BIN="$SCENARISATION_INSTALL_DIR/scenarisation"
else
  SCENARISATION_BIN="$(command -v scenarisation || true)"
fi

if [ -z "$SCENARISATION_BIN" ] || [ ! -x "$SCENARISATION_BIN" ]; then
  echo "Le CLI Scenarisation a été installé, mais la commande scenarisation reste introuvable dans le PATH." >&2
  exit 1
fi

if ! "$SCENARISATION_BIN" list school-systems >/dev/null 2>&1 \
  || ! "$SCENARISATION_BIN" list activity-options >/dev/null 2>&1 \
  || ! "$SCENARISATION_BIN" init --help 2>&1 | grep -q -- "--school-system" \
  || ! "$SCENARISATION_BIN" init --help 2>&1 | grep -q -- "--school-level" \
  || ! "$SCENARISATION_BIN" add-activity --help 2>&1 | grep -q -- "--group" \
  || ! "$SCENARISATION_BIN" add-activity --help 2>&1 | grep -q -- "--teaching" \
  || ! "$SCENARISATION_BIN" add-activity --help 2>&1 | grep -q -- "--pacing" \
  || ! "$SCENARISATION_BIN" add-activity --help 2>&1 | grep -q -- "--mode" \
  || ! "$SCENARISATION_BIN" add-activity --help 2>&1 | grep -q -- "--evaluation" \
  || ! "$SCENARISATION_BIN" add-activity --help 2>&1 | grep -q -- "--aias" \
  || ! "$SCENARISATION_BIN" validate --help 2>&1 | grep -q -- "--strict-pedagogy"; then
  echo "La version installée du CLI n’est pas compatible avec la skill Scenarisation." >&2
  exit 1
fi

mkdir -p "$CLAUDE_SKILL_DIR" "$CODEX_SKILL_DIR"
cp "$SKILL_FILE" "$CLAUDE_SKILL_DIR/SKILL.md"
cp "$SKILL_FILE" "$CODEX_SKILL_DIR/SKILL.md"

printf '\nScenarisation est installé et prêt.\n'
printf 'Claude Code : %s\n' "$CLAUDE_SKILL_DIR/SKILL.md"
printf 'Codex : %s\n' "$CODEX_SKILL_DIR/SKILL.md"
printf 'CLI : %s\n' "$("$SCENARISATION_BIN" --version)"
