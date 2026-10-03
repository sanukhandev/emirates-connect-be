## Emirates Connect API

Laravel 12 REST API for Emirates Connect. APIs are versioned under `/api/v1`.

### Authentication

Sanctum uses its recommended hybrid model. Angular is a first-party SPA using the session cookie and CSRF flow; Flutter uses personal access bearer tokens from the mobile token endpoint. SPA register/login return only the user resource. Mobile tokens are named after the supplied device, logout revokes only the current mobile token, and password reset revokes all mobile tokens and database-backed sessions.

| Method | Endpoint | Authentication | Purpose |
| --- | --- | --- | --- |
| POST | `/api/v1/auth/register` | Public | Create an account |
| POST | `/api/v1/auth/login` | SPA session | Authenticate the Angular SPA |
| POST | `/api/v1/auth/mobile/token` | Public | Issue a device-named Flutter token |
| POST | `/api/v1/auth/logout` | SPA session or bearer token | End the current authentication context |
| GET | `/api/v1/me` | SPA session or bearer token | Read the current account |
| PATCH | `/api/v1/me` | SPA session or bearer token | Update name or email |
| POST | `/api/v1/auth/forgot-password` | Public | Request reset instructions |
| POST | `/api/v1/auth/reset-password` | Public | Set a new password |
| POST | `/api/v1/auth/email/verification-notification` | SPA session or bearer token | Resend verification mail |
| GET | `/api/v1/auth/verify-email/{id}/{hash}` | Signed URL | Verify an email address |

Start an Angular session with `GET /sanctum/csrf-cookie`, then send credentialed requests with the XSRF cookie/header. Password reset and email verification mail are faked in automated tests; configure the application mailer for local/manual use.

Security controls and the production gate are documented in the parent repository under `docs/security/`. Registration, verification submission, reporting, admin mutations and signed verification-document access are rate-limited; production must override local debug, cookie, HTTPS, CORS and infrastructure settings before deployment.

### Profiles and onboarding

`users` stores authentication/account identity; `profiles` stores professional/public identity. A profile is created during registration and backfilled lazily for legacy users. Profile edits and media mutations are limited to the authenticated user, while public profiles omit email and security data.

| Method | Endpoint | Authentication | Purpose |
| --- | --- | --- | --- |
| GET | `/api/v1/me/profile` | SPA session or bearer token | Read the current professional profile |
| PATCH | `/api/v1/me/profile` | SPA session or bearer token | Update profile fields |
| POST | `/api/v1/me/onboarding/complete` | SPA session or bearer token | Validate and mark onboarding complete |
| POST/DELETE | `/api/v1/me/profile/avatar` | SPA session or bearer token | Replace/remove avatar metadata and media |
| POST/DELETE | `/api/v1/me/profile/cover-image` | SPA session or bearer token | Replace/remove cover metadata and media |
| GET | `/api/v1/users/{user}` | Public | Read a safe public profile |
| GET | `/api/v1/meta/industries` | Public | List controlled industries |
| GET | `/api/v1/meta/emirates` | Public | List UAE emirates |

### Business pages and memberships

Businesses are professional pages operated by authenticated users through `owner`, `admin` and `editor` memberships. A business is not an authentication identity. Business slugs are generated at creation and remain stable when the name changes. Public pages expose only active businesses; owners can deactivate a page without deleting its history.

| Method | Endpoint | Authentication | Purpose |
| --- | --- | --- | --- |
| POST | `/api/v1/businesses` | Active SPA session or bearer token | Create a business and its owner membership transactionally |
| GET | `/api/v1/businesses/{slug}` | Public | Read an active public business page |
| PATCH/DELETE | `/api/v1/businesses/{slug}` | Owner or admin / owner | Update or deactivate a business |
| GET | `/api/v1/me/businesses` | Active authenticated user | List the user's businesses and current roles |
| GET/POST | `/api/v1/businesses/{slug}/members` | Owner or admin | List or add business members |
| PATCH/DELETE | `/api/v1/businesses/{slug}/members/{member}` | Role-authorized member manager | Change a role or remove a non-owner member |
| POST/DELETE | `/api/v1/businesses/{slug}/logo` | Owner or admin | Replace or remove the business logo |
| POST/DELETE | `/api/v1/businesses/{slug}/cover-image` | Owner or admin | Replace or remove the business cover image |

### Posts and media

Posts use one polymorphic publishing model for authenticated users and active businesses. User authorship is always resolved from the session; business authorship requires owner, admin or editor membership. Posts support plain text, drafts/published status, and up to four JPEG/PNG/WebP images (8 MB each). Public routes expose published posts only; `/me/posts` includes the current user's drafts.

