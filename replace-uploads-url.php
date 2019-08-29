<?php
/*
 * Plugin Name: Replace Uploads URL
 * Plugin URI:  https://github.com/victorfreitas/replace-uploads-url
 * Description: Replace development environment to production uploads url
 * Author:      Victor Freitas
 * Version:     2.0.1
 */
defined( 'ABSPATH' ) || exit;

if ( ! defined( 'RUU_PRODUCTION_URL' ) ) {
    define( 'RUU_PRODUCTION_URL', '' );
}

class Replace_Uploads_Url {

    private static $instance = null;

    public $production_url = RUU_PRODUCTION_URL;

    public $option_name = 'ruu_production_url';

    public $field_section = 'ruu_field_section';

    public $title = 'Replace Uploads URL';

    private function __construct() {
        if ( $this->production_url_option() ) {
            $this->init_actions();
        }
    }

    public function production_url_option() {
        if ( ! empty( $this->production_url ) ) {
            return true;
        }

        $this->set_production_url();

        add_action( 'admin_init',  array( $this, 'register_option' ) );

        return ! empty( $this->production_url );
    }

    public function init_actions() {
        if ( is_admin() ) {
            $this->admin_actions();
            return;
        }

        add_action( 'wp_loaded', array( $this, 'begin' ) );
    }

    public function admin_actions() {
        add_action( 'wp_get_attachment_url', array( $this, 'parse_thumb_url' ) );
        add_action( 'wp_calculate_image_srcset', array( $this, 'image_srcset' ), 10, 5 );
    }

    public function begin() {
        ob_start( array( $this, 'replace' ) );
    }

	public function get_domain_host() {
		return parse_url(get_site_url(), PHP_URL_HOST);
	}

    public function replace( $content ) {
        preg_match_all(
            "#(https?:\/\/){$this->get_domain_host()}[/|.|\w|-]*\.(jpe?g|gif|png|svg)#",
            $content,
            $images_match
        );

        if ( empty( $images_match[0] ) ) {
            return $content;
        }

        $images = array_unique( $images_match[0] );

        foreach ( $images as $image_url ) {
            $new_image_url = $this->parse_thumb_url( $image_url );

            if ( $new_image_url ) {
                $content = str_replace( $image_url, $new_image_url, $content );
            }
        }

        return $content;
    }

    public function set_production_url() {
        $this->production_url = untrailingslashit( $this->get_option() );
    }

    public function indexof( $value, $search ) {
        return ( false !== strpos( $value, $search ) );
    }

    public function get_relative_uri( $url ) {
        return preg_replace( '/(https?:\/\/(localhost\/|).+?\/)/', '', $url );
    }

    public function get_uri( $url ) {
        $uri = $this->get_relative_uri( $url );

        return file_exists( ABSPATH . $uri ) ? false : $uri;
    }

    public function parse_thumb_url( $url ) {
        if ( ! $uri = $this->get_uri( $url ) ) {
            return $url;
        }

        return sprintf( '%s/%s', $this->production_url, $uri );
    }

    public function image_srcset( $sources ) {
        foreach ( $sources as $key => $source ) :
            $source['url']   = $this->parse_thumb_url( $source['url'] );
            $sources[ $key ] = $source;
        endforeach;

        return $sources;
    }

    public function register_option() {
        register_setting(
            'general',
            $this->option_name,
            'esc_url'
        );

        add_settings_section(
            $this->field_section,
            $this->title,
            '__return_false',
            'general'
        );

        add_settings_field(
            $this->option_name,
            'Production URL',
            array( $this, 'render_field' ),
            'general',
            $this->field_section
        );
    }

    public function render_field() {
        printf(
            '<input
                type="url"
                name="%s"
                class="regular-text"
                value="%s"
                placeholder="Enter your production URL"
            >',
            $this->option_name,
            $this->get_option()
        );
    }

    public function get_option() {
        return esc_url( get_option( $this->option_name ) );
    }

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}

add_action( 'plugins_loaded', array( 'Replace_Uploads_Url', 'instance' ), -1 );
