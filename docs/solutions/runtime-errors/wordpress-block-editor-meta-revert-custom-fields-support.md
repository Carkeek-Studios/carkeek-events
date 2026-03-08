---
title: "WordPress Block Editor Meta Fields Revert on Save"
slug: wordpress-block-editor-meta-revert-custom-fields-support
date: 2026-03-07
category: runtime-errors
subcategory: wordpress-block-editor
tags: [wordpress, gutenberg, block-editor, meta-fields, custom-post-type, rest-api]
symptoms:
  - "Block editor sidebar panel toggle reverts to previous state after saving"
  - "Meta field changes not persisted when saving via REST API"
  - "getEditedPostAttribute('meta') returns null or empty object"
  - "Classic meta box and block editor sidebar managing same field"
components:
  - "Custom Post Type registration (register_post_type)"
  - "register_meta() / REST API meta exposure"
  - "Block editor sidebar plugin (registerPlugin)"
  - "PluginDocumentSettingPanel"
investigation_steps_tried: 4
root_cause: "Custom post type missing 'custom-fields' in supports array prevents block editor meta access via REST API"
resolution_time: "~1 hour (4 failed attempts before root cause found)"
severity: high
---

# WordPress Block Editor Meta Fields Revert on Save

## Symptoms

A `PluginDocumentSettingPanel` sidebar toggle (or any meta field managed by the block editor) reverts to its previous state every time the post is saved. The change appears to take effect, but after save + reload the old value is restored.

Specifically:
- Toggle checked → save → reload → toggle is unchecked again
- `getEditedPostAttribute('meta')` returns `null` or `{}` in the sidebar plugin
- REST API test (`GET /wp/v2/{post_type}/{id}`) shows `meta: {}` instead of the registered field

## Root Cause

**Primary:** The custom post type is missing `'custom-fields'` in its `supports` array.

```php
// BROKEN — meta not exposed to block editor
register_post_type( 'carkeek_event', array(
    'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
    // missing 'custom-fields' !
) );
```

Without `'custom-fields'`, WordPress does not include registered meta in the REST API response for that post type. The block editor entity store (`core/editor`) has no `meta` key to work with, so `getEditedPostAttribute('meta')` returns `null` or `{}`.

**Secondary (compound issue):** A classic PHP meta box save handler was also managing the same field. The block editor submits the classic meta box form **after** the REST API save, using stale `$_POST` values (whatever was in the form before the toggle was changed), overwriting the REST API update.

## Investigation Steps (What Did NOT Work)

1. **Check `$_GET['meta-box-loader']` in save handler** — skips save during certain block editor loads, but does not prevent the post-save meta box form submission.
2. **Check `$_POST['meta-box-loader-nonce']`** — same problem; the relevant submission is a normal POST, not a loader request.
3. **Remove the field from meta box HTML and save handler** — partially correct, eliminates the double-save race condition. But without `'custom-fields'` in `supports`, the block editor still cannot read/write the value via REST.
4. **Root cause discovered:** Adding `'custom-fields'` to the `supports` array resolved everything.

## Solution

### Step 1 — Add `'custom-fields'` to the post type `supports` array

```php
// includes/class-carkeekevents-post-types.php

register_post_type( 'carkeek_event', array(
    // ...
    'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
    // ↑ 'custom-fields' is REQUIRED for block editor meta access
) );
```

This is the **critical fix**. Without it, all other steps are irrelevant.

### Step 2 — Register the meta field with `show_in_rest: true`

```php
// includes/class-carkeekevents-meta.php

register_meta( 'post', '_carkeek_event_hidden', array(
    'object_subtype' => 'carkeek_event',
    'type'           => 'string',
    'single'         => true,
    'default'        => '0',
    'show_in_rest'   => true,
    'auth_callback'  => function() {
        return current_user_can( 'edit_posts' );
    },
) );
```

Note: `show_in_rest: true` alone is NOT sufficient — the post type must also support `'custom-fields'`.

### Step 3 — Remove the field from any classic meta box save handler

If the field is managed via the block editor sidebar, remove it completely from the classic PHP meta box:

```php
// class-carkeekevents-meta-boxes.php

// REMOVED from HTML:
// <input type="checkbox" name="_carkeek_event_hidden" value="1" ...>

// REMOVED from save_event_meta():
// if ( isset( $_POST['_carkeek_event_hidden'] ) ) {
//     update_post_meta( $post_id, '_carkeek_event_hidden', '1' );
// } else {
//     update_post_meta( $post_id, '_carkeek_event_hidden', '0' );
// }

// Note: _carkeek_event_hidden is managed exclusively by the block editor
// sidebar plugin via REST API. Do not save it here.
```

### Step 4 — Implement the block editor sidebar plugin with dual-save pattern

