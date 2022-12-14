<?php
/**
 * Loader.
 *
 * @package ThemeIsle
 */

namespace ThemeIsle\GutenbergBlocks;

use ThemeIsle\GutenbergBlocks\Server\Dashboard_Server;

/**
 * Class Main
 */
class Main {
	/**
	 * Singleton.
	 *
	 * @var Main Class object.
	 */
	protected static $instance = null;

	/**
	 * GutenbergBlocks constructor.
	 *
	 * @since   1.0.0
	 * @access  public
	 */
	public function __construct() {
		$this->name        = __( 'Otter', 'otter-blocks' );
		$this->description = __( 'Blocks for Gutenberg', 'otter-blocks' );
	}

	/**
	 * Method to define hooks needed.
	 *
	 * @since   1.0.0
	 * @access  public
	 */
	public function init() {
		if ( ! defined( 'THEMEISLE_BLOCKS_VERSION' ) ) {
			define( 'THEMEISLE_BLOCKS_VERSION', '1.7.0' );
		}

		add_action( 'init', array( $this, 'autoload_classes' ), 9 );
		add_filter( 'script_loader_tag', array( $this, 'filter_script_loader_tag' ), 10, 2 );
		add_filter( 'safe_style_css', array( $this, 'used_css_properties' ), 99 );
		add_action( 'init', array( $this, 'after_update_migration' ) );

		if ( ! function_exists( 'is_wpcom_vip' ) ) {
			add_filter( 'upload_mimes', array( $this, 'allow_json_svg' ) ); // phpcs:ignore WordPressVIPMinimum.Hooks.RestrictedHooks.upload_mimes
			add_filter( 'wp_check_filetype_and_ext', array( $this, 'fix_mime_type_json_svg' ), 75, 4 );
		}
	}

	/**
	 * Autoload classes for each block.
	 *
	 * @since   1.0.0
	 * @access  public
	 */
	public function autoload_classes() {
		load_plugin_textdomain( 'otter-blocks', false, basename( OTTER_BLOCKS_PATH ) . '/languages' );

		$classnames = array(
			'\ThemeIsle\GutenbergBlocks\Registration',
			'\ThemeIsle\GutenbergBlocks\Pro',
			'\ThemeIsle\GutenbergBlocks\Blocks_Export_Import',
			'\ThemeIsle\GutenbergBlocks\CSS\Block_Frontend',
			'\ThemeIsle\GutenbergBlocks\CSS\CSS_Handler',
			'\ThemeIsle\GutenbergBlocks\Plugins\Block_Conditions',
			'\ThemeIsle\GutenbergBlocks\Plugins\Dashboard',
			'\ThemeIsle\GutenbergBlocks\Plugins\Dynamic_Content',
			'\ThemeIsle\GutenbergBlocks\Plugins\Options_Settings',
			'\ThemeIsle\GutenbergBlocks\Render\Masonry_Variant',
			'\ThemeIsle\GutenbergBlocks\Server\Dashboard_Server',
			'\ThemeIsle\GutenbergBlocks\Server\Dynamic_Content_Server',
			'\ThemeIsle\GutenbergBlocks\Server\Plugin_Card_Server',
			'\ThemeIsle\GutenbergBlocks\Integration\Form_Providers',
			'\ThemeIsle\GutenbergBlocks\Integration\Form_Email',
			'\ThemeIsle\GutenbergBlocks\Server\Form_Server',
		);

		$classnames = apply_filters( 'otter_blocks_autoloader', $classnames );

		foreach ( $classnames as $classname ) {
			$classname = new $classname();

			if ( method_exists( $classname, 'instance' ) ) {
				$classname->instance();
			}
		}

		if ( class_exists( '\ThemeIsle\GutenbergBlocks\Blocks_CSS' ) && get_option( 'themeisle_blocks_settings_css_module', true ) ) {
			\ThemeIsle\GutenbergBlocks\Blocks_CSS::instance();
		}

		if ( class_exists( '\ThemeIsle\GutenbergBlocks\Blocks_Animation' ) && get_option( 'themeisle_blocks_settings_blocks_animation', true ) ) {
			\ThemeIsle\GutenbergBlocks\Blocks_Animation::instance();
		}
	}

