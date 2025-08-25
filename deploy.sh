#!/bin/bash
set -e

echo 'Deploying...'

# Название контейнера с приложением
APP_CONTAINER="laravel-app-app-1"

# Обновляем код из Git
git pull origin main

echo 'Putting application into maintenance mode...'
docker exec $APP_CONTAINER php artisan down

echo 'Installing composer dependencies...'
docker exec $APP_CONTAINER composer install --no-dev --optimize-autoloader

echo 'Running database migrations...'
docker exec $APP_CONTAINER php artisan migrate --force

echo 'Clearing caches...'
docker exec $APP_CONTAINER php artisan config:clear
docker exec $APP_CONTAINER php artisan cache:clear
docker exec $APP_CONTAINER php artisan route:clear
docker exec $APP_CONTAINER php artisan view:clear

echo 'Caching configs and routes...'
docker exec $APP_CONTAINER php artisan config:cache
docker exec $APP_CONTAINER php artisan route:cache
docker exec $APP_CONTAINER php artisan view:cache

echo 'Installing and building frontend assets...'
docker exec $APP_CONTAINER npm install
docker exec $APP_CONTAINER npm run build

echo 'Bringing application up...'
docker exec $APP_CONTAINER php artisan up

echo 'Deploy finished!'
