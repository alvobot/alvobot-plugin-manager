# Data Model: Pixel Tracking Module

**Branch**: `001-pixel-tracking-module` | **Date**: 2026-02-16

## Overview

All data is stored locally in WordPress. No new Supabase tables are created.

**Storage mechanisms**:
- `wp_options` — Module settings (pixel config, test mode, retention, etc.)
- `wp_posts` + `wp_postmeta` — Custom Post Types for events, leads, and conversion rules
- `wp_posts.post_status` — Custom statuses for event lifecycle (`pixel_pending`, `pixel_sent`, `pixel_error`)

**Supabase (read-only)**:
- `meta_pixels` table — queried via `ai_plugin` Edge Function to fetch user's connected pixels
- `connections` table — joined server-side to get the Meta token for Conversion API; WordPress admin responses use short-lived `token_ref` references and local encrypted storage, never raw token HTML

## Entity Relationship

```
wp_options
  └── alvobot_pro_pixel-tracking_settings ──── Module config (pixels, mode, retention)
  └── alvobot_pixel_tracking_pixels ────────── Transient cache of AlvoBot pixels (15min TTL)

wp_posts (CPTs)
  ├── alvobot_pixel_event ──── Captured events (pending → sent → error)
  │     └── wp_postmeta ────── event_id, event_name, user_data, custom_data, fb payloads
  │
  ├── alvobot_pixel_lead ───── Captured leads (upsert by email/fbp)
  │     └── wp_postmeta ────── email, hashes, fbp, fbc, UTMs, geolocation
  │
  └── alvobot_pixel_conversion ── Conversion rules (admin-configured)
        └── wp_postmeta ────── event_type, trigger_type, target_pages, css_selector

Supabase (read-only via ai_plugin Edge Function)
  meta_pixels ──┐
                ├── JOIN → Server-side token lookup; WordPress receives public pixel data + token_ref
  connections ──┘
```

## 1. Module Settings (`wp_options`)

**Option key**: `alvobot_pro_pixel-tracking_settings`

```php
[
    // Pixel configuration mode
    'mode' => 'alvobot',              // 'alvobot' | 'manual'

    // Configured pixels (can have multiple)
    'pixels' => [
        [
            'pixel_id'            => '1234567890123456',   // Meta Pixel ID (15-16 digits)
            'has_api_token'       => true,                  // Boolean UI/runtime hint
            'encrypted_api_token' => 'enc:v2:...',          // AES-GCM encrypted Conversion API token
            'source'              => 'alvobot',             // 'alvobot' | 'manual'
            'connection_id'       => 'uuid-here',           // Only for source=alvobot
            'label'               => 'My Pixel',            // Display name
        ],
    ],

    // Test mode
    'test_mode'        => false,
    'test_event_code'  => 'TEST12345',   // From Facebook Events Manager

    // Consent
    'consent_check'    => true,           // Whether to check consent before tracking
    'consent_cookie'   => 'alvobot_tracking_consent',  // Cookie name to check

    // Role exclusion
    'excluded_roles'   => [],             // e.g., ['administrator', 'editor']

    // Data retention
    'retention_days'   => 7,              // Delete events/leads older than N days
    'max_events'       => 50000,          // Max event CPT records
    'max_leads'        => 10000,          // Max lead CPT records

    // Scheduling
    'send_interval'    => 5,              // Minutes between CAPI send batches
    'batch_size'       => 250,            // Events per batch (Facebook max)
]
```

**Retention enforcement policy**:
- Cleanup applies time-based retention first, then volume caps.
- `pixel_pending` events are preserved while recoverable; stale pending events older than the Meta CAPI 7-day acceptance window are irrecoverable and may be removed with an explicit summary count.
- Event cleanup by age/volume only targets terminal statuses (`pixel_sent`, `pixel_error`) unless applying the stale-pending rule above.
- Leads are deleted by `_last_seen` when available, falling back to `post_date`; active leads with recent `_last_seen` are preserved even if the post was created long ago.
- Each cleanup run deletes in bounded batches and reports whether the per-run deletion limit was reached.

## 2. Pixel Event CPT (`alvobot_pixel_event`)

**Registration**:
```php
register_post_type('alvobot_pixel_event', [
    'labels'       => ['name' => 'Pixel Events'],
    'public'       => false,
    'show_ui'      => false,
    'show_in_rest' => false,
    'supports'     => ['title', 'custom-fields'],
    'capability_type' => 'post',
]);
```

**Custom statuses**:
```php
register_post_status('pixel_pending', [
    'label' => 'Pending',
    'public' => false,
    'internal' => true,
]);
register_post_status('pixel_sent', [
    'label' => 'Sent',
    'public' => false,
    'internal' => true,
]);
register_post_status('pixel_error', [
    'label' => 'Error',
    'public' => false,
    'internal' => true,
]);
```

