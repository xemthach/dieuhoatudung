# Release and Live Server Update Guide

## Release checklist

1. Verify local diff against `origin/main`.
2. Confirm `VERSION` and `CHANGELOG.md` are updated together.
3. Run the full test suite.
4. Run the frontend build.
5. Commit, tag, push, and publish the GitHub release notes.

## Manual release flow (step-by-step)

1. Sync with remote and review delta:

```bash
git fetch origin
git status
git diff --stat origin/main
```

2. Run quality gates:

```bash
php artisan test
npm run build
```

3. Prepare release metadata:
   - Bump `VERSION`.
   - Add a new dated section in `CHANGELOG.md`.

4. Commit and tag:

```bash
git add -A
git commit -m "release: vX.Y.Z"
git tag vX.Y.Z
```

5. Push branch and tag:

```bash
git push origin main
git push origin vX.Y.Z
```

6. Create GitHub release:
   - Title: `vX.Y.Z`
   - Tag: `vX.Y.Z`
   - Description: copy the `CHANGELOG.md` section for `vX.Y.Z`.

## Live server update steps

1. Back up the database.
2. Back up the current `.env` file and uploaded assets if needed.
3. Pull the tagged release on the live server.
4. Run database migrations:

```bash
php artisan migrate --force
```

5. Rebuild or deploy frontend assets if the release includes asset changes:

```bash
npm run build
```

6. Clear and warm the Laravel caches:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

7. Restart queue workers and scheduler processes.
8. Run a quick smoke check:
   - homepage
   - product detail
   - add to compare
   - quote request
   - import/export flow
   - mail/queue health pages

## Queue and AI runtime

This project uses the `database` queue connection by default and sends AI product jobs to the `ai` queue.

Recommended worker command:

```bash
php artisan queue:work --queue=ai,default --sleep=3 --tries=3 --timeout=900
```

If you run multiple workers, keep one worker available for `ai` so product content jobs do not wait behind unrelated work.

Useful AI maintenance commands:

```bash
php artisan ai:queue-health --record
php artisan ai:jobs-recover-stuck
php artisan ai:jobs-cancel-current --flush-queue
```

Scheduler options:

- Cron-based server: run `php artisan schedule:run` every minute through cron.
- Long-running server: run `php artisan schedule:work`.

The scheduled jobs in this release include:

- `ai:jobs-recover-stuck`
- `ai:queue-health --record`
- `ai:technical-logs-cleanup --days=30`
- `products:audit-category-technical-schema --report`
- `products:audit-technical-specs --report`

## Rollback plan

1. Restore the database backup if migration or data issues appear.
2. Revert to the previous tagged release.
3. Restore the previous `.env` file if configuration changed.
4. Restart workers and verify the smoke checks again.
