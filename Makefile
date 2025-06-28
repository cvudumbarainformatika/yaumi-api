# Laravel Artisan Commands
art:
	docker compose exec app php artisan $(cmd)

shell:
	docker compose exec app bash

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
	docker compose exec app php artisan cache:clear

conf:
	docker compose exec app php artisan config:cache

octane_status:
	docker compose exec app php artisan octane:status

octane_reload:
	docker compose exec app php artisan octane:reload

refresh:
	docker compose exec app php artisan config:cache && docker compose exec app php artisan route:cache


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
	@echo "🔄 Flushing and re-importing Product & Supplier indexes..."
	docker compose exec app php artisan scout:flush 'App\Models\Product'
	docker compose exec app php artisan scout:import 'App\Models\Product'
	docker compose exec app php artisan scout:flush 'App\Models\Supplier'
	docker compose exec app php artisan scout:import 'App\Models\Supplier'
	@echo "⚙️  Disabling typo tolerance for Product index..."
	docker compose exec app php artisan meili:disable-typo
	@echo "✅ MeiliSearch reindex & typo tolerance update complete."

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

# Queue Commands
queue_status:
	docker compose exec app supervisorctl status

queue_restart:
	docker compose exec app supervisorctl restart all

queue_stop:
	docker compose exec app supervisorctl stop all

queue_start:
	docker compose exec app supervisorctl start all

# Redis Cache Commands
redis_cache:
	docker compose exec redis redis-cli

redis_keys:
	docker compose exec redis redis-cli KEYS "*"

redis_get:
	docker compose exec redis redis-cli GET $(key)

redis_monitor:
	docker compose exec redis redis-cli MONITOR

redis_clear:
	docker compose exec redis redis-cli FLUSHALL

redis_info:
	docker compose exec redis redis-cli info

redis_flush:
	docker compose exec redis redis-cli FLUSHALL

# Supervisor Commands
supervisor_reload:
	docker compose exec app supervisorctl reload

supervisor_status:
	docker compose exec app supervisorctl status

supervisor_restart:
	docker compose exec app supervisorctl restart all
