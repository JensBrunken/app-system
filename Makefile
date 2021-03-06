# VARIABLES
USER_ID := $(shell id -u)
GROUP_ID := $(shell id -g)
TOOLS_BIN := dev-ops/tools/vendor/bin

# TARGETS
.PHONY: help static-analysis psalm phpstan php-insights test test-fast ecs-dry ecs-fix init install-tools administration-unit administration-lint vendor e2e-init e2e-open e2e-cli-proxy

.DEFAULT_GOAL := help

help:
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

static-analysis: | install-tools vendor ## runs psalm and phpstan and phpinsights
	$(TOOLS_BIN)/psalm
	$(TOOLS_BIN)/phpstan analyze --configuration phpstan.neon src
	$(TOOLS_BIN)/phpinsights --no-interaction --min-quality=100 --min-complexity=75 --min-architecture=100 --min-style=100

psalm: | install-tools vendor ## runs psalm
	$(TOOLS_BIN)/psalm --output-format=compact

phpstan: | install-tools vendor ## runs phpstan
	$(TOOLS_BIN)/phpstan analyze --configuration phpstan.neon src

php-insights: | install-tools vendor ## runs phpinsights
	$(TOOLS_BIN)/phpinsights --no-interaction --min-quality=100 --min-complexity=75 --min-architecture=100 --min-style=100

test: ## runs phpunit
	composer dump-autoload
	php -d pcov.enabled=1 -d pcov.directory=./src ../../../vendor/bin/phpunit \
       --configuration phpunit.xml.dist \
       --coverage-clover build/artifacts/phpunit.clover.xml \
       --coverage-html build/artifacts/phpunit-coverage-html

test-fast: ## runs phpunit but skips all tests that lead to theme compilation
	composer dump-autoload
	php -d pcov.enabled=1 -d pcov.directory=./src ../../../vendor/bin/phpunit \
		--configuration phpunit.xml.dist \
		--coverage-clover build/artifacts/phpunit.clover.xml \
		--coverage-html build/artifacts/phpunit-coverage-html \
		--exclude ThemeCompile

test-no-cov: ## runs phpunit
	composer dump-autoload
	php ../../../vendor/bin/phpunit --configuration phpunit.xml.dist

ecs-dry: | install-tools vendor  ## runs easy coding standard in dry mode
	$(TOOLS_BIN)/ecs check .

ecs-fix: | install-tools vendor  ## runs easy coding standard and fixes issues
	$(TOOLS_BIN)/ecs check . --fix

init: ## activates the plugin and dumps test-db
	- cd ../../../ \
		&& ./psh.phar init \
		&& php bin/console plugin:install --activate SaasConnect \
		&& ./psh.phar storefront:init \
		&& ./psh.phar init-test-databases \
		&& ./psh.phar e2e:dump-db \
		&& ./psh.phar cache

administration-unit: ## run administration unit tests
	npm --prefix src/Resources/app/administration run unit

administration-lint: ## run eslint for administration
	npm --prefix src/Resources/app/administration run eslint

administration-fix-lint: ## run eslint for administration
	npm --prefix src/Resources/app/administration run eslint -- --fix

install-tools: | $(TOOLS_BIN) ## Installs connect dev tooling

$(TOOLS_BIN):
	composer install -d dev-ops/tools

vendor:
	composer install --no-interaction --optimize-autoloader --no-suggest --no-scripts --no-progress

e2e-init: ## installs dependencies for e2e tests
	npm --prefix src/Resources/app/e2e install

e2e-cli-proxy: ## starts an express server to add additional commands for e2e tests
	npm --prefix src/Resources/app/e2e run start-e2e-proxy

e2e-open: ## open cypress
	- cd ../../../ && ./psh.phar cache --DB_NAME="shopware_e2e" --APP_ENV="prod"
	- npm --prefix src/Resources/app/e2e run open
	- cd ../../../ && ./psh.phar cache
