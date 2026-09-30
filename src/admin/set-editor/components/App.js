import { __ } from '@wordpress/i18n';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { TabPanel } from '@wordpress/components';

import { normalizeSet } from '../model';
import WeekEditor from './WeekEditor';
import PeriodsEditor from './PeriodsEditor';
import HolidaysEditor from './HolidaysEditor';
import DisplayPanel from './DisplayPanel';
import SchemaPanel from './SchemaPanel';

export default function App( { config, initial, onChange } ) {
	const [ data, setData ] = useState( () => normalizeSet( initial, config ) );
	// Periods as last saved: date errors, order and the past filter refer to
	// this snapshot so rows do not complain, jump or vanish while typing.
	const [ savedPeriods ] = useState( () => data.periods );

	useEffect( () => {
		onChange( data );
	}, [ data, onChange ] );

	const update = ( key ) => ( value ) =>
		setData( ( prev ) => ( { ...prev, [ key ]: value } ) );

	const tabs = useMemo(
		() => [
			{
				name: 'regular',
				title: __( 'Regular hours', 'rmd-opening-hours' ),
			},
			{
				name: 'periods',
				title:
					data.periods.length > 0
						? `${ __( 'Periods', 'rmd-opening-hours' ) } (${ data.periods.length })`
						: __( 'Periods', 'rmd-opening-hours' ),
			},
			{ name: 'holidays', title: __( 'Holidays', 'rmd-opening-hours' ) },
			{ name: 'display', title: __( 'Display', 'rmd-opening-hours' ) },
			{
				name: 'schema',
				title: __( 'Structured data', 'rmd-opening-hours' ),
			},
		],
		[ data.periods.length ]
	);

	return (
		<TabPanel className="rmd-oh-tabs" tabs={ tabs }>
			{ ( tab ) => {
				switch ( tab.name ) {
					case 'periods':
						return (
							<PeriodsEditor
								config={ config }
								periods={ data.periods }
								savedPeriods={ savedPeriods }
								onChange={ update( 'periods' ) }
							/>
						);
					case 'holidays':
						return (
							<HolidaysEditor
								config={ config }
								value={ data.holidays }
								onChange={ update( 'holidays' ) }
							/>
						);
					case 'display':
						return (
							<DisplayPanel
								config={ config }
								value={ data.display }
								onChange={ update( 'display' ) }
							/>
						);
					case 'schema':
						return (
							<SchemaPanel
								config={ config }
								value={ data.schema }
								onChange={ update( 'schema' ) }
							/>
						);
					default:
						return (
							<>
								<p className="description">
									{ __(
										'The weekly hours that apply unless a period or holiday says otherwise. Add several time slots for breaks (e.g. 08:00–12:00 and 14:00–18:00). An end time earlier than the start means “until the next morning”.',
										'rmd-opening-hours'
									) }
								</p>
								<WeekEditor
									config={ config }
									week={ data.regular }
									onChange={ update( 'regular' ) }
									onImport={ ( { week, holidayDefault } ) =>
										setData( ( prev ) => ( {
											...prev,
											regular: week,
											holidays: holidayDefault
												? {
														...prev.holidays,
														default: holidayDefault,
													}
												: prev.holidays,
										} ) )
									}
								/>
							</>
						);
				}
			} }
		</TabPanel>
	);
}
