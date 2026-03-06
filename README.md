# Carkeek Events

A lightweight, developer-friendly WordPress plugin for managing events. Registers Event, Location, and Organizer custom post types with classic meta boxes for structured data, a daily WP cron for expiry and cleanup, optional Google Maps geocoding, and integration with the `carkeek-blocks` custom-archive block.

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
| `carkeek_event` | `/events/` | `/events/{slug}/` | dashicons-calendar-alt |
| `carkeek_location` | — | `/locations/{slug}/` | dashicons-location |
| `carkeek_organizer` | — | `/organizers/{slug}/` | dashicons-groups |

**Taxonomy:** `carkeek_event_category` — hierarchical, registered to `carkeek_event` only. Archive slug: `/event-category/{slug}/`.

---

## Meta Keys Reference

### carkeek_event

| Meta Key | Type | Notes |
|---|---|---|
| `_carkeek_event_start_date` | string | `YYYY-MM-DD` |
| `_carkeek_event_start_time` | string | `HH:MM` (24h) |
| `_carkeek_event_end_date` | string | `YYYY-MM-DD`. Leave blank for open-ended events (they will never expire). |
| `_carkeek_event_end_time` | string | `HH:MM` (24h) |
| `_carkeek_event_location_id` | integer | Linked `carkeek_location` post ID. `0` if none. |
| `_carkeek_event_location_text` | string | Free-text location fallback when no CPT is linked. |
| `_carkeek_event_organizer_id` | integer | Linked `carkeek_organizer` post ID. `0` if none. |
| `_carkeek_event_organizer_text` | string | Free-text organizer fallback when no CPT is linked. |
| `_carkeek_event_website` | string | External registration or info URL. When set, templates render a CTA button. |
| `_carkeek_event_button_label` | string | CTA button label. Defaults to "Sign Up" at render time if blank. |
| `_carkeek_event_hidden` | boolean | Set to `1` by cron when event expires. |
| `_carkeek_event_hidden_date` | string | `YYYY-MM-DD`. Date cron hid the event. Used for grace period calculation. |

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

The `CarkeekEvents_Display` class provides four static methods for rendering event data. Use these in any custom template — they handle formatting, settings-driven display modes, and the filterable output pattern.

All methods return escaped HTML strings. Output them with `wp_kses_post()`.

---

### `format_date_range()`

Formats a start/end date and time pair into a human-readable string. Respects the Date Format and Time Format settings under **Events > Settings**; falls back to the site's WordPress date/time settings.

**Same-day events:** `March 15, 2026, 10:00 am – 2:00 pm`
**Multi-day events:** `March 15, 2026, 10:00 am – March 16, 2026, 5:00 pm`

```php
/**
 * @param string $start_date  YYYY-MM-DD
 * @param string $start_time  HH:MM (may be empty)
 * @param string $end_date    YYYY-MM-DD (may be empty)
 * @param string $end_time    HH:MM (may be empty)
 * @return string Formatted HTML string, or empty string if no start date.
 */
CarkeekEvents_Display::format_date_range( $start_date, $start_time, $end_date, $end_time );
```

**Usage in a template:**

```php
$post_id    = get_the_ID();
$date_range = CarkeekEvents_Display::format_date_range(
    get_post_meta( $post_id, '_carkeek_event_start_date', true ),
    get_post_meta( $post_id, '_carkeek_event_start_time', true ),
    get_post_meta( $post_id, '_carkeek_event_end_date', true ),
    get_post_meta( $post_id, '_carkeek_event_end_time', true )
);

if ( $date_range ) {
    echo '<div class="event-dates">' . wp_kses_post( $date_range ) . '</div>';
}
```

**Filter:** `carkeek_events_date_range( $output, $start_date, $start_time, $end_date, $end_time )`

---

### `get_location_html()`

Builds the location display HTML per the **Location Display** setting in **Events > Settings**:

