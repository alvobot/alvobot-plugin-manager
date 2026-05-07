# Implementation Plan: Pixel Tracking Module

**Branch**: `001-pixel-tracking-module` | **Date**: 2026-02-16 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `specs/001-pixel-tracking-module/spec.md`

## Summary

New toggleable module `pixel-tracking` for `alvobot-plugin-manager` that provides Meta Pixel browser tracking + Facebook Conversion API server-side event dispatch. Inspired by `pixel-x-app` (reference implementation), adapted for AlvoBot's module system, blog-only scope, and dual pixel configuration (AlvoBot connection OR manual entry).

**Cross-project scope**: This feature touches 2 of the 4 ecosystem projects:
1. **`alvobot-plugin-manager`** — New PHP module + JavaScript tracking (bulk of work)
2. **`alvobot-supabase`** — New `get_meta_pixels` action in `api_plugin` Edge Function (small addition)

## Technical Context

**Language/Version**: PHP 8.0+ (WordPress plugin), JavaScript ES2020+ (browser tracking), TypeScript/Deno (Edge Function)
**Primary Dependencies**: WordPress 6.x APIs, Action Scheduler, Meta Pixel SDK (`fbevents.js`), Facebook Conversion API v24.0, ip-api.com geolocation
**Storage**: WordPress `wp_options` (settings), Custom Post Types (events, leads, conversions) via `wp_posts` + `wp_postmeta`
**Testing**: Manual testing via Facebook Pixel Helper + Events Manager; PHPUnit for unit tests
**Target Platform**: WordPress 6.x running on PHP 8.0+
**Project Type**: WordPress plugin module (PHP + JS)
**Performance Goals**: Tracking script adds < 100ms to LCP (verify via Lighthouse during dev); Conversion API dispatch < 5min latency; handles 10,000 pageviews/day (math: ~7 events/min, batch 250 every 5min = 50/min capacity = 7x headroom)
**Constraints**: No external PHP dependencies beyond WordPress + Action Scheduler; no Supabase writes; module must be togglable via dashboard
**Scale/Scope**: ~15 PHP files, ~2 JS files, ~1 Edge Function modification, 3 Custom Post Types

## Testing Strategy

Tests must be written before or alongside implementation, never deferred to the end of a phase.

| Phase | Unit requirements |
|-------|-------------------|
| Phase 1 | CPT registration, post status registration, settings defaults, settings sanitization |
| Phase 2 | Pixel fetch logic, transient caching, token-ref handling, error responses from AlvoBot |
| Phase 3 | Event/lead REST persistence, nonce/origin validation, required-field validation, rate limiting |
| Phase 4 | CAPI payload generation, pixel deduplication, batch splitting, retry/error status handling |
| Phase 5 | Lead upsert by email/fbp/external_id, attribution persistence, PII hashing |
| Phase 6 | Cleanup by age and volume cap, status endpoint, authenticated REST permissions |

Execution checkpoints: each phase must pass PHPUnit before the next phase starts; security-critical paths (nonce/origin validation, capability checks, input sanitization, token handling) require direct unit coverage. Target at least 70% coverage for business logic and 100% for the security-critical paths above.

Fixtures required: browser event payloads, lead payloads, mock AlvoBot pixel responses, CAPI responses for 200/400/429/500, and conversion-rule samples for every trigger type. Integration scenarios required before release: browser event → CPT → CAPI audit trail, multi-pixel delivery, consent denial, expired token backlog, nonce refresh, and rate-limit enforcement.

## Constitution Check

| Principle | Status | Notes |
|-----------|--------|-------|
| Security First (RLS) | N/A for WP module | WordPress nonces + capability checks used instead. No new Supabase tables. |
| No `any` types | N/A | PHP project — `declare(strict_types=1)` used where applicable |
| Fail Fast — No Silent Failures | PASS | Invalid Pixel ID → error. Expired token → admin warning. API failure → retry + status `error` |
| Push-Down Filtering | PASS | Events filtered by `status = pending` in DB query. Batch size 250 at source. |
| YAGNI | PASS | Blog events only. No e-commerce. No payment integrations. No link tracking. |
| Composition Over Inheritance | PASS | Module extends `AlvoBotPro_Module_Base`. Functional approach for tracking logic (like Pixel X). |
| Observability | PASS | `AlvoBotPro::debug_log('pixel-tracking', ...)` everywhere. Event audit trail via post meta. |
| Module Registration | PASS | Standard 4-array pattern in `class-alvobot-pro.php` |
| Cross-Project Contracts | PASS | Only change: new action in `api_plugin` Edge Function (additive, non-breaking) |

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│  BROWSER (Visitor)                                               │
│                                                                   │
│  1. Page loads → PHP injects inline JS (like Pixel X PXA_action) │
│  2. JS: load fbevents.js → fbq('init', PIXEL_ID)                 │
│  3. JS: capture fbp, fbc, UTMs, geolocation                      │
│  4. JS: evaluate conversion rules → fbq('track', EVENT, DATA)    │
│  5. JS: POST event to WP REST API (event_id for dedup)           │
│  6. JS: detect form submit → capture lead → POST lead to WP      │
└──────────────┬──────────────────────────┬────────────────────────┘
               │ POST /alvobot-pro/v1/    │ POST /alvobot-pro/v1/
               │ pixel-tracking/events    │ pixel-tracking/leads
               ▼                          ▼
