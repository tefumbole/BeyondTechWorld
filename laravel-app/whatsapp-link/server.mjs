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
        lids: entry.lids || {},
        book: entry.book || {},
      }))
    } catch (e) {}
  }, 400)
}

function loadSavedChats(entry) {
  try {
    const saved = JSON.parse(fs.readFileSync(path.join(sessionDir(entry.id), 'chats.json'), 'utf8'))
    entry.chats = saved && saved.chats ? saved.chats : {}
    entry.inbox = saved && Array.isArray(saved.inbox) ? saved.inbox : []
    entry.lids = saved && saved.lids ? saved.lids : {}
    entry.book = saved && saved.book ? saved.book : {}
  } catch (e) {
    if (!entry.chats) entry.chats = {}
    if (!entry.inbox) entry.inbox = []
  }
}

function looksLikeNumber(name) {
  const digits = String(name || '').replace(/\D/g, '')
  return digits.length >= 8 && digits.length >= String(name || '').replace(/[\s+()-]/g, '').length
}

function preferName(current, next) {
  const incoming = String(next || '').trim()
  if (!incoming || looksLikeNumber(incoming)) return current || ''
  if (!current || looksLikeNumber(current)) return incoming.slice(0, 80)
  return current
}

function phoneJid(jid) {
  const id = String(jid || '').split(':')[0]
  if (id.endsWith('@s.whatsapp.net') || id.endsWith('@g.us')) return id
  return ''
}

function when(value) {
  const raw = value && value.toString ? value.toString() : value
  let n = Number(raw) || 0
  if (n > 1000000000000) n = Math.floor(n / 1000)
  return n
}

function rememberLid(entry, lid, phone) {
  const from = String(lid || '').split(':')[0]
  const to = phoneJid(phone)
  if (!from.endsWith('@lid') || !to) return
  if (!entry.lids) entry.lids = {}
  entry.lids[from] = to
  const saved = entry.chats && entry.chats[from]
  if (saved) touchChat(entry, to, { name: saved.name, preview: saved.preview, at: saved.at, unread: saved.unread, trusted: !looksLikeNumber(saved.name) })
}

function rememberContact(entry, person) {
  if (!person) return
  const saved = String(person.name || '').trim()
  const notify = String(person.notify || person.verifiedName || '').trim()
  const phone = phoneJid(person.phoneNumber || (String(person.id || '').indexOf('@s.whatsapp.net') !== -1 ? person.id : ''))
  const lid = String(person.lid || (String(person.id || '').indexOf('@lid') !== -1 ? person.id : '')).split(':')[0]
  if (!entry.book) entry.book = {}
  const known = saved && !looksLikeNumber(saved) ? saved : (notify && !looksLikeNumber(notify) ? notify : '')
  if (phone && known) entry.book[phone] = { name: known.slice(0, 80), saved: saved !== '' && !looksLikeNumber(saved) }
  if (lid && known) entry.book[lid] = { name: known.slice(0, 80), saved: saved !== '' && !looksLikeNumber(saved) }
  if (lid && phone) rememberLid(entry, lid, phone)
  const target = phone || (String(person.id || '').indexOf('@g.us') !== -1 ? String(person.id) : '')
  const existing = target && entry.chats && entry.chats[target] ? target : (lid && entry.chats && entry.chats[lid] ? lid : '')
  if (!existing) return
  touchChat(entry, existing, { name: saved || notify, trusted: saved !== '' && !looksLikeNumber(saved) })
}

function touchChat(entry, jid, patch) {
  let id = String(jid || '').split(':')[0]
  if (!id || id.indexOf('@broadcast') !== -1) return
  if (id.endsWith('@lid') && entry.lids && entry.lids[id]) id = entry.lids[id]
  if (!entry.chats) entry.chats = {}
  const current = entry.chats[id] || { jid: id, name: '', preview: '', at: 0, unread: 0 }
  const book = entry.book && entry.book[id]
  if (book && book.saved && book.name) current.name = book.name
  else if (patch.trusted && patch.name && !looksLikeNumber(patch.name)) current.name = String(patch.name).slice(0, 80)
  else if (patch.name) current.name = preferName(current.name, patch.name)
  if (patch.preview) current.preview = String(patch.preview).slice(0, 140)
  const at = when(patch.at)
  if (at && at >= (current.at || 0)) current.at = at
  if (typeof patch.unread === 'number') current.unread = patch.unread
  if (patch.messaged) current.messaged = true
  if (typeof patch.archived === 'boolean') current.archived = patch.archived
  entry.chats[id] = current
  scheduleSave(entry)
}

