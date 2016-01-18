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

// Skip if project-ID is invalid.
$pid = intval( $pid );
if ( ! $pid ) { return; }

$res = WPMUDEV_Dashboard::$site->get_project_infos( $pid );

// Skip invalid projects.
if ( empty( $res->pid ) || empty( $res->name ) ) { return; }

// Skip hidden projects.
if ( $res->is_hidden ) { return; }

$action = false;
$action_url = '#';
$action_class = '';
$action_ajax = '';
$action_attr = false;
$show_badge = false;
$target = '_self';

if ( ! $res->is_installed ) {
	// Plugin/Theme is not installed yet.
	// Possible Actions: Install, Download, Incompatible

	if ( $res->is_compatible && $res->url->install ) {
		$action = __( 'Install', 'wpmudev' );
		$action_ajax = 'project-install';
		$action_url = $res->url->install;
		$action_class = 'button-green button-cta';
	} elseif ( $res->is_compatible ) {
		$action = __( 'Download', 'wpmudev' );
		$action_url = $res->url->download;
		$action_class = 'button-secondary';
		$target = '_blank';
	} else {
		$action = $res->incompatible_reason;
		$action_url = '#';
		$action_class = 'disabled';
	}
} else {
	// Plugin/Theme is installed.
	// Possible Actions: Update, Activate, Deactivate, Install Upfront

	// 1. Check if the project can be updated.
	if ( $res->has_update ) {
		$action = __( 'Update', 'wpmudev' );
		$action_url = '#update-project';
		$action_class = 'has-update button-yellow';

		if ( $res->can_update ) {
			$show_badge = $res->type;
		} else {
			$action_class .= ' disabled';
		}
	}
	// 2.a Deactivate an active plugin (not for themes!)
	elseif ( $res->is_active ) {
		if ( 'plugin' == $res->type ) {
			if ( $res->is_network_admin ) {
				$action = __( 'Network Deactivate', 'wpmudev' );
			} else {
				$action = __( 'Deactivate', 'wpmudev' );
			}
			$action_ajax = 'project-deactivate';
			$action_class = 'button-light';

			if ( ! $res->can_activate ) {
				$action_class .= ' disabled';
			}
		} elseif ( 'theme' == $res->type ) {
			if ( $res->is_network_admin ) {
				// No change for active themes in network admin mode.
			} else {
				// Show a badge for the active theme.
				$show_badge = 'active-theme';
			}
		}
	}
	// 2.b Activate an inactive project (theme or plugin)
	else {
		if ( 'plugin' == $res->type ) {
			if ( $res->is_network_admin ) {
				$action = __( 'Network Activate', 'wpmudev' );
			} else {
				$action = __( 'Activate', 'wpmudev' );
			}
			$action_ajax = 'project-activate';

			if ( ! $res->can_activate ) {
				$action_class .= ' disabled';
			}
		} elseif ( 'theme' == $res->type ) {
			if ( $res->is_network_admin ) {
				// Themes can only be activated in single-site mode.
			} else {
				$action = __( 'Activate', 'wpmudev' );
				$action_ajax = 'project-activate';

				if ( ! $res->can_activate ) {
					$action_class .= ' disabled';
				}
			}
		}
	}
}

// Special Theme checks: Upfront installed?
if ( 'theme' == $res->type ) {
	if ( $res->is_installed && $res->need_upfront ) {
		if ( ! WPMUDEV_Dashboard::$site->is_upfront_installed() ) {
			// This upfront theme needs Upfront parent to work!
			$show_badge = 'warning';

			$id_upfront = WPMUDEV_Dashboard::$site->id_upfront;
			$upfront = WPMUDEV_Dashboard::$site->get_project_infos( $id_upfront );

			$action = __( 'Install Upfront Parent', 'wpmudev' );
			$action_url = $upfront->url->install;
			$action_ajax = 'project-install-upfront';
			$action_class = 'button-red';
		}
	}
}

