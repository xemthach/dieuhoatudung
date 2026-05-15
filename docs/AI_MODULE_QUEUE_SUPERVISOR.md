# AI Module Queue + Supervisor Setup

This guide explains how to configure a live server so AI modules can process jobs reliably.

Affected modules:

- `Admin > SEO & AI > AI Content Job`
- `Admin > E-commerce > Product` AI Product Content System
- Any Laravel job that uses the `default` queue

The AI modules do not run long AI requests directly inside the web request. They dispatch jobs into Laravel queue. If the server has no queue worker daemon, records will stay in `pending` / `Đang chờ`.

Newer releases use the dedicated `ai` queue first, then `default` as fallback:

```bash
php artisan queue:work --queue=ai,default --tries=3 --timeout=300
```

To stop current AI work immediately:

```bash
php artisan ai:jobs-cancel-current --flush-queue
php artisan queue:restart
sudo supervisorctl stop dieuhoa-worker:*
```

Start it again:

```bash
sudo supervisorctl start dieuhoa-worker:*
```

---

## 1. Required `.env`

Confirm queue is configured for the database driver:

```env
QUEUE_CONNECTION=database
```

Then clear config cache:

```bash
cd /root/atg-part2/public_html
php artisan optimize:clear
php artisan config:cache
```

Confirm database queue tables exist:

```bash
php artisan migrate --force
php artisan tinker --execute="dump([
  'queue' => config('queue.default'),
  'queued_jobs' => DB::table('jobs')->count(),
  'failed_jobs' => DB::table('failed_jobs')->count(),
]);"
```

---

## 2. Install Supervisor

### AlmaLinux / Rocky / CentOS

```bash
sudo dnf install -y epel-release
sudo dnf install -y supervisor
sudo systemctl enable supervisord
sudo systemctl start supervisord
sudo systemctl status supervisord
```

For CentOS 7, use `yum`:

```bash
sudo yum install -y epel-release
sudo yum install -y supervisor
sudo systemctl enable supervisord
sudo systemctl start supervisord
```

### Ubuntu / Debian

```bash
sudo apt update
sudo apt install -y supervisor
sudo systemctl enable supervisor
sudo systemctl start supervisor
sudo systemctl status supervisor
```

---

## 3. Find Supervisor Config Include Path

Run:

```bash
grep -n "files" /etc/supervisord.conf
```

On AlmaLinux/Rocky/CentOS, this is usually:

```ini
files = supervisord.d/*.ini
```

That means worker config must be placed in:

```bash
/etc/supervisord.d/dieuhoa-worker.ini
```

On Ubuntu/Debian, the common path is:

```bash
/etc/supervisor/conf.d/dieuhoa-worker.conf
```

---

## 4. Create Laravel Queue Worker Config

For this server path:

```bash
/root/atg-part2/public_html
```

Create:

```bash
sudo nano /etc/supervisord.d/dieuhoa-worker.ini
```

Use this config:

```ini
[program:dieuhoa-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /root/atg-part2/public_html/artisan queue:work database --queue=ai,default --sleep=3 --tries=3 --timeout=300 --memory=256 --max-time=3600
directory=/root/atg-part2/public_html
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=root
numprocs=1
redirect_stderr=true
stdout_logfile=/root/atg-part2/public_html/storage/logs/queue-worker.log
stopwaitsecs=700
```

Notes:

- Replace `/root/atg-part2/public_html` with the real project path on other servers.
- Replace `user=root` with the real Linux user if the site runs under another account.
- Do not leave `user=USER`; Supervisor will fail with `Invalid user name USER`.
- `timeout=300` matches the application retry policy. Product AI items can still use their own longer timeout when needed.
- `tries=3` allows provider rate-limit or timeout retries with backoff.

---

## 5. Load and Start Worker

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

Expected:

```text
dieuhoa-worker:dieuhoa-worker_00   RUNNING   pid 12345, uptime 0:00:04
```

If it is stopped:

```bash
sudo supervisorctl start dieuhoa-worker:*
```

After every deploy:

```bash
cd /root/atg-part2/public_html
php artisan queue:restart
sudo supervisorctl restart dieuhoa-worker:*
```

Run queue health checks:

```bash
php artisan ai:queue-health
php artisan ai:jobs-recover-stuck
```

