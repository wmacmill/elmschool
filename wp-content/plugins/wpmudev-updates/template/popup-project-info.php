<?php
/**
 * Dashboard popup template: Project info
 *
 * Will output the contents of a Dashboard popup element with details about a
 * single project.
 *
 * Following variables are passed into the template:
 *   $pid (project ID)
 */

$data = WPMUDEV_Dashboard::$api->get_project_data( $pid );

if ( ! empty( $data['thumbnail_large'] ) ) {
	$thumbnail = $data['thumbnail_large'];
} else {
	$thumbnail = $data['thumbnail'];
}

$gallery_items = array();
if ( ! empty( $data['video'] ) ) {
	$gallery_items[] = array(
		'thumb' => $thumbnail,
		'full' => $data['video'],
		'desc' => '',
		'type' => 'video',
	);
}
if ( is_array( $data['screenshots'] ) ) {
	foreach ( $data['screenshots'] as $item ) {
		$gallery_items[] = array(
			'thumb' => $item['url'],
			'full' => $item['url'],
			'desc' => $item['desc'],
			'type' => 'image',
		);
	}
}

if ( empty( $gallery_items ) ) {
	$gallery_items[] = array(
		'thumb' => $thumbnail,
		'full' => $thumbnail,
		'desc' => '',
		'type' => 'image',
	);
}

$slider_class = '';
if ( 1 == count( $gallery_items ) ) {
	$slider_class = 'no-nav';
}

if ( is_array( $data['features'] ) && ! empty( $data['features'] ) ) {
	$has_features = true;
	$feature_break = count( $data['features'] ) / 2;
	$feature_count = 0;
} else {
	$has_features = false;
}

$is_installed = WPMUDEV_Dashboard::$site->is_project_installed( $pid );
$has_update = WPMUDEV_Dashboard::$site->is_update_available( $pid );

$url_autoinstall = WPMUDEV_Dashboard::$site->auto_install_url( $pid );
$is_compatible = WPMUDEV_Dashboard::$site->is_project_compatible(
	$pid,
	$incompatible_reason
);

?>
<dialog title="<?php echo esc_attr( $data['name'] ); ?>">
<div class="wdp-info" data-project="<?php echo esc_attr( $pid ); ?>">

<div class="title-action" data-project="<?php echo esc_attr( $pid ); ?>">
	<?php if ( $is_installed && $has_update ) { ?>
	<a href="#update" class="button button-small show-project-update">
		<?php esc_html_e( 'Update available', 'wpmudev' ); ?>
	</a>
	<?php } elseif ( ! $is_installed ) { ?>
		<?php if ( $url_autoinstall ) { ?>
		<a href="<?php echo esc_url( $url_autoinstall ); ?>"
			class="button button-small button-cta button-green"
			data-project="<?php echo esc_attr( $pid ); ?>"
			data-action="project-install"
			data-hash="<?php echo esc_attr( wp_create_nonce( 'project-install' ) ); ?>">
			<?php
			if ( 'plugin' == $data['type'] ) {
				esc_html_e( 'Install Plugin', 'wpmudev' );
			} else {
				esc_html_e( 'Install Theme', 'wpmudev' );
			}
			?>
		</a>
		<?php } elseif ( $is_compatible ) { ?>
		<a href="<?php echo esc_url( $data['url'] ); ?>" target="_blank" class="button button-small button-secondary">
			<?php
			if ( 'plugin' == $data['type'] ) {
				esc_html_e( 'Download Plugin', 'wpmudev' );
			} else {
				esc_html_e( 'Download Theme', 'wpmudev' );
			}
			?>
		</a>
		<?php } ?>
	<?php } ?>
</div>

