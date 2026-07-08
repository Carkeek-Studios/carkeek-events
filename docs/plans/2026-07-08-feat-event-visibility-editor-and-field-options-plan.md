---
title: "feat: Event editor mode, field-in-use options, and hide-from-calendar visibility"
type: feat
status: completed
date: 2026-07-08
---

# ✨ feat: Event Editor Mode, Field-in-Use Options, and Hide-from-Calendar Visibility

> **Implementation status (2026-07-08):** All four features implemented on branch
> `feat/event-visibility-editor-field-options`. Verified via PHP lint, successful
> `npm run build`, and JS syntax checks. The acceptance-criteria checkboxes below
> are the **live-site QA checklist** carried in the PR — this environment has no
> WP-CLI or single target site, so runtime behavior is verified during PR review.

## Overview

Four related settings/UX additions to the Carkeek Events plugin, bundled because they all touch the same surfaces (settings, the event meta box, the display helpers, the archive block query, and the single-event template):

1. **Disable the block editor** for the plugin's CPTs (Events, Locations, Organizers) via a settings toggle, and load a **lean block-based single-event template** when the block editor is on vs. the **current classic PHP template** when it is off.
2. **Field-in-use settings** — hide the **Location**, **Organizer**, and **Registration button** fields (both the admin editor inputs *and* front-end/archive output) when a site does not use them.
3. **Hide an event from the calendar** — a manual per-event "Hide from calendar" toggle in the editor's right pane that excludes the event from the **events-archive block** and **WordPress search**, while the **published permalink stays viewable by anyone**.
4. **Show-hidden opt-in** on the events-archive block so a specific block instance can include hidden events.

This is the WordPress-native re-implementation of the visibility feature that was deliberately removed in `docs/plans/2026-03-31-refactor-remove-event-hidden-flag-plan.md`. That refactor removed the old `_carkeek_event_hidden` system because it relied on a **cron auto-hide pass** and a **racy dual-write REST sidebar**, and it silently broke published events. This plan brings back *only* the manual, opt-in visibility control — no cron, no auto-hide, no dual-write.

## Problem Statement / Motivation

- **Editor choice.** Some editors prefer the classic editor for structured event content; the block editor adds friction for a data-driven CPT. There is currently no way to turn it off. When it is off, the current single template still assumes the plugin renders the meta header; when it is on, sites want to compose the page with the `event-details`/`event-date-time`/etc. blocks and not have the PHP template duplicate that output.
- **Unused fields clutter the editor.** Not every site uses Locations, Organizers, or a registration button. The front-end display helpers already suppress *empty* values, but the **admin meta box always shows all input rows**, and a site that never uses Organizers still sees the Organizer picker. Editors want to switch whole field groups off site-wide.
- **Some events should not appear on the calendar.** Editors need to publish an event that is reachable by direct link (email, social) but is **not** listed on the main archive and **not** surfaced by site search — e.g. a private/registration-only session. The previous implementation over-reached (main-query filtering + cron) and caused support problems. The new one must be **narrow and predictable**: exclude from the archive block + search only; leave `post_status = publish` untouched so direct links always work.

## Decisions Carried Into This Plan

Resolved during planning (see the four AskUserQuestion answers on 2026-07-08):

| Question | Decision |
|---|---|
| What are the "two templates"? | **Two front-end single-event templates.** `single-carkeek_event-blocks.php` (lean — `the_content()` only) loads when the block editor is on for events; `single-carkeek_event.php` (current — PHP meta header + content) loads when it is off. Selection is automatic from the block-editor setting. |
| Where does field-hiding apply? | **Admin editor inputs *and* front-end/archive output.** A site-level "Fields in use" setting hides the meta-box input rows *and* suppresses the corresponding output in the single template, `event-details` block, and archive block. |
| Where does the hide toggle render? | **Both editor modes.** Block editor: an "Event Options" `PluginDocumentSettingPanel` (single `useEntityProp` write — race-free). Classic editor: a `side`-context meta-box checkbox. Both persist the same `_carkeek_event_hidden` meta. |
| Disable-block-editor scope? | **All three CPTs** (`carkeek_event`, `carkeek_location`, `carkeek_organizer`) via one setting. |

