# LNU Student Square

This repository contains the IT-105 Mobile Development project finalized as
`LNU Student Square`.

The authoritative scope is documented in [FINAL_SCOPE.md](FINAL_SCOPE.md).
Some package names, environment defaults, and internal identifiers still use
the earlier working title `LNU Marketplace`; they are being left in place
unless a scope-alignment change specifically requires otherwise.

This repository contains:

- `laravel-backend` - Laravel 12 REST API
- `flutter-frontend` - Flutter mobile app
- `docker-compose.yml` - local development stack

## Backend (Laravel + Docker)

1. Start services:

```bash
docker compose up -d --build
```

2. Install PHP dependencies:

```bash
docker compose exec app composer install
```

3. Prepare environment:

```bash
cp laravel-backend/.env.example laravel-backend/.env
docker compose exec app php artisan key:generate
```

4. Run migrations and seeders:

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

5. Run tests:

```bash
docker compose exec app php artisan test --filter=AuthTest
```

## Frontend (Flutter)

```bash
cd flutter-frontend
flutter pub get
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8082
```

Use `http://127.0.0.1:8082` instead when running on desktop or an iOS
simulator.
