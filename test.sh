#!/bin/sh
# Installs with composer:2 and runs Pest on PHP 8.5, both in throwaway
# containers. The whole repository is mounted so the path repository
# (../../sdk/php) resolves. Usage: sh laravel/test.sh   (from packages/)
set -eu

repo=$(cd "$(dirname "$0")/../.." && pwd)
user="$(id -u):$(id -g)"

docker run --rm -u "$user" -e COMPOSER_HOME=/tmp/composer -v "$repo":/w -w /w/packages/laravel \
  composer:2 composer update --no-interaction --no-progress --prefer-dist --ignore-platform-req=php

docker run --rm -u "$user" -v "$repo":/w -w /w/packages/laravel \
  php:8.5-cli-bookworm vendor/bin/pest "$@"
