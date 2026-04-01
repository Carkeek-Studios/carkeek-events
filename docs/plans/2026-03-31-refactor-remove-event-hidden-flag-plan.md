---
title: "refactor: Remove _carkeek_event_hidden meta field and auto-hide system"
type: refactor
status: completed
date: 2026-03-31
---

# refactor: Remove `_carkeek_event_hidden` Meta Field and Auto-Hide System

## Overview

Remove all code related to `_carkeek_event_hidden` and its companion `_carkeek_event_manually_restored`. This includes the block editor sidebar toggle, the daily cron pass that marks expired events as hidden, the query filters that exclude hidden events from archives, and the settings field that controlled auto-hide timing. The feature is causing unexpected visibility problems with published events and does not follow the WordPress way of managing post visibility. It will be revisited later as a proper implementation.

## Problem Statement

The `_carkeek_event_hidden` system was a custom visibility flag that operated _outside_ of WordPress post statuses. It caused the following problems:

- Published events are not appearing in the archive, with no visible indication to editors why
- The cron can auto-hide events that editors cannot easily un-hide, creating a confusing support loop
- The dual-write pattern (entity store + direct REST call) in the sidebar plugin has known race-condition risks
- The system adds meta_query overhead to every front-end `carkeek_event` query

Removing it restores predictable WordPress behaviour: a published event is always visible in the archive; an editor hides an event by setting it to Draft or Private.

## Scope

### Remove entirely

| Location | What goes |
|---|---|
| `src/event-editor/index.js` | Entire file — the sidebar plugin only managed the hidden toggle |
| `webpack.config.js` | Entire file — the only reason it existed was to add the `event-editor/index` explicit entry; default `@wordpress/scripts` auto-discovery is sufficient once this entry is gone |
| `includes/class-carkeekevents-meta.php` | `_carkeek_event_hidden` and `_carkeek_event_manually_restored` `register_meta()` calls |
| `includes/class-carkeekevents-cron.php` | `pass_hide_expired()` method; `run()` simplified to only call `pass_expire_old()` |
| `includes/class-carkeekevents-settings.php` | `expiry_behavior` settings field registration, `expiry_behavior_callback()` method, and `expiry_behavior` sanitization — the setting only controlled Pass 1 timing |
| `includes/class-carkeekevents-block.php` | `enqueue_event_editor()` method and its `add_action( 'enqueue_block_editor_assets', ... )` hook line |

### Modify (remove hidden-specific code, keep the rest)

| File | What stays | What goes |
|---|---|---|
| `includes/class-carkeekevents-query.php` | `inject_event_meta_query()` (default sort + `carkeek_events_query_args` filter), `handle_admin_sort()`, `exclude_hidden_events()` (default sort logic only) | `hidden_exclusion_clause()` method; hidden exclusion `meta_query` injection from `exclude_hidden_events()` and `inject_event_meta_query()` |
| `includes/class-carkeekevents-meta-boxes.php` | Everything else | Comment block at ~line 369–371 explaining `_carkeek_event_hidden` is managed by the block editor |
| `README.md` | Everything else | `_carkeek_event_hidden` + `_carkeek_event_manually_restored` meta reference rows, `carkeek_events_before_hide` hook entry, Event Visibility States table, cron Pass 1 description |
| `todos/011-pending-p3-architecture-settings-helper-meta-comment.md` | — | Delete — the stale docblock comment it tracked is removed with the meta registration |

### Build output cleanup

After removing `src/event-editor/index.js` and `webpack.config.js`, run `npm run build`. The `build/event-editor/` directory will no longer be emitted. Remove the stale directory manually if it persists:

```bash
rm -rf build/event-editor/
```

### Data note (no migration required)

Removing the code does **not** delete existing `_carkeek_event_hidden` or `_carkeek_event_manually_restored` meta rows from the database. Events that were previously hidden by the system will immediately reappear in the archive on next query — this is the intended outcome. The orphaned meta rows are inert once the query filter is gone. An optional cleanup query can be run via WP-CLI if desired:

```bash
wp post meta delete --all --meta_key=_carkeek_event_hidden
wp post meta delete --all --meta_key=_carkeek_event_manually_restored
```

## Technical Approach

### `includes/class-carkeekevents-query.php`

The `exclude_hidden_events()` pre_get_posts callback currently does two things: inject the hidden exclusion clause AND apply the default chronological sort. The hidden exclusion is removed; the default sort stays.

