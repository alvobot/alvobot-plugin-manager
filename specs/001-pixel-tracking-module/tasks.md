# Implementation Tasks: Pixel Tracking Module

**Branch**: `001-pixel-tracking-module` | **Date**: 2026-02-16
**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md) | **Data Model**: [data-model.md](data-model.md)

## Verification and Delivery Gates

Each implementation phase must ship with focused automated coverage plus explicit runtime evidence. A phase is not complete until the related tests pass and the evidence is recorded in the delivery notes.

- [x] Phase 1 automated coverage
  - Add PHPUnit/Brain Monkey tests for CPT helpers, lead title pseudonymization, PII hashing, settings sanitization, duplicate pixel filtering, token reference persistence, and encrypted token round trip/failure handling.
  - Validate custom post meta writes do not expose raw PII in titles or debug payload snapshots.

- [x] Phase 2 automated coverage
  - Add PHPUnit/Brain Monkey tests for `fetch_alvobot_pixels()`, AJAX permission/nonces, API error handling, token-reference transient behavior, and responses that never include raw access tokens.
  - Verify AlvoBot mode and manual mode can coexist without overwriting each other.

- [x] Phase 3 automated coverage
  - Add PHPUnit/Brain Monkey tests for frontend injection, bot/role exclusions, public REST origin validation, rate limiting, attribution payload sanitization, and no-secret inline config.
  - Add JavaScript checks for `alvobotPixelReady`, page-load conversion dispatch with deferred `tracking.js`, lead fetch `keepalive`, and malformed admin JSON attributes.

- [x] Phase 4 automated coverage
  - Add PHPUnit/Brain Monkey tests for CAPI payload building, SHA-256 normalization, external_id/user_data mapping, duplicate pixel dedupe, retry/error handling, token auth expiry, and sanitized request/response storage.
  - Confirm CAPI dispatch has an atomic lock or equivalent concurrency guard before processing pending events.

- [x] Phase 5 automated coverage
  - Add PHPUnit/Brain Monkey tests for lead upsert by email hash and `_fbp`, first-touch/last-touch preservation, click ID attribution, phone normalization, and raw-payload minimization.

- [x] Phase 6 automated coverage
  - Add PHPUnit/Brain Monkey tests for cleanup retention rules, authenticated REST permissions, `send-pending`, `test-pixel`, and manual deletion metadata cleanup.

- [ ] Phase 7 integration and release evidence
  - Execute `composer test`, targeted PHPCS, focused PHPStan, Biome checks for touched JS/CSS, and `git diff --check`.
  - Verify rendered frontend HTML contains no `api_token`, `access_token`, `Bearer`, token refs, or encrypted token payloads.
  - Capture admin UI evidence for multi-account Google Ads conversion suggestions, multi-pixel configuration, and PageView trigger behavior on a public page.
  - Run a second-opinion review (`codex exec` or CodeRabbit) with the final diff and resolve actionable findings.
  - Bump the plugin header version and `ALVOBOT_PRO_VERSION` together before release.
  - Create a GitHub release for the new plugin version after validation.
  - Keep implementation commits in small vertical slices; if a slice exceeds roughly 300 changed lines, split the delivery.

## Phase 1: Foundation

- [x] 1.1 Register module in class-alvobot-pro.php
  - Add `'pixel-tracking'` entry to `$module_files` array (~line 40)
  - Add `'pixel-tracking' => true` to `$default_modules` array (~line 83)
  - Add `'pixel-tracking' => 'AlvoBotPro_PixelTracking'` to `$module_classes` array (~line 118)
  - Add `'pixel-tracking' => __( 'Pixel Tracking', 'alvobot-pro' )` to `get_module_names()` (~line 167)
  - Add submenu page in `add_admin_menu()` method
  - **Files**: `alvobot-plugin-manager/includes/class-alvobot-pro.php`
  - **Depends on**: None
  - **Requirement**: FR-001

- [x] 1.2 Create module main class skeleton
  - Create `class-pixel-tracking.php` extending `AlvoBotPro_Module_Base`
  - Implement `define_module_properties()`: id=`pixel-tracking`, name=`Pixel Tracking`, icon=`activity`
  - Implement `init()`: empty for now (sub-components added in later tasks)
  - Implement `render_settings_sections($settings)`: basic placeholder
  - Implement `sanitize_settings($settings)`: basic sanitization
  - Create directory structure: `modules/pixel-tracking/`, `assets/js/`, `assets/css/`, `views/`
  - **Files**: `alvobot-plugin-manager/includes/modules/pixel-tracking/class-pixel-tracking.php`
  - **Depends on**: 1.1
  - **Requirement**: FR-001, FR-004

