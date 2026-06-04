#!/usr/bin/env bash
# Migrate AlphaBridge from Supabase export → Hostinger MySQL
# Usage: bash tools/migrate-hostinger.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ ! -f apps/api/.env ]]; then
  echo "Missing apps/api/.env"
  echo "Copy apps/api/.env.hostinger.example → apps/api/.env and set DB_PASSWORD"
  exit 1
fi

echo "==> Step 1: Export from Supabase (skip if data/export already exists)"
if [[ ! -d data/export ]] || [[ -z "$(ls -A data/export/*.json 2>/dev/null)" ]]; then
  npm run export:supabase
else
  echo "    Using existing data/export/"
fi

echo ""
echo "==> Step 2: Create MySQL tables"
npm run db:migrate

echo ""
echo "==> Step 3: Import exported data"
npm run db:import

echo ""
echo "==> Step 4: Test connection"
npm run db:test

echo ""
echo "Migration complete."
echo "Start API: npm run dev:api  (or pm2 on VPS)"
echo "Login: admin@alpha-bridge.net / ChangeMe@123456"