┌─────────────────────────────────────────────────────────────────┐
│  WORDPRESS (PHP Server)                                          │
│                                                                   │
│  REST Endpoints:                                                  │
│  - Receive events → create CPT `alvobot_pixel_event` (pending)   │
│  - Receive leads → create/update CPT `alvobot_pixel_lead`        │
│                                                                   │
│  Action Scheduler (every 5 min):                                  │
│  - Query events WHERE status = 'pending'                          │
│  - Batch 250 per request → POST graph.facebook.com/v24.0/        │
│    {PIXEL_ID}/events                                              │
│  - Update status → 'sent' or 'error'                             │
│  - Store request/response payloads in post meta                   │
│                                                                   │
│  Action Scheduler (daily):                                        │
│  - Cleanup events/leads older than retention period               │
│  - Cleanup events/leads exceeding volume cap                      │
│                                                                   │
│  Admin Settings Page:                                             │
│  - Pixel config (AlvoBot mode / Manual mode)                      │
│  - Conversion rules CRUD                                          │
│  - Test mode toggle                                               │
│  - Retention settings                                             │
└──────────────┬───────────────────────────────────────────────────┘
               │ wp_remote_post (only for AlvoBot mode)
               │ {site_url, token, action: 'get_meta_pixels'}
               ▼
┌─────────────────────────────────────────────────────────────────┐
│  SUPABASE (Edge Function api_plugin)                              │
│                                                                   │
│  New action: get_meta_pixels                                      │
│  - Auth: validatePluginRequest (site_url + token → project)       │
│  - Query: meta_pixels JOIN connections                             │
│    WHERE is_active = true AND connections.is_active = true         │
│    AND (needs_reconnect IS NULL OR needs_reconnect = false)        │
│  - Return public pixel data; WP admin stores token via token_ref only │
└─────────────────────────────────────────────────────────────────┘
```

### Key Design Decision: api_plugin

The `api_plugin` Edge Function is the established communication channel between the WordPress plugin and Supabase (used for site registration). This function will be extended with action-based routing to support `get_meta_pixels` alongside the existing registration flow. This keeps all WordPress plugin → Supabase communication through a single Edge Function, maintaining the established pattern documented in the spec.

**Decision**: Add action routing and `get_meta_pixels` handler to `api_plugin`. WordPress plugin calls via `wp_remote_post` to `api_plugin` with `action: 'get_meta_pixels'`, `site_url`, and `token`. Authentication uses the existing `site_url + alvobot_site_token` pattern.

## Project Structure

### Documentation (this feature)

```text
specs/001-pixel-tracking-module/
├── spec.md              # Feature specification
├── plan.md              # This file
├── data-model.md        # WordPress CPT schemas + wp_options structure
└── tasks.md             # Actionable task breakdown (next phase)
```

### Source Code Changes

```text
alvobot-plugin-manager/                          # PRIMARY PROJECT
├── alvobot-pro.php                               # (NO CHANGE — constants already defined)
├── includes/
│   ├── class-alvobot-pro.php                     # MODIFY — add pixel-tracking to 4 registration arrays
│   ├── class-alvobot-ai-api.php                  # (NO CHANGE — call() method already supports any action)
│   └── modules/
│       └── pixel-tracking/                       # NEW DIRECTORY
│           ├── class-pixel-tracking.php           # Module main class (extends AlvoBotPro_Module_Base)
│           ├── class-pixel-tracking-cpt.php       # Custom Post Type registration + helpers
│           ├── class-pixel-tracking-rest.php       # REST API endpoints
│           ├── class-pixel-tracking-capi.php       # Conversion API sender (cron/Action Scheduler)
│           ├── class-pixel-tracking-frontend.php   # JS injection (like PXA_action in 06-actions.php)
│           ├── class-pixel-tracking-cleanup.php    # Data cleanup (cron)
│           ├── assets/
│           │   ├── js/
│           │   │   ├── tracking.js                 # Browser tracking (like pxa-base.js, simplified)
│           │   │   └── admin.js                    # Admin settings page JS
│           │   └── css/
│           │       └── admin.css                   # Admin settings page CSS
│           └── views/
│               ├── settings-page.php               # Settings page template
│               └── conversions-page.php            # Conversion rules management template

