# Carkeek Events

A lightweight, developer-friendly WordPress plugin for managing events. Registers Event, Location, and Organizer custom post types with classic meta boxes for structured data, a daily WP cron for hidden/expired state management, optional Google Maps geocoding, a built-in Events Archive Gutenberg block, and integration with the `carkeek-blocks` custom-archive block.

The plugin provides **no opinionated front-end styles**. It ships default templates that you are expected to override in your theme.

---

## Requirements

- WordPress 6.4+
- PHP 8.1+

---

## Installation

1. Drop the `carkeek-events` folder in `wp-content/plugins/`.
2. Activate the plugin in **Plugins**.
3. Go to **Events > Settings** and click **Flush Rewrite Rules** once to register the CPT permalinks.

---

## Post Types and Taxonomy

| Post Type | Archive Slug | Single Slug | Menu Icon |
|---|---|---|---|
| `carkeek_event` | `/events/` | `/event/{slug}/` | dashicons-calendar-alt |
| `carkeek_location` | — | `/locations/{slug}/` | dashicons-location |
| `carkeek_organizer` | — | `/organizers/{slug}/` | dashicons-groups |

**Taxonomy:** `carkeek_event_category` — hierarchical, registered to `carkeek_event` only. Archive slug: `/event-category/{slug}/`.

---

## Meta Keys Reference

### carkeek_event

| Meta Key | Type | Notes |
|---|---|---|
| `_carkeek_event_start` | string | ISO 8601 local time: `YYYY-MM-DDTHH:MM:SS`. Time is `00:00:00` when no time is set. |
| `_carkeek_event_end` | string | ISO 8601 local time. Empty for open-ended events (they will never be hidden or expired). |
| `_carkeek_event_location_id` | integer | Linked `carkeek_location` post ID. `0` if none. |
| `_carkeek_event_location_text` | string | Free-text location fallback when no CPT is linked. |
| `_carkeek_event_organizer_id` | integer | Linked `carkeek_organizer` post ID. `0` if none. |
| `_carkeek_event_organizer_text` | string | Free-text organizer fallback when no CPT is linked. |
| `_carkeek_event_website` | string | External registration or info URL. When set, templates render a CTA button. |
| `_carkeek_event_button_label` | string | CTA button label. Defaults to "Sign Up" at render time if blank. |
### carkeek_location

| Meta Key | Type | Notes |
|---|---|---|
| `_carkeek_location_address` | string | Street address |
| `_carkeek_location_city` | string | |
| `_carkeek_location_state` | string | State or province |
| `_carkeek_location_zip` | string | Zip or postal code |
| `_carkeek_location_country` | string | |
| `_carkeek_location_website` | string | URL |
| `_carkeek_location_lat` | string | Decimal latitude. Auto-populated by geocoding if API key configured. |
| `_carkeek_location_lng` | string | Decimal longitude. Auto-populated by geocoding if API key configured. |

### carkeek_organizer

| Meta Key | Type | Notes |
|---|---|---|
| `_carkeek_organizer_email` | string | |
| `_carkeek_organizer_phone` | string | |
| `_carkeek_organizer_website` | string | URL |

---

## Display Helpers (`CarkeekEvents_Display`)

All display helpers accept just the **event post ID** — they fetch their own meta internally. Output everything with `wp_kses_post()`.

```php
$post_id    = get_the_ID(); // or $post->ID in a card template

$date_range = CarkeekEvents_Display::get_date_range_html( $post_id );
$location   = CarkeekEvents_Display::get_event_location_html( $post_id );
$organizer  = CarkeekEvents_Display::get_event_organizer_html( $post_id );
$event_link = CarkeekEvents_Display::get_event_link_html( $post_id );
```

---

### `get_date_range_html( $post_id, $separator = ', ' )`

Returns the event date/time range as HTML with date and time values wrapped in separate `<span>` tags. This lets you control layout entirely through CSS without touching PHP.

