import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

import { SetSelect } from '../shared/controls';

export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps( { className: 'rmd-oh-block-preview' } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Status', 'rmd-opening-hours' ) }>
					<SetSelect
						value={ attributes.setId }
						onChange={ ( setId ) => setAttributes( { setId } ) }
					/>
					<ToggleControl
						label={ __(
							'Show next opening or closing time',
							'rmd-opening-hours'
						) }
						checked={ attributes.showNext }
						onChange={ ( showNext ) =>
							setAttributes( { showNext } )
						}
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<ServerSideRender
					block="rmd-opening-hours/status"
					attributes={ { ...attributes, isPreview: true } }
					skipBlockSupportAttributes
				/>
			</div>
		</>
	);
}
