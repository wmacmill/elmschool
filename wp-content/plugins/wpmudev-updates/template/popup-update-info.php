<?php
/**
 * Dashboard popup template: Update info
 *
 * Will output the contents of a Dashboard popup element with details about the
 * latest project release that we get from the remote site.
 *
 * Following variables are passed into the template:
 *   $pid (project ID)
 */

$item = WPMUDEV_Dashboard::$site->get_project_infos( $pid, true );

if ( $item->is_installed ) {
	$notes = array();
	$release_date = '';
	$date_format = get_option( 'date_format' );

	foreach ( $item->changelog as $log ) {
		if ( version_compare( $log['version'], $item->version_latest, 'eq' ) ) {
			$release_date = date_i18n( $date_format, $log['time'] );
		}
		if ( version_compare( $log['version'], $item->version_installed, 'gt' ) ) {
			$notes += explode( "\n", $log['log'] );
		}
	}
}

if ( ! $item->is_installed ) : ?>
<dialog title="<?php esc_attr_e( 'Plugin not installed', 'wpmudev' ); ?>" class="small">
<p class="tc">
	<?php _e( 'Something unexpected happened.<br>Please wait one moment while we refresh the page...', 'wpmudev' ); ?>
</p>
<script>
	window.setTimeout(function(){ window.location.reload(); }, 2000 );
</script>
</dialog>
<?php else : ?>
<dialog title="<?php esc_attr_e( 'Update Plugin', 'wpmudev' ); ?>" class="small">
<div class="wdp-update" data-project="<?php echo esc_attr( $pid ); ?>">

<div class="title-action">
	<?php if ( $item->is_licensed ) : ?>
		<?php if ( $item->has_update && $item->url->update ) { ?>
		<a href="<?php echo esc_url( $item->url->update ); ?>" class="button button-small button-yellow btn-update-ajax">
			<?php esc_html_e( 'Update Now', 'wpmudev' ); ?>
		</a>
		<?php } else { ?>
		<a href="<?php echo esc_url( $item->url->download ); ?>" class="button button-small">
			<?php esc_html_e( 'Download Now', 'wpmudev' ); ?>
		</a>
		<?php } ?>
	<?php else : ?>
		<a href="#upgrade" class="button button-small" rel="dialog">
			<?php esc_html_e( 'Upgrade', 'wpmudev' ); ?>
		</a>
	<?php endif; ?>
</div>

<table class="update-infos" cellspacing="0" cellpadding="0" border="0">
	<tr>
		<th class="col-1"><?php esc_html_e( 'Name', 'wpmudev' ); ?></th>
		<th class="col-2"><?php esc_html_e( 'Release Date', 'wpmudev' ); ?></th>
		<th class="col-3"><?php esc_html_e( 'Version', 'wpmudev' ); ?></th>
	</tr>
	<tr>
		<td><?php echo esc_html( $item->name ); ?></td>
		<td><?php echo esc_html( $release_date ); ?></td>
		<td>
			<span class="version">
				<?php echo esc_html( $item->version_latest ); ?>
			</span>
			&nbsp;
			<span tooltip="<?php esc_html_e( 'Show changelog', 'wpmudev' ); ?>" class="pointer tooltip-s tooltip-right">
			<i class="show-project-changelog dev-icon dev-icon-info"></i>
			</span>
		</td>
	</tr>
	<tr class="after-update" style="display:none">
		<td colspan="3">
			<div class="update-complete">
				<i class="wdv-icon wdv-icon-ok"></i>
				<?php esc_html_e( 'Update complete!', 'wpmudev' ); ?>
			</div>
		</td>
	</tr>
	<tr class="before-update">
		<th colspan="3"><?php esc_html_e( 'Notes', 'wpmudev' ); ?></th>
	</tr>
	<tr class="before-update">
		<td colspan="3" class="col-notes"><ul>
		<?php
		foreach ( $notes as $note ) {
			$note = stripslashes( $note );
			$note = preg_replace( '/(<br ?\/?>|<p>|<\/p>)/', '', $note );
			$note = trim( preg_replace( '/^\s*(\*|\-)\s*/', '', $note ) );
			$note = str_replace( array( '<', '>' ), array( '&lt;', '&gt;' ), $note );
			$note = preg_replace( '/`(.*?)`/', '<code>\1</code>', $note );
			if ( empty( $note ) ) { continue; }
			printf( '<li>%s</li>', $note );
		}
		?>
		</ul></td>
	</tr>
</table>

<script>
jQuery(function() {
	var btnUpdate = jQuery('.btn-update-ajax'),
		popup = btnUpdate.closest('.box'),
		pid = "<?php echo esc_attr( $pid ); ?>",
		box = jQuery('.project-box.project-' + pid);

	btnUpdate.on('click', function() {
		var data = {};
		data.action = 'wdp-project-update';
		data.hash = "<?php echo esc_attr( wp_create_nonce( 'project-update' ) ); ?>";
		data.pid = pid;
		data.is_network = +(jQuery('body').hasClass('network-admin'));

		popup.loading(true);
		jQuery.post(
			window.ajaxurl,
			data,
			function(response) {
				if (!response || !response.success) {
					if (response && response.data && response.data.message) {
						WDP.showError('message', response.data.message);
					} else {
						WDP.showError('message');
					}
					WDP.showError();
					return;
				}

				btnUpdate.hide();
				popup.find('.before-update').hide();
				popup.find('.after-update').show();

				// Return value is the new project box for project list.
				jQuery(document).trigger(
					'wpmu:show-project',
					[box, response.data.html]
				);

				// Update number in the counter-badges in the menu.
				jQuery(document).trigger( 'wpmu:update-done' );
			},
			'json'
		).always(function() {
			popup.loading(false);
		});

		return false;
	});
});
</script>
</div>
</dialog>
<?php endif; /* is_installed  check */ ?>