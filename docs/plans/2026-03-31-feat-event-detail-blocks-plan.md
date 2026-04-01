---
title: Add Event Detail Blocks and Remove URL Meta Fields
type: feat
status: active
date: 2026-03-31
---

# feat: Add Event Detail Blocks and Remove URL Meta Fields

## Enhancement Summary

**Deepened on:** 2026-03-31
**Research agents used:** best-practices-researcher, framework-docs-researcher, security-sentinel, performance-oracle, architecture-strategist, code-simplicity-reviewer, julik-frontend-races-reviewer, pattern-recognition-specialist + project learning: `docs/solutions/runtime-errors/wordpress-block-editor-meta-revert-custom-fields-support.md`

### Key Corrections from Research

1. **Critical — PHP post ID resolution**: `$block->context['postId']` is NOT populated for standalone blocks outside a Query Loop. SSR preview passes `postId` via `urlQueryArgs` → `$_GET`. The render callback must check `$_GET['postId']` for REST preview mode, then fall back to `get_the_ID()` for frontend, with `$block->context` only populated in Query Loop contexts.
2. **Category wrong**: `"category": "theme"` is reserved for theme-registered blocks. Plugin blocks must use `"widgets"` (or a custom category).
3. **`"postTypes"` in block.json**: Valid in WordPress 6.0+ per the official schema. Include it plus a PHP `allowed_block_types_all` filter as belt-and-suspenders.
4. **Security**: Validate post status (`publish`) in every render callback. Wrap filter output in `wp_kses_post()`.
5. **JS null-guard**: Guard `context.postId` before mounting `ServerSideRender` to prevent a `postId=0` REST call on initial paint.
6. **Performance**: Call `get_post_meta($post_id)` (no key) at the top of each render to prime the object cache in one query. Without this, multiple blocks on one page each trigger individual meta queries.
7. **Empty-state UX**: Use `EmptyResponsePlaceholder` on `ServerSideRender` so the block is visible/selectable in the editor when the event has no data set.
8. **`get_block_wrapper_attributes()`**: Must be used in render.php files (existing archive block pattern).
9. **`"html": false`** should be in supports — the HTML edit mode is meaningless for dynamic blocks.
10. **`"render": "file:./render.php"`** in block.json is the correct modern approach — `register_block_type()` picks it up automatically, no PHP `render_callback` needed.
11. **webpack auto-discovery**: New block entry points are auto-discovered from `block.json` files in `src/`. No explicit entries needed for the new blocks. Only `event-editor/index` remains explicit (it has no `block.json`).

---

## Overview

Add four dynamic Gutenberg blocks to the `carkeek-events` plugin that expose individual event data fields for use on single event page layouts. Remove the Event Website and Button Label meta box fields from the classic editor, directing editors to use the core Button block instead.

## Problem Statement / Motivation

Currently, event data (date/time, location, organizer) is only surfaced through:
1. The `carkeek-events/archive` listing block (for archive pages)
2. The hard-coded `templates/single-carkeek_event.php` template

Page editors have no way to arrange event fields flexibly on a single event page using the block editor. Adding individual dynamic blocks enables full block-editor layout control for single event pages.

The Website/Registration URL and Button Label fields are being replaced with the core Button block, which is more flexible and already familiar to editors.

## Proposed Solution

### New Blocks (all dynamic / server-side rendered)

| Block Name | Title | Renders |
|---|---|---|
| `carkeek-events/event-date-time` | Event Date & Time | Start/end date and time via `CarkeekEvents_Display::get_date_range_html()` |
| `carkeek-events/event-location` | Event Location | Location name/address via `CarkeekEvents_Display::get_event_location_html()` |
| `carkeek-events/event-organizer` | Event Organizer | Organizer name/info via `CarkeekEvents_Display::get_event_organizer_html()` |
| `carkeek-events/event-details` | Event Details | All three combined in a single wrapper |

Each block:
- Declares `"usesContext": ["postId", "postType"]` in `block.json` (needed for Query Loop contexts)
- Declares `"postTypes": ["carkeek_event"]` in `block.json` (valid since WP 6.0) to restrict the inserter
- Has `"html": false` in `supports` — the HTML edit mode is meaningless for dynamic blocks
- Returns empty string when rendered outside a `carkeek_event` post (PHP fallback safety)
- Uses the existing `CarkeekEvents_Display` static methods — no new display logic needed
- Has `keywords: ["event", "carkeek"]` plus a descriptive title, so all four appear when searching "event"
- Category: `"widgets"` — the correct category for plugin-registered blocks (`"theme"` is reserved for theme-registered blocks)

