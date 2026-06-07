#!/usr/bin/env bash
# Push local MySQL database + uploads to VPS production (Hostinger MySQL).
# WARNING: Overwrites production database tables with local data.
#
# Usage:
#   bash tools/push-local-to-vps.sh
#   SSH_HOST=myvps bash tools/push-local-to-vps.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

SSH_HOST="${SSH_HOST:-myvps}"
REMOTE_DIR="${REMOTE_DIR:-/var/www/alphabridge}"
STAMP="$(date +%Y%m%d-%H%M%S)"

LOCAL_DB_HOST="${LOCAL_DB_HOST:-127.0.0.1}"
LOCAL_DB_PORT="${LOCAL_DB_PORT:-3306}"
LOCAL_DB_USER="${LOCAL_DB_USER:-abt}"
LOCAL_DB_PASSWORD="${LOCAL_DB_PASSWORD:-alphabridge_local}"
LOCAL_DB_NAME="${LOCAL_DB_NAME:-alphabridge}"

DUMP_FILE="data/export/local-full-${STAMP}.sql"
UPLOADS_TAR="data/export/local-uploads-${STAMP}.tar.gz"

mkdir -p data/export backups

echo "==> 1. Dumping LOCAL database (${LOCAL_DB_NAME})..."
mysqldump -h "$LOCAL_DB_HOST" -P "$LOCAL_DB_PORT" -u "$LOCAL_DB_USER" -p"$LOCAL_DB_PASSWORD" "$LOCAL_DB_NAME" \
  --single-transaction --routines --triggers --set-gtid-purged=OFF --no-tablespaces \
  > "$DUMP_FILE"

BYTES=$(wc -c < "$DUMP_FILE" | tr -d ' ')
if [ "$BYTES" -lt 1000 ]; then
  echo "ERROR: Local dump too small (${BYTES} bytes)."
  exit 1
fi
echo "    Saved $(du -h "$DUMP_FILE" | cut -f1) to $DUMP_FILE"

echo "==> 2. Packaging local uploads..."
if [ -d apps/api/uploads ]; then
  tar -czf "$UPLOADS_TAR" -C apps/api uploads
  echo "    Saved $(du -h "$UPLOADS_TAR" | cut -f1) to $UPLOADS_TAR"
else
  echo "    No uploads folder — skipping"
  UPLOADS_TAR=""
fi

echo "==> 3. Uploading to VPS (${SSH_HOST})..."
scp "$DUMP_FILE" "${SSH_HOST}:${REMOTE_DIR}/data/export/"
if [ -n "$UPLOADS_TAR" ]; then
  scp "$UPLOADS_TAR" "${SSH_HOST}:${REMOTE_DIR}/data/export/"
fi

REMOTE_DUMP="${REMOTE_DIR}/data/export/$(basename "$DUMP_FILE")"
REMOTE_UPLOADS=""
if [ -n "$UPLOADS_TAR" ]; then
  REMOTE_UPLOADS="${REMOTE_DIR}/data/export/$(basename "$UPLOADS_TAR")"
fi

echo "==> 4. Importing on VPS into Hostinger MySQL (production DB)..."
ssh "$SSH_HOST" bash -s <<EOF
set -euo pipefail
cd "$REMOTE_DIR"
mkdir -p backups data/export

echo "    Backing up current production DB..."
set -a && source apps/api/.env && set +a
BACKUP="backups/production-before-local-${STAMP}.sql"
mysqldump -h "\$DB_HOST" -P "\${DB_PORT:-3306}" -u "\$DB_USER" -p"\$DB_PASSWORD" "\$DB_NAME" \
  --single-transaction --set-gtid-purged=OFF --column-statistics=0 \
  > "\$BACKUP" 2>/dev/null || mysqldump -h "\$DB_HOST" -P "\${DB_PORT:-3306}" -u "\$DB_USER" -p"\$DB_PASSWORD" "\$DB_NAME" \
  --single-transaction --set-gtid-purged=OFF > "\$BACKUP"
echo "    Backup: \$BACKUP (\$(du -h "\$BACKUP" | cut -f1))"

echo "    Importing local dump..."
mysql -h "\$DB_HOST" -P "\${DB_PORT:-3306}" -u "\$DB_USER" -p"\$DB_PASSWORD" "\$DB_NAME" < "$REMOTE_DUMP"

if [ -n "$REMOTE_UPLOADS" ] && [ -f "$REMOTE_UPLOADS" ]; then
  echo "    Restoring uploads..."
  tar -xzf "$REMOTE_UPLOADS" -C apps/api
fi

echo "    Shareholder counts after import:"
mysql -h "\$DB_HOST" -P "\${DB_PORT:-3306}" -u "\$DB_USER" -p"\$DB_PASSWORD" "\$DB_NAME" -e \
  "SELECT COUNT(*) AS total FROM shareholders; SELECT COUNT(*) AS approved FROM shareholders WHERE status='approved' AND deleted_at IS NULL;"

echo "==> 5. Pull latest code, migrate, build, restart API..."
git pull origin main
cd apps/api && npm install && cd ../..
npm run db:migrate
grep -q '^VITE_DATA_BACKEND=mysql' .env 2>/dev/null || echo 'VITE_DATA_BACKEND=mysql' >> .env
grep -q '^VITE_API_URL=' .env 2>/dev/null || echo 'VITE_API_URL=/api' >> .env
npm install --legacy-peer-deps
npm run build
pm2 restart alphabridge-api || pm2 start apps/api/src/main.js --name alphabridge-api --cwd apps/api
pm2 save
pm2 save
sudo nginx -t && sudo systemctl reload nginx

sleep 2
curl -sf http://127.0.0.1:3003/health && echo
git log -1 --oneline
EOF

echo ""
echo "============================================"
echo "  Local database + uploads pushed to VPS"
echo "  Dump: $DUMP_FILE"
echo "  Verify: https://alpha-bridge.net/admin/shareholders/signed-agreements"
echo "============================================"
