# Licensing design (node-locked, time-limited)

How to make this application refuse to run unless it holds a valid licence
**issued by us**, where a licence:

- is bound to **one machine** and cannot be copied to another,
- has a **fixed time period** and stops working when it lapses,
- is verifiable **offline** (the shop LAN has no internet — see
  [DEPLOY_LOCAL.md](DEPLOY_LOCAL.md) §7).

This is a design document, not finished code. It defines the artifact
format, the checks, the failure behaviour, the activation flow, and an
implementation plan. Build order is in §12.

---

## 1. Scope and deployment reality

From [DEPLOY_LOCAL.md](DEPLOY_LOCAL.md):

- **One app instance per shop.** One database, one server PC. A
  "terminal"/register is a database row, not a device. So the licence is
  **per server install**, not per register — optionally capping the
  number of active terminal rows.
- **No internet at runtime.** Online activation cannot be a hard
  dependency. Offline activation is the primary path; an online layer is
  a bonus (§9).
- **The customer has the PHP source** and updates with `git pull`
  ([DEPLOY_LOCAL.md](DEPLOY_LOCAL.md) §10). Any licence check written in
  plain PHP can be edited out. See the threat model in §2.
- **PHP 8.3 / Laravel 13 / Livewire 4.** libsodium is bundled with PHP
  8.3, so Ed25519 signing and verification need **no extra package**
  (`sodium_crypto_sign_*`).

---

## 2. Threat model — what this does and does not buy

**Licensing here is a speed bump plus a commercial/contractual lever, not
DRM.** A technical customer with the source can locate the check and
remove it. Design to that truth.

| Attack | Defended by | Result |
|---|---|---|
| Copy the install + `license.key` to a second PC | Machine fingerprint in the signed payload (§5) | Second PC fails the fingerprint check |
| Edit `not_after` / fingerprint in the licence file | Ed25519 signature over the whole payload (§4) | Signature check fails |
| Forge a licence file | Private key never leaves us (§9) | Cannot produce a valid signature |
| Set the PC clock back to before expiry | Monotonic "highest time ever seen" state (§6) | Detected as tamper → restricted mode |
| Clone the disk to identical hardware (VM) | BIOS/system UUID differs on a new VM; bare-metal move changes baseboard serial (§5) | Fingerprint drops below match threshold |
| Comment out the middleware in the source | **Only** IonCube/SourceGuardian encoding of the gate classes, or shipping encoded release archives instead of a git remote (§10); plus a redundant check in the sale path and a signed file manifest | Bypass becomes a deliberate, provable act |

Realistic goal: casual copying and clock-setting do not work, and a
deliberate bypass is a clear breach we can pursue commercially. Accept
that a determined bad actor with source can still win.

---

## 3. Architecture overview

```
  OUR SIDE (online, private)                 CUSTOMER SIDE (offline shop PC)
  ─────────────────────────                  ──────────────────────────────
  licence issuer                             this app
   - Ed25519 keypair (secret key offline)     - config/license.php  (PUBLIC key only)
   - licenses + activations tables            - LicenseGate service  (verify + cache)
   - artisan: license:issue / revoke /        - EnsureLicenseValid middleware (web + api)
     extend / resign                          - /license activation page (Livewire)
                                              - license_state row + encrypted state file
        │  issues                             - artisan: license:fingerprint, license:check
        ▼
   license.key  ──(email / USB)──►  storage/app/license/license.key
   (base64 payload "." base64 signature)
```

Runtime check, every request, cheap (cached):

```
request ─► EnsureLicenseValid ─► LicenseGate::status()
                                   ├─ read license.key, verify Ed25519 signature
                                   ├─ check not_before ≤ now ≤ not_after (+ grace)
                                   ├─ compute machine fingerprint, match ≥ N of M
                                   ├─ check now ≥ last_seen_at  (clock-rollback)
                                   └─ cache result ~1h
        status ∈ { Valid, ExpiringSoon, Grace, Restricted }
```

---

## 4. The licence artifact

A single file, `storage/app/license/license.key`:

```
base64url(payload_json) . "." . base64url(ed25519_detached_signature)
```

`payload_json`:

```json
{
  "v": 1,
  "license_id": "LIC-2026-000123",
  "product": "ospos-next",
  "edition": "pro",
  "customer": { "id": 123, "name": "Acme Store" },
  "fingerprint": ["<sha256hex>", "<sha256hex>", "<sha256hex>", "<sha256hex>"],
  "fp_min_match": 3,
  "not_before": "2026-01-01T00:00:00Z",
  "not_after":  "2027-01-01T00:00:00Z",
  "grace_days": 14,
  "max_terminals": 3,
  "features": ["loyalty", "giftcards", "multi_location"],
  "issued_at": "2026-01-01T00:00:00Z"
}
```

- The signature covers the **exact payload bytes**, so `not_after`,
  `fingerprint`, `features`, and `max_terminals` are all tamper-evident.
- The app ships the **public** key only, embedded in code (not in an
  editable `config/*.php` that a customer can swap). The **secret** key
  lives only on our issuing machine / a vault.
- Format is deliberately simple and dependency-free. A JWT with `EdDSA`
  is an acceptable alternative if you prefer a standard envelope.

### Verify (app side) — sketch

```php
// App\Licensing\Signature
public static function verify(string $token): array
{
    [$body, $sig] = explode('.', $token, 2) + [null, null];
    if ($body === null || $sig === null) {
        throw new LicenseInvalid('malformed');
    }
    $payload = self::b64urlDecode($body);
    $ok = sodium_crypto_sign_verify_detached(
        self::b64urlDecode($sig),
        $payload,
        self::PUBLIC_KEY,                 // 32 raw bytes, embedded constant
    );
    if (! $ok) {
        throw new LicenseInvalid('signature');
    }
    return json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
}
```

### Sign (our side) — sketch

```php
$payload = json_encode($data, JSON_UNESCAPED_SLASHES);
$sig = sodium_crypto_sign_detached($payload, $secretKey);   // 64 bytes
$token = b64url($payload) . '.' . b64url($sig);
```

Generate the keypair once:

```php
$kp  = sodium_crypto_sign_keypair();
$pub = sodium_crypto_sign_publickey($kp);   // ship this (hex/base64) in the app
$sec = sodium_crypto_sign_secretkey($kp);   // keep offline, never commit
```

---

## 5. Machine fingerprint (Windows)

Collect several stable identifiers, normalise (trim, upper-case, drop
obviously-bogus values like `To be filled by O.E.M.`), SHA-256 each one
individually, and store the **set** in the licence.

| Component | Source | Changes when |
|---|---|---|
| Machine GUID | `HKLM\SOFTWARE\Microsoft\Cryptography\MachineGuid` | OS reinstall |
| System / BIOS UUID | `(Get-CimInstance Win32_ComputerSystemProduct).UUID` | different machine, new VM |
| Baseboard serial | `(Get-CimInstance Win32_BaseBoard).SerialNumber` | motherboard swap |
| System volume serial | `(Get-CimInstance Win32_LogicalDisk -Filter "DeviceID='C:'").VolumeSerialNumber` | reformat of `C:` |

Matching rule: **`fp_min_match` of the components must match** (default
3 of 4). This tolerates a disk reformat or a board RMA without a support
call, while a different PC matches zero and is refused.

Notes:

- Do **not** shell out to PowerShell on every request. Compute in
  `license:check` (scheduled) and on activation; cache the result
  (`Cache::put('license.fingerprint', …, 3600)`).
- Read the registry with `reg query` or a single `powershell -NoProfile
  -NonInteractive -Command` call; guard with a short timeout.
- Keep the raw components only in memory/log for support; the licence and
  cache store hashes.
- A cross-platform fallback (Linux/macOS dev boxes) can hash
  `/etc/machine-id`, `ioreg` UUID, etc. — not required for production but
  handy so developers are not locked out.

### Fingerprint blob for activation

`php artisan license:fingerprint` prints a copyable, self-describing blob
(base64 of `{components, hashes, hostname, os}`) that the customer sends
us. We extract the hashes into the licence we issue back.

---

## 6. Time period and clock-rollback defence

`not_before` / `not_after` in the signed payload define the window. The
shop controls the PC clock and may run no NTP, so the wall clock alone is
not trustworthy.

Persist, in **both** a single-row `license_state` table **and** an
encrypted file (`storage/app/license/state.bin`):

- `last_seen_at` — the **highest** wall-clock time ever observed by the
  app. Updated on every boot and every `license:check`.
- `runtime_seconds` — cumulative monotonic-ish counter, incremented by
  the scheduled job, so even re-setting the clock to the exact old value
  is caught over time.
- `activated_fingerprint`, `license_id`, `verified_at`.