```js
// src/event-editor/index.js
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { ToggleControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const EventHidePanel = () => {
    const { postType, postId, isHidden } = useSelect( ( select ) => {
        const editor = select( 'core/editor' );
        const meta   = editor.getEditedPostAttribute( 'meta' );
        return {
            postType: editor.getCurrentPostType(),
            postId:   editor.getCurrentPostId(),
            isHidden: meta?._carkeek_event_hidden === '1',
        };
    }, [] );

    const { editPost } = useDispatch( 'core/editor' );

    if ( postType !== 'carkeek_event' ) {
        return null;
    }

    const handleChange = async ( value ) => {
        const metaValue = value ? '1' : '0';

        // Update entity store immediately so the main Save button stays in sync
        // and subsequent saves include the new value.
        editPost( { meta: { _carkeek_event_hidden: metaValue } } );

        // Also persist directly via REST API — this is the reliable write
        // path that does not depend on entity-store timing.
        try {
            await apiFetch( {
                path:   `/wp/v2/carkeek_event/${ postId }`,
                method: 'POST',
                data:   { meta: { _carkeek_event_hidden: metaValue } },
            } );
        } catch {
            // Revert entity store on failure so UI stays consistent.
            editPost( { meta: { _carkeek_event_hidden: value ? '0' : '1' } } );
        }
    };

    return (
        <PluginDocumentSettingPanel
            name="carkeek-event-visibility"
            title={ __( 'Event Visibility', 'carkeek-events' ) }
        >
            <ToggleControl
                label={ __( 'Hide from Events Archive', 'carkeek-events' ) }
                help={ __(
                    'When enabled, this event will not appear in the archive block.',
                    'carkeek-events'
                ) }
                checked={ isHidden }
                onChange={ handleChange }
                __nextHasNoMarginBottom
            />
        </PluginDocumentSettingPanel>
    );
};

registerPlugin( 'carkeek-event-visibility', { render: EventHidePanel } );
```

**Why dual-save?** `editPost()` marks the post as dirty in the entity store so the main Save button will include the meta on next save. The direct `apiFetch` call saves immediately without waiting for the user to click Save — useful when you want instant persistence (e.g., visibility toggles that should take effect right away).

### Step 5 — Register the script with correct dependencies

Use the asset file generated by `@wordpress/scripts`:

```php
$asset_file = include plugin_dir_path( __FILE__ ) . '../build/event-editor/index.asset.php';
wp_enqueue_script(
    'carkeek-event-editor',
    plugins_url( '../build/event-editor/index.js', __FILE__ ),
    $asset_file['dependencies'],
    $asset_file['version'],
    true
);
```

The generated `index.asset.php` will automatically include: `react`, `wp-api-fetch`, `wp-components`, `wp-data`, `wp-editor`, `wp-i18n`, `wp-plugins`.

### Step 6 — Configure webpack for multiple entries (if using `@wordpress/scripts`)

```js
// webpack.config.js
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
module.exports = {
    ...defaultConfig,
    entry: {
        'events-archive/index': './src/events-archive/index.js',
        'events-archive/view':  './src/events-archive/view.js',
        'event-editor/index':   './src/event-editor/index.js',
    },
};
```

## Why It Works

WordPress's REST API only exposes meta fields when:
1. The meta is registered with `register_meta()` + `show_in_rest: true`
2. **AND** the post type declares `'custom-fields'` in `supports`

The `supports` array acts as a gate. Even if `register_meta` is configured perfectly, WordPress checks whether the post type "supports" custom fields before including meta in the REST response. The block editor reads post data exclusively via the REST API — it never reads `$_POST` or uses the classic meta box machinery. So if the REST response has no `meta` object, the block editor entity store simply has no meta to display or save.

The secondary issue — the classic meta box overwrite — is a separate race condition. WordPress's block editor save flow is:
1. REST API PATCH/POST → saves post content + meta
2. Classic meta box form submit → fires a separate PHP `save_post` action

Step 2 uses stale `$_POST` values from the current page state (before any JS toggle). If the classic handler saves the field, it silently overwrites the REST save.

## Prevention Checklist

For any new meta field managed by the block editor sidebar:

- [ ] Post type has `'custom-fields'` in `supports` array
- [ ] `register_meta()` called with `show_in_rest: true` and `object_subtype`
- [ ] Field is NOT present in any classic meta box HTML
- [ ] Field is NOT saved in any `save_post` / classic meta box save handler
- [ ] REST API verified: `GET /wp/v2/{post_type}/{id}` returns `"meta": { "your_field": "value" }`

## Debugging

**Verify REST meta exposure:**
```bash
curl -u admin:password https://example.com/wp-json/wp/v2/carkeek_event/123
# Look for "meta": { "_carkeek_event_hidden": "0" } in the response
# If "meta" key is absent or empty — check the supports array
```

**Inspect entity store in browser console:**
```js
// In browser console on the block editor page:
wp.data.select('core/editor').getEditedPostAttribute('meta')
// Should return: { _carkeek_event_hidden: "0" }
// If returns null or {} — the REST API is not returning meta
```

**Temporary debug logging in sidebar plugin:**
```js
const meta = editor.getEditedPostAttribute( 'meta' );
console.log( 'meta from entity store:', meta );
// Add to useSelect callback temporarily; remove before shipping
```

## Related Files

- `includes/class-carkeekevents-post-types.php` — CPT registration (`supports` array)
- `includes/class-carkeekevents-meta.php` — `register_meta()` calls
- `includes/class-carkeekevents-meta-boxes.php` — Classic meta box (field removed from here)
- `src/event-editor/index.js` — Block editor sidebar plugin
- `includes/class-carkeekevents-block.php` — `enqueue_event_editor()` script registration
