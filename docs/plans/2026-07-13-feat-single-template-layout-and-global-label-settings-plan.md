---
title: "feat: Classic single-event template layout + global label/display settings"
type: feat
status: completed
date: 2026-07-13
origin: docs/brainstorms/2026-07-13-single-template-layout-and-settings-brainstorm.md
---

# ✨ feat: Classic Single-Event Template Layout + Global Label/Display Settings

> **Implementation status (2026-07-13):** Implemented on branch `feat/single-template-layout-settings`.
> Verified via PHP lint (6 files), `npm run build`, `node --check`, and a **10-assertion resolver harness**
> (landing-URL fallback chain, label defaults, blank-label respected, Add-to-Calendar toggle, separator
> sanitize keeps `<br/>` / strips other tags). Build confirmed: details block keeps its registered
> attributes (no invalidation) but the label/separator inspector controls are removed. Acceptance boxes
> below are the live-site QA checklist carried in the PR.

## Overview

Rebuild the classic single-event template (`templates/single-carkeek_event.php`) to the two-column
design our themes use, move the label/separator options out of the `event-details` block into **global
settings** shared by the template and the block, and add extension hooks so themes can customize per site.
Origin: `docs/brainstorms/2026-07-13-single-template-layout-and-settings-brainstorm.md`.

