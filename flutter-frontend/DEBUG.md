# Flutter Frontend Debug Guide

## Core Commands

Run from `flutter-frontend/`:

```bash
flutter pub get
flutter run -v
flutter test
```

## Base URL Rules

- Android emulator:
  - Use `10.0.2.2` to access host machine services.
  - Example: `--dart-define=API_BASE_URL=http://10.0.2.2:8082`
- Physical device:
  - Use your computer LAN IP on the same Wi-Fi network.
  - Example: `--dart-define=API_BASE_URL=http://192.168.1.50:8082`
- iOS simulator:
  - Usually `127.0.0.1` / `localhost` works for host services.

## Debug Logging Toggle

Network logs are disabled by default and only run in debug builds when explicitly enabled.

- Enable:
  - `--dart-define=ENABLE_NETWORK_DEBUG_LOGS=true`
- Includes:
  - Request logs (`method`, `url`, auth header attached/not attached)
  - Response logs (`status`, `url`, truncated body snippet)
  - Error logs and mapped error output for `401`, `403`, `422`, and `5xx`

Example:

```bash
flutter run -v \
  --dart-define=API_BASE_URL=http://10.0.2.2:8082 \
  --dart-define=ENABLE_NETWORK_DEBUG_LOGS=true
```

## Auth + Verification Checks

- Token attachment:
  - Confirm logs show `auth=attached` after login.
- Logout reset:
  - Confirm logout clears session and returns user to login screen.
- Unverified flow:
  - Backend `EMAIL_NOT_VERIFIED` response should navigate to OTP verification screen.

## Common Issues

- Wrong `API_BASE_URL`:
  - Emulator/device cannot reach backend.
- CORS / proxy mismatch:
  - Check backend host/port and reverse-proxy exposure.
- Token not attached:
  - Ensure token exists in secure storage and request interceptor runs.
- Null/invalid JSON mapping:
  - Check backend envelope fields (`success`, `message`, `data`, `errors`) and client parsing.
- Endpoint not found:
  - Verify route exists (`/api/v1/auth/*`) and that app points to the correct backend instance.

## Manual QA Checklist

- Register with valid LNU student ID and domain.
- Login with verified account and confirm profile loads.
- Login with unverified account and confirm OTP screen opens.
- Logout and confirm protected calls are rejected until next login.
