<?php
/**
 * module return as json_encode
 * http://www.aa-team.com
 * ======================
 *
 * @author		Andrei Dinca, AA-Team
 * @version		1.0
 */

function __pspTC_get_image_sizes() {
    global $psp, $_wp_additional_image_sizes;

    $cache_name = 'psp_tiny_compress_wp_sizes';
    $cacheSizes = get_transient( $cache_name );

    $sizes = array();
    if ( !empty($cacheSizes) && is_array($cacheSizes) ) {
        $sizes = $cacheSizes;
    } else {

        // original image
        {
            $sizes[ '__original' ] = array(
                'width' => 0,
                'height' => 0,
            );
        }
            
        $get_intermediate_image_sizes = get_intermediate_image_sizes();
    
        // create array with sizes.
        // original source: http://codex.wordpress.org/Function_Reference/get_intermediate_image_sizes
        foreach ( $get_intermediate_image_sizes as $_size ) {
    
            //if ( in_array( $_size, array( 'thumbnail', 'medium', 'large' ) ) ) {}
            $w = get_option( $_size . '_size_w' );
            $h = get_option( $_size . '_size_h' );
    
            if ( $w && $h );
            else {
                $w = $_wp_additional_image_sizes[ $_size ]['width'];
                $h = $_wp_additional_image_sizes[ $_size ]['height'];
            }
    
            $sizes[ $_size ] = array(
                'width' => $w,
                'height' => $h,
            );
        }

        // basic cache sistem
        set_transient( $cache_name, $sizes, 1200 ); // cache expires in 20 minutes
    }

    // display
    $_sizes = array();
    foreach ($sizes as $key => $size) {
        $_sizes["$key"] = $size['width'] && $size['height'] ?
            sprintf( '%s ( %d x %d )', $key, $size['width'], $size['height'] ) : sprintf( '%s', $key );
    }

    return $_sizes;
}

function __pspTC_connection_status() {
    global $psp;
    
    $html = array();
    
    // get the module init file
    require_once( $psp->cfg['paths']['plugin_dir_path'] . 'modules/tiny_compress/init.php' );
    // Initialize the pspTinyCompress class
    $pspTinyCompress = new pspTinyCompress();
    
    $connection_status = $pspTinyCompress->get_connection_status();
    $compress_limits = $pspTinyCompress->get_compress_limits();

    ob_start();
    ?>
        <div class="psp-form-row">
            <div class="psp-message psp-<?php echo $connection_status['status'] == 'valid' ? 'success' : 'error'; ?>">
                <p><?php echo __('Connection status: ', 'psp') . $connection_status['msg']; ?></p>
            </div>
        </div>
        
        <div class="psp-form-row">
            <div class="psp-message psp-<?php echo $compress_limits['status'] == 'valid' ? 'success' : 'error'; ?>">
                <p><?php echo __('Monthly limit: ', 'psp') . $compress_limits['msg']; ?></p>
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
			'tiny_compress' => array(
				'title' 	=> __('Tiny Compress', 'psp'),
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
						
						 
                        'html'      => '<div class="psp-box-update">' . __('
                            <h2>What is Tiny Compress? <br/></h2>     <p class="psp-update-text">Well, Tiny compress is a module that will help you compress images from your website.<br/> That way you can optimize big images for faster loading time on your website!</p><p class="psp-update-button">
                                <a href="https://codecanyon.net/item/premium-seo-pack-wordpress-plugin/6109437?ref=AA-Team" class="psp-form-button psp-form-button-success" target="_blank">Click here to Purchase Full Version</a>
                            </p>', 'psp') . '</div>',
                    ),
					
				)
			)
			
		)
	)
);