alvobot-supabase/                                # SECONDARY PROJECT
└── supabase/functions/
    └── api_plugin/
        └── index.ts                              # MODIFY — add action routing + handleGetMetaPixels()
```

**Structure Decision**: WordPress module pattern (consistent with existing modules). PHP classes organized by responsibility (main, CPT, REST, CAPI sender, frontend injection, cleanup). JavaScript split into browser tracking (public) and admin (private).

## Component Design

### 1. Module Main Class — `class-pixel-tracking.php`

**Class**: `AlvoBotPro_PixelTracking extends AlvoBotPro_Module_Base`
**File**: `includes/modules/pixel-tracking/class-pixel-tracking.php`

**Responsibilities**:
- `define_module_properties()` — set `module_id = 'pixel-tracking'`, name, description, icon
- `init()` — wire up all sub-components:
  - Instantiate `AlvoBotPro_PixelTracking_CPT` (CPT registration)
  - Instantiate `AlvoBotPro_PixelTracking_REST` (REST API)
  - Instantiate `AlvoBotPro_PixelTracking_Frontend` (JS injection on public pages)
  - Instantiate `AlvoBotPro_PixelTracking_CAPI` (Conversion API sender)
  - Instantiate `AlvoBotPro_PixelTracking_Cleanup` (data cleanup cron)
- `render_settings_sections($settings)` — settings form (pixel config, test mode, retention, excluded roles)
- `sanitize_settings($settings)` — validate Pixel ID format (numeric, 15-16 digits), sanitize all fields
- Override `render_additional_content($settings)` — conversion rules management section

**Settings structure** (`alvobot_pro_pixel-tracking_settings`):
```php
[
    'mode' => 'alvobot' | 'manual',
    'pixels' => [
        ['pixel_id' => '123...', 'has_api_token' => true, 'encrypted_api_token' => 'enc:v2:...', 'source' => 'alvobot|manual', 'connection_id' => 'uuid|null']
    ],
    'test_mode' => false,
    'test_event_code' => '',
    'excluded_roles' => [],
    'consent_check' => true,
    'consent_cookie' => 'alvobot_tracking_consent',
    'retention_days' => 7,
    'max_events' => 50000,
    'max_leads' => 10000,
]
```

### 2. Custom Post Types — `class-pixel-tracking-cpt.php`

**Class**: `AlvoBotPro_PixelTracking_CPT`

Registers 3 CPTs on `init` hook:

| CPT | Slug | Public | Supports | Purpose |
|-----|------|--------|----------|---------|
| `alvobot_pixel_event` | `alvobot-px-event` | false | title, custom-fields | Captured events (pending → sent → error) |
| `alvobot_pixel_lead` | `alvobot-px-lead` | false | title, custom-fields | Captured leads |
| `alvobot_pixel_conversion` | `alvobot-px-conv` | false | title, custom-fields | Conversion rules |

**Custom statuses** for events (registered via `register_post_status()`):
- `pixel_pending` — awaiting dispatch to Conversion API
- `pixel_sent` — successfully sent
- `pixel_error` — dispatch failed

**Helper methods**:
- `create_event($data)` — creates event CPT with meta fields, returns post ID
- `create_lead($data)` — creates/updates lead CPT (upsert by email or fbp)
- `create_conversion($data)` — creates conversion rule CPT
- `get_pending_events($limit = 250)` — query events with status `pixel_pending`
- `get_conversions()` — query active conversion rules (cached via object cache)
- `mark_events_sent($post_ids)` — batch update status
- `mark_events_error($post_ids, $error)` — batch update with error info

**Integrity pattern**: `wp_insert_post()` must be called with error handling, and CPT creation is considered successful only after required post meta is saved. If required meta persistence fails during creation, the helper deletes the new post with `wp_delete_post($post_id, true)`, logs through `AlvoBotPro::debug_log('pixel-tracking', ...)`, and returns `WP_Error`. REST callers must check this return value and respond with HTTP 500 instead of exposing incomplete records.

**Database indexes and caches**: activation must add indexes only if missing:
- `wp_posts(post_type, post_status, post_date)` for pending dispatch and cleanup queries.
- `wp_postmeta(meta_key, meta_value(191))` for `_event_id`, `_email_hash`, `_phone_hash`, `_fbp`, `_lead_id`, and `_external_id` lookups.

`get_conversions()` and active pixel settings should use short-lived object/transient cache. Pending counts may be cached for up to 5 minutes, but dispatch queries must always read pending IDs from the database immediately before sending.

### 3. REST API Endpoints — `class-pixel-tracking-rest.php`

**Class**: `AlvoBotPro_PixelTracking_REST`
**Namespace**: `alvobot-pro/v1/pixel-tracking`

Two types of endpoints:

**A. Public endpoints** (no auth, used by browser JS):
- `POST /events/track` — receive browser event, create CPT. Permission: `__return_true` (public, but validates nonce from inline JS)
- `POST /leads/track` — receive lead data, create/update CPT. Same permission model.

**B. Authenticated endpoints** (Basic Auth via `authenticate_rest_api_request()`):

| Method | Endpoint | Callback | Purpose |
|--------|----------|----------|---------|
| GET | `/settings` | `get_settings` | Return module config |
| POST | `/settings` | `update_settings` | Update module config |
| GET | `/settings/pixels` | `get_pixels` | List configured pixels |
| POST | `/settings/pixels` | `add_pixel` | Add pixel |
| DELETE | `/settings/pixels/(?P<pixel_id>\d+)` | `remove_pixel` | Remove pixel |
| GET | `/conversions` | `get_conversions` | List conversion rules |
| GET | `/conversions/(?P<id>\d+)` | `get_conversion` | Get single conversion |
| POST | `/conversions` | `create_conversion` | Create conversion |
| PUT | `/conversions/(?P<id>\d+)` | `update_conversion` | Update conversion |
| DELETE | `/conversions/(?P<id>\d+)` | `delete_conversion` | Delete conversion |
| GET | `/events` | `list_events` | List events (filterable) |
| GET | `/events/(?P<event_id>[a-f0-9-]+)` | `get_event` | Get event detail |
| GET | `/events/stats` | `get_event_stats` | Aggregated counts |
| GET | `/leads` | `list_leads` | List leads (filterable) |
| GET | `/leads/(?P<lead_id>[a-f0-9-]+)` | `get_lead` | Get lead detail |
| GET | `/leads/stats` | `get_lead_stats` | Aggregated counts |
| GET | `/status` | `get_module_status` | Overall health |
| POST | `/actions/send-pending` | `force_send_pending` | Trigger immediate dispatch |
| POST | `/actions/cleanup` | `force_cleanup` | Trigger cleanup |
| POST | `/actions/refresh-pixels` | `refresh_alvobot_pixels` | Clear transient, re-fetch |
| POST | `/actions/test-pixel` | `send_test_event` | Send test event to Facebook |

**Auth pattern**: Authenticated endpoints use `permission_callback => function() { return current_user_can('manage_options'); }` — the existing `authenticate_rest_api_request()` in `class-alvobot-pro.php` already handles Basic Auth with `alvobot:{site_token}` and sets the user.

**Public tracking endpoints**:
- Use a nonce embedded in inline JS (`wp_create_nonce('alvobot_pixel_tracking')`) for logged-in cookie-auth contexts and origin/referer validation for public tracking calls.
- Add `GET /nonce` for nonce refresh when a cached page serves an expired nonce; it requires a valid session cookie and returns a fresh `alvobot_pixel_tracking` nonce.
- Enforce CORS to the current site origin only (`home_url()` host), never `*`.
- Rate limiting uses transients by endpoint and client fingerprint: `alvobot_px_rate_{endpoint}_{ip_hash}_{ua_hash}`. Limits: events 100/min, leads 20/min. Return 429 with `Retry-After` and log the violation.
- Suspicious bursts from the same IP with many user agents should create a 15-minute temporary block and admin warning.
- Missing required fields return 400 and do not create CPT records.

### 4. Conversion API Sender — `class-pixel-tracking-capi.php`

**Class**: `AlvoBotPro_PixelTracking_CAPI`

**Action Scheduler pattern** (same as Pixel X):
- Recurring action: `alvobot_pixel_send_events` every 5 minutes
- On trigger: query `get_pending_events(250)`, batch send, update statuses
- If batch fails: split into sub-batches of 50, retry each
- If sub-batch fails: mark individual events as `pixel_error` with error message

**Conversion API call** (same as Pixel X `PXA_send_event_to_server()`):
```php
wp_remote_post("https://graph.facebook.com/v24.0/{$pixel_id}/events", [
    'body' => json_encode([
        'data' => $events_batch,        // array of event objects, max 250
        'access_token' => $api_token, // runtime-only; removed from debug payloads before persistence
        'test_event_code' => $test_code, // only if test mode active
    ]),
    'headers' => ['Content-Type' => 'application/json'],
    'timeout' => 30,
]);
```

**Event payload format** (per Facebook Conversion API spec):
```php
[
    'event_name' => 'Lead',
    'event_time' => 1708100000,
    'event_id' => 'uuid-for-dedup',
    'event_source_url' => 'https://example.com/contato',
    'action_source' => 'website',
    'user_data' => [
        'em' => [hash('sha256', $email)],
        'ph' => [hash('sha256', $phone)],
        'fn' => [hash('sha256', $first_name)],
        'ln' => [hash('sha256', $last_name)],
        'ct' => [hash('sha256', $city)],
        'st' => [hash('sha256', $state)],
        'zp' => [hash('sha256', $zip)],
        'country' => [hash('sha256', $country_code)],
        'fbc' => $fbc,
        'fbp' => $fbp,
        'client_ip_address' => $ip,
        'client_user_agent' => $user_agent,
        'external_id' => [hash('sha256', $external_id)],
    ],
    'custom_data' => [
        'content_name' => $content_name,
    ],
]
```

**Concurrency protection**: `process_pending_events()` must acquire an atomic lock before reading pending events. Use `add_option('alvobot_pixel_capi_lock', ...)` with `autoload = no`, treat locks older than 300 seconds as stale, and release the lock in `finally`. Real-time triggers that hit an active lock must schedule a short retry instead of sending concurrently.

**Multiple pixels**: If site has multiple pixels configured, events are sent to all unique Meta pixel IDs. Deduplicate by `pixel_id` before sending, keep the first valid configuration, and log skipped duplicates. Settings sanitization should also reject duplicate `pixel_id` rows and warn the admin to prevent double-counted conversions.

**Audit trail**: After each API call, store request payload and response in event post meta:
- `_fb_request_payload` — serialized request
- `_fb_response_payload` — serialized response
- `_fb_sent_at` — timestamp
- `_fb_pixel_id` — which pixel received it

### 5. Frontend JS Injection — `class-pixel-tracking-frontend.php`

**Class**: `AlvoBotPro_PixelTracking_Frontend`

**Hook**: `wp_head` (priority 10) — same approach as Pixel X `06-actions.php`

**Logic flow** (mirrors Pixel X `PXA_action()`):
1. Check module active + pixels configured
2. Check current user is not excluded (FR-017 configurable roles)
3. Check not a bot (user-agent string matching)
4. Query active conversion rules for current page
5. Generate inline `<script>` with:
   - Configuration object (pixel_ids, API endpoints, nonce, page context)
   - Load `tracking.js` (the base class)
   - Initialize tracking + fire conversion events

**Inline script generation** (like Pixel X):
```php
// In wp_head:
<script>
  var alvobot_pixel_config = {
    pixel_ids: '<?php echo esc_js($pixel_ids); ?>',
    api_event: '<?php echo esc_url(rest_url('alvobot-pro/v1/pixel-tracking/events/track')); ?>',
    api_lead: '<?php echo esc_url(rest_url('alvobot-pro/v1/pixel-tracking/leads/track')); ?>',
    nonce: '<?php echo wp_create_nonce('alvobot_pixel_tracking'); ?>',
    page_id: '<?php echo esc_js($page_id); ?>',
    page_title: '<?php echo esc_js($page_title); ?>',
    consent_cookie: '<?php echo esc_js($consent_cookie); ?>',
  };
