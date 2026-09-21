# Local network deployment (Windows)

How to run OSPOS Next on one Windows PC in a shop and use that same PC
as the first register, with additional registers on the same LAN. This
is a self-hosted, no-internet setup: the **server PC also works as a POS
register**, and every additional register is a browser pointed at it.

> For the developer quick start (SQLite, `php artisan serve`, HMR) see
> [README.md](README.md). This document is the "run it in a real shop"
> version.

---

## 1. Topology

```
                    Shop LAN (router / switch, ideally wired)
                                   |
        +--------------------------+---------------------------+
        |                          |                           |
 [ SERVER + REGISTER 1 ]     [ Register 2 ]              [ Register 3 ]
 Windows 10/11               browser -> /pos             browser -> /pos
 - PHP 8.3+                  (Chrome / Edge)             (Chrome / Edge)
 - MariaDB 10.6+             optional: thermal           optional: thermal
 - Apache/nginx               printer + scanner            printer + scanner
 - this app
 - Chrome/Edge -> /pos
 - thermal printer + scanner
 static IP e.g. 192.168.1.50
```

- **One database, one app instance.** The register running on the server
  PC, every LAN register, the back office, and the API all use that one
  server installation. There is no per-register app/database install and
  no sync between servers.
- A **terminal** (a.k.a. register) is a database row, not a device.
  The register PWA asks which terminal it is on first launch. The
  seeder already creates two: `T1` "Front Register" and `T2` "Second
  Register". Create `T3` and later terminals in the back office if the
  server counter plus the other counters require more than two.
- On the server PC, open the same canonical address used by the other
  registers, for example `http://192.168.1.50/pos` (or `:8000/pos`).
  Assign that browser its own terminal, such as `T1`; each additional
  physical register must select a different terminal (`T2`, `T3`, etc.).
- Closing the browser on the server PC does **not** stop the server when
  Apache/nginx and MariaDB run as Windows services. Do not shut down or
  sleep the server PC while other registers are trading.

---

## 2. Prerequisites on the server PC

| Component | Version | Notes |
|---|---|---|
| Windows | 10 or 11 | Set the shop network profile to **Private**. |
| PHP | 8.3+ (8.4 fine) | CLI + the extensions below. |
| Composer | 2.x | |
| Node.js + npm | Node 20+ | Only needed to build front-end assets. Not needed at runtime. |
| MariaDB | 10.6+ (or MySQL 8) | Runs as an auto-starting Windows service. |
| A real web server | Apache or nginx | See §5. `php artisan serve` is a fallback only. |

Required PHP extensions (enable in `php.ini`): `pdo_mysql`, `mbstring`,
`openssl`, `curl`, `fileinfo`, `gd`, `zip`, `intl`, `bcmath`, `ctype`,
`xml`. Check with:

```powershell
php -m
```

### Easiest path: Laragon

[Laragon](https://laragon.org/) bundles Apache/nginx + PHP + MariaDB,
auto-starts on boot, and gives you a pretty hostname. If you install
Laragon Full you can skip the separate MariaDB install in §3. Point its
web root at this project's `public/` folder (Laragon → Menu → Apache/
nginx → sites-enabled, or drop the project in `C:\laragon\www`).

Everything below is written to also work with a hand-assembled
PHP + MariaDB + Apache/nginx stack.

---

## 3. One-time server setup

### 3.1 Install and secure MariaDB

```powershell
winget install MariaDB.Server
```

Then create the database and a dedicated user (use HeidiSQL, or
`mysql -u root -p`):

```sql
CREATE DATABASE pos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pos_user'@'localhost' IDENTIFIED BY 'CHANGE-ME-strong-password';
GRANT ALL PRIVILEGES ON pos.* TO 'pos_user'@'localhost';
FLUSH PRIVILEGES;
```

The DB only needs to listen on `localhost` — the app is the only thing
that connects to it. Do **not** expose port 3306 to the LAN.

