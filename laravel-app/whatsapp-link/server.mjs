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
import {
  appendOutbox,
  backoffMs,
  claimLock,
  isProtectedPhone,
  opaqueId,
  sealCreds,
  unlinkKeepHistory,
  unsealCreds,
} from './session-store.mjs'

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
  return opaqueId(value) || tenantId(value) || (/^p[1-9][0-9]{7,14}$/.test(String(value || '')) ? String(value) : '')
}

function emit(entry, event) {
  if (!entry || !entry.id) return
  appendOutbox(path.join(root, 'outbox'), Object.assign({ session_id: entry.id }, event))
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

function jidDigits(jid) {
  return String(jid || '').split('@')[0].split(':')[0].replace(/\D/g, '')
}

function scheduleSave(entry) {
  if (!entry || !entry.id) return
  clearTimeout(entry.saveTimer)
  entry.saveTimer = setTimeout(() => {
    try {
      fs.writeFileSync(path.join(sessionDir(entry.id), 'chats.json'), JSON.stringify({
        chats: entry.chats || {},
        inbox: (entry.inbox || []).slice(-200),
      }))
    } catch (e) {}
  }, 400)
}

function loadSavedChats(entry) {
  try {
    const saved = JSON.parse(fs.readFileSync(path.join(sessionDir(entry.id), 'chats.json'), 'utf8'))
    entry.chats = saved && saved.chats ? saved.chats : {}
    entry.inbox = saved && Array.isArray(saved.inbox) ? saved.inbox : []
  } catch (e) {
    if (!entry.chats) entry.chats = {}
    if (!entry.inbox) entry.inbox = []
  }
}

function touchChat(entry, jid, patch) {
  const id = String(jid || '')
  if (!id || id.indexOf('@broadcast') !== -1) return
  if (!entry.chats) entry.chats = {}
  const current = entry.chats[id] || { jid: id, name: '', preview: '', at: 0, unread: 0 }
  if (patch.name) current.name = String(patch.name).slice(0, 80)
  if (patch.preview) current.preview = String(patch.preview).slice(0, 140)
  if (patch.at) current.at = Number(patch.at) || current.at
  if (typeof patch.unread === 'number') current.unread = patch.unread
  if (!current.name) {
    const digits = jidDigits(id)
    if (digits) current.name = '+' + digits
  }
  entry.chats[id] = current
  scheduleSave(entry)
}

function chatRows(entry) {
  const rows = Object.keys(entry.chats || {}).map((jid) => entry.chats[jid])
  rows.sort((left, right) => (right.at || 0) - (left.at || 0))
  return rows.slice(0, 80).map((row) => ({
    jid: row.jid,
    name: row.name || '',
    phone: jidDigits(row.jid),
    preview: row.preview || '',
    at: row.at || 0,
    unread: row.unread || 0,
  }))
}

function rememberMessage(entry, msg) {
  if (!msg || !msg.key) return
  const text = messageText(msg.message).trim()
  const jid = String(msg.key.remoteJid || '')
  const at = Number(msg.messageTimestamp) || 0
  if (jid) {
    touchChat(entry, jid, {
      name: !msg.key.fromMe && msg.pushName ? msg.pushName : '',
      preview: text || (msg.message && msg.message.imageMessage ? 'Photo' : ''),
      at: at,
    })
  }
  if (!text) return
  if (!entry.inbox) entry.inbox = []
  entry.inbox.push({
    id: String(msg.key.id || ''),
    from: jid,
    from_me: !!msg.key.fromMe,
    text: text.slice(0, 1000),
    at: String(msg.messageTimestamp || ''),
  })
  if (entry.inbox.length > 200) entry.inbox.splice(0, entry.inbox.length - 200)
  scheduleSave(entry)
  if (msg.key.id) {
    emit(entry, {
      event_id: String(msg.key.id),
      kind: 'message',
      from_me: !!msg.key.fromMe,
      text: text.slice(0, 1000),
      remote: jidDigits(jid),
      at: String(msg.messageTimestamp || ''),
    })
  }
}

async function attachLids(entry) {
  if (!entry || !entry.sock || entry.lidScan || typeof entry.sock.onWhatsApp !== 'function') return
  entry.lidScan = true
  const phones = Object.keys(entry.chats || {}).filter((jid) => jid.indexOf('@s.whatsapp.net') !== -1).slice(0, 40)
  if (!phones.length) return
  try {
    const rows = await entry.sock.onWhatsApp.apply(entry.sock, phones)
    const lids = entry.lids || {}
    ;(rows || []).forEach((row) => {
      if (!row || !row.lid || !row.jid) return
      const lid = String(row.lid).indexOf('@') === -1 ? String(row.lid) + '@lid' : String(row.lid)
      lids[lid] = String(row.jid)
    })
    entry.lids = lids
    ;(entry.inbox || []).forEach((msg) => {
      if (lids[msg.from]) msg.from = lids[msg.from]
    })
    scheduleSave(entry)
  } catch (e) {}
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
  if (!claimLock(dir)) {
    throw new Error('This session already has a worker.')
  }
  unsealCreds(dir, process.env.MAI_SESSION_KEY || '')
  const { state, saveCreds } = await useMultiFileAuthState(dir)
  const persistCreds = async () => {
    await saveCreds()
    sealCreds(dir, process.env.MAI_SESSION_KEY || '')
  }
  const entry = existing || { status: 'AWAITING_QR', phone: null, qr: null, sock: null, retries: 0, inbox: [], chats: {} }
  entry.id = id
  loadSavedChats(entry)
  const savedChats = Object.keys(entry.chats || {}).length
  entry.recoverChats = savedChats === 0
  if (entry.recoverChats && state.creds) {
    if (Array.isArray(state.creds.processedHistoryMessages) && state.creds.processedHistoryMessages.length) {
      state.creds.processedHistoryMessages = []
    }
    state.creds.accountSyncCounter = 0
    await saveCreds()
    fs.readdirSync(dir).forEach((file) => {
      if (file.indexOf('app-state-sync-version-') === 0) {
        fs.rmSync(path.join(dir, file), { force: true })
      }
    })
  }
  const version = await fetchLatestBaileysVersion()
  if (!entry.chats) entry.chats = {}
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
  sock.ev.on('creds.update', persistCreds)
  sock.ev.on('messages.upsert', (packet) => {
    const list = packet && packet.messages ? packet.messages : []
    list.forEach((msg) => rememberMessage(entry, msg))
  })
  sock.ev.on('messaging-history.set', (payload) => {
    const chats = payload && payload.chats ? payload.chats : []
    const contacts = payload && payload.contacts ? payload.contacts : []
    const messages = payload && payload.messages ? payload.messages : []
    chats.forEach((chat) => touchChat(entry, chat.id, {
      name: chat.name || '',
      at: Number(chat.conversationTimestamp) || 0,
      unread: Number(chat.unreadCount) || 0,
    }))
    contacts.forEach((person) => touchChat(entry, person.id, { name: person.name || person.notify || '' }))
    messages.forEach((msg) => rememberMessage(entry, msg))
  })
  sock.ev.on('chats.upsert', (chats) => {
    ;(chats || []).forEach((chat) => touchChat(entry, chat.id, {
      name: chat.name || '',
      at: Number(chat.conversationTimestamp) || 0,
      unread: Number(chat.unreadCount) || 0,
    }))
  })
  sock.ev.on('chats.update', (chats) => {
    ;(chats || []).forEach((chat) => {
      if (!chat || !chat.id) return
      touchChat(entry, chat.id, {
        name: chat.name || '',
        at: Number(chat.conversationTimestamp) || 0,
        unread: typeof chat.unreadCount === 'number' ? chat.unreadCount : undefined,
      })
    })
  })
  sock.ev.on('contacts.upsert', (people) => {
    ;(people || []).forEach((person) => touchChat(entry, person.id, { name: person.name || person.notify || '' }))
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
      if (entry.recoverChats && !entry.historyAsked && typeof sock.resyncAppState === 'function') {
        entry.historyAsked = true
        sock.resyncAppState(['critical_block', 'critical_unblock_low', 'regular_high', 'regular_low', 'regular'], true)
          .then(() => {
            console.error('mai chats ' + id + ' ' + Object.keys(entry.chats || {}).length)
          })
          .catch((error) => {
            const message = error && error.message ? String(error.message) : 'sync failed'
            console.error('mai chat sync ' + message.slice(0, 140))
          })
      }
      emit(entry, { event_id: 'open-' + Date.now(), kind: 'status', status: 'CONNECTED', phone: entry.phone || '' })
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
        entry.status = code === DisconnectReason.loggedOut ? 'UNLINKED' : 'DISCONNECTED'
        entry.qr = null
        entry.pairing = null
        unlinkKeepHistory(dir)
        emit(entry, { event_id: 'unlink-' + Date.now(), kind: 'status', status: 'UNLINKED' })
        return
      }
      entry.retries += 1
      entry.status = 'ERROR'
      if (entry.retries <= 5) {
        setTimeout(() => {
          startSocket(id).catch(() => {
            entry.status = 'ERROR'
          })
        }, backoffMs(entry.retries))
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
      if (raw.length > 800) {
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
      if (isProtectedPhone(body.phone)) {
        send(res, 403, { error: 'protected' })
        return
      }
      const id = opaqueId(body.session_id) || (body.phone ? phoneKey(body.phone) : tenantId(body.tenant_id))
      if (!id) {
        send(res, 422, { error: 'invalid' })
        return
      }
      const expected = body.phone ? String(body.phone).replace(/\D/g, '') : (id.indexOf('p') === 0 ? id.slice(1) : '')
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
    if (req.method === 'GET' && parts[2] === 'chats') {
      const entry = sessions.get(id) || { chats: {} }
      attachLids(entry).catch(() => {})
      send(res, 200, { phone: entry.phone || null, chats: chatRows(entry) })
      return
    }
    if (req.method === 'GET' && parts[2] === 'messages') {
      const entry = sessions.get(id) || { inbox: [] }
      const ready = entry.lidScan ? Promise.resolve() : attachLids(entry).catch(() => {})
      ready.then(() => send(res, 200, { messages: entry.inbox || [] }))
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
      unlinkKeepHistory(sessionDir(id))
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
    if (fs.existsSync(path.join(sessionDir(id), 'creds.json')) || fs.existsSync(path.join(sessionDir(id), 'creds.json.enc'))) {
      startSocket(id).catch(() => {})
    }
  })
})
