<?php
function zw_TinyMCE( $in ) {
	$in['paste_as_text'] = true;
}
add_filter( 'tiny_mce_before_init', 'zw_TinyMCE' );
