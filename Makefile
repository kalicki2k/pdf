UID := $(shell id -u)
GID := $(shell id -g)
DOCKER_COMPOSE := env UID=$(UID) GID=$(GID) docker compose

build:
	$(DOCKER_COMPOSE) build php

rebuild:
	$(DOCKER_COMPOSE) build --no-cache php

composer-install:
	$(DOCKER_COMPOSE) run --rm php composer install

phpstan:
	$(DOCKER_COMPOSE) run --rm -T php composer phpstan

cs:
	$(DOCKER_COMPOSE) run --rm php composer cs

cs-check:
	$(DOCKER_COMPOSE) run --rm php composer cs:check

rector:
	$(DOCKER_COMPOSE) run --rm php composer rector

rector-check:
	$(DOCKER_COMPOSE) run --rm php composer rector:check

test:
	$(DOCKER_COMPOSE) run --rm php composer test

coverage:
	$(DOCKER_COMPOSE) run --rm -e XDEBUG_MODE=coverage php composer test:coverage

coverage-html:
	$(DOCKER_COMPOSE) run --rm -e XDEBUG_MODE=coverage php composer test:coverage-html

shell:
	$(DOCKER_COMPOSE) run --rm php sh

php-version:
	$(DOCKER_COMPOSE) run --rm php php -v

up:
	$(DOCKER_COMPOSE) up

down:
	$(DOCKER_COMPOSE) down
