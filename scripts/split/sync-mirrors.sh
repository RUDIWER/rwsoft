#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

: "${RWTABLE_MIRROR_TOKEN:?RWTABLE_MIRROR_TOKEN is required}"
: "${RWTABLE_VUE_REPO:?RWTABLE_VUE_REPO is required (owner/repo)}"
: "${RWTABLE_LARAVEL_REPO:?RWTABLE_LARAVEL_REPO is required (owner/repo)}"

DEFAULT_BRANCH="${DEFAULT_BRANCH:-main}"

sync_prefix() {
    local prefix="$1"
    local target_repo="$2"

    echo "[split-sync] Creating subtree split for ${prefix}"
    local split_sha
    split_sha="$(git subtree split --prefix="$prefix" "$DEFAULT_BRANCH")"

    echo "[split-sync] Pushing ${prefix} (${split_sha}) to ${target_repo}:${DEFAULT_BRANCH}"
    local remote_url="https://x-access-token:${RWTABLE_MIRROR_TOKEN}@github.com/${target_repo}.git"
    git push "$remote_url" "${split_sha}:refs/heads/${DEFAULT_BRANCH}" --force
}

sync_prefix "packages/rwtable-vue" "$RWTABLE_VUE_REPO"
sync_prefix "packages/rwtable-laravel" "$RWTABLE_LARAVEL_REPO"

echo "[split-sync] Done"
