# Currency Exchange API

A Symfony application that fetches currency exchange rates (EUR‑base) from the Frankfurter API, stores them in MySQL, caches them in Redis, and exposes a REST endpoint.

---

## Features

- **Console command**: `app:currency:rates` (cron‑ready) to fetch and persist rates daily
- **Redis caching**: avoids repeated DB/API hits for hot data
- **MySQL persistence**: full history of fetched rates
- **REST API**:
  ```http
  GET /api/exchange-rates
    ?base_currency=EUR
    &target_currencies=USD,GBP,JPY
  ```
- **API documentation** with NelmioApiDocBundle (Swagger UI)
- **100% test coverage** across services, client, command, and controller
- **Dockerized**: PHP 8.4 FPM, MySQL 8.0, Nginx, MailCatcher, phpMyAdmin
- **Makefile** for common tasks

---

## Prerequisites

- Docker & Docker Compose (v2 recommended)
- Git

---

## Getting Started

1. **Clone the repository**

   ```bash
   git clone https://github.com/your-user/currency-api.git
   cd currency-api
   ```

2. **Configure environment** Copy and edit `.env`:

   ```ini
   DATABASE_URL="mysql://${DB_USERNAME}:${DB_PASSWORD}@127.0.0.1:4307/${DB_DATABASE}"
   REDIS_URL=redis://127.0.0.1:6379
   ```

3. **Bring up containers**

   ```bash
   docker compose --env-file .env up -d
   ```

4. **Install PHP dependencies**

   ```bash
   make composer ARGS="install"
   ```

5. **Run database migrations**

   ```bash
   make migrate
   ```

6. **Fetch initial rates**

   ```bash
   make console ARGS="app:currency:rates EUR USD,GBP,JPY"
   ```

7. **Explore the API**

    - Browse Swagger UI: [http://localhost:8001/api/doc](http://localhost:8001/api/doc)
    - Example call:
      ```bash
      curl "http://localhost:8001/api/exchange-rates?base_currency=EUR&target_currencies=USD,GBP"
      ```

8. **Run tests**

   ```bash
   make test
   ```

---

## Makefile Targets

| Target          | Description                                  |
| --------------- | -------------------------------------------- |
| `make build`    | Build Docker images                          |
| `make up`       | Start all containers (detached)              |
| `make down`     | Stop & remove containers                     |
| `make logs`     | Tail logs                                    |
| `make test`     | Run PHPUnit inside PHP container             |
| `make console`  | Run Symfony console commands                 |
| `make migrate`  | Run Doctrine migrations                      |
| `make composer` | Run Composer inside container (specify ARGS) |
| `make php`      | Access PHP container shell                   |

---

## Further Configuration

- **Cron**: schedule `app:currency:rates` at 1 AM daily in your host crontab.
- **Mail**: MailCatcher available at [http://localhost:1080](http://localhost:1080)
- **phpMyAdmin**: [http://localhost:93](http://localhost:93) (login with DB credentials)

---