</script>
<script src="<?php echo esc_url($tracking_js_url); ?>" defer></script>
<script>
  // Inline conversion event triggers (generated per-page like PXA_action)
  document.addEventListener('DOMContentLoaded', async () => {
    if (!window.alvobot_pixel) return;
    <?php echo $conversion_events_js; // Generated per active conversions ?>
  });
</script>
```

### 6. Browser Tracking Script — `tracking.js`

**Global**: `window.alvobot_pixel` (class instance, like `window.pixel_x_app`)

**Structure** (inspired by `pxa-base.js`, simplified for blog scope):

```javascript
class AlvoBotPixelTracking {
  constructor() { this.data = {}; }

  // Initialization
  async start(config)          // Initialize with config from PHP inline
  async init_fb_sdk(pixel_ids) // Load fbevents.js, fbq('init', ...)

  // Data capture (same as pxa-base.js)
  get_edge_context()           // Optional Cloudflare/request edge context; no dependency on CDN provider
  async get_geolocation()      // Optional configured provider/fallback with timeout and local cache
  get_fbp()                    // Read _fbp cookie or generate fb.1.{ts}.{random}
  get_fbc()                    // Read _fbc cookie or extract from fbclid URL param
  get_utms()                   // Parse all UTM params + src, sck from URL
  get_referrer()               // document.referrer
  get_user_agent()             // navigator.userAgent

