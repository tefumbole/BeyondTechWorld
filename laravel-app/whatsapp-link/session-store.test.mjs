import assert from 'node:assert/strict'
import fs from 'fs'
import os from 'os'
import path from 'path'
import test from 'node:test'
import {
  appendOutbox,
  backoffMs,
  isProtectedPhone,
  opaqueId,
  sealCreds,
  unlinkKeepHistory,
  unsealCreds,
} from './session-store.mjs'

test('the business number cannot be paired here', () => {
  assert.equal(isProtectedPhone('+237675321739'), true)
  assert.equal(isProtectedPhone('675321739'), true)
  assert.equal(isProtectedPhone('237670000111'), false)
})

test('session ids are opaque', () => {
  assert.equal(opaqueId('s' + 'a'.repeat(32)).length, 33)
  assert.equal(opaqueId('p237670000111'), '')
  assert.equal(opaqueId('237675321739'), '')
})

test('reconnect waits grow and then stop growing', () => {
  assert.equal(backoffMs(1), 2000)
  assert.ok(backoffMs(2) > backoffMs(1))
  assert.equal(backoffMs(8), 30000)
})

test('credentials round-trip encrypted and history stays after unlink', () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'mai-session-'))
  fs.writeFileSync(path.join(dir, 'creds.json'), '{"me":"secret"}')
  fs.writeFileSync(path.join(dir, 'chats.json'), '{"inbox":[{"id":"m1"}]}')
  sealCreds(dir, 'test-key')
  assert.equal(fs.existsSync(path.join(dir, 'creds.json')), false)
  assert.equal(fs.existsSync(path.join(dir, 'creds.json.enc')), true)
  unsealCreds(dir, 'test-key')
  assert.equal(fs.readFileSync(path.join(dir, 'creds.json'), 'utf8'), '{"me":"secret"}')
  unlinkKeepHistory(dir)
  assert.equal(fs.existsSync(path.join(dir, 'creds.json')), false)
  assert.equal(fs.readFileSync(path.join(dir, 'chats.json'), 'utf8'), '{"inbox":[{"id":"m1"}]}')
  const box = path.join(dir, 'out')
  appendOutbox(box, { event_id: 'e1', session_id: 's' + 'b'.repeat(32), kind: 'message' })
  const lines = fs.readFileSync(path.join(box, 's' + 'b'.repeat(32) + '.jsonl'), 'utf8').trim().split('\n')
  assert.equal(lines.length, 1)
})
