import { useEffect, useState } from 'react';
import { Link, NavLink, Route, Routes, useNavigate, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Building2, CalendarClock, Check, ClipboardCheck, Handshake, KeyRound, QrCode, Shield, ShieldCheck, Siren, Users, Wallet } from 'lucide-react';
import { Banner, Field, IconBadge, Logo, createApi, ensureCsrf, errorMessage, portalHome, portalUrls } from '@kavach/app-kit';

const api = createApi();

const links = [
  ['/features', 'Features'],
  ['/solutions', 'Solutions'],
  ['/pricing', 'Pricing'],
  ['/about', 'About'],
  ['/faq', 'FAQ'],
  ['/contact', 'Contact'],
];

function Shell({ children }) {
  return (
    <div className="min-h-screen text-navy">
      <header className="kv-top">
        <div className="mx-auto flex max-w-6xl items-center justify-between gap-6 px-5 py-3">
          <Link to="/" aria-label="KAVACH home"><Logo /></Link>
          <nav className="hidden items-center gap-1 text-sm font-semibold md:flex">
            {links.map(([to, label]) => (
              <NavLink key={to} to={to} className={({ isActive }) => `kv-nav-pill ${isActive ? 'active' : ''}`}>{label}</NavLink>
            ))}
          </nav>
          <div className="flex items-center gap-2">
            <Link to="/login" className="kv-nav-pill">Sign in</Link>
            <Link to="/onboarding" className="kv-btn kv-btn-orange">Start trial</Link>
          </div>
        </div>
      </header>
      {children}
      <footer className="mt-10 bg-navy px-5 py-10 text-sm text-white/70">
        <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4">
          <Logo light />
          <div className="flex gap-5">
            <Link to="/privacy" className="hover:text-white">Privacy</Link>
            <Link to="/terms" className="hover:text-white">Terms</Link>
            <Link to="/contact" className="hover:text-white">Contact</Link>
          </div>
        </div>
      </footer>
    </div>
  );
}

function Home() {
  return (
    <Shell>
      <section className="mx-auto grid max-w-6xl items-center gap-10 px-5 py-16 md:grid-cols-2">
        <div>
          <p className="text-xs font-semibold tracking-[0.22em] text-orange">SECURITY · CONTROL · TRUST</p>
          <h1 className="mt-4 text-5xl font-semibold leading-[1.05] tracking-tight text-navy">Workforce control for agencies and in-house teams.</h1>
          <p className="mt-5 max-w-xl text-base leading-7 text-muted">Rosters, attendance, patrols, incidents, and SOS on one tenant-isolated platform. English for every desk. Hindi and Marathi for the people on post.</p>
          <div className="mt-8 flex flex-wrap gap-3">
            <Link to="/onboarding" className="kv-btn kv-btn-primary">Create an organization</Link>
            <Link to="/pricing" className="kv-btn kv-btn-ghost">See plans</Link>
          </div>
        </div>
        <div className="kv-card overflow-hidden bg-navy p-8 text-white">
          <div className="kv-orbit">
            <img src="/favicon.png" alt="KAVACH shield" className="relative h-40 w-40 object-contain" />
          </div>
          <div className="mt-6 grid grid-cols-2 gap-3 text-sm">
            {[['Published rosters', CalendarClock], ['Server-time attendance', ShieldCheck], ['Checkpoint patrols', QrCode], ['Audited SOS', Siren]].map(([item, Icon]) => (
              <div key={item} className="flex items-center gap-2 rounded-2xl bg-white/10 px-3 py-3 ring-1 ring-white/10">
                <Icon size={16} className="text-orange" />
                {item}
              </div>
            ))}
          </div>
        </div>
      </section>
      <section className="mx-auto grid max-w-6xl gap-4 px-5 pb-8 md:grid-cols-4">
        {[
          [ShieldCheck, 'Agency or in-house', 'Client billing stays off when the organization guards its own sites.', 'teal'],
          [Users, 'Separate portals', 'Owners, clients, guards, and supervisors never share a screen.', 'blue'],
          [Building2, 'Sites and posts', 'Access follows the sites a person is actually assigned to.', 'amber'],
          [Siren, 'SOS with a receipt', 'An alert is confirmed only after the server stores it.', 'red'],
        ].map(([Icon, title, body, tone]) => (
          <article key={title} className="kv-card p-5">
            <IconBadge icon={Icon} tone={tone} />
            <h2 className="mt-4 font-semibold">{title}</h2>
            <p className="mt-2 text-sm leading-6 text-muted">{body}</p>
          </article>
        ))}
      </section>
    </Shell>
  );
}

