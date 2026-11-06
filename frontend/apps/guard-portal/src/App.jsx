import { useEffect, useRef, useState } from 'react';
import { Bell, Home as HomeIcon, LogIn, LogOut, QrCode, Siren, UserRound } from 'lucide-react';
import { NavLink, Route, Routes } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import {
  AuthGate, Banner, ConfirmDialog, EmptyState, LanguageSwitch, Logo, Spinner, StatusPill,
  createApi, ensureCsrf, errorMessage, flushPending, listRows, newIdempotencyKey,
  queuePending, readPending, signOut, useSession, useT,
} from '@kavach/app-kit';

const api = createApi();

function formatWhen(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);
  return date.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}

function Shell({ children }) {
  const t = useT();
  return (
    <div className="mx-auto min-h-screen max-w-md pb-28">
      <header className="kv-card mx-4 mt-4 flex items-center justify-between gap-3 px-4 py-3">
        <div className="min-w-0">
          <Logo />
          <p className="mt-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-muted">On post</p>
        </div>
        <LanguageSwitch api={api} enabled />
      </header>
      <main className="px-4 py-5">{children}</main>
      <nav className="fixed inset-x-0 bottom-3 mx-auto grid max-w-md grid-cols-4 rounded-3xl bg-navy px-2 py-2 text-center text-[11px] text-white/70 shadow-xl">
        {[
          ['/', t('guard.home'), HomeIcon],
          ['/patrol', t('guard.patrol'), QrCode],
          ['/alerts', t('guard.alerts'), Bell],
          ['/profile', t('guard.profile'), UserRound],
        ].map(([to, label, Icon]) => (
          <NavLink key={to} to={to} end={to === '/'} className={({ isActive }) => `flex flex-col items-center gap-1 rounded-2xl py-2 ${isActive ? 'bg-white/15 text-white' : ''}`}>
            <Icon size={18} />
            {label}
          </NavLink>
        ))}
      </nav>
    </div>
  );
}

function usePunch() {
  const t = useT();
  const key = useRef(newIdempotencyKey());
  const [notice, setNotice] = useState(null);

  async function punch(path) {
    setNotice(null);
    const body = {};
    if (navigator.geolocation) {
      try {
        const position = await new Promise((resolve, reject) => navigator.geolocation.getCurrentPosition(resolve, reject, { timeout: 4000 }));
        body.latitude = position.coords.latitude;
        body.longitude = position.coords.longitude;
      } catch {
        // Location is optional unless the tenant requires it.
      }
    }
    if (!navigator.onLine) {
      queuePending({ path, body, idempotencyKey: key.current });
      setNotice({ tone: 'warn', text: t('common.pending') });
      return;
    }
    try {
      await ensureCsrf(api);
      const response = await api.post(path, body, { idempotencyKey: key.current });
      key.current = newIdempotencyKey();
      setNotice({ tone: 'success', text: response.data.message || t('guard.recorded') });
    } catch (error) {
      if (!error.response) {
        queuePending({ path, body, idempotencyKey: key.current });
        setNotice({ tone: 'warn', text: t('common.pending') });
        return;
      }
      setNotice({ tone: 'error', text: errorMessage(error) || t('guard.notRecorded') });
    }
  }

  return { notice, punch };
}

