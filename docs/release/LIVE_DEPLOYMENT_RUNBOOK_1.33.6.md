# Live deployment runbook v1.33.6

## Scope and stop conditions

- Deploy only annotated tag `v1.33.6` / commit `9fb2b215d83495da71f23d6a56e72ef6f843a928`.
- This release has no migration and does not import or backfill Product data.
- Do not use `git pull`, `composer update`, npm build, manual Production patches, or `git reset`.
- Stop if the Production Git worktree is dirty, the tag SHA differs, Composer fails, or worker health cannot be verified.

## 1. Pre-deploy and backup

```bash
cd /home/dieuhoatudungcom/dieuhoatudung.com/public_html
git status --short
git fetch --prune --tags origin
git rev-parse v1.33.6^{commit}
git rev-parse HEAD
git describe --tags --always
cat VERSION
runuser -u dieuhoatudungcom -- php artisan about --no-ansi
runuser -u dieuhoatudungcom -- php artisan migrate:status --no-ansi
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
supervisorctl status
```

Require empty `git status` and verify the tag resolves to the release SHA.
Record the existing desired AI-worker state; restarting Supervisor must not
silently enable a previously disabled worker. Create a non-zero database backup
and record its absolute path, timestamp, size and SHA-256 before deployment.

## 2. Deploy exact tag

```bash
git checkout v1.33.6
git rev-parse HEAD
cat VERSION
runuser -u dieuhoatudungcom -- composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
runuser -u dieuhoatudungcom -- php artisan migrate --force
runuser -u dieuhoatudungcom -- php artisan migrate:status --no-ansi
runuser -u dieuhoatudungcom -- php artisan optimize:clear
runuser -u dieuhoatudungcom -- php artisan config:cache
runuser -u dieuhoatudungcom -- php artisan route:cache
runuser -u dieuhoatudungcom -- php artisan view:cache
supervisorctl reread
supervisorctl update
supervisorctl restart dieuhoa-worker_00
supervisorctl restart dieuhoa-worker_01
supervisorctl restart dieuhoa-ai-governed
supervisorctl status
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
```

Do not run npm on Live. Verify `public/build/manifest.json` is already present
from the certified release artifact. Require application/worker version `1.33.6`,
fresh heartbeat, queue `ai_governed`, and restoration of the exact pre-deploy AI
desired state.

## 3. Product Edit smoke test

Use one safe Product; do not edit a catalog-critical Product just for a smoke
test. In Admin → Product → Thông số kỹ thuật verify:

- **Công suất BTU** displays `technical_capacity_btu`, not the legacy `btu`;
- kW, HP and standard technical fields are editable;
- saving a changed technical field requires **Lý do ghi đè thông số kỹ thuật**;
- after save/reload, the value and override audit metadata persist;
- extended `specs_json` source evidence remains visible and unchanged.

Verify public `/san-pham?btu[]=18000` behavior remains based on
`marketing_capacity_btu`; do not use Product Edit to change the public BTU tier.

## 4. Final verification

```bash
git status --short
git rev-parse HEAD
git describe --tags --always
cat VERSION
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
```

Expected: clean worktree, SHA `9fb2b215d83495da71f23d6a56e72ef6f843a928`,
tag `v1.33.6`, version `1.33.6`, healthy Supervisor workers and the original AI
desired state.

## Rollback

If a critical gate fails before any manual Product edit, pause governed AI only
according to the recorded desired-state contract, then:

```bash
git checkout v1.33.5
runuser -u dieuhoatudungcom -- composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
runuser -u dieuhoatudungcom -- php artisan optimize:clear
runuser -u dieuhoatudungcom -- php artisan config:cache
runuser -u dieuhoatudungcom -- php artisan route:cache
runuser -u dieuhoatudungcom -- php artisan view:cache
supervisorctl restart dieuhoa-worker_00
supervisorctl restart dieuhoa-worker_01
supervisorctl restart dieuhoa-ai-governed
```

There is no schema rollback. Product manual override records are business audit
data; do not delete or rewrite them as a deployment rollback shortcut.
