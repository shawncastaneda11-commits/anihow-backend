#!/usr/bin/env bash
# Deploy the current main branch. Run as the anihow user from any directory.
set -euo pipefail

cd /var/www/anihow

git fetch origin main
git checkout main
git pull --ff-only origin main

composer install --no-dev --optimize-autoloader --no-interaction

php artisan migrate --force

if ! link_output="$(php artisan storage:link 2>&1)"; then
    if [[ "${link_output}" != *"already exists"* ]]; then
        printf '%s\n' "${link_output}" >&2
        exit 1
    fi
fi

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize
php artisan queue:restart

sudo systemctl reload php8.4-fpm
sudo supervisorctl restart anihow-reverb

printf 'Deployed %s\n' "$(git log -1 --format='%h %s')"
