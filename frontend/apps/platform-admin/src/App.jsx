import { useState } from 'react';
import { NavLink, Route, Routes } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Table, TableBody, TableCell, TableHead, TableRow } from '@mui/material';
import { Activity, Building2, LayoutDashboard, Sparkles } from 'lucide-react';
import {
  AuthGate, Banner, EmptyState, Field, Logo, PageHeading, SectionCard, Spinner,
  StatCard, StatusPill, TokenReveal,
  createApi, ensureCsrf, errorMessage, listRows, signOut,
} from '@kavach/app-kit';

const api = createApi();

function Frame({ children }) {
  const queryClient = useQueryClient();
  return (
    <div className="min-h-screen bg-[#081736] text-white">
      <div className="grid min-h-screen md:grid-cols-[260px_1fr]">
        <aside className="flex flex-col gap-2 border-r border-white/10 px-4 py-6">
          <Logo light />
          <p className="mt-6 px-3 text-xs font-bold tracking-[0.2em] text-orange">PLATFORM</p>
          <nav className="mt-2 flex flex-1 gap-2 md:flex-col">
            {[
              ['/', 'Overview', LayoutDashboard],
              ['/tenants', 'Organizations', Building2],
              ['/health', 'Health', Activity],
            ].map(([to, label, Icon]) => (
              <NavLink
                key={to}
                to={to}
                end={to === '/'}
                className={({ isActive }) => `flex items-center gap-2 rounded-2xl px-3 py-3 text-sm font-semibold no-underline ${isActive ? 'bg-white text-navy' : 'text-white/70 hover:bg-white/5'}`}
              >
                <Icon size={18} />
                {label}
              </NavLink>
            ))}
          </nav>
          <button
            type="button"
            className="mt-auto rounded-2xl px-3 py-3 text-left text-sm font-semibold text-white/70 hover:bg-white/5"
            onClick={async () => {
              await signOut(api, queryClient);
              window.location.reload();
            }}
          >
            Sign out
          </button>
        </aside>
        <main className="bg-cloud px-6 py-8 text-navy">{children}</main>
      </div>
    </div>
  );
}

function Overview() {
  const query = useQuery({ queryKey: ['platform-dash'], queryFn: async () => (await api.get('/api/v1/platform/dashboard')).data.data });
  const tenants = query.data?.tenants || {};
  return (
    <Frame>
      <PageHeading
        kicker="Platform"
        title="Control plane"
        body="Provision organizations here. Each owner accepts an invitation, then finishes setup or you activate the draft for them."
      />
      {query.isLoading && <Spinner />}
      {query.isError && <Banner>{errorMessage(query.error) || 'Dashboard could not be loaded.'}</Banner>}
      <div className="grid gap-4 md:grid-cols-3">
        <StatCard icon={Building2} label="Draft" value={tenants.draft} />
        <StatCard icon={Activity} label="Active" value={tenants.active} tone="teal" />
        <StatCard icon={Building2} label="Suspended" value={tenants.suspended} tone="red" />
      </div>
      <div className="mt-6 grid gap-4 lg:grid-cols-3">
        {[
          ['Create organization', 'Send an owner invitation with a one-time token.'],
          ['Owner accepts', 'They open /accept-invitation and set a password.'],
          ['Activate', 'Activating a draft also starts a trial subscription so the workspace can open.'],
        ].map(([title, body], index) => (
          <div key={title} className="kv-muted-panel">
            <span className="kv-chip">Step {index + 1}</span>
            <p className="mt-3 text-sm font-bold text-navy">{title}</p>
            <p className="mt-2 text-sm leading-6 text-muted">{body}</p>
          </div>
        ))}
      </div>
    </Frame>
  );
}

