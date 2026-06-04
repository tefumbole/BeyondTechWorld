#!/usr/bin/env bash
# Creates local MySQL database for offline AlphaBridge development.
# Requires: brew install mysql && brew services start mysql
set -euo pipefail

DB_NAME=alphabridge
DB_USER=abt
DB_PASS=alphabridge_local

echo "This script creates database '$DB_NAME' and user '$DB_USER' on local MySQL."
echo "You will be prompted for your MySQL root password."
echo ""

mysql -u root -p <<SQL
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

echo ""
echo "Local database ready. Next steps:"
echo "  cp apps/api/.env.local.example apps/api/.env   # if not already set"
echo "  npm run db:migrate"
echo "  npm run export:supabase   # once, while online — saves your Supabase data"
echo "  npm run db:import"
echo "  npm run dev:api    # terminal 1"
echo "  npm run dev        # terminal 2"
echo ""
echo "Login: admin@alpha-bridge.net / ChangeMe@123456"
