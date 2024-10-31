<?php
/*
* Define class pspW3C_HTMLValidator
* Make sure you skip down to the end of this file, as there are a few
* lines of code that are very important.
*/
!defined('ABSPATH') and exit;
if (class_exists('pspW3C_HTMLValidator') != true) {
    class pspW3C_HTMLValidator
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

		static protected $_instance;

        /*
        * Required __construct() function that initalizes the AA-Team Framework
        */
        public function __construct()
        {
        	global $psp;

        	$this->the_plugin = $psp;
			$this->module_folder = $this->the_plugin->cfg['paths']['plugin_dir_url'] . 'modules/W3C_HTMLValidator/';
			$this->module = $this->the_plugin->cfg['modules']['W3C_HTMLValidator'];

			if (is_admin()) {
	            add_action('admin_menu', array( &$this, 'adminMenu' ));
			}

			// ajax optimize helper
			if ( $this->the_plugin->is_admin === true )
				add_action('wp_ajax_pspHtmlValidate', array( &$this, 'validate_page' ));
        }

		/**
	    * Singleton pattern
	    *
	    * @return pspW3C_HTMLValidator Singleton instance
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
    		if ( $this->the_plugin->capabilities_user_has_module('W3C_HTMLValidator') ) {
	    		add_submenu_page(
	    			$this->the_plugin->alias,
	    			$this->the_plugin->alias . " " . __('HTML Validator', 'psp'),
		            __('HTML Validator', 'psp'),
		            'read',
		            $this->the_plugin->alias . "_HTMLValidator",
		            array($this, 'display_index_page')
		        );
    		}

			return $this;
		}

		public function display_index_page()
		{
			$this->printBaseInterface();
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
				pspAdminMenu::getInstance()->make_active('advanced_setup|W3C_HTMLValidator')->show_menu();
				?>
				

				<!-- Content -->
				<section class="<?php echo $this->the_plugin->alias; ?>-main">
					
					<?php 
					echo psp()->print_section_header(
						$this->module['W3C_HTMLValidator']['menu']['title'],
						$this->module['W3C_HTMLValidator']['description'],
						$this->module['W3C_HTMLValidator']['help']['url']
					);
					?>
					
					<div id="<?php echo $this->the_plugin->alias; ?>-gAnalytics-wrapper" class="panel panel-default <?php echo $this->the_plugin->alias; ?>-panel">

						<div class="psp-box-update">
							<h2>It's important that your website coding to be valid! <br/></h2>


							<p class="psp-update-text">Using the W3c Validator Module, you can check if your website's pages are valid or not. <br/> That way you can fix all major errors that might cause bad rendering or parser issues.</p>

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
		* validate_page, method
		* ---------------------
		*
		* this will validate your page html code
		*/
		public function validate_page( $id=0 )
		{
			$ret = array(
				'status' 		=> 'invalid',
				'msg'			=> '',
			);

			$html = array();
			$summary = array();
			$score = 0;
			$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : (int)$id;

			$checkUrl = 'http://validator.w3.org/check?uri=' . get_permalink($id);

			sleep(1);
			$browserRequest = $this->the_plugin->remote_get( $checkUrl, 'default', array('timeout' => 10) );

			$last_check_at = date('Y-m-d H:i:s');
			$ret['last_check_at'] = $last_check_at;

			if ( is_wp_error( $browserRequest ) ) { // If there's error
				$body = false;
				$err = htmlspecialchars( implode(';', $browserRequest->get_error_messages()) );

				$ret['msg'] = $err;
				update_post_meta($id, 'psp_w3c_validation', $ret);

				die(json_encode($ret));
			}
			else {
				$body = wp_remote_retrieve_body( $browserRequest );
			}

			/*$status = array(
				'status' => isset($browserRequest['headers']["x-w3c-validator-status"]) ? $browserRequest['headers']["x-w3c-validator-status"] : '',
				'nr_of_errors' => isset($browserRequest['headers']["x-w3c-validator-errors"]) ? $browserRequest['headers']["x-w3c-validator-errors"] : '',
				'nr_of_warning' => isset($browserRequest['headers']["x-w3c-validator-warnings"]) ? $browserRequest['headers']["x-w3c-validator-warnings"] : '',
				'recursion' => isset($browserRequest['headers']["x-w3c-validator-recursion"]) ? $browserRequest['headers']["x-w3c-validator-recursion"] : ''
			);*/
			if ( trim($body) == '' ) {
				$ret['msg'] = isset($browserRequest['msg']) ? $browserRequest['msg'] : 'empty content retrieved!';
				update_post_meta($id, 'psp_w3c_validation', $ret);

				die(json_encode($ret));
			}
			$status = $this->parse_response( $body );

			// valid response
			if ( isset($status['status']) ) {
				$ret = array_replace_recursive($ret, $status);
				update_post_meta($id, 'psp_w3c_validation', $ret);

				die(json_encode($ret));
			}

			$ret['msg'] = 'unknown error occured!';
			die(json_encode($ret));
		}

		// 2015, october 10 - update
		// API http://validator.w3.org/check? don't return necessary headers (regarding number of errors, warning ...) in response 
		private function parse_response( $the_content ) {
			$status = array(
				'status' 		=> 'invalid',
				'nr_of_errors' 	=> 0,
				'nr_of_warning' => 0,
				'nr_of_info'	=> 0,
				//'recursion' 	=> '',
				'msg'			=> '',
			);
			
			//if ( trim($the_content) == "" ) return array_merge($status, array('msg' => 'empty content'));
 
 			// php query class
			require_once( $this->the_plugin->cfg['paths']['scripts_dir_path'] . '/php-query/php-query.php' );  

			if ( !empty($this->the_plugin->charset) )
				$doc = pspphpQuery::newDocument( $the_content, $this->the_plugin->charset );
			else
				$doc = pspphpQuery::newDocument( $the_content );

			$items = array();
			if ( pspPQ('#results li')->size() ) {
				$items = pspPQ('#results li');
			}
			else if ( pspPQ('#result #error_loop li')->size() ) {
				$items = pspPQ('#result #error_loop li');
			}
			foreach( $items as $li ) {
				// cache the object
				$li = pspPQ($li);
				$css_class = $li->attr('class');

				if ( 'info' == $css_class || 'msg_info' == $css_class ) {
					$status['nr_of_info']++;
				}
				else if ( 'info warning' == $css_class || 'msg_warn' == $css_class ) {
					$status['nr_of_warning']++;
				}
				else if ( 'error' == $css_class || 'msg_err' == $css_class ) {
					$status['nr_of_errors']++;
				}
			}
			
			if ( empty($status['nr_of_warning']) && empty($status['nr_of_errors']) ) {
				$status['status'] = 'valid';
			}
			return $status;
		}
    }
}

// Initialize the pspW3C_HTMLValidator class
//$pspW3C_HTMLValidator = new pspW3C_HTMLValidator($this->cfg, ( isset($module) ? $module : array()) );
$pspW3C_HTMLValidator = pspW3C_HTMLValidator::getInstance( $this->cfg, ( isset($module) ? $module : array()) );