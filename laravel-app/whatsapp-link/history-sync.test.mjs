import assert from 'node:assert/strict'
import fs from 'fs'
import { classifyHistory, historyMarkerComplete, historySyncNote } from './history-sync.mjs'

assert.equal(historyMarkerComplete('1'), false)
assert.equal(historyMarkerComplete('100'), true)
assert.equal(historyMarkerComplete(''), false)

const firstChunk = classifyHistory({ syncType: 0, progress: 0, isLatest: true, messages: [{ key: { id: 'a' } }] })
assert.equal(firstChunk.complete, false)
assert.equal(firstChunk.page, false)

const partial = classifyHistory({ syncType: 2, progress: 40, isLatest: false })
assert.equal(partial.complete, false)

const done = classifyHistory({ syncType: 2, progress: 100, isLatest: false })
assert.equal(done.complete, true)

const missing = classifyHistory({ syncType: 2, isLatest: true })
assert.equal(missing.complete, false)

const delayed = classifyHistory({
  syncType: 6,
  messages: [{ key: { remoteJid: '237699000333@s.whatsapp.net', id: 'old' } }],
}, '237688000111@s.whatsapp.net')
assert.equal(delayed.page, true)
assert.equal(delayed.complete, false)
assert.equal(delayed.state, 'received')
assert.equal(delayed.jid, '237699000333@s.whatsapp.net')

const emptyPage = classifyHistory({ syncType: 6, messages: [] }, '237688000111@s.whatsapp.net')
assert.equal(emptyPage.state, 'unavailable')
assert.equal(emptyPage.jid, '237688000111@s.whatsapp.net')
assert.equal(emptyPage.complete, false)

const partialNote = historySyncNote(partial, { contacts: 9, chats: 2 })
assert.equal(partialNote.ready, false)
assert.equal(partialNote.contacts, 9)
assert.equal(partialNote.chats, 2)
assert.equal(partialNote.progress, 40)

const doneNote = historySyncNote(done, { contacts: 3, chats: 1 })
assert.equal(doneNote.ready, true)
assert.equal(doneNote.contacts, 3)
assert.equal(doneNote.chats, 1)

const source = fs.readFileSync(new URL('./server.mjs', import.meta.url), 'utf8')
const historyAt = source.indexOf("sock.ev.on('messaging-history.set'")
const contactsAt = source.indexOf("sock.ev.on('contacts.upsert'")
const chatsAt = source.indexOf("sock.ev.on('chats.upsert'")
const openAt = source.indexOf("update.connection === 'open'")
assert.ok(historyAt > 0 && contactsAt > 0 && chatsAt > 0 && openAt > historyAt && openAt > contactsAt && openAt > chatsAt)
assert.ok(source.includes('historySyncNote'))
assert.ok(source.includes("parts[2] === 'sync'"))
assert.ok(source.includes('const historyOpen = !historyIsComplete(dir)'))
assert.ok(source.includes('registered && historyOpen'))
assert.ok(source.includes('await waitForPair(entry, 1200)'))
assert.equal(source.includes('await waitForPair(entry, 5000)'), false)
assert.ok(source.includes('shouldSyncFullHistory(entry.registered, historyIsComplete(dir)) && !entry.scanned'))
