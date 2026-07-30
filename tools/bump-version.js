#!/usr/bin/env node
/**
 * Increment ERP version (BCL_ERP_Vx.y.z) in frontend + API constants.
 *
 * Scheme (same as .githooks/pre-commit):
 *   patch 0–9, then next minor (2.3.9 → 2.4.0)
 *   minor 0–9, then next major (2.9.9 → 3.0.0)
 *
 * laravel-app/VERSION is the single source of truth when present.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..');

const VERSION_FILES = [
  path.join(ROOT, 'src/constants/appVersion.js'),
  path.join(ROOT, 'apps/api/src/constants/appVersion.js'),
];

const VERSION_RE = /(?:BCL_ERP_V\.?|ABT_ERP_V\.)(\d+)\.(\d+)\.(\d+)/;

function readVersion(filePath) {
  const content = fs.readFileSync(filePath, 'utf8');
  const match = content.match(/export const APP_VERSION = '((?:BCL_ERP_V\.?|ABT_ERP_V\.)\d+\.\d+\.\d+)';/);
  if (!match) throw new Error(`Could not read APP_VERSION from ${filePath}`);
  return match[1];
}

function bumpVersionString(version) {
  const match = version.match(VERSION_RE);
  if (!match) throw new Error(`Invalid version format: ${version}`);
  let major = Number(match[1]);
  let minor = Number(match[2]);
  let patch = Number(match[3]) + 1;
  if (patch >= 10) {
    patch = 0;
    minor += 1;
  }
  if (minor >= 10) {
    minor = 0;
    major += 1;
  }
  return `BCL_ERP_V${major}.${minor}.${patch}`;
}

function replaceVersionInFile(filePath, nextVersion) {
  let content = fs.readFileSync(filePath, 'utf8');
  content = content.replace(
    /export const APP_VERSION = '(?:BCL_ERP_V\.?|ABT_ERP_V\.)\d+\.\d+\.\d+';/,
    `export const APP_VERSION = '${nextVersion}';`
  );
  fs.writeFileSync(filePath, content);
}

function readLaravelVersion() {
  const file = path.join(ROOT, 'laravel-app/VERSION');
  if (!fs.existsSync(file)) return null;
  const raw = fs.readFileSync(file, 'utf8').trim();
  return /^\d+\.\d+\.\d+$/.test(raw) ? `BCL_ERP_V${raw}` : null;
}

function main() {
  const current = readVersion(VERSION_FILES[0]);
  // Prefer laravel-app/VERSION (pre-commit source of truth); else bump locally.
  const next = readLaravelVersion() || bumpVersionString(current);

  for (const filePath of VERSION_FILES) {
    replaceVersionInFile(filePath, next);
  }

  console.log(`${current} -> ${next}`);
  return next;
}

main();