### Fields to Remove from Meta Box

Remove from `CarkeekEvents_Meta_Boxes::render_event_meta_box()`:
- `carkeek_event_website` URL input and its label/row (~lines 178–195)
- `carkeek_event_button_label` text input and its label/row

Also remove the dead save code from `save_event_meta()` in the same file: the `isset( $_POST['carkeek_event_website'] )` and `isset( $_POST['carkeek_event_button_label'] )` blocks are dead code once the inputs are removed. Removing them also eliminates the race condition described in `docs/solutions/runtime-errors/wordpress-block-editor-meta-revert-custom-fields-support.md` where a classic save handler can overwrite a REST API save.

> **Keep** the `_carkeek_event_website` and `_carkeek_event_button_label` meta registrations in `class-carkeekevents-meta.php`. The archive block's `button_link` content slot still reads `_carkeek_event_website`. Removing the meta box UI means the field can no longer be set from the single event editor — existing values persist and the archive slot continues working. Add an inline comment at the registration site noting that UI was intentionally removed and the field is retained for REST API access and template compatibility.

## Technical Considerations

### File Structure

```
src/
  event-date-time/
    block.json     — block metadata ("render": "file:./render.php")
    index.js       — registerBlockType (edit: ServerSideRender)
    render.php     — server-side render callback
  event-location/
    block.json
    index.js
    render.php
  event-organizer/
    block.json
    index.js
    render.php
  event-details/
    block.json
    index.js
    render.php
includes/
  class-carkeekevents-event-blocks.php   — only needed for bootstrap guard + inserter filter
```

### webpack.config.js Changes

`@wordpress/scripts` auto-discovers block entry points from `block.json` files in `src/` via `getWebpackEntryPoints()`. The new blocks do **not** need explicit entries — placing `block.json` files with `"editorScript": "file:./index.js"` in each `src/` directory is sufficient.

The only entry that must remain explicit is `event-editor/index` (a `registerPlugin` sidebar script with no `block.json`):

```js
// webpack.config.js — simplified
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
    ...defaultConfig,
    entry: {
        ...defaultConfig.entry, // auto-discovered from all block.json files in src/
        'event-editor/index': './src/event-editor/index.js', // no block.json — must be explicit
    },
};
```

> The existing explicit entries for `events-archive/index` and `events-archive/view` can also be removed — they will be auto-discovered via the `editorScript` and `viewScript` fields in `src/events-archive/block.json`. This is a cleanup opportunity for the existing config, separate from this feature.

`@wordpress/scripts`' `CopyWebpackPlugin` copies `block.json` files and also handles `render.php` files referenced in block.json's `"render"` field — they will appear in `build/` after running `npm run build`.

### block.json Pattern

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "carkeek-events/event-date-time",
  "title": "Event Date & Time",
  "category": "widgets",
  "description": "Displays the date and time for a Carkeek Event.",
  "keywords": ["event", "date", "time", "carkeek"],
  "textdomain": "carkeek-events",
  "usesContext": ["postId", "postType"],
  "postTypes": ["carkeek_event"],
  "supports": {
    "html": false
  },
  "editorScript": "file:./index.js",
  "render": "file:./render.php"
}
```

Notes:
- `"render": "file:./render.php"` — WordPress 6.1+ resolves this file relative to the block's `build/` directory and uses it as the render callback automatically. No PHP `render_callback` argument needed in `register_block_type()`.
- `"postTypes"` is a valid official `block.json` key since WordPress 6.0 — restricts inserter visibility to the listed CPTs. Does not unregister the block globally.
- `"html": false` disables the HTML block edit mode, which is meaningless for server-rendered blocks.
- Optional: register a custom `"carkeek"` block category using `block_categories_all` filter to group all plugin blocks together in the inserter.

### PHP Registration Class (simplified)

Because `"render": "file:./render.php"` in each `block.json` handles the render callback automatically, the PHP class only needs to register the blocks and add the inserter restriction filter. No render methods needed.

```php
// includes/class-carkeekevents-event-blocks.php

