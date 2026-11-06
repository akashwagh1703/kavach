import { useMemo, useState } from 'react';
import { Link, NavLink, Route, Routes, useNavigate } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Table, TableBody, TableCell, TableHead, TableRow } from '@mui/material';
import {
  Building2, CalendarCheck, FileBarChart, Handshake, LogOut, MapPin, Shield,
  Settings as SettingsIcon, Siren, TriangleAlert, UserPlus, Users, Wallet,
} from 'lucide-react';
import {
  ActionFlash, AuthGate, EmptyState, Field, Logo, PageHeading, SectionCard, Spinner,
  StatCard, StatusPill, TokenReveal,
  createApi, ensureCsrf, errorMessage, formatDate, formatDateTime, formatMoney, formatPeriod,
  listRows, useActionFlash, useSession,
} from '@kavach/app-kit';

const api = createApi();

const nav = [
  ['/', 'Today', CalendarCheck],
  ['/branches', 'Branches', Building2],
  ['/sites', 'Sites', MapPin],
  ['/employees', 'People', Users],
  ['/clients', 'Clients', Handshake],
  ['/attendance', 'Attendance', CalendarCheck],
  ['/payslips', 'Payslips', Wallet],
  ['/incidents', 'Incidents', TriangleAlert],
  ['/alerts', 'SOS', Siren],
  ['/reports', 'Reports', FileBarChart],
  ['/settings', 'Settings', SettingsIcon],
];

function useList(key, path) {
  return useQuery({
    queryKey: [key],
    queryFn: async () => (await api.get(path)).data,
  });
}

function loginLabel(row) {
  if (row.user_id || row.invite_status === 'active') return { value: 'Active login', tone: 'active' };
  if (row.invite_status === 'pending') return { value: 'Invite pending', tone: 'pending' };
  return { value: 'Not invited', tone: 'draft' };
}

function Shell({ children }) {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { profile } = useSession(api);

  async function logout() {
    await ensureCsrf(api);
    await api.post('/api/v1/auth/logout');
    queryClient.clear();
    navigate(0);
  }

  return (
    <div className="grid min-h-screen lg:grid-cols-[280px_1fr]">
      <aside className="kv-side flex flex-col px-4 py-6 text-white">
        <div className="px-2"><Logo light /></div>
        <div className="mt-6 rounded-2xl bg-white/8 px-3 py-3 ring-1 ring-white/10">
          <p className="text-[11px] font-bold uppercase tracking-[0.16em] text-white/45">Organization</p>
          <p className="mt-1 truncate text-sm font-semibold">{profile?.tenant?.name || '—'}</p>
          <p className="mt-1 text-xs text-white/55">{profile?.subscription?.plan || 'plan'} · {profile?.subscription?.status || '—'}</p>
        </div>
        <nav className="mt-4 flex flex-1 flex-col gap-1">
          {nav
            .filter(([to]) => to !== '/clients' || profile?.tenant?.client_workflow_enabled)
            .map(([to, label, Icon]) => (
              <NavLink key={to} to={to} end={to === '/'} className={({ isActive }) => `kv-nav ${isActive ? 'active' : ''}`}>
                <Icon size={18} strokeWidth={1.75} />
                {label}
              </NavLink>
            ))}
        </nav>
        <button type="button" onClick={logout} className="kv-nav">
          <LogOut size={18} />
          Sign out
        </button>
      </aside>
      <main className="px-5 py-7 lg:px-10">{children}</main>
    </div>
  );
}

function Dashboard() {
  const query = useList('dashboard', '/api/v1/dashboard');
  const people = useList('employees-count', '/api/v1/employees');
  const sites = useList('sites-count', '/api/v1/sites');
  const cards = query.data?.data || {};
  const workforce = listRows(people.data);
  const siteRows = listRows(sites.data);
  const guards = workforce.filter((row) => row.kind === 'guard').length;
  const supervisors = workforce.filter((row) => row.kind === 'supervisor').length;
  const linked = workforce.filter((row) => row.user_id || row.invite_status === 'active').length;
  const pending = workforce.filter((row) => row.invite_status === 'pending').length;

  const steps = [
    {
      done: siteRows.length > 0,
      title: '1. Add sites',
      body: 'Create the places guards work. Branches are optional grouping.',
      to: '/sites',
      cta: 'Open Sites',
    },
    {
      done: guards + supervisors > 0,
      title: '2. Register people',
      body: 'Save each guard or supervisor record first. This does not create a login.',
      to: '/employees',
      cta: 'Open People',
    },
    {
      done: linked > 0 || pending > 0,
      title: '3. Invite to portal',
      body: 'Send a one-time token. They accept on the public site, then open Guard or Supervisor.',
      to: '/employees',
      cta: 'Invite people',
    },
    {
      done: supervisors > 0 && siteRows.length > 0,
      title: '4. Assign supervisor sites',
      body: 'Supervisors only see attendance, incidents, and SOS for assigned sites.',
      to: '/employees',
      cta: 'Assign coverage',
    },
  ];

  return (
    <Shell>
      <PageHeading
        kicker="Operations"
        title="Today"
        body="After onboarding, run your organization here: sites → people → invite → coverage. Attendance and payslips appear once the field team starts punching and finance posts drafts."
      />
      {query.isLoading && <Spinner />}
      {query.isError && <ActionFlash flash={{ tone: 'error', text: errorMessage(query.error) || 'The dashboard could not be loaded.' }} />}
      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <StatCard icon={MapPin} label="Sites" value={cards.sites ?? siteRows.length} />
        <StatCard icon={Users} label="Guards" value={people.isLoading ? '—' : guards} />
        <StatCard icon={Shield} label="Supervisors" value={people.isLoading ? '—' : supervisors} tone="orange" />
        <StatCard icon={UserPlus} label="Active logins" value={people.isLoading ? '—' : linked} tone="teal" />
        <StatCard icon={TriangleAlert} label="Open incidents" value={cards.open_incidents} tone="amber" />
        <StatCard icon={Siren} label="Open SOS" value={cards.open_sos} tone="red" />
        <StatCard icon={CalendarCheck} label="Attendance exceptions" value={cards.attendance_exceptions} tone="teal" />
        <StatCard icon={UserPlus} label="Invites pending" value={people.isLoading ? '—' : pending} tone="amber" />
      </div>

      <div className="mt-8">
        <SectionCard title="How this organization runs" body="Follow these steps in order. Each step unlocks the next screen’s purpose." icon={Building2}>
          <div className="grid gap-3 lg:grid-cols-2">
            {steps.map((step) => (
              <div key={step.title} className={`rounded-2xl border p-4 ${step.done ? 'border-teal-200 bg-mint/40' : 'border-[var(--kv-line)] bg-white'}`}>
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="text-sm font-bold text-navy">{step.title}</p>
                    <p className="mt-2 text-sm leading-6 text-muted">{step.body}</p>
                  </div>
                  <StatusPill value={step.done ? 'complete' : 'open'} />
                </div>
                <Link to={step.to} className="kv-btn kv-btn-ghost mt-4 !px-3 !py-2 !text-xs">{step.cta}</Link>
              </div>
            ))}
          </div>
        </SectionCard>
      </div>
    </Shell>
  );
}