## Research Findings (Local)

Consolidated from a read of the plugin source. Key file:line anchors the implementation will touch:

- **Settings** — `includes/class-carkeekevents-settings.php`
  - Sections registered in `register_settings()` (`:46`); `sanitize_settings()` (`:447`). All settings live under one option array `carkeek_events_settings` (`CARKEEKEVENTS_OPTION_NAME`). Saving the option already triggers `flush_rewrite_rules` via `update_option_carkeek_events_settings` (`carkeek-events.php:120`).
  - Existing `use_plugin_template` radio (`:184`) already chooses plugin vs. theme template — the new template logic composes *under* the "plugin template" branch.
  - Existing `location_display` (`:274`) / `organizer_display` (`:303`) selects — the new "Fields in use" checkboxes gate these.
- **Post types** — `includes/class-carkeekevents-post-types.php:68` registers `carkeek_event` with `supports => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' )`. `'editor'` support is required for the *classic* editor too, so it stays; only the block editor is toggled off via a filter.
- **Meta** — `includes/class-carkeekevents-meta.php:41` `register_meta_fields()`. New `_carkeek_event_hidden` boolean registers here following the existing `$auth_callback` (`:44`, `edit_post`) pattern.
- **Meta box** — `includes/class-carkeekevents-meta-boxes.php`
  - `render_event_meta_box()` (`:82`) renders Location (`:156`), Organizer (`:167`), Website + Button (`:178`–`:196`) rows separated by `<hr>`.
  - ⚠️ **Save gotcha:** `save_event_meta()` (`:356`) writes `_carkeek_event_location_id` / `_carkeek_event_organizer_id` with a default of `0` (`:396`, `:411`) whenever the mode field is posted. If a hidden input row stops submitting those fields, the save must **not** run that branch or it will **zero out existing links**. Website/button saves (`:422`, `:432`) are already `isset()`-guarded and safe.
- **Query** — `includes/class-carkeekevents-query.php`
  - `apply_default_sort()` (`:46`) is a `pre_get_posts` hook on front-end main queries for `carkeek_event`. The search + built-in-archive exclusion attaches here.
- **Archive block** — `includes/class-carkeekevents-block.php`
  - `build_query_args()` (`:224`) builds the `WP_Query` args; **the old hidden-exclusion is still present as a commented stub at `:243`–`:245`** — the new clause replaces it.
  - `render_slot()` (`:471`) dispatches `location`/`organizer` slots to `render_location_name()`/`render_organizer_name()` (these bypass the display helpers, so field-hiding needs a guard here too).
- **Archive block metadata / UI** — `src/events-archive/block.json:11` (attributes) and `src/events-archive/inspector.js` (Behavior panel at `:464`) — add `showHidden`.
- **Display helpers** — `includes/class-carkeekevents-display.php`: `get_event_location_html()` (`:140`), `get_event_organizer_html()` (`:298`), `get_event_link_html()` (`:265`). Field-in-use early-returns go here so single template + `event-details` block inherit them for free.
- **Single template** — `templates/single-carkeek_event.php` renders the PHP meta header (`:36`–`:60`) + `the_content()` (`:64`). This becomes the "classic" template; a new lean template is added alongside.
- **Template loader** — `includes/class-carkeekevents-template-loader.php`: `carkeek_events_single_template()` (`:95`) already branches on `use_plugin_template` (`:103`) and supports theme overrides via `locate_template()` (`:114`). This is where the block-vs-classic selection lives.
- **Event-details block render** — `src/event-details/render.php:54` composes the three display helpers via `array_filter` — inherits field-hiding automatically once the helpers early-return.
- **Build tooling note** — the previous refactor deleted `webpack.config.js`; `@wordpress/scripts` now auto-discovers only `src/**/block.json` entries. The block-editor "Event Options" panel is **not** a block, so it needs either a no-build hand-authored script or a re-introduced webpack entry (see Feature 3 → "Build path decision").
- **Old implementation history** (for reference only — do not restore): commits `174fa25` (sidebar panel), `0bc097f` (dual-save race), `2dee50a` (removal).

