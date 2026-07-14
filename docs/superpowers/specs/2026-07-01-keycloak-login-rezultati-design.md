# Keycloak login for Rezultati — Design

Date: 2026-07-01

## Goal

Replace the current HTTP Basic auth (`EnsureAdminKey` middleware) protecting the
`/rezultati` dashboard and its CSV export with **Keycloak SSO** via Laravel
Socialite. Any user who successfully authenticates in Keycloak gets a local
session and can view the dashboard. Access is controlled entirely inside
Keycloak (realm/client membership) — the app grants access to any authenticated
Keycloak user.

A login page with a "Prijava s Keycloak" button is shown to guests; from there
they are redirected to Keycloak.

## Auth flow

```
GET /rezultati (guest)        -> redirect to /prijava
GET /prijava                  -> login page with "Prijava s Keycloak" button
  (click button)
GET /auth/keycloak            -> Socialite redirect to Keycloak
GET /auth/keycloak/callback   -> upsert local User, Auth::login, redirect to /rezultati
POST /odjava                  -> logout -> /prijava
```

## Section 1 — Packages & config

- Add `laravel/socialite` and `socialiteproviders/keycloak` via Composer.
- Register the SocialiteProviders event listener (Laravel 12/13 style) so the
  `keycloak` driver is available. Registration goes in
  `App\Providers\AppServiceProvider::boot()` (or an event listener) per the
  socialiteproviders/manager docs.
- Add a `keycloak` block to `config/services.php`:

  ```php
  'keycloak' => [
      'client_id'     => env('KEYCLOAK_CLIENT_ID'),
      'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
      'redirect'      => env('KEYCLOAK_REDIRECT_URI'),
      'base_url'      => env('KEYCLOAK_BASE_URL'),   // e.g. https://keycloak.example.org
      'realms'        => env('KEYCLOAK_REALM'),      // e.g. pirati
  ],
  ```

- New `.env` / `.env.example` keys:
  - `KEYCLOAK_BASE_URL`
  - `KEYCLOAK_REALM`
  - `KEYCLOAK_CLIENT_ID`
  - `KEYCLOAK_CLIENT_SECRET`
  - `KEYCLOAK_REDIRECT_URI`

## Section 2 — Routes & controller

Routes in `routes/web.php`:

```php
// Auth: login page + Keycloak SSO
Route::get('/prijava',                [AuthController::class, 'login'])->name('login');
Route::get('/auth/keycloak',          [AuthController::class, 'redirect'])->name('auth.redirect');
Route::get('/auth/keycloak/callback', [AuthController::class, 'callback'])->name('auth.callback');
Route::post('/odjava',                [AuthController::class, 'logout'])->name('auth.logout');

// Rezultati behind session auth (was admin.key)
Route::middleware('auth')->group(function () {
    Route::get('/rezultati',           [ResultsController::class, 'index'])->name('results.index');
    Route::get('/rezultati/izvoz.csv', [ResultsController::class, 'export'])->name('results.export');
});
```

`App\Http\Controllers\AuthController`:

- **`login()`** — render `auth.login` view (login page with the Keycloak button).
  If already authenticated, redirect to `results.index`.
- **`redirect()`** — `return Socialite::driver('keycloak')->redirect();`
- **`callback()`** — get the Keycloak user; then:

  ```php
  $kc = Socialite::driver('keycloak')->user();
  $user = User::updateOrCreate(
      ['email' => $kc->getEmail()],
      [
          'name'         => $kc->getName() ?? $kc->getNickname() ?? $kc->getEmail(),
          'keycloak_id'  => $kc->getId(),
      ]
  );
  Auth::login($user, remember: true);
  $request->session()->regenerate();
  return redirect()->intended(route('results.index'));
  ```

  Wrap in try/catch; on failure redirect to `login` with an error flash message.

- **`logout()`** — `Auth::logout()`, `session()->invalidate()`,
  `session()->regenerateToken()`, redirect to `login`.

**Unauthenticated handling:** the `auth` middleware redirects guests to the
`login` named route (`/prijava`) by default — no custom `redirectGuestsTo`
needed since the login route is named `login`.

## Section 3 — User model, migration & cleanup

- **New migration**: add nullable indexed `keycloak_id` (string) to `users`, and
  make `password` nullable (Keycloak users have no local password).
- **User model** (`app/Models/User.php`): add `keycloak_id` to the `#[Fillable]`
  attribute list.
- **Cleanup — remove Basic auth:**
  - Delete `app/Http/Middleware/EnsureAdminKey.php`.
  - Remove the `admin.key` middleware alias registration in `bootstrap/app.php`.
  - Drop `admin_user` / `admin_password` from `config/survey.php`.
  - Remove `SURVEY_ADMIN_USER` / `SURVEY_ADMIN_PASSWORD` from `.env` and
    `.env.example`. Keep `SURVEY_HASH_SALT`.

## Section 4 — Views / UX

**The login page and auth routes are hidden/unlisted.** No links or buttons to
`/prijava`, `/auth/keycloak`, or Rezultati appear anywhere in the public app —
not on the home page, survey pages, the shared layout, or any nav. The login
flow is reachable only by:
- navigating directly to `/rezultati` (guests are redirected to `/prijava`), or
- typing `/prijava` directly.

The **only** place any auth UI is rendered is on the `/prijava` page itself,
once the user is already there.

- **New** `resources/views/auth/login.blade.php` — extends the app layout,
  centered card with a "Prijava s Keycloak" button linking to
  `route('auth.redirect')`, plus an optional error message area (login failed).
  This is the sole auth-related UI in the app.
- **Rezultati header** (`resources/views/results/index.blade.php`) — show the
  logged-in user's name/email and an "Odjava" (logout) button that POSTs to
  `/odjava` with `@csrf`. (Only visible to already-authenticated users on the
  Rezultati page.)
- **No changes** to `resources/views/layouts/app.blade.php`, the home page, or
  survey views that would surface a login/Rezultati link.

## Section 5 — Verification

- No automated tests (per request).
- Verify manually: `php artisan route:list` shows the new auth routes and that
  `/rezultati` is behind the `auth` middleware; guests are redirected to
  `/prijava`; public pages contain no login/Rezultati links.
- Full login round-trip requires a reachable Keycloak realm and is verified in
  the target environment (not in dev).

## Out of scope

- Per-email/role allowlists (access = any authenticated Keycloak user).
- Keycloak single-logout (RP-initiated logout at the IdP) — local logout only
  for now.
- Retaining HTTP Basic auth as a fallback (fully removed).