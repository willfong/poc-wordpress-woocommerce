#!/bin/sh
set -eu

SLUG="${1:-${SLUG:-}}"
NAME="${2:-${NAME:-}}"
NAMESPACE="${3:-${NAMESPACE:-}}"
AUTHOR="${4:-${AUTHOR:-}}"

usage() {
  echo 'Usage: make init-plugin SLUG=my-plugin NAME="My Plugin" NAMESPACE=MyPlugin AUTHOR="Your Name"' >&2
}

if [ -z "$SLUG" ] || [ -z "$NAME" ] || [ -z "$NAMESPACE" ] || [ -z "$AUTHOR" ]; then
  usage
  exit 1
fi

if ! printf '%s' "$SLUG" | grep -Eq '^[a-z0-9][a-z0-9-]*$'; then
  echo "SLUG must contain lowercase letters, numbers, and hyphens, and start with a letter or number." >&2
  exit 1
fi

if ! printf '%s' "$NAMESPACE" | grep -Eq '^[A-Za-z_][A-Za-z0-9_]*(\\[A-Za-z_][A-Za-z0-9_]*)*$'; then
  echo "NAMESPACE must be a valid PHP namespace, for example Acme\\ShopPlugin or ShopPlugin." >&2
  exit 1
fi

PREFIX="$(printf '%s' "$SLUG" | tr '-' '_')"
CONST_PREFIX="$(printf '%s' "$PREFIX" | tr '[:lower:]' '[:upper:]')"
SOURCE_DIR="plugins/woo-dev-template"
TARGET_DIR="plugins/${SLUG}"

if [ ! -d "$SOURCE_DIR" ]; then
  if [ -d "$TARGET_DIR" ]; then
    SOURCE_DIR="$TARGET_DIR"
  else
    echo "Could not find ${SOURCE_DIR} or ${TARGET_DIR}." >&2
    exit 1
  fi
fi

if [ "$SOURCE_DIR" != "$TARGET_DIR" ]; then
  if [ -e "$TARGET_DIR" ]; then
    echo "Target plugin directory already exists: ${TARGET_DIR}" >&2
    exit 1
  fi

  mv "$SOURCE_DIR" "$TARGET_DIR"
fi

if [ -f "${TARGET_DIR}/woo-dev-template.php" ] && [ "$SLUG" != "woo-dev-template" ]; then
  mv "${TARGET_DIR}/woo-dev-template.php" "${TARGET_DIR}/${SLUG}.php"
fi

if [ ! -f "${TARGET_DIR}/${SLUG}.php" ]; then
  echo "Could not find main plugin file ${TARGET_DIR}/${SLUG}.php." >&2
  exit 1
fi

replace_in_files() {
  OLD="$1"
  NEW="$2"
  EXCLUDED_FILE="${3:-}"
  export OLD NEW

  find . -type f \
    ! -path './.git/*' \
    ! -path '*/vendor/*' \
    ! -path './bin/init-plugin.sh' \
    -print |
  while IFS= read -r file; do
    if [ -n "$EXCLUDED_FILE" ] && [ "$file" = "$EXCLUDED_FILE" ]; then
      continue
    fi

    perl -0pi -e 's/\Q$ENV{OLD}\E/$ENV{NEW}/g' "$file"
  done
}

COMPOSER_JSON="./${TARGET_DIR}/composer.json"

replace_in_files 'woo-dev-template' "$SLUG"
replace_in_files 'Woo Dev Template' "$NAME"
replace_in_files 'Template Author' "$AUTHOR"
replace_in_files 'acme-shop' "$SLUG"
replace_in_files 'Acme Shop' "$NAME"
replace_in_files 'AcmeShop' "$NAMESPACE"
replace_in_files 'Acme Inc' "$AUTHOR"
replace_in_files 'woo_dev_template' "$PREFIX"
replace_in_files 'WOO_DEV_TEMPLATE' "$CONST_PREFIX"
replace_in_files 'WooDevTemplate' "$NAMESPACE" "$COMPOSER_JSON"

if [ -f "$COMPOSER_JSON" ]; then
  NEW_NAMESPACE="$NAMESPACE" perl -0pi -e 'my $ns = $ENV{NEW_NAMESPACE}; $ns =~ s/\\/\\\\/g; s/WooDevTemplate\\\\/$ns . "\\\\"/ge' "$COMPOSER_JSON"
fi

sh bin/create-env.sh --force "$SLUG" "$SLUG"

printf 'Initialized plugin scaffold:\n'
printf '  Slug: %s\n' "$SLUG"
printf '  Name: %s\n' "$NAME"
printf '  Namespace: %s\n' "$NAMESPACE"
printf '  Directory: %s\n' "$TARGET_DIR"
