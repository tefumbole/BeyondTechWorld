/**
 * MySQL-backed client that mimics the Supabase JS API surface used by AlphaBridge.
 * Used when VITE_DATA_BACKEND=mysql (local/offline or Hostinger VPS).
 */

const STORAGE_KEY = 'alpha_supabase_auth';
const API_BASE = import.meta.env.VITE_API_URL || '/api';

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

function saveSession(session) {
  if (!session) {
    localStorage.removeItem(STORAGE_KEY);
    return;
  }
  localStorage.setItem(STORAGE_KEY, JSON.stringify({
    access_token: session.access_token,
    refresh_token: session.refresh_token,
    expires_at: session.expires_at,
    currentSession: session,
    user: session.user,
  }));
}

async function apiFetch(path, options = {}) {
  const token = getToken();
  const headers = {
    'Content-Type': 'application/json',
    ...(options.headers || {}),
  };
  if (token) headers.Authorization = `Bearer ${token}`;

  const res = await fetch(`${API_BASE}${path}`, { ...options, headers });
  const json = await res.json().catch(() => ({}));

  if (!res.ok && !json.error) {
    return { data: null, error: { message: json.error || res.statusText } };
  }
  return json;
}

class QueryBuilder {
  constructor(table) {
    this.table = table;
    this.action = 'select';
    this.filters = [];
    this.orderClauses = [];
    this.limitVal = null;
    this.offsetVal = null;
    this.columns = '*';
    this.countOpt = false;
    this.headOpt = false;
    this.singleOpt = false;
    this.maybeSingleOpt = false;
    this.payload = null;
    this.onConflict = 'id';
  }

  select(columns = '*', options = {}) {
    const cols = typeof columns === 'string' ? columns.trim() : '*';
    if (this.action === 'insert' || this.action === 'update' || this.action === 'upsert') {
      this.returning = true;
      this.columns = cols;
      return this;
    }
    this.action = 'select';
    this.columns = cols;
    if (options?.count === 'exact') this.countOpt = true;
    if (options?.head) this.headOpt = true;
    return this;
  }

  insert(payload) {
    this.action = 'insert';
    this.payload = payload;
    return this;
  }

  update(payload) {
    this.action = 'update';
    this.payload = payload;
    return this;
  }

  upsert(payload, opts = {}) {
    this.action = 'upsert';
    this.payload = payload;
    this.onConflict = opts.onConflict || 'id';
    return this;
  }

  delete() {
    this.action = 'delete';
    return this;
  }

  eq(col, val) { this.filters.push({ op: 'eq', col, value: val }); return this; }
  neq(col, val) { this.filters.push({ op: 'neq', col, value: val }); return this; }
  gt(col, val) { this.filters.push({ op: 'gt', col, value: val }); return this; }
  gte(col, val) { this.filters.push({ op: 'gte', col, value: val }); return this; }
  lt(col, val) { this.filters.push({ op: 'lt', col, value: val }); return this; }
  lte(col, val) { this.filters.push({ op: 'lte', col, value: val }); return this; }
  is(col, val) { this.filters.push({ op: 'is', col, value: val }); return this; }
  in(col, values) { this.filters.push({ op: 'in', col, values }); return this; }
  ilike(col, val) { this.filters.push({ op: 'ilike', col, value: val }); return this; }

