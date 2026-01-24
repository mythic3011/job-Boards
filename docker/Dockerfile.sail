# syntax=docker/dockerfile:1.6
FROM composer:2 AS composer_stage

FROM ubuntu:24.04

LABEL maintainer="@mythic3011"
LABEL version="1.0.0"
LABEL description="refactored Laravel Sail Dockerfile for better performance"
LABEL github="https://github.com/mythic3011/"

# arg variables for configuration dockerfile
ARG WWWGROUP
ARG NODE_VERSION=24
ARG POSTGRES_VERSION=18
ARG INSTALL_PLAYWRIGHT=false
ARG INSTALL_BUN=false

WORKDIR /var/www/html

# set default environment variables
ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=Asia/Hong_Kong
ENV PLAYWRIGHT_BROWSERS_PATH=0

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt/lists,sharing=locked \
    rm -f /etc/apt/apt.conf.d/docker-clean \
 && apt-get update \
 && apt-get install -y --no-install-recommends \
        ca-certificates gnupg curl zip unzip git supervisor sqlite3 libpng-dev \
        python3 nano dnsutils ffmpeg software-properties-common \
 && install -d -m 0755 /etc/apt/keyrings \
 && LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php \
 && apt-get update \
 && apt-get install -y --no-install-recommends \
        php8.5-cli php8.5-dev php8.5-pgsql php8.5-sqlite3 php8.5-gd php8.5-curl \
        php8.5-mbstring php8.5-xml php8.5-zip php8.5-bcmath php8.5-intl php8.5-readline \
 && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
 && chmod a+r /etc/apt/keyrings/nodesource.gpg \
 && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_${NODE_VERSION}.x nodistro main" > /etc/apt/sources.list.d/nodesource.list \
 && apt-get update \
 && apt-get install -y --no-install-recommends nodejs \
 && update-alternatives --set php /usr/bin/php8.5 || true \
 && corepack enable \
 && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# skip it by default
# since it's led long build time
RUN if [ "$INSTALL_BUN" = "true" ]; then npm install -g bun; fi
RUN if [ "$INSTALL_PLAYWRIGHT" = "true" ]; then npx playwright install-deps && npx playwright install; fi

RUN setcap "cap_net_bind_service=+ep" /usr/bin/php || true

RUN groupadd --force -g $WWWGROUP sail \
    && useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 sail || true
RUN git config --global --add safe.directory /var/www/html

COPY docker/start-container /usr/local/bin/start-container
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php.ini /etc/php/8.5/cli/conf.d/99-sail.ini
COPY --from=composer_stage /usr/bin/composer /usr/bin/composer
RUN chmod +x /usr/local/bin/start-container || true

EXPOSE 80/tcp
ENTRYPOINT ["start-container"]
