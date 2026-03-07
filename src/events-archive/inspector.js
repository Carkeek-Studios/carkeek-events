import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	PanelRow,
	RangeControl,
	SelectControl,
	ToggleControl,
	TextControl,
	CheckboxControl,
	RadioControl,
	Button,
	Flex,
	FlexBlock,
	FlexItem,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';

const MAX_SLOTS = 5;

const SLOT_OPTIONS = [
	{ value: '', label: __( '— select —', 'carkeek-events' ) },
	{ value: 'title', label: __( 'Title', 'carkeek-events' ) },
	{ value: 'date_time', label: __( 'Date + Time', 'carkeek-events' ) },
	{ value: 'date', label: __( 'Date only', 'carkeek-events' ) },
	{ value: 'time', label: __( 'Time only', 'carkeek-events' ) },
	{ value: 'location', label: __( 'Location', 'carkeek-events' ) },
	{ value: 'organizer', label: __( 'Organizer', 'carkeek-events' ) },
	{ value: 'excerpt', label: __( 'Excerpt', 'carkeek-events' ) },
];

const DATE_SLOT_VALUES = [ 'date_time', 'date', 'time' ];

const EventsInspector = ( { attributes, setAttributes } ) => {
	const {
		numberOfPosts,
		postLayout,
		columns,
		columnsMobile,
		columnsTablet,
		displayFeaturedImage,
		excerptLength,
		showPagination,
		includePastEvents,
		onlyPastEvents,
		sortOrder,
		filterByCategory,
		catFilterMode,
		catTermsSelected,
		hideIfEmpty,
		emptyMessage,
		headline,
		enableLoadMore,
		loadMoreLabel,
		contentSlots,
		slotDateFormat,
		slotTimeFormat,
		showEndDateTime,
	} = attributes;

	// -----------------------------------------------------------------------
	// Content slots
	// -----------------------------------------------------------------------
	const slots = contentSlots ? contentSlots.split( ',' ).filter( Boolean ) : [];
	const hasDateSlot = slots.some( ( s ) => DATE_SLOT_VALUES.includes( s ) );
	const hasExcerptSlot = slots.includes( 'excerpt' );

	const visibleCount = Math.min( MAX_SLOTS, slots.length + 1 );

	const getSlotOptions = ( index ) => {
		const current = slots[ index ] || '';
		return SLOT_OPTIONS.filter(
			( opt ) => opt.value === '' || opt.value === current || ! slots.includes( opt.value )
		);
	};

	const updateSlot = ( index, value ) => {
		const next = [ ...slots ];
		if ( value ) {
			next[ index ] = value;
		} else {
			next.splice( index, 1 );
		}
		setAttributes( { contentSlots: next.filter( Boolean ).join( ',' ) } );
	};

	// -----------------------------------------------------------------------
	// Categories
	// -----------------------------------------------------------------------
	const categories = useSelect( ( select ) => {
		return select( 'core' ).getEntityRecords(
			'taxonomy',
			'carkeek_event_category',
			{ per_page: -1, orderby: 'name', order: 'asc' }
		);
	}, [] );

	const selectedTermIds = catTermsSelected
		? catTermsSelected.split( ',' ).map( Number ).filter( Boolean )
		: [];

	const handleCategoryMultiSelect = ( e ) => {
		const selected = Array.from( e.target.selectedOptions ).map( ( opt ) =>
			parseInt( opt.value, 10 )
		);
		setAttributes( { catTermsSelected: selected.join( ',' ) } );
	};

	// -----------------------------------------------------------------------
	// Number of events — -1 means show all
	// -----------------------------------------------------------------------
	const showAll = numberOfPosts === -1;

	return (
		<InspectorControls>

			{ /* Events Panel — first, open by default */ }
			<PanelBody title={ __( 'Events', 'carkeek-events' ) } initialOpen={ true }>
				<ToggleControl
					label={ __( 'Show All Events', 'carkeek-events' ) }
					help={ __( 'Ignore the number limit and load every matching event.', 'carkeek-events' ) }
					checked={ showAll }
					onChange={ ( value ) =>
						setAttributes( { numberOfPosts: value ? -1 : 6 } )
					}
					__nextHasNoMarginBottom
				/>
				{ ! showAll && (
					<RangeControl
						label={ __( 'Number of Events', 'carkeek-events' ) }
						value={ numberOfPosts }
						onChange={ ( value ) => setAttributes( { numberOfPosts: value } ) }
						min={ 1 }
						max={ 50 }
					/>
				) }
				<SelectControl
					label={ __( 'Sort Order', 'carkeek-events' ) }
					value={ sortOrder }
					options={ [
						{ label: __( 'Upcoming first (ASC)', 'carkeek-events' ), value: 'ASC' },
						{ label: __( 'Latest first (DESC)', 'carkeek-events' ), value: 'DESC' },
					] }
					onChange={ ( value ) => setAttributes( { sortOrder: value } ) }
					__nextHasNoMarginBottom
				/>
				<ToggleControl
					label={ __( 'Include Past Events', 'carkeek-events' ) }
					help={ __( 'Show events whose end date has already passed.', 'carkeek-events' ) }
					checked={ includePastEvents }
					onChange={ ( value ) => setAttributes( { includePastEvents: value, onlyPastEvents: value ? onlyPastEvents : false } ) }
				/>
				{ includePastEvents && (
					<ToggleControl
						label={ __( 'Only Past Events', 'carkeek-events' ) }
						help={ __( 'Show only events that have ended.', 'carkeek-events' ) }
						checked={ onlyPastEvents }
						onChange={ ( value ) => setAttributes( { onlyPastEvents: value } ) }
					/>
				) }
				<ToggleControl
					label={ __( 'Filter by Category', 'carkeek-events' ) }
					checked={ filterByCategory }
					onChange={ ( value ) => setAttributes( { filterByCategory: value } ) }
					__nextHasNoMarginBottom
				/>
				{ filterByCategory && (
					<PanelRow>
						<div style={ { width: '100%' } }>
							<RadioControl
								label={ __( 'Filter mode', 'carkeek-events' ) }
								selected={ catFilterMode || 'include' }
								options={ [
									{ label: __( 'Include selected', 'carkeek-events' ), value: 'include' },
									{ label: __( 'Exclude selected', 'carkeek-events' ), value: 'exclude' },
								] }
								onChange={ ( value ) => setAttributes( { catFilterMode: value } ) }
							/>
							{ categories ? (
								<>
									<p style={ { margin: '4px 0 4px', fontSize: 11, color: '#757575' } }>
										{ __( 'Hold Ctrl / Cmd to select multiple.', 'carkeek-events' ) }
									</p>
									<select
										multiple
										size={ Math.min( 8, categories.length || 4 ) }
										value={ selectedTermIds.map( String ) }
										onChange={ handleCategoryMultiSelect }
										style={ {
											width: '100%',
											minHeight: 100,
											maxHeight: 180,
											overflowY: 'auto',
											border: '1px solid #949494',
											borderRadius: 2,
											padding: '2px 4px',
											fontSize: 13,
										} }
									>
										{ categories.map( ( term ) => (
											<option key={ term.id } value={ String( term.id ) }>
												{ term.name }
											</option>
										) ) }
									</select>
									{ selectedTermIds.length > 0 && (
										<Button
											variant="link"
											isDestructive
											style={ { marginTop: 4, fontSize: 11 } }
											onClick={ () => setAttributes( { catTermsSelected: '' } ) }
										>
											{ __( 'Clear selection', 'carkeek-events' ) }
										</Button>
									) }
								</>
							) : (
								<p style={ { fontSize: 12, color: '#757575' } }>
									{ __( 'Loading categories…', 'carkeek-events' ) }
								</p>
							) }
						</div>
					</PanelRow>
				) }
			</PanelBody>

			{ /* Layout Panel */ }
			<PanelBody title={ __( 'Layout', 'carkeek-events' ) } initialOpen={ false }>
				<RadioControl
					label={ __( 'Post Layout', 'carkeek-events' ) }
					selected={ postLayout }
					options={ [
						{ label: __( 'Grid', 'carkeek-events' ), value: 'grid' },
						{ label: __( 'List', 'carkeek-events' ), value: 'list' },
					] }
					onChange={ ( value ) => setAttributes( { postLayout: value } ) }
				/>
				{ postLayout === 'grid' && (
					<>
						<RangeControl
							label={ __( 'Columns (Desktop)', 'carkeek-events' ) }
							value={ columns }
							onChange={ ( value ) => setAttributes( { columns: value } ) }
							min={ 1 }
							max={ 6 }
						/>
						<RangeControl
							label={ __( 'Columns (Tablet)', 'carkeek-events' ) }
							value={ columnsTablet }
							onChange={ ( value ) => setAttributes( { columnsTablet: value } ) }
							min={ 1 }
							max={ 4 }
						/>
						<RangeControl
							label={ __( 'Columns (Mobile)', 'carkeek-events' ) }
							value={ columnsMobile }
							onChange={ ( value ) => setAttributes( { columnsMobile: value } ) }
							min={ 1 }
							max={ 2 }
						/>
					</>
				) }
				<ToggleControl
					label={ __( 'Show Pagination', 'carkeek-events' ) }
					checked={ showPagination }
					onChange={ ( value ) => setAttributes( { showPagination: value } ) }
				/>
				<ToggleControl
					label={ __( 'Enable Load More Button', 'carkeek-events' ) }
					help={ __( 'Adds a button that loads the next batch of events via AJAX. Does not apply when "Show All" is on.', 'carkeek-events' ) }
					checked={ enableLoadMore }
					onChange={ ( value ) => setAttributes( { enableLoadMore: value } ) }
					__nextHasNoMarginBottom
				/>
				{ enableLoadMore && (
					<TextControl
						label={ __( 'Button Label', 'carkeek-events' ) }
						value={ loadMoreLabel }
						placeholder={ __( 'Load More', 'carkeek-events' ) }
						onChange={ ( value ) => setAttributes( { loadMoreLabel: value } ) }
						__nextHasNoMarginBottom
					/>
				) }
			</PanelBody>

			{ /* Content Panel */ }
			<PanelBody title={ __( 'Content', 'carkeek-events' ) } initialOpen={ false }>
				<TextControl
					label={ __( 'Headline', 'carkeek-events' ) }
					help={ __( 'Optional heading rendered above the events list as an h2.', 'carkeek-events' ) }
					value={ headline }
					onChange={ ( value ) => setAttributes( { headline: value } ) }
					__nextHasNoMarginBottom
				/>
				<hr style={ { margin: '12px 0' } } />
				<p style={ { marginTop: 0, fontSize: 12, color: '#757575' } }>
					{ __( 'Choose up to 5 content items. Output matches this order.', 'carkeek-events' ) }
				</p>

				{ Array.from( { length: visibleCount } ).map( ( _, index ) => (
					<Flex key={ index } align="center" style={ { marginBottom: 8 } }>
						<FlexBlock>
							<SelectControl
								label={ `${ __( 'Slot', 'carkeek-events' ) } ${ index + 1 }` }
								hideLabelFromVision={ index > 0 }
								value={ slots[ index ] || '' }
								options={ getSlotOptions( index ) }
								onChange={ ( value ) => updateSlot( index, value ) }
								__nextHasNoMarginBottom
							/>
						</FlexBlock>
						{ slots[ index ] && (
							<FlexItem style={ { paddingTop: index === 0 ? 20 : 0 } }>
								<Button
									isSmall
									isDestructive
									variant="tertiary"
									onClick={ () => updateSlot( index, '' ) }
									aria-label={ __( 'Remove slot', 'carkeek-events' ) }
								>
									✕
								</Button>
							</FlexItem>
						) }
					</Flex>
				) ) }

				<hr style={ { margin: '12px 0' } } />
				<ToggleControl
					label={ __( 'Display Featured Image', 'carkeek-events' ) }
					checked={ displayFeaturedImage }
					onChange={ ( value ) => setAttributes( { displayFeaturedImage: value } ) }
					__nextHasNoMarginBottom
				/>

				{ /* Date format options — shown when any date/time slot is active */ }
				{ hasDateSlot && (
					<>
						<hr style={ { margin: '12px 0' } } />
						<TextControl
							label={ __( 'Date Format', 'carkeek-events' ) }
							help={ __( 'PHP date format, e.g. M j, Y. Leave blank to use the plugin default.', 'carkeek-events' ) }
							value={ slotDateFormat }
							placeholder="M j, Y"
							onChange={ ( value ) => setAttributes( { slotDateFormat: value } ) }
							__nextHasNoMarginBottom
						/>
						<TextControl
							label={ __( 'Time Format', 'carkeek-events' ) }
							help={ __( 'PHP date format, e.g. g:i a. Leave blank to use the plugin default.', 'carkeek-events' ) }
							value={ slotTimeFormat }
							placeholder="g:i a"
							onChange={ ( value ) => setAttributes( { slotTimeFormat: value } ) }
							__nextHasNoMarginBottom
						/>
						<ToggleControl
							label={ __( 'Show End Date / Time', 'carkeek-events' ) }
							help={ __( 'When off, only the start date/time is shown.', 'carkeek-events' ) }
							checked={ showEndDateTime }
							onChange={ ( value ) => setAttributes( { showEndDateTime: value } ) }
						/>
					</>
				) }

				{ /* Excerpt length — shown when excerpt slot is active */ }
				{ hasExcerptSlot && (
					<>
						<hr style={ { margin: '12px 0' } } />
						<RangeControl
							label={ __( 'Excerpt Length (words)', 'carkeek-events' ) }
							value={ excerptLength }
							onChange={ ( value ) => setAttributes( { excerptLength: value } ) }
							min={ 10 }
							max={ 100 }
						/>
					</>
				) }
			</PanelBody>

			{ /* Behavior Panel */ }
			<PanelBody title={ __( 'Behavior', 'carkeek-events' ) } initialOpen={ false }>
				<ToggleControl
					label={ __( 'Hide Block When Empty', 'carkeek-events' ) }
					help={ __( 'If no events match, the block outputs nothing.', 'carkeek-events' ) }
					checked={ hideIfEmpty }
					onChange={ ( value ) => setAttributes( { hideIfEmpty: value } ) }
				/>
				{ ! hideIfEmpty && (
					<TextControl
						label={ __( 'Empty State Message', 'carkeek-events' ) }
						value={ emptyMessage }
						placeholder={ __( 'No upcoming events.', 'carkeek-events' ) }
						onChange={ ( value ) => setAttributes( { emptyMessage: value } ) }
					/>
				) }
			</PanelBody>

		</InspectorControls>
	);
};

export default EventsInspector;
