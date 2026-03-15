<?php
/**
 * Uninstall FF Signature Field.
 *
 * Fired when the plugin is deleted through the WordPress admin.
 * Removes the ff-signatures upload directory and all saved images.
 *
 * @package FF_Signature_Field
 * @since   2.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Recursively delete a directory and its contents.
 *
 * @param string $dir Absolute path to the directory.
 * @return void
 */
function ffsig_delete_directory( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}

	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $items as $item ) {
		if ( $item->isDir() ) {
			rmdir( $item->getRealPath() ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		} else {
			unlink( $item->getRealPath() ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
	}

	rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions
}

$ffsig_upload_dir     = wp_upload_dir();
$ffsig_signatures_dir = $ffsig_upload_dir['basedir'] . '/ff-signatures';

if ( is_dir( $ffsig_signatures_dir ) ) {
	ffsig_delete_directory( $ffsig_signatures_dir );
}