- [x] 1.3 Register Custom Post Types and custom statuses
  - Create `class-pixel-tracking-cpt.php` with all 3 CPT registrations
  - Register `alvobot_pixel_event` (private, non-public, supports title + custom-fields)
  - Register `alvobot_pixel_lead` (private, non-public, supports title + custom-fields)
  - Register `alvobot_pixel_conversion` (private, non-public, supports title + custom-fields)
  - Register custom statuses: `pixel_pending`, `pixel_sent`, `pixel_error` via `register_post_status()`
  - Add helper methods: `create_event($data)`, `create_lead($data)`, `create_conversion($data)`
  - Add query helpers: `get_pending_events($limit)`, `get_conversions()`, `find_lead_by_email($email)`, `find_lead_by_fbp($fbp)`
  - Add status update helpers: `mark_events_sent($ids)`, `mark_events_error($ids, $error)`
  - Instantiate from `init()` in main class
  - **Files**: `alvobot-plugin-manager/includes/modules/pixel-tracking/class-pixel-tracking-cpt.php`
  - **Depends on**: 1.2
  - **Requirement**: FR-020, FR-023, FR-027

- [x] 1.4 [P] Build settings page UI (pixel config, both modes)
  - Implement `render_settings_sections($settings)` with sections:
    - Pixel Configuration Mode (radio: AlvoBot / Manual)
    - Manual pixel entry (Pixel ID + Token inputs with validation)
    - AlvoBot pixel selector (dropdown, populated via AJAX — wired in Phase 2)
    - Configured pixels list (table showing active pixels with remove button)
    - Test Mode toggle + test_event_code input
    - Role Exclusion (multi-checkbox for WP roles)
    - Consent settings (cookie name input)
    - Retention settings (days + max_events + max_leads number inputs)
  - Implement `sanitize_settings($settings)` with Pixel ID validation (numeric, 15-16 digits)
  - Create `assets/css/admin.css` for module-specific admin styles
  - Create `assets/js/admin.js` for dynamic form behavior (show/hide sections based on mode)
  - Create `views/settings-page.php` if needed for complex layout
  - **Files**: `class-pixel-tracking.php`, `assets/css/admin.css`, `assets/js/admin.js`
  - **Depends on**: 1.2
  - **Requirement**: FR-002, FR-003, FR-004, FR-017, FR-032, FR-033, FR-035

## Phase 2: AlvoBot Connection

- [x] 2.1 Add action routing + get_meta_pixels to api_plugin Edge Function
  - Add action-based routing to `api_plugin/index.ts`: parse `action` field from request body (default: `'register'` for backwards compatibility)
  - Add `handleGetMetaPixels()` function
  - Query `meta_pixels` JOIN `connections` WHERE `workspace_id` matches, `is_active = true`, `connections.is_active = true`, `needs_reconnect IS NULL OR false`, `deleted_at IS NULL`
  - Edge response is consumed only by WordPress PHP over the authenticated `site_url + token` server-to-server channel; no browser/client endpoint may receive raw `access_token`
  - WordPress PHP must strip raw token fields before admin JSON, return only public pixel fields plus `has_api_token` and a short-lived user-scoped `token_ref`, and immediately encrypt the resolved token during settings save
  - Add `case 'get_meta_pixels':` to action router switch statement
  - Ensure existing registration flow continues working (default action = `'register'`)
  - Deploy with `supabase functions deploy api_plugin --project-ref qbmbokpbcyempnaravaw --no-verify-jwt`
  - **Files**: `alvobot-supabase/supabase/functions/api_plugin/index.ts`
  - **Depends on**: None
  - **Requirement**: FR-005, FR-006

- [x] 2.2 WordPress-side pixel fetch via wp_remote_post to api_plugin
  - Add method `fetch_alvobot_pixels($force = false)` to main module class
  - Use `wp_remote_post(ALVOBOT_SERVER_URL, ['body' => json_encode(['action' => 'get_meta_pixels', 'site_url' => get_site_url(), 'token' => $site_token])])` — same pattern as site registration
  - Cache result in `set_transient('alvobot_pixel_tracking_pixels', $pixels, 15 * MINUTE_IN_SECONDS)`
  - Handle error states: no token (FR-007), no pixels found, connection expired, HTTP error
  - Add AJAX handler `wp_ajax_alvobot_pixel_tracking_fetch_pixels` for admin dropdown refresh
  - If a `token_ref` expires before save, keep existing saved token when present, reject mismatched refs, log the reason, and show an admin warning instructing the user to refresh/select the pixel again
  - **Files**: `class-pixel-tracking.php`
  - **Depends on**: 1.2, 2.1
  - **Requirement**: FR-005, FR-006, FR-007

