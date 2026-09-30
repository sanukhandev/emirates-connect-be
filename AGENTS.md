# AGENTS.md

## Project

**Repository:** `sanukhandev/emirates-connect-be`  
**Product:** Emirates Connect  
**Application:** Backend API  
**Stack:** Laravel + MySQL  
**Primary branch:** `main`

This repository is the backend application for Emirates Connect, a UAE-focused professional networking platform for business owners, founders, entrepreneurs, executives, and organizations.

The backend provides APIs consumed by:

- Angular web frontend
- Flutter mobile application
- Administrative interfaces
- Future internal integrations

The backend is included in the parent repository as the `backend` Git submodule.

Parent repository:

```text
git@github.com:sanukhandev/emirates-connect-mvp.git
```

Backend repository:

```text
git@github.com:sanukhandev/emirates-connect-be.git
```

---

# 1. Core Agent Rules

Before modifying this repository, always:

1. Read this file completely.
2. Inspect the current branch.
3. Inspect the working tree.
4. Inspect recent commits.
5. Understand the requested scope.
6. Inspect existing architecture before introducing new patterns.
7. Reuse existing abstractions whenever appropriate.
8. Avoid unrelated refactoring.
9. Never modify the frontend or mobile repositories from this repository.
10. Never modify the parent repository directly from this repository.

Run:

```bash
git status
git branch --show-current
git log --oneline -10
git remote -v
```

Expected branch for direct MVP work:

```text
main
```

Do not create a new branch unless explicitly requested.

Never:

- force push
- rewrite history
- delete user changes
- reset uncommitted work
- commit secrets
- commit `.env`
- commit credentials
- commit private keys
- commit production data

---

# 2. Product Context

Emirates Connect is not intended to be a generic consumer social network.

It is a professional business networking platform focused on the UAE.

Phase 1 MVP includes:

- user registration
- authentication
- onboarding
- professional profiles
- user verification
- business pages
- business administrators
- user posts
- business posts
- comments
- replies to comments
- nested discussion support
- post media
- likes/reactions
- Reels / Shorts
- reporting
- blocking
- notifications
- basic moderation
- discovery
- feed APIs

Phase 1 does not include unless explicitly requested:

- jobs marketplace
- direct messaging
- paid advertising
- subscription billing
- marketplace commerce
- groups
- live streaming
- advanced recommendation ML
- event ticketing
- CRM
- full analytics platform

Do not silently add out-of-scope features.

---

# 3. Backend Architecture

Follow modular Laravel architecture.

Recommended application structure:

```text
app/
├── Domain/
│   ├── Auth/
│   ├── User/
│   ├── Business/
│   ├── Verification/
│   ├── Post/
│   ├── Comment/
│   ├── Media/
│   ├── Reel/
│   ├── Reaction/
│   ├── Notification/
│   ├── Moderation/
│   └── Shared/
│
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── V1/
│   ├── Middleware/
│   ├── Requests/
│   └── Resources/
│
├── Models/
├── Policies/
├── Services/
├── Actions/
├── Jobs/
├── Events/
├── Listeners/
├── Notifications/
├── Exceptions/
└── Support/
```

Exact structure may evolve based on the existing codebase.

Do not restructure working code solely to match this example.

---

# 4. API Versioning

All public application APIs must be versioned.

Base path:

```text
/api/v1
```

Examples:

```text
POST /api/v1/auth/register
POST /api/v1/auth/login
POST /api/v1/auth/logout

GET /api/v1/me
PATCH /api/v1/me

GET /api/v1/users/{id}
GET /api/v1/businesses/{slug}

GET /api/v1/feed

POST /api/v1/posts
GET /api/v1/posts/{id}
DELETE /api/v1/posts/{id}

POST /api/v1/posts/{id}/comments
POST /api/v1/comments/{id}/replies

GET /api/v1/reels
POST /api/v1/reels
```

Breaking changes require a new API version.

Do not introduce breaking API changes silently.

---

# 5. API Response Standard

Use consistent JSON responses.

Success example:

```json
{
  "data": {},
  "meta": {},
  "message": null
}
```

Collection example:

```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 100
  }
}
```

Validation errors should follow a predictable structure.

