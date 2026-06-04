import React, { useState, useEffect, useCallback } from 'react';
import { DEFAULT_LOGO_URL, isValidLogoUrl } from '@/constants/branding';
import { getSystemSettings } from '@/services/settingsService';

/**
 * Renders the company logo without a visible black box on dark headers.
 * - Starts with the default CDN logo (no flash)
 * - Optionally loads a custom logo from system settings once
 * - Uses mix-blend-lighten on dark backgrounds so JPG black backgrounds disappear
 */
const BrandLogo = ({
  alt = 'Alpha Bridge Technologies',
  className = 'h-[40px] md:h-[50px] lg:h-[60px] w-auto object-contain',
  variant = 'onDark',
  preferSystemLogo = true,
  src: srcOverride,
}) => {
  const [src, setSrc] = useState(srcOverride || DEFAULT_LOGO_URL);

  const applyFallback = useCallback(() => {
    setSrc(DEFAULT_LOGO_URL);
  }, []);

  useEffect(() => {
    if (srcOverride) {
      setSrc(srcOverride);
      return undefined;
    }
    if (!preferSystemLogo) return undefined;

    let cancelled = false;

    const load = async () => {
      try {
        const settings = await getSystemSettings();
        const custom = settings?.logo_url || settings?.system_logo;
        if (!cancelled && isValidLogoUrl(custom)) {
          setSrc(custom);
        }
      } catch {
        /* keep default */
      }
    };

    load();
    return () => {
      cancelled = true;
    };
  }, [preferSystemLogo, srcOverride]);

  const blendClass = variant === 'onDark' ? 'mix-blend-lighten' : '';

  return (
    <span className="inline-flex items-center bg-transparent leading-none">
      <img
        src={src}
        alt={alt}
        className={`${className} ${blendClass} bg-transparent`.trim()}
        onError={applyFallback}
        decoding="async"
      />
    </span>
  );
};

export default BrandLogo;