class CarkeekEvents_Event_Blocks {

    public function init() {
        add_action( 'init', array( $this, 'register_blocks' ) );
        add_filter( 'allowed_block_types_all', array( $this, 'restrict_block_inserter' ), 10, 2 );
    }

    public function register_blocks() {
        if ( ! file_exists( CARKEEKEVENTS_PLUGIN_DIR . 'build/event-date-time/index.asset.php' ) ) {
            return;
        }
        register_block_type( CARKEEKEVENTS_PLUGIN_DIR . 'build/event-date-time/' );
        register_block_type( CARKEEKEVENTS_PLUGIN_DIR . 'build/event-location/' );
        register_block_type( CARKEEKEVENTS_PLUGIN_DIR . 'build/event-organizer/' );
        register_block_type( CARKEEKEVENTS_PLUGIN_DIR . 'build/event-details/' );
    }

    /**
     * Belt-and-suspenders inserter restriction for hosts running older WP
     * versions that may not honour "postTypes" in block.json.
     */
    public function restrict_block_inserter( $allowed_blocks, $editor_context ) {
        if ( empty( $editor_context->post->post_type ) ) {
            return $allowed_blocks;
        }
        if ( 'carkeek_event' !== $editor_context->post->post_type ) {
            $event_blocks = [
                'carkeek-events/event-date-time',
                'carkeek-events/event-location',
                'carkeek-events/event-organizer',
                'carkeek-events/event-details',
            ];
            if ( is_array( $allowed_blocks ) ) {
                return array_values( array_diff( $allowed_blocks, $event_blocks ) );
            }
        }
        return $allowed_blocks;
    }
}
```

### render.php Pattern (per block)

The render logic moves into individual `render.php` files. These are standalone PHP files included by WordPress directly — no class context, but plugin constants and all WordPress functions are available.

```php
<?php
// src/event-date-time/render.php
// $attributes, $content, $block are provided by WordPress.

defined( 'ABSPATH' ) || exit;

/**
 * Resolve the event post ID.
 *
 * Priority order:
 * 1. $_GET['postId'] — SSR editor preview via ServerSideRender urlQueryArgs
 * 2. $block->context['postId'] — when nested in a core/query loop
 * 3. get_the_ID() — frontend single event page (most common production path)
 *
 * Note: $block->context['postId'] is only populated inside a core/query loop.
 * For standalone single-post pages, get_the_ID() is the reliable path.
 */
if ( defined( 'REST_REQUEST' ) && REST_REQUEST && ! empty( $_GET['postId'] ) ) {
    $post_id = absint( $_GET['postId'] );
} elseif ( ! empty( $block->context['postId'] ) ) {
    $post_id = (int) $block->context['postId'];
} else {
    $post_id = (int) get_the_ID();
}

if ( ! $post_id ) {
    return;
}

$post = get_post( $post_id );
if ( ! $post || 'carkeek_event' !== $post->post_type || 'publish' !== $post->post_status ) {
    return;
}

// Prime the object cache — loads all meta in one query.
// Subsequent get_post_meta() calls in the Display methods hit cache, not DB.
get_post_meta( $post_id );

$html = CarkeekEvents_Display::get_date_range_html( $post_id );
if ( ! $html ) {
    return;
}

