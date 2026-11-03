<?php
/**
 * Tests for profiling sessions.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler\Tests;

use WP_UnitTestCase;

use function LifecycleProfiler\create_session_token;
use function LifecycleProfiler\get_session_user_id;
use function LifecycleProfiler\is_session_active;
use function LifecycleProfiler\is_session_confirmed;
use function LifecycleProfiler\parse_session_token;
use const LifecycleProfiler\SESSION_COOKIE;
use const LifecycleProfiler\SESSION_SECRET_OPTION;

/**
 * Tests for includes/session.php.
 *
 * @covers ::LifecycleProfiler\create_session_token
 * @covers ::LifecycleProfiler\parse_session_token
 * @covers ::LifecycleProfiler\get_session_user_id
 * @covers ::LifecycleProfiler\is_session_active
 * @covers ::LifecycleProfiler\is_session_confirmed
 */
class Test_Session extends WP_UnitTestCase {

	/**
	 * An administrator.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * A subscriber.
	 *
	 * @var int
	 */
	private static $subscriber_id;

	/**
	 * Creates the users.
	 *
	 * @param \WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );

		if ( is_multisite() ) {
			grant_super_admin( self::$admin_id );
		}
	}

	/**
	 * Removes the session cookie after each test.
	 */
	public function tear_down() {
		unset( $_COOKIE[ SESSION_COOKIE ] );

		parent::tear_down();
	}

	/**
	 * Sets the session cookie of the current request.
	 *
	 * @param string $token Cookie value.
	 */
	private function set_cookie( string $token ): void {
		$_COOKIE[ SESSION_COOKIE ] = $token;
	}

	/**
	 * Runs a callback as if init has not fired yet.
	 *
	 * @param callable $callback Callback to run.
	 * @return mixed What the callback returns.
	 */
	private function before_init( callable $callback ) {
		global $wp_actions;

		$count = $wp_actions['init'] ?? null;
		unset( $wp_actions['init'] );

		try {
			return $callback();
		} finally {
			if ( null !== $count ) {
				$wp_actions['init'] = $count; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}
	}

	/**
	 * A token made by the plugin reads back as its user ID.
	 */
	public function test_valid_token() {
		$this->assertSame( 7, parse_session_token( create_session_token( 7, time() + 60 ) ) );
	}

	/**
	 * Returns tokens that must not start a session.
	 *
	 * @return array<string, array{0: callable}> Token builders.
	 */
	public function data_invalid_tokens() {
		return array(
			'expired'        => array(
				static function () {
					return create_session_token( 1, time() - 1 );
				},
			),
			'changed user'   => array(
				static function () {
					return '2' . substr( create_session_token( 1, time() + 60 ), 1 );
				},
			),
			'changed expiry' => array(
				static function () {
					list( $user, $expires, $signature ) = explode( '|', create_session_token( 1, time() + 60 ) );

					return $user . '|' . ( $expires + 3600 ) . '|' . $signature;
				},
			),
			'other secret'   => array(
				static function () {
					$token = create_session_token( 1, time() + 60 );
					update_option( SESSION_SECRET_OPTION, 'another-secret' );

					return $token;
				},
			),
			'too few parts'  => array(
				static function () {
					return '1|' . ( time() + 60 );
				},
			),
			'not a number'   => array(
				static function () {
					return 'one|two|three';
				},
			),
			'empty'          => array(
				static function () {
					return '';
				},
			),
		);
	}

	/**
	 * Expired, changed and broken tokens read as no session.
	 *
	 * @dataProvider data_invalid_tokens
	 *
	 * @param callable $token Returns the token.
	 */
	public function test_invalid_token( $token ) {
		$this->assertNull( parse_session_token( $token() ) );
	}

	/**
	 * A request without the cookie has no session.
	 */
	public function test_no_cookie_is_no_session() {
		wp_set_current_user( self::$admin_id );

		$this->assertNull( get_session_user_id() );
		$this->assertFalse( is_session_active() );
	}

	/**
	 * A cookie that is not a string has no session.
	 */
	public function test_array_cookie_is_no_session() {
		$_COOKIE[ SESSION_COOKIE ] = array( 'x' );

		$this->assertNull( get_session_user_id() );
	}

	/**
	 * After init, a valid cookie of the current administrator is a session.
	 */
	public function test_session_for_current_admin() {
		wp_set_current_user( self::$admin_id );
		$this->set_cookie( create_session_token( self::$admin_id, time() + 60 ) );

		$this->assertTrue( is_session_active() );
	}

	/**
	 * After init, a cookie of another user is no session.
	 */
	public function test_no_session_for_other_user() {
		wp_set_current_user( self::$admin_id );
		$this->set_cookie( create_session_token( self::$subscriber_id, time() + 60 ) );

		$this->assertFalse( is_session_active() );
	}

	/**
	 * After init, a user without the capability has no session, even with a cookie for that user.
	 */
	public function test_no_session_without_capability() {
		wp_set_current_user( self::$subscriber_id );
		$this->set_cookie( create_session_token( self::$subscriber_id, time() + 60 ) );

		$this->assertFalse( is_session_active() );
	}

	/**
	 * After init, a logged-out visitor with a copied cookie has no session.
	 */
	public function test_no_session_when_logged_out() {
		wp_set_current_user( 0 );
		$this->set_cookie( create_session_token( self::$admin_id, time() + 60 ) );

		$this->assertFalse( is_session_active() );
	}

	/**
	 * Before init, only the cookie is checked, because users are not loaded yet.
	 */
	public function test_before_init_checks_only_the_cookie() {
		wp_set_current_user( 0 );
		$this->set_cookie( create_session_token( self::$admin_id, time() + 60 ) );

		$this->assertTrue( $this->before_init( 'LifecycleProfiler\is_session_active' ) );
	}

	/**
	 * A confirmed session needs init, so a request that ends before init gets no results.
	 */
	public function test_confirmed_session_needs_init() {
		wp_set_current_user( self::$admin_id );
		$this->set_cookie( create_session_token( self::$admin_id, time() + 60 ) );

		$this->assertTrue( is_session_confirmed() );
		$this->assertFalse( $this->before_init( 'LifecycleProfiler\is_session_confirmed' ) );
	}

	/**
	 * Before init, an invalid cookie is still no session.
	 */
	public function test_before_init_rejects_invalid_cookie() {
		$this->set_cookie( create_session_token( self::$admin_id, time() - 1 ) );

		$this->assertFalse( $this->before_init( 'LifecycleProfiler\is_session_active' ) );
	}
}
