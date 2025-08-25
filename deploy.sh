#!/bin/bash
set -e

echo 'Deploying...'

git pull origin main

php artisan down

composer install --no-dev --optimize-autoloader

php artisan migrate --force

php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache

npm install
npm run build

php artisan up

echo 'Done!'