In admin, open:

```text
SEO & AI > AI Queue Health
```

This page warns when the worker heartbeat is stale, shows pending/failed queue counts, processing AI jobs, stuck jobs, and latest processed AI job.

---

## 6. Test AI Queue Processing

Check queue state:

```bash
cd /root/atg-part2/public_html

php artisan tinker --execute="dump([
  'queued_jobs' => DB::table('jobs')->count(),
  'failed_jobs' => DB::table('failed_jobs')->count(),
  'latest_ai_job' => App\Models\AiContentJob::latest()->first(['id','topic','status','error_message','updated_at'])?->toArray(),
]);"
```

Watch worker log:

```bash
tail -f storage/logs/queue-worker.log
```

Manual one-job test:

```bash
php artisan queue:work --queue=ai,default --once --tries=1 --timeout=600 -vvv
```

If the manual command processes jobs but the site still leaves jobs pending, Supervisor is not running or is reading the wrong config path.

---

## 7. Common AI Job States

### `pending` / `Đang chờ`

The AI record exists, but the queue worker has not processed it yet.

Check:

```bash
php artisan tinker --execute="dump(DB::table('jobs')->select('id','queue','attempts','reserved_at','available_at','created_at')->orderByDesc('id')->limit(10)->get()->toArray());"
sudo supervisorctl status
```

If rows exist in `jobs` with `attempts=0` and `reserved_at=null`, start/restart Supervisor.

### `processing`

The worker picked up the job and is calling the AI provider. Wait a few minutes, then check logs.

### `failed`

Read the error:

```bash
php artisan tinker --execute="dump(App\Models\AiContentJob::latest()->first(['id','topic','status','error_message','updated_at'])?->toArray());"
php artisan queue:failed
tail -n 150 storage/logs/laravel.log
```

Common failures:

- `AI output chưa đạt chuẩn`: the provider returned content too short or missing FAQ. Use a stronger model or retry.
- `401/403`: API key or provider endpoint is wrong.
- `429`: provider rate limit.
- timeout: provider response too slow; keep `timeout=600` and consider a faster model.

---

## 8. AI Provider Checklist

In Admin:

```text
SEO & AI > AI Providers
```

Verify:

- Provider status is `active`.
- Endpoint is correct, for example `https://api.shopaikey.com/v1` for OpenAI-compatible ShopAIKey.
- Model supports long output.
- API key is valid.
- Test action returns success.

SSH check:

```bash
php artisan tinker --execute="dump(App\Models\AiProvider::query()->get(['id','name','provider','model','endpoint','status','priority','supports_json_mode','error_count','last_error_message','rate_limited_until'])->toArray());"
```

---

## 9. Cron Is Still Needed for Scheduler

Supervisor is for queue workers. Cron is still used for Laravel Scheduler if scheduled tasks exist.

Add:

```bash
crontab -e
```

```cron
* * * * * cd /root/atg-part2/public_html && php artisan schedule:run >> /dev/null 2>&1
```

Do not use cron as the primary queue worker for AI jobs. AI jobs can run for 1-5 minutes, so `queue:work --once` every minute can overlap or lag behind.

The scheduler runs:

- `ai:jobs-recover-stuck` every 5 minutes.
- `ai:queue-health --record` every 5 minutes to record scheduler heartbeat.
- `ai:technical-logs-cleanup --days=30` daily.

Local development:

```bash
npm run dev:full
```

That starts Vite, queue worker, and scheduler together.

Windows Task Scheduler alternative:

```powershell
php artisan queue:work --queue=ai,default --tries=3 --timeout=300
php artisan schedule:work
```

---

## 10. Troubleshooting Commands

```bash
sudo systemctl status supervisord
sudo supervisorctl status
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart dieuhoa-worker:*

ps aux | grep "queue:work" | grep -v grep
tail -f /root/atg-part2/public_html/storage/logs/queue-worker.log
tail -n 150 /root/atg-part2/public_html/storage/logs/laravel.log
```

If Supervisor does not detect the worker config:

```bash
grep -n "files" /etc/supervisord.conf
ls -la /etc/supervisord.d
ls -la /etc/supervisor/conf.d
```

Place the config file in the directory shown by the `files = ...` include rule.
