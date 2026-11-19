.PHONY: setup dev test lint format analyse deptrac ci

setup: ## Install backend dependencies, create .env, key, migrate
	cd backend && composer setup

dev: ## Run the backend dev server
	cd backend && composer dev

test: ## Run the backend test suite (Pest)
	cd backend && composer test

lint: ## Check code style (Pint)
	cd backend && composer lint

format: ## Fix code style (Pint)
	cd backend && composer format

analyse: ## Static analysis (Larastan, level 8)
	cd backend && composer analyse

deptrac: ## Check module boundaries
	cd backend && composer deptrac

ci: ## Everything CI runs: lint, analyse, deptrac, tests, dependency audit
	cd backend && composer ci
