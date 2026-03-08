---
title: "feat: Alternative Post Type Support + Button Link Slot"
type: feat
status: completed
date: 2026-03-07
---

# feat: Alternative Post Type Support + Button Link Slot

## Overview

Three related improvements to the Carkeek Events Archive block:

1. **Alternative Post Type** — Allow each block instance to display any CPT that stores start/end datetimes in ISO 8601 meta fields. Configured per block via inspector controls (no plugin settings page changes).
2. **Button Link Slot** — Add a new `button_link` slot that renders an `<a class="arrow-link">` permalink anchor with a configurable label.
3. **Rename "Slot 1" label** — Change the Content panel's first slot label from `Slot 1` to `Slots`.

---

## Problem Statement / Motivation

The events archive block is tightly coupled to `carkeek_event` and its specific meta keys (`_carkeek_event_start`, `_carkeek_event_end`). Sites sometimes have other CPTs that store datetime meta in the same ISO 8601 format and want identical archive display without building a second block. Each block instance should be independently configurable — one block shows `carkeek_event`, another shows an alternative CPT, all on the same page.

The button link slot fills a common UX need: a visually distinct "More info →" link per card without requiring the title slot.

---

## Proposed Solution

### 1. Alternative Post Type (per block)

**New block attributes (`src/events-archive/block.json`):**

```json
"useAltPostType":   { "type": "boolean", "default": false },
"altPostType":      { "type": "string",  "default": "" },
"altStartMetaKey":  { "type": "string",  "default": "" },
"altEndMetaKey":    { "type": "string",  "default": "" },
"altTaxonomy":      { "type": "string",  "default": "" }
```

**Inspector UI (`src/events-archive/inspector.js`):**

All configuration lives in the existing Events panel. Use the WordPress `core` data store — it already exposes all REST-enabled post types and taxonomies.

```js
// Load all public post types (requires show_in_rest: true on the CPT)
const postTypes = useSelect( ( select ) => {
    return select( 'core' ).getPostTypes( { per_page: -1 } );
}, [] );

// Load taxonomies registered to the selected alt post type
const altTaxonomies = useSelect( ( select ) => {
    if ( ! useAltPostType || ! altPostType ) return [];
    const allTaxonomies = select( 'core' ).getTaxonomies( { per_page: -1 } ) || [];
    return allTaxonomies.filter( ( tax ) => tax.types.includes( altPostType ) );
}, [ useAltPostType, altPostType ] );
```

Controls added to the Events panel when `useAltPostType` is toggled on:

```jsx
<ToggleControl
    label={ __( 'Use Alternative Post Type', 'carkeek-events' ) }
    help={ __( 'Display a different post type instead of Events.', 'carkeek-events' ) }
    checked={ useAltPostType }
    onChange={ ( value ) => setAttributes( {
        useAltPostType: value,
        // Reset alt fields when toggling off to keep attributes clean.
        ...( ! value && { altPostType: '', altStartMetaKey: '', altEndMetaKey: '', altTaxonomy: '' } ),
    } ) }
    __nextHasNoMarginBottom
/>

{ useAltPostType && (
    <>
        <SelectControl
            label={ __( 'Post Type', 'carkeek-events' ) }
            value={ altPostType }
            options={ [
                { label: __( '— select —', 'carkeek-events' ), value: '' },
                ...( postTypes || [] )
                    .filter( ( pt ) => pt.slug !== 'attachment' )
                    .map( ( pt ) => ( { label: pt.name, value: pt.slug } ) ),
            ] }
            onChange={ ( value ) => setAttributes( {
                altPostType: value,
                altTaxonomy: '', // reset taxonomy when CPT changes
            } ) }
            __nextHasNoMarginBottom
        />
        <TextControl
            label={ __( 'Start Date Meta Key', 'carkeek-events' ) }
            help={ __( 'Meta key storing start datetime in ISO 8601 format, e.g. _my_start_date', 'carkeek-events' ) }
            value={ altStartMetaKey }
            placeholder="_event_start"
            onChange={ ( value ) => setAttributes( { altStartMetaKey: value } ) }
            __nextHasNoMarginBottom
        />
        <TextControl
            label={ __( 'End Date Meta Key', 'carkeek-events' ) }
            help={ __( 'Optional. Leave blank if the post type has no end date.', 'carkeek-events' ) }
            value={ altEndMetaKey }
            placeholder="_event_end"
            onChange={ ( value ) => setAttributes( { altEndMetaKey: value } ) }
            __nextHasNoMarginBottom
        />
        { altPostType && altTaxonomies.length > 0 && (
            <SelectControl
                label={ __( 'Taxonomy (for category filter)', 'carkeek-events' ) }
                value={ altTaxonomy }
                options={ [
                    { label: __( '— none —', 'carkeek-events' ), value: '' },
                    ...altTaxonomies.map( ( tax ) => ( { label: tax.name, value: tax.slug } ) ),
                ] }
                onChange={ ( value ) => setAttributes( { altTaxonomy: value } ) }
                __nextHasNoMarginBottom
            />
        ) }
    </>
) }
```

