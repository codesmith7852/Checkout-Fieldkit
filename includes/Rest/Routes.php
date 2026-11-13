<?php
/**
 * The plugin's REST routes.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Rest;

use CheckoutFieldKit\Domain;
use CheckoutFieldKit\Fields\CheckoutPage;
use CheckoutFieldKit\Fields\CheckoutType;
use CheckoutFieldKit\Fields\Field;
use CheckoutFieldKit\Service;
use CheckoutFieldKit\Storage\SettingsRepository;
use CheckoutFieldKit\Tools\ImportExport;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * The admin app's only way to read and write data (spec §7), under
 * checkout-fieldkit/v1. Every route needs manage_woocommerce: WordPress
 * answers 401 to visitors who are not logged in and 403 to other users.
 *
 * Request bodies and responses are JSON. Field sets, settings and import
 * documents are checked by the domain, which reports every problem at once:
 * 422 with {code, message, data: {status: 422, errors: [...]}}. Malformed
 * request parameters (an unknown export or reset target) get WordPress's own
 * 400 rest_invalid_param or rest_missing_callback_param.
 */
final class Routes implements Service {

	/**
	 * Route namespace.
	 */
	public const NAMESPACE = 'checkout-fieldkit/v1';

	/**
	 * Capability every route needs.
	 */
	public const CAPABILITY = 'manage_woocommerce';

	/**
	 * Domain objects.
	 *
	 * @var Domain
	 */
	private $domain;

	/**
	 * The store's Checkout page.
	 *
	 * @var CheckoutPage
	 */
	private $checkout_page;

	/**
	 * Constructor.
	 *
	 * @param Domain            $domain        Domain objects.
	 * @param CheckoutPage|null $checkout_page The store's Checkout page; a new one when null.
	 */
	public function __construct( Domain $domain, ?CheckoutPage $checkout_page = null ) {
		$this->domain        = $domain;
		$this->checkout_page = null === $checkout_page ? new CheckoutPage() : $checkout_page;
	}

	/**
	 * Registers the routes when the REST API starts.
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_pre_dispatch', array( $this, 'drop_scalar_body' ), 10, 3 );
	}

	/**
	 * Treats a JSON body that is a bare string, number or boolean as no body,
	 * for this plugin's routes only. WordPress loops over the JSON body when it
	 * checks arguments and warns when it is not an object or list. Every route
	 * takes an object, so the route then refuses the request with its usual 422.
	 *
	 * @param mixed           $result  Response to send instead of dispatching, if any.
	 * @param WP_REST_Server  $server  Server.
	 * @param WP_REST_Request $request Request.
	 * @return mixed $result unchanged.
	 */
	public function drop_scalar_body( $result, $server, $request ) {
		if ( $request instanceof WP_REST_Request && 0 === strpos( $request->get_route(), '/' . self::NAMESPACE . '/' ) ) {
			$json = $request->get_json_params();
			if ( null !== $json && ! is_array( $json ) ) {
				$request->set_body( '' );
			}
		}

		return $result;
	}

