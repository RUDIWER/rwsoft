#!/usr/bin/env sh
set -eu

VERSION="${RWSOFT_CLI_VERSION:-dev}"
OUT_DIR="${RWSOFT_CLI_OUT_DIR:-dist}"

if ! command -v go >/dev/null 2>&1; then
    echo "Go is required to build rwsoft release binaries." >&2
    echo "End users do not need Go; they download prebuilt binaries through install.sh/install.ps1." >&2
    exit 1
fi

mkdir -p "$OUT_DIR"
rm -f "$OUT_DIR"/rwsoft-* "$OUT_DIR"/checksums.txt

build_one() {
    os="$1"
    arch="$2"
    suffix=""
    if [ "$os" = "windows" ]; then
        suffix=".exe"
    fi

    output="$OUT_DIR/rwsoft-$os-$arch$suffix"
    echo "Building $output"
    CGO_ENABLED=0 GOOS="$os" GOARCH="$arch" go build -ldflags "-s -w -X main.version=$VERSION" -o "$output" .
}

build_one linux amd64
build_one linux arm64
build_one darwin amd64
build_one darwin arm64
build_one windows amd64
build_one windows arm64

(
    cd "$OUT_DIR"
    if command -v sha256sum >/dev/null 2>&1; then
        sha256sum rwsoft-* > checksums.txt
    elif command -v shasum >/dev/null 2>&1; then
        shasum -a 256 rwsoft-* > checksums.txt
    else
        echo "sha256sum or shasum is required to create checksums." >&2
        exit 1
    fi
)

echo "Wrote $OUT_DIR/checksums.txt"