> **Note:** `core.getPostTypes()` and `core.getTaxonomies()` only return items that have `show_in_rest: true`. The target CPT and its taxonomy must have REST support enabled for them to appear in the dropdowns. Document this in the inspector help text.

**Category filter panel** — when `useAltPostType` is true, use `altTaxonomy` as the taxonomy slug. When `altTaxonomy` is empty, hide the category filter panel entirely (no taxonomy to filter on).

```js
// inspector.js — taxonomy slug for category panel
const taxonomySlug = ( useAltPostType && altTaxonomy )
    ? altTaxonomy
    : 'carkeek_event_category';

const showCategoryPanel = useAltPostType ? !! altTaxonomy : true;

const categories = useSelect( ( select ) => {
    if ( ! showCategoryPanel ) return null;
    return select( 'core' ).getEntityRecords(
        'taxonomy', taxonomySlug, { per_page: -1, orderby: 'name', order: 'asc' }
    );
}, [ taxonomySlug, showCategoryPanel ] );
```

**PHP render (`includes/class-carkeekevents-block.php`):**

Extract a private helper to resolve the active post type config from attributes:

```php
// class-carkeekevents-block.php
private function get_post_type_config( $attributes ) {
    $use_alt = ! empty( $attributes['useAltPostType'] );

    if ( $use_alt ) {
        $post_type  = sanitize_key( $attributes['altPostType'] ?? '' );
        $start_meta = sanitize_key( $attributes['altStartMetaKey'] ?? '' );
        $end_meta   = sanitize_key( $attributes['altEndMetaKey'] ?? '' );
        $taxonomy   = sanitize_key( $attributes['altTaxonomy'] ?? '' );

        // Guard: if post type is invalid or meta key missing, bail to carkeek_event.
        if ( ! $post_type || ! $start_meta || ! post_type_exists( $post_type ) ) {
            $use_alt = false;
        }
    }

    if ( ! $use_alt ) {
        return array(
            'post_type'  => 'carkeek_event',
            'start_meta' => '_carkeek_event_start',
            'end_meta'   => '_carkeek_event_end',
            'taxonomy'   => 'carkeek_event_category',
            'is_alt'     => false,
        );
    }

    return array(
        'post_type'  => $post_type,
        'start_meta' => $start_meta,
        'end_meta'   => $end_meta,
        'taxonomy'   => $taxonomy,
        'is_alt'     => true,
    );
}
```

`build_query_args()` uses `get_post_type_config()`:
- Sets `post_type`, `meta_key` (for orderby), and `tax_query` taxonomy from config.
- Skips `CarkeekEvents_Query::hidden_exclusion_clause()` when `is_alt` is true.
- Uses config `start_meta`/`end_meta` for past/upcoming date meta_query clauses.

`render_date_slot()` uses `get_post_type_config()` to read start/end meta keys instead of hardcoded values.

`prime_linked_posts()` — skips (returns early) when `is_alt` is true, as location/organizer are carkeek-specific.

No changes required to `class-carkeekevents-settings.php`.

---

### 2. Button Link Slot

**New block attribute (`block.json`):**
```json
"buttonLinkLabel": { "type": "string", "default": "" }
```

**Inspector (`inspector.js`):**
- Add to `SLOT_OPTIONS`: `{ value: 'button_link', label: __( 'Button Link', 'carkeek-events' ) }`
- Detect `button_link` in slots:
```js
const hasButtonLinkSlot = slots.includes( 'button_link' );
```
- When true, show a `TextControl` for the label inside the Content panel (same pattern as `hasExcerptSlot`):
```jsx
{ hasButtonLinkSlot && (
    <>
        <hr style={ { margin: '12px 0' } } />
        <TextControl
            label={ __( 'Button Label', 'carkeek-events' ) }
            help={ __( 'Text for the arrow link. Defaults to the post title if blank.', 'carkeek-events' ) }
            value={ buttonLinkLabel }
            placeholder={ __( 'More Info', 'carkeek-events' ) }
            onChange={ ( value ) => setAttributes( { buttonLinkLabel: value } ) }
            __nextHasNoMarginBottom
        />
    </>
) }
```

**PHP render (`render_slot()`):**
```php
case 'button_link':
    $label = ! empty( $attributes['buttonLinkLabel'] )
        ? esc_html( $attributes['buttonLinkLabel'] )
        : esc_html( get_the_title( $post_id ) );
    return '<a class="arrow-link" href="' . esc_url( $permalink ) . '">' . $label . '</a>';
```

---

### 3. Rename "Slot 1" Label to "Slots"

**Inspector (`inspector.js` ~line 304):**