function Home() {
  const t = useT();
  const shifts = useQuery({ queryKey: ['shifts'], queryFn: async () => (await api.get('/api/v1/me/shifts')).data.data });
  const { notice, punch } = usePunch();
  const current = shifts.data?.current || [];

  return (
    <Shell>
      <h1 className="text-xl font-bold text-navy">{t('guard.currentShift')}</h1>
      {shifts.isLoading && <div className="mt-4"><Spinner /></div>}
      {shifts.isError && <div className="mt-4"><Banner>{errorMessage(shifts.error) || t('common.error')}</Banner></div>}
      {current.length === 0 && !shifts.isLoading && <div className="mt-4"><EmptyState title={t('guard.noShift')} /></div>}
      {current.map((assignment) => (
        <article key={assignment.id} className="kv-card mt-4 p-4">
          <p className="font-semibold">{assignment.shift?.site?.name || assignment.shift?.name || 'Shift'}</p>
          <p className="mt-1 text-sm text-muted">{formatWhen(assignment.shift?.starts_at)} — {formatWhen(assignment.shift?.ends_at)}</p>
        </article>
      ))}
      <div className="mt-5 grid grid-cols-2 gap-3">
        <button type="button" className="kv-btn kv-btn-primary py-4" onClick={() => punch('/api/v1/attendance/check-in')}><LogIn size={18} />{t('guard.checkIn')}</button>
        <button type="button" className="kv-btn kv-btn-navy py-4" onClick={() => punch('/api/v1/attendance/check-out')}><LogOut size={18} />{t('guard.checkOut')}</button>
      </div>
      {notice && <div className="mt-4"><Banner tone={notice.tone}>{notice.text}</Banner></div>}
      <h2 className="mt-8 text-sm font-bold text-muted">{t('guard.upcoming')}</h2>
      {(shifts.data?.upcoming || []).map((assignment) => (
        <p key={assignment.id} className="mt-2 text-sm">{formatWhen(assignment.shift?.starts_at)}</p>
      ))}
    </Shell>
  );
}

function Patrol() {
  const t = useT();
  const runs = useQuery({ queryKey: ['runs'], queryFn: async () => (await api.get('/api/v1/patrol-runs')).data });
  const [token, setToken] = useState('');
  const [notice, setNotice] = useState(null);
  const key = useRef(newIdempotencyKey());
  const rows = listRows(runs.data);

  async function submit(event) {
    event.preventDefault();
    const run = rows[0];
    if (!run) {
      setNotice({ tone: 'error', text: 'No patrol run is assigned to you.' });
      return;
    }
    try {
      await ensureCsrf(api);
      const response = await api.post(`/api/v1/patrol-runs/${run.id}/events`, { token }, { idempotencyKey: key.current });
      key.current = newIdempotencyKey();
      setNotice({ tone: 'success', text: response.data.message || t('guard.recorded') });
      setToken('');
    } catch (error) {
      setNotice({ tone: 'error', text: errorMessage(error) || t('guard.notRecorded') });
    }
  }

  return (
    <Shell>
      <h1 className="text-xl font-bold text-navy">{t('guard.patrol')}</h1>
      <p className="mt-1 text-sm text-muted">Scan or type the checkpoint token for your assigned patrol run.</p>
      {runs.isLoading && <div className="mt-4"><Spinner /></div>}
      {!runs.isLoading && rows.length === 0 && (
        <div className="mt-4"><EmptyState title="No patrol run assigned" body="Ask your supervisor to publish a patrol for your shift." /></div>
      )}
      {rows.length > 0 && (
        <form className="kv-card mt-4 space-y-3 p-4" onSubmit={submit}>
          <p className="text-sm text-muted">Active run: {rows[0].id?.slice(0, 8)}…</p>
          <input value={token} onChange={(event) => setToken(event.target.value)} placeholder="Checkpoint token" required />
          <button className="kv-btn kv-btn-primary w-full" type="submit">{t('common.save')}</button>
        </form>
      )}
      {notice && <div className="mt-4"><Banner tone={notice.tone}>{notice.text}</Banner></div>}
    </Shell>
  );
}

