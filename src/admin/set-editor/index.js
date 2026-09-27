/**
 * Set editor: a React app inside the classic edit screen. It keeps the whole
 * set in memory and mirrors it into a hidden JSON field; PHP validates on save.
 */
import { createRoot, StrictMode } from '@wordpress/element';

import App from './components/App';
import './editor.scss';

function mount() {
	const root = document.getElementById( 'rmd-oh-editor-root' );
	const config = window.rmdOhEditorConfig;
	if ( ! root || ! config ) {
		return;
	}
	const field = document.getElementById( config.fieldId );
	if ( ! field ) {
		return;
	}

	let initial = {};
	try {
		initial = JSON.parse( field.value || '{}' );
	} catch {
		initial = {};
	}

	root.innerHTML = '';
	createRoot( root ).render(
		<StrictMode>
			<App
				config={ config }
				initial={ initial }
				onChange={ ( data ) => {
					field.value = JSON.stringify( data );
				} }
			/>
		</StrictMode>
	);
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mount );
} else {
	mount();
}
