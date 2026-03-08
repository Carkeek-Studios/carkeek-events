---
status: pending
priority: p3
issue_id: "011"
tags: [code-review, architecture, settings, documentation]
dependencies: []
---

# Architecture: Settings Helper Method + Stale Meta Comment

## Problem Statement

Two small architecture improvements:

1. `get_option( CARKEEKEVENTS_OPTION_NAME, array() )` is called in 14+ places throughout the plugin. Default values are defined inline at each call site (e.g. `$settings['expiry_behavior'] ?? 'end_of_day'` appears in both cron.php and settings.php). A single `CarkeekEvents_Settings::get()` helper would centralize defaults.

2. The docblock comment for `_carkeek_event_hidden` in `class-carkeekevents-meta.php:124` says *"Set manually via meta box checkbox"* — but the meta box checkbox was removed. The field is now exclusively managed via the block editor sidebar plugin.

## Findings

**Settings scattered defaults** (`includes/class-carkeekevents-cron.php:64`, `includes/class-carkeekevents-settings.php:194`, etc.):
```php
$settings = get_option( CARKEEKEVENTS_OPTION_NAME, array() );
$behavior = $settings['expiry_behavior'] ?? 'end_of_day'; // default defined here...
// ...and again in settings.php:224 with the same default
```

**Stale comment** (`includes/class-carkeekevents-meta.php:124`):
```php
// Set manually via meta box checkbox, or automatically by cron...
// ↑ meta box checkbox was removed; field is now REST-only
```

## Proposed Solutions

### Fix 1: Add `CarkeekEvents_Settings::get()` static helper

```php
// In class-carkeekevents-settings.php:
private static array $cache = [];
private static array $defaults = [
    'expiry_behavior'      => 'end_of_day',
    'content_expiry_days'  => 365,
    'date_format'          => 'M j, Y',
    'time_format'          => 'g:i a',
    // ... all other settings
];

public static function get( string $key = null, $fallback = null ) {
    if ( empty( self::$cache ) ) {
        self::$cache = (array) get_option( CARKEEKEVENTS_OPTION_NAME, [] );
    }
    $merged = array_merge( self::$defaults, self::$cache );
    return $key === null ? $merged : ( $merged[ $key ] ?? $fallback );
}
```

All call sites become `CarkeekEvents_Settings::get( 'expiry_behavior' )`.

### Fix 2: Update stale docblock comment

```php
// _carkeek_event_hidden:
// Set by the block editor sidebar plugin via the REST API,
// or automatically by cron when event end date passes.
```

- **Effort:** Small (Fix 1 medium, Fix 2 trivial)
- **Risk:** Very low

## Acceptance Criteria

- [ ] `CarkeekEvents_Settings::get( $key )` exists and returns merged settings with defaults
- [ ] All call sites using `get_option( CARKEEKEVENTS_OPTION_NAME )` updated to use helper
- [ ] Docblock for `_carkeek_event_hidden` in class-carkeekevents-meta.php is accurate

## Work Log

- 2026-03-07: Identified by architecture-strategist (Findings 3.7, 3.9)
