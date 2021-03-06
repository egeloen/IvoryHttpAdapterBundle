#!/usr/bin/env bash

set -e

DOCKER_BUILD=${DOCKER_BUILD-false}

if [ "$DOCKER_BUILD" = false ]; then
    vendor/bin/phpunit --coverage-clover build/clover.xml
fi

if [ "$DOCKER_BUILD" = true ]; then
    docker-compose up -d
    docker-compose run --rm php vendor/bin/phpunit
fi
