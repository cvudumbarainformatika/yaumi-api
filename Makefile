# Laravel Artisan Commands
art:
	docker compose exec app php artisan $(cmd)

# Common Laravel Commands
migrate:
	docker compose exec app php artisan migrate

fresh:
	docker compose exec app php artisan migrate:fresh

optimize:
	docker compose exec app php artisan optimize

seed:
	docker compose exec app php artisan db:seed

route:
	docker compose exec app php artisan route:list

tinker:
	docker compose exec app php artisan tinker

clear:
	docker compose exec app php artisan cache:clear && docker compose exec app php artisan config:clear

conf:
	docker compose exec app php artisan config:cache

octane_status:
	docker compose exec app php artisan octane:status

octane_reload:
	docker compose exec app php artisan octane:reload


# Docker Commands
up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build --no-cache

logs:
	docker compose logs -f

# Meilisearch Commands
meili_up:
	docker compose up -d meilisearch

meili_status:
	docker compose exec meilisearch meilisearch --version

meili_index:
	docker compose exec app php artisan scout:import "App\Models\Product" && docker compose exec app php artisan scout:import "App\Models\Supplier"

meili_flush:
	docker compose exec app php artisan scout:flush "App\Models\Product" && docker compose exec app php artisan scout:flush "App\Models\Supplier"

meili_sync:
	docker compose exec app php artisan scout:sync-index-settings

meili_check:
	curl -X GET 'http://localhost:7700/indexes/products_index/documents?limit=100' \
	-H "Authorization: Bearer masterKey"

# Composer Commands
composer:
	docker compose exec app composer $(cmd)

# Development Commands
watch:
	docker compose up watcher -d

watch_logs:
	docker compose logs -f watcher