Example:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": [
      "The email has already been taken."
    ]
  }
}
```

Use Laravel API Resources for serialization.

Do not expose raw Eloquent models directly.

---

# 6. Authentication

Use a secure Laravel-supported authentication method.

For web/mobile API authentication, prefer:

- Laravel Sanctum

Unless architecture explicitly requires another solution.

Authentication must support:

- registration
- login
- logout
- token revocation
- password reset
- verified email where applicable
- account state validation

Never store plaintext passwords.

Use Laravel hashing APIs.

---

# 7. Authorization

Authentication and authorization are separate concerns.

Use:

- policies
- gates
- middleware
- domain authorization rules

Examples:

Only authorized users may:

- edit their profile
- delete their posts
- manage businesses they administer
- publish business posts
- review verification requests
- moderate reported content

Never rely on frontend authorization.

Every privileged action must be enforced server-side.

---

# 8. Core Data Model

Expected primary entities include:

```text
users
profiles
businesses
business_members
verification_requests
posts
post_media
comments
reactions
reels
reel_media
follows
notifications
reports
blocks
audit_logs
```

Additional supporting tables are acceptable when justified.

Use foreign keys where appropriate.

Use indexes for frequently queried columns.

Consider indexing:

```text
users.email
businesses.slug
posts.user_id
posts.business_id
posts.created_at
comments.post_id
comments.parent_id
reels.user_id
reels.created_at
verification_requests.status
notifications.user_id
```

---

# 9. User Profiles

Profiles may contain:

- full name
- display name
- professional headline
- biography
- profile photo
- cover image
- business role
- company
- industry
- location
- website
- LinkedIn/social links where allowed
- verification status

Do not return private/internal fields unless explicitly authorized.

---

# 10. Business Pages

Business pages must be independent entities.

Do not model businesses as special user accounts.

Business should support:

- name
- slug
- logo
- cover image
- description
- industry
- website
- phone
- email
- UAE emirate/location
- verification status
- owner/admin relationships
- business posts

Use a relationship table such as:

```text
business_members
```

Possible roles:

```text
owner
admin
editor
```

Authorization must validate membership before mutations.

---

# 11. Posts

Posts may belong to:

- a user
- a business page

Avoid ambiguous ownership.

A post should clearly identify its actor.

Support:

- text
- image
- multi-image
- video where approved
- timestamps
- moderation state
- visibility state

Do not store huge media blobs directly in MySQL.

---

# 12. Comments and Replies

Support threaded comments.

Recommended structure:

```text
comments
- id
- post_id
- user_id
- parent_id nullable
- body
- created_at
- updated_at
```

`parent_id = null`:

```text
top-level comment
```

`parent_id != null`:

```text
reply
```

Avoid unlimited recursive loading.

API responses should use bounded nesting or pagination.

---

# 13. Reels / Shorts

Reels are short-form video content.

The Laravel server must not become a video streaming server.

Preferred flow:

```text
Client
  ↓
Laravel requests/signs upload
  ↓
Object storage
  ↓
Queue
  ↓
Media validation/transcoding
  ↓
Thumbnail
  ↓
CDN
```

Store metadata in MySQL.

Examples:

```text
storage_key
playback_url
thumbnail_url
duration
width
height
mime_type
file_size
processing_status
```

Potential processing states:

```text
pending
uploaded
processing
ready
failed
rejected
```

Validate:

- file type
- file size
- duration
- ownership
- upload authorization

---

# 14. Feed Architecture

Phase 1 feed should remain understandable and maintainable.

Do not introduce machine-learning recommendation infrastructure unless requested.

Initial feed may use:

- chronological ranking
- followed users
- followed businesses
- recent verified businesses
- simple engagement signals

Any ranking algorithm must be explicit and testable.

---

# 15. Pagination

All potentially large collections must use pagination.

Never return unlimited:

- posts
- comments
- users
- businesses
- reels
- notifications
- reports

Use cursor pagination where appropriate for feeds.

Prefer cursor pagination for:

```text
feed
reels
notifications
```

---

# 16. Media

Do not store media binaries in MySQL.

Use:

- object storage
- cloud storage
- CDN

Use temporary/signed upload URLs when possible.

Laravel owns:

- authorization
- upload intent
- metadata
- ownership
- moderation state

Storage owns:

- file bytes

---

# 17. Validation

Every write endpoint requires validation.

Use Form Request classes.

Example:

```text
CreatePostRequest
UpdateProfileRequest
CreateBusinessRequest
SubmitVerificationRequest
CreateCommentRequest
CreateReelRequest
```

Do not put complex validation directly inside controllers.

---

# 18. Controllers

Controllers should remain thin.

Controllers should coordinate:

```text
Request
  ↓
Validation
  ↓
Authorization
  ↓
Action / Service
  ↓
Resource
  ↓
