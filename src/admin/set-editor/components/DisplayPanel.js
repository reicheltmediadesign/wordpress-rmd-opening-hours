import { __ } from '@wordpress/i18n';
import { SelectControl, ToggleControl } from '@wordpress/components';

export default function DisplayPanel( { config, value, onChange } ) {
	const set = ( patch ) => onChange( { ...value, ...patch } );
	const toOptions = ( map ) =>
		Object.entries( map ).map( ( [ v, label ] ) => ( {
			value: v,
			label,
		} ) );

	return (
		<div className="rmd-oh-display">
			<p className="description">
				{ __(
					'Defaults for this set. Every block and shortcode can override them.',
					'rmd-opening-hours'
				) }
			</p>
			<div className="rmd-oh-grid rmd-oh-grid--3">
				<SelectControl
					label={ __( 'Layout', 'rmd-opening-hours' ) }
					value={ value.layout }
					options={ toOptions( config.layouts ) }
					onChange={ ( next ) => set( { layout: next } ) }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
				<SelectControl
					label={ __( 'Time format', 'rmd-opening-hours' ) }
					value={ value.time_style }
					options={ toOptions( config.timeStyles ) }
					onChange={ ( next ) => set( { time_style: next } ) }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
				<SelectControl
					label={ __( 'Week shown', 'rmd-opening-hours' ) }
					value={ value.week_mode }
					options={ [
						{
							value: 'current',
							label: __(
								'Current week (with holidays and periods)',
								'rmd-opening-hours'
							),
						},
						{
							value: 'regular',
							label: __(
								'Regular hours only',
								'rmd-opening-hours'
							),
						},
					] }
					onChange={ ( next ) => set( { week_mode: next } ) }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</div>
			<div className="rmd-oh-grid rmd-oh-grid--3">
				<ToggleControl
					label={ __(
						'Group days with equal hours (Mon–Fri)',
						'rmd-opening-hours'
					) }
					checked={ !! value.group_days }
					onChange={ ( next ) => set( { group_days: next } ) }
					__nextHasNoMarginBottom
				/>
				<ToggleControl
					label={ __( 'Highlight today', 'rmd-opening-hours' ) }
					checked={ !! value.highlight_today }
					onChange={ ( next ) => set( { highlight_today: next } ) }
					__nextHasNoMarginBottom
				/>
				<ToggleControl
					label={ __( 'Show notes', 'rmd-opening-hours' ) }
					checked={ !! value.show_notes }
					onChange={ ( next ) => set( { show_notes: next } ) }
					__nextHasNoMarginBottom
				/>
			</div>
		</div>
	);
}
