FROM php:8.5.12-cli-bookworm AS base

ARG APP_UID=1000
ARG APP_GID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libicu-dev \
        libpq-dev \
        libzip-dev \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        pcntl \
        pdo_pgsql \
        sockets \
        zip \
    && groupadd --gid "${APP_GID}" app \
    && useradd --uid "${APP_UID}" --gid app --create-home --shell /bin/bash app \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

FROM base AS dev

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
    && rm -rf /var/lib/apt/lists/*

USER app

FROM base AS prod

USER app

COPY --chown=app:app . /app