```html
<!-- Default output (same-day with time range) -->
<span class="carkeek-event-date">March 15, 2026</span>, <span class="carkeek-event-time">10:00 am &ndash; 2:00 pm</span>

<!-- Multi-day -->
<span class="carkeek-event-date">March 15, 2026</span>, <span class="carkeek-event-time">10:00 am</span>
&ndash;
<span class="carkeek-event-date">March 16, 2026</span>, <span class="carkeek-event-time">5:00 pm</span>
```

**Display on two lines** — pass `'<br>'` as the separator, or use CSS:

```php
// PHP separator approach
$date_range = CarkeekEvents_Display::get_date_range_html( $post_id, '<br>' );

// CSS approach (works with default separator)
.carkeek-event-date,
.carkeek-event-time { display: block; }
```

Date and time formats respect the **Date Format** and **Time Format** settings under **Events > Settings**, falling back to WordPress site settings.

**Filter:** `carkeek_events_date_range( $output, $start_date, $start_time, $end_date, $end_time )`

---

### `get_event_location_html( $post_id )`

Returns the location display HTML per the **Location Display** setting in **Events > Settings**:

- `link` (default) — location name linked to its CPT single page
- `address` — formatted address block (name, street, city, state/zip/country)
- `address_directions` — address block with a "Get Directions" link to Google Maps

Falls back to the free-text location string if no CPT post is linked.

**Filter:** `carkeek_events_location_display( $html, $post_id )`

---

### `get_event_organizer_html( $post_id )`

Returns the organizer display HTML per the **Organizer Display** setting in **Events > Settings**:

- `link` (default) — organizer name linked to their CPT single page
- `info` — name + email (linked) + phone (linked) + website inline

Falls back to the free-text organizer string if no CPT post is linked.

**Filter:** `carkeek_events_organizer_display( $html, $post_id )`

---

### `get_event_link_html( $post_id )`

Returns an `<a>` tag linking to the event's website/registration URL. The label comes from the **Button Label** meta field, defaulting to "Sign Up" when blank. Returns empty string if no URL is set.

The `<a>` tag includes the class `wp-element-button` so it inherits the theme's button styles automatically.

**Filter:** `carkeek_events_link_html( $html, $post_id, $url, $label )`

---

### Low-level helpers (advanced use)

The convenience wrappers above call these underlying methods, which you can use directly when you need more control:

```php
// format_date_range: pass raw meta values + optional separator
CarkeekEvents_Display::format_date_range( $start_date, $start_time, $end_date, $end_time, $separator );

// get_location_html: pass location ID + text fallback + event post ID
CarkeekEvents_Display::get_location_html( $location_id, $location_text, $post_id );

// get_organizer_html: pass organizer ID + text fallback + event post ID
CarkeekEvents_Display::get_organizer_html( $organizer_id, $organizer_text, $post_id );
```

---

## Custom Templates

### Single Event Page

Template hierarchy (first match wins):

1. **Theme override:** `{your-theme}/carkeek-events/single-carkeek_event.php`
2. **Plugin default:** `carkeek-events/templates/single-carkeek_event.php`
3. **Disabled:** If you set **Single Event Template** to "Use theme template" in **Events > Settings**, the plugin template is bypassed entirely and WordPress falls back to `single.php` or `singular.php`.

**Filter to override the template path:** `carkeek_events_single_template( $template_path )`

**Minimal custom single-event template:**

