#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
VERSION_FILE="$ROOT_DIR/VERSION"

if [[ ! -f "$VERSION_FILE" ]]; then
    echo "BCL V.2.1.0" > "$VERSION_FILE"
fi

current="$(tr -d '\n' < "$VERSION_FILE")"

if [[ $current =~ V\.?([0-9]+)\.([0-9]+)\.([0-9]+) ]]; then
    major="${BASH_REMATCH[1]}"
    minor="${BASH_REMATCH[2]}"
    patch="${BASH_REMATCH[3]}"
    patch=$((patch + 1))
    next="BCL V.${major}.${minor}.${patch}"
    echo "$next" > "$VERSION_FILE"
    echo "Version bumped to $next"
else
    echo "Could not parse version from: $current" >&2
    exit 1
fi
