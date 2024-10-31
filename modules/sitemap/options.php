<?php
/**
 * Dummy module return as json_encode
 * http://www.aa-team.com
 * =======================
 *
 * @author		Andrei Dinca, AA-Team
 * @version		1.0
 */

$__psp_video_include = array(
	'localhost'			=> 'Self Hosted'
	,'youtube'			=> 'Youtube.com'
	,'dailymotion'		=> 'Dailymotion.com'
	,'vimeo'			=> 'Vimeo.com'
	//,'metacafe'			=> 'Metacafe.com' // 2017-march verification: api doesn't work anymore
	,'veoh'				=> 'Veoh.com'
	//,'screenr'			=> 'Screenr.com' // 2017-march verification: Screenr was retired on November 12, 2015 http://www.screenr.com/
	,'wistia'			=> 'Wistia.com'
	,'vzaar'			=> 'Vzaar.com'
	,'viddler'			=> 'Viddler.com'	
	//,'blip'				=> 'Blip.tv' // 2017-march verification: Maker Studios To Officially Shut Down Blip.tv In August 2015. Maker Studios is closing down one of its subsidiary properties. In an email to site users, the YouTube multi-channel network announced it will shutter Blip.tv on August 20, 2015
	,'dotsub'			=> 'Dotsub.com'
	,'flickr'			=> 'Flickr.com'
);

function psp_postTypes_priority( $istab = '', $is_subtab='' ) {
	global $psp;

	ob_start();

	$options = $psp->get_theoption('psp_sitemap');

	$standard_content = psp_standardContent_get();
	$custom_posttypes = psp_postTypes_get(false);
 
	$post_types = (array) $standard_content; //array_intersect( array('post', 'page'), $standard_content );		
	$post_types = array_merge( $post_types, array('taxonomy' => __('Custom Taxonomies', 'psp')), $custom_posttypes );
?>
<div class="psp-form-row<?php echo ($istab!='' ? ' '.$istab : ''); ?><?php echo ($is_subtab!='' ? ' '.$is_subtab : ''); ?>">
	<label><?php _e('Priorities', 'psp'); ?>:</label>
	<div class="psp-form-item large">
	<span class="formNote">&nbsp;</span>
	<?php
	foreach ($post_types as $key => $value){
		$val = '';
		if( isset($options['priority']) && isset($options['priority'][$key]) ){
			$val = $options['priority'][$key];
		}
		$val = (string) $val;
		?>
		<label for="priority[<?php echo $key;?>]" style="display:inline;float:none;"><?php echo ucfirst(str_replace('_', ' ', $value));?>:</label>
		&nbsp;
		<select id="priority[<?php echo $key;?>]" name="priority[<?php echo $key;?>]" style="width:400px;">
			<?php
			foreach (range(0, 1, 0.1) as $kk => $vv){
				$vv = (string) $vv;
				echo '<option value="' . ( $vv ) . '" ' . ( $val == $vv ? 'selected="true"' : '' ) . '>' . ( $vv ) . '</option>';
			} 
			?>
		</select>&nbsp;&nbsp;&nbsp;&nbsp;
		<?php
	} 
	?>
	</div>
	<p style="font-style: italic;"><?php _e('Because this value is relative to other pages on your site, assigning a high priority (or specifying the same priority for all URLs) will not help your site\'s search ranking. In addition, setting all pages to the same priority will have no effect.', 'psp'); ?></p>
</div>
<?php
	$output = ob_get_contents();
	ob_end_clean();
	return $output;
} 

