<?php
/*
 * Define class pspFacebook_Planner
 * Make sure you skip down to the end of this file, as there are a few
 * lines of code that are very important.
 */
!defined('ABSPATH') and exit;
if (class_exists('pspFacebook_Planner') != true) {
	class pspFacebook_Planner {
        /*
         * Some required plugin information
         */
        const VERSION = '1.0';

        /*
         * Store some helpers config
         */
		public $the_plugin = null;

		private $module_folder = '';
		private $module_folder_path = '';
		private $module = '';

		static protected $_instance;
		private $post_privacy_options = array();
		
		private $fb_details = null;
		
		private $file_cache_directory = '/psp-facebook';
		
		public $fb = null;


        /*
         * Required __construct() function that initalizes the AA-Team Framework
         */
        public function __construct( $is_cron=false )
        {
        	global $psp;

        	$this->the_plugin = $psp;
			$this->module_folder = $this->the_plugin->cfg['paths']['plugin_dir_url'] . 'modules/facebook_planner/';
			$this->module_folder_path = $this->the_plugin->cfg['paths']['plugin_dir_path'] . 'modules/facebook_planner/';
			$this->module = $this->the_plugin->cfg['modules']['facebook_planner'];
 
			if (is_admin() && !$is_cron) {
	            add_action('admin_menu', array( &$this, 'adminMenu' ));

				//add_action( 'save_post', array( &$this, 'auto_optimize_on_save' ));
			}
			
			// ajax optimize helper
			//add_action('wp_ajax_pspFacebookRequest', array( &$this, 'ajax_request' ));
			
			$this->post_privacy_options = array(
		        "EVERYONE" => __('Everyone', 'psp'),
		        "ALL_FRIENDS" => __('All Friends', 'psp'),
		        "NETWORKS_FRIENDS" => __('Networks Friends', 'psp'),
		        "FRIENDS_OF_FRIENDS" => __('Friends of Friends', 'psp'),
		        "CUSTOM" => __('Private (only me)', 'psp')
		    );

			if ( 'fbv4' == $this->the_plugin->facebook_sdk_version ) {
				// load the facebook SDK
				$this->the_plugin->facebook_load_sdk();
			}
			//else {
			//	require_once( $this->the_plugin->cfg['paths']['scripts_dir_path'] . '/facebook/facebook.php' );
			//}

			$this->fb_details = $this->the_plugin->getAllSettings('array', 'facebook_planner');

			if( (isset($this->fb_details['app_id']) && trim($this->fb_details['app_id']) != '') && ( isset($this->fb_details['app_secret']) && trim($this->fb_details['app_secret']) != '') ) {

				if ( 'fbv4' == $this->the_plugin->facebook_sdk_version ) {
					add_action('wp_ajax_psp_facebookAuth', array( $this, 'fbAuth_fbv4' ));
				}
				//else {
				//	add_action('wp_ajax_psp_facebookAuth', array( $this, 'fbAuth' ));
				//}
			}
			
			add_action('wp_ajax_psp_publish_fb_now', array( $this, 'fb_postFB_callback' ));
			//add_action('wp_ajax_psp_getFeaturedImage', array( $this, 'fb_getFeaturedImage' ));

			if ( $this->the_plugin->capabilities_user_has_module('facebook_planner') ) {
				add_action( 'save_post', array( $this, 'fb_save_meta' ) );
			}
		
			// @at plugin/module activation - setup cron
			// wp_schedule_event(time(), 'hourly', 'pspwplannerhourlyevent');
			// @at plugin/module deactivation - clean the scheduler on plugin deactivation
			// wp_clear_scheduled_hook('pspwplannerhourlyevent');
			// add_action('pspwplannerhourlyevent', array( $this, 'facebook_wplanner_do_this_hourly' ));

			//delete bulk rows!
			//add_action('wp_ajax_psp_do_bulk_delete_rows', array( $this, 'delete_bulk_rows' ));
			
			// Plugin cron class loading
			//require_once ( 'app.cron.class.php' );
			
			// ajax handler
			//if ( 'fbv4' == $this->the_plugin->facebook_sdk_version ) {
			//}
			//else {
			//	//add_action('wp_ajax_pspFacebookAuthorizeApp', array( &$this, 'facebookAuthorizeApp' ));
			//	//if ( $this->the_plugin->capabilities_user_has_module('facebook_planner') ) {
			//	//	add_action('init', array( &$this, 'check_auth_callback' ));
			//	//}
			//}

			// ajax requests metabox
			add_action('wp_ajax_psp_metabox_fb', array( $this, 'ajax_requests_metabox') );
        }
		
		/**
	     * Hooks
	     */
	    static public function adminMenu()
	    {
	       self::getInstance()
	       		->_registerMetaBoxes()
	       		->_registerAdminPages();
	    }
		
		/**
	     * Register plug-in admin metaboxes
	     */
	    protected function _registerMetaBoxes()
	    {
			// find if user makes the setup
			$moduleValidateStat = $this->moduleValidation();
			if ( !$moduleValidateStat['status'] ) return $this;
							
	    	if ( $this->the_plugin->capabilities_user_has_module('facebook_planner') ) {
		    	//posts | pages | custom post types
		    	$this->post_types = get_post_types(array(
		    		'public'   => true
		    	));
		    	//unset media - images | videos are treated as belonging to post, pages, custom post types
		    	unset($this->post_types['attachment'], $this->post_types['revision']);
	
				$screens = $this->post_types;

				foreach ($screens as $key => $screen) {
					$screen = str_replace("_", " ", $screen);
					$screen = ucfirst($screen);
					add_meta_box(
						'psp_facebook_share-options',
						$screen . ' - ' . __('Publish on Facebook ?', 'psp'),
						array($this, 'display_meta_box'),
						$key,
						'normal',
						'high'
					);
				}
			}

			return $this;
		}

	    /**
	     * Register plug-in module admin pages and menus
	     */
		protected function _registerAdminPages()
    	{
    		if ( $this->the_plugin->capabilities_user_has_module('facebook_planner') ) {
	    		add_submenu_page(
	    			$this->the_plugin->alias,
	    			$this->the_plugin->alias . " " . __('FB Planner Scheduled Tasks', 'psp'),
		            __('FB Planner Tasks', 'psp'),
		            'read',
		            $this->the_plugin->alias . "_facebook_planner",
		            array($this, 'display_index_page')
		        );
    		}

			return $this;
		}
		
		public function moduleValidation() {
			$ret = array(
				'status'			=> false,
				'html'				=> ''
			);
			
			// find if user makes the setup
			$module_settings = $facebook_settings = $this->the_plugin->get_theoption( $this->the_plugin->alias . "_facebook_planner" );

			$facebook_mandatoryFields = array(
				'app_id'			=> false,
				'app_secret'		=> false,
				'language'			=> false,
				'redirect_uri'		=> false
			);
			if ( isset($facebook_settings['app_id']) && !empty($facebook_settings['app_id']) ) {
				$facebook_mandatoryFields['app_id'] = true;
			}
			if ( isset($facebook_settings['app_secret']) && !empty($facebook_settings['app_secret']) ) {
				$facebook_mandatoryFields['app_secret'] = true;
			}
			if ( isset($facebook_settings['redirect_uri']) && !empty($facebook_settings['redirect_uri']) ) {
				$facebook_mandatoryFields['redirect_uri'] = true;
			}
			if ( isset($facebook_settings['language']) && !empty($facebook_settings['language']) ) {
				$facebook_mandatoryFields['language'] = true;
			}
			$mandatoryValid = true;
			foreach ($facebook_mandatoryFields as $k=>$v) {
				if ( !$v ) {
					$mandatoryValid = false;
					break;
				}
			}
			if ( !$mandatoryValid ) {
				$error_number = 1; // from config.php / errors key
				
				$ret['html'] = $this->the_plugin->print_module_error( $this->module, $error_number, 'Error: Unable to use Facebook Planner module, yet!' );
				return $ret;
			}
			
			if( !(extension_loaded("curl") && function_exists('curl_init')) ) {  
				$error_number = 2; // from config.php / errors key
				
				$ret['html'] = $this->the_plugin->print_module_error( $this->module, $error_number, 'Error: Unable to use Facebook Planner module, yet!' );
				return $ret;
			}

			$ret['status'] = true;
			return $ret;
		}

		public function display_index_page()
		{
			$this->printBaseInterface();
		}

		public function display_meta_box() {
			global $post;

			$post_id = isset($post->ID) ? $post->ID : 0;
		?>

			<script type="text/javascript" src="<?php echo $this->module_folder;?>app.class.js" ></script>
			
			<div id="psp-meta-box-preload" style="height:200px; position: relative;">
				<!-- Main loading box -->
				<div id="psp-main-loading" style="display:block;">
					<div id="psp-loading-box" style="top: 50px">
						<div class="psp-loading-text"><?php _e('Loading', 'psp');?></div>
						<div class="psp-meter psp-animate" style="width:86%; margin: 4px 0px 0px 7%;"><span style="width:100%"></span></div>
					</div>
				</div>
			</div>
			
			<div class="psp-meta-box-container psp" style="display:none;" data-post_id="<?php echo $post_id; ?>">

				<?php
					// Lang Messages
					$lang = array(
						'mandatory2'		=> __( "Your mandatory fields were empty. Auto-Complete was done using your current post/page data. Please check before submiting.", 'psp' ),
						'mandatory'			=> __( "Your mandatory fields are empty. Do you want to auto-complete and then publish to Facebook?", 'psp' ),
						'publish_cancel'	=> __( "Publish canceled.", 'psp' ),
						'publish_success'	=> __( 'The post was published on facebook OK!', 'psp' ),
						'publish_error'		=> __( 'Error on publishing. Please try again later!', 'psp' ),

						'timeOnlyTitle'		=> __( 'Choose Time', 'psp' ),
						'timeText'			=> __( 'At', 'psp' ),
						'hourText'			=> __( 'Hour', 'psp' ),
						'currentText'		=> __( 'Now', 'psp' ),
						'closeText'			=> __( 'Done', 'psp' ),
					);
					// Settings
					$settings = array(
						'post_id'			=> $post_id,
						'plugin_url' 		=> $this->the_plugin->cfg['paths']['plugin_dir_url'],
						'thumb_w' 			=> isset($img_size[0]) ? $img_size[0] : 450,
						'thumb_h' 			=> isset($img_size[1]) ? $img_size[1] : 320,
						'thumb_zc' 			=> isset($this->fb_details['featured_image_size_crop'])
							&& $this->fb_details['featured_image_size_crop'] == 'true' ? 1 : 2,
					);
				?>
				<!-- Lang Messages -->
				<div id="psp-meta-boxlang-translation" style="display: none;"><?php echo htmlentities(json_encode( $lang )); ?></div>
				<!-- Params / Settings -->
				<div id="psp-meta-box-settings" style="display: none;"><?php echo htmlentities(json_encode( $settings )); ?></div>

				<!-- box Tab Menu -->
				<div class="psp-tab-menu">
					<a href="#publish_on_facebook" class="open"><?php _e('What do you want to publish on facebook ?', 'psp');?></a>
					<a href="#page_scheduler"><?php _e('Page Scheduler', 'psp');?></a>
					<a href="#page_postnow"><?php _e('Post Now', 'psp');?></a>
				</div>

				<!-- start: psp-tab-container -->
				<div class="psp-tab-container">

					<?php //LOADED BY AJAX ?>

				</div><!-- end: psp-tab-container -->
				<div style="clear:both"></div>
			</div>

		<?php
		}
		
		public function display_page_options( $pms=array() )
		{
			$pms = array_replace_recursive(array(
				'post_id'		=> 0,
			), $pms);
			extract($pms);

			ob_start();
		?>
					<!-- TAB publish_on_facebook -->
					<div id="psp-tab-div-id-publish_on_facebook" style="display:block;">
						<div class="psp-dashboard-box span_3_of_3">
							<!-- Creating the option fields -->
							<table width="98%" cellspacing="5" cellpadding="2" border="0" style="margin: 10px 1% 10px 1%;">
							<tr>
								<td width="15%" valign="top"></td>
								<td width="85%" align="left"><a href="javascript:void(0);" id="psp-wplannerfb-auto-complete" rel="<?php echo home_url(); ?>" class="psp-form-button psp-form-button-info"><?php _e( 'Auto-Complete fields from above', 'psp' ); ?></a></td>
							</tr>
							<?php if( !empty($this->fb_details['inputs_available']) && in_array( 'message', $this->fb_details['inputs_available'])) { ?>
							<tr>
								<td valign="top"><?php _e( 'Message:', 'psp' ); ?></td>
								<td><textarea id="psp_wplannerfb_message" name="psp_wplannerfb_message" rows="4" style="width:100%;"><?php echo $this->fb_post_meta('message', $post_id); ?></textarea></td>
							</tr>
							<?php } ?>
							<tr>
								<td><?php _e( 'Title:', 'psp' ); ?></td>
								<td><input type="text" id="psp_wplannerfb_title" name="psp_wplannerfb_title" value="<?php echo $this->fb_post_meta('title', $post_id); ?>" style="width:100%;"/></td>
							</tr>
							<tr>
								<td><?php _e( 'Permalink:', 'psp' ); ?></td>
								<td>
									<input type="radio" name="psp_wplannerfb_permalink" id="psp_wplannerfb_post_link" value="post_link" <?php echo $this->fb_post_meta('permalink', $post_id) != '' && $this->fb_post_meta('permalink', $post_id) == 'post_link' ? 'checked="checked"' : 'checked="checked"'; ?> onclick="jQuery('#psp_wplannerfb_permalink_value').hide();"/> &nbsp; <label for="psp_wplannerfb_post_link"><?php _e( 'Use post link', 'psp' ); ?></label> &nbsp;&nbsp; 
									<input type="radio" name="psp_wplannerfb_permalink" id="psp_wplannerfb_custom_link" value="custom_link" <?php echo $this->fb_post_meta('permalink', $post_id) != '' && $this->fb_post_meta('permalink', $post_id) != 'post_link' ? 'checked="checked"' : ''; ?> onclick="jQuery('#psp_wplannerfb_permalink_value').show();"/> &nbsp; <label for="psp_wplannerfb_custom_link"><?php _e( 'Use custom link', 'psp' ); ?></label>
									<input type="text" id="psp_wplannerfb_permalink_value" name="psp_wplannerfb_permalink_value" value="<?php echo $this->fb_post_meta('permalink', $post_id) != 'post_link' ? $this->fb_post_meta('permalink', $post_id) : ''; ?>" style="display:<?php echo $this->fb_post_meta('permalink', $post_id) != 'post_link' ? 'block' : 'none'; ?>; float:right; width:75%;"/>
								</td>
							</tr>
							<?php if( !empty($this->fb_details['inputs_available']) && in_array( 'caption', $this->fb_details['inputs_available'])) { ?>
							<tr>
								<td width="15%"><?php _e( 'Caption:', 'psp' ); ?></td>
								<td width="85%"><input type="text" id="psp_wplannerfb_caption" name="psp_wplannerfb_caption" value="<?php echo $this->fb_post_meta('caption', $post_id); ?>" style="width:100%;"/></td>
							</tr>
							<?php } ?>
							<tr>
								<td valign="top"><?php _e( 'Description:', 'psp' ); ?></td>
								<td><textarea id="psp_wplannerfb_description" name="psp_wplannerfb_description" rows="4" style="width:100%;"><?php echo $this->fb_post_meta('description', $post_id); ?></textarea></td>
							</tr>
							<?php if( !empty($this->fb_details['inputs_available']) && in_array( 'image', $this->fb_details['inputs_available'])) { ?>
							<tr>
								<td><?php _e( 'Publish Image:', 'psp' ); ?></td>
								<td>
									<select id="psp_wplannerfb_useimage" name="psp_wplannerfb_useimage">
										<option value="yes" <?php echo $this->fb_post_meta('useimage', $post_id) != '' && $this->fb_post_meta('useimage', $post_id) == 'yes' ? 'selected="selected"' : ''; ?>><?php _e( 'Yes', 'psp' ); ?></option>
										<option value="no" <?php echo $this->fb_post_meta('useimage', $post_id) != '' && $this->fb_post_meta('useimage', $post_id) == 'no' ? 'selected="selected"' : ''; ?>><?php _e( 'No', 'psp' ); ?></option>
									</select>
									<?php _e( 'If you chose yes and you don\'t provide an image, feature image is used if found.', 'psp' ); ?>
								</td>
							</tr>
							<tr id="psp_wplannerfb_upload">
								<td valign="top"><?php _e( 'Image:', 'psp' ); ?></td>
								<td><?php 
									$img_size = explode('x', $this->fb_details['featured_image_size']);
									echo $this->uploadImage(
										array(
										 	'psp_wplannerfb_image' => array(
										 		'db_value'	=> $this->fb_post_meta('image', $post_id),
				
												'type' 		=> 'upload_image',
												'size' 		=> 'large',
												'title' 	=> 'Facebook image',
												'value' 	=> 'Upload image',
												'thumbSize' => array(
													'w' => isset($img_size[0]) ? $img_size[0] : 450,
													'h' => isset($img_size[1]) ? $img_size[1] : 320,
													'zc' => $this->fb_details['featured_image_size_crop'] == 'true' ? 1 : 2,
												),
												'desc' 		=> __('Choose the image', 'psp')
											)
										));
							?></td>
							</tr>
							<?php } ?>
							</table>

							<?php /*
							<script type="text/javascript">
							// (POST) Facebook Planner
							(function ($) {
								$(document).ready(function() {
									pspFacebookPage.fb_planner_post( {
										'post_id'		: <?php echo $post_id; ?>,
										'plugin_url' 	: '<?php echo $this->the_plugin->cfg['paths']['plugin_dir_url']; ?>',
										'thumb_w' 		: '<?php echo isset($img_size[0]) ? $img_size[0] : 450; ?>',
										'thumb_h' 		: '<?php echo isset($img_size[1]) ? $img_size[1] : 320; ?>',
										'thumb_zc' 		: '<?php echo isset($this->fb_details['featured_image_size_crop']) && $this->fb_details['featured_image_size_crop'] == 'true' ? 1 : 2; ?>'
									} );
								});
							})(jQuery);
							</script>
							*/ ?>
						</div>	
					</div><!-- end: TAB publish_on_facebook -->
					
					<!-- TAB page_scheduler -->
					<?php 
					$post_to_check = unserialize( $this->fb_schedule_value('post_to', $post_id) );
					?>
					<div id="psp-tab-div-id-page_scheduler" style="display:none;">
						<div class="psp-dashboard-box span_3_of_3">
							<!-- Creating the scheduler fields -->
							<table width="100%" cellspacing="5" cellpadding="2" border="0">
							<tr>
								<td colspan="2">
									<?php _e( 'Publish on:', 'psp' ); ?>
									&nbsp;
									<input type="checkbox" id="psp_wplannerfb_post_toprofile" name="psp_wplannerfb_post_toprofile" <?php echo isset($post_to_check['profile']) && trim($post_to_check['profile']) == 'on' ? 'checked="checked"' : ''; ?> /> <label for="psp_wplannerfb_post_toprofile"><?php _e( 'Profile', 'psp' ); ?></label>
									&nbsp;
									<input type="checkbox" id="psp_wplannerfb_post_topage_group" name="psp_wplannerfb_post_topage_group" <?php echo $post_to_check['page_group'] ? 'checked="checked"' : ''; ?> onclick="jQuery('#psp_wplannerfb_post_to_page_group').toggle();" /> <label for="psp_wplannerfb_post_topage_group"><?php _e( 'Page / Group', 'psp' ); ?></label>
								</td>
							</tr>
							<tr>
								<td colspan="2">
									<?php
									$pages = get_option('psp_fb_planner_user_pages');
									if(trim($pages) != ""){
										$allPages = @json_decode($pages);
									}
									?>
									
									<select id="psp_wplannerfb_post_to_page_group" name="psp_wplannerfb_post_to_page_group" style="<?php echo $post_to_check['page_group'] ? 'display:block;' : 'display:none;'; ?> width:250px;">
										<?php
										if( isset($this->fb_details['page_filter']) && $this->fb_details['page_filter'] == 1 ) {
											if( count($this->fb_details['available_pages']) > 0 ) {
												echo '<optgroup label="' . __( 'Pages', 'psp' ) . '">';
												//orig: foreach ($this->fb_details['available_pages'] as $key => $status) {
												//foreach ($this->fb_details['available_pages'] as $status => $key) {
												//	//if( $status == 1 ) {
												//		echo '<option value="page##'.($allPages->pages[$key]->id).'##'.($allPages->pages[$key]->access_token).'" '.($post_to_check['page_group'] == "page##".($allPages->pages[$key]->id).'##'.($allPages->pages[$key]->access_token) ? 'selected="selected"' : '').'>' . ($allPages->pages[$key]->name) . '</option>';
												//	//}
												//}
												foreach ($allPages->pages as $key => $value) {
													if ( ! in_array($value->id, $this->fb_details['available_pages']) ) continue 1;
													echo '<option value="page##'.( $value->id ).'##'.( $allPages->pages[$key]->access_token ).'" '.($post_to_check['page_group'] == "page##".($value->id) .'##'.($allPages->pages[$key]->access_token) ? 'selected="selected"' : '').'>' . ($value->name) . '</option>';
												}
												echo '</optgroup>';
											}
										}else{
											if( isset($allPages) && is_object($allPages) && count($allPages->pages) > 0) {
												echo '<optgroup label="' . __( 'Pages', 'psp' ) . '">';
												foreach ($allPages->pages as $key => $value) {
													echo '<option value="page##'.( $value->id ).'##'.( $allPages->pages[$key]->access_token ).'" '.($post_to_check['page_group'] == "page##".($value->id) .'##'.($allPages->pages[$key]->access_token) ? 'selected="selected"' : '').'>' . ($value->name) . '</option>';
												}
												echo '</optgroup>';
											}
										}
										
										if( isset($this->fb_details['group_filter']) && $this->fb_details['group_filter'] == 1 ) {
											if(count($this->fb_details['available_groups']) > 0) {
												echo '<optgroup label="' . __( 'Groups', 'psp' ) . '">';
												//orig: foreach ($this->fb_details['available_groups'] as $key => $status) {
												//foreach ($this->fb_details['available_groups'] as $status => $key) {
												//	//if( $status == 1 ) {
												//		echo '<option value="group##'.($allPages->groups[$key]->id).'" '.($post_to_check['page_group'] == "group##".$allPages->groups[$key]->id ? 'selected="selected"' : '').'>' . ($allPages->groups[$key]->name) . '</option>';
												//	//}
												//}
												foreach ($allPages->groups as $key => $value) {
													if ( ! in_array($value->id, $this->fb_details['available_groups']) ) continue 1;
													echo '<option value="group##'.($value->id).'" '.($post_to_check['page_group'] == "group##".$value->id ? 'selected="selected"' : '').'>' . ($value->name) . '</option>';
												}
												echo '</optgroup>';
											}
										}else{
											if( isset($allPages) && is_object($allPages) && count($allPages->groups) > 0) {
												echo '<optgroup label="' . __( 'Groups', 'psp' ) . '">';
												foreach ($allPages->groups as $key => $value) {
													echo '<option value="group##'.($value->id).'" '.($post_to_check['page_group'] == "group##".$value->id ? 'selected="selected"' : '').'>' . ($value->name) . '</option>';
												}
												echo '</optgroup>';
											}
										}
										?>
									</select>
								</td>
							</tr>
							<tr>
								<td colspan="2">
									<?php _e( 'Privacy:', 'psp' ); ?>
									<select id="psp_wplannerfb_post_privacy" name="psp_wplannerfb_post_privacy">
									<?php
									$default_privacy_option = isset($this->fb_details['default_privacy_option'])
										? $this->fb_details['default_privacy_option'] : '';
									foreach($this->post_privacy_options as $key => $value) {
										echo '<option value="'.($key).'" '.($this->fb_schedule_value('post_privacy', $post_id) != '' ? ($this->fb_schedule_value('post_privacy', $post_id) == $key ? 'selected="selected"' : '') : ($default_privacy_option == $key ? 'selected="selected"' : '')).'>'.($value).'</option>';
									}
									?>
									</select>
								</td>
							</tr>
							<tr><td colspan="2"><hr/></td></tr>
							<tr>
								<td><?php _e( 'Publish date/hour:', 'psp' ); ?></td>
								<td>
									<?php
										$run_date = $this->fb_schedule_value('run_date', $post_id);
										if( $run_date != '' ) {
											$run_date = explode(' ', $run_date);
											$run_date_hour = explode(':', $run_date[1]);
											$run_date = date('m/d/Y', strtotime($run_date[0])) .' @ '. $run_date_hour[0];
										}
									?>
									<input type="text" id="psp_wplannerfb_date_hour" name="psp_wplannerfb_date_hour" value="<?php echo $run_date; ?>" size="13" autocomplete="off" class="psp_wplannerfb_date_hour" />
									<?php /*
									<script type="text/javascript">
									// Display DateTimePicker
									jQuery('#psp_wplannerfb_date_hour').datetimepicker({
										timeFormat: 'H',
										separator: ' @ ',
										showMinute: false,
										ampm: false,
										//addSliderAccess: true,
										//sliderAccessArgs: { touchonly: false },
										timeOnlyTitle: '<?php _e( 'Choose Time', 'psp' ); ?>',
										timeText: '<?php _e( 'At', 'psp' ); ?>',
										hourText: '<?php _e( 'Hour', 'psp' ); ?>',
										currentText: '<?php _e( 'Now', 'psp' ); ?>',
										closeText: '<?php _e( 'Done', 'psp' ); ?>'
									});
									</script>
									*/ ?>
								</td>
							</tr>
							<tr>
								<td colspan="2">
									<input type="checkbox" id="psp_wplannerfb_repeating" name="psp_wplannerfb_repeating"  onclick="jQuery('#psp_wplannerfb_repeating_wrapper').toggle();" <?php echo $this->fb_schedule_value('repeat_status', $post_id) == 'on' ? 'checked="checked"' : ''; ?> /> <label for="psp_wplannerfb_repeating"><?php _e( 'Repeating', 'psp' ); ?></label>
									<br />
									<div id="psp_wplannerfb_repeating_wrapper" style="<?php echo $this->fb_schedule_value('repeat_status', $post_id) == 'on' ? 'display:block;' : 'display:none;'; ?>">
									<input type="text" id="psp_wplannerfb_repeating_interval" name="psp_wplannerfb_repeating_interval" value="<?php echo $this->fb_schedule_value('repeat_interval', $post_id); ?>" size="2"> <?php _e( 'hour(s) or', 'psp' ); ?> 
									<select id="psp_wplannerfb_repeating_interval_sel" name="psp_wplannerfb_repeating_interval_sel" onchange="jQuery('#psp_wplannerfb_repeating_interval').val(jQuery(this).val());">
										<option value="" disabled="disabled">-- <?php _e( 'select interval', 'psp' ); ?> --</option>
										<option value="24"><?php _e( 'Every day', 'psp' ); ?></option>
										<option value="168"><?php _e( 'Every week', 'psp' ); ?></option>
										<option value="730"><?php _e( 'Every month', 'psp' ); ?></option>
										<option value="8766"><?php _e( 'Every year', 'psp' ); ?></option>
									</select>
									</div>
								</td>
							</tr>
							<tr>
								<td colspan="2"><input type="checkbox" id="psp_wplannerfb_email_at_post" name="psp_wplannerfb_email_at_post" <?php echo $this->fb_schedule_value('email_at_post', $post_id) == 'on' ? 'checked="checked"' : ''; ?> /> <label for="psp_wplannerfb_email_at_post"><?php _e( 'Email me when it\'s published on facebook', 'psp' ); ?></label></td>
							</tr>
							<tr>
								<td><input type="checkbox" id="psp_wplannerfb_publish_at_save" name="psp_wplannerfb_publish_at_save" value="psp_wplannerfb_publish_at_save" /> <label for="psp_wplannerfb_publish_at_save"><?php _e( 'Send to Facebook after publish / update', 'psp' ); ?></label></td>
								<td>
									<div style="display: none;" class="psp_wplannerfb_FBLog"></div>
								</td>
							</tr>
							</table>
							
							<?php /*
							<script type="text/javascript">
							// (POST) Facebook Planner
							(function ($) {
								$(document).ready(function() {
									var langmsg = {
										'mandatory2'			: '<?php _e( "Your mandatory fields were empty. Auto-Complete was done using your current post/page data. Please check before submiting.", 'psp' ); ?>'
									};
									pspFacebookPage.setLangMsg( langmsg );
									pspFacebookPage.fb_scheduler( {'post_id': <?php echo $post->ID;?>} );
								});
							})(jQuery);
							</script>
							*/ ?>
						</div>	
					</div><!-- end: TAB page_scheduler -->
					
					<!-- TAB page_postnow -->
					<div id="psp-tab-div-id-page_postnow" style="display:none;">
						<div class="psp-dashboard-box span_3_of_3">
							<!-- Creating the scheduler fields -->
							<table width="100%" cellspacing="5" cellpadding="2" border="0">
							<tr>
								<td colspan="2">
									<?php _e( 'Publish on:', 'psp' ); ?>
									&nbsp;
									<input type="checkbox" id="psp_wplannerfb_now_post_to_me" name="psp_wplannerfb_now_post_toprofile" checked="checked" /> <label for="psp_wplannerfb_now_post_to_me"><?php _e( 'Profile', 'psp' ); ?></label>
									&nbsp;
									<input type="checkbox" id="psp_wplannerfb_now_post_to_page" name="psp_wplannerfb_now_post_topage_group" onclick="jQuery('#psp_wplannerfb_now_post_to_page_group').toggle();" /> <label for="psp_wplannerfb_now_post_to_page"><?php _e( 'Page / Group', 'psp' ); ?></label>
								</td>
							</tr>
							<tr>
								<td colspan="2">
									<?php
									$pages = get_option('psp_fb_planner_user_pages');
									if(trim($pages) != ""){
										$allPages = @json_decode($pages);
									}
									?>
									
									<select id="psp_wplannerfb_now_post_to_page_group" name="psp_wplannerfb_now_post_to_page_group" style="display:none; width:250px;">
										<?php
										if( isset($this->fb_details['page_filter']) && $this->fb_details['page_filter'] == 1 ) {
											if( count($this->fb_details['available_pages']) > 0 ) {
												echo '<optgroup label="' . __( 'Pages', 'psp' ) . '">';
												//orig: foreach ($this->fb_details['available_pages'] as $key => $status) {
												//foreach ($this->fb_details['available_pages'] as $status => $key) {
												//	//if( $status == 1 ) {
												//		echo '<option value="page##' . ( $allPages->pages[$key]->id ) . '##' . ( $allPages->pages[$key]->access_token ) . '">' . ( $allPages->pages[$key]->name ) . '</option>';
												//	//}
												//}
												foreach ($allPages->pages as $key => $value) {
													if ( ! in_array($value->id, $this->fb_details['available_pages']) ) continue 1;
													echo '<option value="page##' . ( $value->id ) . '##' . ( $allPages->pages[$key]->access_token ) . '">' . ( $value->name ) . '</option>';
												}
												echo '</optgroup>';
											}
										}else{
											if( isset($allPages) && is_object($allPages) && count($allPages->pages) > 0) {
												echo '<optgroup label="' . __( 'Pages', 'psp' ) . '">';
												foreach ($allPages->pages as $key => $value) {
													echo '<option value="page##' . ( $value->id ) . '##' . ( $allPages->pages[$key]->access_token ) . '">' . ( $value->name ) . '</option>';
												}
												echo '</optgroup>';
											}
										}
										
										if( isset($this->fb_details['group_filter']) && $this->fb_details['group_filter'] == 1 ) {
											if(count($this->fb_details['available_groups']) > 0) {
												echo '<optgroup label="' . __( 'Groups', 'psp' ) . '">';
												//orig: foreach ($this->fb_details['available_groups'] as $key => $status) {
												//foreach ($this->fb_details['available_groups'] as $status => $key) {
												//	//if( $status == 1 ) {
												//		echo '<option value="group##' . ( $allPages->groups[$key]->id ) . '">' . ( $allPages->groups[$key]->name ) . '</option>';
												//	//}
												//}
												foreach ($allPages->groups as $key => $value) {
													if ( ! in_array($value->id, $this->fb_details['available_groups']) ) continue 1;
													echo '<option value="group##' . ( $value->id ) . '">' . ( $value->name ) . '</option>';
												}
												echo '</optgroup>';
											}
										}else{
											if( isset($allPages) && is_object($allPages) && count($allPages->groups) > 0) {
												echo '<optgroup label="' . __( 'Groups', 'psp' ) . '">';
												foreach ($allPages->groups as $key => $value) {
													echo '<option value="group##' . ( $value->id ) . '">' . ( $value->name ) . '</option>';
												}
												echo '</optgroup>';
											}
										}
										?>
									</select>
								</td>
							</tr>
							<tr>
								<td colspan="2">
									<?php _e( 'Privacy:', 'psp' ); ?>
									<select id="psp_wplannerfb_now_post_privacy" name="psp_wplannerfb_now_post_privacy">
									<?php
									$default_privacy_option = isset($this->fb_details['default_privacy_option'])
										? $this->fb_details['default_privacy_option'] : '';
									foreach($this->post_privacy_options as $key => $value) {
										echo '<option value="'.($key).'" '.($default_privacy_option == $key || $this->fb_schedule_value('post_privacy', $post_id) == $key ? 'selected="selected"' : '' ).'>'.($value).'</option>';
									}
									?>
									</select>
								</td>
							</tr>
							<tr>
								<td colspan="2"><input type="checkbox" id="psp_wplannerfb_now_publish_at_save" name="psp_wplannerfb_now_publish_at_save" value="psp_wplannerfb_now_publish_at_save" /> <label for="psp_wplannerfb_now_publish_at_save"><?php _e( 'Send to Facebook after publish / update', 'psp' ); ?></label></td>
							</tr>
							<tr>
								<td>
									<a href="#" id="psp_post_planner_postNowFBbtn" class="psp-form-button psp-form-button-info"><?php _e( 'Publish now on facebook', 'psp' ); ?> </a>
									<span id="psp_postTOFbNow" style="display: none; border: 1px solid #dadada; text-align: center; margin: 10px 0px 0px 0px; width: 160px; padding: 3px; background-color: #dfdfdf;"><?php _e( 'Publishing on facebook ...', 'psp' ); ?></span>
								</td>
								<td>
									<div style="display: none;" class="psp_post_planner_postNowFBLog"></div>
								</td>
							</tr>
							</table>
							
							<?php /*
							<script type="text/javascript">
							// (POST) Facebook Planner
							(function ($) {
								$(document).ready(function() {
									var langmsg = {
										'mandatory'			: "<?php _e( "Your mandatory fields are empty. Do you want to auto-complete and then publish to Facebook?", 'psp' ); ?>",
										'publish_cancel'	: '<?php _e( "Publish canceled.", 'psp' ); ?>',
										'publish_success'	: '<?php _e( 'The post was published on facebook OK!', 'psp' ); ?>',
										'publish_error'		: '<?php _e( 'Error on publishing. Please try again later!', 'psp' ); ?>'
									};
									pspFacebookPage.setLangMsg( langmsg );
									pspFacebookPage.fb_postnow( {'post_id': <?php echo $post->ID;?>} );
								});
							})(jQuery);
							</script>
							*/ ?>
						</div>	
					</div><!-- end: TAB page_postnow -->
		<?php
			$html = ob_get_clean();
			return $html;
		}
		
		// Retrieve Wordpress Planner metadata values if they exist
		private function fb_post_meta( $field, $post_id ) {
			$base = get_post_meta($post_id, 'psp_wplannerfb_' . $field, true);
			return htmlentities($base, ENT_QUOTES, 'UTF-8');
		}
		
		// Retrieve scheduling values if they exist
		private function fb_schedule_value( $field, $post_id ) {
			global $wpdb;
			return $wpdb->get_var( "SELECT `" . ( $field ) . "` FROM `" . ( $wpdb->prefix . 'psp_post_planner_cron' ) . "` WHERE 1=1 AND id_post=" . $post_id );
		}

/*
		// :: Facebook Authorization STEP 2 - old SDK
		public function fbAuth()
		{
			$facebook = new psp_Facebook(array(
				'appId'  => $this->fb_details['app_id'],
				'secret' => $this->fb_details['app_secret']
			));

			$state = isset($_REQUEST['state']) ? trim($_REQUEST['state']) : '';
			// check if redirect from facebook to page
			$token = $facebook->getAccessToken();

			if (trim($token) != "" && trim($state) != ""){
				$validAuth = false;

				// saving offline session into DB
				update_option('psp_fb_planner_token', $token);
				
				$userPages = array();

				// get user pages
				try {
					$user_accounts = $facebook->api('me/accounts'); 

					if(count($user_accounts) > 0 && isset($user_accounts['data'])){
						$validAuth = true;
						
						foreach ($user_accounts['data'] as $key => $value){
							if($value['category'] != 'Application'){
								$__key = (string) $value['id'];
								$userPages['pages'][ "$__key" ] = $value;
							}
						}
									
						$this->the_plugin->facebook_planner_last_status(array(
							'status' 	=> 'success',
							'step' 		=> 'auth /fbAuth',
							'msg' 		=> $user_accounts,
							'msg_stat'	=> 'auth_fbAuth',
							'msg_elem'	=> array('user_pages' => $userPages),
						));
					} else {
					}
												
				} catch (psp_FacebookApiException $e) {

					$validAuth = 0;	
					// clean token
					//update_option('psp_fb_planner_token', $token);
					
					$this->the_plugin->facebook_planner_last_status(array(
						'status' 	=> 'error',
						'step' 		=> 'auth /fbAuth',
						'msg' 		=> $e,
						'msg_stat'	=> 'exception',
					));
				}

				// get user groups
				try {
					$user_groups = $facebook->api('me/groups');
												
					if(count($user_groups) > 0 && isset($user_groups['data'])){
						$validAuth = true;
						
						foreach ($user_groups['data'] as $key => $value){
							$__key = (string) $value['id'];
							$userPages['groups'][ "$__key" ] = $value;
						}
									
						$this->the_plugin->facebook_planner_last_status(array(
							'status' 	=> 'success',
							'step' 		=> 'auth /fbAuth',
							'msg' 		=> $user_groups,
							'msg_stat'	=> 'auth_fbAuth',
							'msg_elem'	=> array('user_groups' => $userPages),
						));
					} else {
					}
												
				} catch (psp_FacebookApiException $e) {
											
					$validAuth = 0;	
					// clean token
					//update_option('psp_fb_planner_token', $token);
								
					$this->the_plugin->facebook_planner_last_status(array(
						'status' 	=> 'error',
						'step' 		=> 'auth /fbAuth',
						'msg' 		=> $e,
						'msg_stat'	=> 'exception',
					));
				}
			
				if(count($userPages) > 0){
					update_option('psp_fb_planner_user_pages', json_encode($userPages));
					
					$this->the_plugin->facebook_planner_last_status(array(
						'status' 	=> 'success',
						'step' 		=> 'auth /fbAuth',
						'msg' 		=> $userPages,
						'msg_stat'	=> 'auth_fbAuth',
						'msg_elem'	=> array('user_pages' => $userPages),
					));

					header( 'location: ' . admin_url('admin.php?page=psp#facebook_planner') );
					exit();
				}
			} else {
				$this->the_plugin->facebook_planner_last_status(array(
					'status' 	=> 'error',
					'step' 		=> 'auth /fbAuth',
					'msg' 		=> array('token' => $token, 'state' => $state),
					'msg_stat'	=> 'auth_fbAuth',
					'msg_elem'	=> array('token' => $token, 'state' => $state),
				));
			}
		}
*/

		// :: Facebook Authorization STEP 2 - new SDK
		// see aa-framework/settings-template.class.php , option 'authorization_button_fbv4' for step 1
		public function fbAuth_fbv4()
		{
			$plugin_url = admin_url('admin.php?page=psp#facebook_planner');
			if ( isset($_REQUEST['psp_redirect_url']) && ( $_REQUEST['psp_redirect_url'] != '' ) ) {
				if ( 'server_status' == $_REQUEST['psp_redirect_url'] ) {
					$plugin_url = admin_url('admin.php?page=psp_server_status');
				}
			}
			$plugin_url_ = '<a href="'.$plugin_url.'" class="psp-form-button-small psp-form-button-info">' . __('Go Back to the plugin facebook planner module and try again.', $this->the_plugin->localizationName) . '</a><br />';

			$retLogin = $this->the_plugin->facebook_do_login(array(
				'fb_details'		=> $this->fb_details,
				'plugin_url'		=> $plugin_url,
				'plugin_url_'		=> $plugin_url_,
			));
			extract($retLogin);

			if ( ! $retLogin['opStatus'] ) {
				echo $retLogin['opMsg'];
				exit;
			}

			// SUCCESS
			// User is logged in with a long-lived access token.
			// You can redirect them to a members-only page.
			if (1) {
				$userPages = array();

				$retGetPages = $this->the_plugin->facebook_get_user_pages(array(
					'facebook'			=> isset($facebook) ? $facebook : null,
					'fb_details'		=> $this->fb_details,
					'plugin_url'		=> $plugin_url,
					'plugin_url_'		=> $plugin_url_,
					'do_authorize'		=> false,
					'what'				=> 'pages' // pages | groups
				));
				if ( ! $retGetPages['opStatus'] ) {
					echo $retGetPages['opMsg'];
					exit;
				}
				$userPages['pages'] = $retGetPages['result'];

				$retGetGroups = $this->the_plugin->facebook_get_user_pages(array(
					'facebook'			=> isset($facebook) ? $facebook : null,
					'fb_details'		=> $this->fb_details,
					'plugin_url'		=> $plugin_url,
					'plugin_url_'		=> $plugin_url_,
					'do_authorize'		=> false,
					'what'				=> 'groups' // pages | groups
				));
				if ( ! $retGetGroups['opStatus'] ) {
					echo $retGetGroups['opMsg'];
					exit;
				}
				$userPages['groups'] = $retGetGroups['result'];
				
				update_option('psp_fb_planner_user_pages', json_encode($userPages));
					
				$this->the_plugin->facebook_planner_last_status(array(
					'status' 	=> 'success',
					'msg' 		=> $userPages,
					'from_file' => str_replace($this->the_plugin->cfg['paths']['plugin_dir_path'], '', __FILE__),
					'from_func' => __FUNCTION__ != __METHOD__ ? __METHOD__ : __FUNCTION__,
					'from_line' => __LINE__,
				));
					
				header( 'location: ' . $plugin_url );
				exit();
			}
		}

		public function fb_save_meta( $post_id ) {
			global $wpdb;

			// do not save if this is an auto save routine
			if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
				return $post_id;
			}

			//verify post is not a revision
			if ( wp_is_post_revision( $post_id ) ) {
				return $post_id;
			}

			/************************/
			/* TEXT DATA
			/************************/

			if ( isset( $_POST['psp_wplannerfb_message'] ) ) {
				update_post_meta( $post_id, 'psp_wplannerfb_message', strip_tags( $_POST['psp_wplannerfb_message'] ) );
			}

			if ( isset( $_POST['psp_wplannerfb_title'] ) ) {
				update_post_meta( $post_id, 'psp_wplannerfb_title', strip_tags( $_POST['psp_wplannerfb_title'] ) );
			}

			if ( isset( $_POST['psp_wplannerfb_permalink'] ) ) {
				if( trim($_POST['psp_wplannerfb_permalink']) == 'custom_link' ) {
					$wplannerfb_permalink = $_POST['psp_wplannerfb_permalink_value'];
				}else{
					//$wplannerfb_permalink = get_permalink($post_id);
					$wplannerfb_permalink = 'post_link';
				}

				update_post_meta( $post_id, 'psp_wplannerfb_permalink', $wplannerfb_permalink );
			}

			if ( isset( $_POST['psp_wplannerfb_caption'] ) ) {
				update_post_meta( $post_id, 'psp_wplannerfb_caption', strip_tags( $_POST['psp_wplannerfb_caption'] ) );
			}

			if ( isset( $_POST['psp_wplannerfb_description'] ) ) {
				update_post_meta( $post_id, 'psp_wplannerfb_description', strip_tags( $_POST['psp_wplannerfb_description'] ) );
			}

			if ( isset( $_POST['psp_wplannerfb_image'] ) ) {
				update_post_meta( $post_id, 'psp_wplannerfb_image', strip_tags( $_POST['psp_wplannerfb_image'] ) );
			}
			
			if ( isset( $_POST['psp_wplannerfb_useimage'] ) ) {
				update_post_meta( $post_id, 'psp_wplannerfb_useimage', strip_tags( $_POST['psp_wplannerfb_useimage'] ) );
			}

			/************************/
			/* SCHEDULER DATA
			/************************/

			// AUTO-SUBMIT on PUBLISH (Scheduler & Publish now)
			if ( (isset($_POST['psp_wplannerfb_publish_at_save']) && trim($_POST['psp_wplannerfb_publish_at_save']) == 'psp_wplannerfb_publish_at_save') && (isset($_POST['psp_wplannerfb_post_privacy']) && trim($_POST['psp_wplannerfb_post_privacy']) != '') ) {
				$page_group = isset($_POST['psp_wplannerfb_post_topage_group']) ? $_POST['psp_wplannerfb_post_to_page_group'] : '';
				$wherePost 	= serialize(array('profile' => (isset($_POST['psp_wplannerfb_post_toprofile']) ? 'on' : 'off'), 'page_group' => $page_group));
				$privacy 	= $_POST['psp_wplannerfb_post_privacy'];
			}
			else if ( (isset($_POST['psp_wplannerfb_now_publish_at_save']) && trim($_POST['psp_wplannerfb_now_publish_at_save']) == 'psp_wplannerfb_now_publish_at_save') && (isset($_POST['psp_wplannerfb_now_post_privacy']) && trim($_POST['psp_wplannerfb_now_post_privacy']) != '') ) {
				$page_group = isset($_POST['psp_wplannerfb_now_post_topage_group']) ? $_POST['psp_wplannerfb_now_post_to_page_group'] : '';
				$wherePost 	= serialize(array('profile' => isset($_POST['psp_wplannerfb_now_post_toprofile']) ? 'on' : 'off', 'page_group' => $page_group));
				$privacy 	= $_POST['psp_wplannerfb_now_post_privacy'];
			}

			if ( ((isset($_POST['psp_wplannerfb_publish_at_save']) && trim($_POST['psp_wplannerfb_publish_at_save']) == 'psp_wplannerfb_publish_at_save') && (isset($_POST['psp_wplannerfb_post_privacy']) && trim($_POST['psp_wplannerfb_post_privacy']) != '')) ||
			((isset($_POST['psp_wplannerfb_now_publish_at_save']) && trim($_POST['psp_wplannerfb_now_publish_at_save']) == 'psp_wplannerfb_now_publish_at_save') && (isset($_POST['psp_wplannerfb_now_post_privacy']) && trim($_POST['psp_wplannerfb_now_post_privacy']) != '')) )
			{
				// Plugin facebook utils load
				require_once ( 'app.fb-utils.class.php' );

				// start instance of fb post planner
				$fbUtils = psp_fbPlannerUtils::getInstance();
				$fbUtils->publishToWall($post_id, $wherePost, $privacy);
			}
			// END AUTO-SUBMIT

			if ( (isset($_POST['psp_wplannerfb_post_toprofile']) || isset($_POST['psp_wplannerfb_post_topage_group'])) && trim($_POST['psp_wplannerfb_date_hour']) != '' && isset($_POST['psp_wplannerfb_post_privacy'])) {
				$date_hour = $_POST['psp_wplannerfb_date_hour'];
				$date_hour = explode(' @ ', $_POST['psp_wplannerfb_date_hour']);

				// date format
				$date = $date_hour[0];
				$start_date = date('Y-m-d', strtotime($date));

				// hour format
				$hf = explode(' ', $date_hour[1]);
				$start_hour = $hf[0];

				// Final DATETIME MySQL format (first time running)
				$run_date = $start_date.' '.$start_hour.':00:00';

				// check if post_id exists
				$checkIfPostIdExist = $wpdb->get_var( "SELECT `id` FROM `" . ( $wpdb->prefix . 'psp_post_planner_cron' ) . "` WHERE 1=1 AND id_post=" . $post_id );

				if( (int)$checkIfPostIdExist == 0 ) {
					$wpdb->insert(
						$wpdb->prefix . 'psp_post_planner_cron',
						array(
							'id_post' => $post_id,
							'post_to' => serialize(array(
								'profile' => isset($_POST['psp_wplannerfb_post_toprofile']) ? 'on' : 'off',
								'page_group' => isset($_POST['psp_wplannerfb_post_topage_group']) ? $_POST['psp_wplannerfb_post_to_page_group'] : ''
							)),
							'post_privacy' =>  $_POST['psp_wplannerfb_post_privacy'],
							'email_at_post' => !$_POST['psp_wplannerfb_email_at_post'] ? 'off' : 'on',
							'run_date' => $run_date,
							'repeat_status' => !$_POST['psp_wplannerfb_repeating'] ? 'off' : 'on',
							'repeat_interval' => $_POST['psp_wplannerfb_repeating_interval']
						)
					);
				}else{
					$wpdb->update(
						$wpdb->prefix . 'psp_post_planner_cron',
						array(
							'post_to' => serialize(array(
								'profile' => isset($_POST['psp_wplannerfb_post_toprofile']) ? 'on' : 'off',
								'page_group' => isset($_POST['psp_wplannerfb_post_topage_group']) ? $_POST['psp_wplannerfb_post_to_page_group'] : ''
							)),
							'post_privacy' =>  $_POST['psp_wplannerfb_post_privacy'],
							'email_at_post' => !$_POST['psp_wplannerfb_email_at_post'] ? 'off' : 'on',
							'run_date' => $run_date,
							'repeat_status' => !$_POST['psp_wplannerfb_repeating'] ? 'off' : 'on',
							'repeat_interval' => $_POST['psp_wplannerfb_repeating_interval']
						),
						array( 'id' => $checkIfPostIdExist )
					);

					if( $run_date > date('Y-m-d H:00:00') ) {
						$wpdb->update(
							$wpdb->prefix . 'psp_post_planner_cron',
							array(
								'status' => 0
							),
							array( 'id' => $checkIfPostIdExist )
						);
					}
				}
			}
		}
		
		public function fb_postFB_callback () {

			$req = $_REQUEST; //$_POST;
			$id 		= (int) $req['postId'];
			$wherePost 	= serialize($req['postTo']);
			$privacy 	= $req['privacy'];

			$postData = array(
				'name' 			=> isset($req['psp_wplannerfb_title']) ? $req['psp_wplannerfb_title'] : '',
				'link' 			=> isset($req['psp_wplannerfb_permalink']) && trim($req['psp_wplannerfb_permalink'])
					== 'custom_link' ? trim($req['psp_wplannerfb_permalink_value']) : get_permalink($id),
				'description' 	=> isset($req['psp_wplannerfb_description']) ? $req['psp_wplannerfb_description'] : '',
				'caption' 		=> isset($req['psp_wplannerfb_caption']) ? $req['psp_wplannerfb_caption'] : '',
				'message' 		=> isset($req['psp_wplannerfb_message']) ? $req['psp_wplannerfb_message'] : '',
				'picture'	 	=> isset($req['psp_wplannerfb_image']) ? $req['psp_wplannerfb_image'] : '',
				'use_picture' 	=> isset($req['psp_wplannerfb_useimage']) ? $req['psp_wplannerfb_useimage'] : ''
			);

			// Plugin facebook utils load
			require_once ( 'app.fb-utils.class.php' );

			// start instance of fb post planner
			$fbUtils = psp_fbPlannerUtils::getInstance();
			$publishToFBResponse = $fbUtils->publishToWall($id, $wherePost, $privacy, $postData);
				
			if ( isset($publishToFBResponse, $publishToFBResponse['opStatus']) && ($publishToFBResponse['opStatus'] == 'valid') ) {
				//echo 'OK';
			}else{
				//echo 'ERROR';
			}
			//die(); // this is required to return a proper result
			die(json_encode($publishToFBResponse));
		}

		public function fb_getFeaturedImage() {
			$wplannerfb_settings = $this->fb_details;

			$postId = (int)$_POST['postId'];
			$result = array(
				'text' => 'Post has no featured image.',
				'status' => 'ERR'
			);

			// check if the post has a Post Thumbnail assigned to it.
			if ( has_post_thumbnail($postId) ) {
				//$__featuredImage = get_the_post_thumbnail($postId, 'large'); // img html format
				$imgSize = $wplannerfb_settings['featured_image_size'];
				$imgSize_predefined = $wplannerfb_settings['featured_image_size_predefined'];
				
				//add_filter('upload_dir', 'facebook_upload_dir');
				//$upload = wp_upload_dir();
				//remove_filter('upload_dir', 'facebook_upload_dir');

				if( trim($imgSize) != '' && $imgSize_predefined == '' ) {
					$imgSize = explode('x', strtolower($imgSize));
					$imgSize = array('width' => trim($imgSize[0]), 'height' => trim($imgSize[1]));
					$imgCrop = $wplannerfb_settings['featured_image_size_crop'] == 'true' ? true : false;
					$suffix = $imgSize['width'] .'x'. $imgSize['height'] . '_psp_wplannerfb';
					$dest_path = wp_upload_dir();
					$jpeg_quality = 90;

					$__featuredImage = wp_get_attachment_image_src( get_post_thumbnail_id( $postId ), 'full' );
					$__img_path = str_replace( str_replace('/plugins', '', WP_PLUGIN_URL), str_replace('/plugins', '', WP_PLUGIN_DIR), $__featuredImage[0] );
					$__resizedFeaturedImage = image_resize( $__img_path, $imgSize['width'], $imgSize['height'], $imgCrop, $suffix, $dest_path['path'], $jpeg_quality );
					$__resizedFeaturedImage = str_replace( str_replace('/plugins', '', WP_PLUGIN_DIR), str_replace('/plugins', '', WP_PLUGIN_URL), $__resizedFeaturedImage );
				} else {
					$__resizedFeaturedImage = wp_get_attachment_image_src( get_post_thumbnail_id( $postId ), $imgSize_predefined );
					$__resizedFeaturedImage = $__resizedFeaturedImage[0];
				}

				$result = array(
					'text'	=> $__resizedFeaturedImage,
					'status' => (!empty($__resizedFeaturedImage) ? 'OK' : 'ERR')
				);
			}

			if(count($result) > 0){
				echo json_encode($result);
			}else{
				// Error messages in JSON format!
			}
			die(); // this is required to return a proper result
		}
		
		public function facebook_upload_dir($upload) {
			// create cache directory
			clearstatcache();
			$upload_dir = wp_upload_dir();
			if (! is_dir( $upload_dir['path'] . '' . $this->file_cache_directory ) ) {
				@mkdir( $upload_dir['path'] . '' . $this->file_cache_directory );
				if (! is_dir( $upload_dir['path'] . '' . $this->file_cache_directory ) ) {
					die("Could not create the file cache directory.");
					return array_merge( $ret, array(
						'resp' => 'Could not create the file cache directory.'
					));
				}
			}
					
			$upload['subdir']	= $this->file_cache_directory . $upload['subdir'];
			$upload['path']		= $upload['basedir'] . $upload['subdir'];
			$upload['url']		= $upload['baseurl'] . $upload['subdir'];
			return $upload;
		}
		
		/**
		 * Upload Image Button
		 *
		 * is based on settings option:
		 * $elm_id is the array KEY
		 * $elm_data is the array VALUE, which is also an array
		 	'image' => array(
				'type' 		=> 'upload_image',
				'size' 		=> 'large',
				'title' 	=> 'Quiz image',
				'value' 	=> 'Upload image',
				'thumbSize' => array(
					'w' => '100',
					'h' => '100',
					'zc' => '2',
				),
				'desc' 		=> 'Choose the image'
			)
		 */
		private function uploadImage( $elm ) {
			global $psp;

			// loop the box elements now
			foreach ( $elm as $elm_id => $value ){
				
				$val = '';
				
				// Set default value to $val
				if ( isset( $value['std'] ) && !empty( $value['std'] ) ) {
					$val = $value['std'];
				}
				
				// If the option is already saved, ovveride $val
				if ( isset( $value['db_value'] ) && !empty( $value['db_value'] ) ) {
					$val = $value['db_value'];
				}
				

				$html[] = '<table border="0" width="560px">';
				$html[] = '<tr>';
				$html[] = 	'<td width="480">';
				$html[] = 		'<input class="upload-input-text" style="width: 99%;" name="' . ( $elm_id ) . '" id="' . ( $elm_id ) . '_upload" type="text" value="' . ( $val ) . '" />';
				
				$html[] = 		'<script type="text/javascript">
											(function($) {
												jQuery("#' . ( $elm_id ) . '_upload").data({
													"w": ' . ( $value['thumbSize']['w'] ) . ',
													"h": ' . ( $value['thumbSize']['h'] ) . ',
													"zc": ' . ( $value['thumbSize']['zc'] ) . '
												});
				';
				/*								jQuery(document).ready(function() {
													jQuery("#reset_' . ( $elm_id ) . '").on("click", function(e) {
														e.preventDefault();
														jQuery("#' . ( $elm_id ) . '_upload").val(\'\');
														//jQuery("#uploaded_image_' . ( $elm_id ) . '").empty();
														//jQuery("#reset_' . ( $elm_id ) . ', #image_' . ( $elm_id ) . '").remove();
													});
												});
				*/
				$html[] = 		'
											})(jQuery);
										</script>';
	
				$html[] = 	'</td>';
				$html[] = '<td>';
				$html[] = 		'<a href="#" class="button upload_button" id="' . ( $elm_id ) . '">' . ( $value['value'] ) . '</a> ';
				//$html[] = 		'<a href="#" class="button reset_button ' . $hide . '" id="reset_' . ( $elm_id ) . '" title="' . ( $elm_id ) . '">' . __('Remove', 'psp') . '</a> ';
				$html[] = '</td>';
				$html[] = '</tr>';
				$html[] = '</table>';
	
				//<div style="display:block;" id="wrap_uploaded_image_' . ( $elm_id ) . '">
				$html[] = '<a class="thickbox" id="uploaded_image_' . ( $elm_id ) . '" href="' . ( $val ) . '" target="_blank">';
				if(!empty($val)){
					//$html[] = '<a href="#" class="button" id="reset_' . ( $elm_id ) . '" title="' . ( $elm_id ) . '">' . __('Remove', 'psp') . '</a><br />';
					$imgSrc = $psp->image_resize( $val, $value['thumbSize']['w'], $value['thumbSize']['h'], $value['thumbSize']['zc'] );
					$html[] = '<img style="border: 1px solid #dadada;" id="image_' . ( $elm_id ) . '" src="' . ( $imgSrc ) . '" />';
				}
				$html[] = '</a>';
				//</div>
	
				$html[] = 		'<script type="text/javascript">
											psp_loadAjaxUpload( jQuery("#' . ( $elm_id ) . '") );
										</script>';
			}
			
			// return the $html
			return implode("\n", $html);
		}
		
		 
		/*
		 * printBaseInterface, method
		 * --------------------------
		 *
		 * this will add the base DOM code for you options interface
		 */
			private function printBaseInterface()
		{
?>
		<script type="text/javascript" src="<?php echo $this->module_folder;?>app.class.js" ></script>
		
		<div class="<?php echo $this->the_plugin->alias; ?>">
			
			<div class="<?php echo $this->the_plugin->alias; ?>-content">

				<?php
				// show the top menu
				pspAdminMenu::getInstance()->make_active('advanced_setup|facebook_planner')->show_menu();
				?>
				

				<!-- Content -->
				<section class="<?php echo $this->the_plugin->alias; ?>-main">
					
					<?php 
					echo psp()->print_section_header(
						$this->module['facebook_planner']['menu']['title'],
						$this->module['facebook_planner']['description'],
						$this->module['facebook_planner']['help']['url']
					);
					?>
					
					<div id="<?php echo $this->the_plugin->alias; ?>-gAnalytics-wrapper" class="panel panel-default <?php echo $this->the_plugin->alias; ?>-panel">

						<div class="psp-box-update">
							<h2>Automatically post content from your website to Facebook!<br/></h2>


							<p class="psp-update-text">Using the Facebook Planner Module, you can schedule and post any content from your website straight to Facebook Pages / Groups / Profile and so on. </p>

							<p class="psp-update-button">
								<a href="https://codecanyon.net/item/premium-seo-pack-wordpress-plugin/6109437?ref=AA-Team" class="psp-form-button psp-form-button-success" target="_blank">Click here to Purchase Full Version</a>
							</p>
						</div>
					</div>
				</section>
			</div>
		</div>

<?php
		}

		/*		
		public function delete_bulk_rows() {
			global $wpdb; // this is how you get access to the database
			
			$request = array(
				'id' 			=> isset($_REQUEST['id']) && !empty($_REQUEST['id']) ? trim($_REQUEST['id']) : 0
			);
			if ($request['id']!=0) {
				$__rq2 = array();
				$__rq = explode(',', $request['id']);
				if (is_array($__rq) && count($__rq)>0) {
					foreach ($__rq as $k=>$v) {
						$__rq2[] = (int) $v;
					}
				} else {
					$__rq2[] = $__rq;
				}
				$request['id'] = implode(',', $__rq2);
			}
				
			$table_name = $wpdb->prefix . "psp_post_planner_cron";
			if ($wpdb->get_var("show tables like '$table_name'") == $table_name) {

				// delete record
				$query_delete = "DELETE FROM " . ($table_name) . " where 1=1 and id in (" . ($request['id']) . ");";
				$__stat = $wpdb->query($query_delete);
				
				//$query_update = "UPDATE " . ($table_name) . " set
				//		deleted=1
				//		where id in (" . ($request['id']) . ");";
				//$__stat = $wpdb->query($query_update);
				
				if ($__stat!== false) {
					//keep page number & items number per page
					$_SESSION['pspListTable']['keepvar'] = array('posts_per_page'=>true);

					die( json_encode(array(
						'status' => 'valid',
						'msg'	 => ''
					)) );
				}
			}
			
			die( json_encode(array(
				'status' => 'invalid',
				'msg'	 => ''
			)) );
		}
		*/


/*
		// :: Server Status module / oauth step 1 - old SDK
		// oauth step 1: redirecting a browser (popup, or full page if needed) to a Facebook URL
		// this will return a link to facebook auth and save data into wp_option
		public function facebookAuthorizeApp()
		{
			$saveform = isset($_REQUEST['saveform']) ? trim($_REQUEST['saveform']) : 'no';

			if ( $saveform == 'yes' ) {
				$params = isset($_REQUEST['params']) ? $_REQUEST['params'] : '';
				parse_str( $params, $arr_params );

				$saveID = $arr_params['box_id'];

				// clean up array before save into DB 
				unset($arr_params['box_id']);
				unset($arr_params['box_nonce']);

				$this->the_plugin->save_theoption( $saveID, $arr_params);
			} else {
				$arr_params = $this->the_plugin->getAllSettings('array', 'facebook_planner');
			}

				// setup the wrapper
				$this->fb = new psp_Facebook(array(
					'appId'  => $arr_params['app_id'],
					'secret' => $arr_params['app_secret']
				));

			// publish_actions instead of publish_stream | offline_access - deprecated
			$fb_permissions = 'email,publish_actions,manage_pages,user_groups'; // optional
			$loginUrl = $this->fb->getLoginUrl(
				array(
					'scope' => $fb_permissions,
					'redirect_uri' => $arr_params['redirect_uri']
				)
			);

			// Get the Auth-Url
			die(json_encode(array(
				'status' => 'valid',
				'auth_url' => $loginUrl
			)));
		}

		// :: Server Status module / oauth step 2 - old SDK
		// oauth step 2: receiving the authorization code, the application can exchange the code for an access token and a refresh token
		public function check_auth_callback()
		{
			// check in the server request uri if the keyword psp_seo_fb_oauth exists!
			$request_uri = isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : '';
			if( preg_match("/psp_seo_fb_oauth/i", $request_uri ) ){
				$code = isset($_GET['state']) ? $_GET['state'] : '';
				
				$fb_details = $this->the_plugin->getAllSettings('array', 'facebook_planner');

				if( trim($code) != "" ){
					
					if( (isset($fb_details['app_id']) && trim($fb_details['app_id']) != '') && ( isset($fb_details['app_secret']) && trim($fb_details['app_secret']) != '') ) {
						$facebook = new psp_Facebook(array(
							'appId'  => $fb_details['app_id'],
							'secret' => $fb_details['app_secret']
						));
					}

					if( isset($facebook) ) {
						$validAuth = false;
						// $state = isset($_REQUEST['state']) ? trim($_REQUEST['state']) : '';
						$dbToken = get_option('psp_fb_planner_token');

						if(trim($dbToken) != ""
						//&& $state == ""
						) {
							$facebook->setAccessToken($dbToken);
											
							try {
								// get user profile
								$user_profile = $facebook->api('/me');

								if(count($user_profile) > 0){
									$validAuth = true;
									
									$this->the_plugin->facebook_planner_last_status(array(
										'status' 	=> 'success',
										'step' 		=> 'profile',
										'msg' 		=> $user_profile,
										'msg_stat'	=> 'profile',
										'msg_elem'	=> '--psp--',
									));
								} else {
								
									$this->the_plugin->facebook_planner_last_status(array(
										'status' 	=> 'error',
										'step' 		=> 'profile',
										'msg' 		=> $user_profile,
										'msg_stat'	=> 'profile',
										'msg_elem'	=> '--psp--',
									));
								}
												
							} catch (psp_FacebookApiException $e) {

								$validAuth = 0;	
								// clean token
								//update_option('psp_fb_planner_token', $token);
								
								$this->the_plugin->facebook_planner_last_status(array(
									'status' 	=> 'error',
									'step' 		=> 'profile',
									'msg' 		=> $e,
									'msg_stat'	=> 'exception',
								));
							}
						}
								
						if( $validAuth === false ) {

							$this->the_plugin->facebook_planner_last_status(array(
								'status' 	=> 'error',
								'step' 		=> 'auth',
								'msg' 		=> 'no db token',
								'msg_stat'	=> 'auth',
								'msg_elem'	=> 'no db token',
							));
						}
					}

					die('
					<script>
					window.onunload = function() {
						if (window.opener && !window.opener.closed) {
							window.opener.pspPopUpClosed();
						}
					};
					window.close();
					</script>;
					');

				} else {
					$this->the_plugin->facebook_planner_last_status(array(
						'status' 	=> 'error',
						'step' 		=> 'code',
						'msg' 		=> $code,
						'msg_stat'	=> 'code',
						'msg_elem'	=> 'code',
					));
				}
			}
		}

		// :: Server Status module / validate login - old SDK
		public function makeoAuthLogin() {
			$fb_details = $this->the_plugin->getAllSettings('array', 'facebook_planner');
			
			if( (isset($fb_details['app_id']) && trim($fb_details['app_id']) != '') && ( isset($fb_details['app_secret']) && trim($fb_details['app_secret']) != '') ) {
				$facebook = new psp_Facebook(array(
					'appId'  => $fb_details['app_id'],
					'secret' => $fb_details['app_secret']
				));
			}
									
			if( isset($facebook) ) {
				$validAuth = false;
				// $state = isset($_REQUEST['state']) ? trim($_REQUEST['state']) : '';
				$dbToken = get_option('psp_fb_planner_token');

				if(trim($dbToken) != ""
				//&& $state == ""
				) {
					$facebook->setAccessToken($dbToken);
											
					try {
						// get user profile
						$user_profile = $facebook->api('/me');
												
						if(count($user_profile) > 0){
							$validAuth = true;
									
							$this->the_plugin->facebook_planner_last_status(array(
								'status' 	=> 'success',
								'step' 		=> 'profile',
								'msg' 		=> $user_profile,
								'msg_stat'	=> 'profile',
								'msg_elem'	=> '--psp--',
							));
						} else {
								
							$this->the_plugin->facebook_planner_last_status(array(
								'status' 	=> 'error',
								'step' 		=> 'profile',
								'msg' 		=> $user_profile,
								'msg_stat'	=> 'profile',
								'msg_elem'	=> '--psp--',
							));
						}
												
					} catch (psp_FacebookApiException $e) {
											
						$validAuth = 0;	
						// clean token
						//update_option('psp_fb_planner_token', $token);
								
						$this->the_plugin->facebook_planner_last_status(array(
							'status' 	=> 'error',
							'step' 		=> 'profile',
							'msg' 		=> $e,
							'msg_stat'	=> 'exception',
						));
					}
				}
								
				if( $validAuth === false ) {

					$this->the_plugin->facebook_planner_last_status(array(
						'status' 	=> 'error',
						'step' 		=> 'auth',
						'msg' 		=> 'no db token',
						'msg_stat'	=> 'auth',
						'msg_elem'	=> 'no db token',
					));
				}
			}

			return $validAuth ? true : false;
		}
*/

		// facebook authorization validation - new SDK
		// used in /modules/server_status/init.php , /modules/server_status/ajax.php
		public function makeoAuthLogin_fbv4( $pms=array() ) {
			$pms = array_merge(array(
				'fb_details'		=> $this->fb_details,
				'psp_redirect_url'	=> '',
			), $pms);
			extract($pms);

			$is_loggedin = $this->the_plugin->facebook_is_loggedin($pms);
			return $is_loggedin;
		}

		public function ajax_requests_metabox() {
			$action = isset($_REQUEST['sub_action']) ? $_REQUEST['sub_action'] : 'none';

			$allowed_action = array( 'load_box' );

			if( !in_array($action, $allowed_action) ){
				die(json_encode(array(
					'status'		=> 'invalid',
					'html'			=> 'Invalid action!'
				)));
			}

			
			if ( 'load_box' == $action ) {
				$req = array(
					'post_id'		=> isset($_REQUEST['post_id']) ? (int) $_REQUEST['post_id'] : 0,
				);
				extract($req);
				//var_dump('<pre>', $req, '</pre>'); echo __FILE__ . ":" . __LINE__;die . PHP_EOL;

				$pms = $req;
				$html = $this->display_page_options($pms);

				die(json_encode(array(
					'status'	=> 'valid',
					'html'		=> $html,
				)));
			}
			
			die(json_encode(array(
				'status' 		=> 'invalid',
				'html'		=> 'Invalid action!'
			)));
		}

		/**
	     * Singleton pattern
	     *
	     * @return pspFacebook_Planner Singleton instance
	     */
		static public function getInstance() {
			if (!self::$_instance) {
				self::$_instance = new self;
			}
			return self::$_instance;
		}


    	/**
     	 * Cronjobs methods
     	 */
		public function facebook_wplanner_do_this_hourly() {
			// Plugin cron class loading
			require_once ( 'app.cron.class.php' );
		}

		public function facebook_cronjob( $pms, $return='die' ) {
			$ret = array('status' => 'failed');

			//$current_cron_status = $pms['status']; //'new'; //

			$is_psp_cron = true;
			// Plugin cron class loading
			require_once ( 'app.cron.class.php' );

            $ret = array_merge($ret, array(
                'status'            => 'done',
            ));
            return $ret;
		}
	}
}

function pspFP_cronPostWall_event() {
	// Initialize the pspFacebook_Planner class
	$pspFacebook_Planner = pspFacebook_Planner::getInstance();
	$pspFacebook_Planner->facebook_wplanner_do_this_hourly();
}

// Initialize the pspFacebook_Planner class
$pspFacebook_Planner = pspFacebook_Planner::getInstance();