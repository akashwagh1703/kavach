# Architecture

KAVACH is a modular Laravel 12 monolith with six independent React applications. Inertia is not used. The portals do not share a router, a layout, or a session in the browser beyond the API cookie.

```
frontend/apps/public-website      :5173
frontend/apps/platform-admin      :5174
frontend/apps/tenant-workspace    :5175
frontend/apps/client-portal       :5176
frontend/apps/guard-portal        :5177
frontend/apps/supervisor-portal   :5178
frontend/packages/app-kit         shared API client, session, language, visual primitives
backend                           Laravel API under /api/v1
```

Tailwind lays out pages. MUI is used for tables and dialogs. The same control is not styled by both.

## Tenancy

`EstablishContext` reads `kavach.portal` and `kavach.membership_id` from the session. Those values are written only by the login service. Models that use `BelongsToTenant` are filtered when a tenant context is active. If the context is active and the tenant id is empty, the query matches nothing. Creating a row for another tenant throws a not-found error.

System roles have a null tenant id and are not tenant-scoped, so they stay findable during login.

Support access sends `X-Support-Grant`. An empty header is rejected before the grant id is queried. A grant lasts at most eight hours and carries view permissions only.

## Identity

Authentication is a Sanctum cookie session. The access token is not stored in `localStorage`. Login failures use one message. Platform operators, organization owners, and tenant admins must enroll TOTP before operational routes. Onboarding, profile, logout, and the MFA routes stay available during enrollment.

Passwords require 10 characters with mixed case, letters, and numbers. The hasher is Argon2id when PHP defines it, and bcrypt otherwise.

## Attendance and emergencies

Check-in is accepted only inside a published shift window. The same idempotency key returns the same attendance day. After a published shift ends with no punch, a scanner writes status `exception` and exception type `missing_punch`. It does not invent a check-in time.

SOS writes the alert and the in-app notifications in one transaction. The response says supervisors were notified only when recipients exist.

## Language

`SetLocale` forces English except for guard and supervisor memberships, which honor the saved locale or `Accept-Language` of `en`, `hi`, or `mr`. The guard and supervisor apps persist the choice and can change it without signing out.

## Deployment shape

The intended host is Ubuntu with Nginx, PHP-FPM, PostgreSQL, a database queue worker, and the scheduler. Private documents stay on a disk that is not web-served. Suggested hostnames are `www`, `admin`, `app`, `client`, `guard`, `supervisor`, and `api` under `kavach.in`. Those names are a proposal, not a registered deployment.

Scheduled commands:

- `kavach:scan-missing-punches` every 15 minutes
- `kavach:record-usage` daily at 01:30
- `kavach:scan-document-expiry` daily at 02:00