### 3.2 Get the code and dependencies

```powershell
cd C:\pos                     # wherever the project lives
composer install --no-dev --optimize-autoloader
npm install
npm run build                 # REQUIRED: builds public/build + the
                              # /pos service worker (public/sw.js)
```

Re-run `npm run build` after every code update that touches front-end
assets. The register PWA's offline service worker only exists after a
real build.

### 3.3 Configure `.env`

Copy `.env.example` to `.env` if it does not exist, then set:

```dotenv
APP_NAME="Your Shop Name"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://192.168.1.50            # server's static LAN IP (see 3.4)
                                       # add :8000 only if you serve on 8000

APP_KEY=                               # then run: php artisan key:generate

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pos
DB_USERNAME=pos_user
DB_PASSWORD="CHANGE-ME-strong-password"   # must match §3.1

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

# Currency and timezone are app-wide and cannot be changed safely later.
POS_CURRENCY="LKR"
POS_TIMEZONE="Asia/Colombo"
POS_CASH_ROUNDING=0                     # smallest cash denomination, 0 = off

# Per-user API request ceiling per minute (default 300). Raise it only if a
# very fast lane trips a "Too Many Attempts" error mid-basket; see §11.
# POS_API_RATE_LIMIT=300

# Closed LAN, so the wildcard CORS default is acceptable. To lock it down:
# CORS_ALLOWED_ORIGINS=http://192.168.1.50
```

Notes:

- **`APP_URL` must be exactly how the registers reach the server**
  (same scheme, host, and port). Receipt PDFs, signed URLs, and label
  images use it.
- `APP_ENV=production` + `APP_DEBUG=false` hides stack traces from
  cashiers. Keep `APP_DEBUG=false` on a shop machine.
- Redis is **not** required for two registers — the `database` drivers
  are fine. Revisit only if you add many more registers.

### 3.4 Give the server PC a static IP

Reserve an address for it in the router's DHCP table (by MAC), or set a
static IPv4 on the network adapter (e.g. `192.168.1.50`, mask
`255.255.255.0`, gateway = router). If this address changes, every
register loses the server. Prefer a **wired** connection for the server.

Find the current address with `ipconfig` → *IPv4 Address*.

### 3.5 Build the database

```powershell
php artisan migrate --force
php artisan db:seed --force        # roles, permissions, payment methods,
                                   # tax setup; demo data is skipped in production
```

In production, `db:seed` deliberately skips the demo catalogue and demo
users. Create the first Owner account through your controlled provisioning
process, then add staff from the **Users** screen. A database that was first
seeded while `APP_ENV=local` can still contain demo accounts; deactivate or
delete those before going live (see §4).

To seed the production essentials individually, run:

```powershell
php artisan db:seed --force --class=RolesAndPermissionsSeeder
php artisan db:seed --force --class=PaymentMethodSeeder
php artisan db:seed --force --class=TaxSeeder
```

...then create terminals and users by hand in the back office.

### 3.6 First-run configuration in the back office

Sign in at `http://192.168.1.50/` as `admin` / `password` and set:

| Screen | Why |
|---|---|
| **Settings → Business profile** (`/settings/business-profile`) | Shop name, address, business type (retail vs restaurant — this hides/shows features), receipt header/footer. |
| **Settings → Tax defaults / Numbering / Labels** | Default tax category, document number prefixes, label layout. |
| **Users** (`/users`) | Create a real Owner account, then create one cashier login per person. Deactivate `admin`/`cashier`/etc. demo users (deactivation takes effect on their next request). |
| **Terminals** (`/terminals`) | Create one active terminal per physical counter, including the counter on the server PC. `T1` and `T2` come from the seeder; add `T3` or later terminals when needed. Confirm that each points at the correct stock location and rename them to match the counters. |
| **Stock locations** (`/stock-locations`) | Confirm `MAIN` is the selling location. |
| **Items / Categories** | Your real product catalogue, or import via `/items/import`. |