  not(col, op, val) {
    if (op === 'is' && val === null) {
      this.filters.push({ op: 'not_null', col });
    } else if (op === 'in') {
      const values = String(val)
        .replace(/[()"]/g, '')
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean);
      this.filters.push({ op: 'not_in', col, values });
    }
    return this;
  }

  or(expr) {
    const parts = expr.split(',').map((part) => {
      const trimmed = part.trim();
      const m = trimmed.match(/^(\w+)\.(ilike|eq|lte|gte|lt|gt|is)\.(.+)$/i);
      if (!m) return null;
      const [, c, op, rawVal] = m;
      const opLower = op.toLowerCase();
      if (opLower === 'is' && rawVal === 'null') {
        return { col: c, op: 'is', value: null };
      }
      if (opLower === 'ilike') {
        const inner = rawVal.replace(/^%/, '').replace(/%$/, '');
        return { col: c, op: 'ilike', value: `%${inner}%` };
      }
      if (['eq', 'lte', 'gte', 'lt', 'gt'].includes(opLower)) {
        return { col: c, op: opLower, value: rawVal };
      }
      return null;
    }).filter(Boolean);
    this.filters.push({ op: 'or', parts });
    return this;
  }

  order(col, opts = {}) {
    this.orderClauses.push({ column: col, ascending: opts.ascending !== false });
    return this;
  }

  limit(n) { this.limitVal = n; return this; }

  range(from, to) {
    this.offsetVal = from;
    this.limitVal = to - from + 1;
    return this;
  }

  single() { this.singleOpt = true; return this; }
  maybeSingle() { this.singleOpt = true; this.maybeSingleOpt = true; return this; }

  async execute() {
    const body = {
      table: this.table,
      action: this.action,
      filters: this.filters,
      order: this.orderClauses,
      limit: this.limitVal,
      offset: this.offsetVal,
      columns: this.columns,
      count: this.countOpt,
      head: this.headOpt,
      single: this.singleOpt,
      maybeSingle: this.maybeSingleOpt,
      payload: this.payload,
      onConflict: this.onConflict,
    };

    const json = await apiFetch('/data/query', { method: 'POST', body: JSON.stringify(body) });
    if (json && ('data' in json || 'error' in json)) {
      return json;
    }
    return { data: null, error: { message: 'Invalid API response' } };
  }

  then(onFulfilled, onRejected) {
    return this.execute().then(onFulfilled, onRejected);
  }
}

const authListeners = [];

function notifyAuth(event, session) {
  for (const cb of authListeners) {
    try { cb(event, session); } catch (e) { console.error(e); }
  }
}

const auth = {
  async getSession() {
    const token = getToken();
    if (!token) return { data: { session: null }, error: null };

    const { session, error } = await apiFetch('/auth/session');
    if (error || !session) {
      saveSession(null);
      return { data: { session: null }, error: error || null };
    }
    saveSession(session);
    return { data: { session }, error: null };
  },

  async getUser() {
    const { data } = await this.getSession();
    if (!data?.session?.user) {
      return { data: { user: null }, error: { message: 'Not authenticated' } };
    }
    return { data: { user: data.session.user }, error: null };
  },

  async signInWithPassword({ email, password, identifier }) {
    const result = await apiFetch('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, identifier: identifier || email, password }),
    });

    if (result.error || !result.session) {
      return { data: { session: null, user: null }, error: result.error || { message: 'Login failed' } };
    }

    saveSession(result.session);
    notifyAuth('SIGNED_IN', result.session);
    return { data: { session: result.session, user: result.session.user }, error: null };
  },

  async signOut() {
    await apiFetch('/auth/logout', { method: 'POST' });
    saveSession(null);
    notifyAuth('SIGNED_OUT', null);
    return { error: null };
  },

  async refreshSession() {
    const token = getToken();
    if (!token) return { data: { session: null }, error: null };

    const { session, error } = await apiFetch('/auth/refresh', { method: 'POST' });
    if (session) {
      saveSession(session);
      notifyAuth('TOKEN_REFRESHED', session);
      return { data: { session }, error: null };
    }
    return { data: { session: null }, error };
  },

  onAuthStateChange(callback) {
    authListeners.push(callback);
    return {
      data: {
        subscription: {
          unsubscribe: () => {
            const idx = authListeners.indexOf(callback);
            if (idx >= 0) authListeners.splice(idx, 1);
          },
        },
      },
    };
  },
};

const storage = {
  from(bucket) {
    return {
      async upload(filePath, file, _opts = {}) {
        const form = new FormData();
        form.append('file', file);
        form.append('path', filePath);
        const token = getToken();
        const res = await fetch(
          `${API_BASE}/upload/${bucket}?path=${encodeURIComponent(filePath)}`,
          {
            method: 'POST',
            headers: token ? { Authorization: `Bearer ${token}` } : {},
            body: form,
          }
        );
        const text = await res.text();
        let json = {};
        try {
          json = text ? JSON.parse(text) : {};
        } catch {
          json = { error: text || res.statusText || 'Upload failed' };
        }
        if (!res.ok) return { data: null, error: json.error ? json : { message: text || 'Upload failed' } };
        return { data: json, error: null };
      },

      async download(filePath) {
        const res = await fetch(`${API_BASE}/upload/${bucket}/${encodeURIComponent(filePath)}`);
        if (!res.ok) return { data: null, error: { message: 'Download failed' } };
        const blob = await res.blob();
        return { data: blob, error: null };
      },

      getPublicUrl(filePath) {
        const safe = String(filePath || 'file').replace(/[^a-zA-Z0-9._-]/g, '_');
        const origin = typeof window !== 'undefined' ? window.location.origin : '';
        return {
          data: { publicUrl: `${origin}${API_BASE}/upload/${bucket}/${encodeURIComponent(safe)}` },
        };
      },

      async list(_prefix = '', _opts = {}) {
        return { data: [], error: null };
      },
    };
  },
};

const mysqlDataClient = {
  auth,
  storage,
  from(table) {
    return new QueryBuilder(table);
  },
};

export default mysqlDataClient;
export { mysqlDataClient as supabase };
