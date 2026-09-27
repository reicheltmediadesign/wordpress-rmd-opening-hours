import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { Button, Modal, Notice, TextareaControl } from '@wordpress/components';
import { upload } from '@wordpress/icons';

import { WEEKDAYS } from '../model';
import { parseOpeningHours } from '../parser';

function describe( spec ) {
	if ( ! spec ) {
		return '';
	}
	if ( spec.mode === 'open' ) {
		const slots = spec.slots
			.map( ( s ) => `${ s.start }–${ s.end }` )
			.join( ', ' );
		return spec.note ? `${ slots } (${ spec.note })` : slots;
	}
	if ( spec.mode === 'text' ) {
		return spec.text;
	}
	return __( 'closed', 'rmd-opening-hours' );
}

/**
 * "Import from text" button with a modal: paste → analyse → preview → apply.
 *
 * @param {Object}   props
 * @param {Object}   props.config
 * @param {Function} props.onApply Receives { week, holidayDefault }.
 */
export default function ImportDialog( { config, onApply } ) {
	const [ open, setOpen ] = useState( false );
	const [ text, setText ] = useState( '' );
	const [ result, setResult ] = useState( null );

	const close = () => {
		setOpen( false );
		setResult( null );
	};

	const analyse = () => setResult( parseOpeningHours( text ) );

	const apply = () => {
		onApply( { week: result.week, holidayDefault: result.holidayDefault } );
		close();
	};

	return (
		<>
			<Button
				icon={ upload }
				variant="secondary"
				size="small"
				onClick={ () => setOpen( true ) }
			>
				{ __( 'Import from text…', 'rmd-opening-hours' ) }
			</Button>
			{ open && (
				<Modal
					title={ __(
						'Import opening hours from text',
						'rmd-opening-hours'
					) }
					onRequestClose={ close }
					className="rmd-oh-import"
					size="large"
				>
					<p className="description">
						{ __(
							'Copy the opening hours from your old website (a table, a list or a sentence like “Dienstag, Donnerstag: 7 – 16 Uhr”) and paste them here. Days that are not mentioned will be set to closed.',
							'rmd-opening-hours'
						) }
					</p>
					<TextareaControl
						label={ __( 'Text', 'rmd-opening-hours' ) }
						hideLabelFromVision
						value={ text }
						onChange={ setText }
						rows={ 8 }
						placeholder={
							'Mo–Fr 08:00–12:00, 14:00–18:00\nSa 9–12 Uhr\nSo geschlossen\nFeiertage geschlossen'
						}
						__nextHasNoMarginBottom
					/>
					<div className="rmd-oh-import__actions">
						<Button
							variant="secondary"
							onClick={ analyse }
							disabled={ text.trim() === '' }
						>
							{ __( 'Analyse', 'rmd-opening-hours' ) }
						</Button>
					</div>

					{ result && ! result.found && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'No opening hours were recognised. Please check that the text contains weekday names and times.',
								'rmd-opening-hours'
							) }
						</Notice>
					) }

					{ result && result.found && (
						<>
							<table className="widefat striped rmd-oh-import__preview">
								<tbody>
									{ WEEKDAYS.map( ( day ) => (
										<tr key={ day }>
											<th scope="row">
												{ config.weekdays[ day ]
													?.long || day }
											</th>
											<td>
												{ describe(
													result.week[ day ]
												) }
											</td>
										</tr>
									) ) }
									{ result.holidayDefault && (
										<tr>
											<th scope="row">
												{ __(
													'Public holidays',
													'rmd-opening-hours'
												) }
											</th>
											<td>
												{ describe(
													result.holidayDefault
												) }
											</td>
										</tr>
									) }
								</tbody>
							</table>
							{ result.skipped.length > 0 && (
								<p className="description">
									{ sprintf(
										/* translators: %s: list of ignored text snippets */
										__(
											'Ignored: %s',
											'rmd-opening-hours'
										),
										result.skipped.join( ' · ' )
									) }
								</p>
							) }
							<div className="rmd-oh-import__actions">
								<Button variant="primary" onClick={ apply }>
									{ __(
										'Apply to this week',
										'rmd-opening-hours'
									) }
								</Button>
								<Button variant="tertiary" onClick={ close }>
									{ __( 'Cancel', 'rmd-opening-hours' ) }
								</Button>
							</div>
						</>
					) }
				</Modal>
			) }
		</>
	);
}