Response
```

Business logic belongs in domain services/actions where appropriate.

---

# 19. Database Migrations

All schema changes require migrations.

Never manually depend on a developer changing the database.

Migrations must:

- be reversible where practical
- use indexes appropriately
- use proper foreign keys
- avoid unsafe destructive operations

For destructive production migrations, explicitly document risk.

---

# 20. Seeders

Seeders may be used for:

- development accounts
- test businesses
- sample categories
- industries
- controlled lookup data

Do not seed production secrets.

---

# 21. Queues

Use queues for expensive asynchronous work.

Examples:

- email
- push notifications
- video processing
- thumbnail generation
- bulk notification fan-out
- verification processing
- image optimization

Do not block normal API responses on expensive processing.

---

# 22. Redis

Redis may be used for:

- cache
- queues
- throttling
- ephemeral state

Do not treat Redis as the source of truth for critical persistent data.

---

# 23. Security

Security is mandatory.

Validate against common OWASP risks.

Pay special attention to:

- broken access control
- insecure direct object references
- injection
- mass assignment
- rate-limit bypass
- insecure file uploads
- authentication weaknesses
- information leakage
- XSS through user-generated content
- CSRF where relevant
- SSRF
- unsafe redirects

Never trust client IDs without authorization checks.

---

# 24. Mass Assignment

Explicitly define fillable/guarded properties.

Avoid blindly calling:

```php
$model->update($request->all());
```

Prefer validated input:

```php
$model->update($request->validated());
```

---

# 25. Rate Limiting

Rate limit sensitive endpoints.

Especially:

```text
login
register
password reset
verification submission
comments
reactions
reports
media upload initialization
search
```

---

# 26. User-Generated Content

Assume all user-generated content is untrusted.

Sanitize and validate where needed.

Never render or store unsafe HTML without an explicit sanitization strategy.

---

# 27. Privacy

Minimize personally identifiable information.

Never expose:

- password hashes
- access tokens
- internal moderation notes
- private email addresses unless intended
- private phone numbers unless intended
- internal IDs unnecessarily

Follow least-privilege principles.

---

# 28. Verification

User/business verification must have a state machine.

Example:

```text
not_submitted
pending
approved
rejected
expired
```

Verification status must not be writable by normal users.

Only authorized administrative workflows may approve/reject verification.

Store audit history.

---

# 29. Audit Logging

Audit privileged operations such as:

- verification approval
- verification rejection
- business ownership changes
- moderator actions
- account suspension
- content removal

Audit logs should capture:

```text
actor
action
subject
timestamp
relevant metadata
```

---

# 30. Testing

New functionality must include tests.

Prefer:

- feature tests for APIs
- unit tests for domain logic
- authorization tests
- validation tests

Critical APIs must test:

```text
success
unauthenticated
unauthorized
invalid input
not found
edge case
```

Before completion run:

```bash
php artisan test
```

If Pint is configured:

```bash
./vendor/bin/pint --test
```

If static analysis is configured:

```bash
./vendor/bin/phpstan analyse
```

Do not claim tests passed unless they were actually executed.

---

# 31. Performance

Avoid:

- N+1 queries
- unlimited eager loading
- loading full tables
- unnecessary database round trips
- synchronous media processing

Use:

- eager loading
- selective columns
- indexes
- pagination
- caching where justified

Measure before introducing complex optimization.

---

# 32. API Documentation

The API contract should remain consistent with parent documentation.

Parent OpenAPI location:

```text
docs/api/openapi.yaml
```

When backend API changes materially:

1. implement the backend change
2. test it
3. commit backend
4. push backend
5. update parent OpenAPI documentation
6. update the parent backend submodule pointer

Do not silently create API behavior inconsistent with documented contracts.

---

# 33. Environment Configuration

Use environment variables for:

- database credentials
- Redis
- storage
- mail
- external APIs
- application secrets

Never hardcode credentials.

Allowed:

```text
.env.example
```

Forbidden:

```text
.env
production.env
secret files
private keys
```

---

# 34. Logging

Logs must not contain:

- passwords
- access tokens
- authorization headers
- sensitive PII
- private documents

Use structured, useful logs.

---

# 35. Error Handling

Do not expose stack traces in production API responses.

Use predictable exception handling.

Return correct HTTP codes.

Examples:

```text
200 OK
201 Created
204 No Content
400 Bad Request
401 Unauthorized
403 Forbidden
404 Not Found
409 Conflict
422 Unprocessable Entity
429 Too Many Requests
500 Internal Server Error
```

---

# 36. Git Commit Convention

Prefer Conventional Commits.

Examples:

```text
feat: add business profile creation
feat: add threaded comments API
fix: enforce business admin authorization
refactor: extract feed query service
test: add verification authorization coverage
chore: configure queue worker
docs: update API documentation
```

Keep commits focused.

---

# 37. Parent Submodule Workflow

After backend work is complete:

```bash
git add .
git commit -m "feat: ..."
git push origin main
```

Then from parent repository:

```bash
git add backend
git commit -m "chore: update backend submodule"
git push origin main
```

Never update the parent pointer before the backend commit has been pushed.

---

# 38. Definition of Done

A backend task is complete only when:

- implementation matches requirements
- validation exists
- authorization exists
- tests exist where appropriate
- tests pass
- migrations are valid
- no secrets are committed
- API output is stable
- documentation impact is identified
- code is committed
- backend `main` is pushed
- working tree is clean

Final report should include:

```text
Task:
Status:

Files changed:

Database changes:

API changes:

Security considerations:

Tests executed:

Test result:

Commit:

Branch:

Working tree:
```