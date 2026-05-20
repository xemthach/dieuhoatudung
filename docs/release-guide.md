# Release and Live Server Update Guide

## Release checklist

1. Verify local diff against `origin/main`.
2. Confirm `VERSION` and `CHANGELOG.md` are updated together.
3. Run the full test suite.
4. Run the frontend build.
5. Commit, tag, push, and publish the GitHub release notes.

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

## Rollback plan

1. Restore the database backup if migration or data issues appear.
2. Revert to the previous tagged release.
3. Restore the previous `.env` file if configuration changed.
4. Restart workers and verify the smoke checks again.
