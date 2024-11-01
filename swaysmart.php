<?php
/*
Plugin Name: Swaysmart
Description: This plugin allows you to add swaysmart javascript in the header of your Wordpress site.
Version: 1.0.0
Author: Jason Gagner
Author URI: http://www.swaysmart.com
License: GPLv2 or later
*/

define('SWAY_PLUGIN_DIR',str_replace('\\','/',dirname(__FILE__)));
define('SWAY_SMART', '<script class="swaysmart-script" src="//www.swaysmart.com/assets/smartbox/smartbox.js" async="async" data-site-id="DHoxm_X2exyUJEsV3NQ3"></script>');

if (!class_exists('Swaysmart')) {
	class Swaysmart {
		function __construct() {
			add_action( 'init', array( &$this, 'init' ) );
			add_action( 'admin_init', array( &$this, 'admin_init' ) );
			add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
			add_action( 'wp_head', array( &$this, 'wp_head' ) );

			register_activation_hook(__FILE__, array($this, 'plugin_activate'));
			register_deactivation_hook(__FILE__, array($this, 'plugin_deactivate'));
		}

		function plugin_activate() {
			update_option( 'sway_insert_header', SWAY_SMART );
		}

		function plugin_deactivate() {
			update_option( 'sway_insert_header', '' );
		}

		function init() {
			error_log("init");
			load_plugin_textdomain( 'swaysmart', false, dirname( plugin_basename ( __FILE__ ) ).'/lang' );
		}

		function admin_init() {
			error_log("admin_init");

			register_setting( 'swaysmart', 'sway_insert_header', 'trim' );

			foreach (array('post','page') as $type)
			{
				add_meta_box('sway_all_post_meta', 'Insert Script to &lt;head&gt;', 'sway_meta_setup', $type, 'normal', 'high');
			}

			add_action('save_post','sway_post_meta_save');
		}

		function admin_menu() {
			$page = add_submenu_page( 'options-general.php', 'Swaysmart', 'Swaysmart', 'manage_options', __FILE__, array( &$this, 'sway_options_panel' ) );
		}

		function wp_head() {
			$meta = get_option( 'sway_insert_header', '' );
				if ( $meta != '' ) {
					echo $meta, "\n";
				}

			$sway_post_meta = get_post_meta( get_the_ID(), '_inpost_head_script' , TRUE );
				if ( $sway_post_meta != '' ) {
					echo $sway_post_meta['synth_header_script'], "\n";
				}
		}

		function fetch_rss_items( $num, $feed ) {
			include_once( ABSPATH . WPINC . '/feed.php' );
			$rss = fetch_feed( $feed );

			// Bail if feed doesn't work
			if ( !$rss || is_wp_error( $rss ) )
			return false;

			$rss_items = $rss->get_items( 0, $rss->get_item_quantity( $num ) );

			// If the feed was erroneous
			if ( !$rss_items ) {
				$md5 = md5( $feed );
				delete_transient( 'feed_' . $md5 );
				delete_transient( 'feed_mod_' . $md5 );
				$rss = fetch_feed( $feed );
				$rss_items = $rss->get_items( 0, $rss->get_item_quantity( $num ) );
			}

			return $rss_items;
		}


		function sway_options_panel() { ?>
			<div id="fb-root"></div>
			<div id="sway-wrap">
				<div class="wrap">
				<?php screen_icon(); ?>
					<h2>Swaysmart - Options</h2>
					<hr />
					<form name="doInsert" action="options.php" method="post">
						<?php settings_fields( 'swaysmart' ); ?>
						<h3 class="sway-labels" for="sway_insert_header">Scripts in header:</h3>
            <textarea rows="5" cols="57" id="insert_header" name="sway_insert_header"><?php echo esc_html( get_option( 'sway_insert_header' ) ); ?></textarea><br />
					These scripts will be added to the <code>&lt;head&gt;</code> section.
						<p class="submit">
							<input class="button button-primary" type="submit" name="Submit" value="Save Scripts" />
						</p>
					</form>
					</div>
				</div>
				</div>
				<?php
		}
	}

	function sway_meta_setup()
	{
		global $post;

		// using an underscore, prevents the meta variable
		// from showing up in the custom fields section
		$meta = get_post_meta($post->ID,'_inpost_head_script',TRUE);

		// instead of writing HTML here, lets do an include
		include(SWAY_PLUGIN_DIR . '/meta.php');

		// create a custom nonce for submit verification later
		echo '<input type="hidden" name="sway_post_meta_noncename" value="' . wp_create_nonce(__FILE__) . '" />';
	}

	function sway_post_meta_save($post_id)
	{
		// authentication checks

		error_log("adsgfasdfasdf");

		// make sure data came from our meta box
		if ( ! isset( $_POST['sway_post_meta_noncename'] )
			|| !wp_verify_nonce($_POST['sway_post_meta_noncename'],__FILE__)) return $post_id;

		// check user permissions
		if ($_POST['post_type'] == 'page')
		{
			if (!current_user_can('edit_page', $post_id)) return $post_id;
		}
		else
		{
			if (!current_user_can('edit_post', $post_id)) return $post_id;
		}

		$current_data = get_post_meta($post_id, '_inpost_head_script', TRUE);

		$new_data = $_POST['_inpost_head_script'];

		sway_post_meta_clean($new_data);

		if ($current_data)
		{
			if (is_null($new_data)) delete_post_meta($post_id,'_inpost_head_script');
			else update_post_meta($post_id,'_inpost_head_script',$new_data);
		}
		elseif (!is_null($new_data))
		{
			add_post_meta($post_id,'_inpost_head_script',$new_data,TRUE);
		}

		return $post_id;
	}

	function sway_post_meta_clean(&$arr)
	{
		if (is_array($arr))
		{
			foreach ($arr as $i => $v)
			{
				if (is_array($arr[$i]))
				{
					sway_post_meta_clean($arr[$i]);

					if (!count($arr[$i]))
					{
						unset($arr[$i]);
					}
				}
				else
				{
					if (trim($arr[$i]) == '')
					{
						unset($arr[$i]);
					}
				}
			}

			if (!count($arr))
			{
				$arr = NULL;
			}
		}
	}

	$swaysmart_scripts = new Swaysmart();
}