function psp_postTypes_changefreq( $istab = '', $is_subtab='' ) {
	global $psp;

	ob_start();

	$options = $psp->get_theoption('psp_sitemap');

	$standard_content = psp_standardContent_get();
	$custom_posttypes = psp_postTypes_get(false);
 
	$post_types = (array) $standard_content; //array_intersect( array('post', 'page'), $standard_content );		
	$post_types = array_merge( $post_types, array('custom_taxonomies' => __('Custom Taxonomies', 'psp')), $custom_posttypes );
?>
<div class="psp-form-row<?php echo ($istab!='' ? ' '.$istab : ''); ?><?php echo ($is_subtab!='' ? ' '.$is_subtab : ''); ?>">
	<label><?php _e('Frequencies', 'psp'); ?>:</label>
	<div class="psp-form-item large">
	<span class="formNote">&nbsp;</span>
	<?php
	foreach ($post_types as $key => $value){
		
		$val = '';
		if( isset($options['changefreq']) && isset($options['changefreq'][$key]) ){
			$val = $options['changefreq'][$key];
		}
		?>
		<label for="changefreq[<?php echo $key;?>]" style="display:inline;float:none;"><?php echo ucfirst(str_replace('_', ' ', $value));?>:</label>
		&nbsp;
		<select id="changefreq[<?php echo $key;?>]" name="changefreq[<?php echo $key;?>]" style="width:400px;">
			<?php
			foreach (array('always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never') as $kk => $vv){
				echo '<option value="' . ( $vv ) . '" ' . ( $val == $vv ? 'selected="true"' : '' ) . '>' . ( $vv ) . '</option>';
			} 
			?>
		</select>&nbsp;&nbsp;&nbsp;&nbsp;
		<?php
	} 
	?>
	</div>
	<p style="font-style: italic;"><?php _e('Provides a hint about how frequently the page is likely to change.', 'psp'); ?></p>
</div>
<?php
	$output = ob_get_contents();
	ob_end_clean();
	return $output;
}

function __pspNotifyEngine( $engine='google', $action='default', $istab = '', $is_subtab='' ) {
	global $psp;
	
	$req['action'] = $action;
	
	if ( $req['action'] == 'getStatus' ) {
		$notifyStatus = $psp->get_theoption('psp_sitemap_engine_notify');
		if ( $notifyStatus === false || !isset($notifyStatus["$engine"]) || !isset($notifyStatus["$engine"]["sitemap"]) )
			return '';
		return $notifyStatus["$engine"]["sitemap"]["msg_html"];
	}

	$html = array();
	
	$html[] = '<div class="psp-form-row psp-notify-engine-ping psp-notify-' . $engine . ' ' . ($istab!='' ? ' '.$istab : '') . ($is_subtab!='' ? ' '.$is_subtab : '') . '">';

	if ( $engine == 'google' ) {
		$html[] = '<div style="padding-bottom: 8px;">' . sprintf( __('Notify Google: you can check statistics on <a href="%s" target="_blank">Google Webmaster Tools</a>', 'psp'), 'http://www.google.com/webmasters/tools/' ). '</div>';
	} else if ( $engine == 'bing' ) {
		$html[] = '<div style="padding-bottom: 8px;">' . sprintf( __('Notify Bing: you can check statistics on <a href="%s" target="_blank">Bing Webmaster Tools</a>', 'psp'), 'http://www.bing.com/toolbox/webmaster' ). '</div>';
	}
	
	ob_start();
?>
		<label for="sitemap_type<?php echo '_'.$engine; ?>" style="display:inline;float:none;"><?php echo __('Select Sitemap', 'psp');?>:</label>
		&nbsp;
		<select id="sitemap_type<?php echo '_'.$engine; ?>" name="sitemap_type" style="width:160px;">
			<?php
			foreach (array('sitemap' => 'Sitemap.xml', 'sitemap_images' => 'Sitemap-Images.xml', 'sitemap_videos' => 'Sitemap-Videos.xml') as $kk => $vv){
				echo '<option value="' . ( $kk ) . '" ' . ( 0 ? 'selected="true"' : '' ) . '>' . ( $vv ) . '</option>';
			} 
			?>
		</select>&nbsp;&nbsp;
<?php
	$selectSitemap = ob_get_contents();
	ob_end_clean();
	$html[] = $selectSitemap;
	
	$html[] = '<input type="button" class="psp-form-button psp-form-button-info psp-button blue" style="width: 160px;" id="psp-notify-' . $engine . '" value="' . ( __('Notify '.ucfirst($engine), 'psp') ) . '">
	<span style="margin:0px 0px 0px 10px" class="response">' . __pspNotifyEngine( $engine, 'getStatus' ) . '</span>';

	$html[] = '</div>';

	// view page button
	ob_start();
?>
	<script>
	(function($) {
		var ajaxurl = '<?php echo admin_url('admin-ajax.php');?>',
		engine = '<?php echo $engine; ?>';

		$("body").on("click", "#psp-notify-"+engine, function(){

			$.post(ajaxurl, {
				'action' 		: 'pspAdminAjax',
				'sub_action'	: 'notify',
				'engine'		: engine,
				'sitemap_type'	: $('#sitemap_type_'+engine).val()
			}, function(response) {

				var $box = $('.psp-notify-'+engine), $res = $box.find('.response');
				$res.html( response.msg_html );
				if ( response.status == 'valid' )
					return true;
				return false;
			}, 'json');
		});
		
		$('#sitemap_type_'+engine).on('change', function (e) {
			e.preventDefault();

			$.post(ajaxurl, {
				'action' 		: 'pspAdminAjax',
				'sub_action'	: 'getStatus',
				'engine'		: engine,
				'sitemap_type'	: $('#sitemap_type_'+engine).val()
			}, function(response) {

				var $box = $('.psp-notify-'+engine), $res = $box.find('.response');
				$res.html( response.msg_html );
				if ( response.status == 'valid' )
					return true;
				return false;
			}, 'json');
		});
   	})(jQuery);
	</script>
<?php
	$__js = ob_get_contents();
	ob_end_clean();
	$html[] = $__js;

	return implode( "\n", $html );
}

