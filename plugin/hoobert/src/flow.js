/**
 * Standalone Hoobert flow modal.
 *
 * The native WordPress command palette closes as soon as a command's callback
 * runs and offers no surface to render progress. So once "Ask Hoobert" is
 * selected there, we drive the resolve -> (confirm) -> execute -> result flow in
 * this self-contained modal instead. It reuses the hoobert/v1 proxy (api.js) and
 * the shared .hoobert-* styling.
 */

import { useState, useEffect, useCallback, Fragment } from '@wordpress/element';
import { resolve, execute } from './api';

/**
 * Pretty-print a value as JSON, HTML-escape it (the data carries user-controlled
 * strings), then wrap keys/strings/numbers/booleans/null in classed spans. Returns
 * a safe HTML string, so escaping must happen before the spans are added.
 *
 * @param {*} value Any JSON-serializable value.
 */
export function highlightJson( value ) {
	const json = JSON.stringify( value, null, 2 );
	if ( json === undefined ) {
		return '';
	}
	const escaped = json
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' );
	return escaped.replace(
		/("(?:\\.|[^"\\])*"(\s*:)?|\b(?:true|false|null)\b|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?)/g,
		( match ) => {
			let cls = 'number';
			if ( /^"/.test( match ) ) {
				cls = /:$/.test( match ) ? 'key' : 'string';
			} else if ( /true|false/.test( match ) ) {
				cls = 'boolean';
			} else if ( /null/.test( match ) ) {
				cls = 'null';
			}
			return `<span class="hoobert-json-${ cls }">${ match }</span>`;
		}
	);
}

/**
 * Pretty-printed, syntax-highlighted JSON block. The HTML is built by
 * highlightJson, which escapes the payload before adding its own markup.
 */
function JsonBlock( { value } ) {
	return (
		<pre
			className="hoobert-call-args"
			dangerouslySetInnerHTML={ { __html: highlightJson( value ) } }
		/>
	);
}

/**
 * Shows the tool name + arguments the model chose.
 */
function CallPreview( { call } ) {
	return (
		<div className="hoobert-call">
			<code className="hoobert-call-name">{ call.name }</code>
			<JsonBlock value={ call.arguments } />
		</div>
	);
}

/**
 * Reduce a tool's training description to a short, merchant-facing action
 * phrase: the first sentence, before the "Use for ..." examples, with any
 * parenthetical aside and trailing period trimmed. Empty when unavailable.
 *
 * @param {Object} call The resolved tool call, which carries `description`.
 */
export function actionPhrase( call ) {
	const desc = ( call.description || '' ).trim();
	if ( ! desc ) {
		return '';
	}
	return desc
		.split( /\.\s*Use for/i )[ 0 ]
		.replace( /\s*\([^)]*\)/g, '' )
		.replace( /\.+$/, '' )
		.trim();
}

/**
 * Plain-English, non-technical sentence describing the write a confirm-flagged
 * tool will perform, so merchants aren't asked to reason about tool names.
 *
 * @param {Object}  props
 * @param {Object}  props.call   The resolved tool call (carries `description`).
 * @param {boolean} props.prompt Append the "Do you want to run it?" question.
 */
function ConfirmMessage( { call, prompt } ) {
	const phrase = actionPhrase( call );
	const sentence = phrase
		? `This will ${ phrase.charAt( 0 ).toLowerCase() }${ phrase.slice(
				1
		  ) }, which adds or updates data in your store.`
		: 'This will add or update data in your store.';
	return (
		<p className="hoobert-confirm-message">
			{ sentence }
			{ prompt ? ' Do you want to run it?' : '' }
		</p>
	);
}

/**
 * Renders a tool's merchant-facing display payload (built server-side by the
 * executor): a labeled field list for a single object, or a column table for a
 * list. Falls back to a friendly empty message when a list has no rows.
 */
function DisplayView( { display } ) {
	if ( display.type === 'list' ) {
		if ( ! display.rows.length ) {
			return <p className="hoobert-result-summary">{ display.empty }</p>;
		}
		return (
			<div className="hoobert-result">
				<p className="hoobert-result-summary">
					{ display.count } result
					{ display.count === 1 ? '' : 's' }
				</p>
				<table className="hoobert-table">
					<thead>
						<tr>
							{ display.columns.map( ( col ) => (
								<th key={ col }>{ col }</th>
							) ) }
						</tr>
					</thead>
					<tbody>
						{ display.rows.map( ( row, r ) => (
							<tr key={ r }>
								{ row.map( ( cell, c ) => (
									<td key={ c }>{ cell }</td>
								) ) }
							</tr>
						) ) }
					</tbody>
				</table>
			</div>
		);
	}

	return (
		<div className="hoobert-result">
			{ display.title && (
				<p className="hoobert-result-title">{ display.title }</p>
			) }
			<dl className="hoobert-fields">
				{ display.rows.map( ( row ) => (
					<Fragment key={ row.label }>
						<dt>{ row.label }</dt>
						<dd>{ row.value }</dd>
					</Fragment>
				) ) }
			</dl>
		</div>
	);
}

/**
 * The result view: the tool's merchant-facing display when it has one, else a
 * fallback to the raw (syntax-highlighted) response.
 */
