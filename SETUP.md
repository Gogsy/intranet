# Setup Guide

White-label Laravel 11 + Filament 3 application. This guide covers installing and
running a fresh instance for a new company.

## 1. Requirements & Runtime

| Component | Version / Source |
|-----------|------------------|
| PHP | **8.4 via Laravel Herd** (`php.bat`) — app requires PHP `>=8.3` |
| Composer | via Herd (`composer.bat`) |
| MySQL | hosted by **XAMPP** — database name `laravel_db` |

- Start MySQL first: `C:\xampp\mysql\bin\mysqld.exe` must be running.
- **Gotcha:** Do **not** use XAMPP's bundled PHP 8.2 — it fails the `>=8.3`
  platform check during `composer install`. Always run PHP/artisan/composer
  through Herd's 8.4.

```bash
# Confirm you're on Herd's PHP 8.4
php.bat -v
```

## 2. First Install

```bash
composer.bat install

# Create and edit .env, then set:
#   APP_URL=http://your-host
#   DB_CONNECTION=mysql
#   DB_DATABASE=laravel_db
#   DB_USERNAME / DB_PASSWORD as configured in XAMPP

php.bat artisan key:generate
php.bat artisan migrate
php.bat artisan storage:link
```

Once MySQL is running and the migrations are applied, open the site. The
`EnsureAppInstalled` middleware (`app/Http/Middleware/EnsureAppInstalled.php`)
detects that setup is not complete (`AppSetting::setup_completed_at` is null) and
redirects **every** request — including `/admin` and `/login` — to the install
wizard at **`/install`**.

### The 4-step install wizard

Handled by `app/Http/Controllers/InstallController.php`:

1. **Intro** — welcome screen.
2. **Create super admin** — name, email, password. Seeds roles
   (`RolesAndPermissionsSeeder`) automatically on a fresh DB, then assigns the
   `super_admin` role.
3. **SMTP** — optional; only validated/saved if a host is entered. Can send a
   test email. These map to the same `MailSetting` used by the Mail Settings page.
4. **Branding** (final) — app name (required), company name, primary color, logo
   upload. Completing this step stamps `setup_completed_at` and **permanently
   locks the wizard** — any later `/install*` request is bounced to `/`.

> **Guard:** the branding step refuses to finish unless a `super_admin` user
> exists — it redirects you back to step 2 if none is found.

## 3. Branding / White-Label

Configured after install on the **General Settings** page (admin, `super_admin`
only — `app/Filament/Pages/GeneralSettings.php`):

- **Application name** and **Company name**.
- **Logo** upload — a default placeholder logo is shown until a logo is uploaded;
  uploading it overrides the placeholder everywhere (front-end, login page, install
  wizard, and the Filament admin panel).
- **Logo height** (px) — how tall the logo renders in the site header
  (default = `AppSetting::DEFAULT_LOGO_HEIGHT`).
- **Favicon** upload.
- **Primary color** & **Accent color** — drive the front-end CSS variables
  `var(--brand)` / `var(--accent)` and the Filament panel's primary color
  (wired in `app/Providers/Filament/AdminPanelProvider.php`).

**SMTP** is managed separately on the **Mail Settings** page
(`app/Filament/Pages/MailSettings.php`), which shares the same `MailSetting`
record the install wizard writes to.

## 4. App Downloads & Nesy

Each app (`app/Models/Application.php`) can serve downloads in one of two modes:

- **Stored APK** — you upload APK builds in the app's Versions panel; the public
  Download button streams the currently active version (stable, permanent URL).
- **Live download** — toggle `live_download` ON. On every click the public
  Download route asks the Nesy API for the newest build and redirects straight to
  it; nothing is stored locally. Active only when
  `update_provider === 'nesy'` and a Nesy app name is set
  (`Application::isLiveDownload()`).

Nesy fetching (`app/Support/NesyVersionFetcher.php`,
`app/Http/Controllers/AppsController.php`) is configured per app in the App
Downloads resource (`app/Filament/Resources/ApplicationResource.php`):

| Field | Purpose |
|-------|---------|
| `update_provider` (toggle) | "This is a Nesy app" — enables the Nesy fields and the "Fetch latest from Nesy" button in the Versions panel. |
| `update_app_name` | The Nesy build identifier sent to the API (default `Nesy-Mobile-Prod`). Override per app to pull a different channel. |
| `update_endpoint` | **Per-company override** of the API URL. Leave blank to use the default Overseas endpoint (`NesyVersionFetcher::ENDPOINT`); set it only when hosting for another company with its own update server. |
| `live_download` (toggle) | Always-latest mode described above. |

