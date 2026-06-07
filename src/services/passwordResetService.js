const API_BASE = import.meta.env.VITE_API_URL || '/api';

export async function requestPasswordResetOtp(phone) {
  const res = await fetch(`${API_BASE}/auth/password-reset/request`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ phone }),
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok || !json.success) {
    throw new Error(json.error || 'Failed to send verification code');
  }
  return json;
}

export async function confirmPasswordReset({ phone, otp, newPassword }) {
  const res = await fetch(`${API_BASE}/auth/password-reset/confirm`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ phone, otp, newPassword }),
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok || !json.success) {
    throw new Error(json.error || 'Failed to reset password');
  }
  return json;
}
