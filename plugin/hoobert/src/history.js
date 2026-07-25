/**
 * Hoobert query-history modal.
 *
 * Opened from the "Hoobert: Query history" command. Lists the merchant's most
 * recent executed commands (newest first): the natural-language query, the
 * WooCommerce REST request it ran, whether it succeeded, and any error. Data
 * comes from the hoobert/v1 proxy (api.js); it shares the .hoobert-* styling.
 */

import { useState, useEffect, useCallback, Fragment } from '@wordpress/element';
import { history as fetchHistory } from './api';

/**
 * Format a unix timestamp (seconds) as a short local date-time string.
 *
 * @param {number} seconds Unix timestamp; falsy renders as empty.
 */
export function formatTime( seconds ) {
	if ( ! seconds ) {
		return '';
	}
	return new Date( seconds * 1000 ).toLocaleString();
}

/**
 * Render an argument value for display: scalars as-is, objects/arrays as JSON.
 *
 * @param {*} value One argument from the recorded request.
 */
export function formatArg( value ) {
	if ( value === null || value === undefined ) {
		return '-';
	}
	if ( typeof value === 'object' ) {
		return JSON.stringify( value );
	}
	return String( value );
}

/**
 * Collapsed-by-default list of the request's arguments (name -> value).
 * Renders nothing when the request carried no params.
 */
function ArgsList( { params } ) {
	const names = params ? Object.keys( params ) : [];
	if ( ! names.length ) {
		return null;
	}
	return (
		<details className="hoobert-history-args">
			<summary>
				{ names.length } argument{ names.length === 1 ? '' : 's' }
			</summary>
			<dl className="hoobert-fields">
				{ names.map( ( name ) => (
					<Fragment key={ name }>
						<dt>{ name }</dt>
						<dd>{ formatArg( params[ name ] ) }</dd>
					</Fragment>
				) ) }
			</dl>
		</details>
	);
}

/**
 * One history row: query, outcome badge, the request that ran, and any error.
 */
function HistoryEntry( { entry } ) {
	const { method, route, params } = entry.request || {};
	return (
		<li className="hoobert-history-item">
			<div className="hoobert-history-top">
				<span className="hoobert-history-query">
					{ entry.query || <em>(no query)</em> }
				</span>
				<span
					className={ `hoobert-badge ${
						entry.ok ? 'is-ok' : 'is-error'
					}` }
				>
					{ entry.ok
						? 'Success'
						: `Failed${
								entry.status ? ` · ${ entry.status }` : ''
						  }` }
				</span>
			</div>
			<div className="hoobert-history-req">
				<code className="hoobert-call-name">{ entry.tool }</code>
				{ ( method || route ) && (
					<span className="hoobert-history-route">
						{ method } { route }
					</span>
				) }
			</div>
			{ ! entry.ok && entry.error && (
				<p className="hoobert-history-error">{ entry.error }</p>
			) }
			<ArgsList params={ params } />
			<span className="hoobert-history-time">
				{ formatTime( entry.time ) }
			</span>
		</li>
	);
}

/**
 * Modal that loads and renders the current user's command history.
 *
 * @param {Object}   props
 * @param {Function} props.onClose Close/dismiss the modal.
 */
export function HoobertHistoryModal( { onClose } ) {
	const [ state, setState ] = useState( { phase: 'loading' } );

	const load = useCallback( async () => {
		setState( { phase: 'loading' } );
		try {
			const { entries } = await fetchHistory();
			setState( { phase: 'ready', entries: entries || [] } );
		} catch ( e ) {
			setState( { phase: 'error', error: e.message } );
		}
	}, [] );

	useEffect( () => {
		load();
	}, [ load ] );

	// Esc closes the modal.
	useEffect( () => {
		const onKey = ( e ) => {
			if ( e.key === 'Escape' ) {
				onClose();
			}
		};
		document.addEventListener( 'keydown', onKey );
		return () => document.removeEventListener( 'keydown', onKey );
	}, [ onClose ] );

	const entries = state.entries || [];

	return (
		// The backdrop only dismisses; Esc does the same from the keyboard, so it
		// carries no behaviour of its own for assistive tech to announce.
		<div
			className="hoobert-positioner"
			role="presentation"
			onMouseDown={ ( e ) => {
				if ( e.target === e.currentTarget ) {
					onClose();
				}
			} }
		>
			<div className="hoobert-animator">
				<div className="hoobert-flow-head">Hoobert history</div>
				<div className="hoobert-panel">
					{ state.phase === 'loading' && (
						<p className="hoobert-status">Loading…</p>
					) }

					{ state.phase === 'error' && (
						<p className="hoobert-status is-error">
							{ state.error }
						</p>
					) }

					{ state.phase === 'ready' && entries.length === 0 && (
						<p className="hoobert-status">
							No commands yet. Press ⌘K / Ctrl-K and ask Hoobert
							to do something.
						</p>
					) }

					{ state.phase === 'ready' && entries.length > 0 && (
						<ul className="hoobert-history-list">
							{ entries.map( ( entry, i ) => (
								<HistoryEntry key={ i } entry={ entry } />
							) ) }
						</ul>
					) }
				</div>
			</div>
		</div>
	);
}