function Tenants() {
  const queryClient = useQueryClient();
  const query = useQuery({ queryKey: ['tenants'], queryFn: async () => (await api.get('/api/v1/platform/tenants')).data });
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [token, setToken] = useState('');
  const [busy, setBusy] = useState(false);
  const rows = listRows(query.data);

  async function create(event) {
    event.preventDefault();
    setError('');
    setMessage('');
    setToken('');
    setBusy(true);
    try {
      await ensureCsrf(api);
      const response = await api.post('/api/v1/platform/tenants', Object.fromEntries(new FormData(event.currentTarget)));
      setToken(response.data.data.invitation_token || '');
      setMessage(`Draft organization “${response.data.data.name}” created. Share the invitation with the owner.`);
      event.currentTarget.reset();
      queryClient.invalidateQueries({ queryKey: ['tenants'] });
      queryClient.invalidateQueries({ queryKey: ['platform-dash'] });
    } catch (err) {
      setError(errorMessage(err) || 'The organization was not created.');
    } finally {
      setBusy(false);
    }
  }

  async function setStatus(id, status) {
    setBusy(true);
    setError('');
    try {
      await ensureCsrf(api);
      await api.post(`/api/v1/platform/tenants/${id}/status`, { status });
      setMessage(status === 'active'
        ? 'Organization activated with a trial subscription and onboarding marked complete.'
        : 'Organization suspended.');
      queryClient.invalidateQueries({ queryKey: ['tenants'] });
      queryClient.invalidateQueries({ queryKey: ['platform-dash'] });
    } catch (err) {
      setError(errorMessage(err) || 'Status was not changed.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <Frame>
      <PageHeading
        title="Organizations"
        body="Platform operators create the organization shell. The owner invitation token is shown once so you can complete local setup without email."
      />
      <SectionCard title="Provision organization" body="Creates a draft organization and invites the owner. Copy the token below if email is not available on this machine." icon={Sparkles}>
        <form className="grid gap-3 md:grid-cols-2" onSubmit={create}>
          <Field label="Organization name"><input name="name" required placeholder="Acme Security" /></Field>
          <Field label="Business model">
            <select name="business_type">
              <option value="agency">Security agency</option>
              <option value="inhouse">In-house organization</option>
            </select>
          </Field>
          <Field label="Owner name"><input name="owner_name" required placeholder="Jane Doe" /></Field>
          <Field label="Owner email"><input name="owner_email" type="email" required placeholder="jane@acme.com" /></Field>
          {error && <div className="md:col-span-2"><Banner>{error}</Banner></div>}
          {message && <div className="md:col-span-2"><Banner tone="success">{message}</Banner></div>}
          {token && <div className="md:col-span-2"><TokenReveal token={token} label="Owner invitation token" /></div>}
          <button className="kv-btn kv-btn-primary md:col-span-2" type="submit" disabled={busy}>
            {busy ? 'Creating' : 'Create organization & invite owner'}
          </button>
        </form>
      </SectionCard>

      <div className="mt-6">
        <SectionCard title="All organizations" body={`${rows.length} total`} icon={Building2} tone="teal">
          {query.isLoading && <Spinner />}
          {rows.length === 0 && !query.isLoading ? <EmptyState title="No organizations" body="Provision the first organization above." /> : null}
          {rows.length > 0 && (
            <div className="kv-table-wrap -mx-5 -mb-5">
              <Table>
                <TableHead>
                  <TableRow>
                    <TableCell>Name</TableCell>
                    <TableCell>Type</TableCell>
                    <TableCell>Status</TableCell>
                    <TableCell>Plan</TableCell>
                    <TableCell>Actions</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {rows.map((row) => (
                    <TableRow key={row.id}>
                      <TableCell>
                        <div className="font-semibold text-navy">{row.name}</div>
                        <div className="text-xs text-muted">{row.slug}</div>
                      </TableCell>
                      <TableCell className="capitalize">{row.business_type}</TableCell>
                      <TableCell><StatusPill value={row.status} /></TableCell>
                      <TableCell className="capitalize">{row.plan || '—'}{row.subscription_status ? ` · ${row.subscription_status}` : ''}</TableCell>
                      <TableCell>
                        {row.status === 'active' ? (
                          <button type="button" className="text-sm font-semibold text-danger" disabled={busy} onClick={() => setStatus(row.id, 'suspended')}>Suspend</button>
                        ) : (
                          <button type="button" className="text-sm font-semibold text-electric" disabled={busy} onClick={() => setStatus(row.id, 'active')}>Activate</button>
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </SectionCard>
      </div>
    </Frame>
  );
}

function Health() {
  const query = useQuery({ queryKey: ['health'], queryFn: async () => (await api.get('/api/v1/platform/health')).data.data });
  return (
    <Frame>
      <PageHeading title="Health" body="Infrastructure signals for operators." />
      <SectionCard title="Service status" icon={Activity}>
        {query.isLoading && <Spinner />}
        <pre className="overflow-auto rounded-2xl bg-[#0B1F44] p-4 text-sm text-white/90">{JSON.stringify(query.data || {}, null, 2)}</pre>
      </SectionCard>
    </Frame>
  );
}

export default function App() {
  return (
    <AuthGate api={api} portal="platform" title="Platform" hint="KAVACH operators only. Organization records stay closed without a support grant.">
      <Routes>
        <Route path="/" element={<Overview />} />
        <Route path="/tenants" element={<Tenants />} />
        <Route path="/health" element={<Health />} />
      </Routes>
    </AuthGate>
  );
}
