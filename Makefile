export UID := $(shell id -u)
export GID := $(shell id -g)

RUN := docker compose run --rm php

.PHONY: build install validate shell

build:
	docker compose build

install:
	$(RUN) composer install

validate:
	$(RUN) composer validate --strict

shell:
	$(RUN) sh