Encrypt the file with a key derived from `APP_KEY` + the machine
fingerprint (`hash_hkdf`), so the customer cannot hand-edit
`last_seen_at` backwards.

Checks:

- `now < last_seen_at - SKEW` (SKEW ≈ 24h) → **clock moved backward** →
  treat as tamper → restricted mode (§7).
- State file missing on a run where the DB row exists → suspicious →
  force re-verification.
- If the online layer (§9) is reachable, take signed server time and
  trust it over the local clock.

---

## 7. Enforcement in the app

### `LicenseGate` service

Single source of truth. Returns a status enum, cached ~1h in the cache
store (`database` driver per [DEPLOY_LOCAL.md](DEPLOY_LOCAL.md) §3.3):

| Status | Meaning |
|---|---|
| `Valid` | signature ok, within window, fingerprint ok, no rollback |
| `ExpiringSoon` | `Valid` but `not_after` is within 30 days |
| `Grace` | past `not_after` but within `grace_days` |
| `Restricted` | past grace, or bad signature, or fingerprint mismatch, or clock tamper, or no licence file |

### `EnsureLicenseValid` middleware

Registered on the `web` and `api` groups. Allowlist that is **always**
reachable:

- the `/license` activation page and its POST,
- the login routes (Fortify),
- static assets / `build/*`,
- `GET /api/v1/ping` (the register heartbeat — see
  [DEPLOY_LOCAL.md](DEPLOY_LOCAL.md) §7).

Behaviour by status:

| Status | Web | API |
|---|---|---|
| `Valid` | pass | pass |
| `ExpiringSoon` | pass + amber banner "Licence expires in N days" | pass, `X-License-Expires-In` header |
| `Grace` | pass + red persistent banner; block Settings writes | pass, `X-License-State: grace` |
| `Restricted` | redirect to `/license` for the money paths; keep reports/exports/shift-close reachable | `403` JSON `{ "error": "license_restricted" }` on sale endpoints |

### Fail mode — do **not** hard-lock a live register

Restricted mode must still allow:

- the `/license` screen (so they can reactivate),
- **closing** an open shift and counting the drawer,
- reports, Z-reports, and CSV/PDF exports (read-only),

and must block:

- creating sales, opening shifts, refunds/voids,
- the corresponding API endpoints (`POST /api/v1/sales`, etc.).

A blank white page mid-transaction reads as a bug and generates support
load. A clear "Licence needs attention — sales are paused, reports and
shift close still work" screen does not.

### Redundant check

Inside `App\Domain\Sales` sale-creation Action, read the **cached** gate
result and refuse when `Restricted`. Cheap (no PowerShell, no crypto —
just a cache read), and it means deleting the middleware alone is not
enough to resume trading.

### Scheduled `license:check`

Hourly, alongside the existing scheduler
([DEPLOY_LOCAL.md](DEPLOY_LOCAL.md) §5.4):

- recompute fingerprint, re-verify, refresh the cache,
- update `last_seen_at` / `runtime_seconds`,
- write a line to `storage/logs` and (optionally) raise an in-app
  notification as `not_after` approaches (30 / 14 / 7 / 1 days),
- if online (§9), pull a fresh signed licence and revocation status.

---

## 8. Activation and renewal flow (offline-first)

**Activation**

1. Installer runs `php artisan license:fingerprint` (or opens `/license`,
   which shows the same blob).
2. Customer sends us the blob + their order reference.
3. We issue:
   `php artisan license:issue --customer="Acme Store" --fingerprint=<blob> --months=12 --edition=pro --features=loyalty,giftcards --max-terminals=3`
   → produces `license.key`.
4. Customer drops `license.key` into `storage/app/license/` **or** uploads
   it on the `/license` page.
5. App verifies (signature → window → fingerprint), writes
   `license_state` + `state.bin`, clears the gate cache. Trading resumes.

**Renewal**

- We run `license:issue` (or `license:extend`) with a later `not_after`
  for the same fingerprint → email the new `license.key`.
- Customer replaces the file (or uploads it). No reinstall.

**Hardware change**

- If the fingerprint now matches `< fp_min_match`, the app enters
  restricted mode and shows the new fingerprint blob on `/license`.
- Customer sends the new blob; we `license:resign` for the new machine.
  Track it in `activations` so we can see churn / abuse.

---

## 9. Online layer (optional, bonus)

Only if the shop server occasionally has internet. Never required for a
normal request.

