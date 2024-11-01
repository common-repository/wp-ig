<?php
/**
 * Registering & displaying dashboard interface
 * 
 * @package WP_IG/Classes
 * @since 0.1
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WP_IG_Dashboard{
	var $redirect_uri;
	var $prefix;
	var $client_id;
	var $client_secret;
	var $authorization_url;
	var $exchange_code;
	var $current_page;
	var $templates;
	protected $account;

	function __construct(){
		$this->prefix 				= 'wp_ig_';
		$this->client_id 			= get_option( "{$this->prefix}client_id" );
		$this->client_secret 		= get_option( "{$this->prefix}client_secret" );
		$this->redirect_uri 		= admin_url() . 'admin-ajax.php?action=wp_ig_redirect_uri';
		$this->redirect_uri_encoded = urlencode( $this->redirect_uri );
		$this->authorization_url 	= "https://api.instagram.com/oauth/authorize/?client_id={$this->client_id}&redirect_uri={$this->redirect_uri_encoded}&response_type=code";		
		$this->exchange_code 		= get_option( "{$this->prefix}exchange_code" );
		$this->current_page 		= new WP_IG_Current_Page;
		$this->templates 			= new WP_IG_Templates;
		$this->account 				= get_option( "{$this->prefix}account" );

		// If user is currently on wp_ig pages
		if( isset( $_GET['page'] ) && substr( $_GET['page'], 0, 5 ) == "wp_ig" ){
			// Saving form submission
			add_action( 'plugins_loaded', array( $this, 'saving' ) );
		}

		// Register dashboard menu
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_styles' ) );
		add_action( 'admin_menu', array( $this, 'register_pages' ) );

		// Processing auth-redirect data
		add_action( 'wp_ajax_wp_ig_redirect_uri', array( $this, 'page_redirect_callback' ) );
		add_action( 'wp_ajax_wp_ig_deauth_account', array( $this, 'page_deauth' ) );

		// Register custom taxonomy
		add_action( 'init', array( $this, 'register_taxonomy' ) );
	}
	
	/**
	 * Registering dashboard pages
	 * 
	 * @return void
	 */
	function register_pages(){
		add_menu_page( __( 'Instagram', 'wp-ig' ), __( 'Instagram', 'wp-ig' ), 'edit_others_posts', 'wp_ig', array( $this, 'page_main' ), 'dashicons-camera', 7 );

		// If the client ID and client secret has been saved, display edit update settings
		if( $this->client_id && 
			$this->client_secret && 
			$this->account &&
			$this->client_id != '' && 
			$this->client_secret != '' &&
			$this->account != ''
		){
			add_submenu_page( 'wp_ig', __( 'Import', 'wp-ig' ), __( 'Import', 'wp-ig' ), 'edit_others_posts', 'wp_ig_import', array( $this, 'page_import') );
			add_submenu_page( 'wp_ig', __( 'Delete', 'wp-ig' ), __( 'Delete', 'wp-ig' ), 'edit_others_posts', 'wp_ig_delete', array( $this, 'page_delete') );
		}

		if( $this->client_id && 
			$this->client_secret
		){
			add_submenu_page( 'wp_ig', __( 'Settings', 'wp-ig' ), __( 'Settings', 'wp-ig' ), 'edit_others_posts', 'wp_ig_settings', array( $this, 'page_settings') );			
		}
	}

	/**
	 * Register taxonomy
	 * 
	 * @return void
	 */
	function register_taxonomy(){
		$taxonomy = new WP_IG_Taxonomy;
		$taxonomy->register();
	}

	/**
	 * Initiating settings class as method
	 */
	function settings(){
		return new WP_IG_Settings;
	}

	/**
	 * Initiating api class as method
	 * 
	 */
	function api(){
		$api = new WP_IG_API( get_option( "{$this->prefix}access_token" ) );

		return $api;
	}

	/**
	 * Initiating import class as method
	 * 
	 * @return obj WP_IG_Import
	 */
	function import(){
		return new WP_IG_Import;
	}

	/**
	 * Initiating content class as method
	 * 
	 * @return obj WP_IG_Content
	 */
	function content(){
		return new WP_IG_Content;
	}

	/**
	 * Saving form submission
	 * 
	 * @return void
	 */
	function saving(){
		// Updating basic value
		if(	isset( $_POST['client_id'] ) && 
			isset( $_POST['client_secret'] ) && 
			isset( $_POST['_wpnonce'] ) && 
			wp_verify_nonce( $_POST['_wpnonce'], 'wp_ig_settings' ) 
		){
			$saving_client_id 		= update_option( "{$this->prefix}client_id", sanitize_text_field( $_POST['client_id'] ) );
			$saving_client_secret 	= update_option( "{$this->prefix}client_secret", sanitize_text_field( $_POST['client_secret'] ) );
		}

		// Updating advance value..
		if( isset( $_POST['wp_ig_post_category'] ) &&
			isset( $_POST['wp_ig_post_type'] ) &&
			isset( $_POST['wp_ig_sync'] ) &&
			isset( $_POST['_wpnonce'] ) && 
			wp_verify_nonce( $_POST['_wpnonce'], 'wp_ig_settings' ) ){

			$saving_post_type 		= update_option( "{$this->prefix}post_type", sanitize_text_field( $_POST['wp_ig_post_type'] ) );
			$saving_post_category 	= update_option( "{$this->prefix}post_category", intval( $_POST['wp_ig_post_category'] ) );
			$saving_sync 			= update_option( "{$this->prefix}sync", sanitize_text_field( $_POST['wp_ig_sync'] ) );
			
			// Saving prepend item preference
			if( isset( $_POST['wp_ig_prepend_photo_on_index'] ) ){
				$prepend_photo_on_index 	= update_option( "{$this->prefix}prepend_photo_on_index", 'yes' );
			} else {
				$prepend_photo_on_index 	= update_option( "{$this->prefix}prepend_photo_on_index", 'no' );
			}

			if( isset( $_POST['wp_ig_prepend_photo_on_single'] ) ){
				$prepend_photo_on_single 	= update_option( "{$this->prefix}prepend_photo_on_single", 'yes' );
			} else {
				$prepend_photo_on_single 	= update_option( "{$this->prefix}prepend_photo_on_single", 'no' );
			}

			if( isset( $_POST['wp_ig_prepend_video_on_index'] ) ){
				$prepend_video_on_index 	= update_option( "{$this->prefix}prepend_video_on_index", 'yes' );
			} else {
				$prepend_video_on_index 	= update_option( "{$this->prefix}prepend_video_on_index", 'no' );
			}

			if( isset( $_POST['wp_ig_prepend_video_on_single'] ) ){
				$prepend_video_on_single 	= update_option( "{$this->prefix}prepend_video_on_single", 'yes' );
			} else {
				$prepend_video_on_single 	= update_option( "{$this->prefix}prepend_video_on_single", 'no' );
			}


			if( $saving_client_id ){
				$this->client_id = sanitize_text_field( $_POST['client_id'] );
			}

			if( $saving_client_secret ){
				$this->client_secret = sanitize_text_field( $_POST['client_secret'] );
			}
		}

		// If client ID & secret doesn't exist
		if( isset( $_GET['page'] ) && $_GET['page'] != 'wp_ig' && ( !$this->client_id || !$this->client_secret || $this->client_id == '' || $this->client_secret == '' ) ){
			header( "Location: " . admin_url() . 'admin.php?page=wp_ig' );
			exit();
		}	

		// Updating client_id & client_secret
		$this->client_id 			= get_option( "{$this->prefix}client_id" );
		$this->client_secret 		= get_option( "{$this->prefix}client_secret" );			
	}

	/**
	 * Enqueue scripts & stylesheets
	 * 
	 * @return void
	 */
	function enqueue_scripts_styles( $hook ){
		if( $hook == 'toplevel_page_wp_ig' ){
			wp_enqueue_style( 'wp_ig_dashboard', WP_IG_URL . 'css/dashboard.css', false, '1.0.0' );
			wp_enqueue_script( 'wp_ig', WP_IG_URL . 'js/wp-ig.js', array( 'jquery' ), '1.0' );
		}
	}

	/**
	 * Print main / settings page of WP-IG
	 * 
	 * @return void
	 */
	function page_main(){
		?>
			<?php
				if( !$this->client_id || !$this->client_secret || $this->client_id == '' || $this->client_secret == '' ){
					// First time user: create client instruction + client ID + client secret
					include_once( WP_IG_DIR . '/pages/settings.php' );					
				} else {
					// Display profile
					include_once( WP_IG_DIR . '/pages/profile.php' );					

					// Connected: preview your account					
				}
			?>
		<?php
	}

	/**
	 * Print import page
	 * 
	 * @return void
	 */
	function page_import(){
		include_once( WP_IG_DIR . '/pages/import.php' );
	}

	/**
	 * Print delete page
	 * 
	 * @return void
	 */
	function page_delete(){
		include_once( WP_IG_DIR . '/pages/delete.php' );
	}

	/**
	 * Print settings page
	 * 
	 * @return void
	 */
	function page_settings(){
		include_once( WP_IG_DIR . '/pages/settings.php' );
	}

	/**
	 * Handling redirection of Instagram authentication process
	 * 
	 * @return void
	 */
	function page_redirect_callback(){
		if( isset( $_GET['code'] ) ){
			// Save the exchange code
			$save_exchange_code = update_option("{$this->prefix}exchange_code", esc_html( $_GET['code'] ) );

			// Request access token
			$args = array(
				'timeout' => 60,
				'body' => array(
					'client_id' => $this->client_id,
					'client_secret' => $this->client_secret,
					'grant_type' => 'authorization_code',
					'redirect_uri' => $this->redirect_uri,
					'code' => $this->exchange_code
				)
			);

			// Request access token to Instagram
			$request_access_token = wp_remote_post( "https://api.instagram.com/oauth/access_token", $args );

			if( isset( $request_access_token['body'] ) && $request_access_token['response']['code'] == '200' ){
				// Parse the body response
				$response = json_decode( $request_access_token['body'] );

				if( isset( $response->access_token ) ){
					// Save the access token
					$save_access_token = update_option( "{$this->prefix}access_token", esc_html( $response->access_token ) );

					// Save the user info
					$save_user = update_option( "{$this->prefix}account", $response->user );

					// Close the popup, then reload the parent page
					?>
						<script type="text/javascript">
						   opener.location.reload(true);
						   self.close();
						</script>
					<?php
				} else {
					_e( "Fail to connect to your Instagram Account. Please try again", "wp_ig" );
				}
			} else {
				_e( "We cannot connect to Instagram. Please close this popup window then try again.", "wp_ig" );
			}

			die();			
		} elseif( isset( $_GET['error'] ) && isset( $_GET['error_description'] ) ){
			// If user denied to give an access...
			echo $_GET['error_description'];

		} else {
			_e( "An error occurred. We cannot connect this site to your Instagram account. Please try again", "wp_ig" );
		}

		die();		
	}

	/**
	 * Deauth instagram account by deleting options value
	 * 
	 * @return void
	 */
	function page_deauth(){

		// Disconnecting...
		if( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'deauth_instagram_account' ) ){
			_e( "Disconnecting your instagram account...", "wp_ig" );

			// remove the data
			delete_option( "{$this->prefix}exchange_code" );
			delete_option( "{$this->prefix}access_token" );
			delete_option( "{$this->prefix}account" );
		} else {			
			_e( "You are not authorized to disconnect the instagram account from this site...", "wp_ig" );
		}

		// Close the popup, then reload the parent page
		?>
			<script type="text/javascript">
			   opener.location.reload(true);
			   self.close();
			</script>
		<?php		
		die();
	}
}
new WP_IG_Dashboard;