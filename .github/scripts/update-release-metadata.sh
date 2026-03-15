#!/usr/bin/env bash
set -euo pipefail

VERSION="${1:?version is required}"
TAG="${2:?tag is required}"
REPO="${3:?repo is required (owner/name)}"

PLUGIN_ID="$(jq -r '.id' plugin.json)"
if [[ -z "$PLUGIN_ID" || "$PLUGIN_ID" == "null" ]]; then
  echo "plugin.json must include a valid id"
  exit 1
fi

ASSET_URL="https://github.com/${REPO}/releases/download/${TAG}/${PLUGIN_ID}.zip"
UPDATE_URL="https://raw.githubusercontent.com/${REPO}/main/update.json"

TMP_PLUGIN="$(mktemp)"
jq \
  --arg version "$VERSION" \
  --arg update_url "$UPDATE_URL" \
  '.version = $version | .update_url = $update_url' \
  plugin.json > "$TMP_PLUGIN"
mv "$TMP_PLUGIN" plugin.json

if [[ ! -f update.json ]]; then
  echo '{}' > update.json
fi

TMP_UPDATE="$(mktemp)"
jq \
  --arg version "$VERSION" \
  --arg asset_url "$ASSET_URL" \
  '
  (. // {})
  | .[$version] = {
      "version": $version,
      "download_url": $asset_url
    }
  ' \
  update.json > "$TMP_UPDATE"
mv "$TMP_UPDATE" update.json
