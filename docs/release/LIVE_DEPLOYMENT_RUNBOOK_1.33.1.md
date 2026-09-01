# Live deployment runbook - v1.33.1

## 1. Preconditions and immutable evidence

Deploy only after local certification, pushed branch/tag and GitHub Release are verified. Do not alter Product #987, Job #29, Item #3446 or retry the 272 historical blocked items manually.

```bash
cd /home/dieuhoatudungcom/dieuhoatudung.com/public_html
git status --short
git rev-parse HEAD
git describe --tags --always
cat VERSION
runuser -u dieuhoatudungcom -- php artisan about --no-ansi
runuser -u dieuhoatudungcom -- php artisan migrate:status --no-ansi
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
supervisorctl status
ps aux | grep -E "ai:managed-worker|ai:managed-child-worker|queue:work" | grep -v grep
```

Record old SHA/version, AI desired/actual state, supervisor/child PIDs, worker version/build/hash, queue, DB and heartbeat. Require a clean Live worktree and drained governed queue. Stop on unexpected dirty files.

## 2. Backup

Use the server's existing credential-safe backup mechanism. Example only when the configured MySQL client can read credentials without exposing them:

```bash
backup_dir=/home/dieuhoatudungcom/backups
backup_file="$backup_dir/dieuhoatudung-pre-v1.33.1-$(date +%Y%m%d-%H%M%S).sql.gz"
mkdir -p "$backup_dir"
runuser -u dieuhoatudungcom -- sh -c 'mysqldump --single-transaction --quick --routines --triggers "$DB_DATABASE" | gzip -9' > "$backup_file"
test -s "$backup_file"
sha256sum "$backup_file"
ls -lh "$backup_file"
```

Prefer the hosting backup command if environment variables are not exported to this shell. Never print credentials. Record non-zero size and checksum before continuing.

## 3. Drain and maintenance

Preserve the exact pre-deploy desired state. Do not destroy queued jobs. With pending/processing/stuck all zero, enter maintenance mode:

```bash
runuser -u dieuhoatudungcom -- php artisan down --retry=60
```

## 4. Fetch and deploy exact release

```bash
git fetch origin --tags --prune
git rev-parse v1.33.1^{commit}
git checkout v1.33.1
git rev-parse HEAD
cat VERSION
```

Require HEAD equal to the certified release SHA and VERSION `1.33.1`.

## 5. Composer, migrations and caches

Do not run `composer update`, `migrate:fresh` or npm build on Live.

```bash
runuser -u dieuhoatudungcom -- composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
runuser -u dieuhoatudungcom -- php artisan migrate --force
runuser -u dieuhoatudungcom -- php artisan migrate:status --no-ansi
runuser -u dieuhoatudungcom -- php artisan optimize:clear
runuser -u dieuhoatudungcom -- php artisan config:cache
runuser -u dieuhoatudungcom -- php artisan route:cache
runuser -u dieuhoatudungcom -- php artisan view:cache
```

v1.33.1 has no new migration; require all existing migrations Ran and zero pending. `public/build` comes from the certified repository artifact convention; Node 14/npm 11 on Live is not used.

## 6. Restart workers and web runtime

Restart the known PHP/OPcache handler through the hosting platform; do not guess its service name. Then:

```bash
supervisorctl reread
supervisorctl update
supervisorctl restart dieuhoa-worker:*
supervisorctl restart dieuhoa-ai-governed
supervisorctl status
```

If pre-deploy desired state was DISABLED, keep it DISABLED; process restart must not implicitly enable AI.

## 7. Runtime and integrity verification

```bash
runuser -u dieuhoatudungcom -- php artisan about --no-ansi
runuser -u dieuhoatudungcom -- php artisan ai:product-integrity-audit --json
runuser -u dieuhoatudungcom -- php artisan ai:managed-health-check
sleep 5
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
runuser -u dieuhoatudungcom -- php artisan schedule:list
```

Require app/worker `1.33.1`, release SHA parity, matching worker hash, production environment/DB, `ai_governed`, fresh heartbeat, `UP_TO_DATE`, cross-process health PASS without provider/Product mutation and integrity UNKNOWN=0. Scheduler/watchdog are mandatory production gates.

## 8. Product #987 and controlled provider certification

Before Generate, inspect the canonical resolver read-only. Require current `AVAILABLE`, current item/draft null, history Item #3446 `BLOCKED / DUPLICATE_IN_PROGRESS`, and next action Generate. In admin, current status must be "Sẵn sàng tạo nội dung" while Job #29 remains history.

Generate Product #987 once with the authorized provider. Capture new job/item/request/provider/model/tokens/draft/final state. It must create a new operation and must not be poisoned by historical `DUPLICATE_IN_PROGRESS`. Do not Apply. Recheck Job #29, Item #3446 and the 272 historical items unchanged.

Only after #987 passes, run controlled 3-Product and then 10-Product preflight/generation. Do not jump to all historical items or bulk retry.

## 9. Numeric and HTTP/browser smoke

Require Product #1316 HTTP 200 and `24,225.2 / 28,660.8 BTU`; Product #1238 decimal price PASS; Product #1243 no-price fallback PASS. Smoke `/`, `/san-pham`, representative Product detail, `/admin/login` and Product #987 admin with zero relevant HTTP 500, console, page, network or Livewire errors.

## 10. Restore state and final verification

Restore the exact pre-deploy AI desired state, then:

```bash
runuser -u dieuhoatudungcom -- php artisan up
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
git status --short
git rev-parse HEAD
git describe --tags --always
cat VERSION
```

Capture post-deploy DB counts and explain only controlled-test deltas. Product/catalog identity/count must not change unexpectedly.

## 11. Rollback to v1.33.0

If a mandatory gate fails, stop/drain safely, preserve desired state and enter maintenance mode. Restore exact tag:

```bash
git checkout v1.33.0
runuser -u dieuhoatudungcom -- composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
runuser -u dieuhoatudungcom -- php artisan optimize:clear
runuser -u dieuhoatudungcom -- php artisan config:cache
runuser -u dieuhoatudungcom -- php artisan route:cache
runuser -u dieuhoatudungcom -- php artisan view:cache
supervisorctl restart dieuhoa-worker:*
supervisorctl restart dieuhoa-ai-governed
supervisorctl status
runuser -u dieuhoatudungcom -- php artisan about --no-ansi
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
runuser -u dieuhoatudungcom -- php artisan up
```

No destructive migration rollback is required. Use the verified DB backup only if a real database mutation failure requires it. Prove app/worker version/build/hash parity, scheduler, desired state and HTTP smoke after rollback.