function Collection({ title, singular, path, queryKey, fields, columns, body, icon: Icon = Building2 }) {
  const queryClient = useQueryClient();
  const query = useList(queryKey, path);
  const flash = useActionFlash();
  const rows = listRows(query.data);
  const itemLabel = singular || title.replace(/s$/i, '');
  const mutation = useMutation({
    mutationFn: async (payload) => {
      await ensureCsrf(api);
      return api.post(path, payload);
    },
  });

  async function submit(event) {
    event.preventDefault();
    const form = event.currentTarget;
    flash.clear();
    const bodyData = Object.fromEntries(new FormData(form));
    try {
      const response = await mutation.mutateAsync(bodyData);
      form.reset();
      await queryClient.invalidateQueries({ queryKey: [queryKey] });
      flash.success(response.data?.message || `${itemLabel} added successfully.`);
    } catch (err) {
      flash.error(errorMessage(err) || `${itemLabel} was not added. Fix the fields and try again.`);
    }
  }

  return (
    <Shell>
      <PageHeading title={title} body={body} />
      <ActionFlash flash={flash.flash} onDismiss={flash.clear} className="mb-4" />
      <SectionCard title={`Add ${itemLabel.toLowerCase()}`} icon={Icon}>
        <form className="grid gap-3 md:grid-cols-2" onSubmit={submit}>
          {fields.map((field) => (
            field.type === 'select' ? (
              <Field key={field.name} label={field.label}>
                <select name={field.name} required={field.required} defaultValue={field.options[0]?.value}>
                  {field.options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                </select>
              </Field>
            ) : (
              <Field key={field.name} label={field.label} hint={field.hint}>
                <input name={field.name} required={field.required} placeholder={field.placeholder || field.label} type={field.inputType || 'text'} />
              </Field>
            )
          ))}
          <button className="kv-btn kv-btn-primary md:col-span-2" type="submit" disabled={mutation.isPending}>
            {mutation.isPending ? 'Saving…' : `Add ${itemLabel.toLowerCase()}`}
          </button>
        </form>
      </SectionCard>
      <div className="mt-6">
        <SectionCard title={`${title} directory`} body={`${rows.length} record${rows.length === 1 ? '' : 's'}`} tone="teal">
          {query.isLoading && <Spinner />}
          {query.isError && <ActionFlash flash={{ tone: 'error', text: errorMessage(query.error) || 'The list could not be loaded.' }} />}
          {!query.isLoading && !query.isError && rows.length === 0 && (
            <EmptyState title={`No ${title.toLowerCase()} yet`} body={`Add the first ${itemLabel.toLowerCase()} using the form above.`} />
          )}
          {rows.length > 0 && (
            <div className="kv-table-wrap -mx-5 -mb-5">
              <Table>
                <TableHead>
                  <TableRow>{columns.map((column) => <TableCell key={column}>{column.replaceAll('_', ' ')}</TableCell>)}</TableRow>
                </TableHead>
                <TableBody>
                  {rows.map((row) => (
                    <TableRow key={row.id}>
                      {columns.map((column) => (
                        <TableCell key={column}>
                          {column === 'status' || column === 'kind' ? <StatusPill value={row[column]} /> : String(row[column] ?? '—')}
                        </TableCell>
                      ))}
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </SectionCard>
      </div>
    </Shell>
  );
}

function People() {
  const queryClient = useQueryClient();
  const employees = useList('employees', '/api/v1/employees');
  const sites = useList('sites', '/api/v1/sites');
  const rows = listRows(employees.data);
  const siteRows = listRows(sites.data);
  const flash = useActionFlash(7000);
  const [inviteToken, setInviteToken] = useState('');
  const [inviteFor, setInviteFor] = useState(null);
  const [siteFor, setSiteFor] = useState(null);
  const [selectedSites, setSelectedSites] = useState([]);
  const [busyKey, setBusyKey] = useState('');

  const counts = useMemo(() => ({
    guards: rows.filter((row) => row.kind === 'guard').length,
    supervisors: rows.filter((row) => row.kind === 'supervisor').length,
    linked: rows.filter((row) => row.user_id || row.invite_status === 'active').length,
    pending: rows.filter((row) => row.invite_status === 'pending').length,
  }), [rows]);

  async function createEmployee(event) {
    event.preventDefault();
    const form = event.currentTarget;
    flash.clear();
    setInviteToken('');
    const body = Object.fromEntries(new FormData(form));
    setBusyKey('create');
    try {
      await ensureCsrf(api);
      const response = await api.post('/api/v1/employees', body);
      form.reset();
      await queryClient.invalidateQueries({ queryKey: ['employees'] });
      await queryClient.invalidateQueries({ queryKey: ['employees-count'] });
      flash.success(response.data?.message || `${body.name} saved. Invite them from the directory below.`);
    } catch (err) {
      flash.error(errorMessage(err) || 'Person was not saved. Check employee code uniqueness and required fields.');
    } finally {
      setBusyKey('');
    }
  }

  async function inviteEmployee(row) {
    flash.clear();
    setInviteToken('');
    setInviteFor(row.id);
    setBusyKey(`invite-${row.id}`);
    try {
      await ensureCsrf(api);
      const portal = row.kind === 'supervisor' ? 'supervisor' : row.kind === 'guard' ? 'guard' : 'tenant';
      const role_code = portal === 'tenant' ? 'operations_manager' : portal;
      const response = await api.post('/api/v1/invitations', {
        email: row.email,
        name: row.name,
        portal,
        role_code,
        employee_id: row.id,
      });
      setInviteToken(response.data.data.invitation_token);
      await queryClient.invalidateQueries({ queryKey: ['employees'] });
      flash.success(response.data?.message || `Invitation ready for ${row.name}. Copy the token below.`);
    } catch (err) {
      flash.error(errorMessage(err) || 'Invitation was not created. The person needs a valid work email.');
    } finally {
      setBusyKey('');
    }
  }

  async function inviteOffice(event) {
    event.preventDefault();
    const form = event.currentTarget;
    flash.clear();
    setInviteToken('');
    const body = Object.fromEntries(new FormData(form));
    setBusyKey('office');
    try {
      await ensureCsrf(api);
      const response = await api.post('/api/v1/invitations', {
        email: body.email,
        name: body.name,
        portal: 'tenant',
        role_code: body.role_code,
      });
      setInviteToken(response.data.data.invitation_token);
      setInviteFor('office');
      form.reset();
      flash.success(response.data?.message || 'Office invitation ready. Share the token below.');
    } catch (err) {
      flash.error(errorMessage(err) || 'Office invitation was not created.');
    } finally {
      setBusyKey('');
    }
  }

  async function saveSites(event) {
    event.preventDefault();
    if (!siteFor) return;
    flash.clear();
    setBusyKey('sites');
    try {
      await ensureCsrf(api);
      const response = await api.post(`/api/v1/employees/${siteFor}/sites`, { site_ids: selectedSites });
      setSiteFor(null);
      setSelectedSites([]);
      flash.success(response.data?.message || 'Supervisor site coverage saved.');
    } catch (err) {
      flash.error(errorMessage(err) || 'Sites were not assigned.');
    } finally {
      setBusyKey('');
    }
  }

  return (
    <Shell>
      <PageHeading
        kicker="Workforce"
        title="People"
        body="Register → invite → assign. Creating a person only stores the HR record. Inviting creates a login path. Supervisors also need site coverage."
      />
      <ActionFlash flash={flash.flash} onDismiss={flash.clear} className="mb-4" />

      <div className="mb-6 grid gap-3 md:grid-cols-3">
        {[
          ['Register', 'Add guard or supervisor with a unique employee code and work email.'],
          ['Invite', 'Create a one-time token. They accept it on the public site, then open their portal.'],
          ['Cover', 'Assign supervisors to sites so exceptions and SOS reach the right desk.'],
        ].map(([title, body], index) => (
          <div key={title} className="kv-muted-panel">
            <span className="kv-chip">Step {index + 1}</span>
            <p className="mt-3 text-sm font-bold text-navy">{title}</p>
            <p className="mt-2 text-sm leading-6 text-muted">{body}</p>
          </div>
        ))}
      </div>

      <div className="mb-6 grid gap-4 md:grid-cols-4">
        <StatCard icon={Shield} label="Guards" value={counts.guards} />
        <StatCard icon={Users} label="Supervisors" value={counts.supervisors} tone="orange" />
        <StatCard icon={UserPlus} label="Active logins" value={counts.linked} tone="teal" />
        <StatCard icon={UserPlus} label="Invites pending" value={counts.pending} tone="amber" />
      </div>

      {inviteToken && (
        <div className="mb-4">
          <TokenReveal token={inviteToken} label={inviteFor === 'office' ? 'Office invitation token' : 'Portal invitation token'} />
        </div>
      )}

      <div className="grid gap-6 xl:grid-cols-2">
        <SectionCard title="1. Register guard or supervisor" body="Saves the employee only. No password is created here." icon={UserPlus}>
          <form className="grid gap-3 sm:grid-cols-2" onSubmit={createEmployee}>
            <Field label="Full name" className="sm:col-span-2"><input name="name" required placeholder="Ravi Patil" /></Field>
            <Field label="Employee code" hint="Must be unique in this organization."><input name="employee_code" required placeholder="G-001" /></Field>
            <Field label="Role">
              <select name="kind" required defaultValue="guard">
                <option value="guard">Guard → Guard app</option>
                <option value="supervisor">Supervisor → Supervisor desk</option>
                <option value="staff">Staff (record only)</option>
              </select>
            </Field>
            <Field label="Work email" hint="Required to invite them to a portal."><input name="email" type="email" required placeholder="ravi@agency.com" /></Field>
            <Field label="Phone"><input name="phone" placeholder="+91…" /></Field>
            <Field label="Designation"><input name="designation" placeholder="Security guard" /></Field>
            <Field label="Date of joining"><input name="date_of_joining" type="date" /></Field>
            <button className="kv-btn kv-btn-primary sm:col-span-2" type="submit" disabled={!!busyKey}>
              {busyKey === 'create' ? 'Saving…' : 'Save person'}
            </button>
          </form>
        </SectionCard>

        <SectionCard title="Office staff invitation" body="Owners, admins, and managers use this organization workspace — not the Guard app." icon={Building2} tone="orange">
          <form className="grid gap-3" onSubmit={inviteOffice}>
            <Field label="Name"><input name="name" required placeholder="Ops manager" /></Field>
            <Field label="Email"><input name="email" type="email" required placeholder="ops@agency.com" /></Field>
            <Field label="Role">
              <select name="role_code" defaultValue="operations_manager">
                <option value="tenant_admin">Tenant admin</option>
                <option value="operations_manager">Operations manager</option>
                <option value="hr_manager">HR manager</option>
                <option value="site_manager">Site manager</option>
                <option value="finance_manager">Finance manager</option>
                <option value="auditor">Auditor</option>
              </select>
            </Field>
            <button className="kv-btn kv-btn-navy" type="submit" disabled={!!busyKey}>
              {busyKey === 'office' ? 'Creating…' : 'Create office invitation'}
            </button>
          </form>
        </SectionCard>
      </div>

      {siteFor && (
        <div className="mt-6">
          <SectionCard title="Assign supervisor sites" body="Pick every site this supervisor covers. Empty coverage means they see no field exceptions." icon={MapPin} tone="amber">
            <form onSubmit={saveSites} className="space-y-3">
              <div className="grid gap-2 sm:grid-cols-2">
                {siteRows.length === 0 && <p className="text-sm text-muted">Create a site first under Sites.</p>}
                {siteRows.map((site) => (
                  <label key={site.id} className="flex items-center gap-2 rounded-xl border border-[var(--kv-line)] px-3 py-3 text-sm">
                    <input
                      type="checkbox"
                      checked={selectedSites.includes(site.id)}
                      onChange={(event) => {
                        setSelectedSites((current) => event.target.checked
                          ? [...current, site.id]
                          : current.filter((id) => id !== site.id));
                      }}
                    />
                    <span className="font-semibold text-navy">{site.name}</span>
                    <span className="text-muted">{site.code}</span>
                  </label>
                ))}
              </div>
              <div className="flex flex-wrap gap-2">
                <button className="kv-btn kv-btn-primary" type="submit" disabled={!!busyKey || selectedSites.length === 0}>
                  {busyKey === 'sites' ? 'Saving…' : 'Save coverage'}
                </button>
                <button className="kv-btn kv-btn-ghost" type="button" onClick={() => { setSiteFor(null); setSelectedSites([]); }}>Cancel</button>
              </div>
            </form>
          </SectionCard>
        </div>
      )}

      <div className="mt-6">
        <SectionCard
          title="2–3. Directory — invite & coverage"
          body="Invite pending means a token was issued but not accepted yet. Active login means they finished accept-invitation."
          icon={Users}
          tone="teal"
        >
          {employees.isLoading && <Spinner />}
          {employees.isError && <ActionFlash flash={{ tone: 'error', text: errorMessage(employees.error) || 'People could not be loaded.' }} />}
          {!employees.isLoading && rows.length === 0 && (
            <EmptyState title="No people yet" body="Register the first guard or supervisor above." />
          )}
          {rows.length > 0 && (
            <div className="kv-table-wrap -mx-5 -mb-5">
              <Table>
                <TableHead>
                  <TableRow>
                    <TableCell>Name</TableCell>
                    <TableCell>Code</TableCell>
                    <TableCell>Role</TableCell>
                    <TableCell>Email</TableCell>
                    <TableCell>Joined</TableCell>
                    <TableCell>Portal access</TableCell>
                    <TableCell>Actions</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {rows.map((row) => {
                    const login = loginLabel(row);
                    return (
                      <TableRow key={row.id}>
                        <TableCell>
                          <div className="font-semibold text-navy">{row.name}</div>
                          <div className="text-xs text-muted">{row.designation || '—'}</div>
                        </TableCell>
                        <TableCell>{row.employee_code}</TableCell>
                        <TableCell><StatusPill value={row.kind} /></TableCell>
                        <TableCell>{row.email || '—'}</TableCell>
                        <TableCell>{formatDate(row.date_of_joining)}</TableCell>
                        <TableCell>
                          <StatusPill value={login.value} />
                          {row.invite_status === 'pending' && row.invite_expires_at && (
                            <p className="mt-1 text-[11px] text-muted">Expires {formatDateTime(row.invite_expires_at)}</p>
                          )}
                        </TableCell>
                        <TableCell>
                          <div className="flex flex-wrap gap-2">
                            {(row.kind === 'guard' || row.kind === 'supervisor') && !row.user_id && (
                              <button
                                type="button"
                                className="kv-btn kv-btn-primary !px-3 !py-2 !text-xs"
                                disabled={!!busyKey || !row.email}
                                onClick={() => inviteEmployee(row)}
                              >
                                {busyKey === `invite-${row.id}` ? 'Inviting…' : row.invite_status === 'pending' ? 'Re-invite' : `Invite to ${row.kind}`}
                              </button>
                            )}
                            {row.kind === 'supervisor' && (
                              <button
                                type="button"
                                className="kv-btn kv-btn-ghost !px-3 !py-2 !text-xs"
                                onClick={() => { setSiteFor(row.id); setSelectedSites([]); flash.clear(); }}
                              >
                                Assign sites
                              </button>
                            )}
                          </div>
                        </TableCell>
                      </TableRow>
                    );
                  })}
                </TableBody>
              </Table>
            </div>
          )}
        </SectionCard>
      </div>
    </Shell>
  );
}

function Attendance() {
  const queryClient = useQueryClient();
  const [status, setStatus] = useState('');
  const flash = useActionFlash();
  const query = useQuery({
    queryKey: ['attendance', status],
    queryFn: async () => (await api.get('/api/v1/attendance', { params: status ? { status } : {} })).data,
  });
  const rows = listRows(query.data);
  const [busyId, setBusyId] = useState('');

  async function verify(id) {
    flash.clear();
    setBusyId(id);
    try {
      await ensureCsrf(api);
      await api.post(`/api/v1/attendance/${id}/verify`);
      await queryClient.invalidateQueries({ queryKey: ['attendance'] });
      flash.success('Attendance day verified.');
    } catch (err) {
      flash.error(errorMessage(err) || 'Verification failed.');
    } finally {
      setBusyId('');
    }
  }

  const exceptions = rows.filter((row) => row.status === 'exception').length;
  const complete = rows.filter((row) => row.status === 'complete' || row.status === 'verified').length;

  return (
    <Shell>
      <PageHeading
        title="Attendance"
        body="Punches use the server clock, not the phone clock. Missing or late punches stay as exceptions until a supervisor or manager verifies them."
      />
      <ActionFlash flash={flash.flash} onDismiss={flash.clear} className="mb-4" />
      <div className="mb-6 grid gap-4 md:grid-cols-3">
        <StatCard icon={CalendarCheck} label="Days listed" value={rows.length} />
        <StatCard icon={TriangleAlert} label="Exceptions" value={exceptions} tone="amber" />
        <StatCard icon={Shield} label="Complete / verified" value={complete} tone="teal" />
      </div>
      <SectionCard
        title="Attendance register"
        body="Name, site, work date, and punch times for this organization."
        icon={CalendarCheck}
        action={(
          <Field label="Filter">
            <select value={status} onChange={(event) => setStatus(event.target.value)}>
              <option value="">All statuses</option>
              <option value="open">Open</option>
              <option value="complete">Complete</option>
              <option value="exception">Exception</option>
              <option value="verified">Verified</option>
            </select>
          </Field>
        )}
      >
        {query.isLoading && <Spinner />}
        {query.isError && <ActionFlash flash={{ tone: 'error', text: errorMessage(query.error) || 'Attendance could not be loaded.' }} />}
        {!query.isLoading && rows.length === 0 && (
          <EmptyState title="No attendance days yet" body="Days appear after guards check in from the Guard app." />
        )}
        {rows.length > 0 && (
          <div className="kv-table-wrap -mx-5 -mb-5">
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>Work date</TableCell>
                  <TableCell>Person</TableCell>
                  <TableCell>Site</TableCell>
                  <TableCell>Status</TableCell>
                  <TableCell>Exception</TableCell>
                  <TableCell>Check in</TableCell>
                  <TableCell>Check out</TableCell>
                  <TableCell>Verified</TableCell>
                  <TableCell>Actions</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {rows.map((row) => (
                  <TableRow key={row.id}>
                    <TableCell className="font-semibold text-navy">{formatDate(row.work_date)}</TableCell>
                    <TableCell>
                      <div className="font-medium">{row.employee?.name || '—'}</div>
                      <div className="text-xs text-muted">{row.employee?.employee_code || ''}</div>
                    </TableCell>
                    <TableCell>
                      <div>{row.site?.name || '—'}</div>
                      <div className="text-xs text-muted">{row.site?.code || ''}</div>
                    </TableCell>
                    <TableCell><StatusPill value={row.status} /></TableCell>
                    <TableCell>{row.exception_type ? <StatusPill value={row.exception_type} /> : '—'}</TableCell>
                    <TableCell>{formatDateTime(row.check_in_at)}</TableCell>
                    <TableCell>{formatDateTime(row.check_out_at)}</TableCell>
                    <TableCell>{formatDateTime(row.verified_at)}</TableCell>
                    <TableCell>
                      {row.status === 'exception' && (
                        <button
                          type="button"
                          className="kv-btn kv-btn-primary !px-3 !py-2 !text-xs"
                          disabled={busyId === row.id}
                          onClick={() => verify(row.id)}
                        >
                          {busyId === row.id ? 'Verifying…' : 'Verify'}
                        </button>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        )}
      </SectionCard>
    </Shell>
  );
}

function Payslips() {
  const queryClient = useQueryClient();
  const flash = useActionFlash();
  const employees = useList('employees', '/api/v1/employees');
  const query = useList('payslips', '/api/v1/payslips');
  const rows = listRows(query.data);
  const people = listRows(employees.data).filter((row) => row.kind === 'guard' || row.kind === 'supervisor' || row.kind === 'staff');
  const [busy, setBusy] = useState(false);
  const [busyId, setBusyId] = useState('');

  async function create(event) {
    event.preventDefault();
    const form = event.currentTarget;
    flash.clear();
    const raw = Object.fromEntries(new FormData(form));
    const gross = Number(raw.gross);
    const deductions = Number(raw.deductions);
    const net = Number((gross - deductions).toFixed(2));
    setBusy(true);
    try {
      await ensureCsrf(api);
      const response = await api.post('/api/v1/payslips', {
        employee_id: raw.employee_id,
        period_start: raw.period_start,
        period_end: raw.period_end,
        gross,
        deductions,
        net,
        components: {
          basic: Number(raw.basic || gross),
          allowances: Number(raw.allowances || 0),
          note: raw.note || '',
        },
      });
      form.reset();
      await queryClient.invalidateQueries({ queryKey: ['payslips'] });
      flash.success(response.data?.message || 'Payslip draft created.');
    } catch (err) {
      flash.error(errorMessage(err) || 'Payslip was not created. Net must equal gross minus deductions.');
    } finally {
      setBusy(false);
    }
  }

  async function approve(id) {
    flash.clear();
    setBusyId(id);
    try {
      await ensureCsrf(api);
      const response = await api.post(`/api/v1/payslips/${id}/approve`);
      await queryClient.invalidateQueries({ queryKey: ['payslips'] });
      flash.success(response.data?.message || 'Payslip approved.');
    } catch (err) {
      flash.error(errorMessage(err) || 'Payslip was not approved.');
    } finally {
      setBusyId('');
    }
  }

  return (
    <Shell>
      <PageHeading
        title="Payslips"
        body="Payslips are entered by finance — they are not calculated from attendance. Draft first, then approve so guards can see them."
      />
      <ActionFlash flash={flash.flash} onDismiss={flash.clear} className="mb-4" />

      <SectionCard title="Create payslip draft" body="Enter totals for the pay period. Net is calculated as gross − deductions." icon={Wallet}>
        <form className="grid gap-3 md:grid-cols-2" onSubmit={create}>
          <Field label="Employee" className="md:col-span-2">
            <select name="employee_id" required defaultValue="">
              <option value="" disabled>Select person</option>
              {people.map((row) => (
                <option key={row.id} value={row.id}>{row.name} · {row.employee_code} · {row.kind}</option>
              ))}
            </select>
          </Field>
          <Field label="Period start"><input name="period_start" type="date" required /></Field>
          <Field label="Period end"><input name="period_end" type="date" required /></Field>
          <Field label="Gross (₹)"><input name="gross" type="number" min="0" step="0.01" required placeholder="25000" /></Field>
          <Field label="Deductions (₹)"><input name="deductions" type="number" min="0" step="0.01" required defaultValue="0" /></Field>
          <Field label="Basic (₹)" hint="Stored in components for the slip detail."><input name="basic" type="number" min="0" step="0.01" placeholder="Optional" /></Field>
          <Field label="Allowances (₹)"><input name="allowances" type="number" min="0" step="0.01" defaultValue="0" /></Field>
          <Field label="Note" className="md:col-span-2"><input name="note" placeholder="Optional note for this period" /></Field>
          <button className="kv-btn kv-btn-primary md:col-span-2" type="submit" disabled={busy}>
            {busy ? 'Creating…' : 'Create draft payslip'}
          </button>
        </form>
      </SectionCard>

      <div className="mt-6">
        <SectionCard title="Payslip register" body={`${rows.length} slip${rows.length === 1 ? '' : 's'}`} icon={Wallet} tone="teal">
          {query.isLoading && <Spinner />}
          {query.isError && <ActionFlash flash={{ tone: 'error', text: errorMessage(query.error) || 'Payslips could not be loaded. Your role may need finance.view.' }} />}
          {!query.isLoading && !query.isError && rows.length === 0 && (
            <EmptyState title="No payslips yet" body="Create the first draft above. Approval makes it visible to the employee." />
          )}
          {rows.length > 0 && (
            <div className="kv-table-wrap -mx-5 -mb-5">
              <Table>
                <TableHead>
                  <TableRow>
                    <TableCell>Employee</TableCell>
                    <TableCell>Period</TableCell>
                    <TableCell>Gross</TableCell>
                    <TableCell>Deductions</TableCell>
                    <TableCell>Net</TableCell>
                    <TableCell>Status</TableCell>
                    <TableCell>Actions</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {rows.map((row) => (
                    <TableRow key={row.id}>
                      <TableCell>
                        <div className="font-semibold text-navy">{row.employee?.name || '—'}</div>
                        <div className="text-xs text-muted">{row.employee?.employee_code || ''}</div>
                      </TableCell>
                      <TableCell>{formatPeriod(row.period_start, row.period_end)}</TableCell>
                      <TableCell>{formatMoney(row.gross)}</TableCell>
                      <TableCell>{formatMoney(row.deductions)}</TableCell>
                      <TableCell className="font-semibold text-navy">{formatMoney(row.net)}</TableCell>
                      <TableCell><StatusPill value={row.status} /></TableCell>
                      <TableCell>
                        {row.status === 'draft' && (
                          <button
                            type="button"
                            className="kv-btn kv-btn-navy !px-3 !py-2 !text-xs"
                            disabled={busyId === row.id}
                            onClick={() => approve(row.id)}
                          >
                            {busyId === row.id ? 'Approving…' : 'Approve'}
                          </button>
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
    </Shell>
  );
}

function Incidents() {
  const query = useList('incidents', '/api/v1/incidents');
  const rows = listRows(query.data);
  return (
    <Shell>
      <PageHeading title="Incidents" body="Guards report from the field. This list is the organization record of open and closed cases." />
      <SectionCard title="Cases" icon={TriangleAlert} tone="amber">
        {query.isLoading && <Spinner />}
        {query.isError && <ActionFlash flash={{ tone: 'error', text: errorMessage(query.error) || 'Incidents could not be loaded.' }} />}
        {!query.isLoading && rows.length === 0 ? <EmptyState title="No incidents" body="Field reports will appear here." /> : null}
        {rows.length > 0 && (
          <div className="kv-table-wrap -mx-5 -mb-5">
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>Title</TableCell>
                  <TableCell>Priority</TableCell>
                  <TableCell>Status</TableCell>
                  <TableCell>Reported</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {rows.map((row) => (
                  <TableRow key={row.id}>
                    <TableCell>
                      <div className="font-semibold text-navy">{row.title}</div>
                      <div className="mt-1 max-w-md text-xs text-muted line-clamp-2">{row.description}</div>
                    </TableCell>
                    <TableCell><StatusPill value={row.priority} /></TableCell>
                    <TableCell><StatusPill value={row.status} /></TableCell>
                    <TableCell>{formatDateTime(row.created_at)}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        )}
      </SectionCard>
    </Shell>
  );
}

function Alerts() {
  const query = useList('sos', '/api/v1/emergency/sos');
  const rows = listRows(query.data);
  return (
    <Shell>
      <PageHeading title="SOS" body="Emergency alerts stored for this organization. Notification status comes from the server record." />
      {query.isLoading && <Spinner />}
      {query.isError && <ActionFlash flash={{ tone: 'error', text: errorMessage(query.error) || 'SOS alerts could not be loaded.' }} />}
      <div className="space-y-3">
        {rows.length === 0 && !query.isLoading && <EmptyState title="No alerts" body="Guard SOS events will list here." />}
        {rows.map((row) => (
          <article key={row.id} className="kv-card p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <strong className="text-navy">{row.message || 'SOS'}</strong>
              <StatusPill value={row.status} />
            </div>
            <p className="mt-2 text-sm text-muted">Notification: {row.notification_status || '—'}</p>
            <p className="mt-1 text-xs text-muted">{formatDateTime(row.created_at)}</p>
          </article>
        ))}
      </div>
    </Shell>
  );
}

function Reports() {
  const [type, setType] = useState('attendance');
  const flash = useActionFlash();
  const query = useQuery({
    queryKey: ['report', type],
    queryFn: async () => (await api.get(`/api/v1/reports/${type}`)).data.data,
  });

  async function download() {
    flash.clear();
    try {
      await ensureCsrf(api);
      const response = await api.get(`/api/v1/reports/${type}`, { params: { export: 1 }, responseType: 'blob' });
      const url = URL.createObjectURL(response.data);
      const link = document.createElement('a');
      link.href = url;
      link.download = `${type}-${formatDate(new Date()).replace(/\s/g, '-')}.csv`;
      link.click();
      URL.revokeObjectURL(url);
      flash.success(`${type} CSV downloaded.`);
    } catch (err) {
      flash.error(errorMessage(err) || 'Download failed.');
    }
  }

  return (
    <Shell>
      <PageHeading title="Reports" body="Live counts and CSV export for this organization only." />
      <ActionFlash flash={flash.flash} onDismiss={flash.clear} className="mb-4" />
      <SectionCard title="Export" icon={FileBarChart}>
        <div className="max-w-xl space-y-4">
          <Field label="Report type">
            <select value={type} onChange={(event) => setType(event.target.value)}>
              {['attendance', 'incidents', 'patrols', 'workforce', 'finance'].map((item) => <option key={item} value={item}>{item}</option>)}
            </select>
          </Field>
          <p className="text-sm text-muted">
            {query.isLoading ? 'Loading…' : query.isError ? (errorMessage(query.error) || 'Report unavailable') : query.data ? `${query.data.count ?? 0} rows` : 'No report yet.'}
          </p>
          <button type="button" className="kv-btn kv-btn-navy" onClick={download}>Download CSV</button>
        </div>
      </SectionCard>
    </Shell>
  );
}

function SettingsPage() {
  const query = useList('settings', '/api/v1/settings');
  const flash = useActionFlash();
  const settings = query.data?.data?.settings || {};
  const profile = query.data?.data?.profile || {};

  async function submit(event) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    flash.clear();
    try {
      await ensureCsrf(api);
      await api.patch('/api/v1/settings', {
        attendance_grace_minutes: Number(form.get('attendance_grace_minutes')),
        require_supervisor_verification: form.get('require_supervisor_verification') === 'on',
        require_location: form.get('require_location') === 'on',
        patrol_qr_enabled: form.get('patrol_qr_enabled') === 'on',
      });
      flash.success('Workforce rules saved.');
      query.refetch();
    } catch (err) {
      flash.error(errorMessage(err) || 'Settings were not saved.');
    }
  }

  return (
    <Shell>
      <PageHeading title="Settings" body="Organization profile and workforce rules for attendance and patrol." />
      <ActionFlash flash={flash.flash} onDismiss={flash.clear} className="mb-4" />
      {query.isLoading && <Spinner />}
      {!query.isLoading && (
        <div className="grid gap-6 lg:grid-cols-2">
          <SectionCard title="Organization profile" icon={Building2} tone="teal">
            <dl className="space-y-3 text-sm">
              <div><dt className="text-muted">Name</dt><dd className="mt-1 font-semibold text-navy">{profile.name || '—'}</dd></div>
              <div><dt className="text-muted">Legal name</dt><dd className="mt-1 font-semibold text-navy">{profile.legal_name || '—'}</dd></div>
              <div><dt className="text-muted">Model</dt><dd className="mt-1"><StatusPill value={profile.business_type} /></dd></div>
              <div><dt className="text-muted">Timezone</dt><dd className="mt-1 font-semibold text-navy">{profile.timezone || '—'}</dd></div>
              <div><dt className="text-muted">City / state</dt><dd className="mt-1 font-semibold text-navy">{[profile.city, profile.state].filter(Boolean).join(', ') || '—'}</dd></div>
            </dl>
          </SectionCard>
          <SectionCard title="Attendance and patrol rules" icon={SettingsIcon}>
            <form className="space-y-3" onSubmit={submit}>
              <Field label="Grace minutes" hint="Late / early tolerance before an exception.">
                <input name="attendance_grace_minutes" type="number" defaultValue={settings.attendance_grace_minutes ?? 15} />
              </Field>
              <label className="flex gap-2 text-sm text-muted"><input type="checkbox" name="require_supervisor_verification" defaultChecked={!!settings.require_supervisor_verification} /> Supervisor must verify exceptions</label>
              <label className="flex gap-2 text-sm text-muted"><input type="checkbox" name="require_location" defaultChecked={!!settings.require_location} /> Require location on punch</label>
              <label className="flex gap-2 text-sm text-muted"><input type="checkbox" name="patrol_qr_enabled" defaultChecked={!!settings.patrol_qr_enabled} /> Enable patrol QR checkpoints</label>
              <button className="kv-btn kv-btn-primary" type="submit">Save rules</button>
            </form>
          </SectionCard>
        </div>
      )}
    </Shell>
  );
}

function Workspace() {
  return (
    <Routes>
      <Route path="/" element={<Dashboard />} />
      <Route
        path="/branches"
        element={(
          <Collection
            title="Branches"
            singular="Branch"
            path="/api/v1/branches"
            queryKey="branches"
            columns={['name', 'code', 'city', 'status']}
            fields={[
              { name: 'name', label: 'Name', required: true },
              { name: 'code', label: 'Code', required: true, hint: 'Unique branch code' },
              { name: 'city', label: 'City' },
            ]}
            body="Branches group sites for reporting. Create at least one if you operate multiple cities."
          />
        )}
      />
      <Route
        path="/sites"
        element={(
          <Collection
            title="Sites"
            singular="Site"
            path="/api/v1/sites"
            queryKey="sites"
            icon={MapPin}
            columns={['name', 'code', 'city', 'status']}
            fields={[
              { name: 'name', label: 'Name', required: true },
              { name: 'code', label: 'Code', required: true },
              { name: 'city', label: 'City' },
              { name: 'address', label: 'Address' },
            ]}
            body="Sites are posts where guards work. Create sites before inviting supervisors and assigning coverage."
          />
        )}
      />
      <Route path="/employees" element={<People />} />
      <Route
        path="/clients"
        element={(
          <Collection
            title="Clients"
            singular="Client"
            path="/api/v1/clients"
            queryKey="clients"
            icon={Handshake}
            columns={['name', 'code', 'status']}
            fields={[
              { name: 'name', label: 'Name', required: true },
              { name: 'code', label: 'Code', required: true },
              { name: 'billing_email', label: 'Billing email', inputType: 'email' },
            ]}
            body="Agency clients only. Invite client portal users after the client record exists."
          />
        )}
      />
      <Route path="/attendance" element={<Attendance />} />
      <Route path="/payslips" element={<Payslips />} />
      <Route path="/incidents" element={<Incidents />} />
      <Route path="/alerts" element={<Alerts />} />
      <Route path="/reports" element={<Reports />} />
      <Route path="/settings" element={<SettingsPage />} />
    </Routes>
  );
}

export default function App() {
  return (
    <AuthGate
      api={api}
      portal="tenant"
      title="Organization workspace"
      hint="Sign in with the owner or office account for this organization."
      requireOnboarding
    >
      <Workspace />
    </AuthGate>
  );
}