function chatRows(entry) {
  const merged = {}
  Object.keys(entry.chats || {}).forEach((jid) => {
    const row = entry.chats[jid]
    if (!row || row.archived || !(row.at > 0)) return
    if (!row.messaged && !row.preview && !(row.unread > 0)) return
    let id = String(jid || '')
    const lid = id.endsWith('@lid') ? id : ''
    if (lid && entry.lids && entry.lids[lid]) id = entry.lids[lid]
    id = phoneJid(id) || (id.endsWith('@g.us') ? id : (id.endsWith('@lid') ? id : ''))
    if (!id || id.endsWith('@lid')) {
      if (!lid) return
      id = lid
    }
    const phone = id.endsWith('@s.whatsapp.net') ? jidDigits(id) : ''
    if (phone && (phone.length < 8 || phone.length > 15)) return
    const current = merged[id] || { jid: id, name: '', phone, lid: lid ? jidDigits(lid) : '', preview: '', at: 0, unread: 0, seen: false }
    const book = entry.book && (entry.book[id] || entry.book[jid])
    if (book && book.name) current.name = book.saved ? book.name : preferName(current.name, book.name)
    current.name = preferName(current.name, row.name)
    if (!current.seen || (row.at || 0) >= (current.at || 0)) {
      if ((row.at || 0) >= (current.at || 0)) {
        current.preview = row.preview || current.preview
        current.at = row.at || current.at
      }
      current.unread = row.unread || 0
      current.seen = true
    }
    merged[id] = current
  })
  const rows = Object.keys(merged).map((jid) => merged[jid])
  rows.sort((left, right) => (right.at || 0) - (left.at || 0))
  return rows.slice(0, 80)
}