## System-Wide Impact

### Interaction graph
- **Editor mode setting** → `use_block_editor_for_post_type` filter → which editor loads → which single template `carkeek_events_single_template()` returns → whether the block-editor "Event Options" panel enqueues *or* the classic side meta box registers (exactly one, never both).
- **Fields-in-use setting** → gates meta-box row rendering **and** meta-box save branches **and** display-helper early-returns **and** archive `render_slot()` **and** which archive slots are meaningful. One setting, five read sites.
- **Hidden meta** → written by exactly one editor UI at a time → read by archive `build_query_args()`, search `pre_get_posts`, and (optionally) built-in archive `pre_get_posts`. Never read on `is_singular` so direct links are unaffected.

### State lifecycle risks
- **Location/organizer zeroing** (see save gotcha above) — the single most likely regression. Saves must be gated behind the fields-in-use setting.
- **Double toggle** — if both the block-editor panel and the classic side meta box render, an editor sees two "Hide from calendar" controls in the block editor. The side meta box must only register when the block editor is **off** for the post type.
- **Boolean/string storage parity** — the block toggle (boolean via `useEntityProp`) and the classic checkbox (`'1'`/deleted) both resolve to `'1'`/`''` in postmeta; the exclusion clause uses `OR( NOT EXISTS, != '1' )` so both representations behave identically and non-event posts (in search) always pass.

### API surface parity
- Field-hiding must be applied in **all three** output paths (single template via helpers, `event-details` block via helpers, archive block via `render_slot`) — the archive block's `render_location_name`/`render_organizer_name` do **not** call the helpers, so they need their own guard.

### Integration test scenarios
1. Block editor OFF → new event → single page loads `single-carkeek_event.php` and shows the PHP meta header.
2. Block editor ON → event with an `event-details` block → single page loads `single-carkeek_event-blocks.php` and shows **no** duplicated PHP meta header.
3. Organizers disabled site-wide → editor meta box hides the Organizer row; saving an event that previously had an organizer **keeps** the stored organizer id; front-end + archive omit organizer output.
4. Event marked hidden → absent from archive block and from `?s=` search results; direct permalink returns HTTP 200 for a logged-out visitor.
5. Archive block with "Show hidden events" ON → hidden events appear in that block only.

## Feature 1 — Disable Block Editor + Two Single Templates

### Settings
Add to the **Display** section (or a new "Editor" section) in `class-carkeekevents-settings.php`:

- `disable_block_editor` — checkbox, **default `'0'`** (block editor stays on; backward-compatible).

```php
// register_settings() — new field
add_settings_field(
    'disable_block_editor',
    __( 'Editor', 'carkeek-events' ),
    array( $this, 'disable_block_editor_callback' ),
    'carkeek-events',
    'carkeek_events_display_section'
);

// sanitize_settings()
$sanitized['disable_block_editor'] = ! empty( $input['disable_block_editor'] ) ? '1' : '0';
```

Callback renders a single checkbox: *"Use the Classic editor for Events, Locations, and Organizers (disables the block editor for these post types)."*

### Editor toggle
New tiny helper (add to `class-carkeekevents-admin.php` or a new `includes/class-carkeekevents-editor.php`), hooked on `use_block_editor_for_post_type`:

```php
add_filter( 'use_block_editor_for_post_type', function ( $enabled, $post_type ) {
    $settings = get_option( CARKEEKEVENTS_OPTION_NAME, array() );
    $cpts     = array( 'carkeek_event', 'carkeek_location', 'carkeek_organizer' );
    if ( ! empty( $settings['disable_block_editor'] ) && in_array( $post_type, $cpts, true ) ) {
        return false;
    }
    return $enabled;
}, 10, 2 );
```

