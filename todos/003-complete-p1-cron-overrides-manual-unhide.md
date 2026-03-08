---
status: pending
priority: p1
issue_id: "003"
tags: [code-review, architecture, cron, meta-fields]
dependencies: []
---

# Cron Re-hides Events That Editors Manually Restored

## Problem Statement

`_carkeek_event_hidden` is written by two systems: the daily cron (auto-hides expired events) and the block editor sidebar (manual toggle by editors). There is no coordination between them. When an editor manually sets `_carkeek_event_hidden = '0'` to restore a hidden event, the next daily cron run will re-hide it because the event's end date is still in the past.

This creates a frustrating and invisible experience: an editor unhides an event for a legitimate reason (late-running session, re-promoted event), and it silently disappears from the archive again the next morning.

Also related: `$threshold` is mutated inside the cron loop (`cron.php:122`), so the per-post filter result bleeds into subsequent loop iterations — a latent bug.

## Findings

**File:** `includes/class-carkeekevents-cron.php` lines 119–130

```php
foreach ( $query->posts as $post_id ) {
    $end_iso   = get_post_meta( $post_id, '_carkeek_event_end', true );
    $threshold = apply_filters( 'carkeek_events_expiry_threshold', $threshold, $post_id );
    // ↑ mutates $threshold — bleeds to next iteration

    if ( $end_iso >= $threshold ) {
        continue;
    }
    update_post_meta( $post_id, '_carkeek_event_hidden', '1' );
    // ↑ will re-hide events that an editor manually restored
}
```

The WP_Query already filters to `_carkeek_event_hidden != '1'`, so any event set to `'0'` by an editor will be re-hidden on the next cron run if its end date is past.

## Proposed Solutions

### Option A: Add a `_carkeek_event_manually_restored` flag (Recommended — Minimal Change)

Add a separate meta key `_carkeek_event_manually_restored` that the editor sidebar writes when the user explicitly sets hidden to `'0'`. The cron skips any event with this flag set.

```php
// In cron — skip events the editor has manually restored
if ( get_post_meta( $post_id, '_carkeek_event_manually_restored', true ) === '1' ) {
    continue;
}
update_post_meta( $post_id, '_carkeek_event_hidden', '1' );
```

```js
// In event-editor/index.js — set flag when editor unhides
if ( ! value ) { // user is un-hiding
    editPost( { meta: {
        _carkeek_event_hidden: '0',
        _carkeek_event_manually_restored: '1',
    } } );
}
```

- **Pros:** Minimal code change; preserves existing meta key semantics; reversible
- **Cons:** Adds a second meta key; restore flag never automatically clears

### Option B: Separate meta keys for cron vs editor

Use `_carkeek_event_expired` (cron-written) and `_carkeek_event_hidden` (editor-written) as independent flags. The query excludes events where either is set.

- **Pros:** Clean separation of concerns; intent is unambiguous
- **Cons:** Requires query changes in 3 places; migration for existing hidden events

### Option C: Fix $threshold mutation only (Partial)

At minimum, fix the loop variable mutation as a correctness fix regardless of which option is chosen:

```php
foreach ( $query->posts as $post_id ) {
    $end_iso           = get_post_meta( $post_id, '_carkeek_event_end', true );
    $effective_threshold = apply_filters( 'carkeek_events_expiry_threshold', $threshold, $post_id );
    // ↑ use a local variable, don't overwrite $threshold

    if ( $end_iso >= $effective_threshold ) {
        continue;
    }
    update_post_meta( $post_id, '_carkeek_event_hidden', '1' );
}
```

## Recommended Action

Option C immediately (correct the mutation bug). Option A for the manual-restore conflict.

## Technical Details

**Affected files:**
- `includes/class-carkeekevents-cron.php` — `pass_hide_expired()` method
- `src/event-editor/index.js` — `handleChange()` (if Option A)
- `includes/class-carkeekevents-meta.php` — register new meta (if Option A)
- `includes/class-carkeekevents-block.php` — `build_query_args()` (if Option B)

## Acceptance Criteria

- [ ] `$threshold` is not mutated across loop iterations in `pass_hide_expired`
- [ ] An event that an editor manually unhides is not re-hidden by the next cron run
- [ ] An event that the cron auto-hides (expired) still gets hidden on the next run
- [ ] The block editor sidebar toggle still works correctly for manual hide/unhide

## Work Log

- 2026-03-07: Identified by architecture-strategist (Finding 3.4) and code-simplicity-reviewer (Finding 8)
