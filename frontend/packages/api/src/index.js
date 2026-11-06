import axios from 'axios';

export function createApi(baseURL) {
  const http = axios.create({
    baseURL,
    withCredentials: true,
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  http.interceptors.request.use((config) => {
    const locale = localStorage.getItem('kavach.locale');
    if (locale) config.headers['Accept-Language'] = locale;
    return config;
  });

  async function csrf() {
    await http.get('/sanctum/csrf-cookie');
  }

  async function mutate(method, url, body, config = {}) {
    await csrf();
    const response = await http.request({ method, url, data: body, ...config });
    return response.data;
  }

  return {
    http,
    get: (url, params) => http.get(url, { params }).then((r) => r.data),
    post: (url, body, config) => mutate('post', url, body, config),
    patch: (url, body) => mutate('patch', url, body),
  };
}

export function errorMessage(error) {
  const data = error?.response?.data;
  if (!data) return { message: 'The request did not reach KAVACH.', offline: !error?.response };
  const first = data.errors ? Object.values(data.errors).flat()[0] : null;
  return { message: first || data.message || 'The request could not be completed.', code: data.code, offline: false };
}

export function idempotencyKey() {
  return crypto.randomUUID();
}
