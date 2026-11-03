#!/bin/zsh
# Usage: serve-demo.sh <demo-app-dir> <addon-dir> <port>
# Re-points the demo copy's vendor symlink at <addon-dir>, sets APP_URL, clears caches, serves in background; prints PID.
set -e
APP="$1"; ADDON="$2"; PORT="$3"
rm -f "$APP/vendor/reachweb/statamic-livewire-filters"
ln -s "$ADDON" "$APP/vendor/reachweb/statamic-livewire-filters"
sed -i '' "s#^APP_URL=.*#APP_URL=http://127.0.0.1:${PORT}#" "$APP/.env"
cd "$APP"
php artisan optimize:clear >/dev/null 2>&1 || true
php please stache:clear >/dev/null 2>&1 || true
nohup php artisan serve --host=127.0.0.1 --port="$PORT" > "$APP/storage/logs/serve.log" 2>&1 &
echo $!
