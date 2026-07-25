/**
 * The display helpers behind the two modals: JSON highlighting (which builds raw
 * HTML, so escaping is a correctness concern), the confirm-dialog action phrase,
 * and the history argument/time formatters.
 */

import { highlightJson, actionPhrase } from '../../src/flow';
import { formatArg, formatTime } from '../../src/history';

/**
 * Strip the helper's own markup, leaving the text a merchant would read.
 *
 * @param {string} html Output of highlightJson.
 */
function textOf( html ) {
	return html.replace( /<\/?span[^>]*>/g, '' );
}

describe( 'highlightJson', () => {
	it( 'pretty-prints with two-space indentation', () => {
		expect( textOf( highlightJson( { id: 42 } ) ) ).toBe(
			'{\n  "id": 42\n}'
		);
	} );

	it( 'classes keys, strings, numbers, booleans and null separately', () => {
		const html = highlightJson( {
			name: 'Ada',
			count: 3,
			paid: true,
			note: null,
		} );

		expect( html ).toContain(
			'<span class="hoobert-json-key">"name":</span>'
		);
		expect( html ).toContain(
			'<span class="hoobert-json-string">"Ada"</span>'
		);
		expect( html ).toContain(
			'<span class="hoobert-json-number">3</span>'
		);
		expect( html ).toContain(
			'<span class="hoobert-json-boolean">true</span>'
		);
		expect( html ).toContain(
			'<span class="hoobert-json-null">null</span>'
		);
	} );

	it( 'escapes markup in values before adding its own spans', () => {
		const html = highlightJson( { note: '<img src=x onerror=alert(1)>' } );

		expect( html ).not.toContain( '<img' );
		expect( html ).toContain( '&lt;img src=x onerror=alert(1)&gt;' );
	} );

	it( 'escapes ampersands so escaped output cannot be double-decoded', () => {
		expect( highlightJson( { note: 'Tom &amp; Jerry' } ) ).toContain(
			'Tom &amp;amp; Jerry'
		);
	} );

	it( 'handles negative and exponent numbers', () => {
		const html = highlightJson( { balance: -12.5, tiny: 1e-7 } );

		expect( html ).toContain(
			'<span class="hoobert-json-number">-12.5</span>'
		);
		expect( html ).toContain(
			'<span class="hoobert-json-number">1e-7</span>'
		);
	} );

	it( 'returns an empty string for values JSON cannot represent', () => {
		expect( highlightJson( undefined ) ).toBe( '' );
	} );

	it( 'renders an empty array without markup', () => {
		expect( highlightJson( [] ) ).toBe( '[]' );
	} );
} );

describe( 'actionPhrase', () => {
	it( 'keeps only the first sentence, dropping the training examples', () => {
		expect(
			actionPhrase( {
				description:
					"Refund an order in full or in part. Use for 'refund order 42', 'give back $10 on order 7'.",
			} )
		).toBe( 'Refund an order in full or in part' );
	} );

	it( 'strips parenthetical asides', () => {
		expect(
			actionPhrase( {
				description:
					'Create a product (simple or variable) in the catalog.',
			} )
		).toBe( 'Create a product in the catalog' );
	} );

	it( 'is empty when the tool carries no description', () => {
		expect( actionPhrase( {} ) ).toBe( '' );
		expect( actionPhrase( { description: '   ' } ) ).toBe( '' );
	} );
} );

describe( 'formatArg', () => {
	it( 'renders scalars as strings', () => {
		expect( formatArg( 42 ) ).toBe( '42' );
		expect( formatArg( 'completed' ) ).toBe( 'completed' );
		expect( formatArg( false ) ).toBe( 'false' );
	} );

	it( 'renders objects and arrays as JSON', () => {
		expect( formatArg( { amount: '10.00' } ) ).toBe( '{"amount":"10.00"}' );
		expect( formatArg( [ 1, 2 ] ) ).toBe( '[1,2]' );
	} );

	it( 'renders missing values as a dash', () => {
		expect( formatArg( null ) ).toBe( '-' );
		expect( formatArg( undefined ) ).toBe( '-' );
	} );

	it( 'renders zero and the empty string rather than treating them as missing', () => {
		expect( formatArg( 0 ) ).toBe( '0' );
		expect( formatArg( '' ) ).toBe( '' );
	} );
} );

describe( 'formatTime', () => {
	it( 'converts unix seconds to a local date-time string', () => {
		const seconds = Math.floor( Date.UTC( 2026, 2, 4, 10, 15 ) / 1000 );

		expect( formatTime( seconds ) ).toBe(
			new Date( seconds * 1000 ).toLocaleString()
		);
	} );

	it( 'is empty when there is no timestamp', () => {
		expect( formatTime( 0 ) ).toBe( '' );
		expect( formatTime( undefined ) ).toBe( '' );
	} );
} );