function Page({ title, children }) {
  return (
    <Shell>
      <main className="mx-auto max-w-3xl px-5 py-14">
        <h1 className="text-4xl font-semibold tracking-tight">{title}</h1>
        <div className="kv-card mt-6 space-y-4 p-6 text-sm leading-6 text-muted">{children}</div>
      </main>
    </Shell>
  );
}

function Features() {
  const items = [
    [Building2, 'Organization', 'Branches, departments, sites, and workforce records.'],
    [CalendarClock, 'Scheduling', 'Shift templates, rosters, publish, and replacement with an overlap check.'],
    [ShieldCheck, 'Attendance', 'Check-in and check-out use the server clock. A missed punch is an exception, not an absence.'],
    [QrCode, 'Patrol and inspection', 'Checkpoint tokens are stored only as hashes. The raw token is shown once.'],
    [Siren, 'Incidents and SOS', 'Guards report what they see. Supervisors acknowledge and close the record.'],
    [Wallet, 'Finance', 'Invoice lines are calculated on the server. A payslip is entered, reviewed, and approved.'],
  ];
  return (
    <Shell>
      <main className="mx-auto max-w-5xl px-5 py-14">
        <h1 className="text-4xl font-semibold tracking-tight">What the platform records</h1>
        <div className="mt-8 grid gap-4 md:grid-cols-2">
          {items.map(([Icon, title, body]) => (
            <article key={title} className="kv-card flex gap-4 p-5">
              <IconBadge icon={Icon} />
              <div>
                <h2 className="font-semibold">{title}</h2>
                <p className="mt-1 text-sm leading-6 text-muted">{body}</p>
              </div>
            </article>
          ))}
        </div>
      </main>
    </Shell>
  );
}

function Solutions() {
  return (
    <Page title="Two ways to run security">
      <p><strong className="text-navy">Security agency. </strong>Clients, contracts, sites, and a client portal for the sites that client is allowed to see.</p>
      <p><strong className="text-navy">In-house organization. </strong>The same workforce tools, with client records and the client portal turned off.</p>
    </Page>
  );
}

function Pricing() {
  const plans = useQuery({ queryKey: ['plans'], queryFn: async () => (await api.get('/api/v1/public/plans')).data.data });
  return (
    <Shell>
      <main className="mx-auto max-w-6xl px-5 py-14">
        <h1 className="text-4xl font-semibold tracking-tight">Plans</h1>
        <p className="mt-2 text-sm text-muted">Prices come from the live catalog. Amounts are Indian rupees.</p>
        {plans.isError && <div className="mt-6"><Banner>Plans could not be loaded.</Banner></div>}
        <div className="mt-8 grid gap-4 md:grid-cols-3">
          {(plans.data || []).map((plan, index) => (
            <article key={plan.code} className={`kv-card p-6 ${index === 1 ? 'bg-navy text-white' : ''}`}>
              <h2 className="text-xl font-semibold">{plan.name}</h2>
              <p className={`mt-2 text-sm ${index === 1 ? 'text-white/70' : 'text-muted'}`}>{plan.description}</p>
              <p className="mt-6 text-3xl font-semibold tracking-tight">₹{Number(plan.price_monthly).toLocaleString('en-IN')}<span className="text-sm font-normal opacity-70"> / month</span></p>
              <p className={`mt-1 text-sm ${index === 1 ? 'text-white/70' : 'text-muted'}`}>₹{Number(plan.price_yearly).toLocaleString('en-IN')} yearly · {plan.trial_days} day trial</p>
              <ul className="mt-5 space-y-2 text-sm">
                {(plan.features || []).slice(0, 8).map((feature) => (
                  <li key={feature} className="flex items-center gap-2"><Check size={16} className={index === 1 ? 'text-orange' : 'text-teal'} />{feature}</li>
                ))}
              </ul>
              <Link to="/onboarding" className={`kv-btn mt-6 w-full ${index === 1 ? 'kv-btn-orange' : 'kv-btn-navy'}`}>Start with {plan.name}</Link>
            </article>
          ))}
        </div>
      </main>
    </Shell>
  );
}

