---
title: "Add to Calendar module (Google + iCal) for single events"
topic: add-to-calendar-module
date: 2026-07-08
status: brainstorm
---

# Brainstorm: Add to Calendar Module (Google + iCal)

## Context & Goal

Part of a 6-month push to replace **The Events Calendar (TEC)** across as many sites
as possible. One client specifically values TEC's "Add to Calendar" button. This
feature builds a lightweight equivalent for the `carkeek-events` plugin so that
feature-parity gap closes cheaply.

**What TEC's `iCalendar` dir actually contains** (from
`/Users/pok/.../the-events-calendar/src/Tribe/Views/V2/iCalendar`):
- `Link_Interface` + `Link_Abstract` — internal PHP interface/base class, **not**
  user-facing services. Every provider extends them.
- `Google_Calendar` — a plain `google.com/calendar/render?action=TEMPLATE&text=…&dates=…&details=…&location=…` URL.
- `iCal` / `iCalendar_Export` — generate the `.ics` file (**also** consumed by Apple
  Calendar and Outlook desktop).
- `Outlook_365` / `Outlook_Live` — "add to Outlook web" URLs; `Outlook_Export` — the
  same `.ics`.

Conclusion: **Google + iCal (.ics) covers nearly everything.** Outlook-web is the only
extra option and is cheap to add later.

## What We're Building

A **single-event "Add to Calendar"** control offered two ways, matching the plugin's
existing architecture (display helpers + dynamic blocks):

1. **Template module** — `CarkeekEvents_Display::get_add_to_calendar_html( $post_id, $args )`
   returning ready-to-echo markup, plus low-level `get_google_calendar_url( $post_id )`
   and `get_ical_url( $post_id )` helpers. Usable in `single-carkeek_event*.php` and any
   custom theme template. Mirrors the existing `get_event_link_html()` pattern.
2. **Block** — a new dynamic block `carkeek-events/add-to-calendar` whose `render.php`
   calls the helper. Mirrors the existing `event-details` / `event-date-time` blocks.
   Works in any single-event context — a single event page **or** an event card inside a
   query loop.

**UI:** one button that expands via a native `<details>` disclosure (accessible, no JS):
`[ + Add to Calendar ▾ ]` → **Google Calendar** · **Download .ics**.

**Data source:** existing event meta — title, `_carkeek_event_start` / `_carkeek_event_end`
(ISO local time; `00:00:00` = all-day), a plain-text location string, and a description
(see Open Questions) plus a link back to the event permalink.

## Why This Approach

- **Reuses the plugin's proven pattern** (static display helper + a render.php block), so
  it slots in with near-zero new architecture and stays theme-overridable.
- **Minimal infrastructure**: Google is a pure URL. The `.ics` is a small server response.
- **Positions the TEC migration**: covers the single most-requested TEC calendar feature
  at low cost and risk.

## Key Decisions

1. **Scope: single event only.** No calendar-feed "subscribe" in v1 (that needs a live
   `.ics` feed endpoint + caching — deferred).
2. **Services: Google Calendar + iCal `.ics`.** The `.ics` already serves Apple Calendar
   and Outlook desktop. Outlook-**web** deferred (one extra URL, add if a client needs it).
3. **`.ics` delivery: a real endpoint via a registered query var** (e.g. `carkeek_ical`)
   handled on `template_redirect` for `is_singular('carkeek_event')`, streaming
   `Content-Type: text/calendar` + `Content-Disposition: attachment; filename=<slug>.ics`.
   **No rewrite rule → no rewrite-rule flush** (this plugin has a history of rewrite-flush
   pain and a manual "Flush Rewrite Rules" tool — avoid adding to it). Best mobile/Apple UX
   and gives a clean, shareable file that Outlook-web could reuse later.
4. **UI: native `<details>` disclosure.** No JS dependency; keyboard/screenreader friendly.
5. **A global on/off setting**, added to the existing **"Fields in Use"** settings section as
   an "Add to Calendar" checkbox. It gates both the block availability and the `.ics` endpoint
   (endpoint returns 404 when disabled). Consistent with the existing field-in-use pattern.
   Default on.
6. **Calendar-entry body: excerpt + link back.** The notes field carries the event **excerpt**
   (fallback to trimmed post content), followed by a "More info:" link to the event permalink.

## Key Considerations / Risks (for the plan, not decisions now)

- **Timezone + all-day correctness is the main risk.** Meta is local ISO; `00:00:00` means
  all-day. Timed events → convert via `wp_timezone()` (UTC `Z` in `.ics`; UTC + `ctz` for
  Google). All-day → `VALUE=DATE` with an **exclusive** `DTEND` (+1 day) in `.ics`; date-only
  range for Google.
- **`.ics` text rules**: escape `,` `;` `\` and newlines; fold lines at 75 octets; stable
  `UID` + `DTSTAMP`.
- **Reuse gap**: existing location helpers return HTML — need a plain-text address helper
  (`get_location_string()` or similar) for the URL/`.ics` `LOCATION` field.

## Alternatives Considered

- **Inline `data:` URI `.ics`** — zero infra, but worse iOS/Apple + mail-client handling. Rejected.
- **Pretty rewrite endpoint** (`/event/<slug>/ical/`) — nicer URL but reintroduces rewrite
  flushing. Rejected in favor of the query var.
- **Full subscribe/feed** — bigger; not what the client asked for. Deferred.
- **Third-party "add to calendar" JS library** — needless dependency; we already own the data. Rejected.

## Resolved Questions

1. **Calendar-entry description body** → **Excerpt + link back.** Notes field = event excerpt
   (fallback to trimmed content) + a "More info:" link to the event permalink.
2. **Global enable setting** → **Yes — a settings toggle.** Add an "Add to Calendar" checkbox
   to the existing "Fields in Use" section; it gates the block and the `.ics` endpoint. Default on.
