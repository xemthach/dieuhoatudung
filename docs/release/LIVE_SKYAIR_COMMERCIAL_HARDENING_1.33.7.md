# Live SkyAir commercial hardening certification — v1.33.7

## Release identity

- Tag: `v1.33.7`
- Release commit: `43c620e878542ee63f34d2075b6e350f2b251c67`
- GitHub Release: https://github.com/xemthach/dieuhoatudung/releases/tag/v1.33.7
- Assets: certified Local Vite build is included in the tag.

## Local release gates

- Focused SkyAir/Product technical suite: **22 tests, 639 assertions, PASS**.
- Playwright Local: **2 passed, 0 failed**.
- Full PHPUnit: **574 tests; 573 passed; 1 skipped; 0 failed; 3,587 assertions; exit 0**.
- Composer validate/audit, npm high audit, Vite build, PHP lint and diff check: **PASS**.
- Source workbooks and local Product export workbooks were deliberately excluded
  from the release. The reviewed source-derived component matrix is versioned
  with the tests.

## Production predeploy result

The sole permitted read-only SSH precheck was attempted against the documented
endpoint:

```text
ssh -o BatchMode=yes -o ConnectTimeout=12 \
  dieuhoatudungcom@dieuhoatudung.com
```

Result on 2026-09-04: `connect to host dieuhoatudung.com port 22: Connection timed out`.
No alternate server address or control-panel transport is configured in this
workspace, so it would be unsafe to guess one.

Consequently none of the following was executed: Production Git precheck,
database backup, tag checkout, Composer install, migration/cache lifecycle,
Supervisor restart, worker health verification, technical edit smoke, or public
filter acceptance. No Production database, files, workers, queue, or AI desired
state were mutated.

## Operator continuation runbook

Once the authorised SSH endpoint is reachable, run as the production operator:

```bash
cd /home/dieuhoatudungcom/dieuhoatudung.com/public_html
git status --short
git fetch --prune --tags origin
git rev-parse v1.33.7^{commit}
git rev-parse HEAD
cat VERSION
runuser -u dieuhoatudungcom -- php artisan about --no-ansi
runuser -u dieuhoatudungcom -- php artisan migrate:status --no-ansi
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
supervisorctl status
```

Stop if the worktree is dirty or `v1.33.7^{commit}` is not
`43c620e878542ee63f34d2075b6e350f2b251c67`. Capture a verified database backup,
record the existing AI desired state, then deploy only the tag:

```bash
git checkout v1.33.7
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

Do not build Node assets on Production. Restore the exact predeploy AI desired
state after the worker restart. Then use one authorised, safe Product for the
technical-form edit/save/reload proof; retain the audit reason and do not edit
catalog source evidence. Verify public Product cards and the filter URLs:

```text
/san-pham?btu[]=18000
/san-pham?btu[]=24000
/san-pham?btu[]=48000
/san-pham?btu[]=18000&btu[]=48000
```

## Certification verdict

`PRODUCTION = NOT_READY` solely because the mandatory production SSH access and
all runtime gates are blocked externally. The Local release and remote GitHub
release are ready; no conclusion about Live version, worker parity, scheduler,
or browser behavior is made without access.