Manual "Fetch latest from Nesy" downloads the APK into the app's version channel;
the first fetched version is auto-activated, later ones stay inactive until
activated.

## 5. Roles

Defined in `database/seeders/RolesAndPermissionsSeeder.php` — **THE single
source of truth** for every role and permission. The seeder is idempotent and
self-contained (creates all permissions itself, prunes leftovers); re-run it
any time roles look wrong:

```bash
php artisan db:seed --class=Database\\Seeders\\RolesAndPermissionsSeeder --force
```

| Role | Panel? | Purpose |
|------|--------|---------|
| `super_admin` | yes | Full access to everything (Gate::before bypass). **The only role** that can manage roles/permissions, assign the Super Admin role, see the activity log/security widget and fully manage budgets (settings, lock, import, delete, decision, change log). |
| `admin` | yes | Manages all content (Web Tools, App Downloads, Documentation, Phone Book), users, settings (incl. SMTP) and assigns roles (not Super Admin). On IT Budget: views budgets/investments, edits investment rows while unlocked, exports investments. |
| `budget_expenses` | yes | Add-on role (e.g. on top of Admin): unlocks the budget Expenses tab — view/edit expense rows while unlocked, expenses widgets and export. |
| `phonebook_viewer` | **no** (front-end only) | Signs in on the website to view ALL phone numbers (incl. hidden). No export, no editing, no admin. |
| `phonebook_finance` | **no** (front-end only) | Signs in on the website to view ALL numbers and export the full phone book. No editing, no admin. |

- **super_admin vs admin:** only `super_admin` may grant the `super_admin`
  role — enforced server-side in `UserResource` (`PROTECTED_ROLES`).
- Panel access is allow-listed in `User::BACKEND_ROLES`; front-end-only roles
  are deliberately excluded.
- Permissions are grouped per module as `view_<module>` / `manage_<module>`
  (plus `export_phone_book` and the granular budget set) — see the seeder's
  PERMISSIONS constant and its doc block for the full model.
- **Site Map** (`admin/site-map`, `app/Filament/Pages/SiteMap.php`) is an admin
  page available to any panel user for jumping to front-end pages.

## 6. Operations

- **Activity log** is retained **7 days** (`config/activitylog.php`:
  `delete_records_older_than_days => 7`) and pruned by the `activitylog:clean`
  command, scheduled **daily** in `routes/console.php`.
- **The host MUST run Laravel's scheduler**, or pruning never fires. On Windows,
  add a Task Scheduler job that runs every minute:

  ```bash
  php.bat artisan schedule:run
  ```

  (On Linux, the equivalent cron entry: `* * * * * php artisan schedule:run`.)

- **CSV exports** (phone book) are written with a UTF-8 BOM
  (`\xEF\xBB\xBF`, see `app/Http/Controllers/ImenikController.php`) so Croatian
  characters (č ć ž š đ) render correctly in Excel.

## 7. Production deployment (Linux / nginx)

The dev setup above targets Laravel Herd + XAMPP on Windows. Production runs on
Linux + nginx + PHP-FPM. Current host:

| | |
|--|--|
| App root | `/var/www/sites/intranet.overseas.hr` |
| Web root | `/var/www/sites/intranet.overseas.hr/public` |
| URL | `https://intranet.overseas.hr` (wildcard `*.overseas.hr` cert) |
| Server | nginx → PHP-FPM (`www-data`) |

> Replace `8.3` below with the installed PHP version (`php -v`). App requires `>= 8.3`.

### 7.1 Build & configure

```bash
cd /var/www/sites/intranet.overseas.hr
composer install --no-dev --optimize-autoloader
npm ci && npm run build            # auth/guest pages use @vite — a build is required
```

`.env` (production):

```
APP_ENV=production
APP_DEBUG=false                    # MUST be false — debug pages leak source/secrets
APP_URL=https://intranet.overseas.hr
DB_CONNECTION=mysql
DB_DATABASE=...   DB_USERNAME=...   DB_PASSWORD=...
```

```bash
php artisan key:generate
```

`APP_ENV=production` makes `AppServiceProvider` force HTTPS app-wide — correct here
since the host serves a valid cert.

### 7.2 Schema, permissions, storage, caches

```bash
php artisan migrate --force

# Roles & permissions. Do NOT run shield:generate — generation is disabled
# (config/filament-shield.php) and the seeder is the single source of truth.
# Idempotent: safe to re-run on every deploy.
php artisan db:seed --class=Database\\Seeders\\RolesAndPermissionsSeeder --force

php artisan storage:link
php artisan optimize               # config + route + view cache
```

