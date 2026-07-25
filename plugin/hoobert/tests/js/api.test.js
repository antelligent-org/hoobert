/**
 * The hoobert/v1 fetch wrappers: the request each helper sends, and how it turns
 * a failed response into an Error the modals can render.
 */

import { config, resolve, execute, history } from '../../src/api';

const ROOT = 'https://store.test/wp-json/hoobert/v1';

/**
 * Stub global.fetch with a single canned response.
 *
 * @param {Object}  body        Parsed JSON body to hand back.
 * @param {Object}  init        Response overrides.
 * @param {boolean} init.ok     Whether fetch reports success.
 * @param {number}  init.status HTTP status.
 */
function mockFetch( body, { ok = true, status = 200 } = {} ) {
	global.fetch = jest.fn().mockResolvedValue( {
		ok,
		status,
		json: async () => body,
	} );
	return global.fetch;
}

/**
 * The parsed options of the nth fetch call.
 *
 * @param {number} n Index into fetch's recorded calls.
 */
function callArgs( n = 0 ) {
	const [ url, options ] = global.fetch.mock.calls[ n ];
	return { url, options, payload: JSON.parse( options.body ) };
}

beforeEach( () => {
	global.hoobert = {
		root: ROOT,
		nonce: 'test-nonce',
		context: { current_order_id: 42 },
	};
} );

afterEach( () => {
	delete global.hoobert;
	delete global.fetch;
} );

describe( 'config', () => {
	it( 'reads the localized runtime config', () => {
		expect( config() ).toEqual( {
			root: ROOT,
			nonce: 'test-nonce',
			context: { current_order_id: 42 },
		} );
	} );

	it( 'falls back to empty values when the script was not localized', () => {
		delete global.hoobert;

		expect( config() ).toEqual( { root: '', nonce: '', context: {} } );
	} );
} );

describe( 'resolve', () => {
	it( 'posts the query with the page context and the REST nonce', async () => {
		mockFetch( { ok: true, calls: [] } );

		await resolve( 'refund order 42' );

		const { url, options, payload } = callArgs();
		expect( url ).toBe( `${ ROOT }/resolve` );
		expect( options.method ).toBe( 'POST' );
		expect( options.headers[ 'X-WP-Nonce' ] ).toBe( 'test-nonce' );
		expect( options.headers[ 'Content-Type' ] ).toBe( 'application/json' );
		expect( payload ).toEqual( {
			query: 'refund order 42',
			context: { current_order_id: 42 },
		} );
	} );

	it( 'returns the parsed body', async () => {
		mockFetch( { ok: true, calls: [ { name: 'get_order' } ] } );

		await expect( resolve( 'order 42' ) ).resolves.toEqual( {
			ok: true,
			calls: [ { name: 'get_order' } ],
		} );
	} );
} );

describe( 'execute', () => {
	it( 'posts the tool call flattened alongside the originating query', async () => {
		mockFetch( { ok: true } );

		await execute(
			{
				name: 'update_order_status',
				arguments: { id: 42, status: 'completed' },
			},
			'mark order 42 completed'
		);

		const { url, payload } = callArgs();
		expect( url ).toBe( `${ ROOT }/execute` );
		expect( payload ).toEqual( {
			name: 'update_order_status',
			arguments: { id: 42, status: 'completed' },
			query: 'mark order 42 completed',
		} );
	} );

	it( 'defaults the query to an empty string', async () => {
		mockFetch( { ok: true } );

		await execute( { name: 'list_orders', arguments: {} } );

		expect( callArgs().payload.query ).toBe( '' );
	} );
} );

describe( 'history', () => {
	it( 'issues a GET with no body', async () => {
		mockFetch( { ok: true, entries: [] } );

		await history();

		const [ url, options ] = global.fetch.mock.calls[ 0 ];
		expect( url ).toBe( `${ ROOT }/history` );
		expect( options.method ).toBe( 'GET' );
		expect( options.body ).toBeUndefined();
	} );
} );

describe( 'error handling', () => {
	it( 'prefers the WooCommerce message nested at data.data.message', async () => {
		mockFetch(
			{
				code: 'woocommerce_rest_invalid_id',
				data: { message: 'Invalid order ID.', status: 404 },
			},
			{ ok: false, status: 404 }
		);

		await expect(
			execute( { name: 'get_order', arguments: {} } )
		).rejects.toThrow( 'Invalid order ID.' );
	} );

	it( 'uses the proxy error key when present', async () => {
		mockFetch(
			{ ok: false, error: 'Inference endpoint returned HTTP 503.' },
			{ ok: false, status: 502 }
		);

		await expect( resolve( 'anything' ) ).rejects.toThrow(
			'Inference endpoint returned HTTP 503.'
		);
	} );

	it( 'falls back to the bare status when the body carries no message', async () => {
		mockFetch( {}, { ok: false, status: 500 } );

		await expect( resolve( 'anything' ) ).rejects.toThrow( 'HTTP 500' );
	} );

	it( 'falls back to the bare status when the body is not JSON', async () => {
		global.fetch = jest.fn().mockResolvedValue( {
			ok: false,
			status: 502,
			json: async () => {
				throw new SyntaxError( 'Unexpected token < in JSON' );
			},
		} );

		await expect( resolve( 'anything' ) ).rejects.toThrow( 'HTTP 502' );
	} );

	it( 'carries the status and parsed body on the error, for debug info', async () => {
		const body = {
			ok: false,
			status: 400,
			request: { method: 'POST', route: '/wc/v3/orders/42' },
		};
		mockFetch( body, { ok: false, status: 400 } );

		await expect(
			execute( { name: 'update_order_status', arguments: {} } )
		).rejects.toMatchObject( { status: 400, data: body } );
	} );
} );
