#!/usr/bin/env bash
# Enable project git hooks (auto version bump on every commit).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

chmod +x .githooks/pre-commit 2>/dev/null || true
[ -f .githooks/pre-push ] && chmod +x .githooks/pre-push || true
git config core.hooksPath .githooks

echo "Git hooks enabled (core.hooksPath -> .githooks)."
echo ".githooks/pre-commit bumps laravel-app/VERSION on each commit."
echo "  patch 0–9 → next minor (2.3.9 → 2.4.0); minor 0–9 → next major (2.9.9 → 3.0.0)."
echo "Skip once with: SKIP_VERSION_BUMP=1 git commit ..."
