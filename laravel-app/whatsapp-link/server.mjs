import fs from 'fs'
import http from 'http'
import path from 'path'
import { fileURLToPath } from 'url'
import {
  makeWASocket,
  Browsers,
  DisconnectReason,
  fetchLatestBaileysVersion,
  useMultiFileAuthState,
} from '@whiskeysockets/baileys'
import pino from 'pino'
import QRCode from 'qrcode'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../storage/app/whatsapp-link')
const tokenPath = path.join(root, 'token')
const sessionRoot = path.join(root, 'sessions')
const port = 3921
const logger = pino({ level: 'silent' })
const sessions = new Map()

function ensureDir(dir) {
  fs.mkdirSync(dir, { recursive: true })
}

function readToken() {
  try {
    return fs.readFileSync(tokenPath, 'utf8').trim()
  } catch (e) {
    return ''
  }
}

function tenantId(value) {
  const id = String(value || '')
  if (!/^[1-9][0-9]{0,8}$/.test(id)) {
    return ''
  }
  return id
}

function phoneKey(phone) {
  const digits = String(phone || '').replace(/\D/g, '')
  if (!/^[1-9][0-9]{7,14}$/.test(digits)) {
    return ''
  }
  return 'p' + digits
}

function sessionKey(value) {
  return tenantId(value) || (/^p[1-9][0-9]{7,14}$/.test(String(value || '')) ? String(value) : '')
}

function samePhone(expected, reported) {
  const left = String(expected || '').replace(/\D/g, '')
  const right = String(reported || '').replace(/\D/g, '')
  if (left.length < 8 || right.length < 8) {
    return true
  }
  return left.slice(-8) === right.slice(-8)
}

function publicState(entry, withQr) {
  return {
    status: entry.status,
    phone: entry.phone || null,
    qr: withQr && entry.status === 'AWAITING_QR' ? entry.qr : null,
    pairing_code: entry.pairing || null,
    error: entry.error || null,
  }
}

function messageText(message) {
  if (!message) return ''
  if (typeof message.conversation === 'string') return message.conversation
  if (message.extendedTextMessage && message.extendedTextMessage.text) return message.extendedTextMessage.text
  if (message.imageMessage) return message.imageMessage.caption || '[image]'
  if (message.videoMessage) return '[video]'
  if (message.audioMessage) return '[voice]'
  if (message.documentMessage) return '[document]'
  return ''
}

function rememberMessage(entry, msg) {
  if (!msg || !msg.key) return
  const text = messageText(msg.message).trim()
  if (!text) return
  if (!entry.inbox) entry.inbox = []
  entry.inbox.push({
    id: String(msg.key.id || ''),
    from: String(msg.key.remoteJid || ''),
    from_me: !!msg.key.fromMe,
    text: text.slice(0, 1000),
    at: String(msg.messageTimestamp || ''),
  })
  if (entry.inbox.length > 40) entry.inbox.splice(0, entry.inbox.length - 40)
}

function sessionDir(id) {
  return path.join(sessionRoot, id)
}

async function startSocket(id, expected) {
  const existing = sessions.get(id)
  if (expected) {
    if (existing) existing.expected = expected
  }
  if (existing && existing.sock && existing.status !== 'DISCONNECTED' && existing.status !== 'ERROR') {
    return existing
  }
  const dir = sessionDir(id)
  ensureDir(dir)
  const { state, saveCreds } = await useMultiFileAuthState(dir)
  const version = await fetchLatestBaileysVersion()
  const entry = existing || { status: 'AWAITING_QR', phone: null, qr: null, sock: null, retries: 0, inbox: [] }
  if (expected) entry.expected = expected
  entry.status = entry.status === 'CONNECTED' ? 'CONNECTED' : 'AWAITING_QR'
  entry.error = null
  entry.qr = null
  entry.pairing = null
  sessions.set(id, entry)
  const sock = makeWASocket({
    version: version.version,
    auth: state,
    logger,
    printQRInTerminal: false,
    browser: Browsers.macOS('Whats MAI'),
    syncFullHistory: false,
    markOnlineOnConnect: false,
  })
  entry.sock = sock
  sock.ev.on('creds.update', saveCreds)
  sock.ev.on('messages.upsert', (packet) => {
    const list = packet && packet.messages ? packet.messages : []
    list.forEach((msg) => rememberMessage(entry, msg))
  })
  sock.ev.on('connection.update', async (update) => {
    if (update.qr) {
      entry.qr = await QRCode.toDataURL(update.qr)
      entry.status = 'AWAITING_QR'
    }
    if (update.connection === 'open') {
      entry.status = 'CONNECTED'
      entry.qr = null
      entry.pairing = null
      entry.retries = 0
      const raw = sock.user && sock.user.id ? String(sock.user.id) : ''
      entry.phone = raw.split(':')[0].split('@')[0]
      if (entry.expected && !samePhone(entry.expected, entry.phone)) {
        entry.status = 'ERROR'
        entry.error = 'This WhatsApp is a different number.'
        entry.closing = true
        sock.logout().catch(() => {})
      }
    }
    if (update.connection === 'close') {
      const code = update.lastDisconnect && update.lastDisconnect.error && update.lastDisconnect.error.output
        ? update.lastDisconnect.error.output.statusCode
        : 0
      entry.sock = null
      if (entry.closing || code === DisconnectReason.loggedOut) {
        entry.status = 'DISCONNECTED'
        entry.qr = null
        entry.phone = null
        fs.rmSync(dir, { recursive: true, force: true })
        return
      }
      entry.retries += 1
      entry.status = 'ERROR'
      if (entry.retries <= 5) {
        setTimeout(() => {
          startSocket(id).catch(() => {
            entry.status = 'ERROR'
          })
        }, 3000)
      }
    }
  })
  return entry
}

