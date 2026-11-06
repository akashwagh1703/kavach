/** Base URLs for each KAVACH surface (local defaults match Vite ports). */
export const portalUrls = {
  public: import.meta.env.VITE_PUBLIC_URL || 'http://localhost:5173',
  platform: import.meta.env.VITE_PLATFORM_URL || 'http://localhost:5174',
  tenant: import.meta.env.VITE_TENANT_URL || 'http://localhost:5175',
  client: import.meta.env.VITE_CLIENT_URL || 'http://localhost:5176',
  guard: import.meta.env.VITE_GUARD_URL || 'http://localhost:5177',
  supervisor: import.meta.env.VITE_SUPERVISOR_URL || 'http://localhost:5178',
};

export const portalLabels = {
  platform: 'Platform control',
  tenant: 'Organization workspace',
  client: 'Client portal',
  guard: 'Guard app',
  supervisor: 'Supervisor desk',
};

export function acceptInvitationUrl(token) {
  const base = `${portalUrls.public.replace(/\/$/, '')}/accept-invitation`;
  return token ? `${base}?token=${encodeURIComponent(token)}` : base;
}

export function onboardingUrl() {
  return `${portalUrls.public.replace(/\/$/, '')}/onboarding`;
}

export function portalHome(portal) {
  return portalUrls[portal] || portalUrls.public;
}
