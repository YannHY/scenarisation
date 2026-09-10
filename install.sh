#!/usr/bin/env sh
set -eu

# Accept legacy environment overrides during the rename.
SCENARISATION_REF="${SCENARISATION_REF:-${LEARNING_DESIGNER_REF:-}}"
SCENARISATION_REPO="${SCENARISATION_REPO:-${LEARNING_DESIGNER_REPO:-}}"
SCENARISATION_INSTALL_DIR="${SCENARISATION_INSTALL_DIR:-${LEARNING_INSTALL_DIR:-}}"
SCENARISATION_INSTALL_NONINTERACTIVE="${SCENARISATION_INSTALL_NONINTERACTIVE:-${LEARNING_INSTALL_NONINTERACTIVE:-}}"
SCENARISATION_INSTALL_QUIET="${SCENARISATION_INSTALL_QUIET:-${LEARNING_INSTALL_QUIET:-}}"

REPO="${SCENARISATION_REPO:-YannHY/scenarisation}"
REF="${SCENARISATION_REF:-main}"
TTY="/dev/tty"

has_tty() {
  ( : > "$TTY" ) >/dev/null 2>&1
}

say() {
  if [ "${SCENARISATION_INSTALL_QUIET:-0}" = "1" ]; then
    return
  fi
  if has_tty; then
    printf '%s\n' "$*" > "$TTY"
  else
    printf '%s\n' "$*"
  fi
}

ask() {
  prompt="$1"
  default="$2"
  if ! has_tty; then
    printf '%s\n' "$default"
    return
  fi
  if [ -n "$default" ]; then
    printf '%s [%s]: ' "$prompt" "$default" > "$TTY"
  else
    printf '%s: ' "$prompt" > "$TTY"
  fi
  IFS= read -r answer < "$TTY" || answer=""
  if [ -n "$answer" ]; then
    printf '%s\n' "$answer"
  else
    printf '%s\n' "$default"
  fi
}

is_in_path() {
  case ":$PATH:" in
    *":$1:"*) return 0 ;;
    *) return 1 ;;
  esac
}

is_good_path_dir() {
  case "$1" in
    /usr/local/bin|/opt/homebrew/bin|"$HOME"/.local/bin|"$HOME"/bin) return 0 ;;
    *) return 1 ;;
  esac
}

first_suitable_dir() {
  OLD_IFS=$IFS
  IFS=:
  for dir in $PATH; do
    if is_good_path_dir "$dir" && [ -d "$dir" ] && [ -w "$dir" ]; then
      IFS=$OLD_IFS
      printf '%s\n' "$dir"
      return
    fi
  done
  IFS=$OLD_IFS

  if is_in_path "/usr/local/bin"; then
    printf '%s\n' "/usr/local/bin"
    return
  fi
  if is_in_path "/opt/homebrew/bin"; then
    printf '%s\n' "/opt/homebrew/bin"
    return
  fi
  if is_in_path "$HOME/.local/bin"; then
    printf '%s\n' "$HOME/.local/bin"
    return
  fi
  if is_in_path "$HOME/bin"; then
    printf '%s\n' "$HOME/bin"
    return
  fi
  printf '%s\n' ""
}

choose_install_dir() {
  if [ -n "${SCENARISATION_INSTALL_DIR:-}" ]; then
    printf '%s\n' "$SCENARISATION_INSTALL_DIR"
    return
  fi

  default_dir="$(first_suitable_dir)"
  if [ -z "$default_dir" ]; then
    echo "install.sh: no suitable install directory found in PATH" >&2
    exit 1
  fi

  if [ "${SCENARISATION_INSTALL_NONINTERACTIVE:-0}" = "1" ]; then
    printf '%s\n' "$default_dir"
    return
  fi

  if ! has_tty; then
    printf '%s\n' "$default_dir"
    return
  fi

  say ""
  say "Scenarisation CLI installer"
  say ""
  say "This installs the command: scenarisation"
  say "No shell profile will be modified."
  say ""
  say "Choose where to install it:"
  say "  1. $default_dir (recommended)"
  if is_in_path "/usr/local/bin" && [ "$default_dir" != "/usr/local/bin" ]; then
    say "  2. /usr/local/bin"
    second_dir="/usr/local/bin"
  elif is_in_path "/opt/homebrew/bin" && [ "$default_dir" != "/opt/homebrew/bin" ]; then
    say "  2. /opt/homebrew/bin"
    second_dir="/opt/homebrew/bin"
  else
    second_dir=""
  fi
  say "  c. Custom directory already in PATH"
  say ""

  choice="$(ask "Selection" "1")"
  case "$choice" in
    1|"") printf '%s\n' "$default_dir" ;;
    2)
      if [ -n "$second_dir" ]; then
        printf '%s\n' "$second_dir"
      else
        printf '%s\n' "$default_dir"
      fi
      ;;
    c|C|custom|Custom)
      custom_dir="$(ask "Directory" "$default_dir")"
      if ! is_in_path "$custom_dir"; then
        say "That directory is not in PATH, so scenarisation would not be available directly."
        say "Using $default_dir instead."
        printf '%s\n' "$default_dir"
      else
        printf '%s\n' "$custom_dir"
      fi
      ;;
    *) printf '%s\n' "$default_dir" ;;
  esac
}

INSTALL_DIR="$(choose_install_dir)"
TARGET="$INSTALL_DIR/scenarisation"
TMP_FILE="$(mktemp "${TMPDIR:-/tmp}/scenarisation.XXXXXX")"
cleanup() {
  rm -f "$TMP_FILE"
}
trap cleanup EXIT

if ! command -v python3 >/dev/null 2>&1; then
  echo "install.sh: python3 is required to run scenarisation." >&2
  echo "Install Python 3, then run this installer again." >&2
  exit 1
fi

if [ -f "./bin/scenarisation" ]; then
  cp "./bin/scenarisation" "$TMP_FILE"
else
  URL="https://raw.githubusercontent.com/$REPO/$REF/bin/scenarisation"
  if command -v curl >/dev/null 2>&1; then
    curl -fsSL "$URL" -o "$TMP_FILE"
  elif command -v wget >/dev/null 2>&1; then
    wget -qO "$TMP_FILE" "$URL"
  else
    echo "install.sh: curl or wget is required" >&2
    exit 1
  fi
fi

chmod +x "$TMP_FILE"

if [ ! -d "$INSTALL_DIR" ]; then
  if command -v sudo >/dev/null 2>&1; then
    sudo mkdir -p "$INSTALL_DIR"
  else
    mkdir -p "$INSTALL_DIR"
  fi
fi

if [ -w "$INSTALL_DIR" ]; then
  cp "$TMP_FILE" "$TARGET"
  chmod +x "$TARGET"
elif command -v sudo >/dev/null 2>&1; then
  sudo cp "$TMP_FILE" "$TARGET"
  sudo chmod +x "$TARGET"
else
  echo "install.sh: $INSTALL_DIR is not writable and sudo is unavailable" >&2
  exit 1
fi

say ""
say "Installed scenarisation to $TARGET"
say "Run: scenarisation --help"
