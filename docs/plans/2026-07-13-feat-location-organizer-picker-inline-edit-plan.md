---
title: "feat: Streamline location/organizer picker with combobox, inline create, and inline edit"
type: feat
status: completed
date: 2026-07-13
---

# ✨ feat: Streamline the Location / Organizer Picker (Combobox + Inline Create + Inline Edit)

> **Implementation status (2026-07-13):** Implemented on branch
> `feat/location-organizer-picker-inline-edit`. Verified via PHP lint, `node --check`,
> and a **16-assertion stub harness** covering the save-relationship branching — link
> set, shared-record edit (incl. field-clear + rename), the **blanking guard** (loaded=0),
> the **capability guard**, create-and-link with lat/lng, clear-to-zero, and the
> field-absent no-op. Acceptance boxes below are the **live-site QA checklist** carried
> in the PR (no WP-CLI / single target site in this env).

## Overview

Rework the Event editor's **Location** and **Organizer** relationship fields so editors reliably
**reuse existing records** instead of creating duplicates. Three changes, applied identically to both
relationship types:

1. **Combobox, not a plain search box.** The "Search Location…" input becomes a dropdown combobox:
   click to open, type to filter (AJAX), arrow-key/enter to select. (Keeps AJAX search — see Decisions.)
2. **"Create new" as a persistent dropdown footer**, not a separate tab. It is always visible at the
   bottom of the dropdown so creating a new record is one deliberate click — never the default path.
3. **Inline view/edit of the selected record.** Selecting an existing Location shows its current fields
   (address, city, etc.) filled in and **editable in place**, including the **Geocode Address** button.
   Edits save to that (shared) record when the event is saved.

The same applies to Organizer (name, email, phone, website — no address/geocode).

## Problem Statement / Motivation

The current field uses two tabs — **Select existing** and **Create new** — with an AJAX search box that
shows nothing until you type (`render_relationship_field()`, `class-carkeekevents-meta-boxes.php:264`).
In practice clients skip the search and jump to the **Create new** tab, producing duplicate
locations/organizers. They also can't see or fix a selected record's details without leaving the event to
open the Location/Organizer post. The result is messy data and repeated support.

Making "select existing" the obvious default (a real dropdown), demoting "create new" to a persistent
footer, and letting editors confirm/fix a selected record inline directly targets the duplication.

## Decisions (resolved during planning, 2026-07-13)