| Method | Endpoint | Authentication | Purpose |
| --- | --- | --- | --- |
| POST | `/api/v1/posts` | Active authenticated user | Create a user or authorized business post, including optional images |
| GET | `/api/v1/posts/{post}` | Public / authorized draft viewer | Read a visible post |
| PATCH/DELETE | `/api/v1/posts/{post}` | Owner or authorized business member | Update or soft-delete a post |
| POST/DELETE | `/api/v1/posts/{post}/media[/{media}]` | Owner or authorized business member | Add or remove post image media |
| GET | `/api/v1/me/posts` | Active authenticated user | List own published and draft posts |
| GET | `/api/v1/users/{user}/posts` | Public | List published posts by user |
| GET | `/api/v1/businesses/{slug}/posts` | Public | List published posts by active business |

### Comments and replies

Comments use one polymorphic `comments` table for user and business publishing identities. Replies use `parent_id` and are limited to one level; comment bodies are plain text up to 2,000 characters. Public listings expose visible top-level comments with visible replies, ordered oldest first and paginated at 20 per page. Current business membership authorizes business comment edits/deletes, so former members lose access.

| Method | Endpoint | Authentication | Purpose |
| --- | --- | --- | --- |
| GET/POST | `/api/v1/posts/{post}/comments` | Public / active authenticated user for POST | List visible comments or create a top-level comment |
| POST | `/api/v1/comments/{comment}/replies` | Active authenticated user | Create one-level reply |
| PATCH/DELETE | `/api/v1/comments/{comment}` | Comment author or current business owner/admin/editor | Edit or soft-delete a comment |

### Reactions

Active authenticated human users can set, switch, or remove one reaction on a visible post, comment, or reply. Supported types are `like`, `celebrate`, `support`, and `insightful`; post and comment resources expose stable counts plus the current user's reaction. Business identities cannot react, and reactions do not affect feed ranking or notifications.

| Method | Endpoint | Authentication | Purpose |
| --- | --- | --- | --- |
| PUT/DELETE | `/api/v1/posts/{post}/reaction` | Active authenticated user | Set/switch or remove the current user's reaction on a visible post |
| PUT/DELETE | `/api/v1/comments/{comment}/reaction` | Active authenticated user | Set/switch or remove the current user's reaction on a visible comment or reply |

### Follows

Follows are directional edges from an authenticated human user to a visible user or active business. Repeated follow/unfollow requests are idempotent; businesses cannot be followers, and the feed remains chronological rather than follow-personalized.

| Method | Endpoint | Authentication | Purpose |
| --- | --- | --- | --- |
| PUT/DELETE | /api/v1/users/{user}/follow | Active authenticated user | Follow or unfollow a visible user; self-follow is rejected |
| PUT/DELETE | /api/v1/businesses/{slug}/follow | Active authenticated user | Follow or unfollow an active business |
| GET | /api/v1/users/{user}/followers | Public | Paginated visible user followers |
| GET | /api/v1/users/{user}/following | Public | Paginated heterogeneous user/business targets |
| GET | /api/v1/businesses/{slug}/followers | Public | Paginated visible user followers of an active business |

### Feed

The Phase 1 feed is an authenticated global chronological discovery feed over eligible published posts. It is not follow-based or ranked yet. It uses cursor pagination with a default page size of 20 and a maximum of 50.

| Method | Endpoint | Authentication | Purpose |
| --- | --- | --- | --- |
| GET | `/api/v1/feed?per_page=20&cursor=...` | Active authenticated user | List published user and active-business posts ordered by `published_at` then `id` descending |

### Search and discovery

Search is public, MySQL-backed and rate-limited. It covers only visible users/profiles and active businesses; it does not search posts, personalize ranking or expose private verification state. `q` is optional for filter-only discovery, but keyword queries must contain at least two characters. Results use deterministic exact/prefix/contains relevance followed by verified status, name and id, with normal pagination (20 by default, 50 maximum).

| Method | Endpoint | Authentication | Purpose |
| --- | --- | --- | --- |
| GET | `/api/v1/search` | Public | Unified user/business search with `type=all\|users\|businesses`, `q`, `industry`, `emirate`, `verified`, `page` and `per_page` |
| GET | `/api/v1/search/users` | Public | Public professional profile search |
| GET | `/api/v1/search/businesses` | Public | Active public business search |

Search uses bound queries with escaped LIKE wildcards and a 60 requests/minute throttle. Search results expose only public profile/business fields and `is_verified`; follower state and counts are intentionally omitted to avoid per-result queries.

### Notifications

Notifications are database-backed, user-targeted and created from successful follow, comment/reply, reaction and verification events. They expose structured actor/subject metadata only; system-admin identity and verification evidence remain private.