function Contact() {
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  async function submit(event) {
    event.preventDefault();
    if (busy) return;
    setBusy(true);
    setError('');
    setMessage('');
    const form = new FormData(event.currentTarget);
    try {
      await ensureCsrf(api);
      const response = await api.post('/api/v1/public/contact', Object.fromEntries(form));
      setMessage(response.data.message || 'Received.');
      event.currentTarget.reset();
    } catch (err) {
      setError(errorMessage(err) || 'The message was not sent.');
    } finally {
      setBusy(false);
    }
  }
  return (
    <Page title="Talk to KAVACH">
      <form className="space-y-3" onSubmit={submit}>
        <Field label="Your name"><input name="name" required placeholder="Full name" /></Field>
        <Field label="Work email"><input name="email" type="email" required placeholder="you@company.com" /></Field>
        <Field label="Phone"><input name="phone" placeholder="+91…" /></Field>
        <Field label="Organization"><input name="organization" placeholder="Company or agency" /></Field>
        <Field label="Message"><textarea name="message" required placeholder="How can we help?" rows={5} /></Field>
        {error && <Banner>{error}</Banner>}
        {message && <Banner tone="success">{message}</Banner>}
        <button className="kv-btn kv-btn-primary" type="submit" disabled={busy}>{busy ? 'Sending' : 'Send message'}</button>
      </form>
    </Page>
  );
}

function LoginChooser() {
  const portals = [
    [Shield, 'Platform control', portalUrls.platform, 'KAVACH operators provision organizations'],
    [Building2, 'Organization workspace', portalUrls.tenant, 'Owners and office staff run sites and people'],
    [Handshake, 'Client portal', portalUrls.client, 'Clients see only the sites they are granted'],
    [Users, 'Guard app', portalUrls.guard, 'Guards check in, patrol, and send SOS'],
    [ClipboardCheck, 'Supervisor desk', portalUrls.supervisor, 'Supervisors verify attendance and emergencies'],
  ];
  return (
    <Shell>
      <main className="mx-auto max-w-3xl px-5 py-14">
        <h1 className="text-4xl font-semibold tracking-tight">Choose your screen</h1>
        <p className="mt-3 text-sm leading-6 text-muted">Each role opens a different portal. Use the invitation page first if you were invited by email or token.</p>
        <Link to="/accept-invitation" className="kv-btn kv-btn-navy mt-4 inline-flex">
          <KeyRound size={16} /> Accept invitation
        </Link>
        <div className="mt-6 grid gap-3">
          {portals.map(([Icon, name, href, hint]) => (
            <a key={name} href={href} className="kv-card kv-card-hover flex items-center gap-4 px-5 py-4">
              <IconBadge icon={Icon} />
              <div>
                <div className="font-semibold text-navy">{name}</div>
                <div className="text-sm text-muted">{hint}</div>
              </div>
            </a>
          ))}
        </div>
      </main>
    </Shell>
  );
}

