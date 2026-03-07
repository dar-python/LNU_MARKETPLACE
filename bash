PROJECT
- System: LNU Campus Marketplace (Flutter mobile → Laravel REST API → MySQL/MariaDB, Docker)
- Task: Implement **Auth API (Register/Login/Logout)** with strict **validation** and **consistent JSON error responses**.
- Scope constraints: LNU-only access; manual moderation/approval; NO online payments; NO real-time chat.
- Note: Backend-first only (no Flutter work in this task). Keep commits/changes PR-friendly for later merge.

STACK / ASSUMPTIONS
- Laravel API project already exists.
- Database tables likely already exist from prior migrations:
  users, roles, user_roles, student_id_prefixes, student_verifications
- If any are missing or columns required are missing, create/adjust migrations safely.

HARD RULES
1) Do NOT add out-of-scope features (payments, realtime chat, ratings, delivery, etc).
2) Use token-based auth suitable for mobile API. Prefer **Laravel Sanctum**.
3) Implement clean validation via **FormRequest** classes.
4) Standardize ALL API responses (success + errors) in JSON.

AUTH BEHAVIOR (LNU-ONLY + MANUAL MODERATION)
- Registration must enforce LNU-only as realistically possible without a full student dataset:
  a) student_id is REQUIRED and must be numeric.
  b) student_id prefix check: first 2 digits must exist in student_id_prefixes table.
  c) email is OPTIONAL, but if provided must match allowed domain from config/env (do NOT hardcode a domain).
- Manual moderation:
  - New users default to status = "pending" (or is_active=false), meaning they CANNOT login until approved by admin.
  - Create a student_verifications record on register with status="pending".
  - Login must block if status is pending/suspended/disabled.

ENDPOINTS (v1)
1) POST /api/v1/auth/register
   Request JSON:
   - name (required, string, 2..100)
   - student_id (required, digits length reasonable e.g. 6..12, unique)
   - email (nullable, email, unique, must match allowed domain if not null)
   - password (required, strong rules below, confirmed)
   Response (201):
   {
     "success": true,
     "message": "Registration submitted. Await admin approval.",
     "data": {
        "user": { "id": 1, "name": "...", "student_id": "...", "email": null, "status": "pending" }
     },
     "trace_id": "<uuid>"
   }

2) POST /api/v1/auth/login
   Request JSON:
   - identifier (required)  // can be email OR student_id
   - password (required)
   Behavior:
   - If credentials invalid → 401
   - If account not approved (pending) → 403 with clear message
   - If suspended/disabled → 403
   Response (200):
   {
     "success": true,
     "message": "Login successful.",
     "data": {
        "token": "<plain_text_token>",
        "token_type": "Bearer",
        "user": { "id": 1, "name": "...", "student_id": "...", "email": "...", "status": "approved", "roles": ["user"] }
     },
     "trace_id": "<uuid>"
   }

3) POST /api/v1/auth/logout  (auth:sanctum)
   - Revoke the current access token
   Response (200):
   { "success": true, "message": "Logged out.", "data": null, "trace_id": "<uuid>" }

4) GET /api/v1/auth/me (auth:sanctum)
   Response (200): returns authenticated user + roles.

VALIDATION RULES (minimum)
- student_id:
  - numeric string only
  - length 6..12 (choose a safe range)
  - unique(users.student_id)
  - prefix exists: substr(student_id,0,2) must exist in student_id_prefixes.prefix
- email:
  - nullable
  - unique(users.email)
  - if not null: must be in allowed domains list from config (e.g., config('lnu.allowed_email_domains'))
- name:
  - required, 2..100

PASSWORD POLICY (STRONG/“UNIQUE” QUALITY — NOT “unique across all users”)
- Enforce strong password quality using Laravel Password rule:
  - Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised()
- Also block user-related weak patterns:
  - password must NOT contain the student_id (case-insensitive compare as string)
  - if email is provided: password must NOT contain the email local-part (before @)
  - password must NOT contain the name (case-insensitive; ignore spaces)
- password confirmation is required (confirmed).

IMPLEMENTATION REQUIREMENTS (Password)
- RegisterRequest must implement the above using:
  - Illuminate\Validation\Rules\Password
  - a custom Rule class OR inline closure validator for the “must not contain …” checks
- Return violations using the standard 422 JSON error format.

ROLES
- On register: assign default role "user" (via roles + user_roles tables).
- Ensure a seeder exists that creates roles: "admin", "user".
- (Optional but helpful) create a seeded admin account with password "admin123" ONLY if it does not exist.

SANCTUM SETUP
- Install/configure Laravel Sanctum properly:
  - composer require laravel/sanctum
  - publish config + migrate
  - Add HasApiTokens to User model
  - Protect routes with auth:sanctum
- Use token abilities if helpful (e.g., ["user"] / ["admin"]).

STANDARD JSON ERROR FORMAT (REQUIRED)
For validation (422):
{
  "success": false,
  "message": "Validation failed.",
  "errors": { "field": ["message1", "message2"] },
  "trace_id": "<uuid>"
}

For unauthorized (401):
{
  "success": false,
  "message": "Invalid credentials.",
  "errors": null,
  "trace_id": "<uuid>"
}

For forbidden (403) (pending/suspended):
{
  "success": false,
  "message": "Account not approved yet.",
  "errors": null,
  "trace_id": "<uuid>"
}

For not authenticated (401) when missing/invalid token:
{
  "success": false,
  "message": "Unauthenticated.",
  "errors": null,
  "trace_id": "<uuid>"
}

Implement this consistently via:
- a small Response helper (e.g., app/Support/ApiResponse.php or a trait)
- and/or customizing Exception Handler for API routes:
  - ValidationException → 422 JSON format above
  - AuthenticationException → 401 JSON
  - AuthorizationException → 403 JSON
  - fallback Exception → 500 JSON with generic message (do not leak stack trace in production)

FILES TO CREATE/EDIT (EXPECTED)
- routes/api.php (group prefix v1)
- app/Http/Controllers/Api/V1/AuthController.php
- app/Http/Requests/RegisterRequest.php
- app/Http/Requests/LoginRequest.php
- app/Models/User.php (HasApiTokens, relationships)
- database/seeders/RolesSeeder.php (+ optional AdminSeeder)
- config/lnu.php (allowed_email_domains, student_id_prefix_length=2)
- .env.example (add LNU_ALLOWED_EMAIL_DOMAINS=... as comma-separated)
- app/Exceptions/Handler.php (or Laravel 11 equivalent bootstrap exception config) to enforce JSON errors
- tests/Feature/AuthTest.php (register/login/logout/me) using RefreshDatabase

RELATIONSHIPS (use if tables exist)
- User hasMany student_verifications
- User belongsToMany roles via user_roles

DELIVERABLES
1) Working endpoints above.
2) Curl examples for each endpoint.
3) Migration/seed instructions (exact artisan commands).
4) Feature tests passing: php artisan test

OUTPUT REQUIREMENT
- Provide a concise “What changed” list.
- Provide code edits as a clean patch/diff (or full files if required).
- Ensure everything runs in Docker environment (no host-only assumptions).