  // Event dispatch
  async send_event(params)     // fbq('track'/'trackCustom', ...) + POST to WP REST
  async send_lead_data(form)   // Extract email/name/phone from form, SHA-256 hash, POST to WP

  // Helpers
  generate_event_id()          // crypto.randomUUID() or UUID v4 polyfill
  hash_sha256(value)           // Web Crypto API for PII hashing
  check_consent()              // Check consent cookie/variable
  is_excluded()                // Check if current user should be excluded
}
```

**Consent check**: Before any tracking, check for consent signal:
```javascript
check_consent() {
  // Check cookie
  if (document.cookie.includes(this.config.consent_cookie + '=true')) return true;
  // Check JS variable
  if (window.alvobot_tracking_consent === true) return true;
  // Check common consent plugins (CookieYes, Complianz)
  if (window.CookieYes?.getConsent?.('analytics') === 'yes') return true;
  if (window.cmplz_get_consent?.('statistics') === 'allow') return true;
  // Default: no consent → no tracking
  return false;
}
```

**Event persistence to WP** (FR-036, same as Pixel X):
```javascript
async send_event(params) {
  const event_id = this.generate_event_id();

  // 1. Fire browser-side via fbq
  fbq(params.event_custom ? 'trackCustom' : 'track', params.event_name, {
    content_name: params.content_name,
    event_id: event_id,
  });

  // 2. POST to WordPress for server-side dispatch
  fetch(this.config.api_event, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': this.config.nonce },
    body: JSON.stringify({
      event_id, event_name: params.event_name,
      page_url: location.href, page_title: document.title,
      fbp: this.get_fbp(), fbc: this.get_fbc(),
      ip: this.data.ip, user_agent: navigator.userAgent,
      geo: this.data.geolocation, utms: this.get_utms(),
      lead_id: this.data.lead_id,
      custom_data: { content_name: params.content_name },
      pixel_ids: params.fb_pixels || this.config.pixel_ids,
    }),
    keepalive: true,
  });
}
```

### 7. Data Cleanup — `class-pixel-tracking-cleanup.php`

**Class**: `AlvoBotPro_PixelTracking_Cleanup`

**Action Scheduler**: Daily recurring action `alvobot_pixel_cleanup`

**Dual retention** (FR-034):
1. **Time-based**: Delete events/leads older than `retention_days` (default 7)
2. **Volume-based**: If total events > `max_events`, delete oldest until under limit. Same for leads.

**Implementation**: Use `WP_Query` with `date_query` for time-based, `posts_per_page` + `offset` for volume. Delete in batches of 100 to avoid memory issues.

### 8. Edge Function Change — `api_plugin/index.ts`

The `api_plugin` function needs action routing added. Currently it handles only site registration. We add an `action` field parser to the request body and route to the appropriate handler.

**New handler**: `handleGetMetaPixels(supabase, project, requestId, logger)`

```typescript
async function handleGetMetaPixels(
  supabase: any,
  project: any,
  requestId: string,
  logger: LogManager
): Promise<Response> {
  const { data: pixels, error } = await supabase
    .from('meta_pixels')
    .select(`
      pixel_id,
      pixel_name,
      is_active,
      connection_id,
      connections!inner (
        id,
        access_token, // server-side only, converted to token_ref/encrypted local runtime token
        is_active,
        needs_reconnect,
        connection_name
      )
    `)
    .eq('workspace_id', project.workspace_id)
    .eq('is_active', true)
    .eq('connections.is_active', true)
    .or('needs_reconnect.is.null,needs_reconnect.eq.false', { foreignTable: 'connections' });

  if (error) {
    logger.add('meta_pixels_fetch_error', error.message, 'error');
    return errorResponse('Erro ao buscar pixels', 500, requestId);
  }

  const result = (pixels || []).map((p: any) => ({
    pixel_id: p.pixel_id,
    pixel_name: p.pixel_name,
    has_api_token: Boolean(p.connections?.access_token),
    token_ref: storeShortLivedTokenRef(p.connections?.access_token),
    connection_id: p.connection_id,
    connection_name: p.connections?.connection_name,
  }));

  logger.add('meta_pixels_success', { count: result.length });
  return jsonResponse({ success: true, data: result, request_id: requestId });
}
```

**Action routing addition** in `api_plugin/index.ts`:
```typescript
// Parse action from request body (default: 'register' for backwards compatibility)
const action = body.action || 'register';

