#!/bin/bash

set -e

echo 'Deploying...'

git pull origin main

php8.2 artisan down

php8.2 composer i --no-dev --optimize-autoloader

php8.2 artisan migrate --force

php8.2 artisan config:cache

php8.2 artisan event:cache

php8.2 artisan route:cache

php8.2 artisan view:cache

php8.2 artisan up

echo 'Done!'






