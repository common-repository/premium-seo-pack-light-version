<?php
/*
* Define class pspGoogleAnalytics
* Make sure you skip down to the end of this file, as there are a few
* lines of code that are very important.
*/
!defined('ABSPATH') and exit;
if (class_exists('pspGoogleAnalytics') != true) {
    class pspGoogleAnalytics
    {
        /*
        * Some required plugin information
        */
        const VERSION = '1.0';

        /*
        * Store some helpers config
        */
		public $the_plugin = null;

		private $module_folder = '';
		private $module = '';
		
		public $ga = null;
		public $ga_params = array();

		static protected $_instance;

        /*
        * Required __construct() function that initalizes the AA-Team Framework
        */
        public function __construct()
        {
        	global $psp;

        	$this->the_plugin = $psp;
			$this->module_folder = $this->the_plugin->cfg['paths']['plugin_dir_url'] . 'modules/Google_Analytics/';
			$this->module = $this->the_plugin->cfg['modules']['Google_Analytics'];
			
			if ( !$this->the_plugin->verify_module_status( 'Google_Analytics' ) ) ; //module is inactive
			else {
				// google frontend tags & analytics tracking script!
				add_action( 'premiumseo_head', array( &$this, 'google_frontend' ), 30 );
			}
			
			if ( $this->the_plugin->is_admin === true ) {
				if ( $this->the_plugin->capabilities_user_has_module('Google_Analytics') ) {

					// load Analytics API Wrapper
					require_once( $this->the_plugin->cfg['paths']['scripts_dir_path'] . '/google-analytics/GoogleAnalyticsAPI.class.php' );
					$this->ga = new GoogleAnalyticsAPI();
					$this->ga_params = $this->the_plugin->get_theoption( $this->the_plugin->alias . '_oAuthCode' );

					add_action('admin_menu', array( &$this, 'adminMenu' ));
				
					// ajax handler
					add_action('wp_ajax_pspGoogleAuthorizeApp', array( &$this, 'googleAuthorizeApp' ));
					add_action('wp_ajax_pspGoogleAPIRequest', array( &$this, 'ajax_request' ));
				
					add_action('psp_google_analytics_get_profiles', array( &$this, 'get_profiles' ));
				}
			}

			add_action('init', array( &$this, 'check_auth_callback' ));
        }
        
        public function google_frontend() {
			$settings = $this->the_plugin->get_theoption( $this->the_plugin->alias . '_google_analytics' );
			
			// analytics tracking script
			$anonymize_ip = isset($settings['google_anonymize_ip']) && $settings['google_anonymize_ip'] == 'yes' ? true : false;
			$trackingScript = $this->tracking_script( $settings['google_analytics_id'], $anonymize_ip );
			if ( !empty($trackingScript) )
				echo $trackingScript;

			// engine verification tags!
			$meta_tags = array(
				'google'	=> array(
					'name'		=> 'google-site-verification',
					'content'	=> isset($settings['google_verify']) && !empty($settings['google_verify']) ? $settings['google_verify'] : ''
				)
			);
			foreach ( $meta_tags as $engine => $tags ) {
				if ( !empty($tags['content']) )
					echo '<meta name="'.$tags['name'].'" content="'. $tags['content'] .'" />' . PHP_EOL;
			}
        }
        
        private function tracking_script( $account_id='', $anonymize_ip=false ) {
        	if ( empty($account_id) ) return '';

        	ob_start();
?>
<script type="text/javascript">
var _gaq = _gaq || [];
_gaq.push(['_setAccount', '<?php echo $account_id ?>']);
<?php
if ($anonymize_ip) {
	echo "_gaq.push (['_gat._anonymizeIp']);".PHP_EOL;
}
?>
_gaq.push(['_trackPageview']);
(function() {
	var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
	ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
	var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
})();
</script>
<?php
			$ret = ob_get_contents();
			ob_end_clean();
			return $ret;
        }

		/**
	    * Singleton pattern
	    *
	    * @return pspGoogleAnalytics Singleton instance
	    */
	    static public function getInstance()
	    {
	        if (!self::$_instance) {
	            self::$_instance = new self;
	        }

	        return self::$_instance;
	    }

		/**
	    * Hooks
	    */
	    static public function adminMenu()
	    {
	       self::getInstance()
	    		->_registerAdminPages();
	    }

	    /**
	    * Register plug-in module admin pages and menus
	    */
		protected function _registerAdminPages()
    	{
    		if ( $this->the_plugin->capabilities_user_has_module('Google_Analytics') ) {
	    		add_submenu_page(
	    			$this->the_plugin->alias,
	    			$this->the_plugin->alias . " " . __('Google Analytics', 'psp'),
		            __('Google Analytics', 'psp'),
		            'read',
		            $this->the_plugin->alias . "_Google_Analytics",
		            array($this, 'display_index_page')
		        );
    		}

			return $this;
		}
		
		public function display_meta_box()
		{
			if ( $this->the_plugin->capabilities_user_has_module('Google_Analytics') ) {
				$this->printBoxInterface();
			}
		}

		public function display_index_page()
		{
			$this->printBaseInterface();
		}
		
		public function moduleValidation() {
			$ret = array(
				'status'			=> false,
				'html'				=> ''
			);
			
			// find if user makes the setup
			$module_settings = $analytics_settings = $this->the_plugin->get_theoption( $this->the_plugin->alias . "_google_analytics" );

			$analytics_mandatoryFields = array(
				'client_id'			=> false,
				'client_secret'		=> false,
				'redirect_uri'		=> false
			);
			if ( isset($analytics_settings['client_id']) && !empty($analytics_settings['client_id']) ) {
				$analytics_mandatoryFields['client_id'] = true;
			}
			if ( isset($analytics_settings['client_secret']) && !empty($analytics_settings['client_secret']) ) {
				$analytics_mandatoryFields['client_secret'] = true;
			}
			if ( isset($analytics_settings['redirect_uri']) && !empty($analytics_settings['redirect_uri']) ) {
				$analytics_mandatoryFields['redirect_uri'] = true;
			}
			$mandatoryValid = true;
			foreach ($analytics_mandatoryFields as $k=>$v) {
				if ( !$v ) {
					$mandatoryValid = false;
					break;
				}
			}
			if ( !$mandatoryValid ) {
				$error_number = 1; // from config.php / errors key
				
				$ret['html'] = $this->the_plugin->print_module_error( $this->module, $error_number, 'Error: Unable to use Google Analytics module, yet!' );
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
				pspAdminMenu::getInstance()->make_active('monitoring|Google_Analytics')->show_menu();
				?>
				

				<!-- Content -->
				<section class="<?php echo $this->the_plugin->alias; ?>-main">
					
					<?php 
					echo psp()->print_section_header(
						$this->module['Google_Analytics']['menu']['title'],
						$this->module['Google_Analytics']['description'],
						$this->module['Google_Analytics']['help']['url']
					);
					?>
					
					<div id="<?php echo $this->the_plugin->alias; ?>-gAnalytics-wrapper" class="panel panel-default <?php echo $this->the_plugin->alias; ?>-panel">

						<div class="psp-box-update">
							<h2>It's important in a business that you get the analityics data at the right time. <br/></h2>


							<p class="psp-update-text">Using the Google Analytics Module, you will get real time statistics, so you can see how your website is performing right now. <br/> By upgrading to Premium SEO Pack full version, you can see real time statistics, straight from your google analitycs account.</p>

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

		public function ajax_request()
		{
			$ret = array();
			$request = array(
				'sub_action' => isset($_REQUEST['sub_action']) ? explode(",", $_REQUEST['sub_action']) : '',
				'return'	=> isset($_REQUEST['return']) ? $_REQUEST['return'] : 'die' 
			);
			
			// find if user makes the setup
			$moduleValidateStat = $this->moduleValidation();
			if ( !$moduleValidateStat['status'] ) {
	        	$ret['__access']['status'] 	= 'invalid';
				$ret['__access']['isalert'] = 'no';
	        	$ret['__access']['msg'] = __('You configured Google Analytics Service incorrectly!', 'psp');
				
				// stop propagation of this script and output json_encoded data
				if ( $request['return'] == 'array' ) return $ret;
				die(json_encode($ret));
			}
							
			// provide the accountId and client secret
			$analytics_settings = $this->the_plugin->get_theoption( $this->the_plugin->alias . '_google_analytics' );
			$this->ga->setAccountId( $analytics_settings['profile_id']);
 
			$isauth = $this->makeoAuthLogin();
			if (!$isauth) { //invalid auth
	        		$ret['__access']['status'] 	= 'invalid';
					$ret['__access']['isalert'] = 'yes';
	        		$ret['__access']['msg'] = __('Please Authorize the App first!', 'psp');
				
				// stop propagation of this script and output json_encoded data
				if ( $request['return'] == 'array' ) return $ret;
				die(json_encode($ret));
			}

	        $ret['__access']['status'] 	= 'valid';
 
	        //getAudience
			if( in_array('getAudience', $request['sub_action']) ){
				$params = array(
					'metrics' => 'ga:newVisits,ga:visits,ga:avgTimeOnPage,ga:visitBounceRate,ga:pageviewsPerVisit,ga:pageviews,ga:uniquePageviews',
					'dimensions' => 'ga:date',
					'start-date' 	=> isset($_REQUEST['from_date']) ? $_REQUEST['from_date'] : '',
					'end-date' 		=> isset($_REQUEST['to_date']) ? $_REQUEST['to_date'] : '',
				);
				$data = $this->ga->query($params);
	        	
	        	if (!isset($data['totalsForAllResults'])) {
		        	$ret['getAudience']['status'] = 'invalid';
					$ret['__access']['isalert'] = 'yes';
					$ret['getAudience']['reason'] = __('No records found for interval : ', 'psp') . $params['start-date'] . ' - ' . $params['start-date'];
	        	} else {
				
    				// refformating data
    				$returnData = array();
    				if( count($data['rows']) > 0 ){
    					foreach ($data['rows'] as $key => $value){
    						// fix for jquery plot date
    						//$data['rows'][] = array( (strtotime($value[0]) * 1000), $value[1] );
    						$time = (strtotime($value[0]) * 1000);
    						
    						$returnData['newVisits'][] = array( $time, $value[1] );
    						$returnData['visits'][] = array( $time, $value[2] );
    						$returnData['avgTimeOnPage'][] = array( $time, $value[3] );
    						$returnData['visitBounceRate'][] = array( $time, $value[4] );
    						$returnData['pageviewsPerVisit'][] = array( $time, $value[5] );
    						$returnData['pageviews'][] = array( $time, $value[6] );
    						$returnData['uniquePageviews'][] = array( $time, $value[7] );
    					}
    				}
    
    	        	$ret['getAudience']['status'] 	= 'valid';
    				$ret['getAudience']['data'] 	= array(
    					'profileInfo' 			=> $data['profileInfo'],
    					'totalsForAllResults' 	=> $data['totalsForAllResults'],
    					'rows'					=> $returnData
    				);
				}
			}
			
			//getAudienceDemographics
			if( in_array('getAudienceDemographics', $request['sub_action']) ){
				$params = array(
					'metrics' => 'ga:visits',
					'dimensions' => '--default--',
					'sort' => '-ga:visits',
					'start-date' 	=> isset($_REQUEST['from_date']) ? $_REQUEST['from_date'] : '',
					'end-date' 		=> isset($_REQUEST['to_date']) ? $_REQUEST['to_date'] : '',
					'max-results' => 20
				);
				$ret_html = $ret_html2 = $ret_html3 = array();
				
				//demographics
				$params['dimensions'] = 'ga:country';
				$ret_html['country'] = $this->renderStatisticsTable( $this->ga->query($params), 'country' );
				
				$params['dimensions'] = 'ga:city';
				$ret_html['city'] = $this->renderStatisticsTable( $this->ga->query($params), 'city' );
				
				$params['dimensions'] = 'ga:language';
				$ret_html['language'] = $this->renderStatisticsTable( $this->ga->query($params), 'language' );
				
				//system
				$params['dimensions'] = 'ga:browser';
				$ret_html2['browser'] = $this->renderStatisticsTable( $this->ga->query($params), 'browser' );
				
				$params['dimensions'] = 'ga:operatingSystem';
				$ret_html2['operatingSystem'] = $this->renderStatisticsTable( $this->ga->query($params), 'operatingSystem' );
				
				$params['dimensions'] = 'ga:networkLocation';
				$ret_html2['networkDomain'] = $this->renderStatisticsTable( $this->ga->query($params), 'networkDomain' );
				
				//mobile
				$params['dimensions'] = 'ga:operatingSystem';
				$params['segment'] = 'gaid::-11';
				$ret_html3['mob_operatingSystem'] = $this->renderStatisticsTable( $this->ga->query($params), 'mob_operatingSystem' );

				$params['dimensions'] = 'ga:screenResolution';
				$params['segment'] = 'gaid::-11';
				$ret_html3['mob_screenResolution'] = $this->renderStatisticsTable( $this->ga->query($params), 'mob_screenResolution' );

				$params['dimensions'] = 'ga:networkLocation';
				$params['segment'] = 'gaid::-11';
				$ret_html3['mob_networkDomain'] = $this->renderStatisticsTable( $this->ga->query($params), 'mob_networkDomain' );

				//result!
				$ret['getAudienceDemographics']['status'] 	= 'valid';
				$ret['getAudienceDemographics']['data'] 	= array(
					'html' => array(
						'demographics'	=> implode("\n", $ret_html),
						'system'	=> implode("\n", $ret_html2),
						'mobile'	=> implode("\n", $ret_html3)
					)
				);
			}
			
			// stop propagation of this script and output json_encoded data
			if ( $request['return'] == 'array' ) return $ret;
			die(json_encode($ret));
		}

		private function renderStatisticsTable( $sets=array(), $alias='' )
		{
			$html = array();
			$__titles = array(
				//demographics
				'country' => 'Country / Territory',
				'language' => 'Language',
				'city' => 'City',
				
				//system
				'browser' => 'Browser',
				'operatingSystem' => 'Operating System',
				'networkDomain' => 'Service Provider',
				
				//mobile
				'mob_operatingSystem' => 'Operating System',
				'mob_networkDomain' => 'Service Provider',
				'mob_screenResolution' => 'Screen Resolution'
			);
			$title = '';
			$title = __($__titles[$alias], 'psp');

			if( isset($sets["rows"]) && count($sets["rows"]) > 0 ){
				$i = 1;
				$total = array_values($sets['totalsForAllResults']);
				$total = $total[0];
				  
				$html[] = '<table class="psp-table" id="psp-statistics-table-' . ( $alias ) . '">';
				$html[] = 	'<thead>';
				$html[] = 		'<tr>';
				$html[] = 			'<th align="left"><strong>' . ( $title ) . '</strong></th>';
				$html[] = 			'<th>' . __('Visits', 'psp') . '</th>';
				$html[] = 			'<th>' . __('% Visits', 'psp') . '</th>';
				$html[] = 		'</tr>';
				$html[] = 	'</thead>';
				$html[] = 	'<tbody>';

				foreach ($sets["rows"] as $key => $value) {
					$percent = (100 / $total) * $value[1];
					$html[] = 		'<tr>';
					$html[] = 			'<td>' . ( $value[0] ) . '</td>';
					$html[] = 			'<td>' . ( $value[1] ) . '</td>';
					$html[] = 			'<td>';
					$html[] = 				'<div style="width:' . ( $percent ) . '%;"></div>' . ( number_format($percent, 2) ) . '%';
					$html[] = 			'</td>';
					$html[] = 		'</tr>';
				}
				
				$html[] = 	'</tbody>';
				$html[] = '</table>';
			} else { //no data avaialable!
				$html[] = '<table class="psp-table" id="psp-statistics-table-' . ( $alias ) . '">';
				$html[] = 	'<thead>';
				$html[] = 		'<tr>';
				$html[] = 			'<td colspan=4>' . __('There is no data for this view.', 'psp') . '</th>';
				
				$html[] = 		'</tr>';
				$html[] = 	'</thead>';
				$html[] = 	'<tbody>';
				$html[] = '</table>';
			}
			
			return implode("\n", $html);
		}

		/*
		* googleAuthorizeApp, method
		* --------------------------
		*
		* oauth step 1: redirecting a browser (popup, or full page if needed) to a Google URL
		* this will return a link to google auth and save data into wp_option
		*/
		public function googleAuthorizeApp()
		{
			$saveform = isset($_REQUEST['saveform']) ? trim($_REQUEST['saveform']) : 'no';
			
			if ( $saveform == 'yes' ) {

			$params = isset($_REQUEST['params']) ? $_REQUEST['params'] : '';
			parse_str( $params, $arr_params );

			// setup the wrapper
			$this->ga->auth->setClientId( $arr_params['client_id'] );
			$this->ga->auth->setClientSecret( $arr_params['client_secret'] );
			$this->ga->auth->setRedirectUri( $arr_params['redirect_uri'] );

			$saveID = $arr_params['box_id'];

			// clean up array before save into DB 
			unset($arr_params['box_id']);
			unset($arr_params['box_nonce']);

			$this->the_plugin->save_theoption( $saveID, $arr_params);
			} else {

			$arr_params = $this->the_plugin->get_theoption( $this->the_plugin->alias . '_google_analytics' );
  
			// setup the wrapper
			$this->ga->auth->setClientId( $arr_params['client_id'] );
			$this->ga->auth->setClientSecret( $arr_params['client_secret'] );
			$this->ga->auth->setRedirectUri( $arr_params['redirect_uri'] );
			}

			// Get the Auth-Url
			die(json_encode(array(
				'status' => 'valid',
				'auth_url' => $this->ga->auth->buildAuthUrl()
			)));
		}
		
		/*
		* refreshToken, method
		* --------------------------
		*
		* oauth step 3: if access token expired, retrieve a new one, using refresh token which is stored in db!
		*/
		private function verifyToken( $old_token_datas=array() )
		{
			$__is_at = (bool) (isset($old_token_datas['access_token']) && !empty($old_token_datas['access_token']));
			$__is_rt = (bool) (isset($old_token_datas['refresh_token']) && !empty($old_token_datas['refresh_token']));
			
			if (!$__is_at && !$__is_rt) // no access token & no revoke token
				return false;
			if (!$__is_at && $__is_rt) // no access token & has revoke token
				return $this->refreshToken( $old_token_datas );
			
			// Check if the accessToken is expired
			if ((time() - $old_token_datas['token_created']) >= $old_token_datas['expires_in']) {
				if (!$__is_rt) return false; // no revoke token
				return $this->refreshToken( $old_token_datas );
			}
			
			return $old_token_datas["access_token"];
		}
		private function refreshToken( $old_token_datas=array() )
		{
			$analytics_settings = $this->the_plugin->get_theoption( $this->the_plugin->alias . '_google_analytics' );
  
			$this->ga->auth->setClientId( $analytics_settings['client_id'] );
			$this->ga->auth->setClientSecret( $analytics_settings['client_secret'] );
			if ( !isset($analytics_settings['client_id'], $analytics_settings['client_secret'])
				|| empty($analytics_settings['client_id'])
				|| empty($analytics_settings['client_secret']) )
				return false;
		    $auth = $this->ga->auth->refreshAccessToken( $old_token_datas['refresh_token'] );
  
		    // Try to get the AccessToken
			if ((int) $auth['http_code'] == 200) {
				$this->the_plugin->save_theoption( $this->the_plugin->alias . '_oAuthCode', array(
					'access_token' 		=> $auth['access_token'],
					'refresh_token' 	=> $old_token_datas['refresh_token'], //remains unchanged!
					'expires_in' 		=> $auth['expires_in'],
					'token_created' 	=> time(),
					'token_type'		=> $auth['token_type']
				) );

				return $auth['access_token'];
			}
			return false;
		}

		public function makeoAuthLogin()
		{
			$old_token_datas = $this->the_plugin->get_theoption( $this->the_plugin->alias . '_oAuthCode' );
			$__rt = $this->verifyToken( $old_token_datas );
			if (!$__rt) return false;
			$this->ga->setAccessToken( $__rt );
			return true;
		}
	
		public function get_profiles()
		{
  			if( $this->makeoAuthLogin() ){
				$profiles = $this->ga->getProfiles();

				$analytics_settings = $this->the_plugin->get_theoption( $this->the_plugin->alias . '_google_analytics' );
				
				if((int) $profiles["http_code"] == 200 ){
					
					$last_status = array('profile_last_status' => array('status' => 'success', 'step' => 'get_profile', 'data' => date("Y-m-d H:i:s"), 'msg' => $profiles));
					$this->the_plugin->save_theoption( $this->the_plugin->alias . '_ganalytics_profile_last_status', $last_status );
					$this->the_plugin->save_theoption( $this->the_plugin->alias . '_google_analytics', array_merge( (array) $analytics_settings, $last_status ) );
				
					$accounts = array();
					if(count($profiles['items']) > 0) {
						foreach ($profiles['items'] as $item) {
						    $accounts['ga:' . $item['id']] = $item['websiteUrl'] . ' (' . $item['name'] . ')';
						}
					}
					return $accounts; 
				} else {
					
					$last_status = array('profile_last_status' => array('status' => 'error', 'step' => 'get_profile', 'data' => date("Y-m-d H:i:s"), 'msg' => $profiles));
					$this->the_plugin->save_theoption( $this->the_plugin->alias . '_ganalytics_profile_last_status', $last_status );
					$this->the_plugin->save_theoption( $this->the_plugin->alias . '_google_analytics', array_merge( (array) $analytics_settings, $last_status ) );
				}
			}
			
			return array(
				'0' => __('Please Authorize the App first!', 'psp')
			);
		}

		
		/*
		* check_auth_callback, method
		* oauth step 2: receiving the authorization code, the application can exchange the code for an access token and a refresh token
		* ---------------------------
		*/
		public function check_auth_callback()
		{
			// check in the server request uri if the keyword psp_seo_oauth exists!
			$request_uri = isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : '';
			if( preg_match("/psp_seo_oauth/i", $request_uri ) ){
   
                $code = isset($_GET['code']) ? $_GET['code'] : '';
                if ( empty($code) ) {
                    $found = preg_match('~^/psp_seo_oauth\?code\=([^&]+)&?(.*)$~', $request_uri, $m);
                    if ( $found !== false ) {}
                    $code = $m[1];
                }
  
				$analytics_settings = $this->the_plugin->get_theoption( $this->the_plugin->alias . '_google_analytics' );

				if( trim($code) != "" ){
					
					require_once( $this->the_plugin->cfg['paths']['scripts_dir_path'] . '/google-analytics/GoogleAnalyticsAPI.class.php' );
					$this->ga = new GoogleAnalyticsAPI();
					$this->ga_params = $this->the_plugin->get_theoption( $this->the_plugin->alias . '_oAuthCode' );
					
					$this->ga->auth->setClientId( $analytics_settings['client_id'] );
					$this->ga->auth->setClientSecret( $analytics_settings['client_secret'] );
					$this->ga->auth->setRedirectUri( $analytics_settings['redirect_uri'] );
					
					$auth = $this->ga->auth->getAccessToken( $code );
					// Try to get the AccessToken
					if ((int) $auth['http_code'] == 200) {
						$last_status = array('last_status' => array('status' => 'success', 'step' => 'auth', 'data' => date("Y-m-d H:i:s"), 'msg' => $auth));
						$this->the_plugin->save_theoption( $this->the_plugin->alias . '_ganalytics_last_status', $last_status );
						$this->the_plugin->save_theoption( $this->the_plugin->alias . '_google_analytics', array_merge( (array) $analytics_settings, $last_status ) );

						$this->the_plugin->save_theoption( $this->the_plugin->alias . '_oAuthCode', array(
							'access_token' 		=> $auth['access_token'],
							'refresh_token' 	=> $auth['refresh_token'],
							'expires_in' 		=> $auth['expires_in'],
							'token_created' 	=> time(),
							'token_type'		=> $auth['token_type']
						) );
					} else {
						$last_status = array('last_status' => array('status' => 'error', 'step' => 'auth', 'data' => date("Y-m-d H:i:s"), 'msg' => $auth));
						$this->the_plugin->save_theoption( $this->the_plugin->alias . '_ganalytics_last_status', $last_status );
						$this->the_plugin->save_theoption( $this->the_plugin->alias . '_google_analytics', array_merge( (array) $analytics_settings, $last_status ) );
					}
					
					die('<script>
					window.onunload = function() {
					    if (window.opener && !window.opener.closed) {
					        window.opener.pspPopUpClosed();
					    }
					};
					
					window.close();
					</script>;');
				} else {
					$last_status = array('last_status' => array('status' => 'error', 'step' => 'code', 'data' => date("Y-m-d H:i:s"), 'msg' => $code));
					$this->the_plugin->save_theoption( $this->the_plugin->alias . '_ganalytics_last_status', $last_status );
					$this->the_plugin->save_theoption( $this->the_plugin->alias . '_google_analytics', array_merge( (array) $analytics_settings, $last_status ) );
				}
			}
		}
    }
}

// Initialize the pspGoogleAnalytics class
//$pspGoogleAnalytics = new pspGoogleAnalytics();
$pspGoogleAnalytics = pspGoogleAnalytics::getInstance();