switch (action) {
  case 'register':
    // Existing site registration logic (unchanged)
    break;
  case 'get_meta_pixels':
    response = await handleGetMetaPixels(supabase, project, requestId, logger);
    break;
  default:
    response = errorResponse(`Unknown action: ${action}`, 400, requestId);
}
```

### 9. AlvoBot Pixel Fetch (WordPress side)

Uses `wp_remote_post` to `api_plugin` (same pattern as site registration):
```php
$server_url = defined('ALVOBOT_SERVER_URL') ? ALVOBOT_SERVER_URL : '';
$site_token = get_option('alvobot_site_token', '');

$response = wp_remote_post($server_url, [
    'body' => json_encode([
        'action'   => 'get_meta_pixels',
        'site_url' => get_site_url(),
        'token'    => $site_token,
    ]),
    'headers' => ['Content-Type' => 'application/json'],
    'timeout' => 15,
]);

if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
    // Show error, offer manual mode fallback
    return;
}

$body = json_decode(wp_remote_retrieve_body($response), true);
$pixels = $body['data'] ?? [];
// Cache in transient for 15 minutes
set_transient('alvobot_pixel_tracking_pixels', $pixels, 15 * MINUTE_IN_SECONDS);
```

## Module Registration

In `includes/class-alvobot-pro.php`, add to the 4 registration points:

```php
// 1. $module_files array (line ~40)
'pixel-tracking' => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/pixel-tracking/class-pixel-tracking.php',

