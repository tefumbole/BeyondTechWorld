import React from 'react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';

export default function LanguageSwitcher({ className, variant = 'header' }) {
  const { i18n, t } = useTranslation();
  const current = i18n.language?.startsWith('fr') ? 'fr' : 'en';

  const baseBtn =
    variant === 'admin'
      ? 'px-2 py-1 text-xs font-semibold rounded border transition-colors'
      : 'px-2 py-1 text-xs font-semibold rounded border border-white/30 transition-colors';

  return (
    <div
      className={cn('flex items-center gap-1', className)}
      title={t('language.switch', { defaultValue: 'Language' })}
      aria-label={t('language.switch', { defaultValue: 'Language' })}
    >
      <button
        type="button"
        onClick={() => i18n.changeLanguage('en')}
        className={cn(
          baseBtn,
          current === 'en'
            ? 'bg-[#D4AF37] text-[#003D82] border-[#D4AF37]'
            : variant === 'admin'
              ? 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'
              : 'bg-transparent text-white hover:bg-white/10'
        )}
      >
        EN
      </button>
      <button
        type="button"
        onClick={() => i18n.changeLanguage('fr')}
        className={cn(
          baseBtn,
          current === 'fr'
            ? 'bg-[#D4AF37] text-[#003D82] border-[#D4AF37]'
            : variant === 'admin'
              ? 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'
              : 'bg-transparent text-white hover:bg-white/10'
        )}
      >
        FR
      </button>
    </div>
  );
}
