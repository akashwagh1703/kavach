import { useCallback, useEffect, useRef, useState } from 'react';
import { createTheme, Dialog, DialogActions, DialogContent, DialogTitle, ThemeProvider } from '@mui/material';
import { Check, Copy, ExternalLink, Inbox, Shield, X } from 'lucide-react';
import { acceptInvitationUrl } from './portals.js';

const theme = createTheme({
  palette: {
    primary: { main: '#2F6FED' },
    secondary: { main: '#14B8A6' },
    warning: { main: '#F58220' },
    error: { main: '#DC2626' },
    text: { primary: '#0B1F44', secondary: '#64748B' },
    background: { default: '#F4F7FB' },
  },
  typography: {
    fontFamily: '"Plus Jakarta Sans", Inter, "Noto Sans Devanagari", sans-serif',
  },
  shape: { borderRadius: 14 },
  components: {
    MuiTableCell: {
      styleOverrides: {
        root: { borderColor: '#E6EDF6', fontFamily: 'inherit', padding: '14px 16px' },
        head: { fontWeight: 700, color: '#0B1F44', background: '#F8FBFF', fontSize: 12, letterSpacing: '0.04em', textTransform: 'uppercase' },
      },
    },
  },
});

export function KavachTheme({ children }) {
  return <ThemeProvider theme={theme}>{children}</ThemeProvider>;
}

/**
 * Brand lockup.
 * Light surfaces: official logo.png (navy wordmark — never invert).
 * Dark panels: shield + white wordmark (logo.png has navy text).
 */
export function Logo({ compact = false, light = false }) {
  if (compact) {
    return <img src="/favicon.png" alt="KAVACH" className="h-10 w-10 object-contain" />;
  }
  if (light) {
    return (
      <div className="flex items-center gap-3">
        <img src="/favicon.png" alt="" className="h-11 w-11 object-contain" />
        <div>
          <div className="text-[1.15rem] font-extrabold tracking-[0.18em] text-white">
            K<span className="relative">A<span className="absolute left-1/2 top-[58%] h-1.5 w-1.5 -translate-x-1/2 bg-orange" style={{ clipPath: 'polygon(50% 0, 100% 100%, 0 100%)' }} /></span>VACH
          </div>
          <div className="mt-0.5 text-[10px] tracking-[0.2em] text-white/65">SECURITY · CONTROL · TRUST</div>
        </div>
      </div>
    );
  }
  return (
    <img
      src="/logo.png"
      alt="KAVACH — SECURITY · CONTROL · TRUST"
      className="h-12 w-auto max-w-[240px] object-contain object-left drop-shadow-sm sm:h-14"
    />
  );
}

export function BrandMark({ size = 40 }) {
  return <img src="/favicon.png" alt="KAVACH" style={{ width: size, height: size }} className="object-contain" />;
}

export function IconBadge({ icon: Icon = Shield, tone = 'blue' }) {
  return (
    <span className={`kv-icon ${tone === 'blue' ? '' : tone}`}>
      <Icon size={20} strokeWidth={1.75} />
    </span>
  );
}

export function PageHeading({ kicker, title, body, action }) {
  return (
    <header className="mb-6 flex flex-wrap items-end justify-between gap-4">
      <div>
        {kicker && <p className="text-xs font-bold uppercase tracking-[0.18em] text-electric">{kicker}</p>}
        <h1 className="mt-1 text-3xl font-bold tracking-tight text-navy">{title}</h1>
        {body && <p className="mt-2 max-w-2xl text-sm leading-6 text-muted">{body}</p>}
      </div>
      {action}
    </header>
  );
}

export function StatCard({ icon: Icon, label, value, tone = 'blue' }) {
  return (
    <article className="kv-card kv-card-hover flex items-start justify-between p-5">
      <div>
        <p className="text-sm font-medium text-muted">{label}</p>
        <p className="mt-2 text-3xl font-bold tracking-tight text-navy">{value ?? '—'}</p>
      </div>
      {Icon && <IconBadge icon={Icon} tone={tone} />}
    </article>
  );
}