`license:check` (and the `/license` page) may POST to
`https://licenses.ourco.example/api/verify`:

```
POST { license_id, fingerprint, nonce, app_version }
200  { license_key: "<freshly signed, sliding not_after>", revoked: false }
```

Gains:

- **Remote kill switch** — mark a licence `revoked`; next check fails.
- **Duplication detection** — same `license_id` checking in from two
  different fingerprints → flag / auto-revoke.
- **Seat / terminal counting** across activations.
- **Sliding expiry** — `not_after = min(hard_expiry, now + window)` so a
  lapsed subscription stops even if the customer never re-copies a file.

Cache the last good signed response and honour it for `offline_days`
(e.g. 30) so an internet blip does not stop the shop.

---

## 10. Our side — the licence issuer

A small separate Laravel app (or a CLI-only package). Not part of this
repo's deployable.

**Schema**

- `licenses`: `id`, `license_id`, `customer_id`, `edition`,
  `features` (json), `max_terminals`, `issued_at`, `not_before`,
  `not_after`, `status` (`active` / `revoked`), `notes`.
- `activations`: `license_id`, `fingerprint_hashes` (json), `hostname`,
  `os`, `first_seen_at`, `last_seen_at`, `ip`, `revoked_at`.

**Keys**

- `sodium_crypto_sign_keypair()` once. Secret key: offline / vault, and
  in an env var on the issuer host only. Public key: committed into this
  app as an embedded constant.
- Keep a `key_id` in the payload (`"kid": 1`) so a future key rotation
  can ship a second embedded public key and re-sign gradually.

**Commands**

| Command | Does |
|---|---|
| `license:issue` | new `license.key` from customer + fingerprint + term |
| `license:extend` | re-issue with a later `not_after`, same everything else |
| `license:resign` | re-issue for a new fingerprint (hardware change) |
| `license:revoke` | set `status = revoked` (effective via the online layer) |
| `license:show` | print a decoded licence for support |

---

## 11. Hardening / anti-tamper

Ordered by effort vs. payoff:

1. **Redundant gate read in the sale Action** (§7). Cheap, done in the
   MVP.
2. **Embed the public key and product id in code**, not in
   `config/license.php`. The config file holds only paths, grace
   defaults, and the online URL.
3. **Signed core-file manifest.** Ship `storage/license/manifest.sig` — a
   signed list of `sha256(path)` for the licensing classes + a few sale
   paths. `license:check` recomputes and compares; a mismatch (someone
   edited the middleware) → restricted mode + logged. Defence in depth,
   not a wall.
4. **Encode the licensing classes** with IonCube or SourceGuardian (both
   support PHP 8.3 + Laravel). Encode at least `LicenseGate`,
   `Signature`, `MachineFingerprint`, `EnsureLicenseValid`, and the
   redundant check. A patched copy now has to reimplement compiled code.
5. **Ship encoded release archives** instead of giving customers a git
   remote. Biggest change to the delivery model
   ([DEPLOY_LOCAL.md](DEPLOY_LOCAL.md) §10 becomes "unzip the release"),
   biggest increase in bypass cost. Decide early — it is hard to
   retrofit.

Do not rely on obscurity alone, and keep every check **fail-safe for the
shop** (grace + restricted, never data loss).

---

## 12. Build vs. buy

