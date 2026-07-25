/**
 * Phone Number Service — WhatsApp-ready normalization (Cameroon default +237).
 * Strips spaces/dashes/brackets; prepends +237 for local mobiles.
 */

const DEFAULT_CC = '237';

const COUNTRY_CODES = [
  '237', '234', '233', '254', '255', '256', '250', '243', '225', '221', '27',
  '33', '44', '1', '49', '32', '31', '39', '20', '91', '86', '971',
].sort((a, b) => b.length - a.length);

const digitsOnly = (v) => String(v || '').replace(/\D/g, '');

const looksInternational = (digits) => {
  if (!digits || digits.length < 11) return false;
  for (const cc of COUNTRY_CODES) {
    if (digits.startsWith(cc)) {
      const local = digits.slice(cc.length);
      if (local.length >= 7 && local.length <= 12) return true;
    }
  }
  return digits.length >= 11 && digits.length <= 15;
};

/**
 * Normalize to E.164 for WhatsApp: +237681239720
 */
export const normalizeForWhatsApp = (phoneNumber) => {
  let digits = digitsOnly(phoneNumber);
  if (!digits) return '';
  if (digits.startsWith('0') && digits.length >= 9) {
    digits = digits.replace(/^0+/, '');
  }
  if (!digits) return '';
  if (looksInternational(digits)) {
    const dbl = DEFAULT_CC + DEFAULT_CC;
    while (digits.startsWith(dbl)) digits = digits.slice(DEFAULT_CC.length);
    return `+${digits}`;
  }
  if (digits.length >= 8 && digits.length <= 10) {
    return `+${DEFAULT_CC}${digits}`;
  }
  return `+${digits}`;
};

/**
 * Extract phone number without country code
 */
export const extractPhoneWithoutCode = (countryCode, phoneNumber) => {
  if (!phoneNumber) return '';
  let cleaned = digitsOnly(phoneNumber).replace(/^0+/, '');
  if (countryCode) {
    const codeDigits = digitsOnly(countryCode);
    if (cleaned.startsWith(codeDigits)) {
      cleaned = cleaned.substring(codeDigits.length);
    }
  } else if (looksInternational(cleaned)) {
    for (const cc of COUNTRY_CODES) {
      if (cleaned.startsWith(cc)) {
        cleaned = cleaned.slice(cc.length);
        break;
      }
    }
  }
  return cleaned;
};

/**
 * Combine country code and phone number → +E.164
 */
export const combinePhoneNumber = (countryCode, phoneNumber) => {
  if (!phoneNumber) return '';
  const fullLocal = digitsOnly(phoneNumber);
  if (looksInternational(fullLocal)) {
    return normalizeForWhatsApp(phoneNumber);
  }
  const cleanedPhone = extractPhoneWithoutCode(countryCode, phoneNumber);
  const cleanedCode = digitsOnly(countryCode) || DEFAULT_CC;
  return normalizeForWhatsApp(`${cleanedCode}${cleanedPhone}`);
};

/**
 * Validate phone number format
 */
export const validatePhoneFormat = (phoneNumber) => {
  if (!phoneNumber) return false;
  const digits = digitsOnly(phoneNumber);
  return digits.length >= 8 && digits.length <= 15;
};

export default {
  extractPhoneWithoutCode,
  combinePhoneNumber,
  validatePhoneFormat,
  normalizeForWhatsApp,
};