$wrapper_attrs = get_block_wrapper_attributes();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo '<div ' . $wrapper_attrs . '>' . wp_kses_post( $html ) . '</div>';
```

The `render.php` for `event-location` and `event-organizer` follow the same pattern, calling their respective Display methods and adding `prime_meta_cache` logic (reading the linked location/organizer IDs and calling `get_post_meta()` on them before the Display method runs).

The `render.php` for `event-details` calls all three Display methods in sequence after priming both linked CPT meta caches, wrapping output in a single `<div>` with the block wrapper attributes plus a `carkeek-event-details` class.

### JS Edit Component Pattern

> **Important:** `context.postId` may be `undefined` on initial paint (editor store not yet hydrated). Guard before mounting `ServerSideRender` to prevent a `postId=0` REST call. `ServerSideRender` also accepts `EmptyResponsePlaceholder` — use it so the block is visible in the editor when the event has no data set yet.

```js
// src/event-date-time/index.js
import { registerBlockType } from '@wordpress/blocks';
import ServerSideRender from '@wordpress/server-side-render';
import { Placeholder, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

registerBlockType( metadata, {
    edit( { context } ) {
        const { postId } = context;

        // Guard: don't fire a REST call with postId=0 on initial paint.
        if ( ! postId ) {
            return (
                <div className="components-placeholder">
                    <Spinner />
                </div>
            );
        }

        return (
            <ServerSideRender
                block="carkeek-events/event-date-time"
                attributes={ {} }
                urlQueryArgs={ { postId } }
                EmptyResponsePlaceholder={ () => (
                    <Placeholder
                        label={ __( 'Event Date & Time', 'carkeek-events' ) }
                        instructions={ __( 'No date set for this event.', 'carkeek-events' ) }
                    />
                ) }
            />
        );
    },
    save: () => null,
} );
```

> `urlQueryArgs: { postId }` passes the current post ID as a URL query parameter to the REST block-renderer endpoint. The PHP render callback reads it from `$_GET['postId']` in REST_REQUEST mode (see `get_post_id()` above). On the front end, `get_the_ID()` is used — `urlQueryArgs` has no effect outside the editor.
>
> The four `index.js` files are structurally identical except for the block name and placeholder label. They can be simplified further if desired.

### Hooking into carkeek-events.php

In the main plugin's `includes()` method, add the new class alongside the existing block class (following the same `file_exists` guard pattern used at line 141–143):

```php
// carkeek-events.php — inside includes()
if ( file_exists( CARKEEKEVENTS_PLUGIN_DIR . 'build/event-date-time/index.asset.php' ) ) {
    require_once CARKEEKEVENTS_PLUGIN_DIR . 'includes/class-carkeekevents-event-blocks.php';
    $this->event_blocks = new CarkeekEvents_Event_Blocks();
    $this->event_blocks->init();
}
```

The sentinel file `build/event-date-time/index.asset.php` is representative — if the build ran, all four will be present. `register_block_type()` pointing at a directory reads `block.json`, auto-registers the `editorScript` handle from `index.asset.php`, and uses the `render` field to locate `render.php`. No additional PHP wiring needed.

## System-Wide Impact

### Performance

Without the `prime_meta_cache()` call, placing all four blocks on one event page makes ~32 database queries (including duplicate reads for location and organizer meta). With priming, this drops to ~6 queries total. The `prime_meta_cache()` call is idempotent — WordPress's in-process object cache deduplicates the queries, so calling it from multiple blocks on the same request is free after the first call.

For further improvement, consider adding `wp_cache_get/set` to `CarkeekEvents_Display::get_event_location_html()` and `get_event_organizer_html()` — location and organizer data changes infrequently and is shared across many events.

### Security

The render callbacks validate:
- Post ID is non-zero and castable to int
- Post exists
- Post type is `carkeek_event`
- Post status is `publish`

This prevents the SSR endpoint from leaking draft/pending event data to authenticated users who can call the block-renderer REST endpoint. `wp_kses_post()` wraps all Display method output to sanitize any HTML injected through developer filters (`carkeek_events_date_range`, `carkeek_events_location_display`, `carkeek_events_organizer_display`).

### Classic Template Coexistence

`templates/single-carkeek_event.php` renders date, location, organizer, and event link **outside** `the_content()`. If a site uses this classic PHP template AND editors place the new blocks inside post content, the same data renders twice. The template is unchanged by this feature. Resolve the double-render when transitioning to a block theme by either removing the header meta from the template or deprecating the template entirely.

`templates/event-card/default.php` and the archive block's `button_link` slot remain unaffected.

### Archive Block

`carkeek-events/archive` is untouched. Its `button_link` content slot continues reading `_carkeek_event_website` from post meta — existing events with stored URLs still show the CTA button in archive listings.

### Known Pre-existing Issue

`CarkeekEvents_Block` contains private date formatting methods (`format_date_only`, `format_time_only`, `format_date_time`) that duplicate logic in `CarkeekEvents_Display::format_date_range`. The new `event-date-time` block calls `get_date_range_html()` via the Display class (correct), but this duplication means two code paths exist for date formatting. Defer this refactor to a separate PR.

## Acceptance Criteria

- [ ] Four new blocks registered and appearing in the block inserter under category "widgets" on `carkeek_event` edit screens
- [ ] Blocks do NOT appear in the inserter when editing standard posts or pages
- [ ] Searching "event" in the block inserter on a `carkeek_event` edit screen surfaces all four new blocks
- [ ] Each block shows a PHP-rendered preview (`ServerSideRender`) in the block editor
- [ ] When an event has no date/location/organizer set, each block shows a placeholder in the editor (not an invisible void)
- [ ] Each block renders the correct data on the front end of a published event page
- [ ] Blocks on non-event posts render empty with no PHP errors or warnings
- [ ] Draft/pending events do not render via the REST block-renderer endpoint
- [x] Event Website URL and Button Label fields are removed from the Event Details meta box
- [x] Dead save handlers for those fields are removed from `save_event_meta()`
- [ ] Existing events with stored `_carkeek_event_website` values still show the CTA in the archive block's `button_link` slot
- [x] `npm run build` completes without errors
- [x] Git Updater headers in `carkeek-events.php` are preserved
- [x] PHP render callbacks produce no PHPCS warnings (use `phpcs:ignore` comments where appropriate for `get_block_wrapper_attributes()` and `wp_kses_post()` output)

## Open Questions

1. **Classic template**: Does the Skagit Land Trust site use a block theme or `single-carkeek_event.php`? If classic, double-render will occur when editors add the new blocks. Decide before deployment: update the template, or document the coexistence as acceptable until a block theme migration.
2. **`event-date-time` format overrides**: `get_date_range_html()` uses global plugin settings. Per-block date/time format is not supported in the initial implementation (differs from the archive block's per-slot overrides). Acceptable for now; add format attributes in a future iteration if needed.
3. **`_carkeek_event_website` data**: Editors can no longer see or update existing URLs from the event editor. Accept silent data preservation, or surface it as a read-only note in the block editor sidebar? Recommend accepting for now and revisiting if editors report confusion.

## Dependencies & Risks

- **Build step required before blocks appear.** The `index.asset.php` guard in PHP prevents fatal errors when build output is absent.
- **`@wordpress/server-side-render` already in `package.json`** — no new npm packages needed.
- **`@wordpress/components` needed for `Placeholder` and `Spinner`** in the JS empty-state placeholder — verify it is in the dependency graph from `@wordpress/scripts` (it is, via `@wordpress/block-editor` transitive dependency).
- **`"postTypes"` in `block.json`**: Valid in WordPress 6.0+. Also added PHP `allowed_block_types_all` filter as a fallback for older versions.
- **`prime_meta_cache()` correctness**: `absint` is used on `$_GET['postId']` (not `(int)`) to match the pattern used throughout `class-carkeekevents-admin.php`. Verify this is consistent.

## Sources & References

### Internal
- Existing block registration pattern: `includes/class-carkeekevents-block.php:86–93`
- `get_block_wrapper_attributes()` usage: `includes/class-carkeekevents-block.php:136`
- Display methods: `includes/class-carkeekevents-display.php:41–360`
- Meta box fields to remove: `includes/class-carkeekevents-meta-boxes.php:178–201`
- Meta registration (keep): `includes/class-carkeekevents-meta.php:126–147`
- Current webpack config: `webpack.config.js`
- SSR pattern (existing archive block): `src/events-archive/edit.js`
- Sidebar pattern (useSelect, REST): `src/event-editor/index.js`

### Project Learning
- `docs/solutions/runtime-errors/wordpress-block-editor-meta-revert-custom-fields-support.md` — documents the `custom-fields` supports requirement and the danger of classic meta box save handlers overwriting REST API saves. The dead save handler removal (see above) directly applies this learning.

### Framework
- `@wordpress/server-side-render` source: `node_modules/@wordpress/server-side-render/src/server-side-render.js` — `urlQueryArgs` merges into the REST URL; `EmptyResponsePlaceholder` prop for empty PHP returns; `LoadingResponsePlaceholder` for spinner override.
- `CopyWebpackPlugin` config: `node_modules/@wordpress/scripts/config/webpack.config.js:233–281` — confirms `block.json` is copied automatically from `src/` to `build/`.
- WordPress block.json schema: `https://schemas.wp.org/trunk/block.json` — `"postTypes"` is a valid key since WordPress 6.0.
