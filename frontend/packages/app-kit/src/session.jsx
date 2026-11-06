import { useEffect, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { LockKeyhole, Mail, ShieldCheck } from 'lucide-react';
import { setLocale, setProfile } from './store.js';
import { ensureCsrf, errorMessage } from './api.js';
import { translate } from './locales.js';
import { Banner, BrandMark, Logo } from './ui.jsx';
import { onboardingUrl, portalHome, portalLabels as PORTAL_LABELS } from './portals.js';

export function useSession(api) {
  const dispatch = useDispatch();
  const session = useSelector((state) => state.session);
  const query = useQuery({
    queryKey: ['me'],
    queryFn: async () => (await api.get('/api/v1/auth/me')).data.data,
    retry: false,
  });

  useEffect(() => {
    if (query.isSuccess) {
      dispatch(setProfile(query.data));
      if (query.data?.user?.locale) {
        dispatch(setLocale(query.data.user.locale));
      }
    }
    if (query.isError) {
      dispatch(setProfile(null));
    }
  }, [dispatch, query.data, query.isError, query.isSuccess]);

  return { ...session, loading: query.isLoading, reload: query.refetch };
}

export function useT() {
  const locale = useSelector((state) => state.session.locale);
  return (key) => translate(locale, key);
}

export async function signOut(api, queryClient) {
  try {
    await ensureCsrf(api);
    await api.post('/api/v1/auth/logout');
  } catch {
    // Clear local session even if the API call fails.
  }
  queryClient.clear();
}

export function LanguageSwitch({ api, enabled }) {
  const dispatch = useDispatch();
  const locale = useSelector((state) => state.session.locale);
  const profile = useSelector((state) => state.session.profile);
  const t = useT();
  if (!enabled) return null;
  return (
    <label className="flex items-center gap-2 text-sm text-muted">
      <span className="hidden sm:inline">{t('common.language')}</span>
      <select
        className="rounded-xl border border-slate-200 bg-white px-2 py-1.5 text-navy"
        value={locale}
        onChange={async (event) => {
          const next = event.target.value;
          dispatch(setLocale(next));
          if (profile?.user) {
            try {
              await ensureCsrf(api);
              await api.patch('/api/v1/profile', { locale: next });
            } catch {
              // Locale still applies locally even if the save fails.
            }
          }
        }}
      >
        <option value="en">{t('common.english')}</option>
        <option value="hi">{t('common.hindi')}</option>
        <option value="mr">{t('common.marathi')}</option>
      </select>
    </label>
  );
}

export function LoginScreen({ api, portal, title, hint }) {
  const t = useT();
  const dispatch = useDispatch();
  const queryClient = useQueryClient();
  const [challenge, setChallenge] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  async function submit(event) {
    event.preventDefault();
    setError('');
    setBusy(true);
    const form = new FormData(event.currentTarget);
    try {
      await ensureCsrf(api);
      const response = challenge
        ? await api.post('/api/v1/auth/mfa', { challenge, code: form.get('code') })
        : await api.post('/api/v1/auth/login', {
          email: form.get('email'),
          password: form.get('password'),
          portal,
        });
      if (response.data.data.mfa_required) {
        setChallenge(response.data.data.challenge);
        return;
      }
      dispatch(setProfile(response.data.data));
      if (response.data.data.user?.locale) {
        dispatch(setLocale(response.data.data.user.locale));
      }
      await queryClient.invalidateQueries({ queryKey: ['me'] });
    } catch (err) {
      setError(errorMessage(err) || t('common.network'));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="grid min-h-screen lg:grid-cols-2">
      <aside className="kv-side relative hidden overflow-hidden px-12 py-12 text-white lg:flex lg:flex-col lg:justify-between">
        <Logo light />
        <div>
          <p className="text-xs font-bold tracking-[0.22em] text-orange">SECURITY · CONTROL · TRUST</p>
          <h2 className="mt-4 max-w-md text-4xl font-bold leading-tight">The post, the site, and the office stay on their own screens.</h2>
          <div className="mt-8 flex items-center gap-3 rounded-2xl bg-white/8 px-4 py-3 text-sm text-white/75 ring-1 ring-white/10">
            <ShieldCheck size={18} className="text-orange" />
            Session stays on the server. This browser does not keep a token.
          </div>
        </div>
        <div className="absolute -right-10 bottom-10 opacity-30">
          <BrandMark size={180} />
        </div>
        <p className="relative text-sm text-white/50">KAVACH · {PORTAL_LABELS[portal] || portal}</p>
      </aside>
      <main className="flex items-center justify-center px-6 py-12">
        <div className="kv-card w-full max-w-md p-8">
          <div className="lg:hidden"><Logo /></div>
          <h1 className="mt-6 text-2xl font-bold tracking-tight text-navy">{title}</h1>
          <p className="mt-2 text-sm leading-6 text-muted">{hint}</p>
          <form className="mt-6 space-y-4" onSubmit={submit}>
            {challenge ? (
              <label className="block text-sm font-semibold text-navy">
                Authenticator code
                <input name="code" inputMode="numeric" autoComplete="one-time-code" required className="mt-2" />
              </label>
            ) : (
              <>
                <label className="block text-sm font-semibold text-navy">
                  <span className="mb-2 flex items-center gap-2"><Mail size={16} className="text-electric" />{t('common.email')}</span>
                  <input name="email" type="email" autoComplete="username" required />
                </label>
                <label className="block text-sm font-semibold text-navy">
                  <span className="mb-2 flex items-center gap-2"><LockKeyhole size={16} className="text-electric" />{t('common.password')}</span>
                  <input name="password" type="password" autoComplete="current-password" required />
                </label>
              </>
            )}
            {error && <Banner>{error}</Banner>}
            <button type="submit" disabled={busy} className="kv-btn kv-btn-primary w-full">
              {busy ? t('common.loading') : t('common.signIn')}
            </button>
          </form>
        </div>
      </main>
    </div>
  );
}

function WrongPortal({ api, portal, current }) {
  const queryClient = useQueryClient();
  const dispatch = useDispatch();
  const [busy, setBusy] = useState(false);
  const wanted = PORTAL_LABELS[portal] || portal;
  const actual = PORTAL_LABELS[current] || current;

  async function switchAccount() {
    setBusy(true);
    await signOut(api, queryClient);
    dispatch(setProfile(null));
    setBusy(false);
  }

  return (
    <div className="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-16">
      <Logo />
      <h1 className="mt-8 text-2xl font-bold text-navy">Wrong workspace</h1>
      <p className="mt-2 text-sm leading-6 text-muted">
        This browser is signed into <strong>{actual}</strong>. Open that portal, or switch accounts to use <strong>{wanted}</strong>.
      </p>
      <a href={portalHome(current)} className="kv-btn kv-btn-navy mt-6 w-full">
        Open {actual}
      </a>
      <button type="button" className="kv-btn kv-btn-primary mt-3 w-full" disabled={busy} onClick={switchAccount}>
        {busy ? 'Signing out…' : `Sign in to ${wanted}`}
      </button>
    </div>
  );
}

export function AuthGate({ api, portal, title, hint, children, requireOnboarding = false }) {
  const { profile, loading, reload } = useSession(api);
  const [secret, setSecret] = useState(null);
  const [error, setError] = useState('');
  const [deferred, setDeferred] = useState(() => localStorage.getItem(`kavach.mfaDefer.${portal}`) === '1');

  if (loading && !profile) {
    return <div className="grid min-h-screen place-items-center"><div className="h-8 w-8 animate-spin rounded-full border-2 border-electric border-t-transparent" /></div>;
  }
  if (!profile) {
    return <LoginScreen api={api} portal={portal} title={title} hint={hint} />;
  }

  const sessionPortal = profile.membership?.portal;
  if (sessionPortal && sessionPortal !== portal) {
    return <WrongPortal api={api} portal={portal} current={sessionPortal} />;
  }

  const privileged = sessionPortal === 'platform'
    || (profile.membership?.roles || []).some((role) => ['organization_owner', 'tenant_admin'].includes(role));

  if (privileged && !profile.user.mfa_enabled && !deferred) {
    return (
      <div className="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-16">
        <Logo />
        <h1 className="mt-8 text-2xl font-bold">Set up an authenticator</h1>
        <p className="mt-2 text-sm leading-6 text-muted">Owners, admins, and platform operators confirm a one-time code before opening operational records.</p>
        {!secret ? (
          <div className="mt-6 space-y-3">
            <button className="kv-btn kv-btn-primary w-full" type="button" onClick={async () => {
              await ensureCsrf(api);
              const response = await api.post('/api/v1/auth/mfa/enroll');
              setSecret(response.data.data);
            }}>Show setup key</button>
            <button
              className="kv-btn kv-btn-ghost w-full"
              type="button"
              onClick={() => {
                localStorage.setItem(`kavach.mfaDefer.${portal}`, '1');
                setDeferred(true);
              }}
            >
              Continue for now
            </button>
            <Banner tone="info">Local development can continue without MFA. Production still requires enrollment.</Banner>
          </div>
        ) : (
          <form className="mt-6 space-y-3" onSubmit={async (event) => {
            event.preventDefault();
            try {
              await ensureCsrf(api);
              await api.post('/api/v1/auth/mfa/confirm', { code: new FormData(event.currentTarget).get('code') });
              localStorage.removeItem(`kavach.mfaDefer.${portal}`);
              await reload();
            } catch (err) {
              setError(errorMessage(err) || 'The code was not accepted.');
            }
          }}>
            <p className="rounded-2xl bg-cloud px-3 py-3 font-mono text-sm">{secret.secret}</p>
            <p className="text-xs text-muted">Add this key in Google Authenticator or Authy, then enter the 6-digit code.</p>
            <input name="code" required inputMode="numeric" placeholder="6-digit code" />
            {error && <Banner>{error}</Banner>}
            <button className="kv-btn kv-btn-navy w-full" type="submit">Confirm</button>
          </form>
        )}
      </div>
    );
  }

  if (requireOnboarding && profile.tenant && !profile.tenant.onboarding_completed_at) {
    return (
      <div className="mx-auto flex min-h-screen max-w-lg flex-col justify-center px-4 py-16">
        <Logo />
        <h1 className="mt-8 text-2xl font-bold">Finish organization setup</h1>
        <p className="mt-2 text-sm text-muted">This workspace opens after the ten setup steps are complete.</p>
        <a className="kv-btn kv-btn-primary mt-6 w-fit" href={onboardingUrl()}>Continue setup</a>
      </div>
    );
  }

  return children;
}
