import { Bell, CalendarCheck, LayoutDashboard, TriangleAlert } from 'lucide-react';
import { NavLink, Route, Routes } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import {
  AuthGate, Banner, EmptyState, LanguageSwitch, Logo, PageHeading, Spinner, StatCard, StatusPill,
  createApi, ensureCsrf, errorMessage, listRows, signOut, useT,
} from '@kavach/app-kit';

const api = createApi();

function Shell({ children }) {
  const t = useT();
  const queryClient = useQueryClient();
  return (
    <div className="min-h-screen">
      <header className="kv-top flex flex-wrap items-center justify-between gap-3 px-5 py-3">
        <div>
          <Logo />
          <p className="mt-1 text-xs font-semibold uppercase tracking-[0.14em] text-muted">Supervisor desk</p>
        </div>
        <div className="flex items-center gap-3">
          <LanguageSwitch api={api} enabled />
          <button
            type="button"
            className="kv-btn kv-btn-ghost !px-3 !py-2 !text-sm"
            onClick={async () => {
              await signOut(api, queryClient);
              window.location.reload();
            }}
          >
            Sign out
          </button>
        </div>
      </header>
      <div className="mx-auto grid max-w-5xl gap-6 px-4 py-6 md:grid-cols-[220px_1fr]">
        <nav className="flex gap-2 overflow-auto md:flex-col">
          {[
            ['/', t('supervisor.dashboard'), LayoutDashboard],
            ['/attendance', t('supervisor.attendance'), CalendarCheck],
            ['/incidents', t('supervisor.incidents'), TriangleAlert],
            ['/alerts', t('supervisor.emergencies'), Bell],
          ].map(([to, label, Icon]) => (
            <NavLink
              key={to}
              to={to}
              end={to === '/'}
              className={({ isActive }) => `kv-nav-pill w-full ${isActive ? 'active' : 'bg-white shadow-[inset_0_0_0_1px_var(--kv-line)]'}`}
            >
              <Icon size={18} />
              {label}
            </NavLink>
          ))}
        </nav>
        <main>{children}</main>
      </div>
    </div>
  );
}

function Today() {
  const t = useT();
  const query = useQuery({ queryKey: ['dash'], queryFn: async () => (await api.get('/api/v1/dashboard')).data.data });
  const cards = query.data || {};
  return (
    <Shell>
      <PageHeading title={t('supervisor.dashboard')} body="Live counts for the sites assigned to you. Verify exceptions, review incidents, and close SOS from here." />
      {query.isLoading && <Spinner />}
      {query.isError && <Banner>{errorMessage(query.error) || t('common.error')}</Banner>}
      <div className="grid gap-3 sm:grid-cols-3">
        <StatCard icon={CalendarCheck} label={t('supervisor.exceptions')} value={cards.attendance_exceptions} tone="amber" />
        <StatCard icon={TriangleAlert} label={t('supervisor.incidents')} value={cards.open_incidents} />
        <StatCard icon={Bell} label={t('supervisor.emergencies')} value={cards.open_sos} tone="red" />
      </div>
      <div className="kv-guide mt-6">
        {[
          ['Attendance', 'Open exceptions when a punch is missing, late, or early.'],
          ['Incidents', 'Review what guards reported on your sites.'],
          ['Emergencies', 'Acknowledge SOS first, then resolve when the site is safe.'],
        ].map(([title, body]) => (
          <div key={title} className="kv-muted-panel">
            <p className="text-sm font-bold text-navy">{title}</p>
            <p className="mt-2 text-sm leading-6 text-muted">{body}</p>
          </div>
        ))}
      </div>
    </Shell>
  );
}

function Attendance() {
  const t = useT();
  const queryClient = useQueryClient();
  const query = useQuery({ queryKey: ['attendance'], queryFn: async () => (await api.get('/api/v1/attendance?status=exception')).data });
  const rows = listRows(query.data);

  async function verify(id) {
    await ensureCsrf(api);
    await api.post(`/api/v1/attendance/${id}/verify`);
    queryClient.invalidateQueries({ queryKey: ['attendance'] });
  }

  return (
    <Shell>
      <PageHeading title={t('supervisor.exceptions')} body="Verify only after you confirm the person was on post. Verification is stored on the attendance day." />
      {query.isLoading && <Spinner />}
      {rows.length === 0 && !query.isLoading && <EmptyState title={t('supervisor.noExceptions')} />}
      <div className="space-y-3">
        {rows.map((row) => (
          <article key={row.id} className="kv-card p-4">
            <div className="flex items-center justify-between">
              <StatusPill value={row.exception_type || row.status} />
              <span className="text-sm text-muted">{row.work_date}</span>
            </div>
            <button type="button" className="kv-btn kv-btn-primary mt-3" onClick={() => verify(row.id)}>{t('supervisor.verify')}</button>
          </article>
        ))}
      </div>
    </Shell>
  );
}

function Incidents() {
  const t = useT();
  const query = useQuery({ queryKey: ['incidents'], queryFn: async () => (await api.get('/api/v1/incidents')).data });
  const rows = listRows(query.data);
  return (
    <Shell>
      <PageHeading title={t('supervisor.incidents')} body="Field reports for your sites. Status changes happen from operations when your role allows it." />
      {query.isLoading && <Spinner />}
      {rows.length === 0 && !query.isLoading && <EmptyState title={t('common.empty')} />}
      {rows.map((row) => (
        <article key={row.id} className="kv-card mt-3 p-4">
          <div className="flex justify-between gap-3"><strong>{row.title}</strong><StatusPill value={row.status} /></div>
          <p className="mt-2 text-sm text-muted">{row.description}</p>
        </article>
      ))}
    </Shell>
  );
}

function Alerts() {
  const t = useT();
  const queryClient = useQueryClient();
  const query = useQuery({ queryKey: ['sos'], queryFn: async () => (await api.get('/api/v1/emergency/sos')).data });
  const rows = listRows(query.data);

  async function transition(id, type) {
    await ensureCsrf(api);
    await api.post(`/api/v1/emergency/sos/${id}/transition`, { type });
    queryClient.invalidateQueries({ queryKey: ['sos'] });
  }

  return (
    <Shell>
      <PageHeading title={t('supervisor.emergencies')} body="Acknowledge when you have seen the alert. Resolve only when the emergency is closed on site." />
      {query.isLoading && <Spinner />}
      {rows.length === 0 && !query.isLoading && <EmptyState title={t('common.empty')} />}
      {rows.map((row) => (
        <article key={row.id} className="kv-card mt-3 p-4">
          <div className="flex justify-between gap-3"><span>{row.message || 'SOS'}</span><StatusPill value={row.status} /></div>
          <p className="mt-1 text-sm text-muted">Notification: {row.notification_status || '—'}</p>
          <div className="mt-3 flex flex-wrap gap-2">
            <button type="button" className="kv-btn kv-btn-orange" onClick={() => transition(row.id, 'acknowledged')}>{t('supervisor.acknowledge')}</button>
            <button type="button" className="kv-btn kv-btn-navy" onClick={() => transition(row.id, 'resolved')}>{t('supervisor.resolve')}</button>
          </div>
        </article>
      ))}
    </Shell>
  );
}

export default function App() {
  return (
    <AuthGate api={api} portal="supervisor" title="Supervisor desk" hint="Sign in for the sites you supervise.">
      <Routes>
        <Route path="/" element={<Today />} />
        <Route path="/attendance" element={<Attendance />} />
        <Route path="/incidents" element={<Incidents />} />
        <Route path="/alerts" element={<Alerts />} />
      </Routes>
    </AuthGate>
  );
}
