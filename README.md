# Spring Courier

This repository contains a small PHP courier client used to create shipments and download labels via the recruitment API provided in the task.

Quick summary
- Source code: `SpringCourier.php` and `spring.php`.
- Documentation: `docs/` (`docs.html`, `task.txt`).
- Files at project root: `Dockerfile`, `docker-compose.yml`, `composer.json`, `.env.example`, `.gitignore`, `README.md`.

Important configuration notes
- Add your API key to a `.env` file at the project root as `API_KEY=...`. Docker Compose reads this file via `env_file: .env`.

Requirements
- Docker Engine
- Docker Compose (or Docker CLI with `docker compose` support)

Run (recommended: Docker Compose)

1. From the project directory:

```bash
cd /home/junnior/Downloads/Valter_Junior
docker compose up --build -d
```

2. Open the runner in your browser:

```
http://localhost:8000/spring.php
```

What the runner does
- `src/spring.php` loads `API_KEY` (from the environment or `.env`) and instantiates `SpringCourier`.
- The runner submits an `OrderShipment` to the recruitment API and, when a `TrackingNumber` is returned, requests the shipment label (`GetShipmentLabel`) and triggers a download.

Useful environment variables
- `API_KEY` — your API key (required).
- `RECRUITMENT_API_URL` — optional override for the API URL (useful for testing or local mocks). Example:

```bash
RECRUITMENT_API_URL=http://localhost:8081/api docker compose up --build -d
```

Logs and debugging

```bash
docker compose logs -f
```

Stop and cleanup

```bash
docker compose down
```

Important files
- `SpringCourier.php` — main client implementing `newPackage()` and `packagePDF()`.
- `spring.php` — runner/entrypoint for demonstration and manual testing.
- `docs/docs.html` — API reference and notes used for the task.
- `docs/task.txt` — original task description.
- `Dockerfile` — Dockerfile to build the application image.
- `docker-compose.yml` — development compose file to run the app locally.