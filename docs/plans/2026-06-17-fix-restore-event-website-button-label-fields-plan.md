---
title: Restore Event Website and Button Label fields to Event Details meta box
type: fix
status: active
date: 2026-06-17
---

# fix: Restore Event Website and Button Label fields to Event Details meta box

## Overview

The `_carkeek_event_website` and `_carkeek_event_button_label` post meta fields are still registered (`includes/class-carkeekevents-meta.php:110-126`) and still read by the front-end CTA renderer (`includes/class-carkeekevents-display.php:266-267`), but the inputs that let an editor set them were removed from the Event Details meta box. Add the inputs and save handler back so editors can set/update these values from the `carkeek_event` edit screen again.

## Problem Statement / Motivation

Commit `2dee50a` ("adding event blocks, clean up hidden functionality") deliberately removed these two fields from `CarkeekEvents_Meta_Boxes::render_event_meta_box()` and their save logic from `save_event_meta()`, per `docs/plans/2026-03-31-feat-event-detail-blocks-plan.md`. The intent at the time was to direct editors toward the core Button block instead of a dedicated URL/label field.

That removal left a half-finished state: the meta box already pulls both values into local variables (`class-carkeekevents-meta-boxes.php:99-100`) but never renders them, and the values can no longer be set or edited from the UI at all — not via the meta box, not via a block. The original plan's own "Open Questions" section flagged this risk directly: *"Editors can no longer see or update existing URLs from the event editor... revisit if editors report confusion."* That's exactly what's happened — the request now is to bring the fields back.

## Proposed Solution

Restore the exact UI and save logic that existed prior to `2dee50a` (verified via `git show 2dee50a^:includes/class-carkeekevents-meta-boxes.php`):

1. Re-add two field rows to `render_event_meta_box()` in `includes/class-carkeekevents-meta-boxes.php`, placed after the Organizer section's `hr` and `do_action( 'carkeek_events_meta_box_after_organizer', ... )` hook, and before the existing `do_action( 'carkeek_events_meta_box_after_link', ... )` hook:
   - **Event Website / Registration URL** — `type="url"`, id/name `carkeek_event_website`, placeholder `https://`, description "When set, a button linking to this URL will appear in event templates."
   - **Button Label** — `type="text"`, id/name `carkeek_event_button_label`, placeholder "Sign Up", description `Defaults to "Sign Up" if left blank.`
2. Re-add the corresponding save logic to `save_event_meta()` in the same file, after the Organizer save block: sanitize the URL with `esc_url_raw()`, the label with `sanitize_text_field()`, and `delete_post_meta()` when blank (matches the pattern already used for `_carkeek_event_start`/`_carkeek_event_end`).

No changes are needed to `class-carkeekevents-meta.php` (registration is untouched) or `class-carkeekevents-display.php` (CTA rendering already reads these keys correctly).

## Why This Is Safe to Restore

- **No REST/JS writer conflicts with this field.** The race-condition risk documented in `docs/solutions/runtime-errors/wordpress-block-editor-meta-revert-custom-fields-support.md` only applies when a block-editor sidebar plugin *also* writes the same meta key via REST, causing a stale classic `$_POST` save to clobber it. That pattern existed for `_carkeek_event_hidden` (via `src/event-editor/index.js`), but that sidebar plugin and the hidden-flag field were removed entirely in a separate, unrelated refactor (`refactor/remove-event-hidden-flag`). No JS currently writes `_carkeek_event_website` or `_carkeek_event_button_label` via REST — the classic save handler will be the sole writer, exactly as it is today for the Location/Organizer/date fields in the same meta box.
- **No conflict with the new dynamic blocks.** Checked `event-date-time`, `event-location`, `event-organizer`, and `event-details` block source/render files — none reference `_carkeek_event_website` or `_carkeek_event_button_label`. Only `src/events-archive/inspector.js` and the compiled `build/events-archive/index.js` reference the `button_link` content slot, and that slot already reads `_carkeek_event_website` from post meta independent of this meta box — restoring the input doesn't change how the archive block consumes the value.
- **Coexistence with the core Button block is fine.** The original removal pushed editors toward placing a core Button block in the post content instead. Restoring the meta box field doesn't remove that option — both can coexist; this just gives editors the simpler, original path back for the common case (one CTA button per event, used by `get_event_link_html()`).

## Acceptance Criteria

- [x] "Event Website / Registration URL" field appears in the Event Details meta box, pre-filled with any existing `_carkeek_event_website` value
- [x] "Button Label" field appears in the Event Details meta box, pre-filled with any existing `_carkeek_event_button_label` value
- [x] Saving the event with a URL set persists it to `_carkeek_event_website`; clearing the field deletes the meta row
- [x] Saving the event with a label set persists it to `_carkeek_event_button_label`; clearing the field deletes the meta row (front end falls back to "Sign Up")
- [x] Existing events that already had `_carkeek_event_website` values (set before the field was removed) display correctly in the restored field with no data loss (read path was already wired at `class-carkeekevents-meta-boxes.php:99-100`, unchanged)
- [x] CTA button still renders correctly on the front end via `CarkeekEvents_Display::get_event_link_html()` (untouched, reads same meta keys)
- [x] Archive block's `button_link` slot continues to work unaffected (untouched, reads same meta keys)
- [ ] Form editor (Event Details meta box) still loads without broken markup — **not verified live**, no running WP instance reachable from this plugin checkout; `php -l` and PHPCS (WordPress standard) pass with zero new findings, and the markup mirrors the existing Location/Organizer rows exactly. Recommend a quick manual check in the browser before merging.

## Dependencies & Risks

- **Risk: none identified.** This is a like-for-like restoration of previously-shipped, previously-tested code; no schema, registration, or REST changes involved.
- **Dependency:** none — pure PHP change in `includes/class-carkeekevents-meta-boxes.php`, no build step required.

## Sources & References

- Removed in: commit `2dee50a` ("adding event blocks, clean up hidden functionality")
- Original UI code (to restore verbatim): `git show 2dee50a^:includes/class-carkeekevents-meta-boxes.php` (was at lines ~178-201)
- Original save logic (to restore verbatim): `git show 2dee50a^:includes/class-carkeekevents-meta-boxes.php` (was at lines ~424-443)
- Current dead-read of these values (to be wired up): `includes/class-carkeekevents-meta-boxes.php:99-100`
- Meta registration (unchanged, already correct): `includes/class-carkeekevents-meta.php:107-126`
- Front-end consumer (unchanged, already correct): `includes/class-carkeekevents-display.php:251-280`
- Origin plan for the removal, including the open question this fix resolves: `docs/plans/2026-03-31-feat-event-detail-blocks-plan.md` (see "Fields to Remove from Meta Box" and "Open Questions" #3)
- Related learning on classic-meta-box vs. REST race conditions (confirmed not applicable here): `docs/solutions/runtime-errors/wordpress-block-editor-meta-revert-custom-fields-support.md`