export function Field({ label, hint, children, className = '' }) {
  return (
    <label className={`block text-sm font-semibold text-navy ${className}`}>
      <span className="mb-2 block">{label}</span>
      {children}
      {hint ? <span className="mt-1.5 block text-xs font-medium leading-5 text-muted">{hint}</span> : null}
    </label>
  );
}

export function SectionCard({ title, body, icon: Icon, tone = 'blue', children, action }) {
  return (
    <section className="kv-card overflow-hidden">
      <div className="flex flex-wrap items-start justify-between gap-4 border-b border-[var(--kv-line)] bg-[#F8FBFF] px-5 py-4">
        <div className="flex gap-3">
          {Icon ? <IconBadge icon={Icon} tone={tone} /> : null}
          <div>
            <h2 className="text-base font-bold text-navy">{title}</h2>
            {body ? <p className="mt-1 max-w-2xl text-sm leading-6 text-muted">{body}</p> : null}
          </div>
        </div>
        {action}
      </div>
      <div className="p-5">{children}</div>
    </section>
  );
}

export function TokenReveal({ token, label = 'Invitation token' }) {
  const [copied, setCopied] = useState(false);
  if (!token) return null;
  const acceptUrl = acceptInvitationUrl(token);

  async function copy() {
    try {
      await navigator.clipboard.writeText(token);
      setCopied(true);
      setTimeout(() => setCopied(false), 1600);
    } catch {
      // Clipboard may be blocked; token remains visible.
    }
  }

  return (
    <div className="rounded-2xl border border-blue-100 bg-gradient-to-br from-blue-50 to-orange-50/40 p-4">
      <p className="text-[11px] font-bold uppercase tracking-[0.16em] text-electric">{label}</p>
      <p className="mt-2 break-all font-mono text-[13px] leading-6 text-navy">{token}</p>
      <p className="mt-2 text-xs text-muted">Share this once. The owner or worker accepts it on the public accept page, then signs into the matching portal.</p>
      <div className="mt-3 flex flex-wrap gap-2">
        <button type="button" className="kv-btn kv-btn-ghost !px-3 !py-2 !text-xs" onClick={copy}>
          {copied ? <Check size={14} /> : <Copy size={14} />}
          {copied ? 'Copied' : 'Copy token'}
        </button>
        <a className="kv-btn kv-btn-navy !px-3 !py-2 !text-xs" href={acceptUrl} target="_blank" rel="noreferrer">
          <ExternalLink size={14} />
          Open accept page
        </a>
      </div>
    </div>
  );
}