// 2. $default_modules array (line ~83)
'pixel-tracking' => true,

// 3. $module_classes array (line ~118)
'pixel-tracking' => 'AlvoBotPro_PixelTracking',

// 4. get_module_names() method (line ~167)
'pixel-tracking' => __( 'Pixel Tracking', 'alvobot-pro' ),
```

## Conversion Rule Evaluation (JS side)

Conversion rules are passed from PHP to JS as a config array, same pattern as Pixel X `PXA_action()`:

```php
// In class-pixel-tracking-frontend.php
$conversions = $this->cpt->get_conversions();
foreach ($conversions as $conversion) {
    $trigger = get_post_meta($conversion->ID, '_trigger_type', true);
    $event_name = get_post_meta($conversion->ID, '_event_type', true);
    // ... generate JS based on trigger type
}
```

Each trigger type generates specific JS (exactly like Pixel X switch/case in `06-actions.php`):

| Trigger | JS Pattern |
|---------|------------|
| `page_load` | Direct call: `await window.alvobot_pixel.send_event({...})` |
| `page_time` | `setTimeout(async () => { await window.alvobot_pixel.send_event({...}) }, N * 1000)` |
| `form_submit` | `document.querySelectorAll(selector).forEach(el => el.addEventListener('submit', ...))` with `setInterval` re-scan (for dynamic forms) |
| `click` | `document.querySelectorAll(selector).forEach(el => el.addEventListener('click', ...))` |
| `scroll` | `window.addEventListener('scroll', ...)` with percentage check + once flag |
| `view_element` | `document.addEventListener('scroll', ...)` with `getBoundingClientRect()` check + `pxa_tracked` class |

## Error Handling Strategy

| Scenario | Response |
|----------|----------|
| Invalid Pixel ID format | Settings form: inline validation error, save blocked |
| Site not registered in AlvoBot | Admin UI: message + fallback to manual mode |
| No pixels found in AlvoBot connection | Admin UI: "Nenhum pixel encontrado" + link to app |
| Connection token expired (`needs_reconnect`) | Admin UI: yellow banner. CAPI sender: skip, log warning |
| Conversion API returns 400 | Event marked `pixel_error`, error stored in meta |
| Conversion API returns 429 (rate limit) | Exponential backoff: retry in 5min, 15min, 1hr |
| Conversion API returns 500 | Retry in next 5min cron cycle, max 3 retries |
| Geolocation service down | Fallback chain: ip-api.com → ipapi.co → skip geo (event still tracked) |
| JavaScript blocked (ad blocker) | Server-side events still dispatched via CAPI (events created by other page loads that went through) |
| Action Scheduler not available | Fallback to WP Cron (`wp_schedule_event`) with warning in admin |

## Build Sequence

```
Phase 1: Foundation (no tracking yet)
├── 1.1 Module registration in class-alvobot-pro.php
├── 1.2 Module main class (skeleton)
├── 1.3 CPT registration (3 post types + statuses)
├── 1.4 Settings page (pixel config UI — both modes)
├── ✅ PHPUnit for Phase 1
└── 📝 Commit: feat(pixel-tracking): add foundation