function psp_standardContent_get() {
	global $psp;

	$post_types = array(
		'site'		    => __('Home', 'psp'),
		//'misc'          => __('Miscellaneous', 'psp'),
		'post'			=> __('Posts', 'psp'),
		'page'			=> __('Pages (static)', 'psp'),
		'category'		=> __('Categories', 'psp'),
		'post_tag'		=> __('Tag pages', 'psp'),
		'archive'		=> __('Archives', 'psp'),
		'author'		=> __('Author pages', 'psp'),
	);

	//unset($post_types['attachment'], $post_types['revision']);
	return $post_types;
}

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

function psp_taxonomies_get( $builtin=true ) {
	global $psp;

    $pms = array(
        'public'   => true,
    );
    if ( $builtin === true || $builtin === false  ) {
        $pms = array_merge($pms, array(
            '_builtin' => $builtin, // exclude post_tag, category
        ));
    }
	$post_types = get_taxonomies($pms, 'objects');
	unset($post_types['post_format'], $post_types['nav_menu'], $post_types['link_category']);
	
	$ret = array();
	foreach ( $post_types as $key => $post_type ) {
		$value = $post_type->label;
		$ret["$key"] = $value;
	}
	return $ret;
}

function psp_categories_get() {
	global $psp;

	$args = array(
		'orderby' => 'name',
		'parent' => 0
	);
	$categories = get_categories( $args );
	if ( empty($categories) || !is_array($categories)) return array();
			
	$ret = array();
	foreach ( $categories as $category ) {
		$key = $category->term_id;
		$value = $category->name;
		$ret["$key"] = $value;
	}
	return $ret;
}

