<?php
namespace FortAwesome;

/**
 * Class ConflictDetectionControllerTest
 *
 * @noinspection PhpCSValidationInspection
 */
// phpcs:ignoreFile Squiz.Commenting.ClassComment.Missing
// phpcs:ignoreFile Generic.Commenting.DocComment.MissingShort
require_once dirname( __FILE__ ) . '/_support/font-awesome-phpunit-util.php';

use \DateTime, \DateInterval, \DateTimeInterface, \DateTimeZone;

/**
 * Class ConflictDetectionControllerTest
 */
class ConflictDetectionControllerTest extends \WP_UnitTestCase {
	protected $server;
	protected $admin_user;
	protected $namespaced_route = "/" . FontAwesome::REST_API_NAMESPACE . '/report-conflicts';
	protected $fa;

	public function setUp() {
		reset_db();
		FontAwesome::reset();
		$this->set_options('5.4.1');

		global $wp_rest_server;

		$this->server = $wp_rest_server = new \WP_REST_Server;
		$this->admin_user = get_users( [ 'role' => 'administrator' ] )[0];

		wp_set_current_user( $this->admin_user->ID, $this->admin_user->user_login );

		add_action(
			'rest_api_init',
			array(
				new FontAwesome_Conflict_Detection_Controller(
					FontAwesome::PLUGIN_NAME,
					FontAwesome::REST_API_NAMESPACE
				),
				'register_routes',
			)
		);

		do_action( 'rest_api_init' );
	}

	public function set_options( $version ) {
		update_option(
			FontAwesome::OPTIONS_KEY,
			array_merge(
				FontAwesome::DEFAULT_USER_OPTIONS,
				[ 'version' => $version ]
			)
		);
	}

	public function test_register_route() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( $this->namespaced_route, $routes );
  }

  public function test_when_detecting_conflicts() {
		$now = new DateTime('now', new DateTimeZone('UTC'));
		// ten minutes later
		$later = $now->add(new DateInterval('PT10M'));

		update_option(
			FontAwesome::OPTIONS_KEY,
			array_merge(
				FontAwesome::DEFAULT_USER_OPTIONS,
				array(
					'detectConflictsUntil' => $later->format(DateTimeInterface::ATOM)
				)
			)
		);

    $body = array(
      'abc123' => array(
        'type' => 'script',
        'src'  => 'http://example.com/fake.js'
      ),
      'xyz456' => array(
        'type' => 'style',
        'src'  => 'http://example.com/fake.css'
      )
    );

		$request  = new \WP_REST_Request(
			'POST',
			$this->namespaced_route
		);

    $request->add_header('Content-Type', 'application/json');

    $request->set_body( wp_json_encode( $body ) );

    $response = $this->server->dispatch( $request );
    
    $this->assertEquals( 204, $response->get_status() );
    
    $this->assertEquals(
      $body,
      fa()->unregistered_clients()
    );
  }

  public function test_when_not_detecting_conflicts() {
		update_option(
			FontAwesome::OPTIONS_KEY,
			array_merge(
				FontAwesome::DEFAULT_USER_OPTIONS,
				array(
					'detectConflictsUntil' => null
				)
			)
		);

    $body = array(
      'abc123' => array(
        'type' => 'script',
        'src'  => 'http://example.com/fake.js'
      ),
    );

		$request  = new \WP_REST_Request(
			'POST',
			$this->namespaced_route
		);

    $request->add_header('Content-Type', 'application/json');

    $request->set_body( wp_json_encode( $body ) );

    $response = $this->server->dispatch( $request );
    
    $this->assertEquals( 404, $response->get_status() );
    
    $this->assertEquals(
      array(),
      fa()->unregistered_clients()
    );
  }

  public function test_when_adding_additional_conflicts() {
		$now = new DateTime('now', new DateTimeZone('UTC'));
		// ten minutes later
		$later = $now->add(new DateInterval('PT10M'));

		update_option(
			FontAwesome::OPTIONS_KEY,
			array_merge(
				FontAwesome::DEFAULT_USER_OPTIONS,
				array(
					'detectConflictsUntil' => $later->format(DateTimeInterface::ATOM)
				)
			)
		);

    update_option(
			FontAwesome::UNREGISTERED_CLIENTS_OPTIONS_KEY,
      array(
        'abc123' => array(
          'type' => 'script',
          'src'  => 'http://example.com/fake.js'
        ),
      )
		);

    $body = array(
      'xyz456' => array(
        'type' => 'style',
        'src'  => 'http://example.com/fake.css'
      ),
    );

		$request  = new \WP_REST_Request(
			'POST',
			$this->namespaced_route
		);

    $request->add_header('Content-Type', 'application/json');

    $request->set_body( wp_json_encode( $body ) );

    $response = $this->server->dispatch( $request );
    
    $this->assertEquals( 204, $response->get_status() );
    
    $this->assertEquals(
      array(
        'abc123' => array(
          'type' => 'script',
          'src'  => 'http://example.com/fake.js'
        ),
        'xyz456' => array(
          'type' => 'style',
          'src'  => 'http://example.com/fake.css'
        ),
      ),
      fa()->unregistered_clients()
    );
  }

  public function test_change_detection() {
		$now = new DateTime('now', new DateTimeZone('UTC'));
		// ten minutes later
		$later = $now->add(new DateInterval('PT10M'));

		update_option(
			FontAwesome::OPTIONS_KEY,
			array_merge(
				FontAwesome::DEFAULT_USER_OPTIONS,
				array(
					'detectConflictsUntil' => $later->format(DateTimeInterface::ATOM)
				)
			)
		);

    update_option(
			FontAwesome::UNREGISTERED_CLIENTS_OPTIONS_KEY,
      array(
        'abc123' => array(
          'type' => 'script',
          'src'  => 'http://example.com/fake.js'
        ),
      )
		);

    // No change
    $body = array(
      'abc123' => array(
        'type' => 'script',
        'src'  => 'http://example.com/fake.js'
      ),
    );

		$request  = new \WP_REST_Request(
			'POST',
			$this->namespaced_route
		);

    $request->add_header('Content-Type', 'application/json');

    $request->set_body( wp_json_encode( $body ) );

    $response = $this->server->dispatch( $request );

    // The controller should just return a successful response, making no change
    $this->assertEquals( 204, $response->get_status() );
    
    $this->assertEquals(
      array(
        'abc123' => array(
          'type' => 'script',
          'src'  => 'http://example.com/fake.js'
        ),
      ),
      fa()->unregistered_clients()
    );

    // Change only in the value of a sub-array
    $body = array(
      'abc123' => array(
        'type' => 'style',
        'src'  => 'http://example.com/fake.js'
      ),
    );

		$request  = new \WP_REST_Request(
			'POST',
			$this->namespaced_route
		);

    $request->add_header('Content-Type', 'application/json');

    $request->set_body( wp_json_encode( $body ) );

    $response = $this->server->dispatch( $request );

    $this->assertEquals( 204, $response->get_status() );
    
    // Expect that an update was successfully applied
    $this->assertEquals(
      array(
        'abc123' => array(
          'type' => 'style',
          'src'  => 'http://example.com/fake.js'
        ),
      ),
      fa()->unregistered_clients()
    );
  }
}