const DATE = { day: '2-digit', month: 'short', year: 'numeric' };
const TIME = { hour: '2-digit', minute: '2-digit', hour12: true };
const DATETIME = { ...DATE, ...TIME };

function asDate(value) {
  if (value == null || value === '') return null;
  const date = value instanceof Date ? value : new Date(value);
  return Number.isNaN(date.getTime()) ? null : date;
}

/** Prefer calendar Y-M-D so work_date / period fields do not shift by timezone. */
function calendarParts(value) {
  if (typeof value === 'string') {
    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (match) {
      return { y: Number(match[1]), m: Number(match[2]), d: Number(match[3]) };
    }
  }
  const date = asDate(value);
  if (!date) return null;
  return { y: date.getFullYear(), m: date.getMonth() + 1, d: date.getDate() };
}

/** Calendar date for work_date / period fields. */
export function formatDate(value, fallback = '—') {
  const parts = calendarParts(value);
  if (!parts) return fallback;
  const date = new Date(parts.y, parts.m - 1, parts.d);
  return date.toLocaleDateString(undefined, DATE);
}

/** Punch / audit timestamps. */
export function formatDateTime(value, fallback = '—') {
  const date = asDate(value);
  if (!date) return fallback;
  return date.toLocaleString(undefined, DATETIME);
}

export function formatTime(value, fallback = '—') {
  const date = asDate(value);
  if (!date) return fallback;
  return date.toLocaleTimeString(undefined, TIME);
}

export function formatMoney(value, currency = 'INR', fallback = '—') {
  if (value == null || value === '') return fallback;
  const amount = Number(value);
  if (Number.isNaN(amount)) return fallback;
  try {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency, maximumFractionDigits: 2 }).format(amount);
  } catch {
    return `₹${amount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  }
}

export function formatPeriod(start, end) {
  if (!start && !end) return '—';
  return `${formatDate(start)} – ${formatDate(end)}`;
}
