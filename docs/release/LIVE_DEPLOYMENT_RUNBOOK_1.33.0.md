# Live deployment runbook - v1.33.0

## 1. Preconditions

Use this runbook only after tag `v1.33.0` and its GitHub Release are verified. Do not copy individual local files to Live.

```bash
cd /home/dieuhoatudungcom/dieuhoatudung.com/public_html

git status --short
git rev-parse HEAD
git describe --tags --always
runuser -u dieuhoatudungcom -- php artisan about
runuser -u dieuhoatudungcom -- php artisan migrate:status
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
runuser -u dieuhoatudungcom -- php artisan schedule:list
supervisorctl status
```

Require a verified non-zero database backup, the expected production database, queue `ai_governed`, pending/processing/stuck all zero, and a recorded AI desired state. Stop if the Live worktree is unexpectedly dirty.

## 2. Enter maintenance mode and checkout

```bash
runuser -u dieuhoatudungcom -- php artisan down --retry=60
git fetch origin --tags --prune
git checkout v1.33.0
git rev-parse HEAD
cat VERSION
```

Require `VERSION` = `1.33.0` and record the release SHA.

## 3. Install, migrate, and cache

```bash
runuser -u dieuhoatudungcom -- composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
runuser -u dieuhoatudungcom -- php artisan migrate:status
runuser -u dieuhoatudungcom -- php artisan migrate --force
runuser -u dieuhoatudungcom -- php artisan migrate:status
runuser -u dieuhoatudungcom -- php artisan optimize:clear
runuser -u dieuhoatudungcom -- php artisan config:cache
runuser -u dieuhoatudungcom -- php artisan route:cache
runuser -u dieuhoatudungcom -- php artisan view:cache
```

Require migration `2026_08_31_000001_add_ai_product_lifecycle_integrity_columns` to be applied and zero pending migrations. Never run `migrate:fresh`.

## 4. Restart web runtime and workers

Restart the known PHP handler/OPcache through the hosting platform. Do not guess a service name.

```bash
supervisorctl reread
supervisorctl update
supervisorctl restart dieuhoa-ai-governed
supervisorctl restart dieuhoa-worker:*
supervisorctl status
```

## 5. Runtime verification

```bash
runuser -u dieuhoatudungcom -- php artisan about
runuser -u dieuhoatudungcom -- php artisan migrate:status
runuser -u dieuhoatudungcom -- php artisan ai:product-integrity-audit --json
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
runuser -u dieuhoatudungcom -- php artisan ai:managed-health-check
sleep 5
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
runuser -u dieuhoatudungcom -- php artisan schedule:list
```

Require web/worker version `1.33.0`, matching build/hash, deployment `UP_TO_DATE`, fresh heartbeat, queue `ai_governed`, pending/processing/stuck zero, cross-process self-test PASS without provider/Product mutation, scheduler/watchdog healthy, and no unknown integrity violation.

## 6. Application smoke

Without calling the real provider:

1. Open home, Product listing, and representative Product detail pages, including a decimal/range BTU Product and a no-price Product; require HTTP 200 and no `number_format` exception.
2. Confirm admin login, Product list, Product Edit, AI panel, draft preview, and AI job detail load without HTTP 500, Livewire, console, or network errors.
3. Confirm terminal AI history does not block Generate, actionable drafts retain correct actions, hard blockers remain fail-closed, and opening a page creates no job.
4. Do not retry historical jobs or run a provider smoke call unless separately authorized.

## 7. Restore traffic

Restore the exact pre-deploy AI desired state, then:

```bash
runuser -u dieuhoatudungcom -- php artisan up
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
```

## 8. Rollback

If a mandatory gate fails, keep maintenance mode active and return to `v1.32.2`:

```bash
git checkout v1.32.2
runuser -u dieuhoatudungcom -- composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
runuser -u dieuhoatudungcom -- php artisan optimize:clear
runuser -u dieuhoatudungcom -- php artisan config:cache
runuser -u dieuhoatudungcom -- php artisan route:cache
runuser -u dieuhoatudungcom -- php artisan view:cache
supervisorctl restart dieuhoa-ai-governed
supervisorctl restart dieuhoa-worker:*
supervisorctl status
runuser -u dieuhoatudungcom -- php artisan about
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
runuser -u dieuhoatudungcom -- php artisan up
```

Do not roll back the additive migration destructively and do not delete AI/Product history.

## 9. Deployment evidence

Record release SHA, rollback tag, backup verification, web/worker version and hash, desired state before/after, migration state, integrity audit, queue health, self-test, scheduler/watchdog, Product detail smoke, admin AI smoke, and final PASS/ROLLED BACK/BLOCKED result.
