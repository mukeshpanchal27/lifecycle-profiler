/**
 * End-to-end test: an administrator's requests are profiled automatically.
 *
 * Runs against the wp-env development site with the plugin active. Logging
 * in sets the profiler cookie, so the next front-end page has the
 * Server-Timing header.
 */
const { test, expect } = require( '@playwright/test' );

const item = '#wp-admin-bar-lifecycle-profiler > a';

/**
 * Loads a front-end page and returns its response.
 *
 * @param {import('@playwright/test').Page} page Browser page.
 * @return {Promise<import('@playwright/test').Response>} Response.
 */
async function loadPage( page ) {
	// The front page does not redirect, so each call is one request.
	const response = await page.goto( '/' );

	expect( response.status() ).toBe( 200 );

	return response;
}

/**
 * Logs in as the wp-env administrator.
 *
 * @param {import('@playwright/test').Page} page Browser page.
 */
async function logIn( page ) {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await page.click( '#wp-submit' );
	await expect( page ).toHaveURL( /wp-admin/ );
}

test( 'an administrator is profiled from login on, and the admin bar opens the report of the page', async ( { page } ) => {
	await logIn( page );

	const response = await loadPage( page );
	const header = response.headers()[ 'server-timing' ];

	expect( header ).toMatch( /(^|, )lp-bootstrap;dur=\d+\.\d{3}(,|$)/ );
	expect( header ).toMatch( /(^|, )lp-template_redirect;dur=\d+\.\d{3}(,|$)/ );

	// The browser shares the header with scripts on localhost, so the item shows the time.
	await expect( page.locator( item ) ).toHaveText( /^Profiler: [\d.,]+ ms$/ );

	const href = await page.locator( item ).getAttribute( 'href' );
	expect( href ).toMatch( /page=lifecycle-profiler&report=[0-9a-f]{12}$/ );

	await page.locator( item ).click();
	await expect( page.getByRole( 'heading', { name: 'GET /', exact: true } ) ).toBeVisible();
} );

test( 'a missing cookie comes back on the next request', async ( { page, context } ) => {
	await logIn( page );
	await context.clearCookies( { name: 'lifecycle_profiler_session' } );

	// This request sets the cookie, but it started before WordPress knew the user.
	expect( ( await loadPage( page ) ).headers()[ 'server-timing' ] ).toBeUndefined();
	expect( ( await loadPage( page ) ).headers()[ 'server-timing' ] ).toMatch( /lp-bootstrap/ );
} );

test( 'logging out stops profiling', async ( { page, context } ) => {
	await logIn( page );
	await page.goto( '/wp-login.php?action=logout' );
	await page.click( 'a[href*="action=logout"]' );

	const cookies = await context.cookies();
	expect( cookies.find( ( cookie ) => 'lifecycle_profiler_session' === cookie.name ) ).toBeUndefined();
	expect( ( await loadPage( page ) ).headers()[ 'server-timing' ] ).toBeUndefined();
} );

test( 'a logged-out visitor never gets the header', async ( { page } ) => {
	expect( ( await loadPage( page ) ).headers()[ 'server-timing' ] ).toBeUndefined();
} );
