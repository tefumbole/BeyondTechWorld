import fs from 'fs'
import http from 'http'
import path from 'path'
import { fileURLToPath } from 'url'
import makeWASocket, {
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

function publicState(entry, withQr) {
  return {
    status: entry.status,
    phone: entry.phone || null,
    qr: withQr && entry.status === 'AWAITING_QR' ? entry.qr : null,
  }
}

function sessionDir(id) {
  return path.join(sessionRoot, id)
}

async function startSocket(id) {
  const existing = sessions.get(id)
  if (existing && existing.sock && existing.status !== 'DISCONNECTED' && existing.status !== 'ERROR') {
    return existing
  }
  const dir = sessionDir(id)
  ensureDir(dir)
  const { state, saveCreds } = await useMultiFileAuthState(dir)
  const version = await fetchLatestBaileysVersion()
  const entry = existing || { status: 'AWAITING_QR', phone: null, qr: null, sock: null, retries: 0 }
  entry.status = entry.status === 'CONNECTED' ? 'CONNECTED' : 'AWAITING_QR'
  sessions.set(id, entry)
  const sock = makeWASocket({
    version: version.version,
    auth: state,
    logger,
    printQRInTerminal: false,
    browser: Browsers.macOS('Beyond Cloud'),
    syncFullHistory: false,
    markOnlineOnConnect: false,
  })
  entry.sock = sock
  sock.ev.on('creds.update', saveCreds)
  sock.ev.on('connection.update', async (update) => {
    if (update.qr) {
      entry.qr = await QRCode.toDataURL(update.qr)
      entry.status = 'AWAITING_QR'
    }
    if (update.connection === 'open') {
      entry.status = 'CONNECTED'
      entry.qr = null
      entry.retries = 0
      const raw = sock.user && sock.user.id ? String(sock.user.id) : ''
      entry.phone = raw.split(':')[0].split('@')[0]
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
      const id = tenantId(body.tenant_id)
      if (!id) {
        send(res, 422, { error: 'invalid' })
        return
      }
      startSocket(id).then((entry) => {
        send(res, 200, publicState(entry, false))
      }).catch(() => {
        send(res, 500, { status: 'ERROR', phone: null, qr: null })
      })
    })
    return
  }
  if (parts[0] === 'sessions' && parts[1]) {
    const id = tenantId(parts[1])
    if (!id) {
      send(res, 422, { error: 'invalid' })
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
      const entry = sessions.get(id) || { status: 'NOT_CONNECTED', phone: null, qr: null }
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
    if (!tenantId(id)) {
      return
    }
    if (fs.existsSync(path.join(sessionDir(id), 'creds.json'))) {
      startSocket(id).catch(() => {})
    }
  })
})
