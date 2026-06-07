import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import en from './locales/en.json';
import fr from './locales/fr.json';

const STORAGE_KEY = 'alpha_site_language';

function applyDocumentLanguage(lng) {
  if (typeof document !== 'undefined') {
    document.documentElement.lang = lng === 'fr' ? 'fr' : 'en';
  }
}

const saved = typeof localStorage !== 'undefined' ? localStorage.getItem(STORAGE_KEY) : null;

i18n.use(initReactI18next).init({
  resources: {
    en: { translation: en },
    fr: { translation: fr },
  },
  lng: saved || 'en',
  fallbackLng: 'en',
  interpolation: { escapeValue: false },
});

applyDocumentLanguage(i18n.language);

i18n.on('languageChanged', (lng) => {
  localStorage.setItem(STORAGE_KEY, lng);
  applyDocumentLanguage(lng);
});

export default i18n;

/** Translate UI label; key is slugified English fallback. */
export function labelKey(text) {
  return String(text || '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_|_$/g, '');
}

/** Namespaces searched when translating UI labels (slugified English keys). */
const SEARCH_NAMESPACES = ['menu', 'footer', 'common', 'pages', 'home', 'shareholders', 'shareholders_form', 'agreement', 'training', 'login'];

export function translateLabel(t, namespace, text) {
  if (!text) return '';
  const key = labelKey(text);
  const namespaces = namespace
    ? [namespace, ...SEARCH_NAMESPACES.filter((ns) => ns !== namespace)]
    : SEARCH_NAMESPACES;

  for (const ns of namespaces) {
    const translated = t(`${ns}.${key}`, { defaultValue: '' });
    if (translated) return translated;
  }
  return text;
}
