---
status: pending
priority: p2
issue_id: "007"
tags: [code-review, architecture, cron, timezone]
dependencies: []
---

# Cron `pass_expire_old` Uses `date()` Instead of `wp_date()` — Timezone Drift

## Problem Statement

`pass_expire_old` uses PHP's native `date()` function to format the cutoff date. This formats using the server's PHP timezone, not the WordPress site timezone setting. On servers where the PHP timezone differs from the WordPress timezone (common in managed hosting), this can produce a cutoff that is off by hours — meaning events are trashed a day early or a day late.

`pass_hide_expired` in the same file correctly uses `current_time()` which respects WordPress timezone. The inconsistency within the same file is the issue.

## Findings

**File:** `includes/class-carkeekevents-cron.php` line 148

```php
// WRONG — uses PHP server timezone:
$cutoff = date( 'Y-m-d\T00:00:00', strtotime( "-{$expiry_days} days", current_time( 'timestamp' ) ) );

// vs pass_hide_expired which correctly uses:
$threshold = current_time( 'Y-m-d' ) . 'T00:00:00'; // respects WP timezone
```

## Proposed Solution

```php
// Use wp_date() which respects the WordPress timezone setting:
$cutoff = wp_date( 'Y-m-d\T00:00:00', strtotime( "-{$expiry_days} days" ) );
```

`wp_date()` was introduced in WordPress 5.3. Since the plugin requires WP 6.4+, this is safe.

- **Effort:** Tiny (one-line change)
- **Risk:** Very low — only affects sites where server PHP timezone != WP timezone

## Acceptance Criteria

- [ ] `pass_expire_old` uses `wp_date()` for the cutoff calculation
- [ ] The cutoff date matches the WordPress timezone setting, not the PHP server timezone

## Work Log

- 2026-03-07: Identified by architecture-strategist (Finding 3.11)