`'editor'` support stays in `register_post_type` so the classic TinyMCE editor renders. Existing classic meta boxes are unaffected.

### Two front-end templates
- Keep `templates/single-carkeek_event.php` (the **classic** template, unchanged).
- Add `templates/single-carkeek_event-blocks.php` (the **block** template):

```php
<?php
/**
 * Single Event Template — block editor variant.
 * Renders block-composed content only. If the author did not add a
 * carkeek-events/event-details block, fall back to the PHP meta header
 * so dates/location/organizer are never silently lost.
 *
 * Theme override: {theme}/carkeek-events/single-carkeek_event-blocks.php
 */
get_header();
while ( have_posts() ) : the_post();
    $post_id = get_the_ID(); ?>
    <article id="post-<?php the_ID(); ?>" <?php post_class( 'carkeek-event' ); ?>>
        <h1 class="carkeek-event__title"><?php the_title(); ?></h1>
        <?php if ( ! has_block( 'carkeek-events/event-details', $post_id ) ) : ?>
            <div class="carkeek-event__meta"><?php
                echo wp_kses_post( CarkeekEvents_Display::get_date_range_html( $post_id ) );
                echo wp_kses_post( CarkeekEvents_Display::get_event_location_html( $post_id ) );
                echo wp_kses_post( CarkeekEvents_Display::get_event_organizer_html( $post_id ) );
                echo wp_kses_post( CarkeekEvents_Display::get_event_link_html( $post_id ) );
            ?></div>
        <?php endif; ?>
        <div class="carkeek-event__content entry-content"><?php the_content(); ?></div>
    </article>
<?php endwhile; get_footer();
```

> The `has_block()` safety net is a deliberate refinement of the "the_content only" spec: it prevents a real footgun where a new block-editor event that lacks the details block would render with no dates. It is one cheap conditional and can be dropped if undesired.

### Template selection
In `carkeek_events_single_template()` (`class-carkeekevents-template-loader.php:95`), after the `use_plugin_template === '0'` early return, choose the filename by editor mode:

```php
$settings = get_option( CARKEEKEVENTS_OPTION_NAME, array() );
$use_blocks = empty( $settings['disable_block_editor'] );      // block editor on → block template
$file       = $use_blocks ? 'single-carkeek_event-blocks.php' : 'single-carkeek_event.php';

$theme_template = locate_template( array( 'carkeek-events/' . $file ) );
if ( $theme_template ) { return $theme_template; }
$plugin_template = CARKEEKEVENTS_PLUGIN_DIR . 'templates/' . $file;
return file_exists( $plugin_template ) ? $plugin_template : $template;
```

Both filenames get theme-override support.

### Acceptance criteria
- [ ] Settings shows an "Editor" checkbox; default unchecked preserves today's block-editor behavior.
- [ ] With it checked, editing an Event/Location/Organizer loads the classic editor (no block canvas).
- [ ] Block editor ON → single event loads `single-carkeek_event-blocks.php`; with an `event-details` block present, no duplicate PHP meta header appears.
- [ ] Block editor OFF → single event loads `single-carkeek_event.php` (current output unchanged).
- [ ] Theme override at `{theme}/carkeek-events/single-carkeek_event-blocks.php` is respected.

## Feature 2 — Field-in-Use Settings (Location / Organizer / Button)

### Settings
New **"Fields in Use"** section (or three checkboxes appended to Display) in `class-carkeekevents-settings.php`, all **default `'1'`** (backward-compatible — existing sites keep every field):

- `use_locations` — checkbox
- `use_organizers` — checkbox
- `use_button` — checkbox (the registration website URL + button label)

```php
// sanitize_settings() — default ON when key absent (fresh installs & upgrades keep fields)
$sanitized['use_locations']  = array_key_exists( 'use_locations',  $input ) ? ( ! empty( $input['use_locations'] )  ? '1' : '0' ) : '1';
$sanitized['use_organizers'] = array_key_exists( 'use_organizers', $input ) ? ( ! empty( $input['use_organizers'] ) ? '1' : '0' ) : '1';
$sanitized['use_button']     = array_key_exists( 'use_button',     $input ) ? ( ! empty( $input['use_button'] )     ? '1' : '0' ) : '1';
```

