import { useState } from 'react';
import { CalendarCheck, MapPin, MessageSquare, TriangleAlert } from 'lucide-react';
import { NavLink, Route, Routes } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import {
  AuthGate, Banner, EmptyState, Field, Logo, PageHeading, Spinner, StatusPill,
  createApi, ensureCsrf, errorMessage, listRows, signOut, useSession,
} from '@kavach/app-kit';

const api = createApi();

function Bar({ children }) {
  const { profile } = useSession(api);
  const queryClient = useQueryClient();
  return (
    <div className="min-h-screen">
      <header className="kv-top">
        <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-6 py-3">
          <div>
            <Logo />
            <p className="mt-1 text-xs text-muted">{profile?.tenant?.name || 'Client portal'} · read-only view of your sites</p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <nav className="flex flex-wrap gap-2 text-sm">
              {[
                ['/', 'Sites', MapPin],
                ['/attendance', 'Attendance', CalendarCheck],
                ['/incidents', 'Incidents', TriangleAlert],
                ['/requests', 'Requests', MessageSquare],
              ].map(([to, label, Icon]) => (
                <NavLink key={to} to={to} end={to === '/'} className={({ isActive }) => `kv-nav-pill ${isActive ? 'active' : ''}`}>
                  <Icon size={16} />
                  {label}
                </NavLink>
              ))}
            </nav>
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
        </div>
      </header>
      <main className="mx-auto max-w-5xl px-6 py-8">{children}</main>
    </div>
  );
}

function Sites() {
  const query = useQuery({ queryKey: ['sites'], queryFn: async () => (await api.get('/api/v1/sites')).data });
  const rows = listRows(query.data);
  return (
    <Bar>
      <PageHeading kicker="Client" title="Your sites" body="Only the sites this login is allowed to see. You cannot edit agency records here." />
      {query.isLoading && <Spinner />}
      {query.isError && <Banner>{errorMessage(query.error) || 'Sites could not be loaded.'}</Banner>}
      <div className="grid gap-3">
        {rows.length === 0 && !query.isLoading && <EmptyState title="No sites are assigned to this login." body="Ask the agency to grant site access on your client invitation." />}
        {rows.map((site) => (
          <article key={site.id} className="kv-card kv-card-hover flex gap-4 p-4">
            <div className="kv-icon teal"><MapPin size={18} /></div>
            <div className="flex-1">
              <div className="flex justify-between gap-3"><strong>{site.name}</strong><StatusPill value={site.status} /></div>
              <p className="mt-1 text-sm text-muted">{[site.address, site.city].filter(Boolean).join(', ') || 'No address on file'}</p>
            </div>
          </article>
        ))}
      </div>
    </Bar>
  );
}

function Attendance() {
  const query = useQuery({ queryKey: ['attendance'], queryFn: async () => (await api.get('/api/v1/attendance')).data });
  const rows = listRows(query.data);
  return (
    <Bar>
      <PageHeading title="Attendance" body="Attendance for your granted sites only. Status comes from the agency’s server clock." />
      {query.isLoading && <Spinner />}
      {rows.length === 0 && !query.isLoading && <EmptyState title="No attendance is visible for your sites." />}
      {rows.map((row) => (
        <article key={row.id} className="kv-card mt-3 flex justify-between p-4">
          <span>{row.work_date}</span>
          <StatusPill value={row.status} />
        </article>
      ))}
    </Bar>
  );
}

function Incidents() {
  const query = useQuery({ queryKey: ['incidents'], queryFn: async () => (await api.get('/api/v1/incidents')).data });
  const rows = listRows(query.data);
  return (
    <Bar>
      <PageHeading title="Incidents" body="Incidents reported on your sites. Resolution stays with the agency and supervisors." />
      {query.isLoading && <Spinner />}
      {rows.length === 0 && !query.isLoading && <EmptyState title="No incidents" />}
      {rows.map((row) => (
        <article key={row.id} className="kv-card mt-3 p-4">
          <div className="flex justify-between gap-3"><strong>{row.title}</strong><StatusPill value={row.status} /></div>
          <p className="mt-2 text-sm text-muted">{row.description}</p>
        </article>
      ))}
    </Bar>
  );
}

function Requests() {
  const queryClient = useQueryClient();
  const query = useQuery({ queryKey: ['requests'], queryFn: async () => (await api.get('/api/v1/service-requests')).data });
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const rows = listRows(query.data);

  async function submit(event) {
    event.preventDefault();
    setError('');
    setMessage('');
    try {
      await ensureCsrf(api);
      await api.post('/api/v1/service-requests', Object.fromEntries(new FormData(event.currentTarget)));
      event.currentTarget.reset();
      setMessage('Request sent to the agency.');
      queryClient.invalidateQueries({ queryKey: ['requests'] });
    } catch (err) {
      setError(errorMessage(err) || 'The request was not sent.');
    }
  }

  return (
    <Bar>
      <PageHeading title="Service requests" body="Ask the agency for coverage changes, reports, or site support. This does not create a work order by itself." />
      <form className="kv-card space-y-3 p-5" onSubmit={submit}>
        <Field label="Subject"><input name="subject" required placeholder="Extra guard on Saturday" /></Field>
        <Field label="Details"><textarea name="body" required placeholder="What do you need?" rows={4} /></Field>
        {error && <Banner>{error}</Banner>}
        {message && <Banner tone="success">{message}</Banner>}
        <button className="kv-btn kv-btn-primary" type="submit">Send request</button>
      </form>
      {rows.map((row) => (
        <article key={row.id} className="kv-card mt-3 flex justify-between p-4">
          <strong>{row.subject}</strong>
          <StatusPill value={row.status} />
        </article>
      ))}
    </Bar>
  );
}

export default function App() {
  return (
    <AuthGate api={api} portal="client" title="Client portal" hint="You will see only the sites this organization has granted.">
      <Routes>
        <Route path="/" element={<Sites />} />
        <Route path="/attendance" element={<Attendance />} />
        <Route path="/incidents" element={<Incidents />} />
        <Route path="/requests" element={<Requests />} />
      </Routes>
    </AuthGate>
  );
}
