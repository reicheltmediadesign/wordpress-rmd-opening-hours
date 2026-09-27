import { __ } from '@wordpress/i18n';
import { CheckboxControl, SelectControl } from '@wordpress/components';

import DayEditor from './DayEditor';

const DEFAULT_MODES = [
	{ value: 'closed', label: __( 'Closed', 'rmd-opening-hours' ) },
	{ value: 'regular', label: __( 'Regular hours', 'rmd-opening-hours' ) },
	{ value: 'custom', label: __( 'Special hours', 'rmd-opening-hours' ) },
];

const RULE_MODES = [
	{ value: 'default', label: __( 'As all holidays', 'rmd-opening-hours' ) },
	...DEFAULT_MODES,
];

const EMPTY_DAY = { mode: 'open', slots: [], text: '', note: '' };

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
	const defaultRule = value.default || { mode: 'closed', day: EMPTY_DAY };

	const ruleFor = ( id ) =>
		value.rules[ id ] || { mode: 'default', day: EMPTY_DAY };
	const setRule = ( id, patch ) => {
		const rules = {
			...value.rules,
			[ id ]: { ...ruleFor( id ), ...patch },
		};
		if ( rules[ id ].mode === 'default' ) {
			delete rules[ id ];
		}
		set( { rules } );
	};

	return (
		<div className="rmd-oh-holidays">
			<p className="description">
				{ __(
					'Public holidays are calculated automatically. Set the rule for all holidays first, then adjust single holidays if needed. The rule for all holidays is also shown as the “Public holidays” row below Sunday.',
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

			<div className="rmd-oh-holidays__default">
				<SelectControl
					label={ __(
						'On public holidays we are',
						'rmd-opening-hours'
					) }
					value={ defaultRule.mode }
					options={ DEFAULT_MODES }
					onChange={ ( mode ) =>
						set( { default: { ...defaultRule, mode } } )
					}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
				{ defaultRule.mode === 'custom' && (
					<DayEditor
						config={ config }
						value={ defaultRule.day || EMPTY_DAY }
						onChange={ ( day ) =>
							set( { default: { ...defaultRule, day } } )
						}
						compact
					/>
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
