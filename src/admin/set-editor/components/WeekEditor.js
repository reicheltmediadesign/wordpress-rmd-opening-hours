import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

import { WEEKDAYS } from '../model';
import DayEditor from './DayEditor';
import ImportDialog from './ImportDialog';

/**
 * Seven DayEditors in a grid with a "copy to following days" shortcut and an
 * optional text import.
 *
 * @param {Object}   props
 * @param {Object}   props.config
 * @param {Object}   props.week
 * @param {Function} props.onChange
 * @param {boolean}  props.compact
 * @param {Function} props.onImport Receives the parser result; enables the import button.
 */
export default function WeekEditor( {
	config,
	week,
	onChange,
	compact = false,
	onImport = null,
} ) {
	const copyDown = ( fromKey ) => {
		const fromIndex = WEEKDAYS.indexOf( fromKey );
		const source = week[ fromKey ];
		const next = { ...week };
		WEEKDAYS.slice( fromIndex + 1, 5 ).forEach( ( key ) => {
			next[ key ] = JSON.parse( JSON.stringify( source ) );
		} );
		onChange( next );
	};

	return (
		<div className="rmd-oh-week">
			{ onImport && (
				<div className="rmd-oh-week__toolbar">
					<ImportDialog config={ config } onApply={ onImport } />
				</div>
			) }
			{ WEEKDAYS.map( ( key ) => (
				<div className="rmd-oh-week__row" key={ key }>
					<div className="rmd-oh-week__label">
						<strong>{ config.weekdays[ key ]?.long || key }</strong>
						{ key === 'mon' && (
							<Button
								className="rmd-oh-week__copy"
								variant="link"
								size="small"
								onClick={ () => copyDown( key ) }
							>
								{ __( 'Copy to Tue–Fri', 'rmd-opening-hours' ) }
							</Button>
						) }
					</div>
					<DayEditor
						config={ config }
						value={ week[ key ] }
						onChange={ ( day ) =>
							onChange( { ...week, [ key ]: day } )
						}
						compact={ compact }
					/>
				</div>
			) ) }
		</div>
	);
}
