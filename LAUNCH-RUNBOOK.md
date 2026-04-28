# tamabox Production Launch Runbook

**Target**: `tamabox.emomie.com` (Lolipop ハイスピードプラン, PHP 8.1+, MySQL 8.0+)
**Last updated**: Phase 4 plan 04-03 (Phase 4 launch deliverable)
**Operator**: solo developer (the user)

This runbook implements Phase 4 D-32 / D-33 / D-34 / D-37 in concrete SSH command form. Follow steps top-to-bottom on initial deploy. Subsequent deploys repeat only Step 3 (`git push`) + ad-hoc Step 4 (manual migration when new ones ship).

---

## Prerequisites

- [ ] Lolipop SSH credentials in 1Password / secure vault
- [ ] Lolipop control panel: PHP 8.1 (or 8.3) selected for `tamabox.emomie.com` domain
- [ ] Lolipop MySQL database created + tamabox MySQL user granted ALL on database
- [ ] DNS for `tamabox.emomie.com` CNAME → Lolipop wildcard hostname (set up before this runbook)
- [ ] Local VPS: ES256 keypair generated (see Step 2)
- [ ] Local VPS: production `config/.env` file drafted with all required vars (see `config/.env.example` Phase 4 checklist)

---

## Step 1 — Initial Lolipop bare-repo setup (one-time)

SSH into Lolipop:

```bash
ssh -p 2222 <lolipop-account>@<lolipop-ssh-host>
```

Create the bare git repo and the working directory:

```bash
mkdir -p ~/repo
git init --bare ~/repo/tamabox.git
mkdir -p ~/web/tamabox.emomie.com
```

Install Composer (one-shot, downloads composer.phar to home):

```bash
cd ~
curl -sS https://getcomposer.org/installer | /usr/local/php/8.1/bin/php
# This places composer.phar in $HOME — referenced by post-receive hook.
```