- [x] 2.3 Wire AlvoBot pixel selector in settings UI
  - Update `admin.js` to call AJAX endpoint and populate pixel dropdown
  - Show pixel list with radio/checkbox selection (pixel_name + pixel_id)
  - On save: store selected pixel(s) with `source: 'alvobot'`, `connection_id`, `has_api_token` and encrypted token resolved from `token_ref`; never persist raw `access_token` in hidden inputs or rendered HTML
  - Handle states: loading spinner, error message, empty list message, token expired warning
  - Show "Site nao conectado" message when `alvobot_site_token` is empty (FR-007)
  - **Files**: `assets/js/admin.js`, `class-pixel-tracking.php`
  - **Depends on**: 1.4, 2.2
  - **Requirement**: FR-002, FR-005, FR-007

## Phase 3: Browser Tracking

- [x] 3.1 Create tracking.js base class
  - Create `AlvoBotPixelTracking` class with:
    - `start(config)` — initialize with config from PHP inline, same as Pixel X `start()`
    - `init_fb_sdk(pixel_ids)` — load `fbevents.js`, call `fbq('init', id)` per pixel
    - `get_edge_context()` — consume first-party attribution/edge context supplied by PHP; Cloudflare fields are optional and must never be required on non-Cloudflare hosts
    - No client-side geo API key: geolocation enrichment, when enabled, belongs in the server-side REST/CAPI layer using secure runtime config and transient cache; tracking.js receives only sanitized geo output
    - `get_fbp()` — read `_fbp` cookie or generate `fb.1.{ts}.{random10digit}`
    - `get_fbc()` — read `_fbc` cookie or extract from `fbclid` URL param, format `fb.1.{ts}.{click_id}`
    - `get_utms()` — parse `utm_source`, `utm_medium`, `utm_campaign`, `utm_id`, `utm_term`, `utm_content`, `src`, `sck`
    - `check_consent()` — check consent cookie, JS variable, CookieYes, Complianz integration
    - `generate_event_id()` — `crypto.randomUUID()` with fallback
    - `hash_sha256(value)` — Web Crypto API
    - Cookie helpers: `get(key)`, `set(values)` with 180-day expiry, SameSite=None, Secure
  - Export as `window.alvobot_pixel`
  - **Files**: `alvobot-plugin-manager/includes/modules/pixel-tracking/assets/js/tracking.js`
  - **Depends on**: 1.2
  - **Requirement**: FR-008, FR-009, FR-010, FR-011, FR-012, FR-015, FR-016, FR-035

- [x] 3.2 Implement send_event() and facebook_web() in tracking.js
  - `send_event(params)`:
    1. Generate `event_id` UUID
    2. Call `fbq('track'/'trackCustom', event_name, custom_data, {eventID: event_id})`
    3. POST to `config.api_event` with full event payload (async, fire-and-forget with `keepalive: true`)
    4. Include: event_id, event_name, page_url, page_title, page_id, fbp, fbc, ip, user_agent, geo, utms, lead_id, custom_data, pixel_ids
  - Handle `config.consent_cookie` check before any tracking
  - **Files**: `assets/js/tracking.js`
  - **Depends on**: 3.1
  - **Requirement**: FR-014, FR-015, FR-036

- [x] 3.3 Create frontend PHP class for JS injection
  - Create `class-pixel-tracking-frontend.php`
  - Hook into `wp_head` (priority 10)
  - Check: module active, pixels configured, user not excluded, not bot, consent config
  - Generate inline `<script>` with `alvobot_pixel_config` object containing only public pixel IDs, same-origin REST URLs, request token/nonce for WordPress validation, page context and first-party attribution; never include API access tokens or external service secrets
  - Enqueue `tracking.js` with `defer` attribute (FR-016). Verify < 100ms LCP impact via Lighthouse (SC-004)
  - Query active conversion rules for current page (using `get_conversions()` + page filtering)
  - Generate per-conversion inline JS based on trigger type (same pattern as Pixel X `PXA_action()`)
  - Instantiate from `init()` in main class (only on non-admin, public-facing pages)
  - **Files**: `alvobot-plugin-manager/includes/modules/pixel-tracking/class-pixel-tracking-frontend.php`
  - **Depends on**: 1.3, 3.1
  - **Requirement**: FR-008, FR-013, FR-016, FR-017, FR-018

