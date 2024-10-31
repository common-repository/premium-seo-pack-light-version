<?php
/**
 * module return as json_encode
 * http://www.aa-team.com
 * =======================
 *
 * @author		Andrei Dinca, AA-Team
 * @version		1.0
 */

if ( ! function_exists('psp_All404PagesRedirectTo') ) {
function psp_All404PagesRedirectTo( $istab = '', $is_subtab='', $what='' ) {
	global $psp;

	$istab = ''; $is_subtab = '';

	$home_url = trailingslashit( get_home_url() );

	$uniqueKey = 'all_404_pages_to';
	$uniqueKey_cf = 'all_404_pages_to_custom';

	$options = $psp->getAllSettings( 'array', 'Link_Redirect' );

	$val = '';
	if ( isset($options["$uniqueKey"]) ) {
		$val = $options["$uniqueKey"];
	}

	$val_cf = '';
	if ( isset($options["$uniqueKey_cf"]) ) {
		$val_cf = $options["$uniqueKey_cf"];
	}

	ob_start();
?>
<div class="panel-body psp-panel-body psp-form-row<?php echo ($istab!='' ? ' '.$istab : ''); ?><?php echo ($is_subtab!='' ? ' '.$is_subtab : ''); ?>">
	<label class="psp-form-label"><?php _e('Redirect all 404 pages to:', 'psp'); ?></label>
	<div class="psp-form-item large">
	<!--<span class="formNote">&nbsp;</span>-->
		<select id="<?php echo $uniqueKey; ?>" name="<?php echo $uniqueKey; ?>" style="width:350px;">
			<?php
			if (1) {
				$whereto = array(
					''						=> __('Disabled', 'psp'),
					'homepage'				=> __('Homepage', 'psp'),
					'custom_url'			=> __('Custom URL', 'psp')
				);
			}
			foreach ($whereto as $k => $v){
				echo 	'<option value="' . ( $k ) . '" ' . ( $val == $k ? 'selected="true"' : '' ) . '>' . ( $v ) . '</option>';
			}
			?>
		</select>
		<span class="psp-form-note">
		Disabled = if you need to, you must redirect your 404 pages yourself (a posibility is by using .htaccess rules) or with our 404 Monitor module.
		<br/>
		Homepage = <span style="font-weight: bold; color: green;"><?php echo $home_url; ?></span>
		</span>
	</div>
	<div class="psp-form-item small" style="margin-top:5px;">
		<span class=""><?php echo __('Enter custom URL:', 'psp'); ?></span>&nbsp;
		<input id="<?php echo $uniqueKey_cf; ?>" name="<?php echo $uniqueKey_cf; ?>" type="text" value="<?php echo $val_cf; ?>">
	</div>
</div>
	<script>
// Initialization and events code for the app
psp_All404PagesRedirectTo = (function ($) {
	"use strict";
	
	// init function, autoload
	(function init() {
		// load the triggers
		$(document).ready(function(){
			triggers();
		});
	})();
	
	function custom_field(val) {
		var cf = $('#<?php echo $uniqueKey_cf; ?>'), cfp = cf.parent();
		
		if ( val =='custom_url' ) {
			cfp.show();
		} else {
			cfp.hide();
		}
	}
	
	// triggers
	function triggers()
	{
		custom_field( $('#<?php echo $uniqueKey; ?>').val() );
		
		$('#<?php echo $uniqueKey; ?>').on('change', function (e) {
			e.preventDefault();
	
			custom_field( $(this).val() );
		});
	}
	
	// external usage
	return {
	}
})(jQuery);
	</script>
<?php
	$output = ob_get_contents();
	ob_end_clean();
	return $output;
}
}

global $psp;

echo json_encode(
	array(
		$tryed_module['db_alias'] => array(
			/* define the form_messages box */
			'Link_Redirect' => array(
				'icon' 		=> '{plugin_folder_uri}assets/menu_icon.png',
				'size' 		=> 'grid_4', // grid_1|grid_2|grid_3|grid_4
				'header' 	=> true, // true|false
				'toggler' 	=> false, // true|false
				'buttons' 	=> false, // true|false
				'style' 	=> 'panel', // panel|panel-widget

				// create the box elements array
				'elements'	=> array(
					array(
						'type' 		=> 'message',
						
					
						'html' 		=> '<div class="psp-box-update">' . __('
							<h2>Did you know that 301 and 302 Redirect affect SEO?  <br/></h2> 	<p class="psp-update-text">Using the Link Redirect Module, you can take care of custom redirects in your website. A 301 redirect is the HTTP status code for when a page has been moved permanently to a new location or URL. <br/> If it is not done properly, your SEO Score will be affected too. This is valid for any type of redirect.</p><p class="psp-update-button">
								<a href="https://codecanyon.net/item/premium-seo-pack-wordpress-plugin/6109437?ref=AA-Team" class="psp-form-button psp-form-button-success" target="_blank">Click here to Purchase Full Version</a>
							</p>', 'psp') . '</div>',
					), 
					
					 
				)
			)
		)
	)
);