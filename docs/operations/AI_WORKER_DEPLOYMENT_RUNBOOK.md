# AI Worker Deployment Runbook

## Mandatory deployment gate

Every live release must treat the managed AI worker as a separate long-running runtime. A deployment is blocked until all of the following are recorded and verified:

1. Before deployment, record desired state, actual state, heartbeat, web/worker version, queue connection/name, pending and processing jobs, leases, slots and reservations.
2. If work is processing, identify it and allow canonical graceful completion or recovery. Never kill it blindly.
3. Preserve `DESIRED_STATE_BEFORE_DEPLOY`. If enabled, set the existing desired state to `DISABLED` so no new work is claimed, then wait for active work to settle.
4. Deploy application code, dependencies, migrations, assets and compiled caches using the reviewed release workflow.
5. Restart the exact OS-managed worker and prove the old PIDs exited before replacement PIDs started.
6. Require fresh heartbeat plus matching project path, PHP/runtime, environment, database, queue connection, queue `ai_governed`, version, build and worker-code hash.
7. Verify scheduler/watchdog health and queue pending/processing/failed/stuck state, including leases, slots and reservations.
8. Run the non-provider worker self-test and require independent dispatcher/worker PIDs with `QUEUED -> CLAIMED -> COMPLETED`.
9. Restore the original desired state only after all checks pass. `DISABLED` must remain disabled; `ENABLED` may be restored only when runtime health is green.

Block deployment with an explicit reason such as `WORKER_OFFLINE_AFTER_DEPLOY`, `WORKER_STALE_AFTER_DEPLOY`, `WORKER_VERSION_MISMATCH`, `WORKER_WRONG_DATABASE`, `WORKER_WRONG_QUEUE`, or `SCHEDULER_UNHEALTHY`.

Every live update report must use this minimum evidence block:

```text
APPLICATION VERSION:
GIT COMMIT/TAG:

WEB: status / version
AI WORKER: desired / actual / heartbeat / version / queue / DB / restart result
SCHEDULER: status / heartbeat
QUEUE: pending / processing / failed / stuck / leases / slots / reservations
SELF TEST: PASS / NOT AVAILABLE / BLOCKED
FINAL: DEPLOYMENT PASS / BLOCKED
```

## Initial install

1. Configure the same release path, `.env`/cached config, storage path and database for web and worker.
2. Set `QUEUE_CONNECTION` to the reviewed backend and preserve queue `ai_governed`.
3. Keep `DB_QUEUE_RETRY_AFTER` above the maximum worker timeout of 900 seconds; project default is 960.
4. Install one OS process-manager definition using the exact command:

   ```text
   php artisan ai:managed-worker --queue=ai_governed --sleep=3 --tries=3 --timeout=900
   ```

5. Configure the Laravel scheduler separately. Review all scheduled integrations before enabling it.
6. Keep desired state `DISABLED` until health, version and non-provider self-test checks pass.

## Local start

Current Windows development uses:

- `DieuHoaTuDung-AIGovernedWorker` — starts at user logon;
- `DieuHoaTuDung-AIGovernedWatchdog` — runs every minute.

Registration scripts are dry-run by default:

```powershell
powershell -File scripts\register_ai_worker_task.ps1
powershell -File scripts\register_ai_watchdog_task.ps1
```

Use their explicit registration switches only after reviewing the printed executable, arguments and working directory. The worker does not require an open terminal after installation.

## Production install

The production OS is not yet evidenced; select one contract before deployment.

Linux systemd example contract:

```ini
[Service]
Type=simple
WorkingDirectory=/srv/dieuhoa-tudung/current
ExecStart=/usr/bin/php /srv/dieuhoa-tudung/current/artisan ai:managed-worker --queue=ai_governed --sleep=3 --tries=3 --timeout=900
Restart=always
RestartSec=5
User=<dedicated-app-user>
```

Linux scheduler:

```cron
* * * * * cd /srv/dieuhoa-tudung/current && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

For Supervisor, use the same working directory/command and one process. For Windows production, use a dedicated service account and startup trigger/NSSM; do not reuse the local interactive-logon proof as production certification.

## Enable / disable from admin

- `Bật AI Worker` writes desired state `ENABLED`; the OS-managed process begins claiming `ai_governed`.
- `Tắt AI Worker` writes `DISABLED`; an in-flight operation may finish, then the child remains `PAUSED` and claims no new jobs.
- Neither action starts/kills a process or calls a provider.

## Deploy / update

1. Record desired state; do not change it.
2. Verify a backup and deploy code.
3. Run reviewed release commands (`composer install`, migrations, frontend build as applicable).
4. Run `php artisan config:cache`, `route:cache`, and `view:cache`.
5. Restart the OS-managed AI worker because the parent/child PHP process otherwise retains old code/config.
6. Local Windows:

   ```powershell
   powershell -File scripts\restart_ai_worker_task.ps1
   powershell -File scripts\restart_ai_worker_task.ps1 -Restart
   ```

7. Linux systemd: `systemctl restart <reviewed-ai-worker-service>`.
8. Supervisor: `supervisorctl restart <reviewed-ai-worker-program>`.
9. Do not rely on `php artisan queue:restart` alone for this custom supervisor.

The restart script refuses to start a replacement until both old supervisor and child PIDs have exited, preventing deployment overlap.

## Verify

1. `php artisan ai:queue-health --json`.
2. Confirm web/worker version and build match.
3. Confirm environment, queue connection, `ai_governed` and database identity match.
4. Confirm desired state survived restart.
5. Confirm one supervisor and one child process.
6. Run `php artisan ai:managed-health-check` or **Kiểm tra Worker** in admin.
7. Require `QUEUED → CLAIMED → COMPLETED`, different dispatcher/worker PIDs, provider call `false`, Product mutation `false`.
8. Confirm no stuck lease, slot or token reservation.
9. Confirm scheduler/watchdog status according to the deployment contract.

## Rollback

1. Set desired state `DISABLED` if new claims must stop.
2. Wait for active operation/lease policy to settle.
3. Roll back code to the reviewed release.
4. Rebuild config/routes/views.
5. Restart the exact OS-managed worker using the reviewed procedure.
6. Verify version/build/DB/queue and run the non-provider self-test.
7. Restore the previous desired state only after verification.

## Troubleshooting

- **Enabled + offline/stale:** check OS task/service, working directory, PHP binary and logs.
- **Version mismatch:** restart the process manager; verify `APP_BUILD_ID` in artifacts without `.git`.
- **DB mismatch:** compare runtime diagnostics; never point the web app to a clone merely to match the worker.
- **Probe remains queued while enabled:** verify connection/queue, desired state, heartbeat and process ownership.
- **Duplicate process suspected:** do not start another process; use the reviewed restart script or process-manager status and confirm old PIDs exit.
- **Scheduler unknown:** configure the platform scheduler only after reviewing every scheduled command, including external integrations.
