<?php
/**
 * module return as json_encode
 * http://www.aa-team.com
 * =======================
 *
 * @author		Andrei Dinca, AA-Team
 * @version		1.0
 */
global $psp;
echo json_encode(
	array(
		$tryed_module['db_alias'] => array(
			/* define the form_messages box */
			'pagespeed' => array(
				'title' 	=> 'Google Page Speed Insights',
				'icon' 		=> '{plugin_folder_uri}assets/16_pagespeed.png',
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
						
						
						'html' 		=> '<div class="psp-box-update">' . __('
							<h2>With PageSpeed Insights you can identify ways to make your site faster and more mobile-friendly. <br/></h2> 	<p class="psp-update-text">PageSpeed Insights checks to see if a page has applied common performance best practices and provides a score, which ranges from 0 to 100 points, and falls into one of the following three categories: Good, Needs Work and Poor. <br/> Based on these results, you will know what to do to get a bigger score!</p><p class="psp-update-button">
								<a href="https://codecanyon.net/item/premium-seo-pack-wordpress-plugin/6109437?ref=AA-Team" class="psp-form-button psp-form-button-success" target="_blank">Click here to Purchase Full Version</a>
							</p>', 'psp') . '</div>',
					),
				
				)
			)
		)
	)
);