export function StatusPill({ value, label }) {
  const key = String(value || '').toLowerCase();
  const tone = {
    active: 'bg-mint text-teal-800',
    'active login': 'bg-mint text-teal-800',
    verified: 'bg-mint text-teal-800',
    complete: 'bg-mint text-teal-800',
    stored: 'bg-mint text-teal-800',
    approved: 'bg-mint text-teal-800',
    paid: 'bg-mint text-teal-800',
    open: 'bg-amber-100 text-amber-900',
    pending: 'bg-amber-100 text-amber-900',
    'invite pending': 'bg-amber-100 text-amber-900',
    exception: 'bg-amber-100 text-amber-900',
    trial: 'bg-blue-50 text-electric',
    draft: 'bg-slate-100 text-slate-600',
    'not invited': 'bg-slate-100 text-slate-600',
    none: 'bg-slate-100 text-slate-600',
    suspended: 'bg-red-50 text-danger',
    critical: 'bg-red-50 text-danger',
    failed: 'bg-red-50 text-danger',
    assigned: 'bg-blue-50 text-electric',
    investigating: 'bg-amber-100 text-amber-900',
    resolved: 'bg-mint text-teal-800',
    guard: 'bg-blue-50 text-electric',
    supervisor: 'bg-orange-50 text-orange-800',
    staff: 'bg-slate-100 text-slate-700',
    agency: 'bg-blue-50 text-electric',
    inhouse: 'bg-slate-100 text-slate-700',
  }[key] || 'bg-slate-100 text-slate-600';
  const text = label || (value == null || value === '' ? '—' : String(value).replaceAll('_', ' '));
  return <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${tone}`}>{text}</span>;
}

export function EmptyState({ title, body }) {
  return (
    <div className="rounded-2xl border border-dashed border-[var(--kv-line)] bg-[#F8FBFF] px-6 py-12 text-center">
      <span className="kv-icon mx-auto"><Inbox size={20} /></span>
      <p className="mt-4 text-base font-semibold text-navy">{title}</p>
      {body && <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-muted">{body}</p>}
    </div>
  );
}

export function Spinner() {
  return <div className="h-5 w-5 animate-spin rounded-full border-2 border-electric border-t-transparent" />;
}

export function Banner({ tone = 'error', children, onDismiss }) {
  const styles = tone === 'success'
    ? 'bg-mint text-teal-900 border-teal-200'
    : tone === 'warn'
      ? 'bg-amber-50 text-amber-950 border-amber-200'
      : tone === 'info'
        ? 'bg-blue-50 text-navy border-blue-100'
        : 'bg-red-50 text-red-800 border-red-100';
  return (
    <div className={`flex items-start justify-between gap-3 rounded-2xl border px-4 py-3 text-sm leading-6 ${styles}`} role="status">
      <div className="min-w-0 flex-1">{children}</div>
      {onDismiss ? (
        <button type="button" className="shrink-0 rounded-lg p-1 opacity-70 hover:opacity-100" onClick={onDismiss} aria-label="Dismiss">
          <X size={14} />
        </button>
      ) : null}
    </div>
  );
}

/**
 * One feedback banner at a time — success and error never stack.
 * Auto-clears success after a few seconds so stale "failed" copy cannot linger over a fresh list.
 */
export function useActionFlash(successMs = 4500) {
  const [flash, setFlash] = useState(null);
  const timer = useRef(null);

  const clear = useCallback(() => {
    if (timer.current) clearTimeout(timer.current);
    timer.current = null;
    setFlash(null);
  }, []);

  const show = useCallback((tone, text) => {
    if (timer.current) clearTimeout(timer.current);
    setFlash({ tone, text });
    if (tone === 'success' && successMs > 0) {
      timer.current = setTimeout(() => setFlash(null), successMs);
    }
  }, [successMs]);

  useEffect(() => () => { if (timer.current) clearTimeout(timer.current); }, []);

  return {
    flash,
    clear,
    success: (text) => show('success', text),
    error: (text) => show('error', text),
    warn: (text) => show('warn', text),
    info: (text) => show('info', text),
  };
}

export function ActionFlash({ flash, onDismiss, className = '' }) {
  if (!flash?.text) return null;
  return (
    <div className={className}>
      <Banner tone={flash.tone || 'error'} onDismiss={onDismiss}>{flash.text}</Banner>
    </div>
  );
}

export function ConfirmDialog({ open, title, body, confirmLabel, onClose, onConfirm, busy }) {
  return (
    <Dialog open={open} onClose={busy ? undefined : onClose} fullWidth maxWidth="xs">
      <DialogTitle>{title}</DialogTitle>
      <DialogContent><p className="text-sm text-muted">{body}</p></DialogContent>
      <DialogActions>
        <button type="button" className="px-3 py-2 text-sm text-muted" onClick={onClose} disabled={busy}>Cancel</button>
        <button type="button" className="rounded-xl bg-danger px-4 py-2 text-sm font-semibold text-white" onClick={onConfirm} disabled={busy}>{confirmLabel}</button>
      </DialogActions>
    </Dialog>
  );
}

export { theme };
