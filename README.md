# Spring Courier

This repository contains a simple PHP script and a small courier client class to create shipments and download labels using the provided recruitment API.

Included files
- `spring.php` — runner script that reads `api.key`, creates a shipment and forces the label download.
- `SpringCourier.php` — class implementing `newPackage(array $order, array $params)` and `packagePDF(string $trackingNumber)`.
- `api.key` — your API key (already present in the project root).
- `Dockerfile`, `docker-compose.yml` — containers to run the application.

Requirements
- Docker Engine
- Docker Compose (if using `docker-compose.yml`) or Docker CLI supporting `docker compose`.

Build and run (recommended)

1. Build and start with Docker Compose

```bash
This repository contains a small PHP courier client and a runner that implement the recruitment task:
- `SpringCourier.php`: class implementing `newPackage(array $order, array $params): array` and `packagePDF(string $trackingNumber): void`.
- `spring.php`: runner that reads the API key, creates a shipment and downloads the label.
- `Dockerfile`, `docker-compose.yml`: configuration to run the application container.

Prerequisites
- Docker Engine
- Docker Compose (or `docker compose` CLI)

Configuration (`.env`)
- Put your API key in the `.env` file at the project root as `API_KEY=...`.
- The project will prefer `API_KEY` from the environment. A `.gitignore` file is included to avoid committing `.env` or `api.key`.

Run the application with Docker Compose

1. Start the container:

```bash
cd /home/junnior/Downloads/Valter_Junior
docker compose up --build -d
```

2. Open in your browser:

```
http://localhost:8000/spring.php
```

What happens
- `spring.php` reads `API_KEY` from `.env` and constructs a `SpringCourier` instance.
- By default the application uses the real recruitment API endpoint `https://developers.baselinker.com/recruitment/api`.

Overriding the API endpoint (optional)
- If you need to point the application to a different API URL (for testing), set the environment variable `RECRUITMENT_API_URL` before running the container. Example (temporary override):

```bash
RECRUITMENT_API_URL=http://your-test-api:8080/api docker compose up --build -d
```

Logs and debugging
- Follow logs for both services:

```bash
docker compose logs -f
```

Cleaning up

```bash
docker compose down
```

Security note
- Do not commit `.env` or any file containing API keys to a public repository. Use the provided `.gitignore`.

Further improvements (optional)
- Add unit tests to validate field truncation and mapping.
- Make the mock produce more realistic validation errors for negative testing.

Code style (PSR-12)

This project includes a `.php-cs-fixer.dist.php` configuration to help enforce PSR-12 and common formatting rules.

Run PHP-CS-Fixer using one of the following methods:

- Using the official phar (recommended):

```bash
curl -L https://cs.symfony.com/download/php-cs-fixer-v3.phar -o php-cs-fixer.phar
php php-cs-fixer.phar fix ./ --config=.php-cs-fixer.dist.php
```

- Using Docker (no local install required):

```bash
docker run --rm -v $(pwd):/app -w /app ergebnis/php-cs-fixer:3 php-cs-fixer fix ./ --config=.php-cs-fixer.dist.php
```

The fixer will reformat files in place according to the rules in `.php-cs-fixer.dist.php`.

If you want, I can add a convenience script or a `composer.json` with a `scripts` entry to run the fixer easily.
