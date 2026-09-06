FROM php:8.2-cli-bookworm

# System deps: zip support for PHP, Python3 (for .py hosting + syntax checks),
# and Node.js (for .js hosting). All pinned to what Debian bookworm ships,
# plus NodeSource for a current Node LTS.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip zip libzip-dev \
        python3 python3-pip python3-venv \
        curl ca-certificates gnupg procps \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . /app

RUN mkdir -p /app/data /app/data/upload_bots \
    && chmod +x /app/entrypoint.sh

EXPOSE 8080

CMD ["/app/entrypoint.sh"]