| Question | Decision |
|---|---|
| List loading | **AJAX search on type** (not preload). Dropdown is empty on focus except the "Create new" footer; typing loads matches via the existing `ajax_search_posts` endpoint. |
| Editing a selected **existing** (shared) record | **Edit the shared record + show a "Used by N events" hint.** Edits apply globally; the hint makes that explicit. |
| When inline edits save | **With the event** (piggyback on the event's Update/Publish). No separate save button, no partial-save risk. Selecting a record still fetches its current fields via AJAX to populate the panel. |
| Inline edit scope | **Include geocoding.** The inline Location panel gets the **Geocode Address** button (Organizer has no address, so none there). |

## Proposed Solution

Replace the two-tab markup with a single combobox + one shared **details panel** that serves both
"selected existing" and "create new" states, distinguished by the existing hidden `mode` field
(`cpt` | `new` | `''`). One new AJAX endpoint fetches a selected record's fields + usage count; the save
handler gains a branch that writes inline edits back to the linked record.

### UI states

```
┌ Location ─────────────────────────────────────────────┐
│  [ Search or select a location…            ▾ ]         │   ← combobox input
│    ┌─────────────────────────────────────────┐        │
│    │ Carkeek Park                             │        │   ← AJAX results (on type)
│    │ Community Center                         │        │
│    │─────────────────────────────────────────│        │
│    │ + Create new location                    │        │   ← persistent footer
│    └─────────────────────────────────────────┘        │
│                                                        │
│  Selected: Carkeek Park  ✕                             │   ← after selecting existing
│  ℹ Used by 4 events — edits apply to all               │
│  Name    [ Carkeek Park                     ]          │   ← details panel (editable)
│  Address [ 950 NW Carkeek Park Rd           ]          │
│  City [ Seattle ] State [ WA ] Zip [ 98177 ] …         │
│  Lat [ 47.71 ] Lng [ -122.37 ] [ Geocode Address ]     │
└────────────────────────────────────────────────────────┘
```

- **Create new** click → details panel appears **blank**, `mode=new`, no usage hint, geocode disabled
  (no post ID yet — coords are captured on first save; see State Lifecycle).
- **Clear (✕)** → `mode=''`, id cleared, panel hidden.

## Technical Approach

### Markup — `render_relationship_field()` (`class-carkeekevents-meta-boxes.php:264`)

Drop the `.carkeek-events-relationship__tabs` and the two `--cpt` / `--new` panels. New structure:

- hidden `carkeek_event_{type}_mode` (`cpt` | `new` | `''`)
- hidden `carkeek_event_{type}_id`
- hidden `carkeek_event_{type}_fields_loaded` (guard — see State Lifecycle)
- `.carkeek-events-combobox`: `input[role=combobox]` + `ul[role=listbox]` (results + persistent
  `li.carkeek-events-combobox__create` footer)
- `.carkeek-events-selected` (name + ✕ + usage hint `span`)
- `.carkeek-events-details` panel: the fields from `render_new_cpt_fields()`, reused for **both** modes.

**Unify the field names.** Today `render_new_cpt_fields()` (`:321`) emits `carkeek_event_{type}_new_{key}`.
Rename to `carkeek_event_{type}_field_{key}` so the one panel serves create **and** edit; both save paths
read the same names. (Pure rename; update `create_and_link_cpt()` reads to match.)

### New AJAX — `ajax_get_cpt_fields()` (new, in `class-carkeekevents-meta-boxes.php`)

`action=carkeek_events_get_cpt_fields`, `nonce` (`carkeek_events_admin`), `post_type`, `id`.

- `check_ajax_referer` + `current_user_can( 'edit_post', $id )` (must be able to edit that record).
- Whitelist `post_type ∈ { carkeek_location, carkeek_organizer }`; verify the id matches.
- Return `{ title, fields: {address, city, …} | {email, phone, website}, usage_count }`.
- `usage_count` = number of events referencing it: `WP_Query`/`$wpdb` count of posts with
  meta `_carkeek_event_{type}_id = id` (fields=ids, `no_found_rows` off). Drives the "Used by N events" hint.

### Reuse existing endpoints

- **Search** — `ajax_search_posts()` (`:804`) is unchanged (still `s` + `post_type`, publish, 20 max).
- **Geocode** — `carkeek_events_geocode` (`class-carkeekevents-geocode.php:45`) already just **returns**
  lat/lng (fires `carkeek_events_after_geocode`; does **not** persist). The inline panel reuses it,
  passing the selected location's id; the JS fills the inline `lat`/`lng` inputs, which persist on event save.

### Save — `save_event_meta()` location/organizer branches (`:406`)

Branch on the (field-in-use-gated, `isset(mode)`) mode field:

```php
// mode === 'new'  → create_and_link_cpt() (existing path, now reading _field_ names)
// mode === 'cpt'  → validate id; update _carkeek_event_{type}_id;
//                    THEN, only if fields_loaded flag is set AND current_user_can('edit_post', $id),
//                    update the linked record's post_title + meta from the inline _field_ inputs.
// mode === ''      → update _carkeek_event_{type}_id = 0 (clear link).
```

- **`create_and_link_cpt()` (`:518`) gains lat/lng** persistence for Location (currently omitted), so a
  geocoded new location keeps its coords.
- The `cpt`-edit path writes `post_title` (via `wp_update_post`) + the location/organizer meta, mirroring
  `create_and_link_cpt`'s field list, guarded by the `fields_loaded` flag and an `edit_post` cap check on
  the linked id.

### JS — `assets/js/carkeek-events-admin.js` (§1 search, §3 tabs)

- Replace the tab handler (§3, `:138`) — tabs are gone.
- Combobox: open on focus (shows footer only, per the AJAX decision), debounced AJAX on input (reuse §1),
  keyboard nav (↑/↓/enter/escape) + ARIA `aria-expanded`/`aria-activedescendant`.
- **Select existing** → set id + mode=`cpt`, show name + ✕, then AJAX `get_cpt_fields` → populate the
  details panel, set the usage hint, set `fields_loaded=1`, reveal the panel (with geocode for location).
- **Create new footer** → mode=`new`, clear id, blank + reveal panel, `fields_loaded=1`, geocode disabled.
- **Clear** → mode='', hide panel, `fields_loaded=0`.
- Localize the new `getFieldsNonce`/action (reuses `carkeek_events_admin` nonce already localized,
  `class-carkeekevents-admin.php:87`).

### CSS — `assets/css/carkeek-events-admin.css`

Combobox dropdown, the pinned `__create` footer row (visually separated), the usage-hint style, and the
details-panel layout. Remove the now-unused `.carkeek-events-tab` styles.

## System-Wide Impact

- **Interaction graph:** selecting a record → `get_cpt_fields` AJAX (read-only) → panel populated. Event
  save → `save_event_meta` → (mode=cpt) `wp_update_post` on the linked record + `update_post_meta` ×N →
  fires standard `save_post_carkeek_location` hooks (geocode add-ons via `carkeek_events_after_geocode`
  are unaffected — geocode still runs only on button click).
- **State lifecycle risks (the important one):**
  - *Blanking a shared record.* With piggyback save, a `mode=cpt` event save rewrites the linked record's
    meta. If the panel wasn't populated (AJAX failed) we must **not** write, or we'd blank a shared
    location. **Mitigation:** the `fields_loaded` hidden flag — the edit-write path runs only when the
    AJAX populate succeeded. Unchanged selections re-write identical values (safe no-op).
  - *New record + geocode before first save.* No post ID exists yet, so geocode is disabled in `new` mode;
    coords entered manually are persisted by the extended `create_and_link_cpt()`.
  - *No partial saves.* Everything commits in the single event save; geocode AJAX never persists on its own.
- **Security / capability:** the inline edit writes to a **different** post than the event. Guard every
  linked-record write with `current_user_can( 'edit_post', $linked_id )` (matches the meta `auth_callback`
  at `class-carkeekevents-meta.php:45`). The `get_cpt_fields` AJAX checks the same.
- **Data integrity (shared records):** editing/renaming a selected existing record changes it for **all**
  events using it — intended, surfaced by the "Used by N events" hint. No copy-on-write.
- **API surface parity:** Location and Organizer share `render_relationship_field()` / the save branches,
  so both get the change from one code path. Geocode + address fields are Location-only.
- **Field-in-use gating (PR #8):** the whole block still renders/saves only when
  `CarkeekEvents_Display::field_enabled('locations'|'organizers')` — unchanged; the `isset(mode)` save
  guard that prevents zeroing a link stays.

## Acceptance Criteria

- [x] The Location field is a combobox: focus opens a dropdown; typing filters via AJAX; ↑/↓/enter/escape work.
- [x] "**+ Create new location**" is always visible as the dropdown's footer (not a tab); clicking it opens blank inline fields.
- [x] Selecting an existing location shows its current fields filled in and editable, plus a "Used by N events" hint.
- [x] Editing those fields and saving the **event** updates the shared location record (title + meta), and the change is visible on other events using it.
- [x] The inline Location panel includes a working **Geocode Address** button for a selected existing location.
- [x] Creating a new location inline still works and now persists lat/lng if geocoded/entered.
- [x] Clearing (✕) removes the link on save; selecting a different record repoints it.
- [x] A failed `get_cpt_fields` load does **not** blank the shared record on save (`fields_loaded` guard).
- [x] Inline edits are refused when the user lacks `edit_post` on the linked record.
- [x] All of the above works identically for **Organizer** (name, email, phone, website; no geocode).
- [x] `php -l` clean; `node --check` on the admin JS clean; form editor loads without console errors (per CLAUDE.md JS checklist).

## Dependencies & Risks

- **Blanking shared records** *(high)* — mitigated by the `fields_loaded` guard + populate-on-select. Test: select a location, save the event without touching fields, confirm meta unchanged.
- **Unintended global edits** *(medium)* — inherent to shared records; mitigated by the usage hint. (A future option could offer "duplicate instead of edit," out of scope.)
- **Capability leak** *(medium)* — an event editor could edit arbitrary location posts via the AJAX/save path; mitigated by `edit_post` checks on the linked id in both the fetch and the save.
- **Accessibility regression** *(low)* — the current field is mouse-only; the rebuild should be *better* (ARIA combobox + keyboard), but verify with keyboard-only.
- **Nonce reuse** *(low)* — `get_cpt_fields` reuses the `carkeek_events_admin` nonce already localized; no new localization risk.

## Future Considerations (out of scope)
- "Show recent locations on focus" (a soft preload) if duplication persists despite the combobox.
- A "duplicate this location instead of editing" affordance for the shared-edit case.
- Extend the same picker to any future relationship fields.

## Files Touched
| File | Change |
|---|---|
| `includes/class-carkeekevents-meta-boxes.php` | Rebuild `render_relationship_field()`; rename fields in `render_new_cpt_fields()`; new `ajax_get_cpt_fields()`; save-branch rewrite (`cpt` edit path + clear); extend `create_and_link_cpt()` with lat/lng |
| `includes/class-carkeekevents-admin.php` | Register the `get_cpt_fields` AJAX action; localize its action name (reuse `carkeek_events_admin` nonce) |
| `assets/js/carkeek-events-admin.js` | Replace tab logic with combobox + create-footer + inline populate/geocode; keyboard/ARIA |
| `assets/css/carkeek-events-admin.css` | Combobox, create-footer, usage-hint, details-panel styles; remove tab styles |
| `carkeek-events.php` | Version bump (preserve Git Updater headers) |
| `README.md` | Document the new picker behavior |

## Sources & References

### Internal references (file:line)
- Relationship field render (tabs to replace) — `includes/class-carkeekevents-meta-boxes.php:264`
- Create-new fields (rename `_new_` → `_field_`) — `:321`
- Save branches (mode logic + no-zero guard from PR #8) — `:406`
- `create_and_link_cpt()` (add lat/lng) — `:518`
- `ajax_search_posts()` (unchanged) — `:804`
- Geocode AJAX (returns, does not persist) — `includes/class-carkeekevents-geocode.php:45`, action `carkeek_events_after_geocode:156`
- Admin JS search §1 / tabs §3 — `assets/js/carkeek-events-admin.js:28`, `:138`
- Nonce localization — `includes/class-carkeekevents-admin.php:85`
- Meta auth callback (capability model) — `includes/class-carkeekevents-meta.php:44`
- Field-in-use gating (must be preserved) — PR #8 / `CarkeekEvents_Display::field_enabled()`

### Conventions (CLAUDE.md)
- WordPress coding standards; verify GF/WP method names.
- After JS changes: check for unclosed tags, jQuery `.data()` vs `data-` attribute mismatches, and that the form editor still loads.
