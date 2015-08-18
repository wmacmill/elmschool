<?php
	the_title( '<h2 class="ld-entry-title entry-title"><a href="' . get_permalink() . '" title="' . the_title_attribute( 'echo=0' ) . '" rel="bookmark">', '</a></h2>' ); 
?>
<div class="ld-entry-content entry-content">
	<?php 
		the_post_thumbnail(); 
		global $more; $more = 0;
		the_content(__('Read more.', 'learndash')); 
	?>
</div>
