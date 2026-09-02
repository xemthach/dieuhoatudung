# Live deployment runbook v1.33.3

Deploy from `/home/dieuhoatudungcom/dieuhoatudung.com/public_html` only after a verified backup and clean Git worktree.

```bash
git fetch --prune --tags origin
git show-ref --verify --quiet refs/tags/v1.33.3
git checkout v1.33.3
git rev-parse HEAD
cat VERSION
runuser -u dieuhoatudungcom -- composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
runuser -u dieuhoatudungcom -- php artisan migrate --force
runuser -u dieuhoatudungcom -- php artisan migrate:status --no-ansi
runuser -u dieuhoatudungcom -- php artisan optimize:clear
runuser -u dieuhoatudungcom -- php artisan config:cache
runuser -u dieuhoatudungcom -- php artisan route:cache
runuser -u dieuhoatudungcom -- php artisan view:cache
runuser -u dieuhoatudungcom -- php artisan catalog:audit-marketing-capacity --json > /root/marketing_capacity_live_before_$(date +%Y%m%d_%H%M%S).json
```

Do not run `catalog:backfill-marketing-capacity --apply` until reviewed `PROPOSE_UPDATE` rows prove verified `PRODUCT_LIST` evidence. If there are no proposals, stop data remediation.
