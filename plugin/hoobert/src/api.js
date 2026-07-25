/**
 * Fetch wrappers around the plugin's own admin REST routes (hoobert/v1).
 * The browser never sees the inference API key. The PHP proxy holds it.
 */

/* global hoobert */

/**
 * Read the runtime config injected by wp_localize_script.
 */
export function config() {
	return typeof hoobert !== 'undefined'
		? hoobert
		: { root: '', nonce: '', context: {} };
}

/**
 * Fetch helper that attaches the REST nonce and normalizes errors.
 *
 * @param {string} path            Route below the hoobert/v1 root.
 * @param {Object} options         Request options.
 * @param {string} options.method  HTTP method.
 * @param {Object} options.payload JSON body; omitted for GET.
 */
async function request( path, { method = 'GET', payload } = {} ) {
	const { root, nonce } = config();
	const res = await fetch( `${ root }${ path }`, {
		method,
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': nonce,
		},
		body: payload !== undefined ? JSON.stringify( payload ) : undefined,
	} );

	let data = null;
	try {
		data = await res.json();
	} catch {
		data = null;
	}

	if ( ! res.ok ) {
		// The real WooCommerce message is nested at data.data.message (the WC error
		// body); fall back to our own error/message keys, then the bare status.
		const message =
			( data && ( data.error || data.message || data.data?.message ) ) ||
			`HTTP ${ res.status }`;
		// Carry the status + parsed body so callers can surface them in debug info.
		const error = new Error( message );
		error.status = res.status;
		error.data = data;
		throw error;
	}
	return data;
}

/**
 * POST helper that attaches the REST nonce.
 *
 * @param {string} path    Route below the hoobert/v1 root.
 * @param {Object} payload JSON body.
 */
function post( path, payload ) {
	return request( path, { method: 'POST', payload } );
}

/**
 * Ask Hoobert to translate a natural-language query into tool call(s). No execution.
 *
 * @param {string} query Merchant's request.
 * @return {Promise<{ok:boolean, calls:Array}>} Resolved tool calls with confirm flags.
 */
export function resolve( query ) {
	const { context } = config();
	return post( '/resolve', { query, context } );
}

/**
 * Execute a single resolved tool call against WooCommerce.
 *
 * @param {{name:string, arguments:object}} call  The tool call to run.
 * @param {string}                          query The utterance that produced it (logged to history).
 * @return {Promise<object>} Executor result { ok, status, data, request }.
 */
export function execute( call, query = '' ) {
	return post( '/execute', {
		name: call.name,
		arguments: call.arguments,
		query,
	} );
}

/**
 * Fetch the current user's executed-command history, newest first.
 *
 * @return {Promise<{ok:boolean, entries:Array}>} Recorded entries.
 */
export function history() {
	return request( '/history' );
}
