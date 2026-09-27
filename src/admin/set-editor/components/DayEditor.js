import { __, sprintf } from '@wordpress/i18n';
import { Button, SelectControl, TextControl } from '@wordpress/components';
import { closeSmall, plus } from '@wordpress/icons';

import { isValidTime, normalizeTime } from '../model';

const MODES = [
	{ value: 'closed', label: __( 'Closed', 'rmd-opening-hours' ) },
	{ value: 'open', label: __( 'Open', 'rmd-opening-hours' ) },
	{
		value: 'text',
		label: __( 'Text (e.g. by appointment)', 'rmd-opening-hours' ),
	},
];

function TimeField( { value, onChange, label, allowMidnight } ) {
	const invalid = value !== '' && ! isValidTime( value, allowMidnight );
	return (
		<TextControl
			className={ invalid ? 'rmd-oh-time is-invalid' : 'rmd-oh-time' }
			label={ label }
			hideLabelFromVision
			value={ value }
			placeholder="HH:MM"
			inputMode="numeric"
			maxLength={ 5 }
			onChange={ onChange }
			onBlur={ () => onChange( normalizeTime( value ) ) }
			__nextHasNoMarginBottom
			__next40pxDefaultSize
		/>
	);
}

/**
 * Editor for one DaySpec (mode, slots, text, note).
 *
 * @param {Object}   props
 * @param {Object}   props.config
 * @param {Object}   props.value
 * @param {Function} props.onChange
 * @param {boolean}  props.compact  Hide the note field.
 */
export default function DayEditor( {
	config,
	value,
	onChange,
	compact = false,
} ) {
	const set = ( patch ) => onChange( { ...value, ...patch } );
	const slots = value.slots || [];

	const updateSlot = ( index, patch ) => {
		const next = slots.map( ( slot, i ) =>
			i === index ? { ...slot, ...patch } : slot
		);
		set( { slots: next } );
	};

	const addSlot = () => {
		const last = slots[ slots.length - 1 ];
		const suggestion = last
			? { start: last.end === '24:00' ? '' : last.end, end: '' }
			: { start: '09:00', end: '18:00' };
		set( { slots: [ ...slots, suggestion ] } );
	};

	return (
		<div className="rmd-oh-day">
			<SelectControl
				className="rmd-oh-day__mode"
				label={ __( 'Mode', 'rmd-opening-hours' ) }
				hideLabelFromVision
				value={ value.mode }
				options={ MODES }
				onChange={ ( mode ) => {
					const patch = { mode };
					if ( mode === 'open' && slots.length === 0 ) {
						patch.slots = [ { start: '09:00', end: '18:00' } ];
					}
					set( patch );
				} }
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>

			{ value.mode === 'open' && (
				<div className="rmd-oh-day__slots">
					{ slots.map( ( slot, index ) => (
						<div className="rmd-oh-slot" key={ index }>
							<TimeField
								label={ sprintf(
									/* translators: %d: slot number */ __(
										'Opens (slot %d)',
										'rmd-opening-hours'
									),
									index + 1
								) }
								value={ slot.start }
								onChange={ ( start ) =>
									updateSlot( index, { start } )
								}
							/>
							<span
								className="rmd-oh-slot__dash"
								aria-hidden="true"
							>
								–
							</span>
							<TimeField
								label={ sprintf(
									/* translators: %d: slot number */ __(
										'Closes (slot %d)',
										'rmd-opening-hours'
									),
									index + 1
								) }
								value={ slot.end }
								onChange={ ( end ) =>
									updateSlot( index, { end } )
								}
								allowMidnight
							/>
							<Button
								icon={ closeSmall }
								label={ __(
									'Remove slot',
									'rmd-opening-hours'
								) }
								size="small"
								onClick={ () =>
									set( {
										slots: slots.filter(
											( _, i ) => i !== index
										),
									} )
								}
							/>
						</div>
					) ) }
					{ slots.length < config.limits.slots && (
						<Button
							className="rmd-oh-day__add"
							icon={ plus }
							variant="tertiary"
							size="small"
							onClick={ addSlot }
						>
							{ __( 'Add slot', 'rmd-opening-hours' ) }
						</Button>
					) }
				</div>
			) }

			{ value.mode === 'text' && (
				<TextControl
					className="rmd-oh-day__text"
					label={ __( 'Text', 'rmd-opening-hours' ) }
					hideLabelFromVision
					placeholder={ __(
						'e.g. by appointment',
						'rmd-opening-hours'
					) }
					value={ value.text }
					maxLength={ config.limits.text }
					onChange={ ( text ) => set( { text } ) }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			) }

			{ ! compact && (
				<TextControl
					className="rmd-oh-day__note"
					label={ __( 'Note', 'rmd-opening-hours' ) }
					hideLabelFromVision
					placeholder={ __( 'Note (optional)', 'rmd-opening-hours' ) }
					value={ value.note }
					maxLength={ config.limits.note }
					onChange={ ( note ) => set( { note } ) }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			) }
		</div>
	);
}
