import { __ } from '@wordpress/i18n';
import { CheckboxControl, SelectControl } from '@wordpress/components';

import DayEditor from './DayEditor';

const RULE_MODES = [
	{ value: 'closed', label: __( 'Closed', 'rmd-opening-hours' ) },
	{ value: 'regular', label: __( 'Regular hours', 'rmd-opening-hours' ) },
	{ value: 'custom', label: __( 'Special hours', 'rmd-opening-hours' ) },
];

function formatDate( ymd ) {
	if ( ! ymd ) {
		return '';
	}
	const [ , m, d ] = ymd.split( '-' );
	return `${ d }.${ m }.`;
}

export default function HolidaysEditor( { config, value, onChange } ) {
	const set = ( patch ) => onChange( { ...value, ...patch } );
	const state = value.state;
	const regionalOptions = config.holidays.filter( ( h ) =>
		h.regional.includes( state )
	);
	const applicable = config.holidays.filter(
		( h ) =>
			h.states.includes( state ) ||
			( h.regional.includes( state ) && value.regional.includes( h.id ) )
	);
	const years = Object.keys( config.holidays[ 0 ]?.dates || {} );

	const ruleFor = ( id ) =>
		value.rules[ id ] || {
			mode: 'closed',
			day: { mode: 'open', slots: [], text: '', note: '' },
		};
	const setRule = ( id, patch ) => {
		const rules = {
			...value.rules,
			[ id ]: { ...ruleFor( id ), ...patch },
		};
		if ( rules[ id ].mode === 'closed' ) {
			delete rules[ id ];
		}
		set( { rules } );
	};

	return (
		<div className="rmd-oh-holidays">
			<p className="description">
				{ __(
					'Public holidays are calculated automatically. Decide per holiday whether you are closed, keep your regular hours or open with special hours.',
					'rmd-opening-hours'
				) }
			</p>

			<div className="rmd-oh-grid rmd-oh-grid--2">
				<SelectControl
					label={ __( 'Federal state', 'rmd-opening-hours' ) }
					value={ state }
					options={ Object.entries( config.states ).map(
						( [ code, label ] ) => ( { value: code, label } )
					) }
					onChange={ ( next ) =>
						set( { state: next, regional: [] } )
					}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
				{ regionalOptions.length > 0 && (
					<fieldset className="rmd-oh-regional">
						<legend>
							{ __(
								'Regional holidays that apply to you',
								'rmd-opening-hours'
							) }
						</legend>
						{ regionalOptions.map( ( holiday ) => (
							<CheckboxControl
								key={ holiday.id }
								label={ holiday.label }
								checked={ value.regional.includes(
									holiday.id
								) }
								onChange={ ( checked ) =>
									set( {
										regional: checked
											? [ ...value.regional, holiday.id ]
											: value.regional.filter(
													( id ) => id !== holiday.id
												),
									} )
								}
								__nextHasNoMarginBottom
							/>
						) ) }
					</fieldset>
				) }
			</div>

			<table className="widefat striped rmd-oh-holidays__table">
				<thead>
					<tr>
						<th>{ __( 'Holiday', 'rmd-opening-hours' ) }</th>
						{ years.map( ( year ) => (
							<th key={ year }>{ year }</th>
						) ) }
						<th>{ __( 'Rule', 'rmd-opening-hours' ) }</th>
						<th>{ __( 'Special hours', 'rmd-opening-hours' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ applicable.map( ( holiday ) => {
						const rule = ruleFor( holiday.id );
						return (
							<tr key={ holiday.id }>
								<td>{ holiday.label }</td>
								{ years.map( ( year ) => (
									<td key={ year }>
										{ formatDate( holiday.dates[ year ] ) }
									</td>
								) ) }
								<td>
									<SelectControl
										label={ __(
											'Rule',
											'rmd-opening-hours'
										) }
										hideLabelFromVision
										value={ rule.mode }
										options={ RULE_MODES }
										onChange={ ( mode ) =>
											setRule( holiday.id, { mode } )
										}
										__nextHasNoMarginBottom
										__next40pxDefaultSize
									/>
								</td>
								<td>
									{ rule.mode === 'custom' && (
										<DayEditor
											config={ config }
											value={ rule.day }
											onChange={ ( day ) =>
												setRule( holiday.id, { day } )
											}
											compact
										/>
									) }
								</td>
							</tr>
						);
					} ) }
				</tbody>
			</table>
		</div>
	);
}