function ResultPreview( { result } ) {
	if ( result?.display ) {
		return <DisplayView display={ result.display } />;
	}

	const data = result?.data;
	let summary = '';
	if ( Array.isArray( data ) ) {
		summary = `${ data.length } result${ data.length === 1 ? '' : 's' }`;
	} else if ( data && typeof data === 'object' ) {
		summary = data.id ? `#${ data.id }` : 'ok';
	}
	return (
		<div className="hoobert-result">
			{ summary && <p className="hoobert-result-summary">{ summary }</p> }
			<JsonBlock value={ data } />
		</div>
	);
}

/**
 * Collapsed technical detail for merchants who want it: the tool call Fern
 * returned, the REST request the executor ran, the HTTP status, and any error.
 * Hidden behind a toggle so the default view stays free of developer noise.
 */
function DebugInfo( { call, result, error, response } ) {
	return (
		<details className="hoobert-debug">
			<summary>Debug info</summary>
			<div className="hoobert-debug-body">
				{ call && (
					<Fragment>
						<p className="hoobert-debug-label">Fern returned</p>
						<CallPreview call={ call } />
					</Fragment>
				) }
				{ result?.request && (
					<Fragment>
						<p className="hoobert-debug-label">Executor ran</p>
						<JsonBlock value={ result.request } />
					</Fragment>
				) }
				{ response !== undefined && (
					<Fragment>
						<p className="hoobert-debug-label">Response</p>
						<JsonBlock value={ response } />
					</Fragment>
				) }
				{ result?.status !== undefined && (
					<p className="hoobert-debug-line">
						HTTP status: { result.status }
					</p>
				) }
				{ error && (
					<p className="hoobert-debug-line">Error: { error }</p>
				) }
			</div>
		</details>
	);
}

/**
 * Modal that runs one Hoobert request end to end for the given query.
 *
 * @param {Object}   props
 * @param {string}   props.query   The merchant utterance selected in the palette.
 * @param {Function} props.onClose Close/dismiss the modal.
 */
export function HoobertFlowModal( { query, onClose } ) {
	const [ flow, setFlow ] = useState( { phase: 'resolving' } );

	const doExecute = useCallback(
		async ( call ) => {
			setFlow( { phase: 'executing', call } );
			try {
				const result = await execute( call, query );
				setFlow( { phase: 'done', call, result } );
			} catch ( e ) {
				// e.data is the executor result body (status, request) on a failed run.
				setFlow( {
					phase: 'error',
					error: e.message,
					call,
					result: e.data,
				} );
			}
		},
		[ query ]
	);

	// Kick off resolution whenever the query changes.
	useEffect( () => {
		let active = true;
		setFlow( { phase: 'resolving' } );
		( async () => {
			try {
				const { calls, reply } = await resolve( query );
				if ( ! active ) {
					return;
				}
				const call = calls && calls[ 0 ];
				if ( ! call ) {
					setFlow( {
						phase: 'error',
						error: reply || 'Hoobert found no matching action.',
					} );
					return;
				}
				if ( call.confirm ) {
					setFlow( { phase: 'confirm', call } );
				} else {
					doExecute( call );
				}
			} catch ( e ) {
				if ( active ) {
					setFlow( { phase: 'error', error: e.message } );
				}
			}
		} )();
		return () => {
			active = false;
		};
	}, [ query, doExecute ] );

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
				<div className="hoobert-flow-head">
					Ask Hoobert: <span className="q">“{ query }”</span>
				</div>
				<div className="hoobert-panel">
					{ flow.phase === 'resolving' && (
						<p className="hoobert-status">Thinking…</p>
					) }

					{ ( flow.phase === 'confirm' ||
						flow.phase === 'executing' ) &&
						flow.call && (
							<Fragment>
								<p className="hoobert-status">
									{ flow.phase === 'executing'
										? 'Running…'
										: 'Confirm this action' }
								</p>
								{ flow.call.confirm && (
									<ConfirmMessage
										call={ flow.call }
										prompt={ flow.phase === 'confirm' }
									/>
								) }
								<details className="hoobert-debug">
									<summary>Technical details</summary>
									<div className="hoobert-debug-body">
										<CallPreview call={ flow.call } />
									</div>
								</details>
								{ flow.phase === 'confirm' && (
									<div className="hoobert-actions">
										<button
											className="hoobert-btn is-primary"
											onClick={ () =>
												doExecute( flow.call )
											}
										>
											Run
										</button>
										<button
											className="hoobert-btn"
											onClick={ onClose }
										>
											Cancel
										</button>
									</div>
								) }
							</Fragment>
						) }

					{ flow.phase === 'done' && (
						<Fragment>
							<ResultPreview result={ flow.result } />
							<DebugInfo
								call={ flow.call }
								result={ flow.result }
								response={
									flow.result?.display
										? flow.result?.data
										: undefined
								}
							/>
						</Fragment>
					) }

					{ flow.phase === 'error' &&
						( flow.call || flow.result ? (
							// A tool ran and failed: keep the merchant message generic,
							// tuck the real error + status into debug info.
							<Fragment>
								<p className="hoobert-status is-error">
									An error occurred.
								</p>
								<DebugInfo
									call={ flow.call }
									result={ flow.result }
									error={ flow.error }
								/>
							</Fragment>
						) : (
							// No tool ran (e.g. no matching action): the message is
							// already merchant-friendly, so show it as-is.
							<p className="hoobert-status is-error">
								{ flow.error }
							</p>
						) ) }
				</div>
			</div>
		</div>
	);
}
