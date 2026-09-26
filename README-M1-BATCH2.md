# Milestone 1 - Foundation / Batch 2

Implements:
- Laravel Socialite / Google-only sign-in
- exact normalized Gmail/email allowlist check
- one-time registration lock
- preselected role assignment
- employee primary-team membership on registration
- QC scope assignment on registration
- first Super Admin CLI bootstrap (no web self-registration)
- absolute 24-hour session expiration
- session-version revocation foundation
- inactive user login blocking
- basic login/dashboard/logout UI
- automated authentication/session tests

## Why local OAuth uses localhost:8000
Google requires HTTPS redirect URIs except localhost. The Herd `.test` domain is convenient
for normal local development, but the safest standards-compliant local OAuth callback is:

`http://localhost:8000/auth/google/callback`

When testing real Google sign-in locally, run:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

and open:

`http://localhost:8000/login`

Do not start the OAuth flow on the `.test` domain and callback to localhost, because
the OAuth state is stored in the browser session and the host must stay consistent.

## Apply
Extract this ZIP into the Laravel project root and overwrite included files, then run:

```powershell
powershell -ExecutionPolicy Bypass -File .\setup-m1-batch2.ps1
```

The automated tests do not need real Google credentials because Socialite is faked.

## Google Cloud setup after tests pass
Create a Web Application OAuth client and add this local redirect URI exactly:

`http://localhost:8000/auth/google/callback`

Put the Client ID and Client Secret into `.env`:

```env
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback
OFFICE_SESSION_HOURS=24
SESSION_LIFETIME=1440
```

Never commit `.env`.

## Bootstrap the first Super Admin
This is intentionally a CLI-only one-time bootstrap. It does not enable Super Admin
self-registration through the browser.

```powershell
php artisan office:bootstrap-super-admin your-google-email@gmail.com --name="Your Name"
```

Then run the local server and sign in with that exact Google account.

After the first Super Admin exists, the command refuses to create another Super Admin.
Additional admins must later be created/promoted through the controlled application workflow.

## Next batch
- Super Admin dashboard shell
- Team CRUD
- Gmail allowlist UI
- User list/lifecycle
- Employee capabilities
- QC scopes
- permission override UI foundation
