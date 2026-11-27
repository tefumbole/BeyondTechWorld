import assert from 'node:assert/strict'
import { classifyHistory, historyMarkerComplete } from './history-sync.mjs'

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
