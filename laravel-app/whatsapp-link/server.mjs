import fs from 'fs'
import http from 'http'
import path from 'path'
import { fileURLToPath } from 'url'
import {
  makeWASocket,
  Browsers,
  DisconnectReason,
  downloadMediaMessage,
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
    qr: withQr && entry.status !== 'CONNECTED' ? (entry.qr || null) : null,
    pairing_code: entry.pairing || null,
    error: entry.error || null,
  }
}

function unwrapMessage(message) {
  let current = message
  for (let i = 0; i < 6 && current; i++) {
    const wrapped = current.ephemeralMessage || current.viewOnceMessage || current.viewOnceMessageV2
      || current.viewOnceMessageV2Extension || current.documentWithCaptionMessage || current.editedMessage
    if (!wrapped || !wrapped.message) break
    current = wrapped.message
  }
  return current
}

function messageText(message) {
  message = unwrapMessage(message)
  if (!message) return ''
  if (typeof message.conversation === 'string') return message.conversation
  if (message.extendedTextMessage && message.extendedTextMessage.text) return message.extendedTextMessage.text
  if (message.imageMessage) return message.imageMessage.caption || '[image]'
  if (message.videoMessage) return message.videoMessage.caption || '[video]'
  if (message.audioMessage) return '[voice]'
  if (message.documentMessage) return message.documentMessage.caption || message.documentMessage.fileName || '[document]'
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
        generation: entry.generation || 1,
        keys: entry.keys || {},
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
    entry.generation = saved && saved.generation ? Number(saved.generation) : 0
    entry.keys = saved && saved.keys ? saved.keys : {}
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
  const keep = (key) => {
    if (!key) return
    const current = entry.book[key] || { name: '', saved: false }
    entry.book[key] = {
      name: (known || current.name || '').slice(0, 80),
      saved: (saved !== '' && !looksLikeNumber(saved)) || !!current.saved,
    }
  }
  keep(phone)
  keep(lid)
  if (phone || lid) scheduleSave(entry)
  if (lid && phone) rememberLid(entry, lid, phone)
  if (phone || lid) {
    note(entry, {
      kind: 'contact',
      jid: phone || lid,
      phone: phone ? jidDigits(phone) : '',
      lid: lid ? jidDigits(lid) : '',
      saved_name: saved && !looksLikeNumber(saved) ? saved.slice(0, 80) : '',
      profile_name: !saved && notify && !looksLikeNumber(notify) ? notify.slice(0, 80) : '',
    })
  }
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
  const previousSaved = current.saved_name || ''
  const book = entry.book && entry.book[id]
  if (book && book.saved && book.name) current.saved_name = String(book.name).slice(0, 80)
  if (patch.profile && !looksLikeNumber(patch.profile)) current.profile_name = String(patch.profile).slice(0, 80)
  else if (patch.trusted && patch.name && !looksLikeNumber(patch.name)) current.saved_name = String(patch.name).slice(0, 80)
  const saved = current.saved_name || ''
  const profile = current.profile_name || ''
  current.name = saved || profile || current.name || ''
  if (patch.preview) current.preview = String(patch.preview).slice(0, 140)
  const at = when(patch.at)
  if (at && at >= (current.at || 0)) current.at = at
  if (typeof patch.unread === 'number') current.unread = patch.unread
  if (patch.messaged) current.messaged = true
  if (typeof patch.archived === 'boolean') current.archived = patch.archived
  entry.chats[id] = current
  scheduleSave(entry)
  if (current.saved_name && current.saved_name !== previousSaved) {
    note(entry, {
      kind: 'chat',
      jid: id,
      phone: id.endsWith('@s.whatsapp.net') ? jidDigits(id) : '',
      saved_name: current.saved_name,
    })
  }
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
    const current = merged[id] || { jid: id, name: '', saved_name: '', profile_name: '', phone, lid: lid ? jidDigits(lid) : '', preview: '', at: 0, unread: 0, seen: false }
    const book = entry.book && (entry.book[id] || entry.book[jid])
    const savedName = (book && book.saved && book.name) ? book.name : (row.saved_name || '')
    const profileName = row.profile_name || ''
    current.saved_name = savedName || current.saved_name || ''
    current.profile_name = profileName || current.profile_name || ''
    current.name = current.saved_name || current.profile_name || ''
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

function contactRows(entry) {
  const merged = {}
  const mine = String(entry.phone || '').replace(/\D/g, '')
  const add = (jid, name, saved) => {
    const id = String(jid || '')
    const phone = id.endsWith('@s.whatsapp.net') ? jidDigits(id) : ''
    if (!phone || phone === mine || phone.length < 8 || phone.length > 13) return
    const current = merged[phone] || { jid: phone + '@s.whatsapp.net', name: '', saved_name: '', profile_name: '', phone, saved: false }
    if (name && !looksLikeNumber(name)) {
      if (saved) {
        current.saved_name = name.slice(0, 80)
        current.saved = true
      } else if (!current.saved_name) {
        current.profile_name = preferName(current.profile_name, name)
      }
    }
    current.name = current.saved_name || current.profile_name || ''
    merged[phone] = current
  }
  Object.keys(entry.book || {}).forEach((key) => {
    const row = entry.book[key] || {}
    let id = key
    if (id.endsWith('@lid') && entry.lids && entry.lids[id]) id = entry.lids[id]
    add(id, row.name, !!row.saved)
  })
  Object.keys(entry.chats || {}).forEach((jid) => {
    const row = entry.chats[jid]
    if (!row) return
    let id = jid
    if (id.endsWith('@lid') && entry.lids && entry.lids[id]) id = entry.lids[id]
    if (row.saved_name) add(id, row.saved_name, true)
    if (row.profile_name) add(id, row.profile_name, false)
  })
  return Object.keys(merged).map((phone) => merged[phone])
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
      profile: !msg.key.fromMe && msg.pushName ? msg.pushName : '',
      preview: text || (msg.message && msg.message.imageMessage ? 'Photo' : (msg.message && msg.message.audioMessage ? 'Voice note' : '')),
      at: at,
      messaged: true,
    })
  }
  const kind = mediaKind(unwrapMessage(msg.message))
  if (!text && !kind) return
  if (!entry.inbox) entry.inbox = []
  const quote = quoteText(unwrapMessage(msg.message))
  const participant = msg.key.participant ? String(msg.key.participant).split(':')[0] : ''
  const chat = entry.chats && (entry.chats[jid] || entry.chats[stored])
  const book = participant && entry.book ? (entry.book[participant] || null) : null
  entry.inbox.push({
    id: String(msg.key.id || ''),
    from: stored,
    from_me: !!msg.key.fromMe,
    text: text.slice(0, 1000),
    at: at ? String(at) : '',
    receipt: 0,
    type: kind || 'TEXT',
    quote,
  })
  const write = (file) => {
    note(entry, {
      kind: 'message',
      live: !!live,
      id: String(msg.key.id || ''),
      jid: stored,
      remote: jidDigits(stored),
      from_me: !!msg.key.fromMe,
      text: text.slice(0, 1000),
      at: at ? String(at) : '',
      type: kind || 'TEXT',
      quote,
      participant,
      participant_saved: book && book.saved && book.name ? String(book.name).slice(0, 80) : '',
      saved_name: chat && chat.saved_name ? chat.saved_name : '',
      profile_name: !msg.key.fromMe && msg.pushName ? String(msg.pushName).slice(0, 80) : '',
      file,
      unavailable: !!kind && !file,
    })
  }
  if (entry.inbox.length > 200) entry.inbox.splice(0, entry.inbox.length - 200)
  scheduleSave(entry)
  if (live && kind) {
    saveMedia(entry, msg, kind).then(write).catch(() => write(''))
  } else {
    write('')
  }
  if (live && msg.key.id) {
    emit(entry, {
      event_id: String(msg.key.id),
      kind: 'message',
      live: true,
      from_me: !!msg.key.fromMe,
      text: text.slice(0, 1000),
      remote: jidDigits(jid),
      at: String(msg.messageTimestamp || ''),
    })
  }
}