- [x] 3.4 Implement conversion rule JS generation per trigger type
  - In `class-pixel-tracking-frontend.php`, implement JS generation for each trigger:
    - `page_load`: direct `send_event()` call on DOMContentLoaded
    - `page_time`: `setTimeout(() => send_event(), N * 1000)` with once-flag
    - `form_submit`: `querySelectorAll(selector).forEach(el => el.addEventListener('submit', ...))` + `setInterval` re-scan for dynamic forms (like Pixel X)
    - `click`: `querySelectorAll(selector).forEach(el => el.addEventListener('click', ...))`
    - `scroll`: scroll listener with percentage check + once-flag (no duplicate on re-scroll)
    - `view_element`: scroll listener with `getBoundingClientRect()` check + tracked class
  - Filter conversions by current page: `display_on` = all / specific (page_ids) / path (page_paths)
  - Pass event config (event_name, event_id, content_name, pixel_ids) per conversion
  - **Files**: `class-pixel-tracking-frontend.php`
  - **Depends on**: 3.3
  - **Requirement**: FR-013, FR-014

- [x] 3.5 Create public REST endpoints for event/lead persistence
  - Create `class-pixel-tracking-rest.php`
  - Register `POST /events/track` — public endpoint with strict same-origin validation, logged-in nonce validation and IP/User-Agent rate limiting
    - Validate `Origin`/`Referer` and only require nonce for logged-in cookie-auth requests
    - Rate limit: max 100 events/min per IP (transient-based fixed 60s window); excess requests return HTTP 429 with `Retry-After`
    - Sanitize attribution/edge payloads, enrich only server-side when a geo provider is configured, and never expose geo API keys to browser JavaScript, logs or debug payloads
    - Call `$cpt->create_event($data)` to create CPT with `pixel_pending` status
    - Return `{success: true, event_id: '...'}`
  - Register `POST /leads/track` — public endpoint (same security model)
    - Call `$cpt->create_lead($data)` with upsert logic
    - Return `{success: true, lead_id: '...'}`
  - Instantiate from `init()` in main class
  - **Files**: `alvobot-plugin-manager/includes/modules/pixel-tracking/class-pixel-tracking-rest.php`
  - **Depends on**: 1.3
  - **Requirement**: FR-023, FR-036

- [x] 3.6 [P] Build conversion rules CRUD (admin UI)
  - Add conversion management section via `render_additional_content($settings)`
  - Create `views/conversions-page.php` template with:
    - Conversions list (table: name, event type, trigger, pages, status toggle)
    - "Nova Conversao" button opening inline form
    - Form fields: nome, event_type (select), custom_event_name (conditional), trigger_type (select), trigger_value (number), display_on (select), page_ids (multi-select), page_paths (text), css_selector (text), content_name (text), pixel_ids (multi-select)
  - Add AJAX handlers for CRUD operations on conversion CPT
  - Add `admin.js` logic for dynamic form (show/hide fields based on trigger/display_on selection)
  - **Files**: `views/conversions-page.php`, `assets/js/admin.js`, `class-pixel-tracking.php`
  - **Depends on**: 1.3, 1.4
  - **Requirement**: FR-027, FR-028

## Phase 4: Conversion API

- [x] 4.1 Create CAPI sender class with Action Scheduler
  - Create `class-pixel-tracking-capi.php`
  - Register recurring Action Scheduler action: `alvobot_pixel_send_events` every 5 minutes
  - On trigger:
    0. Acquire a process-wide dispatch lock before querying pending events; skip concurrent runs while the lock is active, release stale locks older than `DISPATCH_LOCK_TTL = 300s`, and release the lock on completion/error for both Action Scheduler and WP-Cron fallback paths
    1. Query pending events via `$cpt->get_pending_events(250)` (date_query: 5min to 7d old)
    2. Group events by pixel_id
    3. For each pixel: build CAPI payload batch, send via `wp_remote_post()`
  - Build Facebook Conversion API payload per event (event_name, event_time, event_id, event_source_url, action_source, user_data, custom_data)
  - Store sanitized per-event CAPI request/response slices in `_fb_request_payload` meta before/after sending; never store raw unhashed PII or access tokens in debug payloads
  - Handle multi-pixel: send same event to all configured pixels
  - Instantiate from `init()` in main class
  - Verify Action Scheduler is available, fallback to `wp_schedule_event()` with warning
  - **Files**: `alvobot-plugin-manager/includes/modules/pixel-tracking/class-pixel-tracking-capi.php`
  - **Depends on**: 1.3, 3.5
  - **Requirement**: FR-022, FR-024, FR-026