Reference design: [Figma – Jessica Gigot event](https://www.figma.com/design/Xxns2bIpjJAzGMYdn8Kgym/Jessica-Gigot?node-id=987-248).

```
EVENTS                         ← uppercase tag → events landing page
Grounding In: The Poetry…      ← <h1>
┌ meta (left) ───────┐  ┌ media (right) ──────┐
│ Date and Time      │  │ [ featured image ]  │
│ Thu, Nov 9, 2025   │  │ [ Add to Calendar ] │ ← optional, under image
│ 1:00 – 4:00 pm     │  └─────────────────────┘
│ Location / value   │
│ SIGN UP            │
└────────────────────┘
Interdum et malesuada…         ← full-width the_content()
```

## ⚠️ Important scoping note (template selection)

`single-carkeek_event.php` is loaded by `carkeek_events_single_template()`
(`class-carkeekevents-template-loader.php`) **only when the block editor is disabled**
(`disable_block_editor = '1'`); otherwise `single-carkeek_event-blocks.php` is used (see PR #8). So this
Figma layout renders on **classic-editor sites** (or via a theme override). Block-editor sites compose the
page from blocks — which now read the same global settings, so labels/separators stay consistent. Making
the block template's PHP fallback adopt this layout is **out of scope** (noted under Future Considerations).

## Decisions Carried From the Brainstorm

(see brainstorm §Key Decisions — all resolved 2026-07-13)

| Decision | Choice |
|---|---|
| Events landing target | **URL setting** `events_landing_url`; blank → event archive link. |
| Label/separator options | **Global settings**, used by the classic template **and** the details block; **remove the block controls**. |
| Add to Calendar in template | **Separate setting** `show_add_to_calendar_single`, independent of the feature toggle. |
| Title-area hooks | **Both** a filter on the whole tag+title block **and** before/after actions. |
| Styling | **Minimal structural CSS** (grid + mobile stack), no colors/fonts, dequeuable. |
| Block backward-compat | Keep the removed attributes **registered** in `block.json` (drop only the inspector UI + render usage) — dynamic block, no invalidation. |

## Proposed Solution

1. **New global settings** (Display section) with resolver methods on `CarkeekEvents_Display`.
2. **Rebuild the classic template** to the two-column layout using those resolvers + new hooks.
3. **Details block cleanup**: remove inspector controls; `render.php` reads the global settings.
4. **Minimal structural CSS**, enqueued on single events using the plugin template.

## Technical Approach

### 1. New settings — `includes/class-carkeekevents-settings.php`

Add to the existing **Display** section (`carkeek_events_display_section`, register at `:99`+):

| Key | Type | Default | Sanitize |
|---|---|---|---|
| `events_landing_url` | url text | `''` (→ archive link) | `esc_url_raw` (allows relative `/events/`) |
| `datetime_label` | text | `Date and Time` | `sanitize_text_field` |
| `datetime_separator` | text | `<br/>` | `wp_kses( $v, array( 'br' => array() ) )` — keeps `<br>`, `,`, `\|`, text |
| `location_label` | text | `Location` | `sanitize_text_field` |
| `organizer_label` | text | `Organizer` | `sanitize_text_field` |
| `show_add_to_calendar_single` | checkbox | `'1'` | `! empty() ? '1' : '0'` |

Each label uses the **blank = hide** convention already honored by the display helpers (they omit the
label wrapper when the label arg is empty). `datetime_separator` is trusted HTML concatenated into
`format_date_range()`, so `wp_kses` restricting to `<br>` is the safe sanitizer.

### 2. Resolver methods — `CarkeekEvents_Display`

One source of truth; both the template and the block render call these:

```php
public static function datetime_label()      { return self::setting( 'datetime_label', __( 'Date and Time', 'carkeek-events' ) ); }
public static function datetime_separator()  { return self::setting( 'datetime_separator', '<br/>' ); }
public static function location_label()       { return self::setting( 'location_label', __( 'Location', 'carkeek-events' ) ); }
public static function organizer_label()      { return self::setting( 'organizer_label', __( 'Organizer', 'carkeek-events' ) ); }
public static function show_add_to_calendar_single() { /* returns bool, default true */ }

public static function events_landing_url() {
    $settings = get_option( CARKEEKEVENTS_OPTION_NAME, array() );
    if ( ! empty( $settings['events_landing_url'] ) ) { return $settings['events_landing_url']; }
    $archive = get_post_type_archive_link( 'carkeek_event' );   // false when has_archive disabled
    if ( $archive ) { return $archive; }
    $slug = $settings['archive_slug'] ?? 'events';
    return home_url( '/' . $slug . '/' );                        // custom archive-page fallback
}
```

(`setting()` = tiny private helper reading the option array with a default. Labels default to their
strings even on existing installs, so the classic template gains labels — intended per the design.)

### 3. Classic template rebuild — `templates/single-carkeek_event.php`

```php
$dt   = CarkeekEvents_Display::get_date_range_html( $post_id, CarkeekEvents_Display::datetime_separator(), CarkeekEvents_Display::datetime_label() );
$loc  = CarkeekEvents_Display::get_event_location_html( $post_id, CarkeekEvents_Display::location_label() );
$org  = CarkeekEvents_Display::get_event_organizer_html( $post_id, CarkeekEvents_Display::organizer_label() );
$link = CarkeekEvents_Display::get_event_link_html( $post_id );
$atc  = CarkeekEvents_Display::show_add_to_calendar_single() ? CarkeekEvents_Display::get_add_to_calendar_html( $post_id ) : '';

$has_media = has_post_thumbnail() || $atc || has_action( 'carkeek_events_before_featured_image' ) || has_action( 'carkeek_events_after_featured_image' );
```

Structure:
- **Title block** (filterable): buffer `do_action('carkeek_events_before_title',$id)` + tag (`<a href=events_landing_url()>Events</a>`) + `<h1>` + `do_action('carkeek_events_after_title',$id)`, then
  `echo apply_filters( 'carkeek_events_single_title_block', $buffered_html, $post_id );`.
- **Body**: `<div class="carkeek-event__body <?php echo $has_media ? '' : 'carkeek-event__body--no-media'; ?>">`
  - `.carkeek-event__meta` → date/time, location, organizer (each gated on non-empty), then `.carkeek-event__link` (SIGN UP).
  - `.carkeek-event__media` (only when `$has_media`) → `before_featured_image` action, thumbnail, `$atc`, `after_featured_image` action.
- **Content**: `.carkeek-event__content entry-content` → `the_content()`.

All dynamic strings escaped/`wp_kses_post()` as today.

### 4. New hooks (documented developer API)

| Hook | Type | Args |
|---|---|---|
| `carkeek_events_single_title_block` | filter | `( $html, $post_id )` — replace whole tag+title |
| `carkeek_events_before_title` / `carkeek_events_after_title` | action | `( $post_id )` |
| `carkeek_events_before_featured_image` / `carkeek_events_after_featured_image` | action | `( $post_id )` |

Existing helper filters (`carkeek_events_date_range`, `carkeek_events_location_display`, …) are unchanged.

### 5. Details block cleanup

- `src/event-details/edit.js` — remove the `PanelBody` of label/separator/directions `TextControl`s
  (`:26`–`:55`); update the placeholder note to "Labels are set in Events ▸ Settings." Keep
  `ServerSideRender`.
- `src/event-details/render.php` — replace `$attributes['dateTimeSeparator'|'dateTimeLabel'|'locationLabel'|'showDirectionsLink'|'organizerLabel']` (`:56`–`:58`) with the resolver methods. Directions now
  follow the global `location_display` setting (`address_directions`), so `showDirectionsLink` is dropped;
  call `get_event_location_html( $post_id, self::location_label() )`.
- `src/event-details/block.json` — **leave the attributes registered** (dynamic block → no invalidation);
  only the UI + render usage go away. `npm run build` to recompile.

### 6. Minimal structural CSS — `assets/css/carkeek-events-single.css`

```css
.carkeek-event__body { display:grid; grid-template-columns:1fr 1fr; gap:2rem; align-items:start; }
.carkeek-event__body--no-media { grid-template-columns:1fr; }
@media (max-width:782px) { .carkeek-event__body { grid-template-columns:1fr; } }
.carkeek-event__tag { text-transform:uppercase; letter-spacing:.05em; font-size:.85em; }
```

No colors/fonts. Enqueue on `wp_enqueue_scripts` when `is_singular('carkeek_event')` **and** the plugin
template is active (`use_plugin_template` on), gated by a `carkeek_events_enqueue_single_css` filter
(default true) so a theme disables it in one line. (New enqueue lives in `class-carkeekevents-admin.php`'s
front-end sibling or a small hook in the template-loader file.)

## System-Wide Impact

- **Interaction graph:** template/block → resolver methods → `get_option` (settings). The details block's
  `ServerSideRender` editor preview now renders from settings, so **editor preview matches the front end**
  (previously driven by per-block attributes).
- **Backward-compat / state:** existing `event-details` blocks keep their old attributes in the block
  delimiter; they are ignored (dynamic block, no `save()` markup → no validation error). Sites that had
  **custom block labels** switch to the global settings values — acceptable (most use defaults; bespoke
  needs use the title-block filter / theme override).
- **Behavior change on existing classic-template sites:** the template now renders labels ("Date and
  Time", "Location") and the two-column layout where before it showed an unlabelled single column. Intended
  per the design; call out in the PR so it isn't a surprise.
- **API surface parity:** the classic template and the details block are the two surfaces that render event
  meta; both route through the same resolvers, so they stay in lockstep. The block-template PHP fallback
  (`single-carkeek_event-blocks.php`) still uses the helpers without labels — left as-is (Future).
- **No new persisted post data**, no migrations, no endpoints. Pure settings + presentation.

## Acceptance Criteria

- [x] Six new Display settings render, save, and sanitize correctly (separator keeps `<br/>`; landing URL accepts relative + absolute).
- [x] Classic single template shows: tag → title → two-column (meta | media) → full-width content, matching the Figma structure.
- [x] The "Events" tag links to `events_landing_url` (or the archive when blank).
- [x] Date/Time, Location, Organizer labels come from settings; **a blank label hides that label**; the date/time separator setting changes the date↔time separator.
- [x] Add to Calendar shows under the image only when `show_add_to_calendar_single` is on (and the feature is enabled); otherwise absent.
- [x] No featured image / no media content → meta column spans full width (no empty column).
- [x] `carkeek_events_single_title_block` filter replaces the tag+title; the four before/after actions fire in the right places.
- [x] The details block inspector no longer shows label/separator/directions controls; the block renders from the global settings and its editor preview matches the front end.
- [x] Existing `event-details` blocks do not throw block-validation errors after the change.
- [x] Structural CSS loads on single events using the plugin template and can be disabled via `carkeek_events_enqueue_single_css`.
- [x] `php -l` clean; `npm run build` succeeds; no console errors in the block editor.

## Dependencies & Risks

- **Template only renders when block editor is disabled** *(clarity risk)* — documented above; if a design
  site is on the block editor, this layout won't appear (they compose via blocks). Flag in the PR.
- **Separator sanitization** *(low)* — `wp_kses` to `<br>` prevents injecting arbitrary HTML while allowing
  the intended `<br/>` / `,` / `|`.
- **Custom block labels lost** *(low/medium)* — sites that set per-block labels adopt the global values;
  mitigated by the title-block filter + theme override + a one-time settings pass.
- **Enqueue placement** *(low)* — ensure the CSS only loads front-end on single events, not admin.

## Future Considerations (out of scope)
- Apply the same layout to the block template's PHP fallback for parity.
- A `carkeek_events_before_meta` / `after_meta` action pair if themes want to inject rows.
- Optional tag-label setting (currently a translatable "Events", customizable via the title-block filter).

## Files Touched
| File | Change |
|---|---|
| `includes/class-carkeekevents-settings.php` | +6 Display fields, callbacks, sanitize; docblock defaults |
| `includes/class-carkeekevents-display.php` | resolver methods (`datetime_label`/`_separator`/`location_label`/`organizer_label`/`events_landing_url`/`show_add_to_calendar_single`) + `setting()` helper |
| `templates/single-carkeek_event.php` | rebuilt two-column layout + hooks |
| `src/event-details/edit.js` | remove label/separator/directions controls; update note |
| `src/event-details/render.php` | read resolvers instead of attributes; drop `showDirectionsLink` |
| `assets/css/carkeek-events-single.css` | **new** minimal structural CSS + enqueue |
| `carkeek-events.php` | version bump (preserve Git Updater headers) |
| `README.md` | document settings, hooks, template layout, block cleanup |

## Sources & References

### Origin
- **Brainstorm:** `docs/brainstorms/2026-07-13-single-template-layout-and-settings-brainstorm.md` — carried
  decisions: URL landing setting, global label/separator settings replacing block controls, separate
  Add-to-Calendar template toggle, filter + before/after actions, minimal structural CSS.

### Internal references (file:line)
- Classic template (rebuild target) — `templates/single-carkeek_event.php`
- Template selector (block-editor-off condition) — `includes/class-carkeekevents-template-loader.php` `carkeek_events_single_template()`
- Display helpers + label/separator params — `includes/class-carkeekevents-display.php` (`get_date_range_html`, `get_event_location_html`, `get_event_organizer_html`, `get_add_to_calendar_html`)
- Settings section + sanitize — `includes/class-carkeekevents-settings.php:99` (Display section), `:540`+ (sanitize)
- Details block controls to remove — `src/event-details/edit.js:26`–`:55`
- Details block render consuming attributes — `src/event-details/render.php:56`–`:58`
- Add-to-Calendar feature toggle (independent) — `use_add_to_calendar` / `CarkeekEvents_Display::field_enabled('add_to_calendar')`

### Related work
- PR #8 — two single templates + `disable_block_editor` (defines when the classic template loads).
- PR #9 — Add to Calendar module (`get_add_to_calendar_html`).
- PR #15 — name→website linking in non-link modes (adjacent display behavior).