	/**
	 * Get if the version of plugin in latest.
	 *
	 * @since   1.2.0
	 * @access  public
	 */
	public static function is_compatible() {
		if ( ! function_exists( 'plugins_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}

		if ( ! defined( 'OTTER_BLOCKS_VERSION' ) ) {
			return true;
		}

		$current = OTTER_BLOCKS_VERSION;

		$args = array(
			'slug'   => 'otter-blocks',
			'fields' => array(
				'version' => true,
			),
		);

		$call_api = plugins_api( 'plugin_information', $args );

		if ( is_wp_error( $call_api ) ) {
			return true;
		} else {
			if ( ! empty( $call_api->version ) ) {
				$latest = $call_api->version;
			}
		}

		return version_compare( $current, $latest, '>=' );
	}

	/**
	 * Get global defaults.
	 *
	 * @since   1.4.0
	 * @access  public
	 */
	public static function get_global_defaults() {
		$defaults = get_theme_support( 'otter_global_defaults' );
		if ( ! is_array( $defaults ) ) {
			return false;
		}

		return current( $defaults );
	}

	/**
	 * Adds async/defer attributes to enqueued / registered scripts.
	 *
	 * If #12009 lands in WordPress, this function can no-op since it would be handled in core.
	 *
	 * @link https://core.trac.wordpress.org/ticket/12009
	 *
	 * @param string $tag The script tag.
	 * @param string $handle The script handle.
	 *
	 * @return string Script HTML string.
	 */
	public function filter_script_loader_tag( $tag, $handle ) {
		foreach ( array( 'async', 'defer' ) as $attr ) {
			if ( ! wp_scripts()->get_data( $handle, $attr ) ) {
				continue;
			}
			// Prevent adding attribute when already added in #12009.
			if ( ! preg_match( ":\s$attr(=|>|\s):", $tag ) ) {
				$tag = preg_replace( ':(?=></script>):', " $attr", $tag, 1 );
			}
			// Only allow async or defer, not both.
			break;
		}

		return $tag;
	}

	/**
	 * Used CSS properties
	 *
	 * @param array $attr Array to check.
	 *
	 * @return array
	 * @since   2.0.0
	 * @access  public
	 */
	public function used_css_properties( $attr ) {
		$props = array(
			'background-attachment',
			'background-position',
			'background-repeat',
			'background-size',
			'border-radius',
			'border-top-left-radius',
			'border-top-right-radius',
			'border-bottom-right-radius',
			'border-bottom-left-radius',
			'box-shadow',
			'display',
			'justify-content',
			'mix-blend-mode',
			'opacity',
			'text-shadow',
			'text-transform',
			'transform',
		);

		$list = array_merge( $props, $attr );

		return $list;
	}

	/**
	 * Allow JSON uploads
	 *
	 * @param array $mimes Supported mimes.
	 *
	 * @return array
	 * @since  1.5.7
	 * @access public
	 */
	public function allow_json_svg( $mimes ) {
		$mimes['json'] = 'application/json';
		$mimes['svg']  = 'image/svg+xml';
		return $mimes;
	}

	/**
	 * Allow JSON uploads
	 *
	 * @param null $data File data.
	 * @param null $file File object.
	 * @param null $filename File name.
	 * @param null $mimes Supported mimes.
	 *
	 * @return array
	 * @since  1.5.7
	 * @access public
	 */
	public function fix_mime_type_json_svg( $data = null, $file = null, $filename = null, $mimes = null ) {
		$ext = isset( $data['ext'] ) ? $data['ext'] : '';
		if ( 1 > strlen( $ext ) ) {
			$exploded = explode( '.', $filename );
			$ext      = strtolower( end( $exploded ) );
		}
		if ( 'json' === $ext ) {
			$data['type'] = 'application/json';
			$data['ext']  = 'json';
		}
		if ( 'svg' === $ext ) {
			$data['type'] = 'image/svg+xml';
			$data['ext']  = 'svg';
		}
		return $data;
	}


	/**
	 * After Update Migration
	 *
	 * @return bool
	 * @since  2.0.9
	 * @access public
	 */
	public function after_update_migration() {
		$db_version = get_option( 'themeisle_blocks_db_version', 0 );

		// We don't want to regenerate block styles for every update,
		// only if user is switching from an older version to 2.0.9 or above.
		if ( version_compare( $db_version, '2.0.9', '<' ) ) {
			Dashboard_Server::regenerate_styles();
		}

		return update_option( 'themeisle_blocks_db_version', OTTER_BLOCKS_VERSION );
	}

	/**
	 * Singleton method.
	 *
	 * @static
	 *
	 * @return  GutenbergBlocks
	 * @since   1.0.0
	 * @access  public
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
			self::$instance->init();
		}

		return self::$instance;
	}

	/**
	 * Throw error on object clone
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object therefore, we don't want the object to be cloned.
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function __clone() {
		// Cloning instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, 'Cheatin&#8217; huh?', '1.0.0' );
	}

	/**
	 * Disable unserializing of the class
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function __wakeup() {
		// Unserializing instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, 'Cheatin&#8217; huh?', '1.0.0' );
	}
}