- [x] 4.2 [P] Implement SHA-256 hashing helper
  - Add static method `hash_pii($value)` to CAPI class (or CPT class)
  - Normalize: trim, lowercase, remove spaces
  - Hash with `hash('sha256', $normalized)`
  - Phone normalization: remove non-digits, preserve valid country code, default Brazilian local numbers to `55`, hash only normalized numeric values
  - Used for: em, ph, fn, ln, ct, st, zp, country, external_id
  - Also used when creating leads (FR-021): store `_email_hash`, `_phone_hash`, `_name_hash`
  - **Files**: `class-pixel-tracking-capi.php`
  - **Depends on**: None
  - **Requirement**: FR-021, FR-022

- [x] 4.3 Implement retry and error handling for CAPI
  - On batch send failure (HTTP error or `events_received` missing from response):
    - If batch > 1 event: split into sub-batches of 50, schedule each as single Action Scheduler action (`alvobot_pixel_send_batch_{batch_id}`)
    - If single event fails: mark as `pixel_error`, store error in `_fb_error_message`, increment `_fb_retry_count`
  - On rate limit (429): re-schedule batch with 15min delay
  - On success: mark events `pixel_sent`, store response in `_fb_response_payload`, set `_fb_sent_at` and `_fb_pixel_id`
  - Max 3 retries per event before permanent `pixel_error`
  - Log all outcomes via `AlvoBotPro::debug_log('pixel-tracking', ...)`
  - **Files**: `class-pixel-tracking-capi.php`
  - **Depends on**: 4.1
  - **Requirement**: FR-025, FR-026, FR-029

- [x] 4.4 [P] Add test mode support to CAPI sender
  - Check `test_mode` and `test_event_code` from settings
  - If test mode active: include `test_event_code` in CAPI request body
  - If test mode active: add `[TEST]` prefix to event post_title for easy identification
  - **Files**: `class-pixel-tracking-capi.php`
  - **Depends on**: 4.1
  - **Requirement**: FR-033

## Phase 5: Lead Capture

- [x] 5.1 Implement lead detection in tracking.js
  - Add `monitor_forms()` method (inspired by Pixel X `monitor_forms()` + `input_monitor()`)
  - On DOMContentLoaded + `setInterval(5000)`: scan DOM for forms
  - For each form not already tracked (no `.alvobot_tracked` class):
    - Mark with `.alvobot_tracked` class
    - Listen for `submit` event
    - On submit: extract fields by name/type heuristics (email, name, phone, fname, lname)
    - Also listen for `input`/`change` on email-like fields to capture early
  - Add `send_lead_data()` method:
    - Build lead payload: lead_id (from cookie or new UUID), email, name, phone, fbp, fbc, ip, user_agent, geo, utms
    - POST to `config.api_lead` with nonce
    - Update local `this.data` with server response (lead_id)
    - Store lead_id in cookie for subsequent events
  - **Files**: `assets/js/tracking.js`
  - **Depends on**: 3.1, 3.5
  - **Requirement**: FR-019

- [x] 5.2 Implement lead upsert logic in CPT class
  - In `create_lead($data)` method:
    1. Search existing lead by `_email` meta query (if email provided)
    2. If not found, search by `_fbp` meta query
    3. If found: update `_last_seen`, `_last_utm_*`, geo data, phone/name if newly provided. Do NOT overwrite `_first_utm_*` or `_first_seen`.
    4. If not found: create new lead CPT with all data, set `_first_seen` = `_last_seen` = now, set `_first_utm_*` = current UTMs
  - Store SHA-256 hashes alongside raw values: `_email_hash`, `_phone_hash`, `_name_hash`
  - Set `post_title` = `Lead - {lead_id prefix}`; never expose raw email/phone/name in post titles
  - Return lead_id (new or existing)
  - **Files**: `class-pixel-tracking-cpt.php`
  - **Depends on**: 1.3, 4.2
  - **Requirement**: FR-019, FR-020, FR-021

## Phase 6: Operational

