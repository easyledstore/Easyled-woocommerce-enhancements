<?php

/**
 * GitHub release updater bootstrap for the plugin.
 *
 * @package Easyled_Woocommerce_Enhancements
 */

class Easyled_Woocommerce_Enhancements_Updater {

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Main plugin file path.
	 * @param string $current_version Current plugin version.
	 * @param string $repository_url GitHub repository URL.
	 */
	public function __construct( $plugin_file, $current_version, $repository_url ) {
		if ( ! class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
			return;
		}

		$plugin_file     = (string) $plugin_file;
		$current_version  = (string) $current_version;
		$repository_url   = trim( (string) $repository_url );
		$plugin_slug      = basename( dirname( $plugin_file ) );
		if ( '.' === $plugin_slug || '' === $plugin_slug ) {
			$plugin_slug = plugin_basename( $plugin_file );
		}
		$release_asset_re = (string) apply_filters( 'easyled_woocommerce_enhancements_github_release_asset_pattern', '/\.zip($|[?&#])/i' );
		$token            = (string) apply_filters( 'easyled_woocommerce_enhancements_github_token', '' );
		$branch           = (string) apply_filters( 'easyled_woocommerce_enhancements_github_branch', '' );

		if ( '' === $repository_url ) {
			return;
		}

		$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			$repository_url,
			$plugin_file,
			$plugin_slug
		);

		if ( is_object( $checker ) && method_exists( $checker, 'getVcsApi' ) ) {
			$vcs_api = $checker->getVcsApi();
			if ( is_object( $vcs_api ) && method_exists( $vcs_api, 'enableReleaseAssets' ) ) {
				$vcs_api->enableReleaseAssets( $release_asset_re );
			}
		}

		if ( '' !== $token && method_exists( $checker, 'setAuthentication' ) ) {
			$checker->setAuthentication( $token );
		}

		if ( '' !== $branch && method_exists( $checker, 'setBranch' ) ) {
			$checker->setBranch( $branch );
		}

		/**
		 * Keep a hard reference around so the checker is not lost immediately.
		 * The object also registers its own hooks internally.
		 */
		$this->checker = $checker;
	}

	/**
	 * Hold the active update checker instance.
	 *
	 * @var object|null
	 */
	private $checker = null;
}
