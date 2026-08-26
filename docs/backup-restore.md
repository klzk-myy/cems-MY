# Backup & Restore Runbook

Operational runbook for restoring CEMS-MY from backup, maintenance-mode
procedure, and encryption credential (`APP_KEY` / `APP_ENCRYPTION_SALT`)
safeguards and rotation.

> **Read this fully before touching a production database.** A restore
> overwrites the live database.

---

## 1. Preconditions (before you restore)

1. **Enter maintenance mode** so no writes land while you work:

   ```bash
   php artisan down --secret=restore-<random-token> --retry=60
   ```

   Note the secret URL — only requests to `/restore-<random-token>` can pass
   through. See [Section 4](#4-maintenance-mode-procedure) for details.

2. **Identify the backup to restore from** and confirm it is healthy:

   ```bash
   php artisan backup:list
   php artisan backup:verify <backup-log-id>
   ```

   Only backups whose status is `completed` or `verified` can be restored.
   Restoring from a `failed` / `verification_failed` backup is refused by the
   command by design.

3. **Back up the current state first**, even if it is broken — the restore is
   destructive and there is no undo:

   ```bash
   php artisan backup:run --type=full
   ```

4. **Record environment credentials.** Verify `.env` still holds the same
   values as when the backup was taken:
   - `APP_KEY` — Laravel application key (also seeds PII key derivation).
   - `APP_ENCRYPTION_SALT` — 64-char hex salt for customer PII encryption.

   If either differs from the values at backup time, customer PII restored
   from the dump will be **undecryptable** until you rotate credentials — see
   [Section 5](#5-app_key--app_encryption_salt-guidance).

---

## 2. Restore procedure

The restore command wraps `App\Services\System\BackupService`. It extracts the
backup ZIP (with zip-slip protection), locates the SQL dump inside, and pipes
it into the MySQL database configured in `.env`.

```bash
# Interactive (safer): prompts you to type the exact backup name to confirm
php artisan backup:restore <backup-log-id>

# Scripted / emergency: skips the confirmation prompt
php artisan backup:restore <backup-log-id> --force

# Skip the automatic integrity re-verification before restoring (not recommended)
php artisan backup:restore <backup-log-id> --force --skip-verify
```

Behaviour notes:

| Situation | Command behaviour |
|---|---|
| Backup log ID does not exist | Fails immediately |
| Backup status is not completed/verified | Refused |
| Confirmation name mismatch | Refused |
| Verification fails pre-restore (unless `--skip-verify`) | Refused |

The command prints a details table (ID, name, type, created date, size,
verified flag) before asking for confirmation.

---

## 3. Post-restore checks (mandatory)

Run these in order after every restore:

1. **Clear and warm caches** — cached config may reference stale credentials
   or URLs:

   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan route:clear
   php artisan view:clear
   # then rebuild for production:
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```

2. **Restart queue workers and Horizon** so workers pick up fresh code/config
   and drop any in-memory state pointing at pre-restore data:

   ```bash
   php artisan queue:restart
   php artisan horizon:terminate        # supervisor restarts Horizon
   # or, if Horizon does not auto-restart:
   # systemctl restart horizon   (adjust to your process manager)
   ```

3. **Verify the audit hash chain** — proves the restored audit trail is intact
   and untampered:

   ```bash
   php artisan audit:verify
   ```

   Any chain-break reported here means the restored audit data conflicts with
   later local rows; investigate before resuming operations.

4. **Sanity-check accounting integrity** — generate the trial balance and
   confirm debits equal credits and balances look plausible for the restore
   point:

   ```bash
   php artisan report:trial-balance
   ```

   Spot-check SQL equivalents if needed:

   ```sql
   SELECT SUM(CASE WHEN entry_type = 'debit' THEN amount ELSE -amount END)
   FROM journal_lines;  -- must return ~0
   ```

5. **Application smoke test** — log in via the maintenance bypass URL, open
   the dashboard, search one known customer (exercises blind-index +
   decryption), and view one transaction.

6. **Leave maintenance mode**:

   ```bash
   php artisan up
   ```

---

## 4. Maintenance-mode procedure

Take the application down without locking yourself out using a secret bypass:

```bash
# Bring the app down; only visitors hitting /<secret> get through
php artisan down --secret=ops-2026-a7f3 --retry=60 --allow=*.<internal-domain>

# Rendered page
# Laravel automatically serves resources/views/errors/503.blade.php during
# maintenance mode. Keep that view free of any promised recovery time.

# Bring the app back up
php artisan up
```

Notes:

- `--retry=60` sends `Retry-After: 60` so browsers/monitors back off sensibly;
  it does not promise the app will actually return within that time.
- The bypass secret grants full access — treat it like a password and use a
  random value per incident.
- While down, stop Horizon manually if jobs must not run mid-maintenance:
  `php artisan horizon:pause` … resume with `horizon:continue`.

---

## 5. APP_KEY / APP_ENCRYPTION_SALT guidance

Where these live and why they matter:

| Credential | `.env` variable | Used for |
|---|---|---|
| Application key | `APP_KEY` | Laravel crypto (sessions, cookies) **and** PBKDF2 seed for PII key derivation |
| Encryption salt | `APP_ENCRYPTION_SALT` | Second input to the PBKDF2 derivation for all customer PII |

Derived key = `PBKDF2-SHA256(APP_KEY, APP_ENCRYPTION_SALT, ENCRYPTION_ITERATIONS)`
(AES-256-CBC, see `App\Services\System\EncryptionService`).

**Losing either value destroys decryptability of:**

- `customers.id_number_encrypted` (+ blind index `id_number_hash`)
- `customers.address`, `customers.phone` (+ `phone_hash`),
  `customers.employer_address`
- `customer_relations.id_number_encrypted`

There is **no recovery path** once the original values are gone. Therefore:

1. Store `.env` (or at minimum `APP_KEY` + `APP_ENCRYPTION_SALT`) in a secure
   secrets store (vault, encrypted offline copy) — never in git, never only on
   the app server.
2. Include both values in the pre-restore checklist (Section 1.4).
3. If a backup must be restored onto infrastructure where these values
   changed, run the rotation tool below **after** the restore.

### Key/salt rotation procedure (order matters)

When credentials changed (or must change), rotate the encrypted columns with:

```bash
php artisan customers:re-encrypt --force
```

**Prefer the interactive prompts.** When run from a terminal, omitting
`--old-salt` / `--old-key` makes the command ask for each value with a hidden
response. This keeps the previous credentials out of the process list and
shell history — never paste secrets onto a command line on shared systems.

The CLI options (`--old-salt=… --old-key="…"`) remain available for
non-interactive automation (piped stdin, CI jobs); if either option is
omitted without a TTY, the command fails fast instead of prompting.

The command chunks over customers and relations, decrypts each row with the
old credentials, verifies an encrypt→decrypt roundtrip under the new
credentials, recomputes the blind-index hashes (`id_number_hash`, `phone_hash`),
and reports per-row failures instead of skipping them silently. Rows that fail
are left untouched so they can be fixed and the command re-run.

Full rotation order:

1. `php artisan down --secret=<token>` — take the app down.
2. Take a fresh backup (`php artisan backup:run --type=database`) and set the
   **new** `APP_KEY` / `APP_ENCRYPTION_SALT` in `.env`.
3. `php artisan config:clear` (the service reads config at instantiation).
4. Run `customers:re-encrypt --force` (prompts will request the previous
   credentials; use `--old-salt=… --old-key="…"` only in non-interactive
   automation).
5. **Verify sample decryptions**: open several customers in the UI (search by
   ID number to prove the new blind index works); optionally compare decrypted
   ID numbers against the pre-rotation backup.
6. Restart queues/Horizon (Section 3.2), run `audit:verify` (3.3).
7. `php artisan up`.

If the old credentials are lost and rotation is impossible, the affected PII
must be treated as destroyed: customers need re-onboarding (KYC documents are
kept separately and can repopulate identity data).

---

## 6. Related scheduled tasks

These run via the scheduler (see `bootstrap/app.php`):

| Task | Cadence |
|---|---|
| `backup:run --type=database` | Daily 02:00 |
| `backup:run --type=full` | Sunday 03:00 |
| `backup:run --type=full --disk=s3` | Monthly (BNM 7-year archive) |
| `backup:verify --all` | Daily 05:00 |
| `backup:clean --force` | Daily 06:00 |
| `backup:monitor --notify` | Daily 07:00 |
| `queue:prune-failed --hours=168` | Weekly |
