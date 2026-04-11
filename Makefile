build:
	docker compose build php

rebuild:
	docker compose build --no-cache php

composer-install:
	docker compose run --rm php composer install

phpstan:
	bin/phpstan

shell:
	docker compose run --rm php sh

php-version:
	docker compose run --rm php php -v

up:
	docker compose up

down:
	docker compose down
