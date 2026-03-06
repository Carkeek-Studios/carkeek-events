import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	PanelRow,
	RangeControl,
	SelectControl,
	ToggleControl,
	TextControl,
	CheckboxControl,
	RadioControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import icons from './icons';

const EventsInspector = ( { attributes, setAttributes } ) => {
	const {
		numberOfPosts,
		postLayout,
		columns,
		columnsMobile,
		columnsTablet,
		displayFeaturedImage,
		displayPostExcerpt,
		excerptLength,
		showPagination,
		includePastEvents,
		onlyPastEvents,
		sortOrder,
		filterByCategory,
		catTermsSelected,
		hideIfEmpty,
		emptyMessage,
	} = attributes;

	// Fetch event categories for the filter.
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

	const toggleCategory = ( termId, checked ) => {
		const next = checked
			? [ ...selectedTermIds, termId ]
			: selectedTermIds.filter( ( id ) => id !== termId );
		setAttributes( { catTermsSelected: next.join( ',' ) } );
	};

	return (
		<InspectorControls>
			{ /* Events Panel */ }
			<PanelBody title={ __( 'Events', 'carkeek-events' ) } initialOpen={ true }>
				<RangeControl
					label={ __( 'Number of Events', 'carkeek-events' ) }
					value={ numberOfPosts }
					onChange={ ( value ) => setAttributes( { numberOfPosts: value } ) }
					min={ 1 }
					max={ 50 }
				/>
				<SelectControl
					label={ __( 'Sort Order', 'carkeek-events' ) }
					value={ sortOrder }
					options={ [
						{ label: __( 'Upcoming first (ASC)', 'carkeek-events' ), value: 'ASC' },
						{ label: __( 'Latest first (DESC)', 'carkeek-events' ), value: 'DESC' },
					] }
					onChange={ ( value ) => setAttributes( { sortOrder: value } ) }
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
				/>
				{ filterByCategory && categories && (
					<PanelRow>
						<fieldset style={ { width: '100%' } }>
							<legend>{ __( 'Categories', 'carkeek-events' ) }</legend>
							{ categories.map( ( term ) => (
								<CheckboxControl
									key={ term.id }
									label={ term.name }
									checked={ selectedTermIds.includes( term.id ) }
									onChange={ ( checked ) => toggleCategory( term.id, checked ) }
								/>
							) ) }
						</fieldset>
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
					label={ __( 'Display Featured Image', 'carkeek-events' ) }
					checked={ displayFeaturedImage }
					onChange={ ( value ) => setAttributes( { displayFeaturedImage: value } ) }
				/>
				<ToggleControl
					label={ __( 'Display Excerpt', 'carkeek-events' ) }
					checked={ displayPostExcerpt }
					onChange={ ( value ) => setAttributes( { displayPostExcerpt: value } ) }
				/>
				{ displayPostExcerpt && (
					<RangeControl
						label={ __( 'Excerpt Length (words)', 'carkeek-events' ) }
						value={ excerptLength }
						onChange={ ( value ) => setAttributes( { excerptLength: value } ) }
						min={ 10 }
						max={ 100 }
					/>
				) }
				<ToggleControl
					label={ __( 'Show Pagination', 'carkeek-events' ) }
					checked={ showPagination }
					onChange={ ( value ) => setAttributes( { showPagination: value } ) }
				/>
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
