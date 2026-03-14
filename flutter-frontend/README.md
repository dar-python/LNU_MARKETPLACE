# LNU Student Square Flutter + Laravel (Dev Run Guide)

This guide belongs to the `LNU Student Square` project. Legacy Flutter package
names may still use `flutter_lnu_marketplace` to avoid unnecessary refactors.

## A) Prerequisites

- Docker Desktop running
- Flutter SDK installed and on PATH
- Android Studio with at least one Android emulator OR a physical Android device
- Optional: Xcode + iOS Simulator (macOS only)
- Ports available: `8082` (Laravel web), `3307` (MySQL), `8083` (phpMyAdmin)

## B) Backend Steps (from repo root)

1. Start backend stack:

```bash
docker compose up -d
```

2. First run only (or when schema changed):

```bash
docker compose exec app php artisan migrate --force
```

3. Optional seed data:

```bash
docker compose exec app php artisan db:seed --force
```

4. Verify API is up:

```bash
curl http://localhost:8082/api/ping
```

Expected response:

```json
{"ok":true}
```

## C) Flutter Steps (from `flutter-frontend`)

1. Install dependencies:

```bash
flutter pub get
```

2. Android emulator:

```bash
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8082
```

3. iOS simulator:

```bash
flutter run --dart-define=API_BASE_URL=http://127.0.0.1:8082
```

4. Physical device:

- If `adb reverse tcp:8082 tcp:8082` is configured, run:

```bash
flutter run --dart-define=API_BASE_URL=http://127.0.0.1:8082
```

- Otherwise use host machine LAN IP:

```bash
flutter run --dart-define=API_BASE_URL=http://<LAN_IP>:8082
```

## D) Common Troubleshooting

- Android emulator cannot use `localhost` for host machine; use `10.0.2.2`.
- Physical device can use `127.0.0.1` only when `adb reverse tcp:8082 tcp:8082` is active.
- Without `adb reverse`, physical device must use host LAN IP, not `localhost`.
- If requests fail immediately, confirm containers are up: `docker compose ps`.
- If port conflict occurs, free or remap `8082/3307/8083`.
- Ensure backend `.env` uses `DB_HOST=db` (not `localhost`) in Docker.
- Android HTTP cleartext is enabled in debug manifest only; release should use HTTPS.
- If auth fails unexpectedly, clear app storage/token and log in again.
- If tests fail in container, run from repo root with Docker DB up.

## E) One-Liner (Backend + Android Emulator)

Run from repo root:

```bash
docker compose up -d && cd flutter-frontend && flutter pub get && flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8082
```
