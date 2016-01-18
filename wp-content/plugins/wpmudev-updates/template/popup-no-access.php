<?php
/**
 * Dashboard popup template: No Access!
 *
 * This popup is displayed when a user is logged in and can view the current
 * Dashboard page, but the WPMUDEV account does not allow him to use the
 * features on the current page.
 * Usually this is displayed when a member has a single license and visits the
 * Plugins or Themes page (he cannot install new plugins or themes).
 *
 * Following variables are passed into the template:
 *   $is_logged_in
 *   $urls
 *   $username
 *   $reason
 */

$url_upgrade = $urls->remote_site . '#pricing';
$url_logout = $urls->dashboard_url . '&clear_key=1';

switch ( $reason ) {
	case 'free':
		$reason_text = __( "%s, to get access to all of our premium plugins and themes, as well as 24/7 support you'll need an <strong>active membership</strong>. It's easy to do and only takes a few minutes!", 'wpmudev' );
		break;

	case 'single':
		$reason_text = __( "%s, to get access to all of our premium plugins and themes, as well as 24/7 support you'll need to upgrade your membership from <strong>single</strong> to <strong>full</strong>. It's easy to do and only takes a few minutes!", 'wpmudev' );
		break;

	default:
		$reason_text = __( "%s, to get access to all of our premium plugins and themes, as well as 24/7 support you'll need to upgrade your membership. It's easy to do and only takes a few minutes!", 'wpmudev' );
		break;
}

?>
<dialog id="test" class="auto-show small no-close" title="<?php _e( 'Upgrade Membership', 'wpmudev' ); ?>">
<div class="dialog-upgrade">
	<p>
	<?php printf( $reason_text, $username ); ?>
	</p>
	<ul class="listing bold">
	<li><?php _e( 'Access to 140+ Plugins & Upfront Themes', 'wpmudev' ); ?></li>
	<li><?php _e( 'Access to Security, Backups, SEO and Performance Services', 'wpmudev' ); ?></li>
	<li><?php _e( '24/7 Expert WordPress Support', 'wpmudev' ); ?></li>
	</ul>
	<p>
	<a href="<?php echo esc_url( $url_upgrade ); ?>" class="block button button-big button-cta" target="_blank">
		<?php _e( 'Upgrade Membership', 'wpmudev' ); ?>
	</a>
	</p>
	<div class="dev-man">
		<img src="<?php echo WPMUDEV_Dashboard::$site->plugin_url ?>/image/devman.svg" />
	</div>
	<?php if ( $is_logged_in ) : ?>
	<p class="below-dev-man">
		<?php
		printf(
			__( 'or %slogout%s', 'wpmudev' ),
			'<a href="' . $url_logout . '">',
			'</a>'
		);
		?>
	</p>
	<?php endif; ?>
</div>
</dialog>