- `link` (default) — location name linked to its CPT single page
- `address` — formatted address block (name, street, city, state/zip/country)
- `address_directions` — address block with a "Get Directions" link to Google Maps

Falls back to the free-text location string if no CPT post is linked.

```php
/**
 * @param int    $location_id   Linked carkeek_location post ID (0 if none).
 * @param string $location_text Free-text fallback.
 * @param int    $post_id       Parent event post ID (passed to filter).
 * @return string HTML, or empty string if nothing to display.
 */
CarkeekEvents_Display::get_location_html( $location_id, $location_text, $post_id );
```

**Usage in a template:**

```php
$post_id      = get_the_ID();
$location_id  = (int) get_post_meta( $post_id, '_carkeek_event_location_id', true );
$location_txt = get_post_meta( $post_id, '_carkeek_event_location_text', true );
$location_html = CarkeekEvents_Display::get_location_html( $location_id, $location_txt, $post_id );

if ( $location_html ) {
    echo '<div class="event-location">' . wp_kses_post( $location_html ) . '</div>';
}
```

**Filter:** `carkeek_events_location_display( $html, $post_id )`

---

### `get_organizer_html()`

Builds the organizer display HTML per the **Organizer Display** setting in **Events > Settings**:

- `link` (default) — organizer name linked to their CPT single page
- `info` — name + email (linked) + phone (linked) + website inline

Falls back to the free-text organizer string if no CPT post is linked.

```php
/**
 * @param int    $organizer_id   Linked carkeek_organizer post ID (0 if none).
 * @param string $organizer_text Free-text fallback.
 * @param int    $post_id        Parent event post ID (passed to filter).
 * @return string HTML, or empty string if nothing to display.
 */
CarkeekEvents_Display::get_organizer_html( $organizer_id, $organizer_text, $post_id );
```

**Usage in a template:**

```php
$post_id       = get_the_ID();
$org_id        = (int) get_post_meta( $post_id, '_carkeek_event_organizer_id', true );
$org_txt       = get_post_meta( $post_id, '_carkeek_event_organizer_text', true );
$organizer_html = CarkeekEvents_Display::get_organizer_html( $org_id, $org_txt, $post_id );

if ( $organizer_html ) {
    echo '<div class="event-organizer">' . wp_kses_post( $organizer_html ) . '</div>';
}
```

**Filter:** `carkeek_events_organizer_display( $html, $post_id )`

---

### `get_event_link_html()`

Returns an `<a>` tag linking to the event's website/registration URL (`_carkeek_event_website`). The label comes from `_carkeek_event_button_label`, defaulting to "Sign Up" when blank. Returns empty string if no URL is set.

The `<a>` tag includes the class `wp-element-button` so it picks up the theme's button styles automatically.

```php
/**
 * @param int $post_id Event post ID.
 * @return string HTML <a> tag, or empty string if no URL is set.
 */
CarkeekEvents_Display::get_event_link_html( $post_id );
```

**Usage in a template:**

```php
$event_link = CarkeekEvents_Display::get_event_link_html( get_the_ID() );

if ( $event_link ) {
    echo '<div class="event-link">' . wp_kses_post( $event_link ) . '</div>';
}
```

**Filter:** `carkeek_events_link_html( $html, $post_id, $url, $label )`

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

    $post_id       = get_the_ID();
    $date_range    = CarkeekEvents_Display::format_date_range(
        get_post_meta( $post_id, '_carkeek_event_start_date', true ),
        get_post_meta( $post_id, '_carkeek_event_start_time', true ),
        get_post_meta( $post_id, '_carkeek_event_end_date', true ),
        get_post_meta( $post_id, '_carkeek_event_end_time', true )
    );
    $location_html = CarkeekEvents_Display::get_location_html(
        (int) get_post_meta( $post_id, '_carkeek_event_location_id', true ),
        get_post_meta( $post_id, '_carkeek_event_location_text', true ),
        $post_id
    );
    $organizer_html = CarkeekEvents_Display::get_organizer_html(
        (int) get_post_meta( $post_id, '_carkeek_event_organizer_id', true ),
        get_post_meta( $post_id, '_carkeek_event_organizer_text', true ),
        $post_id
    );
    $event_link = CarkeekEvents_Display::get_event_link_html( $post_id );
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