if ( ! $action ) {
	$action = __( 'Instructions', 'wpmudev' );
	$action_url = $res->url->instructions;
	$action_attr = array(
		'rel="dialog"',
		'data-class="small no-margin"',
		'data-height="600"',
		'data-title="' . sprintf( __( '%s Instructions', 'wpmudev' ), esc_attr( $res->name ) ) . '"',
	);

	$res->url->instructions = false;
}

$minor_actions = array();
if ( $res->is_active && $res->url->config ) {
	$minor_actions[] = sprintf(
		'<a href="%s">%s</a>',
		$res->url->config,
		( 'theme' == $res->type ? __( 'Customize', 'wpmudev' ) : __( 'Configure', 'wpmudev' ) )
	);
}
if ( $res->url->instructions ) {
	$minor_actions[] = sprintf(
		'<a href="%s" rel="dialog" data-class="small no-margin" data-height="600" data-title="%s">%s</a>',
		$res->url->instructions,
		sprintf( __( '%s Instructions', 'wpmudev' ), esc_attr( $res->name ) ),
		__( 'Instructions', 'wpmudev' )
	);
}

$attr = array(
	'project' => $pid,
	'installed' => intval( $res->is_installed ),
	'hasupdate' => intval( $res->has_update ),
	'incompatible' => intval( $res->is_compatible ),
	'active' => intval( $res->is_active ),
	'popularity' => $res->popularity,
	'downloads' => $res->downloads,
	'released' => $res->release_stamp,
	'updated' => $res->update_stamp,
	'type' => $res->type,
);

foreach ( $res->tags as $tid => $tag ) {
	$attr['tag-' . $tid] = 1;
}
$url_spinner = WPMUDEV_Dashboard::$site->plugin_url . 'image/spin-grey.gif';
if ( $action_ajax && empty( $action_url ) ) {
	$action_url = '#' . $action_ajax;
}

?>
<div class="project-box project-<?php echo esc_attr( $pid ); ?>"
	id="project-<?php echo esc_attr( $pid ); ?>"
	<?php
	foreach ( $attr as $key => $value ) {
		printf( 'data-%s="%s" ', $key, esc_attr( $value ) );
	}
	?>
>
<div class="project-inner">
	<div class="show-info">
	<h4><?php echo $res->name; ?></h4>
	<div class="project-image">
		<span class="img" style="background-image: url(<?php echo esc_url( $res->url->thumbnail ); ?>), url(<?php echo $url_spinner; ?>);">
		</span>
	</div>
	</div>
	<div class="project-info">
		<?php echo $res->info; ?>
	</div>
	<div class="project-action">
		<a
		class="button block <?php echo esc_attr( $action_class ); ?>"
		<?php if ( $action_ajax ) : ?>
		data-action="<?php echo esc_attr( $action_ajax ); ?>"
		data-hash="<?php echo wp_create_nonce( $action_ajax ); ?>"
		<?php endif; ?>
		<?php if ( $action_attr && is_array( $action_attr ) ) {
			echo implode( ' ', $action_attr );
		} ?>
		href="<?php echo esc_url( $action_url ); ?>"
		target="<?php echo esc_attr( $target ); ?>"
		>
			<?php echo esc_html( $action ); ?>
		</a>
	</div>
	<div class="project-minor">
		<?php echo implode( ' &bull; ', $minor_actions ); ?>
	</div>

	<?php if ( $show_badge ) : ?>
	<span class="badge badge-<?php echo esc_attr( $show_badge ); ?>">
		<?php if ( 'plugin' == $show_badge ) { ?>
		<i class="dev-icon dev-icon-plugin"></i>
		<?php } elseif ( 'theme' == $show_badge ) { ?>
		<i class="dev-icon dev-icon-theme"></i>
		<?php } elseif ( 'warning' == $show_badge ) { ?>
		<i class="dashicons dashicons-warning"></i>
		<?php } elseif ( 'active-theme' == $show_badge ) { ?>
		<i class="dev-icon dev-icon-radio_checked"></i>
		<?php } ?>
	</span>
	<?php endif; ?>

</div>
</div>