> Re-run `php artisan optimize` (or `config:cache`) after **any** later `.env`
> change — cached config ignores `.env` until rebuilt.

### 7.3 Writable directories

```bash
sudo chown -R www-data:www-data \
    /var/www/sites/intranet.overseas.hr/storage \
    /var/www/sites/intranet.overseas.hr/bootstrap/cache
```

### 7.4 Upload limits — all THREE layers must be ≥ the file size

A 20 MB upload (Documentation attachments, App APKs) must clear every layer or it
fails. The dev-side fix only covered PHP; **nginx and PHP-FPM both need raising on
the server**:

1. **nginx** `client_max_body_size 24M;` (default is **1M** — see `deploy/nginx.conf.example`)
2. **PHP-FPM** `/etc/php/8.3/fpm/php.ini`: `upload_max_filesize = 20M`, `post_max_size = 24M`
   then `sudo systemctl restart php8.3-fpm`
3. **App** `FileUpload::maxSize(20_000)` + Livewire `temporary_file_upload.rules => ['max:200000']` — already in code

### 7.5 nginx

Use `deploy/nginx.conf.example` (in this repo) as the server block. Adjust the cert
paths and the PHP-FPM socket version, then:

```bash
sudo ln -s /etc/nginx/sites-available/intranet.overseas.hr /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

### 7.6 Scheduler (cron)

Activity-log pruning (and other scheduled tasks, see §6) only run if Laravel's
scheduler is invoked every minute:

```cron
* * * * * cd /var/www/sites/intranet.overseas.hr && php artisan schedule:run >> /dev/null 2>&1
```

### 7.7 First-run wizard

Open `https://intranet.overseas.hr` → `EnsureAppInstalled` redirects to `/install`
→ create super admin (auto-seeds roles) → SMTP (optional) → branding → wizard
locks permanently. See §2 for the wizard details.

### 7.8 (Optional) Phone book import from the legacy `imenik` app

Only when migrating the old data. Inverts the legacy `djelatnici.broj_id`
relationship into `phone_numbers.employee_id` and maps `vidljiv_svima → is_public`.
See `imenik_import.sql` for the full transform.

```bash
# MariaDB target:
mysql <app_db> < imenik_dump.sql
# MySQL client (strip the MariaDB sandbox line on row 1):
tail -n +2 imenik_dump.sql | mysql <app_db>

mysql <app_db> < imenik_import.sql   # guarded: aborts if the dump wasn't loaded first
```

### 7.9 Upgrading an EXISTING production install (pre-roles version)

The current production host runs the old version (no Spatie roles, no install
wizard, `users.is_admin` flag). Upgrading it in place:

1. **PHP**: upgrade to **>= 8.3** first (`composer install` fails the platform
   check otherwise). Update the PHP-FPM socket path in the nginx config and the
   `php.ini` upload limits (§7.4) for the new version.
2. Pull the code, then `composer install --no-dev --optimize-autoloader` and
   `npm ci && npm run build`.
3. `php artisan migrate --force` — among others this creates the permission
   tables, converts every `is_admin` user into a real `super_admin` role
   assignment and drops the flag (existing users/passwords are untouched).
4. **Seed roles** (mandatory — the migration only creates the bare
   `super_admin` role, nothing else):
   `php artisan db:seed --class=Database\\Seeders\\RolesAndPermissionsSeeder --force`
5. `php artisan optimize:clear && php artisan optimize`
6. The `app_settings` table is new, so the first request redirects to the
   **install wizard** (`/install`) — this is expected. Walk through it once:
   - Step 2 (admin): entering an **existing** user's email updates that
     account's name/password and (re)assigns `super_admin`; a new email creates
     a fresh super admin. Existing admins already got `super_admin` from the
     migration in step 3 either way.
   - Step 3 (SMTP): optional, skip if unchanged.
   - Step 4 (branding): set the app name (+ logo/colors) and finish — this
     stamps `setup_completed_at` and permanently locks the wizard.
7. Verify roles under **Roles** in the admin panel and re-check user role
   assignments (Users list) — pre-upgrade there were no roles, so only
   ex-`is_admin` users have one; grant `admin`/front-end roles manually as
   needed.

### 7.10 Post-install smoke test

- Trigger an error → generic page (confirms `APP_DEBUG=false`); real error lands in
  `storage/logs/laravel.log`.
- Log in → `/admin` loads; Phone Book, App Downloads, Documentation reachable.
- Upload a **> 2 MB** PDF to a Documentation section (confirms the nginx + PHP-FPM
  upload limits).
- If using Nesy: click **Fetch latest from Nesy** once (confirms outbound TLS
  verification works against your endpoint).