- [x] 6.1 Implement test mode admin UI and banner
  - In settings page: test mode toggle + test_event_code input (already laid out in 1.4)
  - When test mode active: render yellow banner at top of settings page ("Modo Teste Ativo — eventos enviados com test_event_code: {code}")
  - Wire admin.js to show/hide test_event_code input based on toggle
  - **Files**: `class-pixel-tracking.php`, `assets/js/admin.js`, `assets/css/admin.css`
  - **Depends on**: 1.4, 4.4
  - **Requirement**: FR-033

- [x] 6.2 Create data cleanup class with Action Scheduler
  - Create `class-pixel-tracking-cleanup.php`
  - Register daily recurring Action Scheduler action: `alvobot_pixel_cleanup`
  - Implement dual retention (FR-034):
    1. **Time-based**: Delete terminal events (`pixel_sent`, `pixel_error`) older than `retention_days`; delete leads by `_last_seen` older than `retention_days`
    2. **Stale pending**: Delete `pixel_pending` only when older than Meta CAPI's 7-day acceptance window
    3. **Volume-based**: If terminal event count > `max_events`, delete oldest terminal events until under limit. Same for leads > `max_leads`.
  - Delete in batches of 100 (avoid memory issues on large datasets)
  - Use `WP_Query` with `date_query` for time-based, `LIMIT` + `ORDER BY post_date ASC` for volume
  - Also delete associated `wp_postmeta` (handled automatically by `wp_delete_post($id, true)`)
  - Log cleanup summary: "Deleted X events, Y leads"
  - Instantiate from `init()` in main class
  - **Files**: `alvobot-plugin-manager/includes/modules/pixel-tracking/class-pixel-tracking-cleanup.php`
  - **Depends on**: 1.3
  - **Requirement**: FR-034

- [x] 6.3 Add authenticated REST API endpoints (app interaction)
  - Extend `class-pixel-tracking-rest.php` with authenticated endpoints:
    - **Settings**: `GET /settings`, `POST /settings`, `GET /settings/pixels`, `POST /settings/pixels`, `DELETE /settings/pixels/{pixel_id}`
    - **Conversions**: `GET /conversions`, `GET /conversions/{id}`, `POST /conversions`, `PUT /conversions/{id}`, `DELETE /conversions/{id}`
    - **Events**: `GET /events` (filterable: status, event_name, date_from, date_to, limit, offset), `GET /events/{event_id}`, `GET /events/stats`
    - **Leads**: `GET /leads` (filterable: date_from, date_to, search, limit, offset), `GET /leads/{lead_id}`, `GET /leads/stats`
    - **Status**: `GET /status` (module active, pixels configured, last CAPI send, pending count, cron health)
    - **Actions**: `POST /actions/send-pending`, `POST /actions/cleanup`, `POST /actions/refresh-pixels`, `POST /actions/test-pixel`
  - All use `permission_callback => function() { return current_user_can('manage_options'); }`
  - Standard response format: `{success: true, data: {...}, meta: {total, page, per_page}}`
  - **Files**: `class-pixel-tracking-rest.php`
  - **Depends on**: 3.5, 1.3
  - **Requirement**: US-9 (all acceptance scenarios)

- [x] 6.4 [P] Implement /actions/send-pending endpoint
  - When called: immediately trigger `alvobot_pixel_send_events` logic (same as cron)
  - Return `{success: true, data: {events_sent: N, events_failed: M}}`
  - Useful as external trigger backup (e.g., Uptime Robot calling this endpoint)
  - **Files**: `class-pixel-tracking-rest.php`, `class-pixel-tracking-capi.php`
  - **Depends on**: 4.1, 6.3
  - **Requirement**: FR-024

- [x] 6.5 [P] Implement /actions/test-pixel endpoint
  - Send a test PageView event to Facebook Conversion API for each configured pixel
  - Include `test_event_code` regardless of test mode setting
  - Return `{success: true, data: {pixel_id: '...', response: {...}}}` per pixel
  - **Files**: `class-pixel-tracking-rest.php`, `class-pixel-tracking-capi.php`
  - **Depends on**: 4.1, 6.3
  - **Requirement**: FR-033

## Phase 7: Polish & Hardening

