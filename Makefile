build:
	docker compose build php

rebuild:
	docker compose build --no-cache php

composer-install:
	docker compose run --rm php composer install

phpstan:
	docker compose run --rm php composer phpstan

cs:
	docker compose run --rm php composer cs

cs-check:
	docker compose run --rm php composer cs:check

test:
	docker compose run --rm php composer test

coverage:
	docker compose run --rm -e XDEBUG_MODE=coverage php composer test:coverage

coverage-html:
	docker compose run --rm -e XDEBUG_MODE=coverage php composer test:coverage-html

shell:
	docker compose run --rm php sh

php-version:
	docker compose run --rm php php -v

up:
	docker compose up

down:
	docker compose down
