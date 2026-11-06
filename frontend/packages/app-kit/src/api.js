import axios from 'axios';

export function createApi(baseURL) {
  const api = axios.create({
    baseURL: baseURL || import.meta.env.VITE_API_URL || 'http://localhost:8000',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  api.interceptors.request.use((config) => {
    const locale = localStorage.getItem('kavach.locale');
    if (locale) {
      config.headers['Accept-Language'] = locale;
    }
    if (config.idempotencyKey) {
      config.headers['Idempotency-Key'] = config.idempotencyKey;
    }
    return config;
  });

  return api;
}

export async function ensureCsrf(api) {
  await api.get('/sanctum/csrf-cookie');
}

export function errorMessage(error) {
  const data = error?.response?.data;
  if (!data) return null;
  if (data.errors) {
    const first = Object.values(data.errors)[0];
    if (Array.isArray(first) && first[0]) return first[0];
  }
  const message = data.message || null;
  if (!message) return null;
  if (/unique|duplicate key|SQLSTATE/i.test(message)) {
    return 'This code or value is already in use for your organization. Choose a different one.';
  }
  return message;
}

export function newIdempotencyKey() {
  if (crypto.randomUUID) return crypto.randomUUID();
  return `key-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

/** Normalize API list payloads: paginated `{ data: [] }` or raw arrays. */
export function listRows(payload) {
  if (!payload) return [];
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload.data)) return payload.data;
  return [];
}

export function isMfaRequired(error) {
  return error?.response?.status === 403 && error?.response?.data?.code === 'mfa_enrollment_required';
}
