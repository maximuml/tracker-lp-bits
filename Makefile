.PHONY: help up down build test test-unit test-feature test-architecture test-lint migrate-test

# Default: show available commands
help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

# --- Docker stack ---
up: ## Start dev stack
	docker compose up -d --build

down: ## Stop dev stack
	docker compose down

build: ## Build images
	docker compose build

# --- Testing (uses docker-compose.test.yml overlay) ---
# NB: the dev overlay is required for the source bind-mount (the image has
# no code baked in), and --entrypoint sh bypasses entrypoint.sh, which
# ignores the command and would exec php-fpm instead. config:clear drops a
# stale bootstrap/cache/config.php left by the dev container — a cached
# config makes the test env overrides (DB_DATABASE, REDIS_PREFIX) inert.
TEST_COMPOSE = docker compose -f docker-compose.yml -f docker-compose.dev.yml -f docker-compose.test.yml
TEST_RUN = $(TEST_COMPOSE) run --rm -T --entrypoint sh php -c

test: ## Run all test suites (unit + feature + architecture) in isolated test DB
	$(TEST_RUN) 'php artisan config:clear && php artisan migrate:fresh --seed --force && composer test'

test-unit: ## Run unit tests only
	$(TEST_RUN) 'php artisan config:clear && php artisan migrate:fresh --seed --force && composer test:unit'

test-feature: ## Run feature tests only
	$(TEST_RUN) 'php artisan config:clear && php artisan migrate:fresh --seed --force && composer test:feature'

test-architecture: ## Run architecture ratchet tests only
	$(TEST_RUN) 'php artisan config:clear && composer test:architecture'

test-lint: ## Run Pint + PHPStan
	$(TEST_RUN) 'composer test:lint'

migrate-test: ## Run migrations against test DB
	$(TEST_RUN) 'php artisan config:clear && php artisan migrate:fresh --seed --force'

# --- Lint (local, without Docker) ---
lint: ## Run Pint --test locally
	docker compose exec -T php vendor/bin/pint --test

stan: ## Run PHPStan locally
	docker compose exec -T php vendor/bin/phpstan analyse --no-progress --memory-limit=2G
