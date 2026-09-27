import { __ } from '@wordpress/i18n';
import {
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';

export default function SchemaPanel( { config, value, onChange } ) {
	const set = ( patch ) => onChange( { ...value, ...patch } );

	return (
		<div className="rmd-oh-schema">
			<p className="description">
				{ __(
					'Adds schema.org structured data (openingHoursSpecification) for search engines. Leave this off if your SEO plugin already describes your business, otherwise Google may see two businesses.',
					'rmd-opening-hours'
				) }
			</p>
			<ToggleControl
				label={ __(
					'Output structured data for this set',
					'rmd-opening-hours'
				) }
				checked={ !! value.enabled }
				onChange={ ( next ) => set( { enabled: next } ) }
				__nextHasNoMarginBottom
			/>
			{ value.enabled && (
				<div className="rmd-oh-grid rmd-oh-grid--3">
					<SelectControl
						label={ __( 'Business type', 'rmd-opening-hours' ) }
						value={ value.type }
						options={ config.schemaTypes.map( ( type ) => ( {
							value: type,
							label: type,
						} ) ) }
						onChange={ ( next ) => set( { type: next } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<TextControl
						label={ __( 'Business name', 'rmd-opening-hours' ) }
						help={ __(
							'Empty = site title.',
							'rmd-opening-hours'
						) }
						value={ value.name }
						onChange={ ( next ) => set( { name: next } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<TextControl
						label={ __( 'Website URL', 'rmd-opening-hours' ) }
						help={ __( 'Empty = home page.', 'rmd-opening-hours' ) }
						type="url"
						value={ value.url }
						onChange={ ( next ) => set( { url: next } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</div>
			) }
		</div>
	);
}
