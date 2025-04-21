# Makefile

# Set the docker compose command.
# By default, we assume docker-compose (the legacy binary)
# If you use Docker Compose v2, invoke Makefile with:
#   make COMPOSE_CMD="docker compose" <target>
COMPOSE_CMD ?= docker compose

.PHONY: build up down logs test console migrate composer

# Build (or rebuild) the Docker images.
build:
	$(COMPOSE_CMD) build

# Start containers in detached mode.
up:
	$(COMPOSE_CMD) --env-file .env up -d

# Stop and remove containers, networks, etc.
down:
	$(COMPOSE_CMD) down

# Follow logs for all containers.
logs:
	$(COMPOSE_CMD) logs -f

# Run PHPUnit tests inside the currency-php container.
test:
	$(COMPOSE_CMD) exec currency-php bash  -c "./vendor/bin/phpunit"

# Run Symfony console commands.
console:
	$(COMPOSE_CMD) exec currency-php bin/console $(ARGS)

# Run database migrations.
migrate:
	$(COMPOSE_CMD) exec currency-php bin/console doctrine:migrations:migrate

# Run composer commands.
# Example: make composer ARGS="install"
composer:
	$(COMPOSE_CMD) exec currency-php composer $(ARGS)

php:
	$(COMPOSE_CMD) exec -it currency-php bash