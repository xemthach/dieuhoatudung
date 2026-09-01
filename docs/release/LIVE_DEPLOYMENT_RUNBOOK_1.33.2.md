# Live deployment runbook - v1.33.2

Deploy only the exact certified annotated tag. Never patch `/home/dieuhoatudungcom/dieuhoatudung.com/public_html` manually and never alter historical Job #31.

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
```

Stop if the Live worktree is dirty or governed queue has unexpected pending/processing/stuck work. Record the current desired state, worker PID/build/hash, database, queue and heartbeat.

## Backup and safe pause

Use the hosting credential-safe backup facility. The backup must be non-empty and recorded with path, timestamp, size and SHA-256 checksum. Do not print DB credentials.

When the governed queue is drained, preserve the prior desired state and enter maintenance:

```bash
runuser -u dieuhoatudungcom -- php artisan down --retry=60
```

## Exact release deployment

```bash
git fetch origin --tags --prune
git rev-parse v1.33.2^{commit}
git checkout v1.33.2
git rev-parse HEAD
cat VERSION
runuser -u dieuhoatudungcom -- composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
runuser -u dieuhoatudungcom -- php artisan migrate --force
runuser -u dieuhoatudungcom -- php artisan migrate:status --no-ansi
runuser -u dieuhoatudungcom -- php artisan optimize:clear
runuser -u dieuhoatudungcom -- php artisan config:cache
runuser -u dieuhoatudungcom -- php artisan route:cache
runuser -u dieuhoatudungcom -- php artisan view:cache
```

Require checkout SHA equal the annotated tag and `VERSION=1.33.2`. v1.33.2 has no migration; all migrations must still be Ran. Do not run npm build on Live because Node 14/npm 11 is not certified; verify `public/build/manifest.json` from the release artifact convention.

## Restart and parity

```bash
supervisorctl reread
supervisorctl update
supervisorctl restart dieuhoa-worker_00
supervisorctl restart dieuhoa-worker_01
supervisorctl restart dieuhoa-ai-governed
supervisorctl status
runuser -u dieuhoatudungcom -- php artisan ai:product-integrity-audit --json
runuser -u dieuhoatudungcom -- php artisan ai:managed-health-check
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
```

Require integrity `UNKNOWN=0`, health provider call/product mutation false and cross-process true. Require app and worker version/build/hash parity, `ai_governed`, fresh heartbeat and zero pending/processing/stuck. Restore precisely the pre-deploy desired AI state; restarting must not enable it implicitly.

## Historical evidence and controlled acceptance

Read Job #31 and require unchanged historical state: 276 old child rows remain, all historical evidence retained. Do not retry it.

With an explicitly selected safe Product, run Bulk Generate in order:

1. One Product: new parent `config_json.operation_generation` must be a non-empty UUID; child must not false-block as `DUPLICATE_IN_PROGRESS` before provider.
2. Three Products only if step 1 passes: three child operations, one parent identity, provider/draft evidence and truthful terminal state.
3. Ten Products only if step 2 passes: ten children, zero false historical collision, correct parent reconciliation.

Stop immediately on any failure; do not run the 276-product selection. Verify a genuine active operation still blocks a second operation. Verify Product #987 stays current AVAILABLE while Job #29/Item #3446 remain history. Smoke Product #1316, #1238 and #1243 for the prior numeric-formatting contract.

## Final state and rollback

```bash
runuser -u dieuhoatudungcom -- php artisan up
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
git status --short
git rev-parse HEAD
git describe --tags --always
cat VERSION
```

If a mandatory gate fails: keep maintenance, preserve desired state, checkout `v1.33.1`, run `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`, rebuild Laravel caches, restart all three Supervisor workers, verify version/build/hash and then restore desired state. Use the verified backup only for a proven database mutation failure.