function __pspCheckVideoMetas( $action='default', $istab = '', $is_subtab='' ) {
	global $psp;

	$req = array();
	$req['action'] = $action;

	if ( $req['action'] == 'getStatus' ) {
		$notifyStatus = $psp->get_theoption('psp_video_metas');
		if ( $notifyStatus === false || !isset($notifyStatus["clean"]) )
			return '';

		return $notifyStatus["clean"]['msg_html'];
	}

	$html = array();

	$html[] = '<div class="panel-body psp-panel-body psp-form-row psp-clean-video-metas ' . ($istab!='' ? ' '.$istab : '') . ($is_subtab!='' ? ' '.$is_subtab : '') . '" style="">';

	$html[] = '	<div class="psp-form-item large" style="margin-left: 0px; margin-bottom: 7px;">';
	$html[] = '		<label for="site-items" style="margin-bottom: 0px; width: 18rem;">' . __('Video metas recurrency', 'psp') . ':</label>';

	if (1) {
		//$html[] = '<div style="padding-bottom: 8px;">' . '' . '</div>';
	}
	
	$current_recurrency = $psp->get_theoption('psp_sitemap');
	$current_recurrency = isset($current_recurrency['video_recurrence']) ? (string) $current_recurrency['video_recurrence'] : 24;

	ob_start();
?>
		<select id="video_recurrence" name="video_recurrence" style="width:160px;">
			<?php
            $sync_recurrence = array(
                24      => __('Every single day', $psp->localizationName),
                48      => __('Every 2 days', $psp->localizationName),
                72      => __('Every 3 days', $psp->localizationName),
                96      => __('Every 4 days', $psp->localizationName),
                120     => __('Every 5 days', $psp->localizationName),
                144     => __('Every 6 days', $psp->localizationName),
                168     => __('Every 1 week', $psp->localizationName),
                336     => __('Every 2 weeks', $psp->localizationName),
                504     => __('Every 3 weeks', $psp->localizationName),
                720     => __('Every 1 month', $psp->localizationName), // ~ 4 weeks + 2 days
            );
			foreach ($sync_recurrence as $kk => $vv){
				echo '<option value="' . ( $kk ) . '" ' . ( $kk == $current_recurrency ? 'selected="true"' : '' ) . '>' . ( $vv ) . '</option>';
			} 
			?>
		</select>&nbsp;&nbsp;
<?php
	$selectSitemap = ob_get_contents();
	ob_end_clean();
	$html[] = $selectSitemap;
	
	$html[] = '<input type="button" class="psp-form-button psp-form-button-info psp-button blue" style="width: 280px;" id="psp-clean-video-metas" value="' . ( __('Delete video metas for all posts NOW', 'psp') ) . '">
	<span style="margin:0px 0px 0px 10px" class="response">' . __pspCheckVideoMetas( 'getStatus' ) . '</span>';
	
	$html[] = '<div style="margin-left: 18rem;"><span class="psp-form-note">' . sprintf( __('With recurrency you can set the "timeout" for our cached video meta data (info about the videos we found in each post content).<br/> You can also use the "Delete video metas for all posts NOW" button, to delete all video meta data we\'ve cached till now, so they will be rebuild.<br/> Then, if you don\'t have too many videos (let\'s say - around maximum 1000 videos in all your posts), you can can access the link %s, because it will search through all your posts, find the videos and generate the coresponding videos meta data.<br/> The video meta data are also generated (if it isn\'t done yet) when you access a post details page on website frontend.', 'psp'), '<a id="site-items" target="_blank" href="' . ( home_url('/sitemap-videos.xml') ) . '" style="position: relative;">' . ( home_url('/sitemap-videos.xml') ) . '</a>' ) . '</span></div>';

	$html[] = '	</div>';
	$html[] = '</div>';

	// view page button
	ob_start();
?>
	<script>
	(function($) {
		var ajaxurl = '<?php echo admin_url('admin-ajax.php');?>';

		$("body").on("click", "#psp-clean-video-metas", function() {

			$.post(ajaxurl, {
				'action' 		: 'pspVideoMetas',
				'sub_action'	: 'clean',
			}, function(response) {

				var $box = $('.psp-clean-video-metas'), $res = $box.find('.response');
				$res.html( response.msg_html );
				if ( response.status == 'valid' )
					return true;
				return false;
			}, 'json');
		});
   	})(jQuery);
	</script>
<?php
	$__js = ob_get_contents();
	ob_end_clean();
	$html[] = $__js;

	return implode( "\n", $html );
}


global $psp;
echo json_encode(
	array(
		$tryed_module['db_alias'] => array(
			/* define the form_messages box */
			'sitemap' => array(
				'title' 	=> __('Sitemap settings', 'psp'),
				'icon' 		=> '{plugin_folder_uri}assets/menu_icon.png',
				'size' 		=> 'grid_4', // grid_1|grid_2|grid_3|grid_4
				'header' 	=> true, // true|false
				'toggler' 	=> false, // true|false
				'buttons' 	=> false, // true|false
				'style' 	=> 'panel', // panel|panel-widget
				

                
				// create the box elements array
				'elements'	=> array(
				
                    /*'_header_exclude' => array(
                        'type'      => 'html',
                        'html'      => __(
                            '<div class="psp-form-row psp-ad-section-header">
                                <div>Excluding - only for posts | pages | custom post types sitemaps</div>
                            </div>', 'psp')
                    ),*/
                    
					// General
					'help_general' => array(
						'type' 		=> 'html',
						'status' 	=> 'info',
						'html' 		=> '<div class="psp-box-update">' . __('
							<h2>Sitemap Module - WHY XML SITEMAPS ARE IMPORTANT FOR SEO <br/></h2> 	<p class="psp-update-text"> The Sitemaps Module gives you the ability to automatically create Sitemaps. </p><p class="psp-update-button">
								<a href="https://codecanyon.net/item/premium-seo-pack-wordpress-plugin/6109437?ref=AA-Team" class="psp-form-button psp-form-button-success" style="color:#fff" target="_blank">Click here to Purchase Full Version</a>
							</p>', 'psp') . '</div>',
					),

				)
			)
		)
	)
);