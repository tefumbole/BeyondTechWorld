import fs from 'fs'
import path from 'path'

export function mediaMaxBytes() {
  const raw = Number(process.env.MAI_MEDIA_MAX_BYTES || 8 * 1024 * 1024)
  if (!Number.isFinite(raw) || raw < 1024) return 8 * 1024 * 1024
  return Math.floor(raw)
}

export function mediaTooLarge(length) {
  return !length || length > mediaMaxBytes()
}

export function mediaLimitReason() {
  const mb = Math.max(1, Math.round(mediaMaxBytes() / (1024 * 1024)))
  return 'This file is larger than ' + mb + ' MB, so MAI cannot store it.'
}

/**
 * Quoted replies and media retries ask for a message that may already have
 * left the in-memory inbox. The event log is the copy that survives restart.
 */
export function findStoredMessage(sessionDir, id) {
  const wanted = String(id || '')
  if (!wanted) return null
  const file = path.join(sessionDir, 'events.jsonl')
  let stat
  try { stat = fs.statSync(file) } catch (e) { return null }
  const chunk = 1024 * 1024
  let end = stat.size
  let carry = ''
  while (end > 0) {
    const start = Math.max(0, end - chunk)
    const length = end - start
    const buf = Buffer.alloc(length)
    const fd = fs.openSync(file, 'r')
    try {
      fs.readSync(fd, buf, 0, length, start)
    } finally {
      fs.closeSync(fd)
    }
    const lines = (buf.toString('utf8') + carry).split('\n')
    carry = start > 0 ? (lines.shift() || '') : ''
    for (let i = lines.length - 1; i >= 0; i--) {
      if (!lines[i] || lines[i].indexOf(wanted) === -1) continue
      try {
        const row = JSON.parse(lines[i])
        if (row && row.kind === 'message' && String(row.id) === wanted) {
          return { text: row.text || '', type: row.type || 'TEXT', file: row.file || '' }
        }
      } catch (e) {}
    }
    if (start === 0) break
    end = start
  }
  return null
}