$post_id       = $post->ID;
$date_range    = CarkeekEvents_Display::format_date_range(
    get_post_meta( $post_id, '_carkeek_event_start_date', true ),
    get_post_meta( $post_id, '_carkeek_event_start_time', true ),
    get_post_meta( $post_id, '_carkeek_event_end_date', true ),
    get_post_meta( $post_id, '_carkeek_event_end_time', true )
);
$location_html = CarkeekEvents_Display::get_location_html(
    (int) get_post_meta( $post_id, '_carkeek_event_location_id', true ),
    get_post_meta( $post_id, '_carkeek_event_location_text', true ),
    $post_id
);
$organizer_html = CarkeekEvents_Display::get_organizer_html(
    (int) get_post_meta( $post_id, '_carkeek_event_organizer_id', true ),
    get_post_meta( $post_id, '_carkeek_event_organizer_text', true ),
    $post_id
);
$event_link = CarkeekEvents_Display::get_event_link_html( $post_id );
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

### Actions

| Hook | Args | Purpose |
|---|---|---|
| `carkeek_events_meta_box_after_dates` | `$post` | Add fields after dates in the event meta box |
| `carkeek_events_meta_box_after_location` | `$post` | Add fields after location in the event meta box |
| `carkeek_events_meta_box_after_organizer` | `$post` | Add fields after organizer in the event meta box |
| `carkeek_events_meta_box_after_link` | `$post` | Add fields after the website/button section |
| `carkeek_events_before_hide` | `$post_id` | Fires before cron hides an event |
| `carkeek_events_before_delete` | `$post_id` | Fires before cron permanently deletes an event |
| `carkeek_events_after_geocode` | `$post_id, $lat, $lng` | Fires after geocoding completes |

---

## Settings Reference

All settings are stored as a single array under the option key `carkeek_events_settings`.

| Key | Default | Notes |
|---|---|---|
| `expiry_behavior` | `end_of_day` | `end_of_day` \| `immediate` \| `never` |
| `deletion_grace_period` | `30` | Integer, days. Minimum 1. |
| `use_plugin_template` | `1` | `1` = use plugin template, `0` = use theme template |
| `date_format` | `''` | PHP date format string. Falls back to WP site setting. |
| `time_format` | `''` | PHP date format string. Falls back to WP site setting. |
| `location_display` | `link` | `link` \| `address` \| `address_directions` |
| `organizer_display` | `link` | `link` \| `info` |
| `google_maps_api_key` | `''` | Stored server-side only. Never exposed to the browser. |

---

## Expiry and Cron

A daily WP cron job (`carkeek_events_daily_cron`) automatically hides and then permanently deletes past events:

1. **Pass 1 — Hide:** Events whose `_carkeek_event_end_date` has passed (per the `expiry_behavior` setting) receive `_carkeek_event_hidden = 1`. They disappear from the front end immediately.
2. **Pass 2 — Delete:** Events hidden longer than `deletion_grace_period` days are permanently deleted with `wp_delete_post( $id, true )`.

Events with no `_carkeek_event_end_date` are never hidden or deleted.

**Warning:** Permanently deleted events cannot be recovered.

**Manual trigger:**

```bash
wp cron event run carkeek_events_daily_cron
```

Or use the **Run Expiry Check Now** button in **Events > Settings**.

---

## Geocoding

If a Google Maps API key is configured in **Events > Settings**, a "Geocode Address" button appears on Location edit screens. Clicking it sends the saved address fields to the Google Maps Geocoding API and populates the Latitude and Longitude fields automatically.

The API key is stored server-side only and never exposed to the browser or front end.
