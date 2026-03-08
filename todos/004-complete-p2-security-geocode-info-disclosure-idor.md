---
status: pending
priority: p2
issue_id: "004"
tags: [code-review, security, geocoding, information-disclosure]
dependencies: []
---

# Geocode Handler: Raw Error Messages Leaked + Unvalidated post_id (IDOR Risk)

## Problem Statement

Two related security issues in the geocoding AJAX handler:

1. **Information disclosure**: Raw `WP_Error` messages and Google API `error_message` strings are returned directly to the browser. These can contain internal server paths, proxy hostnames, or network topology details.

2. **IDOR / add-on risk**: The `post_id` from `$_POST` is not validated as belonging to a `carkeek_location` post before the `carkeek_events_after_geocode` action is fired with it. An authenticated user can trigger this hook against any post ID.

## Findings

**File:** `includes/class-carkeekevents-geocode.php`

Information disclosure (lines 89–93):
```php
wp_send_json_error( array(
    'code'    => 'request_failed',
    'message' => $response->get_error_message(), // ← raw internal error
) );
```

Also lines 131, 138:
```php
'message' => $body['error_message'] ?? __( 'Google Maps request denied.', 'carkeek-events' ),
// ↑ Google's error_message can reveal key restrictions, network config
```

IDOR (lines 145+):
```php
$post_id = absint( $_POST['post_id'] ?? 0 );
do_action( 'carkeek_events_after_geocode', $post_id, $lat, $lng );
// ↑ post_id not verified as a carkeek_location post
```

## Proposed Solutions

### Fix 1: Generic error messages + server-side logging

```php
if ( is_wp_error( $response ) ) {
    error_log( 'Carkeek Events geocoding error: ' . $response->get_error_message() );
    wp_send_json_error( array(
        'code'    => 'request_failed',
        'message' => __( 'Geocoding request failed. Please try again.', 'carkeek-events' ),
    ) );
}
```

Apply same pattern to `$body['error_message']` pass-through.

### Fix 2: Validate post_id before firing action

```php
$post_id = absint( $_POST['post_id'] ?? 0 );
if ( $post_id ) {
    $post = get_post( $post_id );
    if ( ! $post || 'carkeek_location' !== $post->post_type ) {
        wp_send_json_error( array( 'code' => 'invalid_post' ) );
        return;
    }
}
```

- **Effort:** Small
- **Risk:** Very low (no behavior change for valid requests)

## Acceptance Criteria

- [ ] No raw `WP_Error` messages returned to the browser
- [ ] No Google API `error_message` strings returned to the browser
- [ ] Errors are logged server-side with `error_log()`
- [ ] `post_id` is verified as a `carkeek_location` post before action fires

## Work Log

- 2026-03-07: Identified by security-sentinel (Findings 3, 4)
