# Laravel Backend Runbook

## Tests (Docker + MySQL)

Run Auth feature tests inside the PHP container so PHPUnit uses the MySQL/MariaDB test connection:

```bash
docker compose exec app php artisan test --filter=AuthTest
```

## Reset DB + Seed (Docker + MySQL)

```bash
docker compose exec app php artisan migrate:fresh --seed
```

## Admin Moderation API (cURL)

```bash
# List pending users
curl -X GET "http://localhost:8080/api/v1/admin/users?status=pending" \
  -H "Authorization: Bearer <ADMIN_TOKEN>" \
  -H "Accept: application/json"

# Approve user
curl -X POST "http://localhost:8080/api/v1/admin/users/{id}/approve" \
  -H "Authorization: Bearer <ADMIN_TOKEN>" \
  -H "Accept: application/json"

# Decline user
curl -X POST "http://localhost:8080/api/v1/admin/users/{id}/decline" \
  -H "Authorization: Bearer <ADMIN_TOKEN>" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"reason":"Incomplete student verification"}'
```