> Note the `array_key_exists` guard: an unchecked checkbox is absent from `$_POST`, so a plain `! empty()` would flip a value the user *did* intend to turn off. Because the settings form always submits all three checkboxes on that page, use the standard "present-in-form" convention already used by `disable_wp_archive`.

Add a small helper to read them:

```php
// e.g. CarkeekEvents_Display::field_enabled( 'locations' | 'organizers' | 'button' ) : bool
```

### Admin meta box (`render_event_meta_box`)
Wrap each row group (and its surrounding `<hr>`) in a settings check:

```php
$use_loc = CarkeekEvents_Display::field_enabled( 'locations' );
$use_org = CarkeekEvents_Display::field_enabled( 'organizers' );
$use_btn = CarkeekEvents_Display::field_enabled( 'button' );
// ... render Location block only when $use_loc, Organizer block only when $use_org,
//     Website+Button block only when $use_btn.
```

### Meta box save — **must not zero data** (`save_event_meta`)
Gate the location/organizer branches so a hidden row does not overwrite stored ids:

```php
if ( CarkeekEvents_Display::field_enabled( 'locations' ) && isset( $_POST['carkeek_event_location_mode'] ) ) {
    // existing location save logic
}
if ( CarkeekEvents_Display::field_enabled( 'organizers' ) && isset( $_POST['carkeek_event_organizer_mode'] ) ) {
    // existing organizer save logic
}
```