Phase 2: AlvoBot Connection
├── 2.1 Edge Function: add action routing + get_meta_pixels to api_plugin
├── 2.2 WordPress: fetch pixels via wp_remote_post to api_plugin
├── 2.3 Settings UI: pixel selector dropdown (AlvoBot mode)
├── ✅ PHPUnit for Phase 2
└── 📝 Commit: feat(pixel-tracking): connect alvobot pixels

Phase 3: Browser Tracking
├── 3.1 tracking.js (base class: fbp, fbc, UTMs, geo, consent)
├── 3.2 Frontend PHP (inline JS generation from conversions)
├── 3.3 REST endpoints for event/lead persistence (public)
├── 3.4 Conversion rules CRUD (admin UI + REST)
├── ✅ PHPUnit for Phase 3
└── 📝 Commit: feat(pixel-tracking): capture browser events

Phase 4: Conversion API
├── 4.1 CAPI sender class (Action Scheduler + batching)
├── 4.2 SHA-256 hashing helpers
├── 4.3 Retry/error handling logic
├── 4.4 Audit trail (request/response in post meta)
├── ✅ PHPUnit for Phase 4
└── 📝 Commit: feat(pixel-tracking): dispatch capi events

Phase 5: Lead Capture
├── 5.1 Lead detection in tracking.js (form intercept)
├── 5.2 Lead REST endpoint + CPT creation
├── 5.3 Lead upsert logic (by email or fbp)
├── ✅ PHPUnit for Phase 5
└── 📝 Commit: feat(pixel-tracking): capture leads

Phase 6: Operational
├── 6.1 Test mode implementation
├── 6.2 Data cleanup (Action Scheduler daily)
├── 6.3 REST API for app interaction (authenticated endpoints)
├── 6.4 Status/health endpoint
├── ✅ PHPUnit for Phase 6
└── 📝 Commit: feat(pixel-tracking): add operations

Phase 7: Polish
├── 7.1 Bot detection
├── 7.2 Role exclusion
├── 7.3 Admin UI refinements
├── 7.4 Integration testing with Facebook Pixel Helper
├── ✅ Full PHPUnit + lint + static analysis
└── 📝 Commit: chore(pixel-tracking): polish tracking module

Phase 8: Release
├── 8.1 Version bump: plugin header in alvobot-pro.php + ALVOBOT_PRO_VERSION in class-alvobot-pro.php
├── 8.2 CHANGELOG.md entry with tracking changes and migration notes
├── 8.3 CLAUDE.md update for module architecture and operational caveats
├── 8.4 Full PHPUnit suite, lint, PHPStan, and browser/staging verification
├── 8.5 Facebook Pixel Helper + Events Manager staging validation
├── 8.6 Multi-agent review: codex + gemini + agent for high-risk changes
├── 8.7 GitHub release from the changelog commit
└── 8.8 Production deploy only after rollback notes are ready
```

## Complexity Tracking

No constitution violations. All design choices follow existing patterns:
- Module extends `AlvoBotPro_Module_Base` (existing pattern)
- Settings in `wp_options` (existing pattern)
- REST API in `alvobot-pro/v1/` namespace (existing pattern)
- Edge Function action in `api_plugin` (existing pattern)
- CPTs for data storage (same as Pixel X reference)
- Action Scheduler for crons (same as Pixel X reference)
