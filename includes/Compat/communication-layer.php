<?php
/**
 * Aura Chat – Communication Layer v2.2.0
 * - Registers settings
 * - Exposes settings via REST
 * - Localizes settings & nonces to JS (AuraConfig)
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Aura_Communication_Layer' ) ) {
	class Aura_Communication_Layer {

		const OPTION_KEY = 'aura_chat_settings';
		const REST_NS    = 'aura/v1';

		public static function bootstrap() {
			add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
			add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
			add_action( 'wp_enqueue_scripts', [ __CLASS__, 'localize_to_frontend' ], 100 );
			add_action( 'admin_enqueue_scripts', [ __CLASS__, 'localize_to_admin' ], 100 );
		}

		public static function register_settings() {
			register_setting(
				'aura_chat_group',
				self::OPTION_KEY,
				[
					'type'              => 'object',
					'description'       => 'Aura Chat settings',
					'sanitize_callback' => [ __CLASS__, 'sanitize_settings' ],
					'show_in_rest'      => [ 'schema' => [ 'type' => 'object' ] ],
					'default'           => [
						'primary_color'   => '#00bcd4',
						'accent_color'    => '#ff00a6',
						'enable_debug'    => false,
						'ui_variant'      => 'clean',
					],
				]
			);
		}

		public static function sanitize_settings( $value ) {
			if ( ! is_array( $value ) ) return [];
			$out = [];
			$out['primary_color'] = isset( $value['primary_color'] ) ? sanitize_hex_color( $value['primary_color'] ) : '#00bcd4';
			$out['accent_color']  = isset( $value['accent_color'] ) ? sanitize_hex_color( $value['accent_color'] ) : '#ff00a6';
			$out['enable_debug']  = ! empty( $value['enable_debug'] ) ? (bool) $value['enable_debug'] : false;
			$out['ui_variant']    = isset( $value['ui_variant'] ) ? sanitize_text_field( $value['ui_variant'] ) : 'clean';
			return $out;
		}

		public static function register_routes() {
			register_rest_route(
				self::REST_NS,
				'/settings',
				[
					[
						'methods'             => 'GET',
						'callback'            => [ __CLASS__, 'get_settings' ],
						'permission_callback' => '__return_true',
					],
					[
						'methods'             => 'POST',
						'callback'            => [ __CLASS__, 'update_settings' ],
						'permission_callback' => function () { return current_user_can( 'manage_options' ); },
					],
				]
			);
		}

		public static function get_settings( $request ) {
			$settings = get_option( self::OPTION_KEY, [] );
			return rest_ensure_response( $settings );
		}

		public static function update_settings( $request ) {
			$params = $request->get_json_params();
			$san    = self::sanitize_settings( is_array( $params ) ? $params : [] );
			update_option( self::OPTION_KEY, $san );
			return rest_ensure_response( $san );
		}

		protected static function base_localize() {
			return [
				'restBase'   => esc_url_raw( rest_url() ),
				'restNonce'  => wp_create_nonce( 'wp_rest' ),
				'settings'   => get_option( self::OPTION_KEY, [] ),
				'version'    => '2.2.0',
			];
		}

		public static function localize_to_frontend() {
			// Attempt to attach to existing main script; otherwise enqueue our bus
			$handle = 'aura-chatbot'; // common handle name used by previous builds
			$bus    = plugins_url( '../assets/js/aura-bus.js', __FILE__ );
			if ( ! wp_script_is( $handle, 'registered' ) ) {
				wp_register_script( $handle, $bus, [], '2.2.0', true );
				wp_enqueue_script( $handle );
			} else {
				// Ensure bus is enqueued too
				wp_enqueue_script( 'aura-bus', $bus, [], '2.2.0', true );
			}
			wp_localize_script( $handle, 'AuraConfig', self::base_localize() );
		}

		public static function localize_to_admin() {
			// Mirror into admin for settings pages or live preview
			self::localize_to_frontend();
		}
	}

	Aura_Communication_Layer::bootstrap();
}