function Alerts() {
  const t = useT();
  const [open, setOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState(null);
  const key = useRef(newIdempotencyKey());

  async function sendSos() {
    setBusy(true);
    setNotice(null);
    try {
      await ensureCsrf(api);
      const response = await api.post('/api/v1/emergency/sos', { message: 'SOS' }, { idempotencyKey: key.current });
      key.current = newIdempotencyKey();
      setNotice({
        tone: response.data.data?.notification_status === 'stored' ? 'success' : 'warn',
        text: response.data.message,
      });
      setOpen(false);
    } catch (error) {
      if (!error.response) {
        queuePending({ path: '/api/v1/emergency/sos', body: { message: 'SOS' }, idempotencyKey: key.current });
        setNotice({ tone: 'warn', text: t('common.pending') });
      } else {
        setNotice({ tone: 'error', text: errorMessage(error) || t('common.failed') });
      }
      setOpen(false);
    } finally {
      setBusy(false);
    }
  }

  return (
    <Shell>
      <h1 className="text-xl font-bold text-navy">{t('guard.alerts')}</h1>
      <p className="mt-1 text-sm text-muted">Use SOS only when you need immediate help. Incidents are for non-emergency reports.</p>
      <button type="button" className="kv-btn kv-btn-danger mt-4 w-full py-5 text-lg" onClick={() => setOpen(true)}>
        <Siren size={22} />{t('guard.sos')}
      </button>
      <p className="mt-2 text-sm text-muted">{t('guard.sosHelp')}</p>
      <IncidentForm />
      {notice && <div className="mt-4"><Banner tone={notice.tone}>{notice.text}</Banner></div>}
      <ConfirmDialog open={open} title={t('guard.sos')} body={t('guard.sosHelp')} confirmLabel={busy ? t('guard.sosSending') : t('guard.sosSend')} busy={busy} onClose={() => setOpen(false)} onConfirm={sendSos} />
    </Shell>
  );
}

function IncidentForm() {
  const t = useT();
  const [notice, setNotice] = useState(null);
  async function submit(event) {
    event.preventDefault();
    const body = Object.fromEntries(new FormData(event.currentTarget));
    body.priority = 'medium';
    try {
      await ensureCsrf(api);
      await api.post('/api/v1/incidents', body);
      setNotice({ tone: 'success', text: t('guard.recorded') });
      event.currentTarget.reset();
    } catch (error) {
      setNotice({ tone: 'error', text: errorMessage(error) || t('guard.notRecorded') });
    }
  }
  return (
    <form className="kv-card mt-8 space-y-3 p-4" onSubmit={submit}>
      <h2 className="font-bold">{t('guard.report')}</h2>
      <input name="title" required placeholder={t('guard.title')} />
      <textarea name="description" required placeholder={t('guard.description')} rows={4} />
      {notice && <Banner tone={notice.tone}>{notice.text}</Banner>}
      <button className="kv-btn kv-btn-navy w-full" type="submit">{t('guard.submit')}</button>
    </form>
  );
}

function Profile() {
  const t = useT();
  const queryClient = useQueryClient();
  const { profile } = useSession(api);
  const pending = readPending();
  const documents = useQuery({ queryKey: ['docs'], queryFn: async () => (await api.get('/api/v1/me/documents')).data.data });
  const payslips = useQuery({ queryKey: ['payslips'], queryFn: async () => (await api.get('/api/v1/payslips')).data, retry: false });

  useEffect(() => {
    const flush = () => flushPending(api);
    window.addEventListener('online', flush);
    flush();
    return () => window.removeEventListener('online', flush);
  }, []);

  return (
    <Shell>
      <h1 className="text-xl font-bold">{profile?.user?.name}</h1>
      <p className="text-sm text-muted">{profile?.user?.email}</p>
      <p className="mt-1 text-sm text-muted">{profile?.tenant?.name}</p>
      <h2 className="mt-6 font-bold">{t('guard.documents')}</h2>
      {(documents.data || []).length === 0 && <p className="mt-2 text-sm text-muted">{t('common.empty')}</p>}
      <h2 className="mt-6 font-bold">{t('guard.payslips')}</h2>
      {payslips.isError && <p className="mt-2 text-sm text-muted">{errorMessage(payslips.error) || t('common.empty')}</p>}
      {listRows(payslips.data).map((slip) => (
        <p key={slip.id} className="mt-2 text-sm"><StatusPill value={slip.status} /> {slip.net ?? slip.net_amount}</p>
      ))}
      {pending.length > 0 && <div className="mt-4"><Banner tone="warn">{pending.length} {t('common.pending')}</Banner></div>}
      <button
        type="button"
        className="kv-btn kv-btn-ghost mt-8 w-full"
        onClick={async () => {
          await signOut(api, queryClient);
          window.location.reload();
        }}
      >
        {t('common.signOut')}
      </button>
    </Shell>
  );
}

export default function App() {
  return (
    <AuthGate api={api} portal="guard" title="Guard" hint="Sign in to the post assigned to you.">
      <Routes>
        <Route path="/" element={<Home />} />
        <Route path="/patrol" element={<Patrol />} />
        <Route path="/alerts" element={<Alerts />} />
        <Route path="/profile" element={<Profile />} />
      </Routes>
    </AuthGate>
  );
}