- [x] 7.1 Implement bot detection
  - Add `is_bot()` method to frontend class (check `$_SERVER['HTTP_USER_AGENT']`)
  - Bot signatures: `bot`, `Bot`, `crawl`, `spider`, `HTTrack`, `facebookexternalhit`, `WP Rocket`, `Preload`, `GTmetrix`, `Lighthouse`, `Page Speed`, `Googlebot`, `Bingbot`, `Slurp`, `DuckDuckBot`, `Baidu`
  - If bot: skip all JS injection in `wp_head` hook
  - Add same check in JS: `tracking.js` `is_bot()` method checking `navigator.userAgent`
  - **Files**: `class-pixel-tracking-frontend.php`, `assets/js/tracking.js`
  - **Depends on**: 3.3
  - **Requirement**: FR-018

- [x] 7.2 [P] Implement configurable role exclusion
  - In frontend class: check `is_user_logged_in()` and `wp_get_current_user()->roles`
  - Compare against `excluded_roles` from settings (default: empty = track all)
  - If user's role is in excluded list: skip JS injection entirely
  - Also pass exclusion flag to tracking.js config for client-side check
  - **Files**: `class-pixel-tracking-frontend.php`
  - **Depends on**: 3.3, 1.4
  - **Requirement**: FR-017

- [x] 7.3 [P] Implement expired token detection and admin warning
  - In CAPI sender: when API returns auth error (401/403), update settings to mark pixel as `token_expired`
  - In settings page: render red banner for pixels with expired tokens
  - In AlvoBot mode: suggest refreshing pixels or reconnecting in alvobot-app
  - In manual mode: suggest updating the Conversion API Token
  - **Files**: `class-pixel-tracking-capi.php`, `class-pixel-tracking.php`, `assets/css/admin.css`
  - **Depends on**: 4.1, 1.4
  - **Requirement**: Edge case: Token expired

- [x] 7.4 Handle pending events when pixel removed
  - When a pixel is removed from settings: query events with `pixel_pending` status that reference the removed pixel_id
  - Mark them as `pixel_error` with message "pixel_removed"
  - Log via `AlvoBotPro::debug_log('pixel-tracking', "Discarded N events for removed pixel {$pixel_id}")`
  - **Files**: `class-pixel-tracking.php` (in save_settings flow)
  - **Depends on**: 1.4, 1.3
  - **Requirement**: Edge case: Pixel removed with pending events

- [x] 7.5 Admin UI refinements
  - Add Lucide icons to settings sections (consistent with other modules)
  - Add inline help text / descriptions for all settings fields
  - Ensure all strings use `__()` / `esc_html__()` for i18n readiness
  - Add loading states for AJAX operations (pixel fetch, conversion CRUD)
  - Test responsive layout in WordPress admin
  - **Files**: `class-pixel-tracking.php`, `assets/css/admin.css`, `assets/js/admin.js`, `views/conversions-page.php`
  - **Depends on**: 1.4, 2.3, 3.6, 6.1
  - **Requirement**: FR-030, FR-031

- [x] 7.6 Security audit pass
  - Verify all admin actions use nonces (`wp_verify_nonce` / `check_ajax_referer`)
  - Verify all inputs sanitized (`sanitize_text_field`, `absint`, `esc_url_raw`, etc.)
  - Verify all outputs escaped (`esc_html`, `esc_attr`, `esc_js`, `esc_url`, `wp_json_encode`)
  - Verify Pixel ID validation (numeric, 15-16 digits) blocks saving invalid values
  - Verify API tokens never appear in HTML source (frontend pages)
  - Verify REST public endpoints have rate limiting
  - Verify public tracking endpoints reject cross-origin requests and nonce validation protects logged-in cookie-auth requests
  - Grep rendered frontend HTML for token-like values (`access_token`, `Bearer`, `key=`, long API-token patterns)
  - **Files**: All module files
  - **Depends on**: All previous tasks
  - **Requirement**: FR-029, FR-030, FR-031, FR-032

- [ ] 7.7 Integration testing with Facebook
  - Test with Facebook Pixel Helper browser extension:
    - PageView fires on page load
    - Custom events fire on configured triggers
    - `event_id` present for dedup
    - `fbp` and `fbc` correctly formatted
  - Test in Facebook Events Manager:
    - Events appear in real-time
    - Test mode events appear in "Test Events" tab
    - Server events (CAPI) match browser events by `event_id`
    - PII match rate > 50%
  - Test with ad blocker:
    - Browser pixel blocked, CAPI events still arrive
  - Test multi-pixel:
    - Both pixels receive all events
  - Test AlvoBot mode + Manual mode simultaneously
  - Test lead capture with 5 form plugins (SC-006):
    - Contact Form 7 (CF7)
    - WPForms
    - Elementor Forms
    - Gravity Forms
    - Ninja Forms
  - Privacy compliance checks:
    - Verify retention cleanup removes events/leads per configured policy
    - Verify manual deletion removes lead/event metadata and debug payloads
    - Audit rendered HTML, logs and payload snapshots for raw PII leakage
    - Confirm CAPI debug payloads contain hashed user data summaries, not raw PII or access tokens
  - Performance checks:
    - Ingest 10k+ events and 5k+ leads without admin list timeouts
    - Run cleanup against 100k+ records and record duration
    - Measure query time for event/lead list endpoints under load
    - Run Lighthouse before/after on a tracked page to verify frontend impact
  - **Files**: N/A (manual testing)
  - **Depends on**: All previous tasks
  - **Requirement**: SC-001 through SC-008

