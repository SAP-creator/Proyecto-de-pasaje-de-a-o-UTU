#!/usr/bin/env bash
set -Eeuo pipefail

php-fpm --nodaemonize &
php_fpm_pid=$!
httpd -DFOREGROUND &
httpd_pid=$!

shutdown() {
    kill "$php_fpm_pid" "$httpd_pid" 2>/dev/null || true
    wait "$php_fpm_pid" "$httpd_pid" 2>/dev/null || true
}
trap shutdown TERM INT

set +e
wait -n "$php_fpm_pid" "$httpd_pid"
status=$?
set -e
shutdown
exit "$status"