function waitForQr(entry, ms) {
  if (!entry || entry.qr || entry.status === 'CONNECTED') {
    return Promise.resolve()
  }
  return new Promise((resolve) => {
    const started = Date.now()
    const timer = setInterval(() => {
      if (entry.qr || entry.status === 'CONNECTED' || entry.status === 'ERROR' || Date.now() - started >= ms) {
        clearInterval(timer)
        resolve()
      }
    }, 200)
  })
}

async function pairingFor(id) {
  const entry = await startSocket(id, id.indexOf('p') === 0 ? id.slice(1) : '')
  if (!entry.sock || entry.status === 'CONNECTED') {
    return publicState(entry, false)
  }
  if (entry.pairing) {
    return publicState(entry, false)
  }
  if (typeof entry.sock.requestPairingCode !== 'function') {
    entry.error = 'The link code is not available on this engine.'
    return publicState(entry, false)
  }
  await waitForQr(entry, 8000)
  const phone = String(entry.expected || (id.indexOf('p') === 0 ? id.slice(1) : '')).replace(/\D/g, '')
  const code = await entry.sock.requestPairingCode(phone)
  entry.pairing = String(code || '').replace(/[^A-Za-z0-9-]/g, '').slice(0, 17)
  if (!entry.pairing) {
    entry.error = 'The link code could not be created.'
  }
  return publicState(entry, false)
}

async function sendText(id, to, text) {
  const entry = sessions.get(id)
  if (!entry || !entry.sock || entry.status !== 'CONNECTED') {
    return { success: false, error: 'WhatsApp is not linked for this phone yet.' }
  }
  const clean = String(text || '').trim()
  if (!clean || clean.length > 1000) {
    return { success: false, error: 'Write a message.' }
  }
  let jid = String(to || '').trim()
  if (jid.indexOf('@') === -1) {
    const digits = jid.replace(/\D/g, '')
    if (!/^[1-9][0-9]{7,14}$/.test(digits)) {
      return { success: false, error: 'Enter the other phone number.' }
    }
    jid = digits + '@s.whatsapp.net'
  } else if (!/^([1-9][0-9]{7,14}@s\.whatsapp\.net|[0-9]+(-[0-9]+)?@g\.us)$/.test(jid)) {
    return { success: false, error: 'That chat is not available.' }
  }
  const sent = await entry.sock.sendMessage(jid, { text: clean })
  return { success: true, id: sent && sent.key ? sent.key.id : null }
}

function readBody(req, limit, done) {
  let raw = ''
  req.on('data', (chunk) => {
    raw += chunk
    if (raw.length > limit) req.destroy()
  })
  req.on('end', () => {
    try {
      done(JSON.parse(raw || '{}'))
    } catch (e) {
      done(null)
    }
  })
}

async function groupsFor(id) {
  const entry = sessions.get(id)
  if (!entry || !entry.sock || entry.status !== 'CONNECTED') {
    return { success: false, error: 'WhatsApp is not connected for this company.', groups: [] }
  }
  const listed = await entry.sock.groupFetchAllParticipating()
  const groups = Object.keys(listed || {}).slice(0, 500).map((jid) => {
    const group = listed[jid] || {}
    return {
      jid,
      name: group.subject || '',
      members: Array.isArray(group.participants) ? group.participants.length : 0,
    }
  }).filter((group) => group.name !== '')
  return { success: true, groups }
}

async function groupFor(id, jid) {
  const entry = sessions.get(id)
  if (!entry || !entry.sock || entry.status !== 'CONNECTED') {
    return { success: false, error: 'WhatsApp is not connected for this company.', name: '', participants: [] }
  }
  if (!jid || !jid.endsWith('@g.us')) {
    return { success: false, error: 'Choose a WhatsApp group.', name: '', participants: [] }
  }
  const meta = await entry.sock.groupMetadata(jid)
  const participants = (meta.participants || []).map((person) => {
    const raw = String(person.id || '')
    return {
      phone: raw.split(':')[0].split('@')[0],
      name: person.name || '',
      role: person.admin ? 'admin' : 'member',
    }
  })
  return {
    success: true,
    name: meta.subject || '',
    members: participants.length,
    participants,
  }
}

