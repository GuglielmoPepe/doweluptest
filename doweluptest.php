<?php
/*
 * Plugin name: Doweluptest
 * Description: This simple plugin does nothing, only gets updates from a custom server
 * Version: 1.4
 * Author: Guglielmo Pepe
 * Author URI: https://www.guglielmopepe.it
 * License: GPL
 */

/**/


namespace doweluptest;

// define plugin basename constant
if ( ! \defined( __NAMESPACE__ . '\PLUGIN_BASENAME' ) ) {
	\define( __NAMESPACE__ . '\PLUGIN_BASENAME', \plugin_basename( __FILE__ ) );
}

// define plugin path constant
if ( ! \defined( __NAMESPACE__ . '\PLUGIN_DIR_PATH' ) ) {
	\define( __NAMESPACE__ . '\PLUGIN_DIR_PATH', \plugin_dir_path( __FILE__ ) );
}

// define plugin url constant
if ( ! \defined( __NAMESPACE__ . '\PLUGIN_DIR_URL' ) ) {
	\define( __NAMESPACE__ . '\PLUGIN_DIR_URL', \plugin_dir_url( __FILE__ ) );
}

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

\add_action( 'upgrader_process_complete', function ( $upgrader, $options ) {
	if ( 'update' === $options['action'] && 'plugin' === $options[ 'type' ] ) {
		// just clean the cache when new plugin version is installed
		\delete_transient( __NAMESPACE__ . '_transient_update' );
	}
}, 10, 2 );

\add_action( 'in_plugin_update_message-' . __NAMESPACE__ . '/' . __NAMESPACE__ . '.php', function ( $plugin_info_array, $plugin_info_object ) {
	if ( empty( $plugin_info_array[ 'package' ] ) ) {
		echo ' Please renew your license to update. You can change your license key in Settings > General';
	}
}, 10, 2 );

\add_filter( 'plugins_api', function ( $res, $action, $args ) {

	// do nothing if you're not getting plugin information right now
	if ( 'plugin_information' !== $action ) {
		return $res;
	}

	// do nothing if it is not our plugin
	if ( __NAMESPACE__ !== $args->slug ) {
		return $res;
	}

	// get updates
	$remote = request();

	if ( ! $remote ) {
		return $res;
	}

	$res = new \stdClass();

	$res->name = $remote->name;
	$res->slug = $remote->slug;
	$res->version = $remote->version;
	$res->tested = $remote->tested;
	$res->requires = $remote->requires;
	$res->author = $remote->author;
	$res->author_profile = $remote->author_profile;
	$res->download_link = $remote->download_url;
	$res->trunk = $remote->download_url;
	$res->requires_php = $remote->requires_php;
	$res->last_updated = $remote->last_updated;

	$res->sections = array(
		'description' => $remote->sections->description,
		'installation' => $remote->sections->installation,
		'changelog' => $remote->sections->changelog
	);

	if ( ! empty( $remote->banners ) ) {
		$res->banners = array(
			'low' => $remote->banners->low,
			'high' => $remote->banners->high
		);
	}

	return $res;

}, 20, 3 );

\add_filter( 'site_transient_update_plugins', function ( $transient ) {

	if ( empty($transient->checked ) ) {
		return $transient;
	}

    $plugin_data = \get_plugin_data( PLUGIN_DIR_PATH . __NAMESPACE__ . '.php', false, false );

	$remote = request();

	if (
		$remote
		&& \version_compare( $plugin_data['Version'], $remote->version, '<' )
		&& \version_compare( $remote->requires, \get_bloginfo( 'version' ), '<=' )
		&& \version_compare( $remote->requires_php, \PHP_VERSION, '<' )
	) {

		$res = new \stdClass();
		$res->slug = __NAMESPACE__;
		$res->plugin = PLUGIN_BASENAME;
		$res->new_version = $remote->version;
		$res->tested = $remote->tested;
		$res->package = $remote->download_url;

		$transient->response[ $res->plugin ] = $res;

	}

	return $transient;

} );

function request() {

	$remote = \get_transient( __NAMESPACE__ . '_transient_update' );

	if ( false === $remote ) {

		$remote = \wp_remote_get(
			\add_query_arg( 
				array(
					'license_key' => \urlencode( \get_option( __NAMESPACE__ . '_license_key', __NAMESPACE__ . '_license_key' ) )
				), 
				'https://scribbles.phpapi.it/dowels/' . __NAMESPACE__ . '/info.php'
			), 
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept' => 'application/json'
				)
			)
		);

		if (
			\is_wp_error( $remote )
			|| 200 !== \wp_remote_retrieve_response_code( $remote )
			|| empty( \wp_remote_retrieve_body( $remote ) )
		) {
			return false;
		}

		\set_transient( __NAMESPACE__ . '_transient_update', $remote, 300 );

	}

	$remote = \json_decode( \wp_remote_retrieve_body( $remote ) );

	return $remote;

}

