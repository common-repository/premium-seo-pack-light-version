<?php
/**
 * module return as json_encode
 * http://www.aa-team.com
 * =======================
 *
 * @author		Andrei Dinca, AA-Team
 * @version		1.0
 */

/**
 * Defines an array of options that will be used to generate the settings page and be saved in the database.
 * When creating the "id" fields, make sure to use all lowercase and no spaces.
 *  
 */
if ( !function_exists( 'psp_wplanner_fb_options' ) ) {
	
	function __doRange( $arr ) {
		$newarr = array();
		if ( is_array($arr) && count($arr)>0 ) {
			foreach ($arr as $k => $v) {
				$newarr[ $v ] = $v;
			}
		}
		return $newarr;
	}
		
	function psp_wplanner_fb_options() 
	{
		global $wpdb, $psp;
 
		 
			
		$options = array(
		
		 
		);
		
		// Facebook available user pages / groups
		if( isset($fb_all_user_pages_groups) && count($fb_all_user_pages_groups) > 0 ) {
			// Facebook available user pages
			if(count($fb_all_user_pages_groups->pages) > 0) {
				$fb_all_user_pages = array();
				foreach($fb_all_user_pages_groups->pages as $key => $value) {
					$fb_all_user_pages[ "{$value->id}" ] = $value->name;
				}
				
				$options['page_filter'] = array( 
					"title" => __( "Activate \"Filter Pages\"", 'psp' ),
					"desc" => __( "Select \"Yes\" if you want to limit the pages shown when publishing and then select from above only what you wish to be shown. <i><strong>This is usefull if you have a lot of pages and/or you have a master facebook account and you wish to limit specific users to see other pages.</strong></i>", 'psp' ),
					"type" => "select",
					'size' 	=> 'large',
					'force_width'=> '80',
					"options" => array('No', 'Yes') 
				);
									
				$options['available_pages'] = array( 
					"title" => __( "What pages do you want to be available when publishing?", 'psp' ),
					"desc" => __( "<strong>This option only works if the \"Filter Pages\" option from above is \"Yes\"</strong>", 'psp' ),
					"type" => "multiselect",
					'size' 	=> 'large',
					'force_width'=> '350',
					"options" => $fb_all_user_pages 
				);
			}

			// Facebook available user groups
			if(count($fb_all_user_pages_groups->groups) > 0) {
				$fb_all_user_groups = array();
				foreach($fb_all_user_pages_groups->groups as $key => $value) {
					$fb_all_user_groups[ "{$value->id}" ] = $value->name;
				}

				$options['group_filter'] = array( 
					"title" => __( "Activate \"Filter Groups\"", 'psp' ),
					"desc" => __( "Select \"Yes\" if you want to limit the groups shown when publishing and then select from above only what you wish to be shown. <i><strong>This is usefull if you have a lot of groups and/or you have a master facebook account and you wish to limit specific users to see other groups.</strong></i>", 'psp' ),
					"type" => "select",
					'size' 	=> 'large',
					'force_width'=> '80',
					"options" => array('No', 'Yes') 
				);

				$options['available_groups'] = array( 
					"title" => __( "What groups do you want to be available when publishing?", 'psp' ),
					"desc" => __( "<strong>This option only works if the \"Filter Groups\" option from above is \"Yes\"</strong>", 'psp' ),
					"type" => "multiselect",
					'size' 	=> 'large',
					'force_width'=> '350',
					"options" => $fb_all_user_groups 
				);
			}
		}

		return $options;
	}
}
global $psp;
echo json_encode(
	array(
		$tryed_module['db_alias'] => array(
			/* define the form_messages box */
			'facebook_planner' => array(
				'size' 		=> 'grid_4', // grid_1|grid_2|grid_3|grid_4
				'toggler' 	=> false, // true|false
				'header' 	=> true, // true|false
				//'buttons' 	=> true, // true|false
				'style' 	=> 'panel', // panel|panel-widget
				
				// tabs
				 
				
				
				'elements'	=> array(
					array(
						'type' 		=> 'message',
						
					
						'html' 		=> '<div class="psp-box-update">' . __('
							<h2>Automatically post content from your website to Facebook!<br/></h2> 	<p class="psp-update-text">Using the Facebook Planner Module, you can schedule and post any content from your website straight to Facebook Pages / Groups / Profile and so on. </p><p class="psp-update-button">
								<a href="https://codecanyon.net/item/premium-seo-pack-wordpress-plugin/6109437?ref=AA-Team" class="psp-form-button psp-form-button-success" target="_blank">Click here to Purchase Full Version</a>
							</p>', 'psp') . '</div>',
					), 
					
					 
				)
			)
		)
	)
);