Change:
```js
label={ `${ __( 'Slot', 'carkeek-events' ) } ${ index + 1 }` }
```
To:
```js
label={ __( 'Slots', 'carkeek-events' ) }
```
`hideLabelFromVision={ index > 0 }` already hides the label visually after the first row, so only "Slots" appears once above the dropdown list.

---

## Technical Considerations

- **`get_post_type_config()` reuse:** Both `build_query_args()` and `render_date_slot()` need the resolved meta keys. Calling `get_post_type_config( $attributes )` in each is fine since it's pure (no DB calls). Alternatively store the result on `$this` per request.
- **Client-submitted attributes security:** `ajax_load_more()` receives `attributes` from the browser as JSON. The `altPostType`, `altStartMetaKey`, etc. are included. `get_post_type_config()` guards against invalid post types with `post_type_exists()` and `sanitize_key()` on meta key values — meta key injection is prevented since the value goes through WP's meta query, not raw SQL.
- **`filterByCategory` with alt taxonomy:** The existing `catTermsSelected` and `tax_query` logic already uses the `$taxonomy` variable resolved from config. Term IDs stored in `catTermsSelected` are specific to a taxonomy; if the taxonomy changes, stale term IDs should be cleared. Add: reset `catTermsSelected` when `altTaxonomy` changes in the inspector.
- **Missing `show_in_rest`:** If the target CPT or taxonomy doesn't have `show_in_rest: true`, it won't appear in the dropdowns. Add a note in the inspector when `postTypes` is empty or null (loading state vs. empty).

---

## System-Wide Impact

- **`CarkeekEvents_Query::exclude_hidden_events()`** — checks `post_type === carkeek_event` on `pre_get_posts`. No impact on alt CPT queries.
- **`CarkeekEvents_Query::inject_event_meta_query()`** — same check. No impact.
- **Load More AJAX** — `useAltPostType`, `altPostType`, `altStartMetaKey`, `altEndMetaKey`, `altTaxonomy` are all standard boolean/string block attributes serialized in `data-attributes`. They pass through `build_query_args()` → `get_post_type_config()` on the server. Safe.
- **Cron** — operates only on `carkeek_event`. No impact.
- **No settings page changes** — `class-carkeekevents-settings.php` is unchanged.

---

## Acceptance Criteria

- [x] `useAltPostType`, `altPostType`, `altStartMetaKey`, `altEndMetaKey`, `altTaxonomy` attributes added to `block.json`
- [x] Inspector Events panel shows "Use Alternative Post Type" toggle
- [x] When toggled on: post type `<select>` appears (all REST-enabled post types, excluding `attachment`)
- [x] Selecting a post type populates the taxonomy `<select>` with only taxonomies registered to that CPT
- [x] When post type changes, taxonomy resets and re-filters
- [x] Start Date Meta Key and End Date Meta Key text inputs appear when alt mode is on
- [x] Category filter panel uses `altTaxonomy` when in alt mode; hidden when no taxonomy selected
- [x] Block renders alt CPT posts sorted by `altStartMetaKey`
- [x] Past/upcoming date filters use `altStartMetaKey`/`altEndMetaKey`
- [x] Hidden event exclusion clause NOT applied for alt CPTs
- [x] `prime_linked_posts()` skipped for alt CPTs
- [x] `get_post_type_config()` falls back to `carkeek_event` if post type is invalid or missing
- [x] `button_link` appears in Slots dropdown
- [x] When `button_link` is selected, Button Label text field appears in Content panel
- [x] `button_link` slot renders `<a class="arrow-link" href="...">` with configured label (falls back to post title)
- [x] First slot label reads "Slots" instead of "Slot 1"
- [x] `npm run build` succeeds with no errors

---

## Dependencies & Risks

- **`show_in_rest` requirement:** CPTs and taxonomies must have REST support to appear in dropdowns. The block cannot display post types registered with `show_in_rest: false`. Add inline help text in the inspector.
- **Term ID stale state:** If a user changes `altTaxonomy`, existing `catTermsSelected` term IDs become invalid (they belong to the old taxonomy). Reset `catTermsSelected` to `''` whenever `altTaxonomy` changes.
- **Meta key format:** The alt CPT's start/end meta must be ISO 8601 (`Y-m-d\TH:i:s`) for CHAR sorting and date slot rendering to work. Document with a help text note in the inspector text field.

---

## Sources & References

- Block attributes: `src/events-archive/block.json`
- Inspector: `src/events-archive/inspector.js:34–99` (attribute destructuring, category select)
- Query builder: `includes/class-carkeekevents-block.php:197` (`build_query_args`)
- Date slot: `includes/class-carkeekevents-block.php:461` (`render_date_slot`)
- Slot renderer: `includes/class-carkeekevents-block.php:423` (`render_slot`)
- Hidden exclusion: `includes/class-carkeekevents-query.php` (`hidden_exclusion_clause`)
- WP data: `select('core').getPostTypes()`, `select('core').getTaxonomies()`
