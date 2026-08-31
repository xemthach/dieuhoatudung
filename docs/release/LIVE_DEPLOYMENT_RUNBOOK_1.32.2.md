# Live deployment runbook — v1.32.2

## 1. Preconditions

This runbook assumes tag `v1.32.2` has already been reviewed, committed, tagged and pushed. Do not deploy an uncommitted local worktree or copy individual files to Live.

Create and verify a non-zero database backup. Record the current release as `ROLLBACK_TAG` and preserve the AI worker desired state. Do not deploy while an AI Product operation is processing.

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

Before continuing, require:

- the expected production database;
- queue connection `database` and queue `ai_governed`;
- pending, processing and stuck AI Product counts all zero;
- no unresolved deployment/version mismatch;
- a recorded `DESIRED_STATE_BEFORE_DEPLOY` value.

If `git status --short` is not empty, stop and review the Live-only changes. Do not discard them blindly.

## 2. Enter maintenance mode

```bash
runuser -u dieuhoatudungcom -- php artisan down --retry=60
```

If this command fails, stop. Do not continue with a partially controlled in-place deployment.

## 3. Checkout the release

```bash
git fetch origin --tags --prune
git checkout v1.32.2
git rev-parse HEAD
cat VERSION
```

Require `VERSION` to print `1.32.2`. Record the checked-out commit for deployment evidence.

## 4. Install and rebuild

Release `v1.32.2` has no new migration, but migration status must still be verified.

```bash
runuser -u dieuhoatudungcom -- composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
runuser -u dieuhoatudungcom -- php artisan migrate:status
runuser -u dieuhoatudungcom -- php artisan optimize:clear
runuser -u dieuhoatudungcom -- php artisan config:cache
runuser -u dieuhoatudungcom -- php artisan route:cache
runuser -u dieuhoatudungcom -- php artisan view:cache
```

Do not run `migrate:fresh`. Run `php artisan migrate --force` only if `migrate:status` shows a reviewed pending migration from the deployed tag; none is expected for `v1.32.2`.

The reviewed tag contains production assets. Do not rebuild assets on Live unless the host's deployment policy explicitly requires it.

## 5. Restart PHP and workers

Restart the reviewed PHP handler/OPcache using the hosting platform's known command. Do not guess its service name.

Then restart the existing Supervisor definitions:

```bash
supervisorctl reread
supervisorctl update
supervisorctl restart dieuhoa-ai-governed
supervisorctl restart dieuhoa-worker:*
supervisorctl status
```

Restarting processes must not change operator intent. Restore the exact pre-deploy desired state after health verification.

## 6. Runtime verification

```bash
runuser -u dieuhoatudungcom -- php artisan about
runuser -u dieuhoatudungcom -- php artisan migrate:status
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
runuser -u dieuhoatudungcom -- php artisan ai:managed-health-check
sleep 5
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
runuser -u dieuhoatudungcom -- php artisan schedule:list
```

Require all of the following:

- application and worker version `1.32.2`;
- application/worker build ID and worker code hash match;
- deployment status `UP_TO_DATE`;
- worker heartbeat is fresh and the worker accepts jobs when desired state is enabled;
- managed health probe is `COMPLETED`, cross-process, with `provider_call=false` and `product_mutation=false`;
- queue is exactly `ai_governed`;
- pending, processing and stuck counts are zero;
- production scheduler/watchdog are healthy;
- migrations have no pending entry.

Do not enable processing if any version, hash, database or queue mismatch exists.

## 7. Application smoke

Check without invoking the real provider:

1. Public home, Product listing and one Product detail return HTTP 200.
2. Admin login succeeds.
3. Product list and Product Edit load without HTTP 500, Livewire or console errors.
4. Product AI panel resolves existing `REVIEW_REQUIRED`, `APPROVED`, `APPLIED`, `BLOCKED` and `FAILED` evidence truthfully.
5. Existing draft preview shows editorial warnings, optional data and technical evidence in separate groups.
6. A verified historical technical fact does not remain a false hard blocker.
7. A real contradiction remains blocked.
8. Opening Product Edit does not create a duplicate AI job.

Do not bulk retry historical failed/blocked jobs. Do not perform a real-provider smoke call unless separately authorized for Live.

## 8. Restore traffic

After every gate passes, restore `DESIRED_STATE_BEFORE_DEPLOY` through the canonical operator control and bring the application online:

```bash
runuser -u dieuhoatudungcom -- php artisan up
```

Re-run `ai:queue-health --json` and record the final state.

## 9. Rollback

If any mandatory gate fails, keep maintenance mode active and roll back:

```bash
git checkout v1.32.1
runuser -u dieuhoatudungcom -- composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
runuser -u dieuhoatudungcom -- php artisan optimize:clear
runuser -u dieuhoatudungcom -- php artisan config:cache
runuser -u dieuhoatudungcom -- php artisan route:cache
runuser -u dieuhoatudungcom -- php artisan view:cache
supervisorctl restart dieuhoa-ai-governed
supervisorctl restart dieuhoa-worker:*
supervisorctl status
runuser -u dieuhoatudungcom -- php artisan about
runuser -u dieuhoatudungcom -- php artisan migrate:status
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
runuser -u dieuhoatudungcom -- php artisan up
```

Verify rollback web/worker version, build/hash, database, queue and heartbeat alignment. Do not delete Product rows, drafts, jobs, request logs or historical evidence.

## 10. Deployment evidence

Record:

```text
RELEASE: v1.32.2
COMMIT:
ROLLBACK TAG:
BACKUP VERIFIED:
APPLICATION VERSION/BUILD:
WORKER VERSION/BUILD/HASH:
DESIRED STATE BEFORE/AFTER:
QUEUE / PENDING / PROCESSING / STUCK:
SELF TEST:
MIGRATIONS:
SCHEDULER/WATCHDOG:
PUBLIC SMOKE:
ADMIN/PRODUCT AI SMOKE:
FINAL: PASS / ROLLED BACK / BLOCKED
```
