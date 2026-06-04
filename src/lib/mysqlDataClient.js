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
    this.order = [];
    this.limitVal = null;
    this.columns = '*';
    this.countOpt = false;
    this.headOpt = false;
    this.singleOpt = false;
    this.maybeSingleOpt = false;
    this.payload = null;
    this.onConflict = 'id';
  }

  select(columns = '*', options = {}) {
    this.action = 'select';
    this.columns = typeof columns === 'string' ? columns.split(',')[0].trim() : '*';
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

  or(expr) {
    const parts = expr.split(',').map((part) => {
      const m = part.trim().match(/^(\w+)\.(ilike|eq)\.(.+)$/i);
      if (!m) return null;
      const [, c, op, val] = m;
      if (op.toLowerCase() === 'ilike') {
        const inner = val.replace(/^%/, '').replace(/%$/, '');
        return { col: c, op: 'ilike', value: `%${inner}%` };
      }
      return { col: c, op: 'eq', value: val };
    }).filter(Boolean);
    this.filters.push({ op: 'or', parts });
    return this;
  }

  order(col, opts = {}) {
    this.order.push({ column: col, ascending: opts.ascending !== false });
    return this;
  }

  limit(n) { this.limitVal = n; return this; }
  single() { this.singleOpt = true; return this; }
  maybeSingle() { this.singleOpt = true; this.maybeSingleOpt = true; return this; }

  async execute() {
    const body = {
      table: this.table,
      action: this.action,
      filters: this.filters,
      order: this.order,
      limit: this.limitVal,
      columns: this.columns,
      count: this.countOpt,
      head: this.headOpt,
      single: this.singleOpt,
      maybeSingle: this.maybeSingleOpt,
      payload: this.payload,
      onConflict: this.onConflict,
    };

    return apiFetch('/data/query', { method: 'POST', body: JSON.stringify(body) });
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

  async signInWithPassword({ email, password }) {
    const result = await apiFetch('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
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
        const res = await fetch(`${API_BASE}/upload/${bucket}`, {
          method: 'POST',
          headers: token ? { Authorization: `Bearer ${token}` } : {},
          body: form,
        });
        const json = await res.json();
        if (!res.ok) return { data: null, error: json };
        return { data: json, error: null };
      },

      async download(filePath) {
        const res = await fetch(`${API_BASE}/upload/${bucket}/${encodeURIComponent(filePath)}`);
        if (!res.ok) return { data: null, error: { message: 'Download failed' } };
        const blob = await res.blob();
        return { data: blob, error: null };
      },

      getPublicUrl(filePath) {
        return {
          data: { publicUrl: `${API_BASE}/upload/${bucket}/${encodeURIComponent(filePath)}` },
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
