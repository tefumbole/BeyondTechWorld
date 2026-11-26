import crypto from 'crypto'
import fs from 'fs'
import path from 'path'

const protectedPhones = new Set(['237675321739', '675321739'])

export function digits(phone) {
  return String(phone || '').replace(/\D/g, '')
}

export function isProtectedPhone(phone) {
  return protectedPhones.has(digits(phone))
}

export function opaqueId(value) {
  const raw = String(value || '')
  return /^s[a-f0-9]{32}$/.test(raw) ? raw : ''
}

export function backoffMs(attempt) {
  const step = Math.min(Math.max(Number(attempt) || 1, 1), 5)
  return Math.min(30000, 1000 * Math.pow(2, step))
}

export function atomicWrite(file, data) {
  const dir = path.dirname(file)
  fs.mkdirSync(dir, { recursive: true })
  const temp = file + '.tmp-' + process.pid
  fs.writeFileSync(temp, data)
  fs.renameSync(temp, file)
}

function keyBytes(secret) {
  return crypto.createHash('sha256').update(String(secret)).digest()
}

export function sealFile(file, secret) {
  if (!secret || !fs.existsSync(file)) return
  const iv = crypto.randomBytes(12)
  const cipher = crypto.createCipheriv('aes-256-gcm', keyBytes(secret), iv)
  const enc = Buffer.concat([cipher.update(fs.readFileSync(file)), cipher.final()])
  atomicWrite(file + '.enc', Buffer.concat([iv, cipher.getAuthTag(), enc]))
  fs.unlinkSync(file)
}

export function unsealFile(file, secret) {
  const sealed = file + '.enc'
  if (!secret || !fs.existsSync(sealed) || fs.existsSync(file)) return
  const raw = fs.readFileSync(sealed)
  const iv = raw.subarray(0, 12)
  const tag = raw.subarray(12, 28)
  const enc = raw.subarray(28)
  const decipher = crypto.createDecipheriv('aes-256-gcm', keyBytes(secret), iv)
  decipher.setAuthTag(tag)
  atomicWrite(file, Buffer.concat([decipher.update(enc), decipher.final()]))
}

export function sealCreds(dir, secret) {
  sealFile(path.join(dir, 'creds.json'), secret)
}

export function unsealCreds(dir, secret) {
  unsealFile(path.join(dir, 'creds.json'), secret)
}

export function claimLock(dir) {
  fs.mkdirSync(dir, { recursive: true })
  const file = path.join(dir, 'owner.lock')
  try {
    const pid = Number(fs.readFileSync(file, 'utf8').trim())
    if (pid && pid !== process.pid && alive(pid)) return false
  } catch (e) {}
  atomicWrite(file, String(process.pid))
  return true
}

function alive(pid) {
  try {
    process.kill(pid, 0)
    return true
  } catch (e) {
    return false
  }
}

export function unlinkKeepHistory(dir) {
  if (!fs.existsSync(dir)) return
  fs.readdirSync(dir).forEach((name) => {
    if (name === 'chats.json') return
    fs.rmSync(path.join(dir, name), { recursive: true, force: true })
  })
}

export function appendOutbox(dir, event) {
  if (!event || !event.event_id || !event.session_id) return
  fs.mkdirSync(dir, { recursive: true })
  const file = path.join(dir, event.session_id + '.jsonl')
  fs.appendFileSync(file, JSON.stringify(event) + '\n')
}
