<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || die();

delete_option( 'wwhwl_slug' );

if ( is_multisite() ) {
	$network = get_network();
	if ( $network ) {
		$offset = 0;
		$number = 100;

		do {
			$sites = get_sites( [
				'network_id' => $network->id,
				'offset'     => $offset,
				'number'     => $number,
				'fields'     => 'ids',
			] );

			foreach ( $sites as $site_id ) {
				switch_to_blog( $site_id );
				delete_option( 'wwhwl_slug' );
			}

			restore_current_blog();
			$offset += count( $sites );
		} while ( ! empty( $sites ) );

		delete_site_option( 'wwhwl_slug' );
	}
}