	/**
	 * Registers every route.
	 */
	public function register_routes(): void {
		$permission = array( $this, 'can_manage' );

		register_rest_route(
			self::NAMESPACE,
			'/context',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_context' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/field-sets/(?P<checkout_type>classic|block)',
			array(
				// The route pattern already allows only these; the handlers read the
				// value from the route, so a checkout_type key in a body is ignored.
				'args'   => array(
					'checkout_type' => self::described(
						array(
							'description' => __( 'Checkout type of the field set.', 'checkout-fieldkit' ),
							'type'        => 'string',
							'enum'        => CheckoutType::all(),
						)
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_field_set' ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_field_set' ),
					'permission_callback' => $permission,
					'args'                => self::body_args( self::field_set_schema()['properties'] ),
				),
				'schema' => array( self::class, 'field_set_schema' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => $permission,
					'args'                => self::body_args( self::settings_schema()['properties'] ),
				),
				'schema' => array( self::class, 'settings_schema' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/export',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export' ),
				'permission_callback' => $permission,
				'args'                => array(
					'include' => array(
						'description' => __( 'What to export: a list, or a comma-separated string, of classic, block and settings.', 'checkout-fieldkit' ),
						'type'        => 'array',
						'items'       => array(
							'type' => 'string',
							'enum' => ImportExport::TARGETS,
						),
						'minItems'    => 1,
						'default'     => ImportExport::TARGETS,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/import/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview_import' ),
				'permission_callback' => $permission,
				'args'                => self::body_args( self::document_schema()['properties'] ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'import' ),
				'permission_callback' => $permission,
				'args'                => self::body_args( self::document_schema()['properties'] ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/reset',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reset' ),
				'permission_callback' => $permission,
				'args'                => array(
					'target' => array(
						'description' => __( 'What to reset: classic, block or settings.', 'checkout-fieldkit' ),
						'type'        => 'string',
						'enum'        => ImportExport::TARGETS,
						'required'    => true,
					),
				),
			)
		);
	}

	/**
	 * Whether the current user may use the routes.
	 *
	 * @return bool
	 */
	public function can_manage(): bool {
		return current_user_can( self::CAPABILITY );
	}

	/**
	 * GET /context.
	 *
	 * @return WP_REST_Response
	 */
	public function get_context(): WP_REST_Response {
		return new WP_REST_Response( ( new Context( $this->domain, $this->checkout_page ) )->to_array() );
	}

	/**
	 * GET /field-sets/{checkout_type}: the stored field set, or the defaults.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_field_set( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( $this->domain->field_sets( self::checkout_type( $request ) )->get() );
	}

	/**
	 * PUT /field-sets/{checkout_type}: replaces the whole field set and
	 * returns it as saved.
	 *
	 * @param WP_REST_Request $request Request; the JSON body is the field set.
	 * @return WP_REST_Response|WP_Error 422 checkout_fieldkit_invalid_field_set when invalid.
	 */
	public function update_field_set( WP_REST_Request $request ) {
		$field_sets = $this->domain->field_sets( self::checkout_type( $request ) );
		$result     = $field_sets->save( $request->get_json_params() );

		return is_wp_error( $result ) ? $result : new WP_REST_Response( $field_sets->get() );
	}

	/**
	 * GET /settings.
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings(): WP_REST_Response {
		return new WP_REST_Response( $this->domain->settings()->get() );
	}

	/**
	 * PUT /settings: replaces the settings and returns them as saved.
	 *
	 * @param WP_REST_Request $request Request; the JSON body is the settings.
	 * @return WP_REST_Response|WP_Error 422 checkout_fieldkit_invalid_settings when invalid.
	 */
	public function update_settings( WP_REST_Request $request ) {
		$result = $this->domain->settings()->save( $request->get_json_params() );

		return is_wp_error( $result ) ? $result : new WP_REST_Response( $this->domain->settings()->get() );
	}

	/**
	 * GET /export: the export document, with a suggested file name in
	 * Content-Disposition.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function export( WP_REST_Request $request ): WP_REST_Response {
		$import_export = $this->domain->import_export();
		$response      = new WP_REST_Response( $import_export->export( (array) $request->get_param( 'include' ) ) );
		$response->header( 'Content-Disposition', sprintf( 'attachment; filename="%s"', $import_export->file_name() ) );

		return $response;
	}

	/**
	 * POST /import/preview: what importing the document would change.
	 *
	 * @param WP_REST_Request $request Request; the JSON body is an export document.
	 * @return WP_REST_Response|WP_Error 422 checkout_fieldkit_invalid_import when invalid.
	 */
	public function preview_import( WP_REST_Request $request ) {
		$preview = $this->domain->import_export()->preview( $request->get_json_params() );

		return is_wp_error( $preview ) ? $preview : new WP_REST_Response( $preview );
	}

	/**
	 * POST /import: replaces every target the document contains. Returns
	 * {imported: [target, ...], <target>: <value now in effect>, ...}.
	 *
	 * @param WP_REST_Request $request Request; the JSON body is an export document.
	 * @return WP_REST_Response|WP_Error 422 checkout_fieldkit_invalid_import when invalid.
	 */
	public function import( WP_REST_Request $request ) {
		$document = $request->get_json_params();
		$result   = $this->domain->import_export()->import( $document );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$data = array( 'imported' => array() );
		foreach ( ImportExport::TARGETS as $target ) {
			if ( array_key_exists( $target, $document ) ) {
				$data['imported'][] = $target;
				$data[ $target ]    = $this->current( $target );
			}
		}

		return new WP_REST_Response( $data );
	}

	/**
	 * POST /reset: returns a field set or the settings to the defaults.
	 * Returns {reset: <target>, <target>: <value now in effect>}.
	 *
	 * @param WP_REST_Request $request Request with `target`.
	 * @return WP_REST_Response
	 */
	public function reset( WP_REST_Request $request ): WP_REST_Response {
		$target = (string) $request->get_param( 'target' );

		if ( 'settings' === $target ) {
			$this->domain->settings()->reset();
		} else {
			$this->domain->field_sets( $target )->reset();
		}

		return new WP_REST_Response(
			array(
				'reset' => $target,
				$target => $this->current( $target ),
			)
		);
	}

	/**
	 * JSON schema of a field set, as GET returns it and PUT takes it.
	 *
	 * @return array<string, mixed>
	 */
	public static function field_set_schema(): array {
		$string_list = array(
			'type'  => 'array',
			'items' => array( 'type' => 'string' ),
		);

		$field = array(
			'type'       => 'object',
			'properties' => array(
				'name'             => array(
					'type'    => 'string',
					'pattern' => '^[a-z][a-z0-9_]{0,59}$',
				),
				'type'             => array( 'type' => 'string' ),
				'builtIn'          => array( 'type' => 'boolean' ),
				'enabled'          => array( 'type' => 'boolean' ),
				'required'         => array( 'type' => 'boolean' ),
				'label'            => array( 'type' => 'string' ),
				'placeholder'      => array( 'type' => 'string' ),
				'default'          => array( 'type' => 'string' ),
				'options'          => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'value' => array( 'type' => 'string' ),
							'label' => array( 'type' => 'string' ),
						),
					),
				),
				'validation'       => $string_list,
				'classes'          => $string_list,
				'headingLevel'     => array(
					'type' => 'string',
					'enum' => Field::HEADING_LEVELS,
				),
				'showInEmails'     => array( 'type' => 'boolean' ),
				'showOnOrderPages' => array( 'type' => 'boolean' ),
			),
		);

		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'checkout-fieldkit-field-set',
			'type'       => 'object',
			'properties' => array(
				'version'  => array(
					'description' => __( 'Schema version of the field set.', 'checkout-fieldkit' ),
					'type'        => 'integer',
				),
				'sections' => array(
					'description'          => __( 'Fields of each section, in display order, keyed by section name.', 'checkout-fieldkit' ),
					'type'                 => 'object',
					'additionalProperties' => array(
						'type'  => 'array',
						'items' => $field,
					),
				),
			),
		);
	}

	/**
	 * JSON schema of the settings, as GET returns them and PUT takes them.
	 *
	 * @return array<string, mixed>
	 */
	public static function settings_schema(): array {
		$overrides = array();
		foreach ( SettingsRepository::LOCALE_OVERRIDES as $key => $default ) {
			$overrides[ $key ] = array(
				'type'    => 'boolean',
				'default' => $default,
			);
		}

		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'checkout-fieldkit-settings',
			'type'       => 'object',
			'properties' => array(
				'version'         => array(
					'description' => __( 'Schema version of the settings.', 'checkout-fieldkit' ),
					'type'        => 'integer',
				),
				'localeOverrides' => array(
					'description' => __( 'Which of your address field settings to keep in every country instead of the country-specific ones.', 'checkout-fieldkit' ),
					'type'        => 'object',
					'properties'  => $overrides,
				),
			),
		);
	}

	/**
	 * JSON schema of an export document.
	 *
	 * @return array<string, mixed>
	 */
	public static function document_schema(): array {
		$field_set = self::field_set_schema();
		$settings  = self::settings_schema();
		unset( $field_set['$schema'], $field_set['title'], $settings['$schema'], $settings['title'] );

		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'checkout-fieldkit-export',
			'type'       => 'object',
			'properties' => array(
				'plugin'     => array(
					'description' => __( 'Always checkout-fieldkit.', 'checkout-fieldkit' ),
					'type'        => 'string',
					'enum'        => array( ImportExport::PLUGIN ),
				),
				'format'     => array(
					'description' => __( 'Format version of the document.', 'checkout-fieldkit' ),
					'type'        => 'integer',
				),
				'exportedAt' => array(
					'description' => __( 'When the document was exported.', 'checkout-fieldkit' ),
					'type'        => 'string',
					'format'      => 'date-time',
				),
				'classic'    => $field_set,
				'block'      => $field_set,
				'settings'   => $settings,
			),
		);
	}

	/**
	 * Arguments that describe a JSON body. The domain checks the whole body
	 * and reports every problem in one 422 response, so WordPress's own
	 * argument checks (which stop at the first problem, answer 400 and coerce
	 * values such as "1" to true) are switched off for them.
	 *
	 * @param array<string, array<string, mixed>> $properties Schema properties of the body.
	 * @return array<string, array<string, mixed>>
	 */
	private static function body_args( array $properties ): array {
		return array_map( array( self::class, 'described' ), $properties );
	}

	/**
	 * An argument that is described by its schema but not checked or changed
	 * by WordPress.
	 *
	 * @param array<string, mixed> $schema Schema of the argument.
	 * @return array<string, mixed>
	 */
	private static function described( array $schema ): array {
		return array_merge(
			$schema,
			array(
				'validate_callback' => null,
				'sanitize_callback' => null,
			)
		);
	}

	/**
	 * The checkout type in the route, never one from the body or query.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string
	 */
	private static function checkout_type( WP_REST_Request $request ): string {
		$params = $request->get_url_params();

		return (string) $params['checkout_type'];
	}

	/**
	 * The field set or settings now in effect.
	 *
	 * @param string $target 'classic', 'block' or 'settings'.
	 * @return array<string, mixed>
	 */
	private function current( string $target ): array {
		return 'settings' === $target ? $this->domain->settings()->get() : $this->domain->field_sets( $target )->get();
	}
}
