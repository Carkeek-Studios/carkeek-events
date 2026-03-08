---
status: pending
priority: p2
issue_id: "006"
tags: [code-review, performance, cron]
dependencies: [003]
---

# Cron: Redundant Per-Post Meta Lookups + Unbounded Batch Size

## Problem Statement

Both cron passes have performance issues that compound as event count grows:

1. `pass_hide_expired` re-fetches `_carkeek_event_end` via `get_post_meta` inside the loop for every post, even though the WP_Query already selected posts based on that same meta value. With 200 expired events, this is 200 unnecessary DB queries.

2. Both passes use `posts_per_page: -1` with no batch processing. For sites accumulating thousands of events, this loads all IDs in one shot and, for `pass_expire_old`, calls `wp_trash_post()` per post (which triggers multiple queries/hooks per call) synchronously in a single cron run.

## Findings

**File:** `includes/class-carkeekevents-cron.php`

Redundant meta lookup (lines 119–130):
```php
// WP_Query already filtered to end < $threshold.
// get_post_meta below re-reads what the query already used:
$end_iso = get_post_meta( $post_id, '_carkeek_event_end', true );
$threshold = apply_filters( 'carkeek_events_expiry_threshold', $threshold, $post_id );
if ( $end_iso >= $threshold ) { continue; }
```

Unbounded query (line 82):
```php
'posts_per_page' => -1, // loads ALL matching IDs at once
```

`wp_trash_post` in loop (line 179):
```php
foreach ( $query->posts as $post_id ) {
    wp_trash_post( $post_id ); // multiple queries + hook fire per post
}
```

## Proposed Solutions

### Fix 1: Guard per-post meta fetch behind `has_filter()`

```php
foreach ( $query->posts as $post_id ) {
    do_action( 'carkeek_events_before_hide', $post_id );

    if ( has_filter( 'carkeek_events_expiry_threshold' ) ) {
        $end_iso             = get_post_meta( $post_id, '_carkeek_event_end', true );
        $effective_threshold = apply_filters( 'carkeek_events_expiry_threshold', $threshold, $post_id );
        if ( $end_iso >= $effective_threshold ) {
            continue;
        }
    }

    update_post_meta( $post_id, '_carkeek_event_hidden', '1' );
}
```

When no add-on uses the filter, zero extra DB queries per post.

### Fix 2: Add a configurable batch size cap

```php
$batch_size = apply_filters( 'carkeek_events_cron_batch_size', 200 );
// Use in both pass queries:
'posts_per_page' => $batch_size,
```

For `pass_expire_old`, this prevents runaway memory use and timeout on large sites.

- **Effort:** Small
- **Risk:** Low — only changes behavior for sites with >200 events to expire at once (uncommon edge case)

## Acceptance Criteria

- [ ] `pass_hide_expired` does not call `get_post_meta` per-post when no filter is registered
- [ ] Both cron passes have a configurable batch size cap (default 200)
- [ ] The `carkeek_events_cron_batch_size` filter is documented

## Work Log

- 2026-03-07: Identified by performance-oracle (CRIT-2, CRIT-3)
