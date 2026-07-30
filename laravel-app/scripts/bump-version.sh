#!/usr/bin/env bash
# Legacy helper — prefer .githooks/pre-commit (laravel-app/VERSION).
# Scheme: patch 0–9 → next minor; minor 0–9 → next major (2.9.9 → 3.0.0).
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
VERSION_FILE="$ROOT_DIR/VERSION"

if [[ ! -f "$VERSION_FILE" ]]; then
    echo "2.3.0" > "$VERSION_FILE"
fi

current="$(tr -d '[:space:]' < "$VERSION_FILE")"

if [[ $current =~ ^([0-9]+)\.([0-9]+)\.([0-9]+)$ ]]; then
    major="${BASH_REMATCH[1]}"
    minor="${BASH_REMATCH[2]}"
    patch="${BASH_REMATCH[3]}"
    patch=$((patch + 1))
    if [ "$patch" -ge 10 ]; then
        patch=0
        minor=$((minor + 1))
    fi
    if [ "$minor" -ge 10 ]; then
        minor=0
        major=$((major + 1))
    fi
    next="${major}.${minor}.${patch}"
    echo "$next" > "$VERSION_FILE"
    echo "Version bumped to $next"
elif [[ $current =~ V\.?([0-9]+)\.([0-9]+)\.([0-9]+) ]]; then
    major="${BASH_REMATCH[1]}"
    minor="${BASH_REMATCH[2]}"
    patch="${BASH_REMATCH[3]}"
    patch=$((patch + 1))
    if [ "$patch" -ge 10 ]; then
        patch=0
        minor=$((minor + 1))
    fi
    if [ "$minor" -ge 10 ]; then
        minor=0
        major=$((major + 1))
    fi
    next="BCL V.${major}.${minor}.${patch}"
    echo "$next" > "$VERSION_FILE"
    echo "Version bumped to $next"
else
    echo "Could not parse version from: $current" >&2
    exit 1
fi
