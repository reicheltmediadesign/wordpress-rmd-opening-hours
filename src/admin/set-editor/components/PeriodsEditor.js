import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import {
	Button,
	CheckboxControl,
	Notice,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { chevronDown, chevronUp, plus, trash } from '@wordpress/icons';

import { isValidDate, newPeriod, periodStatus } from '../model';
import DayEditor from './DayEditor';
import WeekEditor from './WeekEditor';

const MODES = [
	{ value: 'closed', label: __( 'Closed', 'rmd-opening-hours' ) },
	{
		value: 'daily',
		label: __( 'Same hours every day', 'rmd-opening-hours' ),
	},
	{
		value: 'weekly',
		label: __( 'Different weekly hours', 'rmd-opening-hours' ),
	},
];

const STATUS_LABELS = {
	past: __( 'past', 'rmd-opening-hours' ),
	running: __( 'running', 'rmd-opening-hours' ),
	upcoming: __( 'upcoming', 'rmd-opening-hours' ),
	recurring: __( 'yearly', 'rmd-opening-hours' ),
};

function formatDate( ymd ) {
	if ( ! isValidDate( ymd ) ) {
		return ymd || '–';
	}
	const [ y, m, d ] = ymd.split( '-' );
	return `${ d }.${ m }.${ y }`;
}

function dateErrorFor( period ) {
	if ( ! isValidDate( period.start ) || ! isValidDate( period.end ) ) {
		return __( 'Please enter valid dates.', 'rmd-opening-hours' );
	}
	if ( ! period.recurring && period.end < period.start ) {
		return __(
			'The end date is before the start date.',
			'rmd-opening-hours'
		);
	}
	return '';
}

function PeriodRow( { config, period, onChange, onRemove } ) {
	const [ open, setOpen ] = useState( period.name === '' );
	const set = ( patch ) => onChange( { ...period, ...patch } );
	const status = periodStatus( period, config.today );
	const dateError = dateErrorFor( period );

	return (
		<div
			className={ `rmd-oh-period is-${ status }${ open ? ' is-open' : '' }` }
		>
			<div className="rmd-oh-period__summary">
				<button
					type="button"
					className="rmd-oh-period__toggle"
					onClick={ () => setOpen( ! open ) }
					aria-expanded={ open }
				>
					<span
						className={ `rmd-oh-badge rmd-oh-badge--${ status }` }
					>
						{ STATUS_LABELS[ status ] }
					</span>
					<strong>
						{ period.name ||
							__( '(unnamed period)', 'rmd-opening-hours' ) }
					</strong>
					<span className="rmd-oh-period__dates">
						{ formatDate( period.start ) } –{ ' ' }
						{ formatDate( period.end ) }
						{ period.recurring &&
							' · ' + __( 'every year', 'rmd-opening-hours' ) }
					</span>
					<span className="rmd-oh-period__mode">
						{
							MODES.find( ( m ) => m.value === period.mode )
								?.label
						}
					</span>
				</button>
				<Button
					icon={ open ? chevronUp : chevronDown }
					label={
						open
							? __( 'Collapse', 'rmd-opening-hours' )
							: __( 'Expand', 'rmd-opening-hours' )
					}
					onClick={ () => setOpen( ! open ) }
				/>
				<Button
					icon={ trash }
					label={ __( 'Remove period', 'rmd-opening-hours' ) }
					isDestructive
					onClick={ () => {
						if (
							// eslint-disable-next-line no-alert
							window.confirm(
								__( 'Remove this period?', 'rmd-opening-hours' )
							)
						) {
							onRemove();
						}
					} }
				/>
			</div>

			{ open && (
				<div className="rmd-oh-period__body">
					<div className="rmd-oh-grid rmd-oh-grid--3">
						<TextControl
							label={ __( 'Name', 'rmd-opening-hours' ) }
							help={ __(
								'Shown in the notice, e.g. “Summer break”.',
								'rmd-opening-hours'
							) }
							value={ period.name }
							maxLength={ config.limits.name }
							onChange={ ( value ) => set( { name: value } ) }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
						<TextControl
							label={ __( 'From', 'rmd-opening-hours' ) }
							type="date"
							value={ period.start }
							onChange={ ( value ) => set( { start: value } ) }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
						<TextControl
							label={ __(
								'Until (inclusive)',
								'rmd-opening-hours'
							) }
							type="date"
							value={ period.end }
							onChange={ ( value ) => set( { end: value } ) }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					</div>
					{ dateError && (
						<Notice status="error" isDismissible={ false }>
							{ dateError }
						</Notice>
					) }
					<div className="rmd-oh-grid rmd-oh-grid--3">
						<SelectControl
							label={ __(
								'During this period',
								'rmd-opening-hours'
							) }
							value={ period.mode }
							options={ MODES }
							onChange={ ( value ) => set( { mode: value } ) }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
						<TextControl
							label={ __(
								'Lead time for the notice (days)',
								'rmd-opening-hours'
							) }
							help={ sprintf(
								/* translators: %d: default lead time */
								__(
									'Empty = plugin default (%d days).',
									'rmd-opening-hours'
								),
								config.defaults.leadDays
							) }
							type="number"
							min={ 0 }
							max={ 365 }
							value={
								period.lead_days === null
									? ''
									: period.lead_days
							}
							onChange={ ( value ) =>
								set( {
									lead_days:
										value === ''
											? null
											: parseInt( value, 10 ),
								} )
							}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
						<TextControl
							label={ __( 'Note', 'rmd-opening-hours' ) }
							help={ __(
								'Optional, shown with the hours and in the notice.',
								'rmd-opening-hours'
							) }
							value={ period.note }
							maxLength={ config.limits.note }
							onChange={ ( value ) => set( { note: value } ) }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					</div>
					<div className="rmd-oh-grid rmd-oh-grid--2">
						<ToggleControl
							label={ __(
								'Repeats every year (same dates)',
								'rmd-opening-hours'
							) }
							checked={ period.recurring }
							onChange={ ( value ) =>
								set( { recurring: value } )
							}
							__nextHasNoMarginBottom
						/>
						{ period.mode !== 'closed' && (
							<CheckboxControl
								label={ __(
									'Also open on public holidays in this period',
									'rmd-opening-hours'
								) }
								help={ __(
									'Otherwise the holiday rules win over these hours.',
									'rmd-opening-hours'
								) }
								checked={ period.ignore_holidays }
								onChange={ ( value ) =>
									set( { ignore_holidays: value } )
								}
								__nextHasNoMarginBottom
							/>
						) }
					</div>

					{ period.mode === 'daily' && (
						<div className="rmd-oh-period__hours">
							<p className="description">
								{ __(
									'Hours on every day of the period:',
									'rmd-opening-hours'
								) }
							</p>
							<DayEditor
								config={ config }
								value={ period.day }
								onChange={ ( value ) => set( { day: value } ) }
								compact
							/>
						</div>
					) }
					{ period.mode === 'weekly' && (
						<div className="rmd-oh-period__hours">
							<p className="description">
								{ __(
									'Weekly hours during the period:',
									'rmd-opening-hours'
								) }
							</p>
							<WeekEditor
								config={ config }
								week={ period.week }
								onChange={ ( value ) => set( { week: value } ) }
								compact
							/>
						</div>
					) }
				</div>
			) }
		</div>
	);
}

export default function PeriodsEditor( { config, periods, onChange } ) {
	const [ showPast, setShowPast ] = useState( false );

	const sorted = [ ...periods ].sort( ( a, b ) => {
		const sa = periodStatus( a, config.today );
		const sb = periodStatus( b, config.today );
		if ( ( sa === 'past' ) !== ( sb === 'past' ) ) {
			return sa === 'past' ? 1 : -1;
		}
		return a.start.localeCompare( b.start );
	} );
	const visible = showPast
		? sorted
		: sorted.filter( ( p ) => periodStatus( p, config.today ) !== 'past' );
	const pastCount =
		sorted.length -
		sorted.filter( ( p ) => periodStatus( p, config.today ) !== 'past' )
			.length;

	const replace = ( uid, next ) =>
		onChange( periods.map( ( p ) => ( p.uid === uid ? next : p ) ) );
	const remove = ( uid ) =>
		onChange( periods.filter( ( p ) => p.uid !== uid ) );

	return (
		<div className="rmd-oh-periods">
			<p className="description">
				{ __(
					'Plan holidays, reduced or extended hours. The notice block announces each period ahead of time and disappears automatically when it is over.',
					'rmd-opening-hours'
				) }
			</p>

			{ visible.length === 0 && (
				<p className="rmd-oh-empty">
					{ __( 'No upcoming periods.', 'rmd-opening-hours' ) }
				</p>
			) }

			{ visible.map( ( period ) => (
				<PeriodRow
					key={ period.uid }
					config={ config }
					period={ period }
					onChange={ ( next ) => replace( period.uid, next ) }
					onRemove={ () => remove( period.uid ) }
				/>
			) ) }

			<div className="rmd-oh-periods__actions">
				{ periods.length < config.limits.periods && (
					<Button
						variant="secondary"
						icon={ plus }
						onClick={ () =>
							onChange( [
								...periods,
								newPeriod( config.today ),
							] )
						}
					>
						{ __( 'Add period', 'rmd-opening-hours' ) }
					</Button>
				) }
				{ pastCount > 0 && (
					<Button
						variant="link"
						onClick={ () => setShowPast( ! showPast ) }
					>
						{ showPast
							? __( 'Hide past periods', 'rmd-opening-hours' )
							: sprintf(
									/* translators: %d: number of past periods */
									__(
										'Show %d past periods',
										'rmd-opening-hours'
									),
									pastCount
								) }
					</Button>
				) }
			</div>
		</div>
	);
}
