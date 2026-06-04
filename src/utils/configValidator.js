export const validateConfig = () => {
  if (import.meta.env.VITE_DATA_BACKEND === 'mysql') {
    return true;
  }

  const requiredVars = [
    'VITE_SUPABASE_URL',
    'VITE_SUPABASE_ANON_KEY',
  ];

  const missing = requiredVars.filter((key) => {
    const val = import.meta.env[key];
    return !val || val.startsWith('your_');
  });

  if (missing.length > 0) {
    console.error(
      `[Config] Missing or placeholder environment variables: ${missing.join(', ')}`
    );
    return false;
  }

  return true;
};