**[Keygen](https://keygen.sh)** (hosted or self-hostable) already does
machine-fingerprint node-locking, expiry, and **Ed25519 offline licence
files** that verify with no network. Adopting it would replace §4, §9 and
§10 entirely; we would still write the Laravel middleware, the Windows
fingerprint, and the clock-rollback guard (§5–§7). Worth a half-day
evaluation before committing to build the issuer and the crypto plumbing
ourselves.

Other packages exist but most assume an always-online licence server,
which does not fit [DEPLOY_LOCAL.md](DEPLOY_LOCAL.md) §7.

---

## 13. Implementation plan

### MVP (~2–3 days, all offline, no issuer infra beyond a CLI)

1. `App\Licensing\Signature` — Ed25519 verify + embedded public key.
2. `App\Licensing\MachineFingerprint` — 4 Windows sources, normalise,
   hash, cache, fuzzy `≥ 3/4` match.
3. `App\Licensing\LicenseGate` — load `license.key`, verify, window
   check, fingerprint check, rollback check; status enum; 1h cache.
4. `license_state` migration + `App\Licensing\LicenseState` (DB row +
   encrypted `state.bin`).
5. `EnsureLicenseValid` middleware on `web` + `api`, with the allowlist
   and the per-status behaviour in §7.
6. `/license` Livewire page — shows the fingerprint blob, accepts a
   pasted key or an uploaded file, validates, activates.
7. Redundant gate read in the `App\Domain\Sales` create Action.
8. `config/license.php` (§15).
9. Artisan: `license:fingerprint` (customer), `license:check`
   (scheduled), `license:issue` + `license:show` (ours — can start life
   in this repo behind an env flag, then move out).
10. Register `license:check` hourly in `routes/console.php` /
    scheduler.
11. Tests (Pest): valid licence passes; expired → grace → restricted;
    wrong fingerprint → restricted; tampered payload → restricted;
    clock set back → restricted; allowlisted routes reachable in
    restricted mode.

### Later

- Online verify + kill switch + duplication detection (§9).
- Signed core-file manifest (§11.3).
- IonCube/SourceGuardian encoding (§11.4) and/or encoded release
  archives (§11.5).
- `max_terminals` enforcement against active terminal rows.
- Feature gating (`features[]` → hide loyalty / gift cards / multi-
  location when not licensed).
- Expiry notifications in the back office (30 / 14 / 7 / 1 day).

---

## 14. File checklist

| Path | Purpose |
|---|---|
| `app/Licensing/Signature.php` | Ed25519 verify, base64url, embedded public key |
| `app/Licensing/MachineFingerprint.php` | Windows identifier collection + hashing + match |
| `app/Licensing/LicenseGate.php` | orchestrates all checks, returns cached status |
| `app/Licensing/LicenseStatus.php` | enum: `Valid` / `ExpiringSoon` / `Grace` / `Restricted` |
| `app/Licensing/LicenseState.php` | DB row + encrypted `state.bin` (rollback data) |
| `app/Licensing/Exceptions/LicenseInvalid.php` | thrown on malformed / bad signature |
| `app/Http/Middleware/EnsureLicenseValid.php` | gate enforcement on web + api |
| `app/Livewire/License/Activate.php` (+ Blade) | activation / renewal UI |
| `app/Console/Commands/LicenseFingerprint.php` | prints the blob for the customer |
| `app/Console/Commands/LicenseCheck.php` | scheduled re-verify + state update |
| `app/Console/Commands/LicenseIssue.php` | **ours** — signs a `license.key` |
| `app/Console/Commands/LicenseShow.php` | decode + print a licence (support) |
| `config/license.php` | paths, grace defaults, online URL, thresholds |
| `database/migrations/*_create_license_state_table.php` | single-row state |
| `storage/app/license/` (gitignored) | `license.key`, `state.bin` |
| `tests/Feature/Licensing/*` | the scenarios in §13 |

Add `storage/app/license/` to `.gitignore`. The **public** key is
committed (in `Signature.php`); the **secret** key never is.

---

## 15. `config/license.php` reference

```php
return [
    // storage_path('app/license/license.key')
    'key_path'       => storage_path('app/license/license.key'),
    'state_path'     => storage_path('app/license/state.bin'),

    // fingerprint match tolerance (payload may override downward)
    'fp_min_match'   => 3,

    // wall-clock skew tolerated before "clock moved back" fires
    'clock_skew_hours' => 24,

    // banner lead time
    'warn_within_days' => 30,

    // how long a cached gate result is trusted
    'cache_ttl'      => 3600,

    // optional online layer; null = fully offline
    'verify_url'     => env('LICENSE_VERIFY_URL'),
    'offline_days'   => 30,

    // set true once the issuer moves to its own app
    'issuer_disabled' => env('LICENSE_ISSUER_DISABLED', true),
];
```

---

## 16. Open decisions

- **Delivery model:** keep `git pull` updates, or move to encoded release
  archives? (§11.5 — affects [DEPLOY_LOCAL.md](DEPLOY_LOCAL.md) §10.)
- **Build vs. buy:** evaluate Keygen before building the issuer (§12).
- **Hard lock vs. grace-only:** confirm that "sales paused, reports +
  shift close still work" is the accepted restricted behaviour (§7).
- **Encoding:** IonCube vs. SourceGuardian, and which classes.
- **Trial licences:** short `not_after` + `edition: "trial"`, or a
  separate flag?
- **Key rotation:** ship `kid` from day one (cheap) even if we only ever
  have one key.
