#!/bin/sh
set -eu

FORCE=0

if [ "${1:-}" = "--force" ]; then
  FORCE=1
  shift
fi

PROJECT_HINT="${1:-}"
PLUGIN_SLUG="${2:-}"
ENV_FILE="${ENV_FILE:-.env}"
ENV_EXAMPLE="${ENV_EXAMPLE:-.env.example}"

if [ ! -f "$ENV_EXAMPLE" ]; then
  echo "Missing ${ENV_EXAMPLE}." >&2
  exit 1
fi

if [ ! -f "$ENV_FILE" ]; then
  cp "$ENV_EXAMPLE" "$ENV_FILE"
fi

sanitize_compose_name() {
  value="$(printf '%s' "$1" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9_-]/-/g; s/^-*//; s/-*$//')"

  if [ -z "$value" ]; then
    value="wordpress-plugin"
  fi

  case "$value" in
    [a-z0-9]*)
      printf '%s' "$value"
      ;;
    *)
      printf 'wp-%s' "$value"
      ;;
  esac
}

get_key() {
  awk -F= -v key="$1" '$1 == key { print substr($0, index($0, "=") + 1); found = 1; exit } END { if (! found) exit 1 }' "$ENV_FILE" || true
}

set_key() {
  key="$1"
  value="$2"
  tmp="${ENV_FILE}.tmp.$$"

  awk -v key="$key" -v value="$value" '
    BEGIN { found = 0 }
    $0 ~ "^" key "=" {
      print key "=" value
      found = 1
      next
    }
    { print }
    END {
      if (! found) {
        print key "=" value
      }
    }
  ' "$ENV_FILE" > "$tmp"

  mv "$tmp" "$ENV_FILE"
}

if [ -z "$PROJECT_HINT" ]; then
  PROJECT_HINT="$(basename "$(pwd)")"
fi

PROJECT_NAME="$(sanitize_compose_name "$PROJECT_HINT")"
CURRENT_PROJECT="$(get_key COMPOSE_PROJECT_NAME)"

if [ "$FORCE" = "1" ] || [ -z "$CURRENT_PROJECT" ] || [ "$CURRENT_PROJECT" = "__AUTO__" ] || [ "$CURRENT_PROJECT" = "woo-dev-template" ]; then
  set_key COMPOSE_PROJECT_NAME "$PROJECT_NAME"
fi

if [ -n "$PLUGIN_SLUG" ]; then
  CURRENT_PLUGIN_SLUG="$(get_key PLUGIN_SLUG)"

  if [ "$FORCE" = "1" ] || [ -z "$CURRENT_PLUGIN_SLUG" ] || [ "$CURRENT_PLUGIN_SLUG" = "woo-dev-template" ]; then
    set_key PLUGIN_SLUG "$PLUGIN_SLUG"
  fi
fi
