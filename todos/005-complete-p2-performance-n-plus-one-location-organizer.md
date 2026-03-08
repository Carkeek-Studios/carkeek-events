---
status: pending
priority: p2
issue_id: "005"
tags: [code-review, performance, queries, n-plus-one]
dependencies: []
---

# N+1 Queries: Location and Organizer Posts Fetched Per Card

## Problem Statement

Every rendered event card calls `get_post()` for the linked location and organizer post. With N events on the page, this produces up to 2N extra database queries on every page render and every AJAX load-more response. On a page showing 12 events with distinct venues, that's 24 extra queries before factoring in featured image lookups.

## Findings

**Files:**
- `includes/class-carkeekevents-block.php` lines 557–590 (`render_location_name`, `render_organizer_name`)
- `includes/class-carkeekevents-display.php` lines 138–175, 292–334 (`get_location_html`, `get_organizer_html`)
- `templates/event-card/default.php` lines 26–28

Each card calls:
```php
$loc = get_post( $loc_id );          // DB query per card
$org = get_post( $organizer_id );    // DB query per card
```

**Scale projections:**
- 12 events/page: up to 24 extra queries
- 50 events/page (`numberOfPosts: 50`): up to 100 extra queries
- Repeated on every AJAX load-more click

## Proposed Solution

Collect all distinct location and organizer IDs after the main `WP_Query` runs, then prime the WordPress object cache with a single `get_posts` call per type. Subsequent `get_post( $id )` calls within the render loop hit the in-memory cache.

```php
// In render() and ajax_load_more(), after WP_Query, before the render loop:
private function prime_linked_posts( array $post_ids ): void {
    $loc_ids = array_filter( array_unique( array_map( function( $id ) {
        return (int) get_post_meta( $id, '_carkeek_event_location_id', true );
    }, $post_ids ) ) );

    $org_ids = array_filter( array_unique( array_map( function( $id ) {
        return (int) get_post_meta( $id, '_carkeek_event_organizer_id', true );
    }, $post_ids ) ) );

    if ( $loc_ids ) {
        get_posts( array(
            'post__in'       => $loc_ids,
            'post_type'      => 'carkeek_location',
            'posts_per_page' => count( $loc_ids ),
            'no_found_rows'  => true,
        ) );
    }
    if ( $org_ids ) {
        get_posts( array(
            'post__in'       => $org_ids,
            'post_type'      => 'carkeek_organizer',
            'posts_per_page' => count( $org_ids ),
            'no_found_rows'  => true,
        ) );
    }
}
```

Call `$this->prime_linked_posts( $query->posts )` (passing IDs array) before the `foreach` render loop in both `render()` and `ajax_load_more()`.

- **Effort:** Small
- **Risk:** Very low — purely additive; existing `get_post()` calls are unchanged, they just hit cache
- **Impact:** Reduces queries from O(N) to O(1) for location/organizer fetches

## Acceptance Criteria

- [ ] A page with 12 events and distinct venues performs 2 location/organizer DB queries, not 24
- [ ] AJAX load-more response queries are similarly reduced
- [ ] No change in rendered HTML output

## Work Log

- 2026-03-07: Identified by performance-oracle (CRIT-1)
