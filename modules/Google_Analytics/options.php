<?php
/**
 * module return as json_encode
 * http://www.aa-team.com
 * =======================
 *
 * @author		Andrei Dinca, AA-Team
 * @version		1.0
 */

function __pspGA_authorize_section() {
    global $psp;
    
    $html = array();
    
    // get the module init file
    require_once( $psp->cfg['paths']['plugin_dir_path'] . 'modules/Google_Analytics/init.php' );
    // Initialize the pspTinyCompress class
    $pspGA = new pspGoogleAnalytics();
    
    $connection_status = apply_filters('psp_google_analytics_get_profiles', '');
    $connection_msg = '<span style="color: red; font-weight: bold; margin-right: 1rem;">'
    	. __('NOT authorized.', 'psp')
    	. '</span>';
    if ( is_array($connection_status) && ! empty($connection_status) && ! isset($connection_status[0]) ) {
		$connection_msg = '<p>' . sprintf(
			'<span style="color: green; font-weight: bold; margin-right: 1rem;">'
				. __('Successfull authorization.', 'psp')
				. '</span>'
				. __('You have the following profiles: %s', 'psp'),
			'<ul><li>' . implode('</li><li>', $connection_status) . '</li></ul>'
		) . '</p>';
    }

    ob_start();
    ?>

<div class="panel-body psp-panel-body psp-form-row " style="display: block;">
	<label class="psp-form-label" for="auth">Authorization</label>
	<div class="psp-form-item large">
		<input type="" style="width:180px;" value="<?php _e('Authorize the app', 'psp'); ?>" class="psp-form-button psp-form-button-info  psp-google-authorize-app">
		<?php echo $connection_msg; ?>
	</div>
</div>

    <?php
    $content = ob_get_contents();
    ob_end_clean();
    $html[] = $content;
    
    return implode( "\n", $html );
}

global $psp;
echo json_encode(
	array(
		$tryed_module['db_alias'] => array(
			/* define the form_messages box */
			'google_analytics' => array(
				'title' 	=> __('Google Analytics', 'psp'),
				'icon' 		=> '{plugin_folder_uri}assets/menu_icon.png',
				'size' 		=> 'grid_4', // grid_1|grid_2|grid_3|grid_4
				'header' 	=> true, // true|false
				'toggler' 	=> false, // true|false
				'buttons' 	=> array(
					/*'save' => array(
						'value' => __('Save settings', 'psp'),
						'color' => 'success',
						'action'=> 'psp-saveOptions'
					)*/
				), // true|false
				'style' 	=> 'panel', // panel|panel-widget

				// create the box elements array
				'elements'	=> array(
					array(
						'type' 		=> 'html',
						
						//'html' 		=> '<div class="panel-heading psp-panel-heading">' . __('<h2>Basic Setup</h2>', 'psp') . '</div>',
					),
					
					array(
						'type' 		=> 'html',

						'html' 		=> '<div class="psp-box-update">' . __('
							<h2>It is important in a business that you get the analityics data at the right time. <br/></h2> 	<p class="psp-update-text">Using the Google Analytics Module, you will get real time statistics, so you can see how your website is performing right now. <br/> By upgrading to Premium SEO Pack full version, you can see real time statistics, straight from your google analitycs account.</p><p class="psp-update-button">
								<a href="https://codecanyon.net/item/premium-seo-pack-wordpress-plugin/6109437?ref=AA-Team" class="psp-form-button psp-form-button-success" target="_blank">Click here to Purchase Full Version</a>
							</p>', 'psp') . '</div>',
					),
						

				)
			)
		)
	)
);