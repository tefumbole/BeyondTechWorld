import { format } from 'date-fns';

/**
 * WasenderAPI client — aligned with New Vision Travel / Manukeza contract.
 * Auth: Bearer token. Send: POST {base}/send-message with { to, text }.
 */

const API_KEY = import.meta.env.VITE_WASENDER_API_KEY;
const BASE_URL = normalizeBaseUrl(
  import.meta.env.VITE_WASENDER_BASE_URL ||
    import.meta.env.VITE_WASENDER_API_URL ||
    'https://wasenderapi.com/api'
);

const DEFAULT_COUNTRY = (import.meta.env.VITE_DEFAULT_PHONE_COUNTRY || 'RW').toUpperCase();

const COUNTRY_CODES = {
  RW: '+250',
  CM: '+237',
  UG: '+256',
  KE: '+254',
  TZ: '+255',
};

export const maskKey = (key) =>
  key && key.length > 8 ? `${key.substring(0, 4)}***${key.slice(-4)}` : 'MISSING';

function normalizeBaseUrl(url) {
  const trimmed = String(url || 'https://wasenderapi.com').trim().replace(/\/$/, '');
  if (trimmed.endsWith('/send-message')) {
    return trimmed.replace(/\/send-message$/, '');
  }
  return trimmed.endsWith('/api') ? trimmed : `${trimmed}/api`;
}

/**
 * Format to E.164 (+250..., +237..., etc.)
 */
export function formatPhoneNumber(phone, countryCode = DEFAULT_COUNTRY) {
  if (!phone) return '';

  let cleaned = String(phone).trim().replace(/[\s\-()]/g, '');

  if (cleaned.startsWith('00')) {
    cleaned = cleaned.substring(2);
  }

  if (cleaned.startsWith('+')) {
    if (!/^\+\d{8,15}$/.test(cleaned)) {
      return '';
    }
    return cleaned;
  }

  const prefix = COUNTRY_CODES[countryCode.toUpperCase()];
  if (!prefix) {
    return '';
  }

  const digits = prefix.substring(1);
  if (cleaned.startsWith(digits)) {
    return `+${cleaned}`;
  }

  if (cleaned.startsWith('0')) {
    cleaned = cleaned.substring(1);
  }

  const formatted = `${prefix}${cleaned}`;
  return /^\+\d{8,15}$/.test(formatted) ? formatted : '';
}

export function isWasenderConfigured() {
  return Boolean(API_KEY);
}

async function wasenderRequest(method, url, { json, body, contentType } = {}) {
  const headers = {
    Authorization: `Bearer ${API_KEY}`,
    Accept: 'application/json',
  };

  const options = { method, headers };

  if (json !== undefined) {
    headers['Content-Type'] = 'application/json';
    options.body = JSON.stringify(json);
  } else if (body !== undefined) {
    if (contentType) headers['Content-Type'] = contentType;
    options.body = body;
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
    return buildResult(false, toPhone, null, 'Wasender API key missing. Set VITE_WASENDER_API_KEY.');
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

export async function uploadBuffer(buffer, mimeType = 'application/pdf') {
  if (!isWasenderConfigured()) {
    return { success: false, error: 'Wasender API key missing.', public_url: null };
  }

  const response = await wasenderRequest('POST', `${BASE_URL}/upload`, {
    body: buffer,
    contentType: mimeType,
  });

  const payload = response.json || { raw: response.body };

  if (response.ok && payload?.publicUrl) {
    return {
      success: true,
      public_url: payload.publicUrl,
      error: null,
    };
  }

  const error = payload?.message || payload?.error || response.body;
  return {
    success: false,
    public_url: null,
    error: typeof error === 'string' ? error : 'Upload failed.',
  };
}

export async function sendDocumentMessage(toPhone, documentUrl, text = null, fileName = null) {
  const to = formatPhoneNumber(toPhone);
  if (!to) return buildResult(false, toPhone, null, 'Invalid phone number.');

  const payload = { to, documentUrl };
  if (text) payload.text = text;
  if (fileName) payload.fileName = fileName;

  const response = await wasenderRequest('POST', `${BASE_URL}/send-message`, { json: payload });
  const body = response.json || {};

  if (response.ok && body?.success) {
    return buildResult(true, to, body, null);
  }

  return buildResult(false, to, body, body?.message || 'Send failed');
}

export async function sendImageMessage(toPhone, imageUrl, text = null) {
  const to = formatPhoneNumber(toPhone);
  if (!to) return buildResult(false, toPhone, null, 'Invalid phone number.');

  const payload = { to, imageUrl };
  if (text) payload.text = text;

  const response = await wasenderRequest('POST', `${BASE_URL}/send-message`, { json: payload });
  const body = response.json || {};

  if (response.ok && body?.success) {
    return buildResult(true, to, body, null);
  }

  return buildResult(false, to, body, body?.message || 'Send failed');
}

const personalizeMessage = (template, recipientData, referenceCode, pdfUrl) => {
  if (!template) return '';
  let content = String(template);
  const today = format(new Date(), 'dd MMMM yyyy');

  const replacements = {
    '{name}': recipientData?.name || recipientData?.recipient_name || 'Recipient',
    '{email}': recipientData?.email || recipientData?.recipient_email || '',
    '{phone}': recipientData?.phone || recipientData?.recipient_phone || '',
    '{date}': today,
  };

  for (const [key, value] of Object.entries(replacements)) {
    const regex = new RegExp(key.replace(/([.*+?^=!:${}()|\[\]\/\\])/g, '\\$1'), 'g');
    content = content.replace(regex, value);
  }

  if (referenceCode || pdfUrl) {
    content += '\n\n---\n';
    if (referenceCode) content += `Ref: ${referenceCode}\n`;
    if (pdfUrl) content += `Document: ${pdfUrl}\n`;
  }

  return content;
};

/** @deprecated Use sendTextMessage — kept for existing callers */
export const sendWhatsAppMessage = async (
  phoneNumber,
  messageTemplate,
  recipientData = {},
  referenceCode = null,
  pdfUrl = null
) => {
  const message = personalizeMessage(messageTemplate, recipientData, referenceCode, pdfUrl);
  const result = await sendTextMessage(phoneNumber, message);
  return {
    success: result.success,
    error: result.error,
    data: result.data,
  };
};

/** @deprecated Use sendImageMessage — kept for existing callers */
export const sendWhatsAppMessageWithImage = async (
  phoneNumber,
  messageTemplate,
  imageUrl,
  recipientData = {},
  referenceCode = null,
  pdfUrl = null
) => {
  const text = personalizeMessage(messageTemplate, recipientData, referenceCode, pdfUrl);
  const result = await sendImageMessage(phoneNumber, imageUrl, text);
  return {
    success: result.success,
    error: result.error,
    data: result.data,
  };
};