function AcceptInvitation() {
  const [params] = useSearchParams();
  const navigate = useNavigate();
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [busy, setBusy] = useState(false);
  const [portalHint, setPortalHint] = useState('');

  async function submit(event) {
    event.preventDefault();
    setBusy(true);
    setError('');
    setMessage('');
    const body = Object.fromEntries(new FormData(event.currentTarget));
    try {
      await ensureCsrf(api);
      const response = await api.post('/api/v1/auth/invitations/accept', body);
      const portal = response.data.data?.membership?.portal;
      setPortalHint(portal || '');
      setMessage('Account created. Opening your portal…');
      if (portal) setTimeout(() => { window.location.href = portalHome(portal); }, 900);
    } catch (err) {
      setError(errorMessage(err) || 'The invitation could not be accepted.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <Shell>
      <main className="mx-auto max-w-xl px-5 py-14">
        <p className="text-xs font-bold uppercase tracking-[0.18em] text-electric">Invitation</p>
        <h1 className="mt-2 text-4xl font-semibold tracking-tight">Accept your KAVACH invite</h1>
        <p className="mt-3 text-sm leading-6 text-muted">
          Paste the token from your email or from the person who provisioned you. Create a password, then open the correct portal.
        </p>
        <form className="kv-card mt-6 space-y-4 p-6" onSubmit={submit}>
          <Field label="Invitation token">
            <textarea name="token" required rows={3} defaultValue={params.get('token') || ''} placeholder="Paste the 64-character token" />
          </Field>
          <Field label="Your name"><input name="name" required placeholder="Full name" /></Field>
          <Field label="Password" hint="At least 10 characters with upper, lower, and a number.">
            <input name="password" type="password" required minLength={10} autoComplete="new-password" />
          </Field>
          <Field label="Confirm password">
            <input name="password_confirmation" type="password" required minLength={10} autoComplete="new-password" />
          </Field>
          {error && <Banner>{error}</Banner>}
          {message && <Banner tone="success">{message}{portalHint ? ` Portal: ${portalHint}.` : ''}</Banner>}
          <button className="kv-btn kv-btn-primary w-full" type="submit" disabled={busy}>{busy ? 'Creating account' : 'Accept invitation'}</button>
          <button type="button" className="kv-btn kv-btn-ghost w-full" onClick={() => navigate('/login')}>Back to portals</button>
        </form>
      </main>
    </Shell>
  );
}

const steps = ['Account', 'Model', 'Organization', 'Verify', 'Profile', 'Branch', 'Team', 'Rules', 'Plan', 'Finish'];

function Onboarding() {
  const navigate = useNavigate();
  const [step, setStep] = useState(1);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const plans = useQuery({ queryKey: ['plans'], queryFn: async () => (await api.get('/api/v1/public/plans')).data.data });

  useEffect(() => {
    api.get('/api/v1/onboarding').then((response) => {
      if (response.data.data?.step) setStep(response.data.data.step);
    }).catch(() => {});
  }, []);

  async function post(path, body) {
    setBusy(true);
    setError('');
    try {
      await ensureCsrf(api);
      const response = await api.post(path, body);
      const next = response.data.data?.step;
      if (next) setStep(next);
      if (response.data.data?.completed) {
        window.location.href = portalUrls.tenant;
        return response;
      }
      return response;
    } catch (err) {
      setError(errorMessage(err) || 'This step was not saved.');
      return null;
    } finally {
      setBusy(false);
    }
  }

  async function submit(event) {
    event.preventDefault();
    const body = Object.fromEntries(new FormData(event.currentTarget));
    if (step === 1) {
      const saved = await post('/api/v1/onboarding/register', body);
      if (saved?.data?.data?.onboarding?.step) setStep(saved.data.data.onboarding.step);
      return;
    }
    const routes = {
      2: ['/api/v1/onboarding/business-type', body],
      3: ['/api/v1/onboarding/organization', { ...body, timezone: 'Asia/Kolkata' }],
      4: ['/api/v1/onboarding/verify', body],
      5: ['/api/v1/onboarding/profile', body],
      6: ['/api/v1/onboarding/branch', body.skip ? { skip: true } : body],
      7: ['/api/v1/onboarding/invites', {}],
      8: ['/api/v1/onboarding/workforce-settings', {
        attendance_grace_minutes: Number(body.attendance_grace_minutes),
        require_supervisor_verification: body.require_supervisor_verification === 'on',
        require_location: body.require_location === 'on',
        patrol_qr_enabled: body.patrol_qr_enabled === 'on',
      }],
      9: ['/api/v1/onboarding/plan', body],
      10: ['/api/v1/onboarding/complete', {}],
    };
    const [path, payload] = routes[step];
    await post(path, payload);
  }

  return (
    <Shell>
      <main className="mx-auto max-w-xl px-5 py-12">
        <div className="mb-4 h-1.5 overflow-hidden rounded-full bg-white">
          <div className="h-full rounded-full bg-electric" style={{ width: `${step * 10}%` }} />
        </div>
        <p className="text-xs font-semibold tracking-[0.18em] text-electric">STEP {step} OF 10 · {steps[step - 1]}</p>
        <h1 className="mt-2 text-3xl font-semibold tracking-tight">Set up your organization</h1>
        <form className="kv-card mt-6 space-y-3 p-6" onSubmit={submit}>
          {step === 1 && (
            <>
              <Field label="Your name"><input name="name" required placeholder="Full name" /></Field>
              <Field label="Work email"><input name="email" type="email" required placeholder="you@company.com" /></Field>
              <Field label="Phone"><input name="phone" placeholder="+91…" /></Field>
              <Field label="Password" hint="At least 10 characters with upper, lower, and a number.">
                <input name="password" type="password" required minLength={10} autoComplete="new-password" />
              </Field>
              <Field label="Confirm password">
                <input name="password_confirmation" type="password" required minLength={10} autoComplete="new-password" />
              </Field>
            </>
          )}
          {step === 2 && (
            <Field label="How will you use KAVACH?">
              <select name="business_type">
                <option value="agency">Security agency — clients and contracts</option>
                <option value="inhouse">In-house — own sites only</option>
              </select>
            </Field>
          )}
          {step === 3 && (
            <>
              <Field label="Organization name"><input name="name" required placeholder="Acme Security" /></Field>
              <Field label="Organization email"><input name="email" type="email" placeholder="ops@acme.com" /></Field>
              <Field label="Organization phone"><input name="phone" placeholder="+91…" /></Field>
            </>
          )}
          {step === 4 && (
            <>
              <p className="text-sm leading-6 text-muted">Enter the 6-digit code sent to your email. On this machine the code is also written to the API log.</p>
              <Field label="Verification code"><input name="code" inputMode="numeric" required placeholder="000000" /></Field>
            </>
          )}
          {step === 5 && (
            <>
              <Field label="Legal name"><input name="legal_name" placeholder="Registered company name" /></Field>
              <Field label="Address"><input name="address" placeholder="Street address" /></Field>
              <Field label="City"><input name="city" placeholder="City" /></Field>
              <Field label="State"><input name="state" placeholder="State" /></Field>
            </>
          )}
          {step === 6 && (
            <>
              <Field label="Branch name"><input name="name" placeholder="Main branch" /></Field>
              <Field label="Branch code"><input name="code" placeholder="BR-01" /></Field>
              <Field label="City"><input name="city" placeholder="City" /></Field>
              <label className="flex items-center gap-2 text-sm text-muted"><input type="checkbox" name="skip" value="true" /> Skip branch for now</label>
            </>
          )}
          {step === 7 && (
            <div className="space-y-3 text-sm leading-6 text-muted">
              <p className="font-semibold text-navy">Team invites happen after this setup.</p>
              <ol className="list-decimal space-y-2 pl-5">
                <li>Finish onboarding and open the <strong className="text-navy">Organization workspace</strong>.</li>
                <li>Add <strong className="text-navy">Sites</strong>, then open <strong className="text-navy">People</strong>.</li>
                <li><strong className="text-navy">Register</strong> each guard or supervisor (employee record only).</li>
                <li><strong className="text-navy">Invite</strong> them — share the token; they accept and open Guard or Supervisor.</li>
                <li>For supervisors, <strong className="text-navy">assign sites</strong> so they see the right attendance and SOS.</li>
              </ol>
              <p>Continue now. You are not blocked by skipping invites on this step.</p>
            </div>
          )}
          {step === 8 && (
            <>
              <Field label="Attendance grace minutes"><input name="attendance_grace_minutes" type="number" defaultValue="15" /></Field>
              <label className="flex items-center gap-2 text-sm text-muted"><input type="checkbox" name="require_supervisor_verification" defaultChecked /> Supervisor verifies attendance</label>
              <label className="flex items-center gap-2 text-sm text-muted"><input type="checkbox" name="require_location" /> Require location on punch</label>
              <label className="flex items-center gap-2 text-sm text-muted"><input type="checkbox" name="patrol_qr_enabled" defaultChecked /> Enable patrol QR checkpoints</label>
            </>
          )}
          {step === 9 && (
            <Field label="Plan">
              <select name="plan">
                {(plans.data || []).map((plan) => <option key={plan.code} value={plan.code}>{plan.name}</option>)}
              </select>
            </Field>
          )}
          {step === 10 && <p className="text-sm leading-6 text-muted">Finishing activates the organization on the trial you selected and opens the workspace.</p>}
          {error && <Banner>{error}</Banner>}
          <button disabled={busy} className="kv-btn kv-btn-primary w-full" type="submit">{busy ? 'Saving' : step === 10 ? 'Finish and open workspace' : 'Continue'}</button>
        </form>
      </main>
    </Shell>
  );
}

export default function App() {
  return (
    <Routes>
      <Route path="/" element={<Home />} />
      <Route path="/features" element={<Features />} />
      <Route path="/solutions" element={<Solutions />} />
      <Route path="/pricing" element={<Pricing />} />
      <Route path="/about" element={<Page title="About KAVACH"><p>KAVACH is an operations system for security workforces. It keeps each organization’s records separate and shows people only the portal their role uses.</p></Page>} />
      <Route path="/faq" element={<Page title="FAQ"><p>A missing punch stays an exception until a supervisor reviews it. Device time is not the attendance clock. A payslip is not created from attendance by itself.</p></Page>} />
      <Route path="/privacy" element={<Page title="Privacy"><p>Account data, attendance, documents, and support access are stored for the organization that owns them. Support staff see tenant records only during a time-limited grant.</p></Page>} />
      <Route path="/terms" element={<Page title="Terms"><p>Use of KAVACH is limited to the organization you belong to. Sharing a login, or presenting another person’s QR as your own identity, is outside the product’s rules.</p></Page>} />
      <Route path="/contact" element={<Contact />} />
      <Route path="/login" element={<LoginChooser />} />
      <Route path="/accept-invitation" element={<AcceptInvitation />} />
      <Route path="/onboarding" element={<Onboarding />} />
    </Routes>
  );
}