```php
<?php
// Place this file at: {your-theme}/carkeek-events/single-carkeek_event.php

get_header();

while ( have_posts() ) :
    the_post();

    $post_id        = get_the_ID();
    $date_range     = CarkeekEvents_Display::get_date_range_html( $post_id );
    $location_html  = CarkeekEvents_Display::get_event_location_html( $post_id );
    $organizer_html = CarkeekEvents_Display::get_event_organizer_html( $post_id );
    $event_link     = CarkeekEvents_Display::get_event_link_html( $post_id );
    ?>

    <article <?php post_class( 'my-event' ); ?>>

        <h1><?php the_title(); ?></h1>

        <?php if ( $date_range ) : ?>
            <p class="event-dates"><?php echo wp_kses_post( $date_range ); ?></p>
        <?php endif; ?>

        <?php if ( $location_html ) : ?>
            <div class="event-location"><?php echo wp_kses_post( $location_html ); ?></div>
        <?php endif; ?>

        <?php if ( $organizer_html ) : ?>
            <div class="event-organizer"><?php echo wp_kses_post( $organizer_html ); ?></div>
        <?php endif; ?>

        <?php if ( $event_link ) : ?>
            <div class="event-cta"><?php echo wp_kses_post( $event_link ); ?></div>
        <?php endif; ?>

        <div class="event-content">
            <?php the_content(); ?>
        </div>

    </article>

<?php endwhile; ?>

<?php get_footer(); ?>
```

---

### Event Card (Archive / carkeek-blocks)

When the `carkeek-blocks` custom-archive block queries `carkeek_event`, the plugin intercepts the card template. Template hierarchy (first match wins):

1. **Filter:** `carkeek_events_card_template( $template, $post, $attributes )` — return a full file path
2. **Theme override:** `{your-theme}/carkeek-events/event-card/default.php`
3. **Plugin default:** `carkeek-events/templates/event-card/default.php`

**Minimal custom event card template:**

```php
<?php
// Place this file at: {your-theme}/carkeek-events/event-card/default.php

if ( ! isset( $post ) ) {
    $post = get_post();
}

$post_id        = $post->ID;
$date_range     = CarkeekEvents_Display::get_date_range_html( $post_id );
$location_html  = CarkeekEvents_Display::get_event_location_html( $post_id );
$organizer_html = CarkeekEvents_Display::get_event_organizer_html( $post_id );
$event_link     = CarkeekEvents_Display::get_event_link_html( $post_id );
?>

<div class="my-event-card">

    <?php if ( has_post_thumbnail( $post_id ) ) : ?>
        <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
            <?php echo get_the_post_thumbnail( $post_id, 'medium' ); ?>
        </a>
    <?php endif; ?>

    <a class="event-card__title" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
        <?php echo esc_html( get_the_title( $post_id ) ); ?>
    </a>

    <?php if ( $date_range ) : ?>
        <p class="event-card__dates"><?php echo wp_kses_post( $date_range ); ?></p>
    <?php endif; ?>

    <?php if ( $location_html ) : ?>
        <div class="event-card__location"><?php echo wp_kses_post( $location_html ); ?></div>
    <?php endif; ?>

    <?php if ( $organizer_html ) : ?>
        <div class="event-card__organizer"><?php echo wp_kses_post( $organizer_html ); ?></div>
    <?php endif; ?>

    <?php if ( $event_link ) : ?>
        <div class="event-card__cta"><?php echo wp_kses_post( $event_link ); ?></div>
    <?php endif; ?>

</div>
```

---

## Filter and Action Hook Reference

### Filters

| Hook | Args | Purpose |
|---|---|---|
| `carkeek_events_date_range` | `$output, $start_date, $start_time, $end_date, $end_time` | Override formatted date range string |
| `carkeek_events_location_display` | `$html, $post_id` | Override rendered location HTML |
| `carkeek_events_organizer_display` | `$html, $post_id` | Override rendered organizer HTML |
| `carkeek_events_link_html` | `$html, $post_id, $url, $label` | Override or wrap the CTA button HTML |
| `carkeek_events_query_args` | `$args, $attributes` | Modify WP_Query args for the carkeek-blocks archive block |
| `carkeek_events_expiry_threshold` | `$threshold, $post_id` | Override expiry date per event |
| `carkeek_events_card_template` | `$template, $post, $attributes` | Supply a custom event card template path |
| `carkeek_events_single_template` | `$template` | Override the single event template path |
| `carkeek_events_block_query_args` | `$args, $attributes` | Modify WP_Query args for the Events Archive block |

### Actions

