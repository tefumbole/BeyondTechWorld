const API_BASE = import.meta.env.VITE_API_URL || '/api';
const STORAGE_KEY = 'alpha_supabase_auth';

function getToken() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    return parsed?.access_token || parsed?.currentSession?.access_token || null;
  } catch {
    return null;
  }
}

function authHeaders(extra = {}) {
  const token = getToken();
  return {
    'Content-Type': 'application/json',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
    ...extra,
  };
}

export const getActivityLogs = async ({ search = '', action = '', entity = '', limit = 100, offset = 0 } = {}) => {
  try {
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    if (action) params.set('action', action);
    if (entity) params.set('entity', entity);
    params.set('limit', String(limit));
    params.set('offset', String(offset));

    const res = await fetch(`${API_BASE}/activity-logs?${params.toString()}`, {
      headers: authHeaders(),
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.error) {
      throw new Error(json.error?.message || 'Failed to load activity logs');
    }
    return { success: true, data: json.data || [], count: json.count || 0 };
  } catch (error) {
    console.error('getActivityLogs error:', error);
    return { success: false, error: error.message, data: [], count: 0 };
  }
};

export const deleteActivityLogs = async (ids = []) => {
  try {
    const res = await fetch(`${API_BASE}/activity-logs`, {
      method: 'DELETE',
      headers: authHeaders(),
      body: JSON.stringify({ ids }),
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.error) throw new Error(json.error?.message || 'Delete failed');
    return { success: true, count: json.data?.count || ids.length };
  } catch (error) {
    return { success: false, error: error.message };
  }
};

export const clearActivityLogs = async () => {
  try {
    const res = await fetch(`${API_BASE}/activity-logs`, {
      method: 'DELETE',
      headers: authHeaders(),
      body: JSON.stringify({ all: true }),
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.error) throw new Error(json.error?.message || 'Clear failed');
    return { success: true };
  } catch (error) {
    return { success: false, error: error.message };
  }
};

export default { getActivityLogs, deleteActivityLogs, clearActivityLogs };