(If Lolipop's PHP path differs from `/usr/local/php/8.1/bin/php`, identify the correct path via `ls /usr/local/php/` and adjust here AND in Step 3 hook script. RESEARCH Pitfall 6.)

---

## Step 2 — Place secrets via SSH (one-time, never re-deployed via git)

### 2a. Generate ES256 keypair on VPS (local dev environment)

The filenames `private.key` and `public.key` are NOT optional — `KeyManager.php` defaults to those exact paths under `config/keys/`. Do not rename to `.pem` etc.

```bash
mkdir -p ~/secrets/tamabox-prod && chmod 700 ~/secrets/tamabox-prod
cd ~/secrets/tamabox-prod
openssl ecparam -name prime256v1 -genkey -noout -out private.key
openssl ec -in private.key -pubout -out public.key
chmod 600 private.key
chmod 644 public.key
```

### 2b. Transfer to Lolipop

On Lolipop SSH first (creates target dir before scp):

```bash
mkdir -p ~/web/tamabox.emomie.com/config/keys
```

Back on the VPS:

```bash
scp -P 2222 ~/secrets/tamabox-prod/private.key <lolipop-account>@<lolipop-ssh-host>:~/web/tamabox.emomie.com/config/keys/private.key
scp -P 2222 ~/secrets/tamabox-prod/public.key  <lolipop-account>@<lolipop-ssh-host>:~/web/tamabox.emomie.com/config/keys/public.key
```

Re-assert permissions on Lolipop SSH (scp may not preserve mode):

```bash
chmod 600 ~/web/tamabox.emomie.com/config/keys/private.key
chmod 644 ~/web/tamabox.emomie.com/config/keys/public.key
ls -la ~/web/tamabox.emomie.com/config/keys/
```

Verify JWKS picks them up after Step 3 deploy completes:

```bash
curl -s https://tamabox.emomie.com/oauth/jwks.json
# Expect: {"keys":[{"kty":"EC","crv":"P-256","kid":"ssr-box-key-1","use":"sig","alg":"ES256",...}]}
# Failure mode: {"error":"key_not_available"} → KeyManager couldn't read config/keys/*.key — re-check filenames + perms
```

### 2c. Place production .env

Author `~/web/tamabox.emomie.com/config/.env` with the FULL set of vars from `config/.env.example` (Phase 4 production checklist section). Critical values:

```bash
export APP_NAME="tamabox"
export DEBUG="false"                           # INFRA-06
export APP_DEFAULT_TIMEZONE="Asia/Tokyo"
export SECURITY_SALT="<64-char hex>"           # openssl rand -hex 32
export SERVER_SECRET="<64-char hex>"           # openssl rand -hex 32 (DIFFERENT from SECURITY_SALT)
export DATABASE_URL="mysql://<user>:<pass>@localhost/<dbname>?encoding=utf8mb4&timezone=UTC&cacheMetadata=true"
export OAUTH_KID="ssr-box-key-1"               # MUST match the kid claim used by ClientJwtService
export TOKEN_ENC_KEY="<64-char hex>"           # openssl rand -hex 32 (DIFFERENT from above two)
export BLUESKY_AUTH_SERVER="https://bsky.social"
export BLUESKY_ISSUER="https://bsky.social"
# (other Phase 2 OAuth vars per config/.env.example)
```

```bash
chmod 600 ~/web/tamabox.emomie.com/config/.env
```

(Per D-33, this file is placed ONCE and not touched by future deploys. The `.gitignore` excludes `config/.env` from any push.)

### 2d. Place `config/app_local.php`

Lolipop SSH:

```bash
cp ~/web/tamabox.emomie.com/config/app_local.example.php ~/web/tamabox.emomie.com/config/app_local.php
```

(After the first git push checks out the working tree — do this AFTER Step 3.)

---

## Step 3 — Push main + observe post-receive hook

### 3a. Install the post-receive hook on Lolipop

Create `~/repo/tamabox.git/hooks/post-receive` with the following contents (Lolipop SSH):

```bash
#!/bin/sh
# tamabox post-receive deploy hook (Phase 4 D-32, RESEARCH Pitfall 12).
set -e

WORKING_DIR="$HOME/web/tamabox.emomie.com"
GIT_DIR="$HOME/repo/tamabox.git"
PHP_BIN="/usr/local/php/8.1/bin/php"            # Adjust if Lolipop assigned a different version path
COMPOSER_PHAR="$HOME/composer.phar"

mkdir -p "$WORKING_DIR"

# Checkout the pushed main branch into web tree.
git --git-dir="$GIT_DIR" --work-tree="$WORKING_DIR" checkout -f main

# Install PHP dependencies excluding dev (D-32, D-36 — DebugKit excluded here).
cd "$WORKING_DIR"
"$PHP_BIN" "$COMPOSER_PHAR" install --no-dev --optimize-autoloader --no-interaction

# Clear ORM/route caches (CakePHP deployment best practice; RESEARCH Pitfall 7).
"$PHP_BIN" bin/cake.php cache clear_all || true

echo "[tamabox] post-receive deploy complete: $(date)"
```

```bash
chmod +x ~/repo/tamabox.git/hooks/post-receive
```

### 3b. Add Lolipop git remote on local VPS

```bash
cd ~/projects/tamabox
git remote add lolipop ssh://<lolipop-account>@<lolipop-ssh-host>:2222/~/repo/tamabox.git
```

### 3c. Push main

```bash
git push lolipop main
```

Observe the SSH session output for the hook execution. The deploy is successful when you see `[tamabox] post-receive deploy complete: <date>`. The hook runs `composer install --no-dev --optimize-autoloader --no-interaction` (D-32, INFRA-06): the `--no-dev` output should explicitly NOT mention `cakephp/debug_kit` — that is the entire mechanism that keeps DebugKit out of production vendor/.

---

## Step 4 — Manual migration + cache clear (D-34)

`bin/cake migrations migrate` is intentionally NOT in the hook — failed migrations would brick the deploy mid-checkout. Run them by hand on Lolipop SSH:

```bash
cd ~/web/tamabox.emomie.com
/usr/local/php/8.1/bin/php bin/cake.php migrations migrate
/usr/local/php/8.1/bin/php bin/cake.php cache clear_all
```

Phase 4 introduces 1 new migration: `AddReporterMessageUniqueToReports` (Plan 04-02 Task 1). Verify it shows `up`:

```bash
/usr/local/php/8.1/bin/php bin/cake.php migrations status
# Expect: every Phase 1+3+4 migration in 'up' state
```

If a migration fails, do NOT continue to Step 5. Diagnose via `tmp/logs/error.log`, fix, re-run. (Phase 1 INFRA-04 confirmed all baseline migrations succeed against Lolipop MySQL 8.0.)

---

## Step 5 — Bluesky AS client-metadata.json registration

Plan 02-03 ships `https://tamabox.emomie.com/oauth/client-metadata.json`. Verify it returns valid JSON from the production URL:

```bash
curl -s https://tamabox.emomie.com/oauth/client-metadata.json | head -50
```

(Expect: `application/json` content with `client_id`, `client_uri`, `redirect_uris`, `jwks_uri`, etc.)

If the JSON validates, no further action is needed for Bluesky AS — atproto.com discovers the metadata on the first OAuth handshake (no manual registration step exists for Bluesky's public AS).

---

## Step 6 — Manual smoke test

Follow `MANUAL-SMOKE-CHECKLIST.md` end-to-end (12 items: 9 Phase 4 flows + 3 Phase 2/3 carry-overs). All boxes must check.

If any item fails, capture the failure in `.planning/phases/04-moderation-production-launch/VERIFICATION.md` (the verify-phase deliverable) and decide gap-closure approach.

---

## Verification gates (before declaring "launch complete")

After Step 6:

- [ ] `curl -sI https://tamabox.emomie.com/` returns `200` or `302` (LP renders or redirects to Bluesky).
- [ ] `curl -s https://tamabox.emomie.com/oauth/jwks.json | head` returns valid JWKS JSON.
- [ ] `curl -s https://tamabox.emomie.com/oauth/client-metadata.json | head` returns valid client metadata JSON.
- [ ] **DebugKit absent verification** (RESEARCH Pitfall 11): on Lolipop SSH:
  ```bash
  ls ~/web/tamabox.emomie.com/vendor/cakephp/debug_kit 2>/dev/null && echo "ALERT: DebugKit installed in production" || echo "OK: DebugKit not installed"
  ```
  Expect `OK: DebugKit not installed`. If `ALERT`, the `--no-dev` flag was missing from composer install — re-run Step 3 hook manually.
- [ ] **Production debug=false verification** (INFRA-06): visit `https://tamabox.emomie.com/somethinginvalid404` and confirm the response is the production error page (no stack trace, no DebugKit panel). If a stack trace appears, debug=true is leaking — check `config/.env` DEBUG value + `bin/cake cache clear_all`.
- [ ] **TLS verification**: `curl -vI https://tamabox.emomie.com/ 2>&1 | grep -i "subject\|issuer"` shows valid CN matching the domain. (Lolipop provides the cert; this is just a smoke check.)

---

## Rollback procedure

If Step 6 surfaces a launch-blocking bug:

1. **Quick rollback (no schema change yet)**:
   ```bash
   ssh lolipop
   cd ~/web/tamabox.emomie.com
   git --git-dir=$HOME/repo/tamabox.git --work-tree=$PWD checkout -f <previous-commit-sha>
   /usr/local/php/8.1/bin/php $HOME/composer.phar install --no-dev --optimize-autoloader --no-interaction
   /usr/local/php/8.1/bin/php bin/cake.php cache clear_all
   ```

2. **Schema rollback (if Phase 4 migration causes issue)**:
   ```bash
   /usr/local/php/8.1/bin/php bin/cake.php migrations rollback
   /usr/local/php/8.1/bin/php bin/cake.php cache clear_all
   ```

3. **Force-redeploy after fix on local VPS**:
   ```bash
   git push lolipop main --force-with-lease   # safe-force, requires up-to-date local
   # OR if force-with-lease is unsupported:
   git push lolipop main -f
   ```

---

## Lolipop-specific quirks (collected from research + first-deploy discoveries)

- **PHP binary path** (RESEARCH Pitfall 6): default SSH `php` may point to PHP 5.6/7.4. Always use the explicit path `/usr/local/php/8.1/bin/php` (or `/usr/local/php/8.3/bin/php` if your domain is on the newer PHP). Verify via `ls /usr/local/php/`.
- **mod_rewrite**: enabled on standard Lolipop plan; `.htaccess` rewrite to `webroot/` works out of the box (Phase 1 INFRA-05 verified).
- **session.save_path**: Lolipop default is `/tmp` (or `tmp/sessions/` per CakePHP defaults). PHP file-session is fine for MVP (PROJECT.md Out of Scope: DB session storage).
- **cron jobs**: forbidden on Lolipop standard plan. Token-refresh-on-cron is therefore impossible (REV-03 redundancy: token refresh is also descoped from Phase 4).
- **HOME path drift** (RESEARCH Pitfall 12): Lolipop user homes can be `/home/users/0/<acct>/` (legacy) or `/home/users/2/<acct>/` (newer). Use `$HOME` in scripts, never hardcode.
- **Composer phar location**: by convention place at `~/composer.phar` (downloaded once in Step 1). The post-receive hook references `$HOME/composer.phar`.
- **First-deploy file ownership**: tmp/ and logs/ directories must be writable by the Apache process (typically Lolipop user is the same as SSH user, so default `chmod 755 tmp logs` works). If 500 errors appear in early deploy, verify `chmod -R 755 ~/web/tamabox.emomie.com/tmp ~/web/tamabox.emomie.com/logs`.

---

## Out-of-scope reminders (defer to post-MVP)

- GitHub Actions CI → Lolipop pipeline (deferred — git deploy direct is MVP)
- Synthetic uptime monitor (deferred — manual periodic check at MVP)
- `bin/cake smoke` automated walkthrough (Phase 4 manual only — D-35)
- Token refresh integration (REV-03 — not needed for MVP, no live PDS calls outside login)
- Admin web UI for reports (v2 — DB-direct SQL is MVP review mode)

---

*Runbook authored from .planning/phases/04-moderation-production-launch/04-CONTEXT.md D-32..D-37 and 04-RESEARCH.md §6 + Pitfalls 6/7/11/12. First exercised: <date of first deploy — fill in during launch>.*
