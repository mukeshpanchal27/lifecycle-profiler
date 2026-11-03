<?php
/**
 * Profiling sessions.
 *
 * A request is profiled only when it carries a valid session cookie. The
 * cookie holds a user ID, an expiry time and an HMAC signature. The session
 * check must run before regular plugins load, when the WordPress login
 * functions do not exist yet. Until init, the check only verifies the
 * signature and the expiry. After init, it also checks the current user.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Name of the session cookie.
 */
const SESSION_COOKIE = 'lifecycle_profiler_session';

/**
 * Option that stores the secret key for signing session cookies.
 */
const SESSION_SECRET_OPTION = 'lifecycle_profiler_session_secret';

/**
 * Capability a user needs to start a session.
 */
const SESSION_CAPABILITY = 'manage_options';

/**
 * How long a session lasts, in seconds.
 */
const SESSION_LENGTH = 3600;

/**
 * Returns the secret key for signing session cookies.
 *
 * Creates the key on first use.
 *
 * @return string Secret key.
 */
function get_session_secret(): string {
	$secret = get_option( SESSION_SECRET_OPTION );

	if ( ! is_string( $secret ) || '' === $secret ) {
		$secret = bin2hex( random_bytes( 32 ) );
		update_option( SESSION_SECRET_OPTION, $secret, true );
	}

	return $secret;
}

/**
 * Creates a signed session token.
 *
 * @param int $user_id User ID.
 * @param int $expires Unix time when the session ends.
 * @return string Token in the form "user_id|expires|signature".
 */
function create_session_token( int $user_id, int $expires ): string {
	$data = $user_id . '|' . $expires;

	return $data . '|' . hash_hmac( 'sha256', $data, get_session_secret() );
}

/**
 * Reads and verifies a session token.
 *
 * @param string $token Token from the session cookie.
 * @return int|null User ID if the token is valid and not expired, otherwise null.
 */
function parse_session_token( string $token ): ?int {
	$parts = explode( '|', $token );

	if ( 3 !== count( $parts ) ) {
		return null;
	}

	list( $user_id, $expires, $signature ) = $parts;

	if ( ! preg_match( '/^\d+$/', $user_id ) || ! preg_match( '/^\d+$/', $expires ) || (int) $expires < time() ) {
		return null;
	}

	$expected = hash_hmac( 'sha256', $user_id . '|' . $expires, get_session_secret() );

	if ( ! hash_equals( $expected, $signature ) ) {
		return null;
	}

	return (int) $user_id;
}

/**
 * Returns the user ID of the current session.
 *
 * @return int|null User ID if the request has a valid session cookie, otherwise null.
 */
function get_session_user_id(): ?int {
	if ( ! isset( $_COOKIE[ SESSION_COOKIE ] ) || ! is_string( $_COOKIE[ SESSION_COOKIE ] ) ) {
		return null;
	}

	return parse_session_token( sanitize_text_field( wp_unslash( $_COOKIE[ SESSION_COOKIE ] ) ) );
}

/**
 * Checks whether the current request should be profiled.
 *
 * Before init, only the cookie is checked. From init on, the current user
 * must also match the cookie and have the session capability.
 *
 * @return bool True if the request has an active session.
 */
function is_session_active(): bool {
	$user_id = get_session_user_id();

	if ( null === $user_id ) {
		return false;
	}

	if ( did_action( 'init' ) ) {
		return get_current_user_id() === $user_id && current_user_can( SESSION_CAPABILITY );
	}

	return true;
}

/**
 * Checks whether the request has a session for a confirmed user.
 *
 * Code that sends or saves results calls this instead of is_session_active().
 * Before init, a session is only a valid cookie. A request that ends before
 * init never confirms the user, so it gets no results.
 *
 * @return bool True if init ran and the request has an active session.
 */
function is_session_confirmed(): bool {
	return did_action( 'init' ) && is_session_active();
}

/**
 * Starts a session for a user by setting the session cookie.
 *
 * @param int $user_id User ID.
 * @return bool True if the cookie was set.
 */
function start_session( int $user_id ): bool {
	if ( ! user_can( $user_id, SESSION_CAPABILITY ) || headers_sent() ) {
		return false;
	}

	$expires = time() + SESSION_LENGTH;
	$token   = create_session_token( $user_id, $expires );

	set_session_cookie( $token, $expires );
	$_COOKIE[ SESSION_COOKIE ] = $token;

	return true;
}

/**
 * Ends the current session by removing the session cookie.
 */
function end_session(): void {
	if ( ! headers_sent() ) {
		set_session_cookie( '', time() - YEAR_IN_SECONDS );
	}

	unset( $_COOKIE[ SESSION_COOKIE ] );
}

/**
 * Sends the session cookie.
 *
 * @param string $value   Cookie value.
 * @param int    $expires Unix time when the cookie expires.
 */
function set_session_cookie( string $value, int $expires ): void {
	setcookie(
		SESSION_COOKIE,
		$value,
		array(
			'expires'  => $expires,
			'path'     => COOKIEPATH,
			'domain'   => COOKIE_DOMAIN,
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		)
	);
}
