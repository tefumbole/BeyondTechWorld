import fs from 'fs'
import { createRequire } from 'module'

const require = createRequire(import.meta.url)
const file = require.resolve('@whiskeysockets/baileys/lib/Utils/chat-utils.js')
const src = fs.readFileSync(file, 'utf8')
const needle = "ev.emit('contacts.upsert', [{ id, name: action.contactAction.fullName }]);"
const replacement = "ev.emit('contacts.upsert', [{ id, name: action.contactAction.fullName || action.contactAction.firstName, phoneNumber: action.contactAction.pnJid, lid: action.contactAction.lidJid }]);"
if (src.indexOf(needle) !== -1) {
  fs.writeFileSync(file, src.replace(needle, replacement))
}
