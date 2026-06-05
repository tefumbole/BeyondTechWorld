/**
 * WasenderAPI — server-side (Manukeza / New Vision pattern).
 * Keeps API key off the browser.
 */

const API_KEY = process.env.WASENDER_API_KEY || '';
const BASE_URL = normalizeBaseUrl(
  process.env.WASENDER_BASE_URL || 'https://wasenderapi.com/api'
);
const DEFAULT_COUNTRY = (process.env.WASENDER_DEFAULT_COUNTRY || 'CM').toUpperCase();

const COUNTRY_CODES = {
  RW: '+250',
  CM: '+237',
  UG: '+256',
  KE: '+254',
  TZ: '+255',
};

function normalizeBaseUrl(url) {
  const trimmed = String(url || 'https://wasenderapi.com').trim().replace(/\/$/, '');
  if (trimmed.endsWith('/send-message')) {
    return trimmed.replace(/\/send-message$/, '');
  }
  return trimmed.endsWith('/api') ? trimmed : `${trimmed}/api`;
}

export function formatPhoneNumber(phone, countryCode = DEFAULT_COUNTRY) {
  if (!phone) return '';

  let cleaned = String(phone).trim().replace(/[\s\-()]/g, '');

  if (cleaned.startsWith('00')) cleaned = cleaned.substring(2);

  if (cleaned.startsWith('+')) {
    return /^\+\d{8,15}$/.test(cleaned) ? cleaned : '';
  }

  const prefix = COUNTRY_CODES[countryCode.toUpperCase()];
  if (!prefix) return '';

  const digits = prefix.substring(1);
  if (cleaned.startsWith(digits)) return `+${cleaned}`;
  if (cleaned.startsWith('0')) cleaned = cleaned.substring(1);

  const formatted = `${prefix}${cleaned}`;
  return /^\+\d{8,15}$/.test(formatted) ? formatted : '';
}

export function isWasenderConfigured() {
  return Boolean(API_KEY && !API_KEY.startsWith('your_'));
}

async function wasenderRequest(method, url, { json } = {}) {
  const headers = {
    Authorization: `Bearer ${API_KEY}`,
    Accept: 'application/json',
  };
  const options = { method, headers };

  if (json !== undefined) {
    headers['Content-Type'] = 'application/json';
    options.body = JSON.stringify(json);
  }

  const response = await fetch(url, options);
  const text = await response.text();
  let parsed = null;
  try {
    parsed = text ? JSON.parse(text) : null;
  } catch {
    parsed = { raw: text };
  }

  return { ok: response.ok, status: response.status, json: parsed, body: text };
}

function buildResult(success, phone, body, error) {
  const msgId = body?.data?.msgId || null;
  return {
    success,
    status: success ? 'sent' : 'failed',
    error: error || null,
    phone_number: phone,
    messageSid: msgId,
    provider_sid: msgId,
    data: body,
  };
}

export async function sendTextMessage(toPhone, text, messageType = 'text') {
  if (!isWasenderConfigured()) {
    return buildResult(false, toPhone, null, 'Wasender API key missing. Set WASENDER_API_KEY in apps/api/.env');
  }

  const to = formatPhoneNumber(toPhone);
  if (!to) {
    return buildResult(false, toPhone, null, 'Invalid phone number format.');
  }

  const response = await wasenderRequest('POST', `${BASE_URL}/send-message`, {
    json: { to, text: String(text ?? '') },
  });

  const body = response.json || { raw: response.body };

  if (response.ok && body?.success) {
    console.log(`[WASENDER] ${messageType} sent to ${to}: ${body?.data?.msgId}`);
    return buildResult(true, to, body, null);
  }

  const error = body?.message || body?.error || response.body || 'Send failed';
  console.error('[WASENDER] Send failed:', error);
  return buildResult(false, to, body, typeof error === 'string' ? error : 'Send failed');
}

export async function sendOtp(toPhone, otp, context = null) {
  let message = `Your Alpha Bridge verification code is: *${otp}*`;
  if (context) message += `\n\n${context}`;
  message += '\n\nThis code expires in 10 minutes. Do not share it with anyone.';
  return sendTextMessage(toPhone, message, 'otp');
}
