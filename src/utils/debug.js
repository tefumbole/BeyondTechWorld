/**
 * Debug utility for diagnosing application issues
 */

export const logError = (context, error, additionalData = {}) => {
  console.error(`[${context}] Error:`, error);
  if (Object.keys(additionalData).length > 0) {
    console.error(`[${context}] Additional Data:`, additionalData);
  }
};

export const logInfo = (context, message, data = null) => {
  if (import.meta.env.DEV) {
    console.log(`[${context}] ${message}`, data || '');
  }
};

export const logWarn = (context, message, data = null) => {
  if (import.meta.env.DEV) {
    console.warn(`[${context}] ${message}`, data || '');
  }
};

export const debugEnv = () => {
  if (import.meta.env.DEV) {
    console.group('Environment Debug');
    console.log('Data backend:', import.meta.env.VITE_DATA_BACKEND || 'supabase');
    console.log('API URL:', import.meta.env.VITE_API_URL || '(default /api)');
    console.log('Mode:', import.meta.env.MODE);
    console.groupEnd();
  }
};