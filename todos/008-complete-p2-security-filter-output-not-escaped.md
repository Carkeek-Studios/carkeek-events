---
status: pending
priority: p2
issue_id: "008"
tags: [code-review, security, xss, filters]
dependencies: []
---

# Filter Output for `before_slots`/`after_slots` Not Wrapped in `wp_kses_post`

## Problem Statement

`render_single_card()` in the block renderer applies two filters (`carkeek_events_block_before_slots` and `carkeek_events_block_after_slots`) and inserts their return values directly into the HTML string without sanitization. If a third-party add-on hooks these filters and returns unescaped HTML, it becomes a stored XSS vector that the plugin itself introduces by trusting filter output unconditionally.

## Findings

**File:** `includes/class-carkeekevents-block.php` lines ~277, ~290

```php
$before_slots = apply_filters( 'carkeek_events_block_before_slots', '', $post_id, $attributes );
if ( $before_slots ) {
    $html .= '<div class="carkeek-event-card__before-slots">' . $before_slots . '</div>';
    // ↑ no wp_kses_post() — trusts filter output completely
}

$after_slots = apply_filters( 'carkeek_events_block_after_slots', '', $post_id, $attributes );
if ( $after_slots ) {
    $html .= '<div class="carkeek-event-card__after-slots">' . $after_slots . '</div>';
    // ↑ same issue
}
```

The comment nearby says "already escaped in each render_* method" — true for the built-in slot renderers, but not for external filter return values.

## Proposed Solution

```php
$before_slots = apply_filters( 'carkeek_events_block_before_slots', '', $post_id, $attributes );
if ( $before_slots ) {
    $html .= '<div class="carkeek-event-card__before-slots">' . wp_kses_post( $before_slots ) . '</div>';
}

$after_slots = apply_filters( 'carkeek_events_block_after_slots', '', $post_id, $attributes );
if ( $after_slots ) {
    $html .= '<div class="carkeek-event-card__after-slots">' . wp_kses_post( $after_slots ) . '</div>';
}
```

`wp_kses_post` allows safe HTML (headings, paragraphs, links, spans) while stripping scripts and event attributes. Add-on developers can still return rich HTML; they just can't introduce XSS.

- **Effort:** Tiny (add `wp_kses_post()` wrapper in 2 places)
- **Risk:** Very low — no behavior change for well-written add-ons

## Acceptance Criteria

- [ ] Both `$before_slots` and `$after_slots` filter outputs are wrapped in `wp_kses_post()` before string concatenation
- [ ] A filter hook returning `<script>alert(1)</script>` does not render in the browser

## Work Log

- 2026-03-07: Identified by security-sentinel (Finding 7)
