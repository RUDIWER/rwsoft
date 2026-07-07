#!/usr/bin/env sh
set -eu

BASE_URL="${RWSOFT_CLI_BASE_URL:-https://github.com/RUDIWER/rwsoft/releases/latest/download}"
TMP_DIR="${TMPDIR:-/tmp}/rwsoft-installer.$$"

cleanup() {
    rm -rf "$TMP_DIR"
}
trap cleanup EXIT INT TERM

os="$(uname -s | tr '[:upper:]' '[:lower:]')"
arch="$(uname -m)"

case "$os" in
    linux) os="linux" ;;
    darwin) os="darwin" ;;
    *) echo "Unsupported OS: $os" >&2; exit 1 ;;
esac

case "$arch" in
    x86_64|amd64) arch="amd64" ;;
    arm64|aarch64) arch="arm64" ;;
    *) echo "Unsupported architecture: $arch" >&2; exit 1 ;;
esac

asset="rwsoft-${os}-${arch}"
url="${BASE_URL}/${asset}"
checksums_url="${BASE_URL}/checksums.txt"
mkdir -p "$TMP_DIR"

echo "Downloading ${url}"
if command -v curl >/dev/null 2>&1; then
    curl -fsSL "$url" -o "$TMP_DIR/${asset}"
    curl -fsSL "$checksums_url" -o "$TMP_DIR/checksums.txt"
elif command -v wget >/dev/null 2>&1; then
    wget -q "$url" -O "$TMP_DIR/${asset}"
    wget -q "$checksums_url" -O "$TMP_DIR/checksums.txt"
else
    echo "curl or wget is required to download the installer binary." >&2
    exit 1
fi

(
    cd "$TMP_DIR"
    if command -v sha256sum >/dev/null 2>&1; then
        grep "  ${asset}$" checksums.txt | sha256sum -c -
    elif command -v shasum >/dev/null 2>&1; then
        expected="$(grep "  ${asset}$" checksums.txt | awk '{print $1}')"
        actual="$(shasum -a 256 "$asset" | awk '{print $1}')"
        if [ "$expected" != "$actual" ]; then
            echo "Checksum verification failed for ${asset}." >&2
            exit 1
        fi
    else
        echo "sha256sum or shasum is required to verify the installer binary." >&2
        exit 1
    fi
)

chmod +x "$TMP_DIR/${asset}"
exec "$TMP_DIR/${asset}" install "$@"