```php
// Before — two responsibilities
public function exclude_hidden_events( $query ) {
    if ( is_admin() ) { return; }
    if ( ! $query->is_main_query() ) { return; }
    if ( 'carkeek_event' !== $query->get( 'post_type' ) ) { return; }

    $meta_query   = $query->get( 'meta_query' ) ?: array();
    $meta_query[] = self::hidden_exclusion_clause();   // ← REMOVE
    $query->set( 'meta_query', $meta_query );          // ← REMOVE

    if ( ! $query->get( 'orderby' ) ) {
        $query->set( 'orderby', 'meta_value' );
        $query->set( 'meta_key', '_carkeek_event_start' );
        $query->set( 'order', 'ASC' );
    }
}
```

Rename the method to `apply_default_sort()` for clarity (it no longer excludes anything).

Similarly `inject_event_meta_query()` has the hidden exclusion lines removed; the `carkeek_events_query_args` filter call and default sort remain.

### `includes/class-carkeekevents-cron.php`

`pass_hide_expired()` is removed in full. `run()` becomes:

```php
public function run() {
    $this->pass_expire_old();
}
```

The class docblock is updated to remove the Pass 1 description. The `carkeek_events_expiry_threshold` and `carkeek_events_cron_batch_size` filter references in Pass 1 are gone; `carkeek_events_cron_batch_size` is still used by Pass 2 so it stays in README.

### `webpack.config.js`

Deleting the file is sufficient. `@wordpress/scripts` will use its default configuration, which sets `entry: getWebpackEntryPoints` (a function reference webpack calls at build time). This auto-discovers all `block.json` files under `src/` — the five existing blocks (`events-archive`, `event-date-time`, `event-location`, `event-organizer`, `event-details`) are all covered.

If a custom webpack config is ever needed again in the future, recreate the file.

### `includes/class-carkeekevents-settings.php`

Remove only the `expiry_behavior` field. `content_expiry_days` is still read by `pass_expire_old()` and must stay. The "Event Expiry" settings section (`carkeek_events_expiry_section`) should stay (renamed or left) because it now only contains the `content_expiry_days` field. Update the section description from "hide expired events" language to "content expiry / trash" language.

## Acceptance Criteria

- [x] `npm run build` completes without errors; no `event-editor/` output in `build/`
- [ ] All published `carkeek_event` posts appear in the archive block and front-end queries regardless of any `_carkeek_event_hidden` value in the database
- [ ] Block editor on a `carkeek_event` post has no "Event Visibility" sidebar panel
- [ ] Plugin settings page no longer shows "Expiry Behavior" dropdown
- [ ] Cron daily pass still trashes events older than `content_expiry_days` (Pass 2 untouched)
- [ ] No PHP errors or warnings on event archive pages or single event pages
- [ ] No JavaScript console errors in the block editor when editing a `carkeek_event`
- [x] Git Updater headers in `carkeek-events.php` are preserved (no accidental edits)

## Dependencies & Risks

**Risk: Pass 2 cron relies on `content_expiry_days` which lives in the same "Event Expiry" settings section.** Ensure `expiry_behavior` field/callback/sanitization is removed without accidentally removing `content_expiry_days`.

**Risk: `carkeek_events_cron_batch_size` filter.** Pass 1 used this filter; Pass 2 also uses it. The filter stays and its README documentation entry should remain.

**Risk: carkeek-blocks integration.** The `inject_event_meta_query()` method is hooked on `carkeek_block_custom_post_layout__query_args`. That filter and hook stay — only the hidden exclusion lines inside the method are removed.

**Not in scope:** Designing a WordPress-native replacement for the event visibility feature. That is deferred to a separate plan.

## Sources & References

- `includes/class-carkeekevents-query.php:50` — `hidden_exclusion_clause()` to remove
- `includes/class-carkeekevents-cron.php:63` — `pass_hide_expired()` to remove
- `src/event-editor/index.js:1` — entire sidebar plugin file
- `includes/class-carkeekevents-block.php:53` — `enqueue_event_editor` hook line
- `includes/class-carkeekevents-meta.php:128` — `_carkeek_event_hidden` registration
- `includes/class-carkeekevents-meta.php:140` — `_carkeek_event_manually_restored` registration
- `includes/class-carkeekevents-settings.php:62` — `expiry_behavior` field registration
- `docs/solutions/runtime-errors/wordpress-block-editor-meta-revert-custom-fields-support.md` — documents the dual-write pattern being removed
- `todos/011-pending-p3-architecture-settings-helper-meta-comment.md` — becomes moot; delete
