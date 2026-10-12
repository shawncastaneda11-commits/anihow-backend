#!/usr/bin/env bash
# Deploy a branch to the test copy (staging). Run as the anihow user:
#   bash /var/www/anihow-staging/scripts/vps/deploy-staging.sh            # main
#   bash /var/www/anihow-staging/scripts/vps/deploy-staging.sh my-branch  # a PR branch
# The live site in /var/www/anihow is never touched.
set -euo pipefail

BRANCH="${1:-main}"
if [[ ! "${BRANCH}" =~ ^[A-Za-z0-9._/-]+$ || "${BRANCH}" == -* ]]; then
    echo "Branch name must contain only letters, digits, dots, slashes, underscores and hyphens." >&2
    exit 1
fi

cd /var/www/anihow-staging

if ! grep -qx 'APP_ENV=staging' .env; then
    echo "/var/www/anihow-staging/.env is not a staging .env (APP_ENV=staging). Stopping." >&2
    exit 1
fi

git fetch origin "+refs/heads/${BRANCH}:refs/remotes/origin/${BRANCH}"
git checkout -f -B "${BRANCH}" "origin/${BRANCH}"

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
# `status anihow-staging-reverb` is not in sudoers; bare `status` is.
if sudo supervisorctl status | grep -q 'anihow-staging-reverb'; then
    sudo supervisorctl restart anihow-staging-reverb
else
    echo "anihow-staging-reverb not loaded yet; skipped"
fi

printf 'Staging now runs %s: %s\n' "${BRANCH}" "$(git log -1 --format='%h %s')"
