---
status: pending
priority: p1
issue_id: "002"
tags: [code-review, security, performance, ajax]
dependencies: []
---

# AJAX Load-More: No Server-Side Cap on `numberOfPosts` — DoS Vector

## Problem Statement

The `ajax_load_more` handler accepts a full JSON blob from `$_POST['attributes']` submitted by unauthenticated visitors. The `numberOfPosts` attribute is taken from this payload and used directly in `WP_Query` with no maximum cap. An attacker who possesses a valid nonce (publicly available on any page with the block) can request tens of thousands of posts per click, causing high memory consumption, slow DB queries, and potential server exhaustion.

## Findings

**File:** `includes/class-carkeekevents-block.php` lines 307–351

```php
$attributes = json_decode( wp_unslash( $_POST['attributes'] ), true );
// ...
$per_page = isset( $attributes['numberOfPosts'] ) ? (int) $attributes['numberOfPosts'] : 6;
// ← no maximum enforced; attacker can pass numberOfPosts: 99999
```

The nonce is publicly rendered on the page, making it accessible to any visitor who views source. The handler is registered on both `wp_ajax_` and `wp_ajax_nopriv_`, confirming unauthenticated access.

Additionally, `sortOrder` is passed verbatim to `WP_Query`'s `order` param without whitelisting to `ASC`/`DESC`.

## Proposed Solutions

### Option A: Server-side cap + sortOrder whitelist (Recommended)

```php
// Cap numberOfPosts
$per_page = isset( $attributes['numberOfPosts'] ) ? (int) $attributes['numberOfPosts'] : 6;
if ( $per_page < 1 || $per_page > 100 ) {
    $per_page = 6; // reset to safe default, or use min/max
}

// Whitelist sortOrder
$sort_order = strtoupper( $attributes['sortOrder'] ?? 'ASC' );
if ( ! in_array( $sort_order, array( 'ASC', 'DESC' ), true ) ) {
    $sort_order = 'ASC';
}
```

- **Pros:** Closes DoS vector; validates sortOrder; simple; doesn't break normal usage (block editor max is 50)
- **Cons:** None
- **Effort:** Small
- **Risk:** Very low

### Option B: Allowlist entire attributes array

Strip all attribute keys not in a known allowlist before passing to `build_query_args`. More thorough but higher effort.

```php
$allowed_keys = array( 'numberOfPosts', 'sortOrder', 'includePastEvents', 'onlyPastEvents',
                       'filterByCategory', 'catTermsSelected', 'catFilterMode',
                       'contentSlots', 'slotDateFormat', 'slotTimeFormat', 'showEndDateTime',
                       'displayFeaturedImage', 'excerptLength' );
$attributes = array_intersect_key( $attributes, array_flip( $allowed_keys ) );
```

## Recommended Action

Option A as immediate fix. Option B as follow-up hardening.

## Technical Details

**Affected files:**
- `includes/class-carkeekevents-block.php` — `ajax_load_more()` method, `build_query_args()` method

## Acceptance Criteria

- [ ] `numberOfPosts` is capped at 100 (or a configurable limit) in `ajax_load_more`
- [ ] `sortOrder` is validated as `ASC` or `DESC` before passing to `WP_Query`
- [ ] An invalid `numberOfPosts` value resets to the default (6) rather than silently accepting an absurd value

## Work Log

- 2026-03-07: Identified by security-sentinel review agent (Finding 2, Finding 1)
