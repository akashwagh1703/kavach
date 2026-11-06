# KAVACH

KAVACH is a security-operations platform for agencies and in-house guard teams. One Laravel API serves six separate web portals. Each portal is its own React app. A person signs in to the portal that matches their membership.

## What runs locally

| Surface | URL |
| --- | --- |
| Public website and onboarding | http://localhost:5173 |
| Platform admin | http://localhost:5174 |
| Organization workspace | http://localhost:5175 |
| Client portal | http://localhost:5176 |
| Guard portal | http://localhost:5177 |
| Supervisor portal | http://localhost:5178 |
| API | http://localhost:8000 |

PostgreSQL runs in Docker as `kavach-postgres` and owns the `kavach` database. Queues, cache, and sessions use that database. Redis is not required.

## Start

```powershell
cd "D:\Akash Wagh\Security Guard Project\kavach"
docker compose up -d
cd backend
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
cd ..\frontend
pnpm install
pnpm dev:public
```

Start the other portals with `pnpm dev:admin`, `dev:tenant`, `dev:client`, `dev:guard`, and `dev:supervisor`.

Mail is written to `backend/storage/logs/laravel.log`. Verification codes and invitation tokens are in that log. They are not returned by the API.

Create a platform operator only when both variables are set in the environment, then run:

```powershell
php artisan kavach:create-platform-admin
```

The command refuses to run if the email or password is missing, and it does not print the password.

## Product rules that the API enforces

- The browser never chooses the tenant. Login stores the membership on the server session.
- A missed punch is an attendance exception. It is not stored as an absence.
- Check-in, check-out, SOS, and patrol events require an `Idempotency-Key`. Repeating the key returns the original record.
- Punch time is the server clock.
- A payslip is created from amounts you enter. Net pay must equal gross pay minus deductions. Attendance does not create a payslip.
- Checkpoint QR values are stored as hashes. The raw token is returned once, when the checkpoint is created.
- In-house organizations cannot create clients.
- Platform admins see organization metadata. Reading a tenant's sites requires a time-limited support grant.
- Hindi and Marathi are accepted on the guard and supervisor portals. Every other portal stays in English.

## What this checkout includes

The API covers organization setup, workforce, scheduling, attendance, patrols, inspections, incidents, SOS, leave, equipment, finance, reports, and the public catalog. Feature tests cover onboarding, login, tenant isolation, attendance idempotency, missing punches, in-house clients, and payslip arithmetic.

The six portals call that API. They are the working shells for those flows, not every screen in the specification. Form requests and model policies are still expressed as controller validation and gates. Playwright is not set up. This PHP build does not include Argon2id, so local passwords use bcrypt. A host whose PHP defines `PASSWORD_ARGON2ID` can set `HASH_DRIVER=argon2id`.

See `docs/architecture/overview.md` for the layout and the security boundaries.