function mediaExt(kind) {
  if (kind === 'IMAGE') return 'jpg'
  if (kind === 'VIDEO') return 'mp4'
  if (kind === 'AUDIO') return 'ogg'
  if (kind === 'STICKER') return 'webp'
  return 'bin'
}

async function saveMedia(entry, msg, kind) {
  if (!entry || !kind || typeof downloadMediaMessage !== 'function') return ''
  const buffer = await downloadMediaMessage(msg, 'buffer', {}, {
    logger,
    reuploadRequest: entry.sock && entry.sock.updateMediaMessage ? entry.sock.updateMediaMessage.bind(entry.sock) : undefined,
  })
  if (!buffer || !buffer.length || buffer.length > 8 * 1024 * 1024) return ''
  const name = String(msg.key && msg.key.id ? msg.key.id : Date.now()).replace(/[^A-Za-z0-9_-]/g, '').slice(0, 80) + '.' + mediaExt(kind)
  const dir = path.join(sessionDir(entry.id), 'media')
  ensureDir(dir)
  fs.writeFileSync(path.join(dir, name), buffer)
  return 'sessions/' + entry.id + '/media/' + name
}

function mediaKind(message) {
  if (!message) return ''
  if (message.imageMessage) return message.imageMessage.isAnimated ? 'STICKER' : 'IMAGE'
  if (message.stickerMessage) return 'STICKER'
  if (message.videoMessage) return 'VIDEO'
  if (message.audioMessage) return 'AUDIO'
  if (message.documentMessage) return 'DOCUMENT'
  return ''
}