| Hook | Args | Purpose |
|---|---|---|
| `carkeek_events_meta_box_after_dates` | `$post` | Add fields after dates in the event meta box |
| `carkeek_events_meta_box_after_location` | `$post` | Add fields after location in the event meta box |
| `carkeek_events_meta_box_after_organizer` | `$post` | Add fields after organizer in the event meta box |
| `carkeek_events_meta_box_after_link` | `$post` | Add fields after the website/button section |
| `carkeek_events_before_expire` | `$post_id` | Fires before cron sets `post_status = private` |
| `carkeek_events_after_geocode` | `$post_id, $lat, $lng` | Fires after geocoding completes |

---

## Settings Reference

All settings are stored as a single array under the option key `carkeek_events_settings`.

| Key | Default | Notes |
|---|---|---|
| `content_expiry_days` | `365` | Integer, days. Events are set to `private` this many days after their end date. Minimum 1. |
| `disable_wp_archive` | `1` | `1` = WP CPT archive disabled (use a custom Page + archive block). `0` = enabled. |
| `archive_slug` | `events` | Slug for the WP CPT archive when enabled. Rewrite rules flush automatically on save. |
| `use_plugin_template` | `1` | `1` = use plugin template, `0` = use theme template |
| `date_format` | `''` | PHP date format string. Falls back to WP site setting. |
| `time_format` | `''` | PHP date format string. Falls back to WP site setting. |
| `location_display` | `link` | `link` \| `address` \| `address_directions` |
| `organizer_display` | `link` | `link` \| `info` |
| `google_maps_api_key` | `''` | Stored server-side only. Never exposed to the browser. |

---

## Expiry and Cron

A daily WP cron job (`carkeek_events_daily_cron`) manages event expiry automatically:

**Expire:** Events whose `_carkeek_event_end` is older than `content_expiry_days` (default 365) are set to `post_status = private`, returning 404 to visitors. No permanent deletion — posts remain in the database.

Events with no `_carkeek_event_end` are never expired.

**Manual trigger:**

```bash
wp cron event run carkeek_events_daily_cron
```

Or use the **Run Expiry Check Now** button in **Events > Settings**.

---

## Events Archive Block

The plugin ships a native Gutenberg block — **Carkeek Events Archive** — available in the block inserter under **Widgets**.

### Why use this instead of the WordPress CPT archive?

The WordPress archive template is hard to customise without a page builder. The archive block lets you place a filterable event list anywhere on any page, controlling layout, columns, and category filters from the block sidebar. Set **Disable WordPress Archive** in **Events > Settings** (the default), then create a regular Page with the slug `events` and drop the block on it.

### Block options

| Option | Default | Notes |
|---|---|---|
| Number of Events | `6` | Posts per page |
| Sort Order | `ASC` | Ascending (upcoming first) or Descending (latest first) |
| Include Past Events | off | Show events whose end date has passed |
| Only Past Events | off | Show only past events |
| Filter by Category | off | Multi-select from `carkeek_event_category` terms |
| Post Layout | `grid` | `grid` or `list` |
| Columns (Desktop/Tablet/Mobile) | 3/2/1 | Grid columns at each breakpoint |
| Display Featured Image | on | |
| Display Excerpt | off | |
| Excerpt Length | 25 words | |
| Show Pagination | off | |
| Hide Block When Empty | on | Returns empty string if no events match |
| Empty State Message | — | Shown when Hide When Empty is off |

**Filter:** `carkeek_events_block_query_args( $args, $attributes )` — modify the WP_Query args for the block.

### Build

The block source is in `src/events-archive/`. After installing npm dependencies, run:

```bash
npm install
npm run build
```

The compiled assets in `build/events-archive/` are committed to the repo so the plugin works without a build step on deployment.

---

## Geocoding

If a Google Maps API key is configured in **Events > Settings**, a "Geocode Address" button appears on Location edit screens. Clicking it sends the saved address fields to the Google Maps Geocoding API and populates the Latitude and Longitude fields automatically.

The API key is stored server-side only and never exposed to the browser or front end.
