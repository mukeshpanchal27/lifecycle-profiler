/**
 * End-to-end test: a profiling session adds the Server-Timing header.
 *
 * Runs against the wp-env development site. Log in as the wp-env administrator,
 * start a session from the admin bar, then check the header on a front-end
 * page.
 */
const { test, expect } = require( '@playwright/test' );

const toggle = '#wp-admin-bar-lifecycle-profiler > a';

/**
 * Loads a front-end page and returns its Server-Timing header.
 *
 * @param {import('@playwright/test').Page} page Browser page.
 * @return {Promise<string|undefined>} Header value, or undefined if missing.
 */
async function getServerTiming( page ) {
	const response = await page.goto( '/?p=1' );

	expect( response.status() ).toBe( 200 );

	return response.headers()[ 'server-timing' ];
}

test( 'a session adds the Server-Timing header, and stopping it removes the header', async ( { page } ) => {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await page.click( '#wp-submit' );
	await expect( page ).toHaveURL( /wp-admin/ );

	// Start from no session, whatever an earlier run left behind.
	if ( ( await page.textContent( toggle ) ).includes( 'ON' ) ) {
		await page.click( toggle );
	}
	await expect( page.locator( toggle ) ).toHaveText( 'Profiler: OFF' );

	expect( await getServerTiming( page ) ).toBeUndefined();

	await page.click( toggle );
	await expect( page.locator( toggle ) ).toHaveText( 'Profiler: ON' );

	const header = await getServerTiming( page );

	expect( header ).toMatch( /(^|, )lp-bootstrap;dur=\d+\.\d{3}(,|$)/ );
	expect( header ).toMatch( /(^|, )lp-template_redirect;dur=\d+\.\d{3}(,|$)/ );

	await page.click( toggle );
	await expect( page.locator( toggle ) ).toHaveText( 'Profiler: OFF' );

	expect( await getServerTiming( page ) ).toBeUndefined();
} );

test( 'a logged-out visitor never gets the header', async ( { page } ) => {
	expect( await getServerTiming( page ) ).toBeUndefined();
} );
