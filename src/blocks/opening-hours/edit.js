import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

import { SetSelect, TriStateControl } from '../shared/controls';

const LAYOUTS = [
	{ value: '', label: __( 'Set default', 'rmd-opening-hours' ) },
	{ value: 'table', label: __( 'Table', 'rmd-opening-hours' ) },
	{ value: 'list', label: __( 'List', 'rmd-opening-hours' ) },
	{
		value: 'compact',
		label: __( 'Compact (one line)', 'rmd-opening-hours' ),
	},
	{
		value: 'paragraphs',
		label: __( 'Paragraphs (written out)', 'rmd-opening-hours' ),
	},
];

const TIME_STYLES = [
	{ value: '', label: __( 'Set default', 'rmd-opening-hours' ) },
	{ value: '24h', label: __( '24-hour (09:00–18:00)', 'rmd-opening-hours' ) },
	{
		value: '24h-suffix',
		label: __( '24-hour with unit (08:00 – 12:00 h)', 'rmd-opening-hours' ),
	},
	{
		value: '24h-short',
		label: __( '24-hour, short (9–18)', 'rmd-opening-hours' ),
	},
	{
		value: '12h',
		label: __( '12-hour (9:00 am–6:00 pm)', 'rmd-opening-hours' ),
	},
];

const DAY_NAMES = [
	{ value: '', label: __( 'Set default', 'rmd-opening-hours' ) },
	{
		value: 'auto',
		label: __( 'By layout (full for paragraphs)', 'rmd-opening-hours' ),
	},
	{ value: 'short', label: __( 'Abbreviated (Mon)', 'rmd-opening-hours' ) },
	{ value: 'long', label: __( 'Full (Monday)', 'rmd-opening-hours' ) },
];

const WEEK_MODES = [
	{ value: '', label: __( 'Set default', 'rmd-opening-hours' ) },
	{
		value: 'current',
		label: __(
			'Current week (with holidays and periods)',
			'rmd-opening-hours'
		),
	},
	{
		value: 'regular',
		label: __( 'Regular hours only', 'rmd-opening-hours' ),
	},
];

export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps( { className: 'rmd-oh-block-preview' } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Opening hours', 'rmd-opening-hours' ) }>
					<SetSelect
						value={ attributes.setId }
						onChange={ ( setId ) => setAttributes( { setId } ) }
					/>
					<ToggleControl
						label={ __(
							'Show set name as heading',
							'rmd-opening-hours'
						) }
						checked={ attributes.showTitle }
						onChange={ ( showTitle ) =>
							setAttributes( { showTitle } )
						}
						__nextHasNoMarginBottom
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Layout', 'rmd-opening-hours' ) }
					initialOpen={ false }
				>
					<SelectControl
						label={ __( 'Layout', 'rmd-opening-hours' ) }
						value={ attributes.layout }
						options={ LAYOUTS }
						onChange={ ( layout ) => setAttributes( { layout } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<SelectControl
						label={ __( 'Week', 'rmd-opening-hours' ) }
						value={ attributes.weekMode }
						options={ WEEK_MODES }
						onChange={ ( weekMode ) =>
							setAttributes( { weekMode } )
						}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<SelectControl
						label={ __( 'Time format', 'rmd-opening-hours' ) }
						value={ attributes.timeStyle }
						options={ TIME_STYLES }
						onChange={ ( timeStyle ) =>
							setAttributes( { timeStyle } )
						}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<SelectControl
						label={ __( 'Day names', 'rmd-opening-hours' ) }
						value={ attributes.dayNames }
						options={ DAY_NAMES }
						onChange={ ( dayNames ) =>
							setAttributes( { dayNames } )
						}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<TriStateControl
						label={ __(
							'Group days with equal hours (Mon–Fri)',
							'rmd-opening-hours'
						) }
						value={ attributes.groupDays }
						onChange={ ( groupDays ) =>
							setAttributes( { groupDays } )
						}
					/>
					<TriStateControl
						label={ __( 'Highlight today', 'rmd-opening-hours' ) }
						value={ attributes.highlightToday }
						onChange={ ( highlightToday ) =>
							setAttributes( { highlightToday } )
						}
					/>
					<TriStateControl
						label={ __( 'Show notes', 'rmd-opening-hours' ) }
						value={ attributes.showNotes }
						onChange={ ( showNotes ) =>
							setAttributes( { showNotes } )
						}
					/>
					<TriStateControl
						label={ __(
							'Show “Public holidays” row',
							'rmd-opening-hours'
						) }
						value={ attributes.showHolidays }
						onChange={ ( showHolidays ) =>
							setAttributes( { showHolidays } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<ServerSideRender
					block="rmd-opening-hours/opening-hours"
					attributes={ { ...attributes, isPreview: true } }
					skipBlockSupportAttributes
				/>
			</div>
		</>
	);
}