### 3.7 Optimise for production

Run after every `.env` change or code update:

```powershell
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

To undo (e.g. while troubleshooting): `php artisan optimize:clear`.

---

## 4. Security checklist before going live

- [ ] `APP_DEBUG=false` and `APP_ENV=production` in `.env`.
- [ ] `APP_KEY` generated (not blank, not copied from `.env.example`).
- [ ] Strong, unique `DB_PASSWORD`; MariaDB bound to `localhost` only.
- [ ] All demo users deleted or deactivated; real accounts have strong
      passwords and the least role that fits the job.
- [ ] MariaDB port **3306 not** allowed through the firewall.
- [ ] Only the web port (80 or 8000) allowed inbound, **Private profile
      only** (see §5.2).
- [ ] `storage/` and `bootstrap/cache/` writable by the web server
      account; the rest of the tree not writable by it.
- [ ] Automated database backup scheduled (see §9).
- [ ] Windows auto-updates won't reboot the PC mid-trading-day
      (set active hours).

---

## 5. Running the server

### 5.1 Option A — Apache / nginx (recommended)

Serve the project's `public/` directory as the document root. This is
the only setup that handles several registers hitting the app at once
without stalling.

**Apache** (`httpd-vhosts.conf`):

```apache
<VirtualHost *:80>
    ServerName pos.local
    DocumentRoot "C:/pos/public"
    <Directory "C:/pos/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Laravel ships the needed `public/.htaccess`. Make sure
`mod_rewrite` is enabled.

**nginx**: use the standard Laravel server block from
<https://laravel.com/docs/deployment#nginx>, `root C:/pos/public;`,
with PHP-FPM / `php-cgi` on a local port.

Apache (via Laragon or a Windows service) and MariaDB both start on
boot, so the shop just powers on the PC.

### 5.2 Option B — `php artisan serve` (quick / testing only)

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

`--host=0.0.0.0` is what makes it reachable from other machines; the
default `127.0.0.1` is local-only. Set `APP_URL=http://192.168.1.50:8000`
to match.

Caveat: on Windows PHP's built-in server processes requests **one at a
time**. With two registers doing live updates plus the back office it
can feel sluggish or briefly stall. Fine for evaluation, not ideal for
all-day trading — move to Option A for that.

