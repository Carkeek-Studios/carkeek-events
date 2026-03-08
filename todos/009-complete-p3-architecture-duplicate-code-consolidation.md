---
status: pending
priority: p3
issue_id: "009"
tags: [code-review, architecture, simplicity, refactor]
dependencies: []
---

# Consolidate Duplicate Code: Date Formatting, Hidden-Event Clause, Slot Parsing

## Problem Statement

Multiple pieces of logic are duplicated across files, creating maintenance burden and risk of the copies diverging over time. Three main areas:

1. **Date formatting**: `CarkeekEvents_Block::format_date_time()` (block.php:511–548) duplicates `CarkeekEvents_Display::format_date_range()` (display.php:68–122)
2. **Hidden-event meta clause**: The `OR (NOT EXISTS / != '1')` array appears verbatim in 3 places
3. **Slot parsing**: `$slots = array_filter(explode(',', $attributes['contentSlots']))` is copy-pasted in `render()` and `ajax_load_more()`

## Findings

### Date formatting duplication
**Files:** `includes/class-carkeekevents-block.php:511`, `includes/class-carkeekevents-display.php:68`

The block comment at line 498 even says *"same logic as CarkeekEvents_Display::format_date_range"* — a self-identified TODO.

### Hidden-event clause duplication
**Files:**
- `includes/class-carkeekevents-query.php:60–71`
- `includes/class-carkeekevents-query.php:101–112`
- `includes/class-carkeekevents-block.php:208–215`

Three identical meta query arrays. If `_carkeek_event_hidden` semantics change (e.g. switching to boolean type or normalizing default values), all three must be updated.

### Slot parsing duplication
**File:** `includes/class-carkeekevents-block.php` lines 133–135 and 330–332

Identical 3-liner. Extract to `private function get_slots_from_attributes( $attributes ): array`.

## Proposed Solutions

### Fix 1: Parameterize `format_date_range` to accept optional format overrides

```php
// display.php — add optional params
public static function format_date_range( $post_id, $date_format = null, $time_format = null ): string {
    $settings    = get_option( CARKEEKEVENTS_OPTION_NAME, array() );
    $date_format = $date_format ?? ( $settings['date_format'] ?? 'M j, Y' );
    $time_format = $time_format ?? ( $settings['time_format'] ?? 'g:i a' );
    // ... rest of existing logic unchanged
}
```

Then `CarkeekEvents_Block::render_date_slot()` calls `CarkeekEvents_Display::format_date_range( $post_id, $slotDateFormat, $slotTimeFormat )` and the 4 private format methods in block.php are deleted (~90 lines removed).

### Fix 2: Static helper on `CarkeekEvents_Query`

```php
public static function hidden_exclusion_clause(): array {
    return array(
        'relation' => 'OR',
        array( 'key' => '_carkeek_event_hidden', 'compare' => 'NOT EXISTS' ),
        array( 'key' => '_carkeek_event_hidden', 'value'   => '1', 'compare' => '!=' ),
    );
}
```

All 3 call sites replace their inline array with `CarkeekEvents_Query::hidden_exclusion_clause()`.

### Fix 3: Extract slot-parsing method

```php
private function get_slots_from_attributes( array $attributes ): array {
    return ! empty( $attributes['contentSlots'] )
        ? array_filter( explode( ',', $attributes['contentSlots'] ) )
        : array( 'title', 'date_time' );
}
```

- **Combined effort:** Medium
- **Risk:** Low — all refactoring with no behavior change
- **LOC reduction:** ~80–100 lines

## Acceptance Criteria

- [ ] Date formatting for block and template contexts uses a single code path
- [ ] Hidden-event meta clause defined in one place, referenced in 3
- [ ] Slot parsing extracted to a private helper method
- [ ] All existing rendering behavior unchanged (no visual regression)

## Work Log

- 2026-03-07: Identified by code-simplicity-reviewer (Findings 1, 2, 3) and architecture-strategist (Findings 3.2, 3.3)