**Post fields**:
| Field | Source | Example |
|-------|--------|---------|
| `post_title` | `{event_name} - {event_id}` | `Lead - a1b2c3d4-...` |
| `post_status` | Custom status | `pixel_pending` |
| `post_date` | Event capture time | `2026-02-16 14:30:00` |

**Post meta fields**:
| Meta key | Type | Description | Example |
|----------|------|-------------|---------|
| `_event_id` | string (UUID) | Unique ID for dedup (browser + server) | `a1b2c3d4-e5f6-7890-abcd-ef1234567890` |
| `_event_name` | string | Facebook event name | `Lead`, `ViewContent`, `PageView` |
| `_event_time` | int | Unix timestamp | `1708100000` |
| `_page_url` | string | Full page URL | `https://example.com/contato` |
| `_page_title` | string | Page title | `Contato - Meu Blog` |
| `_page_id` | int | WordPress post/page ID | `42` |
| `_lead_id` | string (UUID) | Associated lead ID (if any) | `uuid-or-empty` |
| `_fbp` | string | Facebook Browser Pixel ID | `fb.1.1708100000.1234567890` |
| `_fbc` | string | Facebook Click ID | `fb.1.1708100000.AbCdEfGhI` |
| `_ip` | string | Visitor IP | `203.0.113.42` |
| `_user_agent` | string | Browser user agent | `Mozilla/5.0 ...` |
| `_geo_city` | string | City | `Sao Paulo` |
| `_geo_state` | string | State/region | `SP` |
| `_geo_country` | string | Country name | `Brazil` |
| `_geo_country_code` | string | ISO country code | `BR` |
| `_geo_zipcode` | string | Zip/postal code | `01310-100` |
| `_geo_currency` | string | Local currency | `BRL` |
| `_geo_timezone` | string | Timezone | `America/Sao_Paulo` |
| `_utm_source` | string | UTM source | `facebook` |
| `_utm_medium` | string | UTM medium | `cpc` |
| `_utm_campaign` | string | UTM campaign | `spring_sale` |
| `_utm_id` | string | UTM ID | `123` |
| `_utm_term` | string | UTM term | `keyword` |
| `_utm_content` | string | UTM content | `banner_a` |
| `_custom_data` | serialized | Custom event data | `{content_name: 'Artigo X'}` |
| `_pixel_ids` | string | Comma-separated pixel IDs | `123456,789012` |
| `_fb_request_payload` | serialized | Sent to Facebook | `{data: [...]}` |
| `_fb_response_payload` | serialized | Facebook response | `{events_received: 1}` |
| `_fb_sent_at` | string | ISO timestamp of dispatch | `2026-02-16T14:35:00Z` |
| `_fb_pixel_id` | string | Which pixel received it | `1234567890123456` |
| `_fb_error_message` | string | Error details (if error) | `Invalid pixel ID` |
| `_fb_retry_count` | int | Number of retries | `0` |

**Lifecycle**:
```
Browser JS fires event
  → POST /events/track (REST API)
    → create_event() → CPT with status pixel_pending
      → Action Scheduler (every 5min)
        → get_pending_events(250)
          → wp_remote_post to Facebook Conversion API
            → Success: status → pixel_sent, store response
            → Failure: retry or status → pixel_error
```

## 3. Pixel Lead CPT (`alvobot_pixel_lead`)

**Registration**:
```php
register_post_type('alvobot_pixel_lead', [
    'labels'       => ['name' => 'Pixel Leads'],
    'public'       => false,
    'show_ui'      => false,
    'show_in_rest' => false,
    'supports'     => ['title', 'custom-fields'],
    'capability_type' => 'post',
]);
```

**Post fields**:
| Field | Source | Example |
|-------|--------|---------|
| `post_title` | Pseudonymized title `Lead - {lead_id}` | `Lead - a1b2c3d4` |
| `post_status` | `publish` | Always published |
| `post_date` | First capture time | `2026-02-16 14:30:00` |
| `post_modified` | Last update time | `2026-02-16 15:00:00` |