To keep it running without a logged-in console window, wrap it as a
service with [NSSM](https://nssm.cc/):

```powershell
nssm install OSPOS "C:\php\php.exe" "artisan serve --host=0.0.0.0 --port=8000"
nssm set OSPOS AppDirectory "C:\pos"
nssm start OSPOS
```

### 5.3 Open the Windows Firewall (server PC only)

Admin PowerShell:

```powershell
New-NetFirewallRule -DisplayName "OSPOS web" -Direction Inbound `
  -Protocol TCP -LocalPort 80 -Action Allow -Profile Private
# use -LocalPort 8000 if you serve on 8000
```

Confirm the shop network is **Private** (`Get-NetConnectionProfile`).
Never open this on the Public profile.

### 5.4 Background: the task scheduler

Two maintenance jobs need Laravel's scheduler running continuously:
`stock:reconcile` (daily drift check) and idempotency-key pruning
(hourly). Run one long-lived process:

```powershell
php artisan schedule:work
```

Install it as a service the same way as §5.2 (NSSM), or add a **Task
Scheduler** task: trigger "At startup", action `php.exe artisan
schedule:work`, "Run whether user is logged on or not", working
directory = project root.

A queue worker (`php artisan queue:work`) is **not** required today —
there are no queued jobs. Add one as a service if that changes.

---

## 6. Setting up the server register and LAN registers

First, on the **server PC**:

1. Make sure Apache/nginx and MariaDB are running as Windows services.
2. Open **`http://192.168.1.50/pos`** (or `:8000/pos`) in Chrome/Edge.
3. Sign in and select the terminal assigned to the server counter, for
   example `T1`.
4. Connect that counter's scanner and receipt printer as usual.

Then, on every **additional register** (PC, laptop, or tablet with
Chrome/Edge):

1. Browse to **`http://192.168.1.50/pos`** (or `:8000/pos`).
2. Sign in with that person's cashier login.
3. Pick the terminal for that physical counter (for example Register 2
   selects `T2`, Register 3 selects `T3`). Never share one terminal
   assignment between two counters operating at the same time.
4. Browser menu → **Install app** / **Add to Home screen** → you get a
   full-screen shortcut that launches straight into the register.
5. Bookmark `http://192.168.1.50/` too for supervisors who need the
   back office (refunds, shift reports).

### Barcode scanner

Any USB **keyboard-wedge (HID)** scanner works with zero configuration —
it types the barcode digits and an Enter. Test in a text field first.
Configure the scanner to send a carriage return / Enter suffix.

### Opening and closing a shift

A terminal **cannot ring a sale until it has an open shift.** Each
trading session:

- **Open:** back office → `/shift` → open a shift for that terminal with
  the starting cash float. (A cashier with the right permission can do
  this; otherwise a supervisor does it.)
- **Close:** `/shift` → close the shift, count the cash drawer by
  denomination, the system reports over/short.
- History and Z-reports: `/shift/history` and **Reports → Shifts**.

Only **one open shift per terminal** is allowed (enforced in the DB).

---

## 7. HTTPS and offline mode — important

The register is an offline-first PWA: if the network or server blips, it
queues sales locally (IndexedDB) and syncs when the server returns. That
offline queue depends on a **service worker**, and browsers only run
service workers on `https://` or `http://localhost`.

**Over plain `http://192.168.1.50` the registers run online-only** — the
UI still works, but every sale needs the server reachable at that moment.

For a shop where the server PC is always on and on the same switch, that
is usually acceptable. If you need registers to keep selling through a
server reboot or a flaky access point, add TLS on the LAN:

1. Install [mkcert](https://github.com/FiloSottile/mkcert) on the
   server, run `mkcert -install`, then
   `mkcert pos.local 192.168.1.50`.
2. Serve HTTPS with that cert (Apache/nginx `443` vhost, or Caddy).
3. Set `APP_URL=https://pos.local` and `SESSION_SECURE_COOKIE=true` in
   `.env`, re-run `php artisan config:cache`.
4. On **each register**: install the mkcert **root CA**
   (`mkcert -CAROOT` shows the folder — copy `rootCA.pem`, import into
   the OS/browser trust store), and add a `hosts` entry
   `192.168.1.50  pos.local`.

After that the service worker installs and cold-start-offline works.

### The "Offline" banner

The register shows an amber **"Offline — sales saved on this register"**
banner whenever it can't reach *this server*. It probes
`GET /api/v1/ping` on a heartbeat (every ~20 s when healthy, every ~5 s
while it thinks it's offline) rather than trusting the browser's
`navigator.onLine` flag — so the banner also appears when the **server
PC** is asleep or crashed while the register's own network is fine, and
it does **not** appear just because the shop's internet link is down
while the LAN still works. Sales made while the banner is up are held in
the register's IndexedDB queue and complete automatically on the next
successful probe.

---

## 8. Receipt printer (optional)

The app prints ESC/POS thermal receipts. A **network (Ethernet/Wi-Fi)**
printer is simplest: give it a static IP on the LAN and enter
`host:port` (usually port `9100`) as the terminal's / location's printer
target in the back office. Loopback and link-local addresses are
rejected by validation.

A USB printer must be attached to the machine doing the printing and
shared/queued through the OS; network printers avoid that.

PDF receipts (`/sales/{sale}/receipt`) work with any ordinary printer
and need no ESC/POS setup.

---

## 9. Backups

The entire business lives in MariaDB. Schedule a nightly dump and copy
it off the machine.

```powershell
# C:\pos\backup.ps1
$stamp = Get-Date -Format "yyyy-MM-dd_HHmm"
$out   = "D:\pos-backups\pos_$stamp.sql"
& "C:\Program Files\MariaDB 10.6\bin\mysqldump.exe" `
  -u pos_user -p"CHANGE-ME-strong-password" pos | Out-File -Encoding utf8 $out
# keep 30 days
Get-ChildItem D:\pos-backups\*.sql |
  Where-Object LastWriteTime -lt (Get-Date).AddDays(-30) | Remove-Item
```

Task Scheduler → daily, after close. Also back up `.env` and the
`storage/app` folder (uploaded images, generated PDFs). Test a restore
onto a spare machine at least once:

```powershell
mysql -u pos_user -p pos < D:\pos-backups\pos_2026-09-09_2200.sql
```

---

## 10. Updating the app

```powershell
# on the server, during a closed period
php artisan down
git pull                              # or copy the new files in
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

Then hard-refresh each register once (Ctrl+F5) so it picks up the new
service worker / assets. Back up the database **before** `migrate`.

---

## 11. Troubleshooting

| Symptom | Likely cause / fix |
|---|---|
| Register can't reach the server | Wrong/changed server IP; firewall rule missing or on the wrong profile; server not on Private network; web server / DB service not started. From a register run `ping 192.168.1.50` then open the URL in a plain browser tab. |
| Login works on the server but not from a register | `APP_URL` doesn't match the URL the registers use (scheme/host/port). Fix `.env`, `php artisan config:cache`. |
| "No open shift" when starting a sale | Open a shift for that terminal at `/shift`. |
| `/pos` shows a stale version after an update | Service worker cache — Ctrl+F5, or in DevTools → Application → Service Workers → Unregister, then reload. Confirm `npm run build` ran. |
| Register won't load at all when the network drops | Expected over `http://<ip>` — the service worker (needed for cold-start offline) requires HTTPS; see §7. A register that's *already open* still queues sales offline without it. |
| "Offline" banner stuck on / flapping | The register can't reach `GET /api/v1/ping`. Check the web server is up and the server PC isn't asleep; open `http://192.168.1.50/api/v1/ping` from the register (should return an empty `204`). |
| Everything slow with 2+ registers | You're on `php artisan serve` — move to Apache/nginx (§5.1). |
| "Too Many Attempts" / "sending requests faster than the server allows" during fast scanning | The per-user API rate limit was hit. Default is 300 req/min; queued sales are **not** lost (they retry automatically within seconds). If a lane genuinely runs faster, set `POS_API_RATE_LIMIT` higher in `.env` and `php artisan config:cache`. If it happens at *idle*, suspect a client stuck in a retry loop — check `storage/logs` and the browser console. |
| 500 error with no detail | `APP_DEBUG=false` (correct for prod). Read `storage/logs/laravel.log` on the server. |
| Permission / "This action is unauthorized" | The user's role lacks that permission. Adjust the role in `/users` — abilities are exact-match, there is no wildcard. |
| Times or currency look wrong | `POS_TIMEZONE` / `POS_CURRENCY` in `.env`. Currency is fixed after the first sale — do not change it on a live database. |
| Scheduled drift report never appears | `php artisan schedule:work` (or `schedule:run` every minute) isn't running as a service. |

---

## 12. Daily operation

**Open:** power on the server PC → confirm the back office loads on it →
each cashier opens the register PWA, signs in, opens their shift with
the cash float.

**During the day:** registers just work. Supervisors use the back office
for refunds (`/sales/{id}/refund`), voids, price checks, and re-prints.

**Close:** each cashier closes their shift and counts the drawer →
supervisor reviews over/short in **Reports → Shifts** → confirm the
nightly backup ran → leave the server PC on (or shut down after the
backup; it is not needed overnight unless you run reports).
