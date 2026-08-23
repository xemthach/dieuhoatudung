# AI Worker Environment Matrix

| Environment | PHP binary | Project path | APP_ENV | Database | Queue | Process manager | Startup | Restart | Logs | Scheduler | Desired-state behavior | Deploy restart | Health proof |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Localhost / Laragon (proven 2026-08-23) | `D:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe` | `D:\laragon\www\dieuhoa-tudung` | `local` | `mysql`, `127.0.0.1:3306`, `dieuhoa-tudung` | connection `database`, queue `ai_governed` | Windows Task Scheduler: `DieuHoaTuDung-AIGovernedWorker`; watchdog: `DieuHoaTuDung-AIGovernedWatchdog` | Worker task triggers at interactive user logon. It is independent of a Laragon terminal. | `powershell -File scripts\restart_ai_worker_task.ps1 -Restart` | `storage/logs/ai-worker.log`, Laravel `ai-jobs` log, `ai_technical_logs` | Full Laravel scheduler is not installed locally; AI watchdog has its own one-minute task. Scheduler-dependent stale recovery remains unavailable until explicitly configured. | State file is preserved. Process stays online/paused while disabled; enabled state resumes claims. | Stop, wait for both old PIDs to exit, start one reviewed task, verify version/build/code hash. | Version 1.28.0, matching worker code hash, separate dispatcher/worker PIDs, DB/queue match, safe probes completed without provider calls. |
| Production target | Must be selected and pinned | Release `current` path with shared writable `storage` | `production` | Must match web process exactly | configured connection, only `ai_governed` | **TARGET SELECTION REQUIRED BEFORE DEPLOY.** Preferred Linux: systemd or Supervisor. Windows: service-grade Task Scheduler/NSSM under a dedicated account. | Linux service at boot or Windows startup/service trigger. Never HTTP. | Linux `systemctl restart <reviewed-service>` / `supervisorctl restart <reviewed-program>`; Windows reviewed restart script/task. | stdout/journal or Supervisor log plus Laravel logs; no payload secrets | Linux cron or service must run `php artisan schedule:run` each minute. Windows requires a reviewed one-minute scheduled task. | Shared desired-state storage survives releases/restarts; restart must not alter it. | Restart process manager after code/config cache deployment; `queue:restart` alone is insufficient for this custom parent/child process. | Require version/build match, DB/queue identity match, fresh heartbeat and non-provider self-test. |

## Local lifecycle findings

- Current worker task uses `AtLogOn`, interactive account `Peter`, limited run level and exact reviewed working directory.
- Task Scheduler `MultipleInstances=IgnoreNew` plus the managed ownership/PID guard prevents duplicate supervisors.
- The current local task is suitable for development after user login. It is **not** proof of unattended pre-login server startup.
- A Laragon restart does not reload an already-running PHP worker. Use the reviewed restart script after code/config changes.
- Current worker output is appended to `storage/logs/ai-worker.log`.

## Binding contract

Both web and worker report:

- version `1.28.0`;
- build `a6d89d9cb761c319734aa364917eca105c6d8de5`;
- environment `local`;
- queue connection `database`;
- queue `ai_governed`;
- database connection `mysql`;
- database `dieuhoa-tudung`.

Secrets and database credentials are intentionally excluded from runtime diagnostics.