function rememberMessage(entry, msg, live) {
  if (!msg || !msg.key) return
  const text = messageText(msg.message).trim()
  let jid = String(msg.key.remoteJid || '').split(':')[0]
  const alt = String(msg.key.remoteJidAlt || '')
  if (jid.endsWith('@lid') && alt.indexOf('@s.whatsapp.net') !== -1) {
    rememberLid(entry, jid, alt)
  }
  const at = when(msg.messageTimestamp) || (live ? Math.floor(Date.now() / 1000) : 0)
  const stored = jid.endsWith('@lid') && entry.lids && entry.lids[jid] ? entry.lids[jid] : jid
  if (jid && (jid.endsWith('@s.whatsapp.net') || jid.endsWith('@lid') || jid.endsWith('@g.us'))) {
    touchChat(entry, jid, {
      name: !msg.key.fromMe && msg.pushName ? msg.pushName : '',
      preview: text || (msg.message && msg.message.imageMessage ? 'Photo' : (msg.message && msg.message.audioMessage ? 'Voice note' : '')),
      at: at,
      messaged: true,
    })
  }
  if (!text) return
  if (!entry.inbox) entry.inbox = []
  entry.inbox.push({
    id: String(msg.key.id || ''),
    from: stored,
    from_me: !!msg.key.fromMe,
    text: text.slice(0, 1000),
    at: at ? String(at) : '',
    receipt: 0,
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

function pullAddressBook(entry, sock, dir) {
  if (!sock || typeof sock.resyncAppState !== 'function' || entry.bookAsked) return
  entry.bookAsked = true
  const mark = path.join(dir, 'address-book.pulled')
  let first = true
  try { first = !fs.existsSync(mark) } catch (e) { first = true }
  if (first) {
    try {
      fs.readdirSync(dir).forEach((file) => {
        if (file.indexOf('app-state-sync-version-critical_unblock_low') === 0 || file.indexOf('app-state-sync-version-regular') === 0) {
          fs.rmSync(path.join(dir, file), { force: true })
        }
      })
    } catch (e) {}
  }
  sock.resyncAppState(['critical_unblock_low', 'regular_low', 'regular'], first)
    .then(() => { try { fs.writeFileSync(mark, String(Date.now())) } catch (e) {} })
    .catch(() => { entry.bookAsked = false })
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
    const live = !packet || packet.type === 'notify'
    list.forEach((msg) => rememberMessage(entry, msg, live))
  })
  sock.ev.on('messages.update', (updates) => {
    ;(updates || []).forEach((update) => {
      const id = update && update.key ? String(update.key.id || '') : ''
      const status = update && update.update ? Number(update.update.status) : 0
      if (!id || !status) return
      ;(entry.inbox || []).forEach((row) => {
        if (row.id === id && status > (row.receipt || 0)) row.receipt = status
      })
      scheduleSave(entry)
    })
  })
  sock.ev.on('messaging-history.set', (payload) => {
    const chats = payload && payload.chats ? payload.chats : []
    const contacts = payload && payload.contacts ? payload.contacts : []
    const messages = payload && payload.messages ? payload.messages : []
    chats.forEach((chat) => touchChat(entry, chat.id, {
      name: chat.name || '',
      trusted: !!(chat.name && !looksLikeNumber(chat.name)),
      at: Number(chat.conversationTimestamp) || 0,
      unread: Number(chat.unreadCount) || 0,
      archived: !!chat.archived,
      messaged: !!(Number(chat.conversationTimestamp) || chat.unreadCount),
    }))
    contacts.forEach((person) => rememberContact(entry, person))
    messages.forEach((msg) => rememberMessage(entry, msg))
  })
  sock.ev.on('chats.upsert', (chats) => {
    ;(chats || []).forEach((chat) => touchChat(entry, chat.id, {
      name: chat.name || '',
      trusted: !!(chat.name && !looksLikeNumber(chat.name)),
      at: Number(chat.conversationTimestamp) || 0,
      unread: Number(chat.unreadCount) || 0,
      archived: !!chat.archived,
      messaged: !!(Number(chat.conversationTimestamp) || chat.unreadCount),
    }))
  })
  sock.ev.on('chats.update', (chats) => {
    ;(chats || []).forEach((chat) => {
      if (!chat || !chat.id) return
      touchChat(entry, chat.id, {
        name: chat.name || '',
        trusted: !!(chat.name && !looksLikeNumber(chat.name)),
        at: Number(chat.conversationTimestamp) || 0,
        unread: typeof chat.unreadCount === 'number' ? chat.unreadCount : undefined,
        archived: typeof chat.archived === 'boolean' ? chat.archived : undefined,
      })
    })
  })
  sock.ev.on('contacts.upsert', (people) => {
    ;(people || []).forEach((person) => rememberContact(entry, person))
  })
  sock.ev.on('contacts.update', (people) => {
    ;(people || []).forEach((person) => rememberContact(entry, person))
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
      pullAddressBook(entry, sock, dir)
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

function lidForPhone(entry, phone) {
  const lids = entry && entry.lids ? entry.lids : {}
  const keys = Object.keys(lids)
  for (let i = 0; i < keys.length; i++) {
    if (lids[keys[i]] === phone) return keys[i]
  }
  return ''
}

async function resolveSendJid(entry, to) {
  const raw = String(to || '').trim()
  if (raw.endsWith('@g.us')) return raw
  if (raw.endsWith('@lid')) return raw.split(':')[0]
  const digits = raw.replace(/\D/g, '')
  if (!/^[1-9][0-9]{7,15}$/.test(digits)) return ''
  const lidKey = digits + '@lid'
  if (entry.chats && entry.chats[lidKey]) return lidKey
  if (entry.lids && entry.lids[lidKey]) return lidKey
  const phone = digits + '@s.whatsapp.net'
  const known = lidForPhone(entry, phone)
  if (known) return known
  if (entry.sock && typeof entry.sock.onWhatsApp === 'function') {
    try {
      const rows = await entry.sock.onWhatsApp(phone)
      const row = rows && rows[0]
      if (row && row.lid) {
        const lid = String(row.lid).indexOf('@') === -1 ? String(row.lid) + '@lid' : String(row.lid).split(':')[0]
        rememberLid(entry, lid, row.jid || phone)
        return lid
      }
      if (row && row.exists === false && digits.length >= 14) return lidKey
    } catch (e) {}
  }
  if (digits.length >= 14 && !(entry.chats && entry.chats[phone])) return lidKey
  return phone
}

async function markRead(id, to) {
  const entry = sessions.get(id)
  if (!entry || !entry.sock || entry.status !== 'CONNECTED') {
    return { success: false }
  }
  const jid = await resolveSendJid(entry, to)
  if (!jid) return { success: false }
  const keys = []
  ;(entry.inbox || []).forEach((row) => {
    if (!row || row.from_me || !row.id) return
    if (row.from !== jid && row.from !== to) return
    keys.push({ remoteJid: jid, id: String(row.id), fromMe: false })
  })
  Object.keys(entry.chats || {}).forEach((chatId) => {
    if (chatId === jid || (entry.lids && entry.lids[chatId] === jid)) {
      entry.chats[chatId].unread = 0
    }
  })
  scheduleSave(entry)
  if (keys.length && typeof entry.sock.readMessages === 'function') {
    await entry.sock.readMessages(keys.slice(-20))
  }
  return { success: true }
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
  const jid = await resolveSendJid(entry, to)
  if (!jid) return { success: false, error: 'That chat is not available.' }
  const sent = await entry.sock.sendMessage(jid, { text: clean })
  return { success: true, id: sent && sent.key ? sent.key.id : null }
}

function chatJid(to) {
  let jid = String(to || '').trim()
  if (jid.indexOf('@') === -1) {
    const digits = jid.replace(/\D/g, '')
    if (!/^[1-9][0-9]{7,14}$/.test(digits)) return ''
    return digits + '@s.whatsapp.net'
  }
  if (!/^([1-9][0-9]{7,14}@s\.whatsapp\.net|[0-9]+(-[0-9]+)?@g\.us)$/.test(jid)) return ''
  return jid
}

async function sendFile(id, to, payload) {
  const entry = sessions.get(id)
  if (!entry || !entry.sock || entry.status !== 'CONNECTED') {
    return { success: false, error: 'WhatsApp is not linked for this phone yet.' }
  }
  const jid = await resolveSendJid(entry, to)
  if (!jid) return { success: false, error: 'That chat is not available.' }
  const data = Buffer.from(String(payload.data || ''), 'base64')
  if (!data.length || data.length > 8 * 1024 * 1024) {
    return { success: false, error: 'Send a file up to 8 MB.' }
  }
  const caption = String(payload.caption || '').slice(0, 1000)
  const kind = String(payload.kind || 'document')
  let content
  if (kind === 'image') content = { image: data, caption }
  else if (kind === 'audio') content = { audio: data, mimetype: payload.mime || 'audio/ogg; codecs=opus', ptt: true }
  else content = { document: data, mimetype: payload.mime || 'application/octet-stream', fileName: String(payload.name || 'file').slice(0, 120), caption }
  const sent = await entry.sock.sendMessage(jid, content)
  const messageId = sent && sent.key ? sent.key.id : null
  return { success: true, id: messageId, msg_id: messageId }
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
    if (req.method === 'POST' && parts[2] === 'read') {
      readBody(req, 2000, (body) => {
        if (!body) {
          send(res, 422, { success: false })
          return
        }
        markRead(id, body.to).then((payload) => send(res, 200, payload)).catch(() => {
          send(res, 200, { success: false })
        })
      })
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
    if (req.method === 'POST' && parts[2] === 'send-file') {
      readBody(req, 12000000, (body) => {
        if (!body) {
          send(res, 422, { success: false, error: 'invalid' })
          return
        }
        sendFile(id, body.to, body).then((payload) => send(res, 200, payload)).catch(() => {
          send(res, 200, { success: false, error: 'The file was not sent.' })
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