The website/button branch is already `isset()`-guarded (`:422`, `:432`) — additionally skip it when `! use_button` so a re-enabled site does not lose data (leave existing meta intact; simply don't render/save).

### Front-end + archive output suppression
Early-return in the display helpers so single template and `event-details` block both honor it:

```php
// get_event_location_html()  → if ( ! field_enabled('locations') )  return '';
// get_event_organizer_html() → if ( ! field_enabled('organizers') ) return '';
// get_event_link_html()      → if ( ! field_enabled('button') )      return '';
```

Archive block bypasses the helpers, so also guard `render_slot()` in `class-carkeekevents-block.php:471`:

```php
case 'location':  return field_enabled('locations')  ? $this->render_location_name( $post_id )  : '';
case 'organizer': return field_enabled('organizers') ? $this->render_organizer_name( $post_id ) : '';
```

(The archive `button_link` slot links to the permalink, *not* the registration URL, so it is out of scope for `use_button`.)

### Acceptance criteria
- [ ] Unchecking "Organizers" hides the Organizer input row in the event meta box.
- [ ] Saving an event whose Organizer field is hidden **retains** the previously stored organizer id (no zeroing).
- [ ] With Organizers off, the single template, `event-details` block, and archive `organizer` slot render nothing.
- [ ] Same three checks pass for Locations and for the registration button.
- [ ] Re-enabling a field restores prior data and UI.
- [ ] Defaults leave all three fields on for existing installs.

## Feature 3 — Hide Event from Calendar + Search (direct link stays public)

### Meta registration (`class-carkeekevents-meta.php`)
```php
register_meta( 'post', '_carkeek_event_hidden', array(
    'object_subtype' => 'carkeek_event',
    'type'           => 'boolean',
    'single'         => true,
    'show_in_rest'   => true,
    'auth_callback'  => $auth_callback, // edit_post — same as sibling fields
) );
```

### Editor UI — both modes, exactly one active

**Block editor: "Event Options" document sidebar panel.** A single toggle bound to the meta via `useEntityProp` (single write — this is the fix for the old dual-write race). Enqueued on `enqueue_block_editor_assets`, restricted to `carkeek_event`.

**Build path decision (recommended: no-build script).** Because `webpack.config.js` was intentionally removed and `@wordpress/scripts` only auto-builds `block.json` entries, avoid re-introducing a build entry for a ~40-line panel. Ship a hand-authored `build/event-options.js` using `wp.*` globals (no JSX, no build step):

```js
// build/event-options.js  (enqueued directly; deps: wp-plugins wp-edit-post
//                          wp-components wp-core-data wp-data wp-element wp-i18n)
( function ( wp ) {
    const { registerPlugin } = wp.plugins;
    const { PluginDocumentSettingPanel } = wp.editPost;      // or wp.editor in newer WP
    const { ToggleControl } = wp.components;
    const { useEntityProp } = wp.coreData;
    const { useSelect } = wp.data;
    const { createElement: el } = wp.element;
    const { __ } = wp.i18n;

    registerPlugin( 'carkeek-events-options', {
        render: function () {
            const postType = useSelect( ( s ) => s( 'core/editor' ).getCurrentPostType(), [] );
            if ( postType !== 'carkeek_event' ) return null;
            const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );
            return el( PluginDocumentSettingPanel,
                { name: 'carkeek-event-options', title: __( 'Event Options', 'carkeek-events' ) },
                el( ToggleControl, {
                    label: __( 'Hide from calendar', 'carkeek-events' ),
                    help: __( 'Keeps the event published and reachable by direct link, but hides it from the events archive block and site search.', 'carkeek-events' ),
                    checked: !! meta._carkeek_event_hidden,
                    onChange: ( v ) => setMeta( { ...meta, _carkeek_event_hidden: v } ),
                } )
            );
        },
    } );
}( window.wp ) );
```

> Alternative if the team prefers JSX consistency: add `src/event-options/index.js` and re-introduce a minimal `webpack.config.js` that spreads `@wordpress/scripts` defaults and adds the extra entry. Functionally identical; more tooling. The no-build script is preferred for "don't overcomplicate."

Enqueue (in the same editor helper as Feature 1):
```php
add_action( 'enqueue_block_editor_assets', function () {
    if ( get_current_screen() && 'carkeek_event' === get_current_screen()->post_type ) {
        wp_enqueue_script( 'carkeek-event-options',
            CARKEEKEVENTS_PLUGIN_URL . 'build/event-options.js',
            array( 'wp-plugins', 'wp-edit-post', 'wp-components', 'wp-core-data', 'wp-data', 'wp-element', 'wp-i18n' ),
            CARKEEKEVENTS_VERSION, true );
    }
} );
```

**Classic editor: `side` meta box** — registered **only when the block editor is off for events** (prevents the double-toggle):

```php
// in add_meta_boxes()
if ( ! use_block_editor_for_post_type( 'carkeek_event' ) ) {
    add_meta_box( 'carkeek_event_options', __( 'Event Options', 'carkeek-events' ),
        array( $this, 'render_event_options_box' ), 'carkeek_event', 'side', 'default' );
}
```

Render a nonce + single checkbox; save in `save_event_meta` (guarded by its own nonce presence):
```php
if ( isset( $_POST['carkeek_event_options_nonce'] ) && wp_verify_nonce( ... ) ) {
    if ( ! empty( $_POST['carkeek_event_hidden'] ) ) {
        update_post_meta( $post_id, '_carkeek_event_hidden', '1' );
    } else {
        delete_post_meta( $post_id, '_carkeek_event_hidden' );
    }
}
```

### Archive block exclusion (`class-carkeekevents-block.php:243`)
Replace the commented stub with a real clause, gated on the new `showHidden` attribute and on the non-alt post type:

```php
if ( ! $config['is_alt'] && empty( $attributes['showHidden'] ) ) {
    $args['meta_query'][] = array(
        'relation' => 'OR',
        array( 'key' => '_carkeek_event_hidden', 'compare' => 'NOT EXISTS' ),
        array( 'key' => '_carkeek_event_hidden', 'value' => '1', 'compare' => '!=' ),
    );
}
```

### Search exclusion (`class-carkeekevents-query.php`)
Add a `pre_get_posts` callback that, on the front-end main **search** query, appends the same `OR( NOT EXISTS, != '1' )` clause. Because non-event posts have no such meta, the `NOT EXISTS` arm lets them through — the clause is safe on a mixed-post-type search.

```php
public function exclude_hidden_from_search( $query ) {
    if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) { return; }
    $meta_query   = $query->get( 'meta_query' ) ?: array();
    $meta_query[] = array(
        'relation' => 'OR',
        array( 'key' => '_carkeek_event_hidden', 'compare' => 'NOT EXISTS' ),
        array( 'key' => '_carkeek_event_hidden', 'value' => '1', 'compare' => '!=' ),
    );
    $query->set( 'meta_query', $meta_query );
}
```

### Built-in archive exclusion (consistency — optional but recommended)
If the built-in WP event archive is enabled (`disable_wp_archive` off), also exclude hidden events there by extending `apply_default_sort()` (or a sibling `pre_get_posts` guarded by `is_post_type_archive( 'carkeek_event' )`) with the same clause. This keeps "the primary calendar" consistent regardless of whether it is the block or the native archive. Skip if you want the absolute minimum footprint.

### Direct link stays public
No `post_status` change and no `is_singular` filtering — a hidden event remains `publish`, so `carkeek_events_single_template()` and the permalink behave exactly as for any published event. **Nothing to implement here; the requirement is satisfied by *not* touching single-view queries.**

### Archive block attribute + inspector
- `src/events-archive/block.json` — add `"showHidden": { "type": "boolean", "default": false }`.
- `src/events-archive/inspector.js` — add a `ToggleControl` in the **Behavior** panel (`:464`):
  *"Show hidden events — include events marked 'Hide from calendar' in this block."* (default off).
- Rebuild so `build/events-archive` picks up the new attribute + control.

### Acceptance criteria
- [ ] "Event Options ▸ Hide from calendar" appears in the block editor sidebar for events (and nowhere for other post types).
- [ ] With the block editor disabled for events, the same toggle appears as a classic `side` meta box, and **only one** control is ever visible.
- [ ] Toggling and saving persists `_carkeek_event_hidden` correctly in both modes (verify via `get_post_meta`).
- [ ] A hidden, published event does **not** appear in the events-archive block or in `?s=` search results.
- [ ] The hidden event's permalink returns HTTP 200 to a logged-out visitor.
- [ ] An events-archive block with "Show hidden events" ON includes hidden events; other blocks still exclude them.
- [ ] No cron pass and no auto-hide behavior is introduced.

## Feature 4 — "Show Hidden Events" Block Opt-In
Covered inline in Feature 3 (attribute + inspector + `build_query_args` gate). Called out separately here only so it is not lost in review: it is the mechanism that lets a single archive instance surface otherwise-hidden events.

## Dependencies & Risks

- **Location/Organizer save zeroing** *(high)* — the most likely regression. Mitigated by gating the save branches (Feature 2). Add an explicit test: disable Organizers, save an event that had one, confirm the id survives.
- **Double hide-toggle** *(medium)* — mitigated by registering the classic side meta box only when the block editor is off for events.
- **Build tooling** *(low)* — the no-build `event-options.js` avoids re-introducing `webpack.config.js`. If JSX is preferred, restore a minimal config that spreads defaults.
- **Block template with no details block** *(low)* — mitigated by the `has_block()` fallback in `single-carkeek_event-blocks.php`.
- **Search clause on mixed post types** *(low)* — the `OR( NOT EXISTS … )` shape is safe; still worth a manual check that non-event search results are unaffected.
- **Settings defaults / upgrades** *(low)* — all new field-in-use keys default ON and `disable_block_editor` defaults OFF, so existing installs see no behavior change until an editor opts in.
- **carkeek-blocks integration** — the `carkeek_events_query_args` / `carkeek_block_custom_post_layout__query_args` path (`class-carkeekevents-query.php:33`, `:73`) is **not** modified here; hidden-exclusion is intentionally scoped to the plugin's own archive block + search. If the external custom-archive block should also hide events, that is a follow-up.

## Build & Release Steps
1. `npm run build` — recompiles `events-archive` (new `showHidden` attribute/control). The hand-authored `build/event-options.js` is not processed by webpack; keep it out of any `build/` clean that only removes generated dirs, or place it under `assets/js/` instead and enqueue from there.
2. Bump `Version:` in `carkeek-events.php` (currently `2.0.18` at `:7`). **Preserve the Git Updater headers** (`GitHub Plugin URI`, `Primary Branch`) exactly.
3. Update `README.md`: document the three new settings, the `_carkeek_event_hidden` meta + "Hide from calendar" behavior (published-but-unlisted), the two single-template filenames + theme-override paths, and the archive block `showHidden` attribute.

## Files Touched (summary)
| File | Change |
|---|---|
| `includes/class-carkeekevents-settings.php` | +`disable_block_editor`, +`use_locations`/`use_organizers`/`use_button`; sanitize + callbacks |
| `includes/class-carkeekevents-admin.php` *(or new `class-carkeekevents-editor.php`)* | `use_block_editor_for_post_type` filter; enqueue `event-options.js` |
| `includes/class-carkeekevents-meta.php` | +register `_carkeek_event_hidden` |
| `includes/class-carkeekevents-meta-boxes.php` | gate field rows; gate saves; classic side "Event Options" box + save |
| `includes/class-carkeekevents-display.php` | field-in-use early-returns; `field_enabled()` helper |
| `includes/class-carkeekevents-query.php` | search (+ optional built-in archive) hidden exclusion |
| `includes/class-carkeekevents-block.php` | real hidden-exclusion clause (replace stub `:243`); slot guards |
| `includes/class-carkeekevents-template-loader.php` | block-vs-classic template selection |
| `templates/single-carkeek_event-blocks.php` | **new** lean block template |
| `src/events-archive/block.json` | +`showHidden` attribute |
| `src/events-archive/inspector.js` | +"Show hidden events" toggle |
| `build/event-options.js` *(or `assets/js/`)* | **new** no-build sidebar panel |
| `carkeek-events.php` | version bump (preserve Git Updater headers) |
| `README.md` | document all four features |

## Sources & References

### Internal (file:line)
- Settings registration & sanitize — `includes/class-carkeekevents-settings.php:46`, `:447`; existing template/display fields `:184`, `:274`, `:303`
- CPT registration & `supports` — `includes/class-carkeekevents-post-types.php:68`
- Meta registration pattern — `includes/class-carkeekevents-meta.php:41`, `:44`
- Meta box render + **save zeroing gotcha** — `includes/class-carkeekevents-meta-boxes.php:82`, `:356`, `:396`, `:411`, `:422`, `:432`
- Display helpers — `includes/class-carkeekevents-display.php:140`, `:265`, `:298`
- Archive query + **commented hidden stub** — `includes/class-carkeekevents-block.php:224`, `:243`, `:471`
- Front-end main-query hook — `includes/class-carkeekevents-query.php:46`
- Template selection + theme override — `includes/class-carkeekevents-template-loader.php:95`, `:103`, `:114`
- Single template (classic) — `templates/single-carkeek_event.php:36`
- `event-details` block render — `src/event-details/render.php:54`
- Archive block metadata + inspector — `src/events-archive/block.json:11`, `src/events-archive/inspector.js:464`

### Related work
- **Origin of the removed feature:** `docs/plans/2026-03-31-refactor-remove-event-hidden-flag-plan.md` — why the old cron/dual-write system was removed; this plan is the WordPress-native replacement it deferred.
- `docs/plans/2026-03-31-feat-event-detail-blocks-plan.md` — the event-detail blocks used by the block template.
- `docs/plans/2026-03-07-feat-alt-post-type-button-slot-plan.md` — archive block alt-post-type + button slot context.
- History (do not restore): commits `174fa25`, `0bc097f`, `2dee50a`.
