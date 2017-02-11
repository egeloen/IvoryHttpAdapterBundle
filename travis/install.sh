#!/usr/bin/env bash

set -e

TRAVIS_PHP_VERSION=${TRAVIS_PHP_VERSION-5.6}
SYMFONY_VERSION=${SYMFONY_VERSION-2.3.*}
GUZZLE_VERSION=${GUZZLE_VERSION-6.*}
COMPOSER_PREFER_LOWEST=${COMPOSER_PREFER_LOWEST-false}
DOCKER_BUILD=${DOCKER_BUILD-false}

if [ "$DOCKER_BUILD" = true ]; then
    cp .env.dist .env

    docker-compose build
    docker-compose run --rm php composer update --prefer-source

    exit
fi

if [[ "$TRAVIS_PHP_VERSION" =~ ^5.* ]]; then
    printf "\n" | pecl install propro-1.0.2
    printf "\n" | pecl install raphf-1.1.2
    printf "\n" | pecl install pecl_http-2.5.6
fi

if [[ "$TRAVIS_PHP_VERSION" =~ ^7.* ]]; then
    printf "\n" | pecl install propro
    printf "\n" | pecl install raphf
    printf "\n" | pecl install pecl_http
fi

composer self-update
composer require --no-update symfony/framework-bundle:${SYMFONY_VERSION}

if [[ "$SYMFONY_VERSION" =~ ^3.* ]]; then
    composer remove --dev --no-update guzzle/guzzle
fi

if [ ! "$GUZZLE_VERSION" = "6.*" ]; then
    composer require --no-update --dev guzzlehttp/guzzle:${GUZZLE_VERSION}
fi

composer remove --no-update --dev friendsofphp/php-cs-fixer

if [[ "$SYMFONY_VERSION" = *dev* ]]; then
    composer config minimum-stability dev
fi

composer update --prefer-source `if [ "$COMPOSER_PREFER_LOWEST" = true ]; then echo "--prefer-lowest --prefer-stable"; fi`
