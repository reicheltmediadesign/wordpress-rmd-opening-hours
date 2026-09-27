import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { SelectControl, Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

let setsPromise = null;

/**
 * Loads the list of sets once per editor session.
 *
 * @return {Promise<Array<{id: number, slug: string, title: string}>>} Sets.
 */
function loadSets() {
	if ( ! setsPromise ) {
		setsPromise = apiFetch( { path: '/rmd-opening-hours/v1/sets' } ).catch(
			() => []
		);
	}
	return setsPromise;
}

export function useSets() {
	const [ sets, setSets ] = useState( null );

	useEffect( () => {
		let mounted = true;
		loadSets().then( ( result ) => {
			if ( mounted ) {
				setSets( Array.isArray( result ) ? result : [] );
			}
		} );
		return () => {
			mounted = false;
		};
	}, [] );

	return sets;
}

/**
 * Set picker. `allowAll` adds an "all sets" choice (value 0) used by the notice block.
 *
 * @param {Object}   props
 * @param {number}   props.value
 * @param {Function} props.onChange
 * @param {boolean}  props.allowAll
 */
export function SetSelect( { value, onChange, allowAll = false } ) {
	const sets = useSets();

	if ( sets === null ) {
		return <Spinner />;
	}

	const options = [
		{
			value: 0,
			label: allowAll
				? __( 'All sets', 'rmd-opening-hours' )
				: __( 'Default set (see settings)', 'rmd-opening-hours' ),
		},
		...sets.map( ( set ) => ( { value: set.id, label: set.title } ) ),
	];

	return (
		<SelectControl
			label={ __( 'Opening hours set', 'rmd-opening-hours' ) }
			value={ value }
			options={ options }
			onChange={ ( next ) => onChange( parseInt( next, 10 ) || 0 ) }
			help={
				sets.length === 0
					? __(
							'No sets yet. Create one under Opening Hours in the admin menu.',
							'rmd-opening-hours'
						)
					: undefined
			}
			__nextHasNoMarginBottom
			__next40pxDefaultSize
		/>
	);
}

/**
 * "Set default / Yes / No" select for options that fall back to the set's own settings.
 *
 * @param {Object}   props
 * @param {string}   props.label
 * @param {string}   props.value
 * @param {Function} props.onChange
 */
export function TriStateControl( { label, value, onChange } ) {
	return (
		<SelectControl
			label={ label }
			value={ value }
			options={ [
				{ value: '', label: __( 'Set default', 'rmd-opening-hours' ) },
				{ value: 'yes', label: __( 'Yes', 'rmd-opening-hours' ) },
				{ value: 'no', label: __( 'No', 'rmd-opening-hours' ) },
			] }
			onChange={ onChange }
			__nextHasNoMarginBottom
			__next40pxDefaultSize
		/>
	);
}
