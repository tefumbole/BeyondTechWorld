export function historyMarkerComplete(contents) {
  return String(contents == null ? '' : contents).trim() === '100'
}

export function classifyHistory(payload, pendingJid) {
  const messages = payload && Array.isArray(payload.messages) ? payload.messages : []
  const syncType = payload && payload.syncType != null ? Number(payload.syncType) : null
  const progress = payload && payload.progress != null && payload.progress !== '' ? Number(payload.progress) : null
  if (syncType === 6) {
    const remote = messages[0] && messages[0].key ? String(messages[0].key.remoteJid || '') : ''
    return {
      page: true,
      complete: false,
      jid: remote ? remote.split(':')[0] : (pendingJid || ''),
      state: messages.length ? 'received' : 'unavailable',
      count: messages.length,
    }
  }
  return {
    page: false,
    complete: progress != null && progress >= 100,
    progress,
    syncType,
  }
}
