#!/usr/bin/env bash
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SERVER="${SERVER:-ubuntu@145.239.71.158}"
REMOTE_DIR="${REMOTE_DIR:-/var/www/tunzone/backend}"
REMOTE_OWNER="${REMOTE_OWNER:-ubuntu:ubuntu}"
PERSISTENT_FILES_DIR="${PERSISTENT_FILES_DIR:-/var/www/tunzone/files}"
PERSISTENT_MODEL_DIR="${PERSISTENT_MODEL_DIR:-$PERSISTENT_FILES_DIR/models}"
PERSISTENT_IMAGE_DIR="${PERSISTENT_IMAGE_DIR:-$PERSISTENT_FILES_DIR/images}"
VISTA_FILES_DIR="${VISTA_FILES_DIR:-/var/www/tunzone/vista}"
CLEAN_LEGACY_STORAGE_MODELS="${CLEAN_LEGACY_STORAGE_MODELS:-1}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.4-fpm}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-1}"
CACHE_ROUTES="${CACHE_ROUTES:-0}"
SSH="${SSH:-ssh}"

echo "==> Preparing $SERVER:$REMOTE_DIR ..."
$SSH "$SERVER" "sudo mkdir -p '$REMOTE_DIR' && sudo chown -R '$REMOTE_OWNER' '$REMOTE_DIR'"

echo "==> Syncing backend source to $SERVER:$REMOTE_DIR ..."
rsync -avz --delete \
  --exclude ".git" \
  --exclude ".cursor" \
  --exclude "vendor" \
  --exclude "node_modules" \
  --exclude ".env*" \
  --exclude ".DS_Store" \
  --exclude "Untitled" \
  --exclude "database/database.sqlite" \
  --exclude "public/storage" \
  --exclude "storage/app/public/files" \
  --exclude "storage/*.key" \
  --exclude "storage/*.html" \
  --exclude "storage/framework/cache/*" \
  --exclude "storage/framework/sessions/*" \
  --exclude "storage/framework/testing/*" \
  --exclude "storage/framework/views/*" \
  --exclude "storage/logs/*" \
  --exclude "storage/app/vista-files" \
  --exclude "bootstrap/cache/*.php" \
  --rsync-path="sudo rsync" \
  "$APP_DIR/" "$SERVER:$REMOTE_DIR/"

$SSH "$SERVER" "set -e \
  && sudo mkdir -p '$PERSISTENT_MODEL_DIR' '$PERSISTENT_IMAGE_DIR' '$VISTA_FILES_DIR' \
  && sudo mkdir -p '$REMOTE_DIR/storage/app/public/files' \
  && sudo mkdir -p '$REMOTE_DIR/storage/framework/cache/data' '$REMOTE_DIR/storage/framework/sessions' '$REMOTE_DIR/storage/framework/views' '$REMOTE_DIR/storage/logs' '$REMOTE_DIR/bootstrap/cache' \
  && if [ '$CLEAN_LEGACY_STORAGE_MODELS' = '1' ]; then sudo find '$REMOTE_DIR/storage/app/public/files' -mindepth 2 -maxdepth 2 -type d -name models -exec rm -rf {} +; fi \
  && sudo rm -rf '$REMOTE_DIR/storage/app/public/files/models' \
  && sudo chown -R '$REMOTE_OWNER' '$REMOTE_DIR' \
  && sudo chown -R www-data:www-data '$PERSISTENT_FILES_DIR' '$VISTA_FILES_DIR' '$REMOTE_DIR/storage' '$REMOTE_DIR/bootstrap/cache' \
  && sudo chmod -R 775 '$PERSISTENT_FILES_DIR' '$VISTA_FILES_DIR' '$REMOTE_DIR/storage' '$REMOTE_DIR/bootstrap/cache'"

echo "==> Installing and optimizing Laravel on server..."
$SSH "$SERVER" "cd '$REMOTE_DIR' \
  && if [ ! -f .env ]; then echo 'Missing production .env in $REMOTE_DIR'; exit 1; fi \
  && if ! grep -q '^APP_KEY=base64:' .env; then echo 'ERROR: .env is missing APP_KEY=base64:... — add one (php artisan key:generate) before deploy or OAuth/sessions will 500.'; exit 1; fi \
  && composer install --no-dev --optimize-autoloader \
  && (php artisan down || true)"

if [[ "$RUN_MIGRATIONS" == "1" ]]; then
  echo "==> Running database migrations..."
  $SSH "$SERVER" "cd '$REMOTE_DIR' && php artisan migrate --force"
else
  echo "==> Skipping database migrations because RUN_MIGRATIONS=$RUN_MIGRATIONS"
fi

echo "==> Ensuring VISTA_FILES_PATH in production .env ..."
$SSH "$SERVER" bash -s "$REMOTE_DIR" "$VISTA_FILES_DIR" <<'EOS'
set -euo pipefail
REMOTE_DIR="$1"
VISTA_FILES_DIR="$2"
cd "$REMOTE_DIR"
touch .env
tmp="$(mktemp)"
grep -v '^VISTA_FILES_PATH=' .env >"$tmp" || true
mv "$tmp" .env
printf '%s\n' "VISTA_FILES_PATH=${VISTA_FILES_DIR}" >>.env
EOS

$SSH "$SERVER" "cd '$REMOTE_DIR' \
  && php artisan storage:link --force \
  && php artisan optimize:clear \
  && php artisan config:cache \
  && php artisan view:cache \
  && sudo chown -R www-data:www-data storage bootstrap/cache \
  && sudo chmod -R 775 storage bootstrap/cache \
  && php artisan up \
  && (sudo systemctl reload '$PHP_FPM_SERVICE' || sudo systemctl restart '$PHP_FPM_SERVICE')"

if [[ "$CACHE_ROUTES" == "1" ]]; then
  echo "==> Caching Laravel routes..."
  $SSH "$SERVER" "cd '$REMOTE_DIR' && php artisan route:cache"
else
  echo "==> Skipping route cache because CACHE_ROUTES=$CACHE_ROUTES"
fi

echo "==> Done! Backend deployed successfully."