function quoteText(message) {
  const info = message && message.extendedTextMessage && message.extendedTextMessage.contextInfo
  if (!info || !info.quotedMessage) return ''
  return messageText(info.quotedMessage).trim().slice(0, 300)
}

function note(entry, event) {
  if (!entry || !entry.id) return
  entry.seq = (entry.seq || 0) + 1
  const row = Object.assign({ seq: entry.seq, generation: entry.generation || 1 }, event)
  if (row.id) {
    if (!entry.keys) entry.keys = {}
    entry.keys[row.id] = { text: row.text || '', type: row.type || 'TEXT' }
    const ids = Object.keys(entry.keys)
    if (ids.length > 500) delete entry.keys[ids[0]]
  }
  try {
    fs.appendFileSync(path.join(sessionDir(entry.id), 'events.jsonl'), JSON.stringify(row) + '\n')
  } catch (e) {}
}

function readEvents(entry, after) {
  let raw = ''
  try {
    raw = fs.readFileSync(path.join(sessionDir(entry.id), 'events.jsonl'), 'utf8')
  } catch (e) {
    raw = ''
  }
  const events = []
  raw.split('\n').forEach((line) => {
    if (!line) return
    try {
      const row = JSON.parse(line)
      if (row && row.seq > after && row.generation === (entry.generation || 1)) events.push(row)
    } catch (e) {}
  })
  return events.slice(0, 80)
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
  const mark = path.join(dir, 'address-book.v2')
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
  entry.generation = (entry.generation || 0) + 1
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
  const keptQr = entry.qr || null
  entry.registered = !!(state.creds && state.creds.registered)
  entry.status = entry.status === 'CONNECTED' ? 'CONNECTED' : (entry.registered && !keptQr ? 'CONNECTING' : 'AWAITING_QR')
  entry.error = null
  entry.qr = keptQr
  entry.pairing = null
  sessions.set(id, entry)
  const sock = makeWASocket({
    version: version.version,
    auth: state,
    logger,
    printQRInTerminal: false,
    browser: Browsers.macOS('Whats MAI'),
    syncFullHistory: !fs.existsSync(path.join(dir, 'history.ready')),
    markOnlineOnConnect: false,
    getMessage: async (key) => {
      const found = key && entry.keys ? entry.keys[key.id] : null
      if (!found || !found.text) return undefined
      return { conversation: found.text }
    },
  })
  entry.sock = sock
  const generation = entry.generation
  const current = () => entry.generation === generation
  sock.ev.on('creds.update', persistCreds)
  sock.ev.on('messages.upsert', (packet) => {
    if (!current()) return
    const list = packet && packet.messages ? packet.messages : []
    const live = !packet || packet.type === 'notify'
    list.forEach((msg) => rememberMessage(entry, msg, live))
  })
  sock.ev.on('messages.update', (updates) => {
    if (!current()) return
    ;(updates || []).forEach((update) => {
      const id = update && update.key ? String(update.key.id || '') : ''
      const status = update && update.update ? Number(update.update.status) : 0
      if (!id || !status) return
      ;(entry.inbox || []).forEach((row) => {
        if (row.id === id && status > (row.receipt || 0)) row.receipt = status
      })
      scheduleSave(entry)
      note(entry, { kind: 'receipt', id, receipt: status, jid: update.key.remoteJid ? String(update.key.remoteJid).split(':')[0] : '' })
    })
  })
  sock.ev.on('messaging-history.set', (payload) => {
    if (!current()) return
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
    messages.forEach((msg) => rememberMessage(entry, msg, false))
    if (payload && payload.isLatest) {
      try { fs.writeFileSync(path.join(sessionDir(entry.id), 'history.ready'), '1') } catch (e) {}
      note(entry, { kind: 'history', ready: true })
    }
  })
  sock.ev.on('chats.upsert', (chats) => {
    if (!current()) return
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
    if (!current()) return
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
    if (!current()) return
    ;(people || []).forEach((person) => rememberContact(entry, person))
  })
  sock.ev.on('contacts.update', (people) => {
    if (!current()) return
    ;(people || []).forEach((person) => rememberContact(entry, person))
  })
  sock.ev.on('connection.update', async (update) => {
    if (!current()) return
    if (update.qr) {
      const image = await QRCode.toDataURL(update.qr)
      if (!current()) return
      entry.qr = image
      entry.status = 'AWAITING_QR'
      entry.error = null
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
      const restart = code === DisconnectReason.restartRequired || code === 515
      if (!restart) entry.retries += 1
      if (entry.retries > 8) {
        entry.status = 'ERROR'
        entry.error = entry.qr ? '' : 'The QR code could not be created. Refresh this page.'
        return
      }
      entry.status = entry.qr ? 'AWAITING_QR' : (entry.registered ? 'CONNECTING' : 'AWAITING_QR')
      setTimeout(() => {
        startSocket(id).catch(() => {
          entry.status = 'ERROR'
          entry.error = 'The QR code could not be created. Refresh this page.'
        })
      }, restart ? 250 : backoffMs(entry.retries))
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

async function changeMessage(id, to, key, patch) {
  const entry = sessions.get(id)
  if (!entry || !entry.sock || entry.status !== 'CONNECTED' || !key) {
    return { success: false, unsupported: true, error: 'That action is not available on this link yet.' }
  }
  const jid = await resolveSendJid(entry, to)
  if (!jid) return { success: false, error: 'That chat is not available.' }
  const messageKey = { remoteJid: jid, fromMe: true, id: String(key) }
  if (patch.delete) await entry.sock.sendMessage(jid, { delete: messageKey })
  else await entry.sock.sendMessage(jid, { text: String(patch.text || '').slice(0, 1000), edit: messageKey })
  return { success: true }
}

async function showPresence(id, to, state) {
  const entry = sessions.get(id)
  if (!entry || !entry.sock || typeof entry.sock.sendPresenceUpdate !== 'function') {
    return { success: false, unsupported: true }
  }
  const jid = await resolveSendJid(entry, to)
  if (!jid) return { success: false }
  await entry.sock.sendPresenceUpdate(state === 'recording' ? 'recording' : 'composing', jid)
  return { success: true }
}

async function sendText(id, to, text, quote) {
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
  const options = {}
  if (quote && quote.id) {
    options.quoted = {
      key: { remoteJid: jid, fromMe: !!quote.from_me, id: String(quote.id) },
      message: { conversation: String(quote.text || '').slice(0, 300) },
    }
  }
  const sent = await entry.sock.sendMessage(jid, { text: clean }, options)
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
  else if (kind === 'video') content = { video: data, caption, mimetype: payload.mime || 'video/mp4' }
  else if (kind === 'sticker') content = { sticker: data }
  else if (kind === 'audio') content = { audio: data, mimetype: payload.mime || 'audio/ogg; codecs=opus', ptt: payload.ptt !== false }
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
    const raw = String(person.id || '').split(':')[0]
    const mapped = raw.endsWith('@lid') && entry.lids && entry.lids[raw] ? entry.lids[raw] : raw
    const book = entry.book && (entry.book[mapped] || entry.book[raw])
    const saved = book && book.saved && book.name ? book.name : ''
    return {
      phone: mapped.split('@')[0],
      name: saved,
      saved_name: saved,
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
    if (req.method === 'GET' && parts[2] === 'contacts') {
      const entry = sessions.get(id) || { book: {}, chats: {} }
      send(res, 200, { phone: entry.phone || null, contacts: contactRows(entry) })
      return
    }
    if (req.method === 'GET' && parts[2] === 'events') {
      const entry = sessions.get(id)
      if (!entry) {
        send(res, 200, { generation: 0, history_ready: false, events: [] })
        return
      }
      const after = Number(url.searchParams.get('after') || 0)
      send(res, 200, {
        generation: entry.generation || 1,
        history_ready: fs.existsSync(path.join(sessionDir(id), 'history.ready')),
        events: readEvents(entry, after),
      })
      return
    }
    if (req.method === 'POST' && parts[2] === 'history') {
      readBody(req, 4000, (body) => {
        const entry = sessions.get(id)
        if (!entry || !entry.sock || typeof entry.sock.fetchMessageHistory !== 'function' || !body || !body.id || !body.jid) {
          send(res, 200, { success: false, unsupported: true })
          return
        }
        const oldest = { remoteJid: String(body.jid), fromMe: !!body.from_me, id: String(body.id) }
        entry.sock.fetchMessageHistory(40, oldest, Number(body.at || 0) * 1000)
          .then(() => send(res, 200, { success: true }))
          .catch(() => send(res, 200, { success: false, unsupported: true }))
      })
      return
    }
    if (req.method === 'GET' && parts[2] === 'messages') {
      const entry = sessions.get(id) || { inbox: [] }
      if (!entry.lidScan) attachLids(entry).catch(() => {})
      send(res, 200, { messages: entry.inbox || [] })
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
    if (req.method === 'POST' && parts[2] === 'presence') {
      readBody(req, 2000, (body) => {
        if (!body) {
          send(res, 422, { success: false })
          return
        }
        showPresence(id, body.to, body.state).then((payload) => send(res, 200, payload)).catch(() => {
          send(res, 200, { success: false, unsupported: true })
        })
      })
      return
    }
    if (req.method === 'POST' && (parts[2] === 'edit' || parts[2] === 'delete')) {
      readBody(req, 4000, (body) => {
        if (!body || !body.id) {
          send(res, 200, { success: false, unsupported: true })
          return
        }
        changeMessage(id, body.to, body.id, { delete: parts[2] === 'delete', text: body.text }).then((payload) => send(res, 200, payload)).catch(() => {
          send(res, 200, { success: false, unsupported: true, error: 'WhatsApp did not change that message.' })
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
        sendText(id, body.to, body.text, body.quote).then((payload) => send(res, 200, payload)).catch(() => {
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
      const wantQr = url.searchParams.get('qr') === '1'
      const finish = (entry) => {
        const state = entry || { status: 'NOT_CONNECTED', phone: null, qr: null, pairing: null, error: null }
        const ready = !wantQr || state.qr || state.status === 'CONNECTED' || state.status === 'ERROR'
          ? Promise.resolve()
          : waitForQr(state, 6000)
        ready.then(() => send(res, 200, publicState(state, wantQr)))
      }
      const entry = sessions.get(id)
      const stale = !entry || !entry.sock || entry.status === 'ERROR' || entry.status === 'DISCONNECTED' || entry.status === 'UNLINKED' || entry.status === 'NOT_CONNECTED'
      if (wantQr && stale) {
        const expected = id.indexOf('p') === 0 ? id.slice(1) : ''
        startSocket(id, expected).then(finish).catch((error) => {
          const message = error && error.message ? String(error.message).slice(0, 180) : 'The QR code could not be created.'
          send(res, 200, { status: 'ERROR', phone: null, qr: null, pairing_code: null, error: message })
        })
        return
      }
      finish(entry)
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
