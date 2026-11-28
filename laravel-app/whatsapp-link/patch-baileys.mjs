import fs from 'fs'
import { createRequire } from 'module'

const require = createRequire(import.meta.url)
const file = require.resolve('@whiskeysockets/baileys/lib/Utils/chat-utils.js')
const src = fs.readFileSync(file, 'utf8')
const plain = "ev.emit('contacts.upsert', [{ id, name: action.contactAction.fullName }]);"
const patched = "ev.emit('contacts.upsert', [{ id, name: action.contactAction.fullName || action.contactAction.firstName, phoneNumber: action.contactAction.pnJid, lid: action.contactAction.lidJid }]);"
if (src.indexOf(patched) !== -1) {
  // Already patched — never process.exit here; that kills the whole link worker.
} else if (src.indexOf(plain) !== -1) {
  fs.writeFileSync(file, src.replace(plain, patched))
}
