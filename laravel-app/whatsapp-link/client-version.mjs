import fs from 'fs'
import path from 'path'

export function bundledBaileysVersion(packageRoot) {
  const file = path.join(packageRoot, 'node_modules/@whiskeysockets/baileys/lib/Defaults/baileys-version.json')
  const parsed = JSON.parse(fs.readFileSync(file, 'utf8'))
  if (!Array.isArray(parsed.version) || parsed.version.length < 3) {
    throw new Error('WhatsApp version is not available.')
  }
  return parsed.version.map((part) => Number(part))
}

export function shouldSyncFullHistory(registered, historyComplete) {
  return !!registered && !historyComplete
}

export function chooseClientVersion(latest, bundled) {
  if (latest && latest.isLatest && Array.isArray(latest.version) && latest.version.length >= 3) {
    return latest.version.map((part) => Number(part))
  }
  return bundled
}
