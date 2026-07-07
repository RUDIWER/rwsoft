#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

: "${RWTABLE_MIRROR_TOKEN:?RWTABLE_MIRROR_TOKEN is required}"
: "${RWTABLE_VUE_REPO:?RWTABLE_VUE_REPO is required (owner/repo)}"
: "${RWTABLE_LARAVEL_REPO:?RWTABLE_LARAVEL_REPO is required (owner/repo)}"

TAG_NAME="${TAG_NAME:-${GITHUB_REF_NAME:-}}"

if [[ -z "$TAG_NAME" ]]; then
    echo "[mirror-tag] TAG_NAME (or GITHUB_REF_NAME) is required"
    exit 1
fi

push_tag_for_prefix() {
    local prefix="$1"
    local target_repo="$2"

    echo "[mirror-tag] Creating subtree split for ${prefix} at tag ${TAG_NAME}"
    local split_sha
    split_sha="$(git subtree split --prefix="$prefix" "$TAG_NAME")"

    echo "[mirror-tag] Pushing tag ${TAG_NAME} (${split_sha}) to ${target_repo}"
    local remote_url="https://x-access-token:${RWTABLE_MIRROR_TOKEN}@github.com/${target_repo}.git"
    git push "$remote_url" "${split_sha}:refs/tags/${TAG_NAME}" --force
}

push_tag_for_prefix "packages/rwtable-vue" "$RWTABLE_VUE_REPO"
push_tag_for_prefix "packages/rwtable-laravel" "$RWTABLE_LARAVEL_REPO"

echo "[mirror-tag] Done"
