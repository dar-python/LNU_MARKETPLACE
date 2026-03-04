# Laravel Backend Debug Guide

## Quick Start

Run from repository root (`g:\APP-DEV-FILES\LNU_MARKETPLACE`):

```bash
docker compose up -d
docker compose exec app php artisan test
docker compose logs -f app
```

## Common Failures and Where to Check

- `422 Validation failed.`:
  - Check request payload shape and field names against request classes in `app/Http/Requests`.
  - Check response `errors` object for exact failing fields.
- `401 Unauthenticated.`:
  - Check `Authorization: Bearer <token>` header.
  - Confirm token exists in `personal_access_tokens` and is not revoked.
- `403 Forbidden.`:
  - Check account state (`users.status` / `users.account_status`) and verification state.
  - Confirm middleware and policy checks for authenticated routes.
- `500 Server error.`:
  - Inspect `laravel-backend/storage/logs/laravel.log`.
  - Inspect container logs: `docker compose logs -f app`.

## Test Commands

- Run all tests:
  - `docker compose exec app php artisan test`
- Run auth feature tests only:
  - `docker compose exec app php artisan test --filter=AuthTest`
- Run listing-related feature tests:
  - `docker compose exec app php artisan test --filter=ListingsApiTest`
  - `docker compose exec app php artisan test --filter=ListingImagesApiTest`

## Auth Debugging Checklist

- Token format:
  - Ensure `Authorization` header is exactly `Bearer <plain_text_token>`.
- Guard/middleware:
  - Protected auth endpoints use `auth:sanctum`.
  - Confirm route registration in `routes/api.php`.
- Login status gates:
  - `EMAIL_NOT_VERIFIED` path returns `403`.
  - Suspended users return `403`.
  - Wrong password returns `401`, not `500`.
- Verify route inventory:
  - `docker compose exec app php artisan route:list --path=api`

## Storage Debugging Checklist

- Public storage symlink:
  - `docker compose exec app php artisan storage:link`
- Disk config:
  - Verify `config/filesystems.php` and `FILESYSTEM_DISK` env.
- Permissions:
  - Confirm writable `storage/` and `bootstrap/cache/` in container.
- Upload failures:
  - Confirm request is multipart/form-data.
  - Confirm max upload size constraints in validation and PHP settings.

## Reproducible cURL Templates

Base URL examples assume the backend is exposed at `http://localhost:8080`.
If your compose ports differ, replace host/port accordingly.

### Register

```bash
curl -X POST "http://localhost:8080/api/v1/auth/register" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "name":"Jane Doe",
    "student_id":"2301234",
    "email":"2301234@lnu.edu.ph",
    "password":"Safe!Pass123",
    "password_confirmation":"Safe!Pass123"
  }'
```

### Login

```bash
curl -X POST "http://localhost:8080/api/v1/auth/login" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "identifier":"2301234",
    "password":"Safe!Pass123"
  }'
```

### Authenticated `me`

```bash
curl -X GET "http://localhost:8080/api/v1/auth/me" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>"
```

### Logout

```bash
curl -X POST "http://localhost:8080/api/v1/auth/logout" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>"
```

### Verify OTP

```bash
curl -X POST "http://localhost:8080/api/v1/auth/email/otp/verify" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "identifier":"2301234@lnu.edu.ph",
    "otp":"123456"
  }'
```

### Resend OTP

```bash
curl -X POST "http://localhost:8080/api/v1/auth/email/otp/resend" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "identifier":"2301234@lnu.edu.ph"
  }'
```

## Route Gap Note

- Current route inventory in this branch contains auth routes only.
- `ListingsApiTest` and `ListingImagesApiTest` are route-aware and will skip when listing/image routes are not registered.
