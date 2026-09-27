import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	TextareaControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

import { SetSelect, TriStateControl } from '../shared/controls';

export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps( { className: 'rmd-oh-block-preview' } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Notice', 'rmd-opening-hours' ) }>
					<SetSelect
						value={ attributes.setId }
						onChange={ ( setId ) => setAttributes( { setId } ) }
						allowAll
					/>
					<RangeControl
						label={ __( 'Lead time (days)', 'rmd-opening-hours' ) }
						help={ __(
							'0 uses the plugin setting. Periods can override this individually.',
							'rmd-opening-hours'
						) }
						value={ attributes.leadDays }
						onChange={ ( leadDays ) =>
							setAttributes( { leadDays: leadDays || 0 } )
						}
						min={ 0 }
						max={ 90 }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<TriStateControl
						label={ __(
							'Visitors can dismiss the notice',
							'rmd-opening-hours'
						) }
						value={ attributes.dismissible }
						onChange={ ( dismissible ) =>
							setAttributes( { dismissible } )
						}
					/>
					<TextareaControl
						label={ __( 'Text template', 'rmd-opening-hours' ) }
						help={ __(
							'Leave empty to use the plugin setting. Placeholders: {name}, {start}, {end}, {hours}, {note}, {set}.',
							'rmd-opening-hours'
						) }
						value={ attributes.template }
						onChange={ ( template ) =>
							setAttributes( { template } )
						}
						rows={ 3 }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<ServerSideRender
					block="rmd-opening-hours/notice"
					attributes={ { ...attributes, isPreview: true } }
					skipBlockSupportAttributes
				/>
			</div>
		</>
	);
}
