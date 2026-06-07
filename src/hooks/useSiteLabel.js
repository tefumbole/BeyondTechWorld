import { useTranslation } from 'react-i18next';
import { translateLabel } from '@/i18n';

/** Hook to translate UI labels using slugified English fallback keys. */
export function useSiteLabel() {
  const { t } = useTranslation();
  return (namespace, text) => translateLabel(t, namespace, text);
}

export function usePageT(namespace) {
  const { t } = useTranslation();
  return (key, defaultValue) => t(`${namespace}.${key}`, { defaultValue });
}
