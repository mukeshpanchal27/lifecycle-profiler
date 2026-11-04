/**
 * Makes the wordpress.org banners, icons and screenshots in .wordpress-org/,
 * and the GitHub social preview image in .github/.
 *
 * Banners, icons and the social preview come from the HTML files in bin/assets/.
 * The social preview is not in .wordpress-org/, because the deploy copies
 * that folder to wordpress.org.
 *
 * Screenshots come from the wp-env development site, which must be running
 * and have at least two saved reports.
 *
 * Usage: node bin/make-wordpress-org-assets.js [--skip-screenshots]
 */
const path = require( 'path' );
const { chromium } = require( '@playwright/test' );

const root = path.join( __dirname, '..' );
const out = path.join( root, '.wordpress-org' );
const site = process.env.WP_BASE_URL || 'http://localhost:8888';

/**
 * Renders an HTML file to PNG files of several sizes.
 *
 * @param {import('@playwright/test').Browser} browser Browser.
 * @param {string} file HTML file in bin/assets/.
 * @param {{width: number, height: number}} size CSS size of the page.
 * @param {Array<{name: string, scale: number, dir?: string}>} outputs File names, pixel ratios and optional output folders.
 */
async function render( browser, file, size, outputs ) {
	for ( const output of outputs ) {
		const page = await browser.newPage( { viewport: size, deviceScaleFactor: output.scale } );

		await page.goto( 'file://' + path.join( __dirname, 'assets', file ) );
		await page.screenshot( { path: path.join( output.dir || out, output.name ) } );
		await page.close();
	}
}

/**
 * Takes the plugin screenshots.
 *
 * @param {import('@playwright/test').Browser} browser Browser.
 */
async function screenshots( browser ) {
	const page = await browser.newPage( { viewport: { width: 1280, height: 860 } } );
	const shot = ( n ) => page.screenshot( { path: path.join( out, `screenshot-${ n }.png` ) } );
	const showHeading = async ( text ) => {
		await page.locator( 'h2', { hasText: text } ).first().evaluate( ( el ) => window.scrollTo( 0, el.getBoundingClientRect().top + window.scrollY - 50 ) );
	};

	await page.goto( site + '/wp-login.php' );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await page.click( '#wp-submit' );

	await page.goto( site + '/wp-admin/tools.php?page=lifecycle-profiler' );
	await shot( 1 );

	await showHeading( 'Templates and blocks' );
	await shot( 2 );

	await page.goto( site + '/wp-admin/tools.php?page=lifecycle-profiler&view=compare' );
	await showHeading( 'Plugins, themes and core' );
	await shot( 3 );

	// Turn the session on for the admin bar screenshot, then off again.
	const toggle = '#wp-admin-bar-lifecycle-profiler > a';

	await page.setViewportSize( { width: 1280, height: 420 } );
	await page.goto( site + '/sample-page/' );
	await page.click( toggle );
	await page.locator( toggle, { hasText: 'Profiler: ON' } ).waitFor();
	await shot( 4 );
	await page.click( toggle );

	await page.close();
}

( async () => {
	const browser = await chromium.launch();

	await render( browser, 'banner.html', { width: 1544, height: 500 }, [
		{ name: 'banner-1544x500.png', scale: 1 },
		{ name: 'banner-772x250.png', scale: 0.5 },
	] );
	await render( browser, 'icon.html', { width: 256, height: 256 }, [
		{ name: 'icon-256x256.png', scale: 1 },
		{ name: 'icon-128x128.png', scale: 0.5 },
	] );

	await render( browser, 'social-preview.html', { width: 1280, height: 640 }, [
		{ name: 'social-preview.png', scale: 1, dir: path.join( root, '.github' ) },
	] );

	if ( ! process.argv.includes( '--skip-screenshots' ) ) {
		await screenshots( browser );
	}

	await browser.close();
} )();
