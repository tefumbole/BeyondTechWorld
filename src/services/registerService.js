const API_BASE = import.meta.env.VITE_API_URL || '/api';

export async function requestRegistration({ email, password, full_name, phone, inviteToken }) {
  const res = await fetch(`${API_BASE}/auth/register/request`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password, full_name, phone, inviteToken }),
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok || !json.success) {
    throw new Error(json.error || 'Registration request failed');
  }
  return json;
}

export async function verifyRegistration({ pendingId, otp }) {
  const res = await fetch(`${API_BASE}/auth/register/verify`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ pendingId, otp }),
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok || !json.success) {
    throw new Error(json.error || 'Verification failed');
  }
  return json;
}

export async function fetchTaskInvite(token) {
  const res = await fetch(`${API_BASE}/tasks/invite/${encodeURIComponent(token)}`);
  const json = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(json.error || 'Invite not found');
  return json;
}
