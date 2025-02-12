<?php
/*
* Define class pspSERP
* Make sure you skip down to the end of this file, as there are a few
* lines of code that are very important.
*/
!defined('ABSPATH') and exit;
if (class_exists('pspSERP') != true) {
    class pspSERP
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
		
		private $plugin_settings = array();
		
		private $search_engine = 'google'; //search engine used from plugin serp settings!
		private $serp_sleep = 0;
		
		private $__initialDate = array();
		private $__defaultClause = '';


        /*
        * Required __construct() function that initalizes the AA-Team Framework
        */
        public function __construct( $is_cron=false )
        {
        	global $psp;

        	$this->serp_sleep = rand(30,55); //in seconds: serp sleep between consecutive requests!

        	$this->the_plugin = $psp;
			$this->module_folder = $this->the_plugin->cfg['paths']['plugin_dir_url'] . 'modules/serp/';
			$this->module = $this->the_plugin->cfg['modules']['serp'];

			$this->plugin_settings = $this->the_plugin->get_theoption( $this->the_plugin->alias . '_serp' );

			$this->search_engine .= ('.' . $this->plugin_settings['google_country']);

			// ajax  helper
			if ( $this->the_plugin->is_admin === true && !$is_cron ) {
	            add_action('admin_menu', array( $this, 'adminMenu' ));

				// ajax handler
				add_action('wp_ajax_pspAddToReporter', array( $this, 'addToReporter' ));
				add_action('wp_ajax_pspUpdateToReporter', array( $this, 'updateToReporter' ));
				add_action('wp_ajax_pspRemoveFromReporter', array( $this, 'removeFromReporter' ));

				add_action('wp_ajax_pspGetSERPGraphData', array( $this, 'getSERPGraphData' ));
				add_action('wp_ajax_pspSetSearchEngine', array( $this, 'setSearchEngine' ));

				add_action('wp_ajax_pspGetEngineAccessTime', array( $this, 'getEngineAccessTime' ));
				add_action('wp_ajax_pspGetFocusKW', array( $this, 'getFocusKW' ));
			}

			//if ( $this->the_plugin->capabilities_user_has_module('serp') )
			if ( !$this->the_plugin->verify_module_status( 'serp' ) ) ; //module is inactive
			else {
				if ( $this->the_plugin->is_admin !== true ) {
					// visits!
					add_action('wp_head',  array( $this, 'save_visits' ));
				}
			}
			
			// cron to check all serp rows!
			// wp_schedule_event(time(), 'daily', 'psp_start_cron_serp_check'); //plugin activation daily|hourly
			// add_action('psp_start_cron_serp_check', array( $this, 'check_reporter' ));
			// wp_clear_scheduled_hook('psp_start_cron_serp_check'); //plugin deactivation
			// add_filter( 'cron_schedules', array( $this, 'cron_add_custom' ));
			
			if ( $this->the_plugin->is_admin === true && !$is_cron ) {

				$this->__initialDate = $this->getInitialData(); //initial date!
				if ( empty($this->__initialDate) )
					$this->__initialDate = array( date( 'Y-m-d' ) => 1  );
				$this->__initialDate = array(
					'from' 	=> date( 'Y-m-d', strtotime( "-1 week", strtotime( key($this->__initialDate) ) ) ),
					'to' 	=> date( 'Y-m-d', strtotime( key($this->__initialDate) ) )
				);
				$engine = '';
				if (isset($_SESSION['psp_serp']['search_engine']) && !empty($_SESSION['psp_serp']['search_engine'])
				&& $_SESSION['psp_serp']['search_engine']!='--all--')
					$engine = $_SESSION['psp_serp']['search_engine'];

				$this->__defaultClause = $this->getDefaultClause(array(
					'engine'	=> $engine,
					'from_date'	=> $this->__initialDate['from'],
					'to_date'	=> $this->__initialDate['to'],
				));
			}
        }
        
		/**
	    * Singleton pattern
	    *
	    * @return pspSERP Singleton instance
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
    		if ( $this->the_plugin->capabilities_user_has_module('serp') ) {
	    		add_submenu_page(
	    			$this->the_plugin->alias,
	    			__('Search Engine Results Page Tracking', 'psp'),
		            __('SERP Tracking', 'psp'),
		            'read',
		            $this->the_plugin->alias . "_SERP",
		            array($this, 'display_index_page')
		        );
    		}

			return $this;
		}

		public function display_meta_box()
		{
			if ( $this->the_plugin->capabilities_user_has_module('serp') ) {
				$this->printBoxInterface();
			}
		}
		
		public function save_visits()
		{
			global $post, $wpdb;
			
			//Due to late-2011 Google security changes, this is no longer possible when the search was performed by a signed-in Google user!
			//referrer ex: http://www.google.fi/search?hl=en&q=http+header+referer&btnG=Google-search&meta=&aq=f&oq=

			$searchEngines = $this->getSearchEngineUsed();
			$referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
			$currentPage = isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : '';
			$search_engine = '';
			$postId = 0;
			
			//not from admin!
			if (!is_user_logged_in() && count($searchEngines)>0) {
			//if (1) { //debug!
				$__referrer = parse_url($referrer); //search engine reffer url!
				//$__referrer['host'] = 'google.com'; //debug!
				foreach ($searchEngines as $k=>$v) {
					$search_engine = $v;
					if (preg_match("/".str_replace('.', '\.', $search_engine)."$/i", $__referrer['host'])) {
						parse_str($__referrer['query'], $__query);
						$__q = strtolower(trim($__query['q'])); // searched keyword!
						$__q = htmlspecialchars(stripslashes($__q), ENT_QUOTES);
						if ($__q!='') { // non empty keyword!
						//if (1) { //debug!
							if (preg_match("/post-([0-9]+)\//i", $currentPage, $__m)) $postId = $__m[1];
							else if (preg_match("/page-([0-9]+)\//i", $currentPage, $__m)) $postId = $__m[1];
						}
					}
				}
			}

			// update reported row!
			if ($search_engine!='' && $postId>0) {
				// check if you already have this info into DB
				$reporterSql = $wpdb->prepare( "SELECT a.id as rowid, a.* FROM " . ( $wpdb->prefix ) . "psp_serp_reporter as a WHERE 1=1 AND a.search_engine=%s AND a.post_id=%s LIMIT 1", $search_engine, $postId );

				$row = $wpdb->get_row( $reporterSql, ARRAY_A );
				$row_id = (int) $row['rowid'];

				// if not found
				if( $row_id > 0 ) {

					// update report - previous, worst, best rank!
					$wpdb->update(
						$wpdb->prefix . "psp_serp_reporter",
						array(
							'visits'		=> (int) ($row['visits'] + 1)
						),
						array( 'post_id' => $postId, 'search_engine' => $search_engine ),
						array(
							'%d'
						),
						array( '%d', '%s' )
					);
				}
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
			$module_settings = $serp_settings = $this->the_plugin->get_theoption( $this->the_plugin->alias . "_serp" );

			$serp_mandatoryFields = array(
				'developer_key'			=> false,
				'custom_search_id'		=> false,
				'google_country'		=> false
			);
			if ( isset($serp_settings['developer_key']) && !empty($serp_settings['developer_key']) ) {
				$serp_mandatoryFields['developer_key'] = true;
			}
			if ( isset($serp_settings['custom_search_id']) && !empty($serp_settings['custom_search_id']) ) {
				$serp_mandatoryFields['custom_search_id'] = true;
			}
			if ( isset($serp_settings['google_country']) && !empty($serp_settings['google_country']) ) {
				$serp_mandatoryFields['google_country'] = true;
			}
			$mandatoryValid = true;
			foreach ($serp_mandatoryFields as $k=>$v) {
				if ( !$v ) {
					$mandatoryValid = false;
					break;
				}
			}
			if ( !$mandatoryValid ) {
				$error_number = 1; // from config.php / errors key
				
				$ret['html'] = $this->the_plugin->print_module_error( $this->module, $error_number, 'Error: Unable to use Google Serp module, yet!' );
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
				pspAdminMenu::getInstance()->make_active('monitoring|serp')->show_menu();
				?>
				

				<!-- Content -->
				<section class="<?php echo $this->the_plugin->alias; ?>-main">
					
					<?php 
					echo psp()->print_section_header(
						$this->module['serp']['menu']['title'],
						$this->module['serp']['description'],
						$this->module['serp']['help']['url']
					);
					?>
					
					<div id="<?php echo $this->the_plugin->alias; ?>-gAnalytics-wrapper" class="panel panel-default <?php echo $this->the_plugin->alias; ?>-panel">

						<div class="psp-box-update">
							<h2>What's SERP? <br/></h2>


							<p class="psp-update-text">A search engine results page (SERP) is the page displayed by a search engine in response to a query by a searcher. The main component of the SERP is the listing of results that are returned by the search engine in response to a keyword query. <br/> Using this module you can keep track of your focus keywords rankings on google easily!</p>

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
		//getSERPGraphData: this will create request to psp_serp_reporter table
		public function getSERPGraphData()
		{
			global $wpdb;
			
			$request = array(
				'engine' 	=> isset($_REQUEST['engine']) ? trim($_REQUEST['engine']) : '',
				'from_date' => isset($_REQUEST['from_date']) ? trim($_REQUEST['from_date']) : '',
				'to_date' 	=> isset($_REQUEST['to_date']) ? trim($_REQUEST['to_date']) : '',
				'keys' 		=> isset($_REQUEST['keys']) ? trim($_REQUEST['keys']) : '',
				'urls' 		=> isset($_REQUEST['urls']) ? trim($_REQUEST['urls']) : ''
			);

			//search engine
			$__dose = false;
			if ($request['engine']!='--all--') $__dose = true;

			//keys
			$request['keys_tmp'] = explode(',', $request['keys']);
			$q_key_clause = ($request['keys']!='' && is_array($request['keys_tmp']) && count($request['keys_tmp'])>0 ? ' a.focus_keyword in (' . implode(', ', array_map(array($this, 'prepareForInList'), $request['keys_tmp'])) . ') ' : '');

			//urls
			$request['urls_tmp'] = explode(',', $request['urls']);
			$q_url_clause = ($request['urls']!='' && is_array($request['urls_tmp']) && count($request['urls_tmp'])>0 ? ' a.url in (' . implode(', ', array_map(array($this, 'prepareForInList'), $request['urls_tmp'])) . ') ' : '');
			
			//keys + urls clause!
			$q_keyurl_clause = ($q_key_clause!='' && $q_url_clause!='' ? ' and (' . $q_key_clause . ' or ' . $q_url_clause . ') ' : ($q_key_clause!='' ? ' and ' . $q_key_clause : ($q_url_clause!='' ? ' and ' . $q_url_clause : '')));
			
			//default clause!
			if ($q_keyurl_clause=='') {
				$q_keyurl_clause .= $this->__defaultClause;
			}
			
			//Query
			$get_ranks_sql = $wpdb->prepare( "SELECT a.*, b.* FROM " . ( $wpdb->prefix ) . "psp_serp_reporter as a LEFT JOIN " . ( $wpdb->prefix ) . "psp_serp_reporter2rank as b ON a.id=report_id WHERE 1=1 " . ($__dose ? " AND a.search_engine='".$request['engine']."' " : " ") . $q_keyurl_clause . " AND b.report_day BETWEEN %s and %s order by b.report_day DESC;", $request['from_date'], $request['to_date'] );

			$results = $wpdb->get_results( $get_ranks_sql, ARRAY_A );
			
			// reorder array base on focus kw and link as key
			if( count($results) > 0 ){
				$serp_data = array();
				foreach ($results as $key => $value){
					unset($value['top100']);
					$serp_data[sanitize_text_field( $value['focus_keyword'] . '!!' . $value['url'] )][$value['report_day']] = $value;
				}
				
				if( count($serp_data) > 0 ){		
					$ret_data = array();
					foreach ($serp_data as $key => $value){
						
						// Alias 
						$alias = explode("!!", $key);
						$alias = $alias[0] . ' - ' . $alias[1];
						
						// rank per day
						$data = array();
						if( count($value) > 0 ){
							foreach ($value as $key2 => $value2) {
								$data[] = array( strtotime($value2['report_day']) * 1000, $value2['position']==999 ? 0 : $value2['position'] );
							}  
						}
						
						$ret_data[] = array(
							'label' => $alias,
							'data' 	=> $data
						);
					}
				}
				
				die( json_encode(
					array(
						'status' 	=> 'valid',
						'data'		=> $ret_data,
						'def_key'	=> isset($__latestKey2) ? $__latestKey2 : ''
						//,'sql'		=> $get_ranks_sql
					)
				));
			}
			
			die( json_encode(
				array(
					'status' 	=> 'invalid'
					//,'sql'		=> $get_ranks_sql
				)
			));
		}
		
		public function getDefaultClause( $request ) {
			global $wpdb;

			$q_keyurl_clause = '';

			$__dose = false;
			if ( $request['engine']!='' ) $__dose = true;

			//Error Code: 1235
			//This version of MySQL doesn't yet support 'LIMIT & IN/ALL/ANY/SOME subquery'
			//$q_keyurl_clause = " and a.focus_keyword in (select c.focus_keyword from " . ( $wpdb->prefix ) . "psp_serp_reporter as c where 1=1 group by c.focus_keyword order by c.created desc limit 5) ";
			/*$__latestKeyQuery = "select c.focus_keyword from " . ( $wpdb->prefix ) . "psp_serp_reporter as c where 1=1 " . ($__dose ? " AND c.search_engine='".$request['engine']."' " : " ") . " group by c.focus_keyword order by c.created desc limit 5;";

			$__latestKey = $wpdb->get_results( $__latestKeyQuery, ARRAY_A );
			$__latestKey2 = array();
			if (is_array($__latestKey) && count($__latestKey)>0) {
				foreach ($__latestKey as $k=>$v) {
					$__latestKey2[] = $v['focus_keyword'];
				}
			}
			$q_keyurl_clause = (is_array($__latestKey2) && count($__latestKey2)>0 ? ' a.focus_keyword in (' . implode(', ', array_map(array(self, 'prepareForInList'), $__latestKey2)) . ') ' : '');
			$q_keyurl_clause = ($q_keyurl_clause!='' ? ' and ' . $q_keyurl_clause  : '');*/

			$__latestKeyQuery = $wpdb->prepare( "SELECT a.focus_keyword, a.url FROM " . ( $wpdb->prefix ) . "psp_serp_reporter as a LEFT JOIN " . ( $wpdb->prefix ) . "psp_serp_reporter2rank as b ON a.id=report_id WHERE 1=1 " . ($__dose ? " AND a.search_engine='".$request['engine']."' " : " ") . " AND b.report_day BETWEEN %s and %s GROUP BY a.focus_keyword, a.url order by b.report_day DESC LIMIT 5;", $request['from_date'], $request['to_date'] );

			$__latestKey = $wpdb->get_results( $__latestKeyQuery, ARRAY_A );

			$__latestKey2 = array();
			if (is_array($__latestKey) && count($__latestKey)>0) {
				foreach ($__latestKey as $k=>$v) {
					$__latestKey2["{$v['url']}"] = $v['focus_keyword'];

					$_SESSION['psp_serp']['filter_keywords']["{$v['focus_keyword']}"] = true;
					$_SESSION['psp_serp']['filter_urls']["{$v['url']}"] = true;
				}
				$__tmp = array();
				foreach ($__latestKey2 as $kk=>$vv) {
					$__tmp[] = ( "('" . $kk . "', '" . $vv . "')");
				}
				$__tmp = implode(', ', $__tmp);
				$q_keyurl_clause = $__tmp!='' ? " (a.url, a.focus_keyword) in ( " . $__tmp . " ) " : "";
				$q_keyurl_clause = ($q_keyurl_clause!='' ? ' and ' . $q_keyurl_clause  : '');
			}
			return $q_keyurl_clause;
		}

		public function getInitialData() {
			global $wpdb;

			$sql = "
				SELECT COUNT(b.id) AS nb, b.report_day FROM " . ( $wpdb->prefix ) . "psp_serp_reporter AS a LEFT JOIN
				 " . ( $wpdb->prefix ) . "psp_serp_reporter2rank AS b ON a.id=report_id WHERE 1=1
				 AND b.position>0
				 GROUP BY b.report_day
				 HAVING nb>1
				 ORDER BY b.report_day DESC
				 limit 7;
			";
			$results = $wpdb->get_results( $sql, ARRAY_A );

			// reorder array
			$ret = array();
			if( count($results) > 0 ){
				foreach ($results as $kk=>$vv) {
					$ret[ $vv['report_day'] ] = $vv['nb'];
				}
			}
			return $ret;
		}

		//addToReporter: this will create request to psp_serp_reporter table
		public function addToReporter( $keyword='', $link='', $itemid=0 )
		{
			$request = array(
				'itemid' 		=> isset($_REQUEST['itemid']) ? trim($_REQUEST['itemid']) : $itemid,
				'return'		=> isset($_REQUEST['return']) ? trim($_REQUEST['return']) : '',
				'action'		=> isset($_REQUEST['action']) ? trim($_REQUEST['action']) : 'pspAddToReporter',
				'sub_action' 	=> isset($_REQUEST['sub_action']) ? trim($_REQUEST['sub_action']) : '',

				'keyword' 		=> isset($_REQUEST['keyword']) ? trim($_REQUEST['keyword']) : $keyword,
				'link' 			=> isset($_REQUEST['link']) ? trim($_REQUEST['link']) : $link,
			);

			$ret = array(
				'status'		=> 'invalid',
				'msg'			=> '',
				'msg_wait'		=> '',
				'html'			=> '',
			);

			$search_engine = $this->search_engine;
			
			// publish/unpublish
			if ( $request['sub_action']=='publish' ) {

				//keep page number & items number per page
				$_SESSION['pspListTable']['keepvar'] = array('paged'=>true,'posts_per_page'=>true);
				
				// add to DB or update if is from new day
				$addToDb = $this->addToReportDB( array( 'keyword' => $request['keyword'], 'url' => $request['link'] ), 'default' );

				$list_table = $this->ajax_list_table_rows();
				$waitStat = $this->getEngineAccessTime( 'return' );

				// return for ajax
				$ret = array_replace_recursive($ret, array(
					'status'		=> 'valid',
					'msg'			=> '<div class="psp-message psp-success">' . __('success/ keyword publish status changed.', 'psp') . '</div>',
					'msg_wait'		=> $waitStat['html'],
					'html'	 		=> $list_table['html'],
				), $addToDb);
				if ( $request['return'] == 'array' ) {
					return $ret;
				}
				die(json_encode($ret));
			}

			require_once( $this->the_plugin->cfg['paths']['scripts_dir_path'] . '/serp/serp.api.class.php' );
			$serp = pspSERPCheck::getInstance(); 
			
			$__spParams = array(
				'engine'		=> $search_engine,
				'keyword'		=> $request['keyword'],
				'link'			=> $request['link']
			);
			$serp->saveLog(true);
			$googleScoreInfo = $serp->__get_serp_score( $__spParams );
			if ( $googleScoreInfo===false || ( isset($googleScoreInfo['status']) 
				&& $googleScoreInfo['status']=='invalid' ) ) {
					
				if ( $request['action'] == 'pspUpdateToReporter' && $request['itemid'] > 0 ) { //update error message only for update!

					$this->googleAccessStatus( $request['itemid'], array(
						'status'	=> 'invalid',
						'msg'		=> $googleScoreInfo['msg']
					) );
				}
				
				$_SESSION['psp_engine_access_status'] = 'invalid';

				$list_table = $this->ajax_list_table_rows();
				$waitStat = $this->getEngineAccessTime( 'return' );

				// return for ajax
				$ret = array_replace_recursive($ret, array(
					'status'		=> 'invalid',
					'msg'			=> '<div class="psp-message psp-error">' . __('error/ keyword - could not retrieve google score.', 'psp') . '</div>',
					'msg_wait'		=> $waitStat['html'],
					'html'	 		=> $list_table['html'],
				));
				if ( $request['return'] == 'array' ) {
					return $ret;
				}
				die(json_encode($ret));
			}
			
			if ( $request['action'] == 'pspUpdateToReporter' ) {
				//keep page number & items number per page
				$_SESSION['pspListTable']['keepvar'] = array('paged'=>true,'posts_per_page'=>true);
			}
			
			// add to DB or update if is from new day
			$addToDb = $this->addToReportDB( $googleScoreInfo, 'default', $request['itemid'] );
			
			$_SESSION['psp_engine_access_status'] = 'valid';

			$list_table = $this->ajax_list_table_rows();
			$waitStat = $this->getEngineAccessTime( 'return' );

			// return for ajax
			$retdata = $this->get_serp_scores( $request['keyword'], $request['link'], 'default' );

			$ret = array_replace_recursive($ret, array(
				'status'		=> 'valid',
				//'data'		=> $retdata,
				'msg'			=> '<div class="psp-message psp-success">' . __('success/ keyword was updated.', 'psp') . '</div>',
				'msg_wait'		=> $waitStat['html'],
				'html'	 		=> $list_table['html'],
			), $addToDb);
			if ( $request['return'] == 'array' ) {
				return $ret;
			}
			die(json_encode($ret));
		}

		//updateToReporter: this will create request to psp_serp_reporter table
		public function updateToReporter()
		{
			global $wpdb;

			$request = array(
				'itemid' 	=> isset($_REQUEST['itemid']) ? (int)$_REQUEST['itemid'] : 0,
				'return'	=> isset($_REQUEST['return']) ? trim($_REQUEST['return']) : '',
			);

			$ret = array(
				'status'		=> 'invalid',
				'msg'			=> '',
				'msg_wait'		=> '',
				'html'			=> '',
			);
			
			if ( $request['itemid'] > 0 ) {
				$row = $wpdb->get_row( "SELECT * FROM " . ( $wpdb->prefix ) . "psp_serp_reporter WHERE id = '" . ( $request['itemid'] ) . "'", ARRAY_A );
				 
				// this function will automaticaly detect if already have this item and just update the score
				//$_REQUEST['return'] = 'array';
				$addResult = $this->addToReporter( $row['focus_keyword'], $row['url'] );
				$ret = array_replace_recursive($ret, $addResult);

				if ( $request['return'] == 'array' ) {
					return $ret;
				}
				die(json_encode($ret));
			}

			$waitStat = $this->getEngineAccessTime( 'return' );

			$ret = array_replace_recursive($ret, array(
				'msg'			=> '<div class="psp-message psp-error">' . __('error/ itemid is empty.', 'psp') . '</div>',
				'msg_wait'		=> $waitStat['html'],
			));
			if ( $request['return'] == 'array' ) {
				return $ret;
			}
			die(json_encode($ret));
		}
		
		//addToReportDB: this will create request to psp_serp_reporter table
		public function addToReportDB( $scoreArray=array(), $search_engine='default', $itemid=0 )
		{
			global $wpdb;
			$wpdb->suppress_errors = false;
			$wpdb->show_errors = true;

			$ret = array(
				'status'		=> 'invalid',
				'msg'			=> '',
			);

			$fieldLimits = array(
				'keyword'			=> 100,
				'url'				=> 200,
			);

			// helper today date
			$today = date("Y-m-d");
			
			if ($search_engine=='default') {
				$search_engine = $this->search_engine;
			}

			// check if you already have this info into DB 
			$checkSQL = $wpdb->prepare( "SELECT a.id as rowid, a.*, b.* FROM " . ( $wpdb->prefix ) . "psp_serp_reporter as a LEFT JOIN " . ( $wpdb->prefix ) . "psp_serp_reporter2rank as b ON a.id=b.report_id WHERE 1=1 AND a.focus_keyword=%s AND a.url=%s AND a.search_engine=%s order by b.report_day DESC LIMIT 1", $scoreArray['keyword'], $scoreArray['url'], $search_engine );

			$row = $wpdb->get_row( $checkSQL, ARRAY_A );
			$row_id = (int) $row['rowid'];
  
  			//:: FIRST - we don't return here
  			// just try publish / unpublish existent keyword
			if ( $row_id > 0 ) {
  
					$request = array(
						'sub_action' => isset($_REQUEST['sub_action']) ? trim($_REQUEST['sub_action']) : ''
					);
				
					// publish/unpublish
					if ( $request['sub_action']=='publish' ) {
						$wpdb->update( 
							$wpdb->prefix . "psp_serp_reporter", 
							array( 
								'publish'		=> $row['publish']=='Y' ? 'N' : 'Y'
							), 
							array( 'id' => $row_id ), 
							array( 
								'%s'
							), 
							array( '%d' ) 
						);

						$ret = array_replace_recursive($ret, array(
							'status'		=> 'valid',
							'msg'			=> '<div class="psp-message psp-success">' . sprintf( __('success/db/ keyid %d : keyword publish or unpublish changed.', 'psp'), $row_id ) . '</div>',
						));
						return $ret;
					}
					else {
						$ret = array_replace_recursive($ret, array(
							'status'		=> 'invalid',
							'msg'			=> '<div class="psp-message psp-error">' . sprintf( __('error/db/ keyid %d : but action is not publish.', 'psp'), $row_id ) . '</div>',
						));
					}
			}

			//:: SECOND
			// new keyword => insert it
			if( $row_id == 0 ){
  
				$isErr = false;
   				if ( !$isErr && strlen($scoreArray['keyword']) > $fieldLimits['keyword'] ) {
   					$isErr = true;
					$msg = '<div class="psp-message psp-error">' . sprintf( __('error/db/ keyid %d : keyword is too long - more than allowed max %d chars.', 'psp'), $row_id, $fieldLimits['keyword'] ) . '</div>';
  				}
   				if ( !$isErr && strlen($scoreArray['url']) > $fieldLimits['url'] ) {
   					$isErr = true;
					$msg = '<div class="psp-message psp-error">' . sprintf( __('error/db/ keyid %d : url is too long - more than allowed max %d chars.', 'psp'), $row_id, $fieldLimits['url'] ) . '</div>';
  				}

  				if ( $isErr ) {
					$ret = array_replace_recursive($ret, array(
						'status'		=> 'invalid',
						'msg'			=> $msg,
					));
					return $ret;
  				}

				// add new row into report table
				$insert_id = $wpdb->insert( 
					$wpdb->prefix . "psp_serp_reporter", 
					array( 
						'focus_keyword' => $scoreArray['keyword'], 
						'url' 			=> $scoreArray['url'],
						'search_engine' => $search_engine,
						'post_id'		=> $itemid,
						'position' 		=> $scoreArray['pos'],
						'position_prev' => $scoreArray['pos'],
						'position_worst'=> $scoreArray['pos'],
						'position_best' => $scoreArray['pos'],
						'last_check_status'	=> 'valid',
						'last_check_data'	=> date("Y-m-d H:i:s"),
						'last_check_msg'	=> ''
					), 
					array( 
						'%s',
						'%s',
						'%s',
						'%d',
						'%d',
						'%d',
						'%d',
						'%d',
						'%s',
						'%s',
						'%s'
					) 
				);
				$insert_id = $wpdb->insert_id;

				// add keyword rank for today
				if ( $insert_id ) {
					$insert_id2 = $wpdb->insert( 
						$wpdb->prefix . "psp_serp_reporter2rank", 
						array( 
							'report_id' 	=> $insert_id, 
							'position' 		=> $scoreArray['pos'],
							'top100' 		=> @serialize($scoreArray['top100']),
							'report_day' 	=> date("Y-m-d")
						), 
						array( 
							'%d',
							'%d',
							'%s',
							'%s'
						) 
					);
					$insert_id2 = $wpdb->insert_id;

					$ret = array_replace_recursive($ret, array(
						'status'		=> 'valid',
						'msg'			=> '<div class="psp-message psp-success">' . sprintf( __('success/db/ keyid %d, rankid %d : add new keyword & its rank for today.', 'psp'), $insert_id, $insert_id2 ) . '</div>',
					));
				}
				else {
					$ret = array_replace_recursive($ret, array(
						'status'		=> 'invalid',
						'msg'			=> '<div class="psp-message psp-error">' . sprintf( __('error/db/ keyid %d : error add new keyword for today.', 'psp'), $row_id ) . '</div>',
					));
				}
			}
			
			// keyword exists, but add new rank for today
			elseif( $row['report_day'] < $today ){
  
				// add row into rank table
				$insert_id2 = $wpdb->insert( 
					$wpdb->prefix . "psp_serp_reporter2rank", 
					array( 
						'report_id' 	=> $row_id, 
						'position' 		=> $scoreArray['pos'],
						'top100' 		=> @serialize($scoreArray['top100']),
						'report_day' 	=> date("Y-m-d")
					), 
					array( 
						'%d',
						'%d',
						'%s',
						'%s',
					) 
				);
				$insert_id2 = $wpdb->insert_id;
				
				// best & worst ranks!
				$__ranks = $this->getCustomRanks($row_id);
				
				// update keyword - previous, worst, best rank!
				$wpdb->update( 
					$wpdb->prefix . "psp_serp_reporter", 
					array( 
						'position' 		=> $scoreArray['pos'],
						'position_prev'	=> $row['position'],
						'position_worst'=> $__ranks['rank_worst'],
						'position_best' => $__ranks['rank_best'],
						'last_check_status'	=> 'valid',
						'last_check_data'	=> date("Y-m-d H:i:s"),
						'last_check_msg'	=> ''
					), 
					array( 'id' => $row_id ), 
					array( 
						'%d',
						'%d',
						'%d',
						'%d',
						'%s',
						'%s',
						'%s'
					), 
					array( '%d' ) 
				);

				$ret = array_replace_recursive($ret, array(
					'status'		=> 'valid',
					'msg'			=> '<div class="psp-message psp-success">' . sprintf( __('success/db/ keyid %d, rankid %d : keyword exists, but add new rank for today.', 'psp'), $row_id, $insert_id2 ) . '</div>',
				));
			}
			
			// keyword exists and just update current existent rank for today
			else{
  
				$row2 = $wpdb->get_row( "SELECT * FROM " . ( $wpdb->prefix ) . "psp_serp_reporter2rank WHERE report_id = '" . ( $row_id ) . "' and report_day='" . ( $today ) . "'", ARRAY_A );
  
				// update rank
				$wpdb->update( 
					$wpdb->prefix . "psp_serp_reporter2rank", 
					array( 
						'position' 		=> $scoreArray['pos'],
						'top100' 		=> @serialize($scoreArray['top100'])
					), 
					array( 'id' => $row2['id'] ), 
					array( 
						'%d',
						'%s'
					), 
					array( '%d' ) 
				);
  
				// best & worst ranks!
				$__ranks = $this->getCustomRanks($row_id);
				
				// update report - previous, worst, best rank!
				$wpdb->update( 
					$wpdb->prefix . "psp_serp_reporter", 
					array( 
						'position' 		=> $scoreArray['pos'],
						'position_prev'	=> $row['position'],
						'position_worst'=> $__ranks['rank_worst'],
						'position_best' => $__ranks['rank_best'],
						'last_check_status'	=> 'valid',
						'last_check_data'	=> date("Y-m-d H:i:s"),
						'last_check_msg'	=> ''
					), 
					array( 'id' => $row_id ), 
					array( 
						'%d',
						'%d',
						'%d',
						'%d',
						'%s',
						'%s',
						'%s'
					), 
					array( '%d' ) 
				);

				$ret = array_replace_recursive($ret, array(
					'status'		=> 'valid',
					'msg'			=> '<div class="psp-message psp-success">' . sprintf( __('success/db/ keyid %d, rankid %d : keyword exists and just update current existent rank for today.', 'psp'), $row_id, $row2['id'] ) . '</div>',
				));
			}
  
			$request['wait_time'] = isset($_REQUEST['wait_time']) ? (int) $_REQUEST['wait_time'] : 0;  //(int) value in seconds!
			if ( $request['wait_time'] > 0 ) {
				$_SESSION['psp_engine_access_time'] = $request['wait_time'];
			}

			return $ret;
		}
		
		//removeFromReporter: this will create request to psp_serp_reporter table
		public function removeFromReporter()
		{
			global $wpdb;
			
			$request = array(
				'itemid' 	=> isset($_REQUEST['itemid']) ? (int)$_REQUEST['itemid'] : 0,
				'return'	=> isset($_REQUEST['return']) ? trim($_REQUEST['return']) : '',
			);

			$ret = array(
				'status'		=> 'invalid',
				'msg'			=> '',
				'msg_wait'		=> '',
				'html'			=> '',
			);
			
			if( $request['itemid'] > 0 ){
				$wpdb->delete( 
					$wpdb->prefix . "psp_serp_reporter", 
					array( 'id' => $request['itemid'] ) 
				);
				
				$wpdb->delete( 
					$wpdb->prefix . "psp_serp_reporter2rank", 
					array( 'report_id' => $request['itemid'] ) 
				); 
				
				//keep page number & items number per page
				$_SESSION['pspListTable']['keepvar'] = array('posts_per_page'=>true);

				$list_table = $this->ajax_list_table_rows();
				$waitStat = $this->getEngineAccessTime( 'return' );

				// return for ajax
				$ret = array_replace_recursive($ret, array(
					'status' 		=> 'valid',
					'msg'			=> '<div class="psp-message psp-success">' . __('success/ keyword was removed.', 'psp') . '</div>',
					'msg_wait'		=> $waitStat['html'],
					'html'	 		=> $list_table['html'],
				));
				if ( $request['return'] == 'array' ) {
					return $ret;
				}
				die(json_encode($ret));
			}
			
			$ret = array_replace_recursive($ret, array(
				'msg'			=> '<div class="psp-message psp-error">' . __('error/ itemid is empty.', 'psp') . '</div>',
				'msg_wait'		=> $waitStat['html'],
			));
			if ( $request['return'] == 'array' ) {
				return $ret;
			}
			die(json_encode($ret));
		}

		//get_serp_scores: this will create request to psp_serp_reporter table
		public function get_serp_scores( $kw='', $link='', $se='default' )
		{
			global $wpdb;
			
			if ($se=='default')
				$se = $this->search_engine;
			
			$serpScoresSQL = $wpdb->prepare( "SELECT a.*, b.* FROM " . ( $wpdb->prefix ) . "psp_serp_reporter as a LEFT JOIN " . ( $wpdb->prefix ) . "psp_serp_reporter2rank as b ON a.id=report_id WHERE 1=1 AND a.focus_keyword=%s AND a.url=%s AND a.search_engine=%s;", $kw, $link, $se );
			return $wpdb->get_results( $serpScoresSQL, ARRAY_A );
		}
		
		//googleAccessStatus: update google access error status!
		public function googleAccessStatus( $row_id=0, $status=array() ) {
			global $wpdb;
			
			if ( $row_id == 0 ) return false;

			// update report - previous, worst, best rank!
			$wpdb->update(
				$wpdb->prefix . "psp_serp_reporter",
				array(
					'last_check_data'	=> date("Y-m-d H:i:s"),
					'last_check_status' => $status['status'],
					'last_check_msg'	=> $status['msg']
				),
				array( 'id' => $row_id ),
				array(
					'%s',
					'%s',
					'%s'
				),
				array( '%d' )
			);
		}
		
		public function getCustomRanks($report_id) {
			global $wpdb;
			
			// get best rank
			$best_rank_data = $this->the_plugin->db->get_row( "SELECT position FROM " . ( $this->the_plugin->db->prefix ) . "psp_serp_reporter2rank where 1=1 and report_id='" . ( $report_id ) . "' order by position asc limit 1;", ARRAY_A );
			/* and position>0
			if ( is_null($best_rank_data) || empty($best_rank_data) )
				$best_pos = 0; // assume not in top 100!
			else
				$best_pos = (int) $best_rank_data['position'];*/
			$best_pos = (int) $best_rank_data['position'];

			// get worst
			$worst_rank_data = $this->the_plugin->db->get_row( "SELECT position FROM " . ( $this->the_plugin->db->prefix ) . "psp_serp_reporter2rank where 1=1 and report_id='" . ( $report_id ) . "' order by position desc limit 1;", ARRAY_A );
			/*$worst_rank_data = $this->the_plugin->db->get_row( "SELECT position FROM " . ( $this->the_plugin->db->prefix ) . "psp_serp_reporter2rank where 1=1 and report_id='" . ( $report_id ) . "' and position=0 limit 1;", ARRAY_A );
			if ( is_null($worst_rank_data) || empty($worst_rank_data) ) {
				$worst_rank_data = $this->the_plugin->db->get_row( "SELECT position FROM " . ( $this->the_plugin->db->prefix ) . "psp_serp_reporter2rank where 1=1 and report_id='" . ( $report_id ) . "' order by position desc limit 1;", ARRAY_A );
				$worst_pos = (int) $worst_rank_data['position'];
			} else {
				$worst_pos = 0; // assume not in top 100!
			}*/
			$worst_pos = (int) $worst_rank_data['position'];
			
			return array(
				'rank_best' => $best_pos,
				'rank_worst'=> $worst_pos
			);
		}
		
		public function getEngineAccessTime( $retType='die' )
		{
			$last_msg = '';
			if ( isset($_SESSION['psp_engine_access_status']) ) {
				
				if ( $_SESSION['psp_engine_access_status']=='valid' ) {
					$last_msg = '<div class="psp-message psp-success">';
					$last_msg .= __('<span class="engine-access-msg-success">' . 'Response received from Google API.' . '</span>', 'psp');
					
				} else {
					$last_msg = '<div class="psp-message psp-error">';
					$last_msg .= __('<span class="engine-access-msg-error">' . 'Could not retrieve response from Google API - you might have used all your available requests for this day!' . '</span>', 'psp');
				}
			}
			
			$settings = $this->the_plugin->getAllSettings( 'array', 'serp' );
			$nbReqMax = $settings['nbreq_max_limit'];

			// here we only retrieve it
			// the update_option is made in lib/scripts/serp/serp.api.class.php
			$currentReqInfo = get_option('psp_serp_nbrequests');
			$currentNbReq = (int) $currentReqInfo['nbreq'];
			$currentData = $currentReqInfo['data'];

			$nb_req_ = sprintf( __('<span class="engine-access-msg-info">' . 'The number of requests made is <strong>%s</strong> (of maximum %s per day).' . '</span></div>', 'psp'), $currentNbReq, $nbReqMax );

			$ret = array(
				'status' 	=> 'valid',
				'data' 		=> isset($_SESSION['psp_engine_access_time']) && $_SESSION['psp_engine_access_time']>0 ? $_SESSION['psp_engine_access_time'] : 0,
				'last_msg'	=> $last_msg,
				'nb_req'	=> $nb_req_,
				'html'		=> $last_msg . ' ' . $nb_req_,
			);

			if ( $retType == 'return' ) { return $ret; }
			else { die( json_encode( $ret ) ); }
		}

		//getFocusKW: this will create requesto to 404 table
		public function getFocusKW()
		{
			global $wpdb;
			$html = array();

			$html[] = '<table class="psp-table" style="width: 100%;border: 1px solid #dadada;background: #fff;margin: -10px 0px 0px 0px;" cellspacing="0" cellpadding="0">'; 
			$html[] = 	'<thead>'; 
			$html[] = 		'<tr>'; 
			$html[] = 			'<th>' . __('ID', 'psp') . '</th>'; 
			$html[] = 			'<th>' . __('Focus Keywords', 'psp') . '</th>'; 
			$html[] = 			'<th align="left">' . __('Permalink', 'psp') . '</th>';
			$html[] = 			'<th></th>';  
			$html[] = 		'</tr>'; 
			$html[] = 	'</thead>'; 
			
			// get all focus keywords from post_meta table 
			$results = $wpdb->get_results( "SELECT  * FROM " . ( $wpdb->prefix ) . "postmeta WHERE `meta_key` = 'psp_kw' and meta_value != '' ", ARRAY_A);
			$html[] = '<tbody>'; 
			if( count($results) > 0 ){
				foreach ($results as $key => $value){
					$permalink = get_permalink($value['post_id']);
					$html[] = '<tr>'; 
					$html[] = 	'<td width="50" style="text-align: center;">' . ( $value['post_id'] ). '</td>';
					$html[] = 	'<td width="120" style="text-align: center;">' . ( $value['meta_value'] ). '</td>';
					$html[] = 	'<td><div style="overflow:hidden; width:300px;">' . ( $permalink ). '</div></td>';
					$html[] = 	'<td><input type="button" data-itemid="' . ( $value['post_id'] ) . '" data-permalink="' . ( $permalink ) . '" data-keyword="' . ( $value['meta_value'] ) . '" value="' . __('Add to Reporter', 'psp') . '" class="psp-this-select-fw psp-form-button psp-form-button-small psp-form-button-info"></td>';
					$html[] = '</tr>';
				}
			}else{
				$html[] = '<tr><td rowspan="3">' . __('No focus keywords for you posts', 'psp') . '</td></tr>';
			}
			
			$html[] = '<tbody>';
			
			$html[] = '</table>'; 
			
			// die(implode("\n", $html)); // debug
			die( json_encode(array(
				'status' => 'valid',
				'html'	=> implode("\n", $html)
			)) );
		}


		private function getSearchEngineUsed() {
			global $wpdb;
			
			$serpScoresSQL = "SELECT a.search_engine FROM " . ( $wpdb->prefix ) . "psp_serp_reporter as a WHERE 1=1 GROUP BY a.search_engine;";
			$ret = $wpdb->get_results( $serpScoresSQL, ARRAY_A );
			$__ret = array();
			if ($ret!==false && count($ret)>0) {
				foreach ($ret as $__k=>$__v) {
					$__ret[] = $__v['search_engine'];
				}
			}
			return $__ret;
		}
		
		public function setSearchEngine() {
			global $wpdb;
			
			$request = array(
				'search_engine' 	=> isset($_REQUEST['search_engine']) ? trim($_REQUEST['search_engine']) : '',
			);
			if ($request['search_engine']!='') {
				$_SESSION['psp_serp']['search_engine'] = $request['search_engine'];
			}
			
			// return for ajax
			die(json_encode( array(
				'status' => 'valid'
			)));
		}
		
		private function getKeywordsList() {
			global $wpdb;
			
			$serpScoresSQL = "SELECT a.id, a.focus_keyword as info FROM " . ( $wpdb->prefix ) . "psp_serp_reporter as a WHERE 1=1 ";

			if (isset($_SESSION['psp_serp']['search_engine']) && !empty($_SESSION['psp_serp']['search_engine'])
			&& $_SESSION['psp_serp']['search_engine']!='--all--') {
				$serpScoresSQL = str_replace("1=1 ", " 1=1 and a.search_engine='".$_SESSION['psp_serp']['search_engine']."' ", $serpScoresSQL);
			}
			$serpScoresSQL .= " GROUP BY a.focus_keyword;";
			$ret = $wpdb->get_results( $serpScoresSQL, ARRAY_A );
			return $ret;
		}
		
		private function getUrlsList() {
			global $wpdb;
			
			$serpScoresSQL = "SELECT a.id, a.url as info FROM " . ( $wpdb->prefix ) . "psp_serp_reporter as a WHERE 1=1 ";

			if (isset($_SESSION['psp_serp']['search_engine']) && !empty($_SESSION['psp_serp']['search_engine'])
			&& $_SESSION['psp_serp']['search_engine']!='--all--') {
				$serpScoresSQL = str_replace("1=1 ", " 1=1 and a.search_engine='".$_SESSION['psp_serp']['search_engine']."' ", $serpScoresSQL);
			}
			$serpScoresSQL .= " GROUP BY a.url;";
			$ret = $wpdb->get_results( $serpScoresSQL, ARRAY_A );
			return $ret;
		}
		
		private function prepareForInList($v) {
			return "'".$v."'";
		}

		public function cron_add_custom( $schedules ) {
			// Adds to the existing schedules.
			$schedules['daily'] = array(
				'interval' => 86400, //that's how many seconds are in 1 day, for the unix timestamp
				'display' => __('Once Daily', 'psp')
			);
			return $schedules;
		}

		private function ajax_list_table_rows() {
			return pspAjaxListTable::getInstance( $this->the_plugin )->list_table_rows( 'return', array() );
		}


    	/**
     	 * Cronjobs methods
     	 */
		//check_reporter: this will check search engine rank for all rows in psp_serp_reporter
		public function check_reporter() {
			@ini_set('max_execution_time', 0);
			@set_time_limit(0); // infinte

			$this->do_check_reporter();

			// return for ajax
			die(json_encode( array(
				'status' => 'valid',
				'msg' => ''
			)));
		}

		public function do_check_reporter() {
			global $wpdb;

			$__tasks = array();

			//retrives (url, keyword) pairs which have common keywords!
			$sql = "SELECT a.id, a.focus_keyword, a.search_engine, COUNT(a.id) AS nb FROM " . ( $wpdb->prefix ) . "psp_serp_reporter as a WHERE 1=1 and a.publish='Y' GROUP BY a.focus_keyword, a.search_engine HAVING nb>1 ORDER BY a.id ASC;";
			$res = $wpdb->get_results( $sql, ARRAY_A );

			// exit if no tasks to be run
			if(count($res) > 0){
				$__tasks[0] = $res;
			}
			
			//retrives (url, keyword) pairs which don't have common keywords!
			$sql2 = "SELECT a.url, a.id, a.focus_keyword, a.search_engine, COUNT(a.id) AS nb FROM " . ( $wpdb->prefix ) . "psp_serp_reporter as a WHERE 1=1 and a.publish='Y' GROUP BY a.focus_keyword, a.search_engine HAVING nb<=1 ORDER BY a.id ASC;";
			$res2 = $wpdb->get_results( $sql2, ARRAY_A );

			// exit if no tasks to be run
			if(count($res2) > 0){
				$__tasks[1] = $res2;
			}

			require_once( $this->the_plugin->cfg['paths']['scripts_dir_path'] . '/serp/serp.api.class.php' );
			$serp = pspSERPCheck::getInstance();

			//var_dump('<pre>', $__tasks , '</pre>'); echo __FILE__ . ":" . __LINE__;die . PHP_EOL;
			// loop tasks
			foreach ($__tasks as $__k => $__v) {
				foreach ($__v as $key => $value) {
					if ($__k==0) { //use cache!

						$sql_url = "SELECT a.id, a.focus_keyword, a.url, a.search_engine FROM " . $wpdb->prefix . "psp_serp_reporter as a WHERE 1=1 and a.publish='Y' and a.focus_keyword=%s and a.search_engine=%s ORDER BY a.id ASC;";
						$sql_url = $wpdb->prepare( $sql_url, $value['focus_keyword'], $value['search_engine'] );
						//var_dump('<pre>',$sql_url ,'</pre>'); 
						$res_url = $wpdb->get_results( $sql_url, ARRAY_A );

						if(count($res_url) > 0){
							foreach ($res_url as $ku=>$vu) {
								$__spParams = array(
									'engine'		=> $value['search_engine'],
									'keyword'		=> $value['focus_keyword'],
									'link'			=> $vu['url'],
									'dopause'		=> $this->serp_sleep
								);
								$googleScoreInfo = $serp->__get_serp_score( $__spParams );

								if ($googleScoreInfo===false || ( isset($googleScoreInfo['status'])
									&& $googleScoreInfo['status']=='invalid' )) {

									$this->googleAccessStatus( $vu['id'], array(
										'status'	=> 'invalid',
										'msg'		=> $googleScoreInfo['msg']
									) );
								} else {
									// add to DB or update if is from new day
									$this->addToReportDB( $googleScoreInfo, $value['search_engine'] );
								}
							}
						}
					} else {
						$__spParams = array(
							'engine'		=> $value['search_engine'],
							'keyword'		=> $value['focus_keyword'],
							'link'			=> $value['url'],
							'dopause'		=> $this->serp_sleep
						);
						$googleScoreInfo = $serp->__get_serp_score( $__spParams );
						
						if ($googleScoreInfo===false || ( isset($googleScoreInfo['status'])
							&& $googleScoreInfo['status']=='invalid' )) {

							$this->googleAccessStatus( $value['id'], array(
								'status'	=> 'invalid',
								'msg'		=> $googleScoreInfo['msg']
							) );
						} else {
							// add to DB or update if is from new day
							$this->addToReportDB( $googleScoreInfo, $value['search_engine'] );
						}
					}
				}
			}

			//send email!
			$this->cron_reporter_email();
		}
		
		//cron_reporter_email: this will send and email with ranks!
		public function cron_reporter_email() {
			global $wpdb;
			
			// select from DB
			$myQuery = "SELECT a.* FROM " . ( $wpdb->prefix . "psp_serp_reporter" ) . " as a WHERE 1=1 ";
			$myQuery .= " and a.publish = 'Y' ";
		    $myQuery .= " and a.position != a.position_prev ";
			$result_query = $myQuery;
		    $result_query .= " ORDER BY a.focus_keyword DESC;";

		    $query_res = $wpdb->get_results( $result_query, ARRAY_A);
		    
		    $items = array(); $pages = array();
		    foreach ($query_res as $key => $myrow){
		    	//if( $opt["custom_table"] == 'psp_serp_reporter' ) {
		    		$pages[$myrow['id']] = array(
			    		'id' 			=> $myrow['id'],
			    		'focus_keyword' => $myrow['focus_keyword'],
			    		'url' 			=> $myrow['url'],
			    		'position' 		=> $myrow['position'],
			    		'position_prev'	=> $myrow['position_prev'],
			    		'position_worst'=> $myrow['position_worst'],
			    		'position_best'	=> $myrow['position_best'],
			    		'visits' 		=> $myrow['visits'],
			    		'created' 		=> $myrow['created']
		    		);
		    	//}
		    }
		    $items = $pages;

		    $items_nr = 0;
		    $items_nr = $wpdb->get_var( str_replace("a.*", "count(a.id) as nbRow", $myQuery) );
			//var_dump('<pre>', $items_nr , '</pre>'); echo __FILE__ . ":" . __LINE__;die . PHP_EOL;

		    if ($items_nr<=0) {
		    	return false;
		    }


			// get the email template
			ob_start();
			require_once( $this->the_plugin->cfg['paths']["design_dir_path"] . '/serp_email.html' );
			$output = ob_get_contents();
			ob_end_clean();

			
			//html body - rows
			foreach ($items as $post){
				$html[] = '<tr data-itemid="' . ( $post['id'] ) . '">';

				$rank_data = $post;

				$html[] = '<td style="text-align: left;">';
				$html[] = '' . ( $post['focus_keyword'] ) . '';
				$html[] = '</td>';
				
				$html[] = '<td style="text-align: left;">';
				$html[] = '' . ( $post['url'] ) . '';
				$html[] = '</td>';

				$html[] = '<td style="text-align: left;">';
				if( isset($rank_data) && is_array($rank_data) && count($rank_data) > 0 ){
					// get best rank
					$best_pos = (int) $post['position_best'];

					// get worst
					$worst_pos = (int) $post['position_worst'];

					// current rank
					$current_pos = (int) $rank_data['position'];

					// previous rank
					$previous_pos = (int) $rank_data['position_prev'];

					//direction icon!
					$icon = 'icon_same.png';
					if( $current_pos > $previous_pos ){
						$icon = 'icon_down.png';
					}
					if( $current_pos < $previous_pos ){
						$icon = 'icon_up.png';
					}

					$__notInTop100 = __('Not in top 100', 'psp');
					$__icon_not100 = '<img src="' . ($this->the_plugin->cfg['paths']['plugin_dir_url']) . 'modules/serp/assets/icon_not100.png" width="" height="" title="' . $__notInTop100 . '">';

					$__icon = '<img src="' . ($this->the_plugin->cfg['paths']['plugin_dir_url']) . 'modules/serp/assets/' . ($icon) . '" width="" height="">';
					$__iconExtra = '';
					if (preg_match("/up/i", $icon)) {
						$__iconExtra .= '('.($previous_pos==999 ? '~' : '').'&#43;' . ( $previous_pos==999 ? (int) (100 - $current_pos) : (int) ($previous_pos - $current_pos) ) . ')';
					}
					else if(preg_match("/down/i", $icon)) {
						$__iconExtra .= '('.($current_pos==999 ? '~' : '').'&minus;' . ($current_pos==999 ? (int) (100 - $previous_pos) : (int) ($current_pos - $previous_pos) ) . ')';
					}
					$__icon .= $__iconExtra;
						
					$html[] = '<div style="position: relative; margin: -8px -10px 0px -10px; width: 100%;">';
					$html[] = 	'<table style="width: 200px; position: absolute; top: -14px; left: 0px; height: 43px; font-weight: bold;">';
					$html[] = 		'<tbody>';
					$html[] = 			'<tr>';
					$html[] = 					'<td width="90" align="center">' . ( $current_pos==999? $__icon_not100 . '&nbsp;&nbsp;' . $__iconExtra : '#'.$current_pos . '&nbsp;&nbsp;' . $__icon ) . '</td>';
					$html[] = 					'<td width="90" align="center">' . ( $previous_pos==999 ? $__icon_not100 : '#'.$previous_pos ) . '</td>';
					$html[] = 			'</tr>';
					$html[] = 		'</tbody>';
					$html[] = 	'</table>';
					$html[] = '</div>';
				}
				$html[] = '</td>';

				$html[] = '<td style="text-align: left;">';
				$html[] = '' . ( $post['created'] ) . '';
				$html[] = '</td>';

				$html[] = '<td style="text-align: left;">';
				$html[] = '' . ( $post['visits'] ) . '';
				$html[] = '</td>';
				
				$html[] = '</tr>';
			} //end foreach
			
            $__html_res = implode("\n", $html);
            
            
			// start make the replacements 
			$output = str_replace("{website_name}", get_bloginfo('name'), $output);
			$output = str_replace("{plugin_name}", 'Premium SEO pack - Wordpress Plugin', $output);
			$output = str_replace("{website_address}", get_bloginfo('url'), $output);
			$output = str_replace("{serp_email_title}", __('SERP Keywords Ranking Changes', 'psp'), $output);
			
			$output = str_replace("{table_title}", __('Keywords with ranking positions changes on Google since the last rank check on', 'psp') . ' (' . ($items_nr) . ' ' . __('items', 'psp') . ')', $output);

			$output = str_replace("{Focus Keyword}", __('Focus Keyword', 'psp'), $output);
			$output = str_replace("{URL}", __('URL', 'psp'), $output);
			$output = str_replace("{Google Rank}", __('Google Rank', 'psp'), $output);
			$output = str_replace("{Current Rank}", __('Current Rank', 'psp'), $output);
			$output = str_replace("{Previous Rank}", __('Previous Rank', 'psp'), $output);
			$output = str_replace("{Start Date}", __('Start Date', 'psp'), $output);
			$output = str_replace("{Visits}", __('Visits', 'psp'), $output);

			$output = str_replace("{table_content}", $__html_res, $output);


            //send mail!
            if (isset($this->plugin_settings['cron_email']) && trim($this->plugin_settings['cron_email'])!='') {
            	//$subject = __('Alert | Keywords Ranking Changes | ', 'psp') . str_replace('http://', '', get_bloginfo('url'));
            	$subject = '[' . ( get_bloginfo('name') ) . ']' . __(' Alert | Keywords Ranking Changes | ', 'psp');
            	
            	$headers = array();
            	$headers[] = __('From: '.$this->the_plugin->details['plugin_name'].' SERP module | ', 'psp') . get_bloginfo('name') . " <" . get_bloginfo('admin_email') . ">";
            	$headers[] = "MIME-Version: 1.0";
		
            	add_filter( 'wp_mail_content_type', array($this, 'set_html_content_type') );
            	wp_mail(
            		$this->plugin_settings['cron_email'],
            		$subject,
            		$output,
            		$headers
            	);
            	// Reset content-type to avoid conflicts -- http://core.trac.wordpress.org/ticket/23578
				remove_filter( 'wp_mail_content_type', array($this, 'set_html_content_type') );
            }
		}
		
		public function set_html_content_type() {
			return 'text/html';
		}

		public function serp_cronjob_check_reporter( $pms, $return='die' ) {
			$ret = array('status' => 'failed');

			//$current_cron_status = $pms['status']; //'new'; //

			$this->do_check_reporter();

            $ret = array_merge($ret, array(
                'status'            => 'done',
            ));
            return $ret;
		}
    }
}

function pspSERP_cronReporter_event() {
	// Initialize the pspSERP class
	$pspSERP = new pspSERP();
	$pspSERP->check_reporter();
}

// Initialize the pspSERP class
//$pspSERP = new pspSERP();
$pspSERP = pspSERP::getInstance();