| Method | Endpoint | Authentication | Purpose |
| --- | --- | --- | --- |
| GET | `/api/v1/notifications` | Active authenticated user | Cursor-paginated notifications; optional `unread=true` |
| GET | `/api/v1/notifications/unread-count` | Active authenticated user | Count the current user's unread notifications |
| PATCH | `/api/v1/notifications/{id}/read` | Active authenticated user | Mark one owned notification read |
| POST | `/api/v1/notifications/read-all` | Active authenticated user | Mark all owned notifications read |

Notification reads are recipient-scoped and ordered newest first. Delivery is synchronous in the MVP, with domain events keeping a future queued listener path open. No push, email, SMS or realtime delivery is included.

### Reporting and moderation

Authenticated active users can report a visible user, business, post, comment or reel with a controlled reason. Reports are deduplicated per reporter and target while unresolved and limited to 10 per hour. System admins review reports through `/api/v1/admin/reports`; business administrators are not platform moderators. Dismissals and moderation actions are append-only audited, and moderation uses existing soft-delete/account-status behavior without automatic thresholds, AI moderation or reporter notifications.

| Method | Endpoint | Authentication | Purpose |
| --- | --- | --- | --- |
| POST | `/api/v1/reports` | Active authenticated user | Create a pending report |
| GET | `/api/v1/me/reports` | Active authenticated user | List the caller's reports without reviewer/internal data |
| GET | `/api/v1/admin/reports` | Active system admin | Paginated queue with status/type/reason filters |
| GET | `/api/v1/admin/reports/{report}` | Active system admin | View report, safe target summary and audit history |
| PATCH | `/api/v1/admin/reports/{report}` | Active system admin | Dismiss or apply a target-appropriate moderation action |

### Admin Console API

The platform Admin Console consumes system-admin-only endpoints under `/api/v1/admin/*` for dashboard counts, mixed user/business verification administration and audit, the EC-015 moderation queue/audit, and bounded user/business operational lists/details. Direct user or business suspension requires an explicit reason and is audited; system-admin accounts cannot be suspended through the generic action. Business memberships do not grant platform-admin access, and no impersonation, password administration or generic database editing endpoint is provided.

| Method | Endpoint | Authentication | Purpose |
| --- | --- | --- | --- |
| GET | `/api/v1/admin/dashboard` | Active system admin | Operational counts |
| GET | `/api/v1/admin/verifications` | Active system admin | Mixed user/business verification queue |
| GET | `/api/v1/admin/verifications/audit` | Active system admin | Verification audit history |
| GET | `/api/v1/admin/moderation/audit` | Active system admin | Moderation audit history |
| GET | `/api/v1/admin/users` | Active system admin | Filtered operational user list |
| GET | `/api/v1/admin/users/{id}` | Active system admin | Safe user detail |
| POST | `/api/v1/admin/users/{id}/suspend` | Active system admin | Reasoned audited user suspension |
| GET | `/api/v1/admin/businesses` | Active system admin | Filtered operational business list |
| GET | `/api/v1/admin/businesses/{id}` | Active system admin | Safe business detail |
| POST | `/api/v1/admin/businesses/{id}/suspend` | Active system admin | Reasoned audited business suspension |

### Reels

Reels use a dedicated two-step lifecycle: create a reel record, then upload one MP4 video to private source storage. The local MVP processing service promotes the validated source to the public playback disk synchronously; the service boundary is ready for queued transcoding and CDN/object storage later. Only `published` reels are public, and public resources expose playback metadata without source or storage paths.

| Method | Endpoint | Authentication | Purpose |
| --- | --- | --- | --- |
| POST | `/api/v1/reels` | Active authenticated user | Create a user or authorized business reel in `uploading` state |
| POST | `/api/v1/reels/{reel}/video` | Active creator/manager | Store one private MP4 and process it to playback |
| PATCH/DELETE | `/api/v1/reels/{reel}` | Creator or business owner/admin/editor | Update caption or soft-delete a reel |
| GET | `/api/v1/reels` | Public | Chronological published reel feed with cursor pagination |
| GET | `/api/v1/reels/{reel}` | Public / authorized creator | Read a published reel or an authorized non-public reel |
| GET | `/api/v1/me/reels` | Active authenticated user | List the current user's reels, including processing failures |
| GET | `/api/v1/users/{user}/reels` | Public | List published reels for a visible user |
| GET | `/api/v1/businesses/{slug}/reels` | Public | List published reels for an active business |

Accepted uploads are `video/mp4` up to 100 MB. Source files use generated private paths; public playback uses a separate disk. Reels are globally chronological and have no recommendations, reactions, comments, view counts or ranking signals.

---

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