## Requirement Coverage Matrix

| Requirement | Task(s) | Status |
|-------------|---------|--------|
| FR-001 | 1.1, 1.2 | Covered |
| FR-002 | 1.4, 2.3 | Covered |
| FR-003 | 1.4 | Covered |
| FR-004 | 1.2, 1.4 | Covered |
| FR-005 | 2.1, 2.2 | Covered |
| FR-006 | 2.1, 2.2 | Covered |
| FR-007 | 2.2, 2.3 | Covered |
| FR-008 | 3.1, 3.3 | Covered |
| FR-009 | 3.1 | Covered |
| FR-010 | 3.1 | Covered |
| FR-011 | 3.1 | Covered |
| FR-012 | 3.1 | Covered |
| FR-013 | 3.3, 3.4 | Covered |
| FR-014 | 3.2, 3.4 | Covered |
| FR-015 | 3.1, 3.2 | Covered |
| FR-016 | 3.1, 3.3 | Covered |
| FR-017 | 1.4, 7.2 | Covered |
| FR-018 | 3.3, 7.1 | Covered |
| FR-019 | 5.1 | Covered |
| FR-020 | 1.3, 5.2 | Covered |
| FR-021 | 4.2, 5.2 | Covered |
| FR-022 | 4.1, 4.2 | Covered |
| FR-023 | 1.3, 3.5 | Covered |
| FR-024 | 4.1, 6.4 | Covered |
| FR-025 | 4.3 | Covered |
| FR-026 | 4.1, 4.3 | Covered |
| FR-027 | 1.3, 3.6 | Covered |
| FR-028 | 3.6 | Covered |
| FR-029 | 4.3, 7.6 | Covered |
| FR-030 | 7.5, 7.6 | Covered |
| FR-031 | 7.5, 7.6 | Covered |
| FR-032 | 1.4, 7.6 | Covered |
| FR-033 | 4.4, 6.1, 6.5 | Covered |
| FR-034 | 6.2 | Covered |
| FR-035 | 1.4, 3.1 | Covered |
| FR-036 | 3.2, 3.5 | Covered |

## Critical Path

```
1.1 → 1.2 → 1.3 → 3.5 → 4.1 → 4.3 → 7.7
                 ↘ 3.3 → 3.4 ↗
                       ↘ 3.1 → 3.2 ↗
```

The critical path runs through: module registration → CPT setup → REST endpoints → CAPI sender → retry logic → integration testing. Browser tracking (3.x) and CAPI (4.x) converge at integration testing.

## Parallelization Opportunities

- **1.3 and 1.4** can run in parallel after 1.2
- **2.1** (Edge Function) can start immediately (no WP dependency)
- **3.1** (tracking.js) can start after 1.2 (only needs config structure)
- **3.6** (conversion CRUD UI) runs in parallel with 3.3-3.4 (frontend injection)
- **4.2** (SHA-256 helper) has no dependencies
- **4.4** (test mode) parallels 4.3 (retry logic)
- **6.4 and 6.5** (action endpoints) parallel each other
- **7.1, 7.2, 7.3** (bot detection, role exclusion, token warning) all parallel

## Notes

- `[P]` indicates tasks that can be parallelized with siblings in the same phase
- All PHP files must use `AlvoBotPro::debug_log('pixel-tracking', ...)` for logging (FR-029)
- All admin inputs must be sanitized, all outputs escaped (FR-030, FR-031)
- The reference implementation (`examples/pixel-x-app/`) should be consulted for every task — it has proven patterns for all the tracking logic
- Action Scheduler should be checked at module activation; if not available, warn admin
- Edge Function deployment requires `--no-verify-jwt` flag (WordPress plugin calls without Authorization header)
