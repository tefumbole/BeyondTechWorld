export function looksLikeNumber(name) {
  const digits = String(name || '').replace(/\D/g, '')
  return digits.length >= 8 && digits.length >= String(name || '').replace(/[\s+()-]/g, '').length
}

function plain(value) {
  return String(value || '').toLowerCase().replace(/[^\p{L}\p{N}\s.]+/gu, ' ').replace(/\s+/g, ' ').trim()
}

/**
 * An address-book name may extend a shorter saved name, or replace it when
 * the phone saved a different full name. A shorter fragment does not.
 * A profile or chat-list name must not be passed here.
 */
export function keepSavedName(current, incoming, fromAddressBook) {
  const now = String(current || '').trim()
  if (!fromAddressBook) return now
  const next = String(incoming || '').trim()
  if (!next || looksLikeNumber(next)) return now
  if (!now || looksLikeNumber(now)) return next.slice(0, 80)
  const short = plain(now)
  const long = plain(next)
  if (short && long && long.length > short.length && long.includes(short)) return next.slice(0, 80)
  if (short && long && short.length > long.length && short.includes(long)) return now
  if (short === long) return now
  return next.slice(0, 80)
}