function send(res, status, payload) {
  const body = JSON.stringify(payload)
  res.writeHead(status, {
    'Content-Type': 'application/json',
    'Cache-Control': 'no-store, private',
    'Content-Length': Buffer.byteLength(body),
  })
  res.end(body)
}

function authorized(req) {
  const token = readToken()
  const header = String(req.headers.authorization || '')
  return token !== '' && header === 'Bearer ' + token
}

const server = http.createServer((req, res) => {
  if (!authorized(req)) {
    send(res, 401, { error: 'unauthorized' })
    return
  }
  const url = new URL(req.url, 'http://127.0.0.1')
  const parts = url.pathname.split('/').filter(Boolean)
  if (req.method === 'GET' && url.pathname === '/health') {
    send(res, 200, { ok: true })
    return
  }
  if (req.method === 'POST' && url.pathname === '/sessions') {
    let raw = ''
    req.on('data', (chunk) => {
      raw += chunk
      if (raw.length > 200) {
        req.destroy()
      }
    })
    req.on('end', () => {
      let body = {}
      try {
        body = JSON.parse(raw || '{}')
      } catch (e) {
        send(res, 422, { error: 'invalid' })
        return
      }
      const id = body.phone ? phoneKey(body.phone) : tenantId(body.tenant_id)
      if (!id) {
        send(res, 422, { error: 'invalid' })
        return
      }
      const expected = body.phone ? id.slice(1) : ''
      startSocket(id, expected).then((entry) => {
        const ready = entry.qr || entry.status === 'CONNECTED' ? Promise.resolve() : waitForQr(entry, 8000)
        ready.then(() => send(res, 200, publicState(entry, true)))
      }).catch((error) => {
        const message = error && error.message ? String(error.message).slice(0, 180) : 'WhatsApp could not be started.'
        console.error('session start failed', message)
        send(res, 500, { status: 'ERROR', phone: null, qr: null, pairing_code: null, error: message })
      })
    })
    return
  }
  if (parts[0] === 'sessions' && parts[1]) {
    const id = sessionKey(parts[1])
    if (!id) {
      send(res, 422, { error: 'invalid' })
      return
    }
    if (req.method === 'POST' && parts[2] === 'pair') {
      pairingFor(id).then((payload) => send(res, 200, payload)).catch((error) => {
        const message = error && error.message ? String(error.message).slice(0, 180) : 'The link code could not be created.'
        console.error('pairing code failed', message)
        send(res, 200, { status: 'ERROR', phone: null, qr: null, pairing_code: null, error: message })
      })
      return
    }
    if (req.method === 'GET' && parts[2] === 'messages') {
      const entry = sessions.get(id) || { inbox: [] }
      send(res, 200, { messages: entry.inbox || [] })
      return
    }
    if (req.method === 'POST' && parts[2] === 'send') {
      readBody(req, 4000, (body) => {
        if (!body) {
          send(res, 422, { success: false, error: 'invalid' })
          return
        }
        sendText(id, body.to, body.text).then((payload) => send(res, 200, payload)).catch(() => {
          send(res, 200, { success: false, error: 'The message was not sent.' })
        })
      })
      return
    }
    if (req.method === 'GET' && parts[2] === 'groups') {
      groupsFor(id).then((payload) => send(res, 200, payload)).catch(() => {
        send(res, 200, { success: false, error: 'Could not load groups.', groups: [] })
      })
      return
    }
    if (req.method === 'GET' && parts[2] === 'group') {
      groupFor(id, url.searchParams.get('jid') || '').then((payload) => send(res, 200, payload)).catch(() => {
        send(res, 200, { success: false, error: 'Could not read this group.', name: '', participants: [] })
      })
      return
    }
    if (req.method === 'GET' && !parts[2]) {
      const entry = sessions.get(id) || { status: 'NOT_CONNECTED', phone: null, qr: null, pairing: null, error: null }
      send(res, 200, publicState(entry, url.searchParams.get('qr') === '1'))
      return
    }
    if (req.method === 'DELETE' && !parts[2]) {
      const entry = sessions.get(id)
      if (entry) {
        entry.closing = true
        entry.status = 'DISCONNECTED'
        entry.qr = null
        const sock = entry.sock
        entry.sock = null
        if (sock) {
          sock.logout().catch(() => {})
        }
      }
      fs.rmSync(sessionDir(id), { recursive: true, force: true })
      send(res, 200, { status: 'DISCONNECTED', phone: null, qr: null })
      return
    }
  }
  send(res, 404, { error: 'not_found' })
})

ensureDir(sessionRoot)
server.listen(port, '127.0.0.1', () => {
  fs.readdirSync(sessionRoot).forEach((id) => {
    if (!sessionKey(id)) {
      return
    }
    if (fs.existsSync(path.join(sessionDir(id), 'creds.json'))) {
      startSocket(id).catch(() => {})
    }
  })
})