**Post meta fields**:
| Meta key | Type | Description | Example |
|----------|------|-------------|---------|
| `_lead_id` | string (UUID) | Unique lead identifier | `uuid-here` |
| `_email` | string | Raw email | `user@example.com` |
| `_email_hash` | string | SHA-256 of lowercased email | `abc123...` |
| `_phone` | string | Raw phone | `+5511999887766` |
| `_phone_hash` | string | SHA-256 of normalized phone | `def456...` |
| `_name` | string | Full name | `Joao Silva` |
| `_first_name` | string | First name | `Joao` |
| `_last_name` | string | Last name | `Silva` |
| `_name_hash` | string | SHA-256 of lowercased name | `ghi789...` |
| `_fbp` | string | Facebook Browser Pixel ID | `fb.1.170810...` |
| `_fbc` | string | Facebook Click ID | `fb.1.170810...` |
| `_ip` | string | Last known IP | `203.0.113.42` |
| `_user_agent` | string | Last known user agent | `Mozilla/5.0 ...` |
| `_geo_city` | string | City | `Sao Paulo` |
| `_geo_state` | string | State | `SP` |
| `_geo_country` | string | Country | `Brazil` |
| `_geo_country_code` | string | ISO code | `BR` |
| `_geo_zipcode` | string | Zip code | `01310-100` |
| `_geo_currency` | string | Currency | `BRL` |
| `_geo_timezone` | string | Timezone | `America/Sao_Paulo` |
| `_first_utm_source` | string | First-touch UTM source | `facebook` |
| `_first_utm_medium` | string | First-touch UTM medium | `cpc` |
| `_first_utm_campaign` | string | First-touch UTM campaign | `spring` |
| `_last_utm_source` | string | Last-touch UTM source | `google` |
| `_last_utm_medium` | string | Last-touch UTM medium | `organic` |
| `_last_utm_campaign` | string | Last-touch UTM campaign | `` |
| `_src` | string | Custom source param | `landing_page_a` |
| `_sck` | string | Custom tracking param | `abc` |
| `_first_seen` | string | ISO timestamp | `2026-02-10T10:00:00Z` |
| `_last_seen` | string | ISO timestamp | `2026-02-16T14:30:00Z` |
| `_last_seen_ts` | int | Unix timestamp for cleanup comparisons | `1778115950` |

**Upsert logic**: When a new lead comes in:
1. Acquire a short-lived lock keyed by normalized email hash or `_fbp` to avoid concurrent duplicate creates.
2. Search for existing lead by `_email` (if email provided).
3. If not found, search by `_fbp`.
4. If found: update `_last_seen`, last UTMs, geo data, phone/name if newly provided.
5. If not found: create new lead with pseudonymized `post_title` and all private meta.
6. Release the lock in success/error paths; on lock contention, retry with short backoff and re-query before inserting.

## Required Indexes

WordPress does not add efficient indexes for custom meta keys. High-traffic sites should provision indexes or migrate hot paths to custom tables before relying on 50k+ events:

- `wp_posts(post_type, post_status, post_date)` for event status lists and retention.
- `wp_posts(post_type, post_date)` for lead/event cleanup.
- `wp_postmeta(meta_key, meta_value(191))` for `_event_id`, `_lead_id`, `_email`, `_fbp`, `_pixel_ids`, `_event_time`, `_fb_retry_count`.
- Avoid unbounded `meta_query` scans in admin list endpoints; keep REST pagination capped.

## Privacy & Compliance

- Raw PII fields (`_email`, `_phone`, `_name`, `_ip`, `_geo_*`) must be treated as private admin data and never used in `post_title`, public REST responses, debug payloads, or logs.
- Hash fields are derived only after deterministic normalization documented in FR-021/FR-022.
- Site owners must be able to export/delete visitor event and lead data by lead identifier, email hash, `_fbp`, or `_fbc`.
- Right-to-erasure requests must purge queued/pending events as well as lead records when identifiers match.
- Retention settings apply to both events and leads; defaults must be conservative and configurable by the admin.
- Access to PII views/actions should require `manage_options` or an equivalent explicit capability and should be auditable by the host/site owner.

## 4. Pixel Conversion CPT (`alvobot_pixel_conversion`)

**Registration**:
```php
register_post_type('alvobot_pixel_conversion', [
    'labels'       => ['name' => 'Pixel Conversions'],
    'public'       => false,
    'show_ui'      => false,
    'show_in_rest' => false,
    'supports'     => ['title', 'custom-fields'],
    'capability_type' => 'post',
]);
```

**Post fields**:
| Field | Source | Example |
|-------|--------|---------|
| `post_title` | Conversion name | `Lead - Formulario Contato` |
| `post_status` | `publish` (active) or `draft` (inactive) | `publish` |

