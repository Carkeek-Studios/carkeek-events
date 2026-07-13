---
title: "Classic single-event template: Figma layout + global display settings"
topic: single-template-layout-and-settings
date: 2026-07-13
status: brainstorm
---

# Brainstorm: Classic Single-Event Template — Figma Layout + Global Settings

## Context & Goal

Clean up the **classic (non-block) single-event template** (`templates/single-carkeek_event.php`)
so it ships the common design our themes use, and move the per-block label/separator options — which
confused a client in the details block — into **global settings** instead. Reference design:
[Figma – Jessica Gigot event](https://www.figma.com/design/Xxns2bIpjJAzGMYdn8Kgym/Jessica-Gigot?node-id=987-248).

## Reference Layout (from Figma)

```
EVENTS                         ← small uppercase tag, links to events landing page
Grounding In: The Poetry…      ← <h1> title

┌ meta (left) ───────────┐   ┌ media (right) ────────┐
│ Date and Time          │   │  [ featured image ]   │
│ Thursday, Nov 9, 2025  │   │  [ Add to Calendar ]  │ ← optional, under image
│ 1:00 pm – 4:00 pm      │   └───────────────────────┘
│ Location               │
│ Location Name Here     │
│ City, State            │
│ SIGN UP                │   ← event link / registration button
└────────────────────────┘

Interdum et malesuada fames…   ← full-width the_content() below both columns
```

Graceful degradation: no featured image → meta column spans full width.

## What We're Building

1. **Rebuild the classic single template** to the two-column layout above (tag → title → meta | media → content).
2. **Add global Display settings** (Events ▸ Settings) that drive both the classic template **and** the
   `event-details` block, replacing the block's per-instance controls:
   - `events_landing_url` — URL for the “EVENTS” tag; blank → event post-type archive link.
   - `datetime_label` — section label (default **“Date and Time”**); **blank = hide the label**.
   - `datetime_separator` — between date and time (default `<br/>`; e.g. `, ` or ` | `).
   - `location_label` — section label (default **“Location”**); blank = hide.
   - `organizer_label` — section label (default **“Organizer”**); blank = hide.
   - `show_add_to_calendar_single` — show the Add to Calendar button under the image in the default template (default **on**; independent of the Fields-in-Use feature toggle).
3. **De-clutter the details block:** remove the `dateTimeLabel` / `dateTimeSeparator` / `locationLabel` /
   `organizerLabel` / `showDirectionsLink` inspector controls; the block now renders from the global
   settings (directions come from the existing `location_display` = `address_directions` setting).
4. **Extension hooks** for per-theme customization (below).

## Why This Approach

- One source of truth for labels/separators → the classic template and the block always match, and editors
  aren’t confronted with confusing per-block label fields.
- Ships the design our sites actually use, while staying overridable (theme template override + hooks +
  minimal, dequeuable CSS) so we “easily modify per theme.”

## Key Decisions (resolved 2026-07-13)

| Question | Decision |
|---|---|
| Events landing page target | **URL field**, default = event archive link when blank. |
| Label/separator options | **Global settings**, used by classic template **and** details block; **remove the block controls**. Existing blocks adopt the global values. |
| Add to Calendar in template | **Separate “Show in single template” setting**, independent of the feature toggle. |
| Title-area extensibility | **Both** a filter on the whole tag+title block **and** before/after actions. |
| Styling | **Minimal structural CSS** (two-column grid, mobile stack, spacing) — no colors/fonts; opt-in and easy to dequeue. |

## Extension Hooks

- `carkeek_events_single_title_block` — filter, `( $html, $post_id )`: replace the entire tag+title markup
  (e.g. inject the event category, which varies per site).
- `carkeek_events_before_title` / `carkeek_events_after_title` — actions, `( $post_id )`.
- `carkeek_events_before_featured_image` / `carkeek_events_after_featured_image` — actions, `( $post_id )`:
  add content under the image (the requested spot for extra media/CTAs).
- Existing helper filters (`carkeek_events_date_range`, `carkeek_events_location_display`, etc.) remain.

## Scope & Constraints

- **Classic template only.** The block template (`single-carkeek_event-blocks.php`) is composed from blocks;
  those blocks now read the same global settings, so behavior is consistent without a bespoke layout there.
- **No block invalidation:** keep the removed attributes *registered* in `event-details/block.json` (just
  drop the inspector UI and ignore them at render) so existing saved blocks don’t throw validation errors.
- **Backward-compat / defaults:** global label defaults follow the Figma (“Date and Time”, “Location”,
  “Organizer”; separator `<br/>`). Sites that had customized block labels will switch to the global values —
  acceptable for the common case (most use defaults); the title-block filter covers bespoke needs.

## Alternatives Considered

- **Keep per-block overrides too** — rejected; it’s the clutter we’re removing.
- **Full Figma styling (colors/fonts)** — rejected; too opinionated, fights themes.
- **Page-dropdown for landing page** — rejected in favor of a URL field (also supports the archive / external).
- **Reuse the Add-to-Calendar feature toggle for template display** — rejected; would couple feature
  availability to template display.

## Resolved Questions
All open questions from planning were resolved in the decisions table above. Remaining minor defaults
(tag text “Events”, Add-to-Calendar-in-template defaulting on) are set as noted and are overridable via
settings/hooks.
