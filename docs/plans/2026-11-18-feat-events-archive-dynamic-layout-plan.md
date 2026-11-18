---
title: "feat: Events Archive dynamic layout and linked element option"
type: feat
status: active
date: 2026-11-18
origin: docs/brainstorms/2026-11-18-events-archive-dynamic-layout-brainstorm.md
---

# ✨ feat: Events Archive Dynamic Layout and Linked Element Option

## Overview

Add two opt-in options to the `carkeek-events/archive` block (see brainstorm: docs/brainstorms/2026-11-18-events-archive-dynamic-layout-brainstorm.md):

1. **Dynamic** as a third Post Layout, arranged by how many events are displayed.
2. **Linked Element**, which links either the Title (default) or the Date to the event, on every layout.

Reference design: [SVPA Events Listings](https://www.figma.com/design/gRGMEYCA0rIZD3IP3aVMBB/SVPA?node-id=1142-4764&m=dev).

## Problem Statement / Motivation

A recurring design pattern changes a listing's arrangement according to its item count. The block offers only fixed Grid and List layouts, and always makes the title the text link. The SVPA comp needs both to be configurable, without changing any block already published on any site using this shared plugin.

## Proposed Solution

### Behavior

| Events on page | Desktop | Tablet (≤782px) | Phone (≤480px) | Excerpt |
| --- | --- | --- | --- | --- |
| 1 | Text left, image right, equal columns, vertically centered | Same | Stacked, image first | Shown |
| 2 | 2 columns, image above text | 2 columns | 1 column | Hidden |
| 3+ | 3 columns, wrapping | 2 columns | 1 column | Hidden |

The count is the number of cards on the page, not the number of matching events (see brainstorm).

### Why this needs little PHP

- `render()` already emits `is-{layout}`, so `postLayout: "dynamic"` yields `.is-dynamic` with no change (`includes/class-carkeekevents-block.php:97-109`).
- Column classes are grid-only, and the column controls are already gated on Grid (`src/events-archive/inspector.js:309`), so they disappear for Dynamic automatically.
- Cards are direct children of the list, so CSS can read the count from them.
- FacetWP swaps the list with `$('.facetwp-template').html(inject)` (`facetwp/assets/js/src/front.js`), keeping the container and its classes. Load More appends to the same list (`src/events-archive/view.js:62`). A CSS count stays correct through both.

## Technical Considerations

### 1. Block attributes: `src/events-archive/block.json`

- Add `"linkedElement": { "type": "string", "default": "title" }`.
- Add a top-level `"version": "2.6.0"`. The block has none today, so WordPress versions `style-index.css` with the WordPress version (`wp-includes/blocks.php`, `register_block_style_handle`). Without this, browsers and CDNs can keep the old stylesheet after the plugin update, and Dynamic would appear unstyled.
- `postLayout` needs no schema change. There is no `save` output, so no deprecation or migration is needed.

### 2. Inspector: `src/events-archive/inspector.js`

- Add `{ label: 'Dynamic', value: 'dynamic' }` to the Post Layout radio (`:303-306`), with help text shown only when selected: "Arranges events automatically for 1, 2, or 3 or more. The excerpt shows only for a single event."
- Add a **Linked Element** `SelectControl` (Title, Date) to the Content panel after the slot list (`:397`).
- When Date is selected and neither `date` nor `date_time` is in the slots, show help text: "Add a Date slot to link the date. Until then, the title is linked." The existing `hasDateSlot` includes `time`, so this needs its own check.

### 3. Rendering: `includes/class-carkeekevents-block.php`

Resolve the link target once per card in `render_single_card()` (`:307`), then pass it to `render_slot()` (`:482`), whose only caller is that method.

```php
// includes/class-carkeekevents-block.php
private function get_linked_slot( $slots, $post_id, $attributes ) {
	if ( 'date' !== ( $attributes['linkedElement'] ?? 'title' ) ) {
		return 'title';
	}

	$config = $this->get_post_type_config( $attributes );
	if ( ! get_post_meta( $post_id, $config['start_meta'], true ) ) {
		return 'title';
	}

	foreach ( $slots as $slot ) {
		if ( in_array( $slot, array( 'date', 'date_time' ), true ) ) {
			return $slot;
		}
	}

	return 'title';
}
```

- The `?? 'title'` and strict comparison cover Load More requests from pages cached before the update, and any unexpected value in the client-supplied AJAX attributes.
- `title` slot: when linked, output stays byte-identical to today. Otherwise output `<span class="carkeek-event-card__title">`, keeping the class for theme styling.
- Date slot: when it is the linked slot, wrap the existing output:

```php
// includes/class-carkeekevents-block.php, in render_slot()
$label = sprintf(
	/* translators: 1: event date, 2: event title. */
	_x( '%1$s: %2$s', 'event date link label', 'carkeek-events' ),
	wp_strip_all_tags( $date_html ),
	get_the_title( $post_id )
);

return '<a class="carkeek-event-card__date-link" href="' . esc_url( $permalink ) . '"'
	. ' aria-label="' . esc_attr( $label ) . '">' . $date_html . '</a>';
```

- The accessible name starts with the visible date, so voice-control users can still target it by what they see.
- A `time` slot alone does not count as a date, so it falls back to the title.
- Only one date slot is linked if both `date` and `date_time` are present: the first in slot order.
- The image link and `button_link` slot are unchanged (see brainstorm).
- `ajax_load_more()` shares `render_single_card()`, so Load More cards inherit the setting.
- Update the class docblock's slot notes.

### 4. Structural CSS: `src/events-archive/style.scss`

Scoped entirely under `.is-dynamic`, so no other layout can change. `:has()` is already used for this kind of count in the theme (`wp-rig/assets/css/global/src/_theme-styles.css:308`).

```scss
// src/events-archive/style.scss, inside .carkeek-events-archive
&.is-dynamic {
	display: grid;
	gap: 1.5rem;
	grid-template-columns: repeat(3, minmax(0, 1fr));

	&:has(> .carkeek-event-card:nth-child(2):last-child) {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}

	.carkeek-event-card {
		flex-direction: column;
		min-width: 0;
	}

	.carkeek-event-card__image-link,
	.carkeek-event-card__content {
		flex: 0 0 auto;
	}

	.carkeek-event-card__image-link img {
		display: block;
		width: 100%;
		height: auto;
	}

	.carkeek-event-card:only-child {
		grid-column: 1 / -1;

		@media ( min-width: 481px ) {
			align-items: center;
			flex-direction: row-reverse;

			.carkeek-event-card__image-link,
			.carkeek-event-card__content {
				flex: 1 1 0;
				min-width: 0;
			}
		}
	}

	.carkeek-event-card:not(:only-child) .carkeek-event-card__slot--excerpt {
		display: none;
	}

	@media ( max-width: 782px ) {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}

	@media ( max-width: 480px ) {
		&,
		&:has(> .carkeek-event-card:nth-child(2):last-child) {
			grid-template-columns: minmax(0, 1fr);
		}
	}
}
```

Points that are easy to get wrong:

- The existing `.carkeek-events-archive__list .carkeek-event-card` rules (`style.scss:28-50`) turn every card into a row at 600px and up. The higher-specificity `.is-dynamic` rules must override both the direction and the `flex: 1 1 50%` children. Do not edit the existing rules.
- The `:has()` rule outranks a plain media-query rule, so the phone rule must repeat it.
- With no image, the single card's content grows to full width because of `flex: 1 1 0`.
- If `:has()` is unsupported, two events sit in the first two of three columns, which is an acceptable fallback.
- Follow the existing 782px and 480px block breakpoints.

### 5. SVPA theme styling: `themes/wp-rig`

The plugin stays structural, and visual design belongs to the theme (see brainstorm). Edit **`wp-rig` only**. `themes/svpa` is generated by `npm run bundle` and is never edited directly.

Add to `assets/css/global/src/_theme-styles.css`, beside the existing archive rules (`:338-368`), which already style `--date` and `--title`. Avoid `_events.css`, which has uncommitted changes.

| Element | Comp value |
| --- | --- |
| 3+ grid gap | 30px |
| 1 and 2 event column gap | 50px |
| 3+ image ratio | 384 / 300 |
| 1 and 2 event image ratio | 581 / 414 (existing 1.4 is equivalent) |
| Image to text gap | 15px |
| Date to title gap | 5px, or 10px for a single event |
| Title to excerpt gap, single event | 20px |

- `.carkeek-event-card__date-link` needs `color: inherit`, so the date keeps its theme color, including the white variant on `.has-secondary-background-color` (`:370`). Add a visible hover state and keep the focus outline.
- Before styling, load exact type and color from the comp using the `figma-design-to-code` workflow. These measurements came from frame metadata only. The title looks black in the comp, while the theme's title slot uses `$accent1`, so confirm whether Dynamic needs an override.

### 6. Documentation: `README.md`

Update the Post Layout row, add a Linked Element row, and add a short Dynamic section with the comp's setup: slots **Date only, Title, Excerpt**; Date Format `D, M j`; Linked Element **Date**.

## System-Wide Impact

- **Interaction graph:** Block render → `render_single_card()` → `get_linked_slot()` → `render_slot()`. The same path serves the page, the editor's `ServerSideRender` preview, Load More, and FacetWP refreshes.
- **Editor preview:** `ServerSideRender` sends all attributes to the REST block renderer, which rejects unknown ones. `build/events-archive/block.json` must be rebuilt, or the preview errors.
- **Error propagation:** No new failure paths. Missing dates and unexpected values fall back to the title link.
- **State lifecycle:** No data is written beyond one block attribute. Unused defaults are not serialized, so existing post content is unchanged.
- **API surface parity:** `templates/event-card/default.php` serves the `carkeek-blocks` integration, not this block, and is out of scope. Existing filters keep working. The SVPA `carkeek_events_block_before_slots` filter adds "Featured Event" for List only, so it does not appear in Dynamic.
- **Other sites:** The new CSS is inert without `.is-dynamic`, and `.carkeek-event-card__date-link` exists only when opted in.

## Acceptance Criteria

### Backward compatibility

- [x] Existing Grid and List blocks need no re-save, and their card markup is byte-identical before and after.
- [x] The only expected rendered differences are `linkedElement` inside Load More's `data-attributes` and the stylesheet's `ver` value.
- [ ] A Load More click on a page rendered before the update still links titles.

### Dynamic layout

- [ ] Post Layout offers Grid, List, and Dynamic, and column controls are hidden for Dynamic.
- [x] 1, 2, 3, and 4+ events match the Behavior table at desktop, tablet, and phone widths.
- [x] Excerpt shows only when exactly one card is on the page.
- [x] A card with no featured image shows text only, with no empty column, at every count.
- [ ] Load More from 1 to 2+ events updates the layout and hides the excerpt.
- [ ] FacetWP filtering to 1, 2, and 3+ events updates the layout and excerpt.
- [ ] Editor preview matches the front end.

### Linked element

- [x] Default is Title, on all layouts.
- [x] With Date selected, the first `date` or `date_time` slot is linked, the title is plain text, and the image stays linked.
- [x] The title falls back to a link if the card has no linked-date slot or no start date.
- [x] The date link's accessible name is the date followed by the event title.
- [ ] Load More cards follow the setting.
- [ ] The setting works with an alternative post type.

### SVPA and build

- [x] The block matches the comp in `wp-rig`.
- [ ] The same styles are present in the production theme after `npm run bundle`.
- [ ] Date link color is correct on default and secondary backgrounds.
- [x] `php -l` passes on changed PHP, and `npm run build` passes with `:has()` selectors intact in `build/events-archive/style-index.css`.
- [x] The rebuild changes no unrelated block's compiled source, or any such change is understood before commit.

## Implementation Phases

1. **Prerequisites:** resolve the branch and template items under Dependencies & Risks. Save rendered HTML of an existing Grid and List block as the baseline.
2. **Linked Element:** attribute, inspector control, PHP, build, and a baseline comparison.
3. **Dynamic layout:** radio option, structural CSS, build, and browser checks at each count and width.
4. **SVPA styling:** theme CSS in `wp-rig` against the comp, then confirm the bundle output.
5. **Finish:** README, version bump to 2.6.0 in `carkeek-events.php` and `block.json`, final QA.

The repo has no automated test suite, so verification is lint, build, baseline comparison, and browser QA on the local site.

## Success Metrics

An editor can reproduce all three comp states with block settings alone, and no existing block on any site changes.

## Dependencies & Risks

- **Uncommitted syntax error, fix first.** The working copy of `templates/single-carkeek_event-blocks.php` removed a closing `?>` at line 57, and `php -l` fails at line 60. The committed version passes. Single event pages using this template will fatal until it is fixed or reverted.
- **Branch base.** The checkout is `feat/single-template-layout-settings`, 2 commits ahead of `main` and unmerged, and the local site runs whatever is checked out through its symlink. Default: branch `feat/archive-dynamic-layout` from the current branch, so the local site keeps its single-template work, and open the PR after that branch merges. The two share only `README.md` and the version line.
- **Stale CSS** after deployment is handled by the new block `version`. It must be kept current with future block CSS changes.
- **SEO and link clarity.** Date anchor text is less descriptive. This is an accepted design decision, offset by the accessible name and the still-linked image (see brainstorm).
- **Tab order on a single event.** The image is visually right but first in the DOM, so focus goes right, then left. This is accepted, matching the theme's existing `row-reverse` List layout.
- **Existing gap, not changed here.** A featured image with empty alt text gives its link no accessible name.

## Sources & References

- **Origin brainstorm:** [docs/brainstorms/2026-11-18-events-archive-dynamic-layout-brainstorm.md](../brainstorms/2026-11-18-events-archive-dynamic-layout-brainstorm.md). Carried forward: CSS count from cards on the page; excerpt for single events only; Title or Date link with the image always linked and a title fallback.
- Block render and cards: `includes/class-carkeekevents-block.php:83`, `:307`, `:356`, `:482`
- Inspector: `src/events-archive/inspector.js:299-355`, `:371-397`
- Existing styles: `src/events-archive/style.scss:6-50`
- Load More: `src/events-archive/view.js:61-63`
- Theme styles: `wp-content/themes/wp-rig/assets/css/global/src/_theme-styles.css:308`, `:338-383`
- Past learnings: `docs/solutions/` has one entry, on block-editor post meta, which does not apply here because this feature uses block attributes.
