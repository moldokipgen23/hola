# Phase 0 production checklist

Run this checklist for every production deployment. Commands assume the release directory is the Laravel application root.

## Before deployment

1. Confirm the worktree contains only reviewed release changes.
2. Back up the database and record the backup identifier.
3. Run locally:

   ```bash
   php artisan test
   vendor/bin/pint --test
   php artisan route:clear
   php artisan route:cache
   php artisan route:list
   php artisan schedule:list
   php artisan agent:maintain-tasks --dry-run
   ```

4. Confirm these route contracts:
   - `GET /admin/claims` is named `admin.claims`.
   - `GET /login` is named `login`.
   - `POST /auth/login` is named `api.login`.

## Deployment

1. Enable maintenance mode when a migration or incompatible release requires it.
2. Deploy the reviewed revision; do not edit tracked production files manually.
3. Run:

   ```bash
   composer install --no-dev --prefer-dist --optimize-autoloader
   php artisan migrate --force
   php artisan optimize:clear
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan agent:maintain-tasks --stale-minutes=60
   ```

4. Ensure the scheduler invokes `php artisan schedule:run` every minute.
5. Restart long-running queue workers when the queue connection is not `sync`.
6. Disable maintenance mode.

## Smoke tests

- `/up` returns HTTP 200.
- `/health` returns HTTP 200 after the next scheduler minute.
- Signed-out `/admin/dashboard` redirects to `/admin/login`.
- The admin dashboard renders for an authenticated admin.
- `/admin/claims` renders for an authenticated admin.
- Autopilot shows its latest task and scheduler heartbeat.

## Rollback

1. Enable maintenance mode.
2. Restore the previous reviewed revision.
3. Restore the database only when the migration rollback is not safe or sufficient.
4. Rebuild config, route, and view caches.
5. Run the smoke tests above before disabling maintenance mode.

Production must use `APP_ENV=production` and `APP_DEBUG=false`.
