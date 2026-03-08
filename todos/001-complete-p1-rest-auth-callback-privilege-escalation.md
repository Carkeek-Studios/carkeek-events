---
status: pending
priority: p1
issue_id: "001"
tags: [code-review, security, rest-api, meta-fields]
dependencies: []
---

# REST auth_callback Uses `edit_posts` Instead of `edit_post` — Privilege Escalation

## Problem Statement

`_carkeek_event_hidden` is registered with `show_in_rest: true` and an `auth_callback` that checks `current_user_can( 'edit_posts' )` (plural). This means any Contributor-level user can send a REST API request to hide **any** event — even events they didn't create — from the public archive. `edit_posts` (plural) grants blanket write access; `edit_post` (singular, with a post ID) respects WordPress ownership.

## Findings

**File:** `includes/class-carkeekevents-meta.php` lines 125–131

```php
$auth_callback = function() {
    return current_user_can( 'edit_posts' ); // ← checks generic cap, not ownership
};
```

A Contributor can call:
```
POST /wp/v2/carkeek_event/123
{ "meta": { "_carkeek_event_hidden": "1" } }
```
…to suppress any event, even events belonging to other users or admins.

## Proposed Solutions

### Option A: Use `edit_post` with post ID (Recommended)

```php
register_meta( 'post', '_carkeek_event_hidden', array(
    'object_subtype' => 'carkeek_event',
    'type'           => 'string',
    'single'         => true,
    'default'        => '0',
    'show_in_rest'   => true,
    'auth_callback'  => function( $allowed, $meta_key, $post_id ) {
        return current_user_can( 'edit_post', $post_id );
    },
) );
```

`edit_post` (singular) with `$post_id` checks ownership — a Contributor can only edit their own events. Requires the callback accept 3 parameters so WordPress passes `$post_id`.

- **Pros:** Correct WordPress ownership model; minimal change
- **Cons:** None
- **Effort:** Small
- **Risk:** Low

### Option B: Require `manage_options`

Restrict to admins only. Too restrictive for typical multi-author event sites.

## Recommended Action

Option A — change `edit_posts` to `edit_post` with `$post_id` parameter.

## Technical Details

**Affected files:**
- `includes/class-carkeekevents-meta.php` — `$auth_callback` definition (applies to all 23 registered meta fields using the shared callback)

**Note:** The shared `$auth_callback` is reused for all meta fields. The `$post_id` parameter is passed as the third argument by WordPress. Updating the signature to accept all three arguments and using `edit_post` applies the ownership check consistently to all event, location, and organizer meta fields.

## Acceptance Criteria

- [ ] `auth_callback` for `_carkeek_event_hidden` (and all event meta) uses `current_user_can( 'edit_post', $post_id )`
- [ ] Contributor can edit meta on their own events
- [ ] Contributor cannot edit meta on another user's events via REST API

## Work Log

- 2026-03-07: Identified by security-sentinel review agent
