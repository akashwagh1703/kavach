const KEY = 'kavach.pending';

export function readPending() {
  try {
    return JSON.parse(localStorage.getItem(KEY) || '[]');
  } catch {
    return [];
  }
}

export function queuePending(item) {
  const items = readPending();
  items.push({ ...item, queuedAt: new Date().toISOString(), state: 'pending' });
  localStorage.setItem(KEY, JSON.stringify(items));
}

export function writePending(items) {
  localStorage.setItem(KEY, JSON.stringify(items));
}

export async function flushPending(api) {
  const items = readPending();
  const remaining = [];
  for (const item of items) {
    if (!navigator.onLine) {
      remaining.push(item);
      continue;
    }
    try {
      await api.post(item.path, item.body || {}, { idempotencyKey: item.idempotencyKey });
    } catch (error) {
      if (!error.response) {
        remaining.push(item);
      } else {
        remaining.push({ ...item, state: 'failed', message: error.response?.data?.message || 'Rejected' });
      }
    }
  }
  writePending(remaining);
  return remaining;
}