<div class="slider <?php echo esc_attr( $slider_class ); ?>">
	<ul class="slider-big">
		<?php foreach ( $gallery_items as $key => $item ) : ?>
		<li class="item-<?php echo esc_attr( $key ); ?> <?php echo esc_attr( $item['type'] ); ?>"
			data-full="<?php echo esc_url( $item['full'] ); ?>">
			<span style="background-image:url(<?php echo esc_url( $item['thumb'] ); ?>)"></span>
			<?php if ( ! empty( $item['desc'] ) ) : ?>
			<span class="desc"><?php echo esc_html( $item['desc'] ); ?></span>
			<?php endif; ?>
		</li>
		<?php endforeach; ?>
	</ul>
	<div class="slider-nav-wrapper">
	<span class="nav nav-left"><i class="wdv-icon wdv-icon-chevron-left"></i></span>
		<div class="slider-nav-items">
		<ul class="slider-nav">
			<?php foreach ( $gallery_items as $key => $item ) : ?>
			<li class="item <?php echo esc_attr( $item['type'] ); ?>"
				data-key="item-<?php echo esc_attr( $key ); ?>"
				data-full="<?php echo esc_url( $item['full'] ); ?>">
				<span style="background-image:url(<?php echo esc_url( $item['thumb'] ); ?>)"></span>
			</li>
			<?php endforeach; ?>
		</ul>
		</div>
	<span class="nav nav-right"><i class="wdv-icon wdv-icon-chevron-right"></i></span>
	</div>
</div>

<section class="overview">
	<h3><?php esc_html_e( 'Overview', 'wpmudev' ); ?></h3>
	<p><?php echo $data['short_description']; ?></p>
	<p><a href="<?php echo esc_url( $data['url'] ); ?>" target="_blank">
		<?php esc_html_e( 'More information on WPMU DEV', 'wpmudev' ); ?>
		<i class="wdv-icon wdv-icon-arrow-right"></i>
	</a></p>
</section>

<section class="features group">
<?php if ( $has_features ) : ?>
	<h3><?php esc_html_e( 'Features', 'wpmudev' ); ?></h3>
	<ul>
		<?php foreach ( $data['features'] as $feature ) : ?>
			<?php if ( $feature_count++ >= $feature_break ) : ?>
			<?php $feature_count = -2; ?>
			</ul><ul>
			<?php endif; ?>
		<li>
			<i class="dev-icon dev-icon-radio_checked"></i>
			<?php echo esc_html( $feature ); ?>
		</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
</section>

<div class="row-sep">
	<a href="#changelog" class="show-project-changelog button button-small button-light">
		<span class="loading-icon"></span>
		<?php esc_html_e( 'Show changelog', 'wpmudev' ); ?>
	</a>
</div>

<script>
jQuery(function(){
	var slider = jQuery('.wdp-info .slider'),
		previews = slider.find('.slider-big'),
		thumbs = slider.find('.slider-nav'),
		navRight = slider.find('.nav-right'),
		nevLeft = slider.find('.nav-left');

	function selectImage() {
		var thumb = jQuery(this),
			key = thumb.data('key'),
			big = previews.find('.' + key),
			pos = big.position();

		previews.css({'margin-left': (-1 * pos.left) });
		thumbs.find('.current').removeClass('current');
		thumb.addClass('current');
	}

	function scrollRight() {
		var curPos = thumbs.position(),
			curLeft = curPos.left,
			width = thumbs.outerWidth();

		curLeft -= 250;
		if (curLeft + width <= 350) {
			curLeft = 350 - width;
		}
		thumbs.css({'left': curLeft + 'px'});
	}

	function scrollLeft() {
		var curPos = thumbs.position(),
			curLeft = curPos.left;

		curLeft += 250;
		if (curLeft >= 0) {
			curLeft = 0;
		}
		thumbs.css({'left': curLeft + 'px'});
	}

	thumbs.on('click', 'li.item', selectImage);
	nevLeft.on('click', scrollLeft);
	navRight.on('click', scrollRight);
	thumbs.find('li.item').first().addClass('current');
});
</script>
</div>
</dialog>