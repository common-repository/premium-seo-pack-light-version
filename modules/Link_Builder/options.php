<?php
/**
 * module return as json_encode
 * http://www.aa-team.com
 * =======================
 *
 * @author		Andrei Dinca, AA-Team
 * @version		1.0
 */

function psp_postTypes_get( $builtin=true ) {
	global $psp;

	$pms = array(
		'public'   => true,
	);
	if ( $builtin === true || $builtin === false  ) {
		$pms = array_merge($pms, array(
			'_builtin' => $builtin, // exclude post, page, attachment
		));
	}
	$post_types = get_post_types($pms, 'objects');
	unset($post_types['attachment'], $post_types['revision'], $post_types['nav_menu_item']);

	$ret = array();
	foreach ( $post_types as $key => $post_type ) {
		$value = $post_type->label;
		$ret["$key"] = $value;
	}
	return $ret;
}

global $psp;
echo json_encode(
	array(
		$tryed_module['db_alias'] => array(
			/* define the form_messages box */
			'Link_Builder' => array(
				//'title' 	=> __('Link Builder', 'psp'),
				'icon' 		=> '{plugin_folder_uri}assets/menu_icon.png',
				'size' 		=> 'grid_4', // grid_1|grid_2|grid_3|grid_4
				'header' 	=> true, // true|false
				'toggler' 	=> false, // true|false
				//'buttons' 	=> true, // true|false
				'style' 	=> 'panel', // panel|panel-widget

				// create the box elements array
				'elements'	=> array(
					array(
						'type' 		=> 'message',
	
					
					'html' 		=> '<div class="psp-box-update">' . __('
							<h2>What Is Link Building?  <br/></h2> 	<p class="psp-update-text">Link building is the process of acquiring hyperlinks (links) from other websites to your own. Using the Link Builder Module you will be able to do that!</p><p class="psp-update-button">
								<a href="https://codecanyon.net/item/premium-seo-pack-wordpress-plugin/6109437?ref=AA-Team" class="psp-form-button psp-form-button-success" target="_blank">Click here to Purchase Full Version</a>
							</p>', 'psp') . '</div>',
					), 
					
				)
			)
		)
	)
);