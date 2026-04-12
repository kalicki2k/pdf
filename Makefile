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
	$(DOCKER_COMPOSE) run --rm php composer phpstan

cs:
	$(DOCKER_COMPOSE) run --rm php composer cs

cs-check:
	$(DOCKER_COMPOSE) run --rm php composer cs:check

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

qpdf-version:
	$(DOCKER_COMPOSE) run --rm qpdf qpdf --version

verapdf-version:
	$(DOCKER_COMPOSE) run --rm verapdf --version

check-qpdf:
	@if [ -z "$(PDF)" ]; then echo "Usage: make check-qpdf PDF=path/to/file.pdf"; exit 1; fi
	$(DOCKER_COMPOSE) run --rm qpdf qpdf --check "$(PDF)"

check-verapdf:
	@if [ -z "$(PDF)" ]; then echo "Usage: make check-verapdf PDF=path/to/file.pdf"; exit 1; fi
	$(DOCKER_COMPOSE) run --rm verapdf --format text --verbose "/app/$(PDF)"

validate-pdfa:
	@if [ -z "$(PDF)" ]; then echo "Usage: make validate-pdfa PDF=path/to/file.pdf"; exit 1; fi
	$(DOCKER_COMPOSE) run --rm verapdf --format text --verbose "/app/$(PDF)"

validate-pdfua:
	@if [ -z "$(PDF)" ]; then echo "Usage: make validate-pdfua PDF=path/to/file.pdf"; exit 1; fi
	$(DOCKER_COMPOSE) run --rm verapdf --format text --verbose --defaultflavour ua1 --flavour ua1 "/app/$(PDF)"

check-pdf:
	@if [ -z "$(PDF)" ]; then echo "Usage: make check-pdf PDF=path/to/file.pdf"; exit 1; fi
	$(MAKE) check-qpdf PDF="$(PDF)"
	$(MAKE) check-verapdf PDF="$(PDF)"

up:
	$(DOCKER_COMPOSE) up

down:
	$(DOCKER_COMPOSE) down
