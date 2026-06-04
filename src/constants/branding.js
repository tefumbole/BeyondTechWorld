/** Canonical brand assets — single source of truth for logo URLs */
export const DEFAULT_LOGO_URL =
  'https://horizons-cdn.hostinger.com/81ef3422-3855-479e-bfe8-28a4ceb0df39/a742e501955dd22251276e445b31816d.png';

export function isValidLogoUrl(url) {
  if (!url || typeof url !== 'string') return false;
  return /^https?:\/\/.+/i.test(url.trim());
}
