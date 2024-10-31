<?php
/**
 * module return as json_encode
 * http://www.aa-team.com
 * =======================
 *
 * @author		Andrei Dinca, AA-Team
 * @version		1.0
 */

$__google_locations = array(
	'com' => 'Default - Google.com',
	'as' => 'American Samoa',
	'off.ai' => 'Anguilla',
	'com.ag' => 'Antigua and Barbuda',
	'com.ar' => 'Argentina',
	'com.au' => 'Australia',
	'at' => 'Austria',
	'az' => 'Azerbaijan',
	'be' => 'Belgium',
	'com.br' => 'Brazil',
	'vg' => 'British Virgin Islands',
	'bi' => 'Burundi',
	'ca' => 'Canada',
	'td' => 'Chad',
	'cl' => 'Chile',
	'com.co' => 'Colombia',
	'co.cr' => 'Costa Rica',
	'ci' => 'Cote d\'Ivoire',
	'com.cu' => 'Cuba',
	'cz' => 'Czech Rep.',
	'cd' => 'Dem. Rep. of the Congo',
	'dk' => 'Denmark',
	'dj' => 'Djibouti',
	'com.do' => 'Dominican Republic',
	'com.ec' => 'Ecuador',
	'com.sv' => 'El Salvador',
	'fm' => 'Federated States of Micronesia',
	'com.fj' => 'Fiji',
	'fi' => 'Finland',
	'fr' => 'France',
	'gm' => 'The Gambia',
	'ge' => 'Georgia',
	'de' => 'Germany',
	'com.gi' => 'Gibraltar',
	'com.gr' => 'Greece',
	'gl' => 'Greenland',
	'gg' => 'Guernsey',
	'hn' => 'Honduras',
	'com.hk' => 'Hong Kong',
	'co.hu' => 'Hungary',
	'co.in' => 'India',
	'google.co.id' => 'Indonezia',
	'ie' => 'Ireland',
	'co.im' => 'Isle of Man',
	'co.il' => 'Israel',
	'it' => 'Italy',
	'com.jm' => 'Jamaica',
	'co.jp' => 'Japan',
	'co.je' => 'Jersey',
	'kz' => 'Kazakhstan',
	'co.kr' => 'Korea',
	'lv' => 'Latvia',
	'co.ls' => 'Lesotho',
	'li' => 'Liechtenstein',
	'lt' => 'Lithuania',
	'lu' => 'Luxembourg',
	'mw' => 'Malawi',
	'com.my' => 'Malaysia',
	'com.mt' => 'Malta',
	'mu' => 'Mauritius',
	'com.mx' => 'Mexico',
	'ms' => 'Montserrat',
	'com.na' => 'Namibia',
	'com.np' => 'Nepal',
	'nl' => 'Netherlands',
	'co.nz' => 'New Zealand',
	'com.ni' => 'Nicaragua',
	'com.nf' => 'Norfolk Island',
	'no' => 'Norway',
	'com.pk' => 'Pakistan',
	'com.pa' => 'Panama',
	'com.py' => 'Paraguay',
	'com.pe' => 'Peru',
	'com.ph' => 'Philippines',
	'pn' => 'Pitcairn Islands',
	'pl' => 'Poland',
	'pt' => 'Portugal',
	'com.pr' => 'Puerto Rico',
	'cg' => 'Rep. of the Congo',
	'ro' => 'Romania',
	'ru' => 'Russia',
	'rw' => 'Rwanda',
	'sh' => 'Saint Helena',
	'sm' => 'San Marino',
	'com.sa' => 'Saudi Arabia',
	'rs' => 'Serbia',
	'com.sg' => 'Singapore',
	'sk' => 'Slovakia',
	'co.za' => 'South Africa',
	'es' => 'Spain',
	'se' => 'Sweden',
	'ch' => 'Switzerland',
	'com.tw' => 'Taiwan',
	'co.th' => 'Thailand',
	'tt' => 'Trinidad and Tobago',
	'com.tr' => 'Turkey',
	'com.ua' => 'Ukraine',
	'ae' => 'United Arab Emirates',
	'co.uk' => 'United Kingdom',
	'com.uy' => 'Uruguay',
	'uz' => 'Uzbekistan',
	'vu' => 'Vanuatu',
	'co.ve' => 'Venezuela',
);
global $psp;
echo json_encode(
	array(
		$tryed_module['db_alias'] => array(
			/* define the form_messages box */
			'serp' => array(
				'title' 	=> 'SERP',
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
						
						'html' 		=> '<div class="psp-box-update">' . __('
							<h2>What is SERP? <br/></h2> <p class="psp-update-text">A search engine results page (SERP) is the page displayed by a search engine in response to a query by a searcher. The main component of the SERP is the listing of results that are returned by the search engine in response to a keyword query. <br/> Using this module you can keep track of your focus keywords rankings on google easily!</p><p class="psp-update-button">
								<a href="https://codecanyon.net/item/premium-seo-pack-wordpress-plugin/6109437?ref=AA-Team" class="psp-form-button psp-form-button-success" target="_blank">Click here to Purchase Full Version</a>
							</p>', 'psp') . '</div>',
					)

				)
			)
		)
	)
);