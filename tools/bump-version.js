#!/usr/bin/env node
/**
 * Increment ERP patch version (BCL_ERP_V2.2.z -> z+1) in frontend + API constants.
 * Also accepts legacy ABT_ERP_V.x.y.z when reading.
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
  const major = Number(match[1]);
  const minor = Number(match[2]);
  const patch = Number(match[3]) + 1;
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

function main() {
  const current = readVersion(VERSION_FILES[0]);
  const next = bumpVersionString(current);

  for (const filePath of VERSION_FILES) {
    replaceVersionInFile(filePath, next);
  }

  console.log(`${current} -> ${next}`);
  return next;
}

main();
