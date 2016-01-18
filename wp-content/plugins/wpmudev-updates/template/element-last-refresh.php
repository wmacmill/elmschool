<?php
/**
 * Dashboard popup template: Info on last update-check
 *
 * Will output a single line of text that displays the last update time and
 * a link to check again.
 *
 * Following variables are passed into the template:
 *   - (nonne)
 */

$url_check = wp_nonce_url( add_query_arg( 'action', 'check-updates' ), 'check-updates', 'hash' );
$last_check = WPMUDEV_Dashboard::$site->get_option( 'last_run_updates' );
$last_check = WPMUDEV_Dashboard::$site->to_localtime( $last_check );

$time_format = get_option( 'time_format' );
$day_diff = date( 'Yz', time() ) - date( 'Yz', $last_check );
if ( $day_diff < 1 ) {
	$day_expression = __( 'today', 'wpmudev' );
} elseif ( $day_diff == 1 ) {
	$day_expression = __( 'yesterday', 'wpmudev' );
} else {
	$day_expression = sprintf( __( '%s days ago', 'wpmudev' ), $day_diff );
}

?>
<div class="refresh-infos">
<?php
printf(
	_x( 'We last checked for updates %1$s at %2$s %3$sCheck again%4$s', 'Placeholders: date, time, link-open, link-close', 'wpmudev' ),
	'<strong>' . $day_expression . '</strong>',
	'<strong>' . date_i18n( $time_format, $last_check ) . '</strong>',
	' - <a href="' . esc_url( $url_check ) . '" class="has-spinner"><i class="wdv-icon wdv-icon-refresh spin-on-click"></i> ',
	' </a>'
);
?>
</div>