**Post meta fields**:
| Meta key | Type | Description | Values |
|----------|------|-------------|--------|
| `_event_type` | string | Facebook event type | `PageView`, `Lead`, `ViewContent`, `CompleteRegistration`, `Contact`, `Search`, `Subscribe`, `StartTrial`, `SubmitApplication`, `Schedule`, `CustomEvent` |
| `_event_custom_name` | string | Custom event name (when event_type = CustomEvent) | `MyCustomEvent` |
| `_trigger_type` | string | How event is triggered | `page_load`, `page_time`, `form_submit`, `click`, `scroll`, `view_element` |
| `_trigger_value` | int | Trigger parameter | `30` (seconds), `80` (scroll %), etc. |
| `_display_on` | string | Where conversion fires | `all`, `specific`, `path` |
| `_page_ids` | serialized array | Specific page/post IDs | `[42, 55, 100]` |
| `_page_paths` | string | Comma-separated paths | `/contato,/obrigado` |
| `_css_selector` | string | CSS selector for click/form/view triggers | `.cta-button`, `#contact-form` |
| `_content_name` | string | Custom content_name for event data | `Artigo sobre Marketing` |
| `_pixel_ids` | string | Specific pixels (empty = all) | `123456` or empty |

**Admin UI fields mapping** (form → meta):
| UI Label | Meta Key | Input Type |
|----------|----------|------------|
| Nome da Conversao | `post_title` | text |
| Tipo de Evento | `_event_type` | select |
| Nome Customizado | `_event_custom_name` | text (conditional) |
| Tipo de Trigger | `_trigger_type` | select |
| Valor do Trigger | `_trigger_value` | number |
| Exibir em | `_display_on` | select (all/specific/path) |
| Paginas | `_page_ids` | multi-select (WP pages/posts) |
| Caminhos | `_page_paths` | text |
| Seletor CSS | `_css_selector` | text |
| Content Name | `_content_name` | text |
| Pixels | `_pixel_ids` | multi-select (configured pixels) |
| Ativo | `post_status` | toggle (publish/draft) |

## 5. Supabase Tables (Read-Only Reference)

### `meta_pixels` table

```sql
CREATE TABLE meta_pixels (
    id              uuid DEFAULT gen_random_uuid() PRIMARY KEY,
    connection_id   uuid NOT NULL REFERENCES connections(id) ON DELETE CASCADE,
    ad_account_id   uuid REFERENCES meta_ad_accounts(id) ON DELETE CASCADE,
    user_id         uuid REFERENCES auth.users(id) ON DELETE CASCADE,
    workspace_id    uuid REFERENCES workspaces(id) ON DELETE CASCADE,
    pixel_id        text NOT NULL,           -- Meta Pixel ID (e.g., '1234567890123456')
    pixel_name      text NOT NULL,           -- Display name
    code            text,                     -- Pixel code snippet (unused by our module)
    is_unavailable  boolean DEFAULT false,
    is_active       boolean DEFAULT false,    -- User selected this pixel to work with
    created_at      timestamptz DEFAULT now(),
    updated_at      timestamptz DEFAULT now(),
    last_synced_at  timestamptz,
    UNIQUE (connection_id, pixel_id)
);
```

### `connections` table (relevant fields)

```sql
CREATE TABLE connections (
    id                  uuid DEFAULT gen_random_uuid() PRIMARY KEY,
    user_id             uuid REFERENCES auth.users(id) ON DELETE CASCADE,
    workspace_id        uuid REFERENCES workspaces(id) ON DELETE CASCADE,
    connection_name     text,                 -- e.g., 'Facebook - Joao Silva'
    plataform_name      text,                 -- e.g., 'meta'
    access_token        text,                 -- OAuth access token (used server-side as CAPI token; never exposed to frontend HTML)
    is_active           boolean,
    needs_reconnect     boolean DEFAULT false, -- Token expired / revoked
    deleted_at          timestamptz,           -- Soft delete
    -- ... other fields
);
```

### Query used by `handleGetMetaPixels()`:

```sql
SELECT
    mp.pixel_id,
    mp.pixel_name,
    c.access_token, -- consumed server-side to create token_ref/encrypted local runtime token
    mp.connection_id,
    c.connection_name
FROM meta_pixels mp
INNER JOIN connections c ON c.id = mp.connection_id
WHERE mp.workspace_id = $workspace_id
  AND mp.is_active = true
  AND c.is_active = true
  AND (c.needs_reconnect IS NULL OR c.needs_reconnect = false)
  AND c.deleted_at IS NULL;
```

## 6. Transient Cache

| Transient key | TTL | Contents |
|---------------|-----|----------|
| `alvobot_pixel_tracking_pixels` | 15 min | Array of pixels from AlvoBot (ai_plugin response) |

## 7. Action Scheduler Actions

| Action hook | Schedule | Description |
|-------------|----------|-------------|
| `alvobot_pixel_send_events` | Every 5 min (recurring) | Dispatch pending events to Conversion API |
| `alvobot_pixel_cleanup` | Daily (recurring) | Delete old events/leads per retention settings |
| `alvobot_pixel_send_batch_{batch_id}` | Single (on-demand) | Process a specific sub-batch after failure |
