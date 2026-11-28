import fs from 'fs'
import os from 'os'
import path from 'path'
import { findStoredMessage, mediaLimitReason, mediaTooLarge } from './store-lookup.mjs'

const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'mai-preflight-'))
const small = path.join(dir, 'below.pdf')
const big = path.join(dir, 'above.pdf')
fs.writeFileSync(small, Buffer.concat([Buffer.from('%PDF-1.4\n'), Buffer.alloc(2048, 0x20)]))
fs.writeFileSync(big, Buffer.concat([Buffer.from('%PDF-1.4\n'), Buffer.alloc(8 * 1024 * 1024, 0x20)]))
const smallBytes = fs.statSync(small).size
const bigBytes = fs.statSync(big).size
if (mediaTooLarge(smallBytes) || !mediaTooLarge(bigBytes)) {
  throw new Error('size gate failed ' + smallBytes + ' ' + bigBytes)
}

const session = path.join(dir, 'session')
fs.mkdirSync(session)
const lines = []
for (let i = 0; i < 600; i++) {
  lines.push(JSON.stringify({ kind: 'message', id: 'M' + i, text: 'body ' + i, type: 'TEXT' }))
}
lines.push(JSON.stringify({ kind: 'message', id: 'QUOTE1', text: 'the quoted sentence', type: 'TEXT', file: 'sessions/p1/media/quote.pdf' }))
fs.writeFileSync(path.join(session, 'events.jsonl'), lines.join('\n') + '\n')
const oldest = findStoredMessage(session, 'M0')
const quoted = findStoredMessage(session, 'QUOTE1')
if (!oldest || oldest.text !== 'body 0') throw new Error('oldest message was not in the event log')
if (!quoted || quoted.text !== 'the quoted sentence' || quoted.file !== 'sessions/p1/media/quote.pdf') {
  throw new Error('quoted message was not recovered')
}

console.log(JSON.stringify({
  below: { file: 'below.pdf', bytes: smallBytes, accepted: true },
  above: { file: 'above.pdf', bytes: bigBytes, accepted: false, reason: mediaLimitReason() },
  beyondCache: { oldest: oldest.text, quoted: quoted.text, quotedFile: quoted.file },
}))
