---
status: pending
priority: p3
issue_id: "010"
tags: [code-review, performance, queries, database]
dependencies: [005]
---

# Query Optimizations: Named Meta Clauses, Normalize Hidden Flag, wpdb Delete

## Problem Statement

Three lower-priority query improvements that improve scalability at higher event counts:

1. **Sort order via separate meta JOIN**: `orderby: meta_value` with a separate top-level `meta_key` creates an extra JOIN to `wp_postmeta`. Named clauses eliminate the extra join.
2. **NOT EXISTS meta query**: The `_carkeek_event_hidden` exclusion uses `OR (NOT EXISTS / != '1')`. When events don't have the meta row, the NOT EXISTS leg requires a correlated subquery (expensive at scale). Normalizing the field (always write `'0'` on publish) eliminates the NOT EXISTS branch.
3. **Location/organizer deletion**: `clear_stale_location_organizer_ids` loads all events via `get_posts` to delete a single meta value. A direct `wpdb->delete` is O(1) at the DB level.

## Findings

### Named meta clause for sort (block.php:205–207, query.php:76–78)
```php
'meta_key' => '_carkeek_event_start', // separate top-level key = extra JOIN
'orderby'  => 'meta_value',
```

### NOT EXISTS exclusion (3 places — see todo #009)
The `NOT EXISTS` leg forces MySQL to verify the absence of a row per post. At 10k+ events, this is measurably slower than a simple `!= '1'` comparison.

### wpdb delete (post-types.php:190–220)
```php
$events = get_posts( array( 'posts_per_page' => -1, ... ) ); // loads all events
foreach ( $events as $event ) {
    delete_post_meta( $event->ID, '_carkeek_event_location_id', $post_id );
}
```

## Proposed Solutions

### Fix 1: Named meta_query clause for sort
```php
'meta_query' => array(
    'relation'      => 'AND',
    'start_clause'  => array(
        'key'     => '_carkeek_event_start',
        'compare' => 'EXISTS',
    ),
    // ... other clauses
),
'orderby' => array( 'start_clause' => 'ASC' ),
// ← no separate meta_key; uses same JOIN already in query
```

### Fix 2: Normalize `_carkeek_event_hidden` default

In `save_event_meta()`, always write the meta:
```php
update_post_meta( $post_id, '_carkeek_event_hidden', '0' ); // ensure row exists
```

Then all exclusion clauses can drop the `NOT EXISTS` branch:
```php
array( 'key' => '_carkeek_event_hidden', 'value' => '1', 'compare' => '!=' ),
```

### Fix 3: Direct wpdb delete for stale meta cleanup
```php
global $wpdb;
$wpdb->delete(
    $wpdb->postmeta,
    array( 'meta_key' => '_carkeek_event_location_id', 'meta_value' => $post_id ),
    array( '%s', '%d' )
);
```

- **Effort:** Medium (Fix 1, 2); Small (Fix 3)
- **Risk:** Low — same behavior, better SQL plans

## Acceptance Criteria

- [ ] Event archive queries use named meta_query clauses for sort
- [ ] Hidden-event exclusion drops `NOT EXISTS` branch (requires normalization)
- [ ] Location/organizer deletion uses direct `wpdb->delete`

## Work Log

- 2026-03-07: Identified by performance-oracle (OPT-4, OPT-5, OPT-7)
