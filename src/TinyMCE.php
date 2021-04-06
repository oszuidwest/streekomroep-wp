<?php
function zw_TinyMCE( $in ) {
	$in['paste_remove_styles'] = true;
	$in['paste_remove_spans'] = false;
	$in['paste_strip_class_attributes'] = 'all';
}
add_filter( 'tiny_mce_before_init', 'zw_TinyMCE' );
