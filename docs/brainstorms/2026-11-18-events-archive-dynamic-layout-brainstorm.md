---
date: 2026-11-18
topic: events-archive-dynamic-layout
---

# Events Archive: Dynamic Layout and Linked Element

## What We're Building

Two additive options on the `carkeek-events/archive` block. Existing blocks render exactly as they do today, with no re-saving or migration.

**Dynamic layout.** A third Post Layout choice beside Grid and List. The arrangement depends on how many events are displayed, per the [SVPA comp](https://www.figma.com/design/gRGMEYCA0rIZD3IP3aVMBB/SVPA?node-id=1142-4764&m=dev):

| Events shown | Desktop arrangement | Content |
| --- | --- | --- |
| 1 | Two columns: text left, image right, text vertically centered | Date, title, excerpt |
| 2 | Two equal columns, image above text | Date, title |
| 3 or more | Three columns, image above text, wrapping to new rows | Date, title |

**Linked element.** A new setting choosing whether the event's Title or Date links to the event. The default is Title. It applies to every layout, not only Dynamic.

## Why This Approach

The layout responds to the number of cards actually on the page, using CSS. The block outputs the same card markup at every count.

We chose this over a server-set count class because Load More appends cards and FacetWP replaces the list contents after page load. A server-set class would need extra JavaScript and could go stale. The CSS approach stays correct with no new JavaScript.

## Key Decisions

- **Count means events displayed**, not total matching events. A block limited to 2 events uses the two-column layout even if 10 match.
- **"Event at Location" is the event title.** It is placeholder text in the comp, so the existing Title slot is used and no combined slot is added.
- **Content still comes from the existing slots.** The comp's setup is the Date only slot, then Title, then Excerpt, with a date format of `D, M j`.
- **Excerpt shows only for a single event.** It stays in the HTML and is hidden at 2 or more events, so it reappears correctly if filtering narrows the list to one.
- **Linked element options are Title and Date.** The chosen element is the text link and the other renders as plain text, keeping its existing class for styling.
- **The featured image stays linked** in all cases, matching current behavior.
- **Date link falls back to Title.** If Date is selected but a card has no date slot or value, the title is linked so the card keeps a text link.
- **Date links name the event for screen readers.** The accessible name starts with the visible date and adds the title, for example "Sat, Nov 1: Creek Cleanup". This offsets the weaker link text, while the SEO cost of the non-descriptive anchor is accepted as a design choice.
- **Responsive:** every count stacks to one column on phones, image above text. On tablet, 3 or more events use two columns, and the 1 and 2 event layouts stay as designed.
- **Column controls are hidden for Dynamic**, since the event count determines columns.
- **Styling split follows the plugin's convention:** the plugin ships structural layout only. Colors and type, such as the green date, belong in the theme and must work in both `wp-rig` (local preview) and `svpa` (production bundle).

## Edge Cases to Cover

- An event with no featured image shows its text alone, without an empty image column.
- Load More moving from 1 to 2 or more events changes the layout and hides the excerpt.
- FacetWP filtering down to 1, 2, or 3+ events updates the layout.
- The existing `.carkeek-events-archive__list .carkeek-event-card` rule makes cards side by side at 600px and up on every layout, so Dynamic must override it for the 2 and 3+ arrangements.
- Load More cards must honor the Linked element setting, as they are rendered by the same card method.

## Resolved Questions

- **How is the "Event at Location" line built?** Event title only.
- **When does the excerpt show?** Only when one event is displayed.
- **Does the image stay linked when Date is chosen?** Yes.
- **How is the count determined?** By CSS, from the cards on the page.
- **What happens on smaller screens?** Stack on phones, two columns on tablet for 3 or more events.

## Open Questions

None.

## Next Steps

→ `/ce:plan` for implementation details
