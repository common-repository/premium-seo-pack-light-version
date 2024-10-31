<?php
/*
* Define class pspPageSpeedInsights
* Make sure you skip down to the end of this file, as there are a few
* lines of code that are very important.
*/
!defined('ABSPATH') and exit;
if (class_exists('pspPageSpeedInsights') != true) {
    class pspPageSpeedInsights
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
			$this->module_folder = $this->the_plugin->cfg['paths']['plugin_dir_url'] . 'modules/google_pagespeed/';
			$this->module = $this->the_plugin->cfg['modules']['google_pagespeed'];

			if (is_admin()) {
	            add_action('admin_menu', array( &$this, 'adminMenu' ));
			}
			
			// load the ajax helper
			require_once( $this->the_plugin->cfg['paths']['plugin_dir_path'] . 'modules/google_pagespeed/ajax.php' );
			new pspPageSpeedInsightsAjax( $this->the_plugin );
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
    		if ( $this->the_plugin->capabilities_user_has_module('google_pagespeed') ) {
	    		add_submenu_page(
	    			$this->the_plugin->alias,
	    			$this->the_plugin->alias . " " . __('PageSpeed Insights', 'psp'),
		            __('PageSpeed Insights', 'psp'),
		            'read',
		            $this->the_plugin->alias . "_PageSpeedInsights",
		            array($this, 'display_index_page')
		        );
    		}

			return $this;
		}

		public function display_meta_box()
		{
			if ( $this->the_plugin->capabilities_user_has_module('google_pagespeed') ) {
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
			$module_settings = $pagespeed_settings = $this->the_plugin->get_theoption( $this->the_plugin->alias . "_pagespeed" );

			$pagespeed_mandatoryFields = array(
				'developer_key'			=> false,
				'google_language'		=> false
			);
			if ( isset($pagespeed_settings['developer_key']) && !empty($pagespeed_settings['developer_key']) ) {
				$pagespeed_mandatoryFields['developer_key'] = true;
			}
			if ( isset($pagespeed_settings['google_language']) && !empty($pagespeed_settings['google_language']) ) {
				$pagespeed_mandatoryFields['google_language'] = true;
			}
			$mandatoryValid = true;
			foreach ($pagespeed_mandatoryFields as $k=>$v) {
				if ( !$v ) {
					$mandatoryValid = false;
					break;
				}
			}
			if ( !$mandatoryValid ) {
				$error_number = 1; // from config.php / errors key
				
				$ret['html'] = $this->the_plugin->print_module_error( $this->module, $error_number, 'Error: Unable to use PageSpeed module, yet!' );
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
				pspAdminMenu::getInstance()->make_active('monitoring|google_pagespeed')->show_menu();
				?>
				

				<!-- Content -->
				<section class="<?php echo $this->the_plugin->alias; ?>-main">
					
					<?php 
					echo psp()->print_section_header(
						$this->module['google_pagespeed']['menu']['title'],
						$this->module['google_pagespeed']['description'],
						$this->module['google_pagespeed']['help']['url']
					);
					?>
					
					<div id="<?php echo $this->the_plugin->alias; ?>-gAnalytics-wrapper" class="panel panel-default <?php echo $this->the_plugin->alias; ?>-panel">

						<div class="psp-box-update">
							<h2>With PageSpeed Insights you can identify ways to make your site faster and more mobile-friendly. <br/></h2>


							<p class="psp-update-text">PageSpeed Insights checks to see if a page has applied common performance best practices and provides a score, which ranges from 0 to 100 points, and falls into one of the following three categories: Good, Needs Work and Poor. <br/> Based on these results, you will know what to do to get a bigger score!</p>

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
		
		/**
	    * Singleton pattern
	    *
	    * @return pspPageSpeedInsights Singleton instance
	    */
	    static public function getInstance()
	    {
	        if (!self::$_instance) {
	            self::$_instance = new self;
	        }

	        return self::$_instance;
	    }
    }
}

// Initialize the pspPageSpeedInsights class
$pspPageSpeedInsights = pspPageSpeedInsights::getInstance();