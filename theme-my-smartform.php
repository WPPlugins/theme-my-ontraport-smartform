<?php
/**
 * Plugin Name: Theme My Ontraport Smartform
 * Plugin URI: http://www.itmooti.com/
 * Description: Custom Themes for Ontraport/Office Auto Pilot Smart Forms
 * Version: 1.2.11
 * Stable tag: 1.2.11
 * Author: ITMOOTI
 * Author URI: http://www.itmooti.com/
 */

class itmooti_oap_custom_theme
{
    private $options;
	private $url;
	private $plugin_links;
	
	public function __construct(){
		$this->plugin_links=(object)array("support_link"=>get_option("oap_custom_theme_plugin_link_support_link", ""), "license_link"=>get_option("oap_custom_theme_plugin_link_license_link", ""));
		register_activation_hook(__FILE__, array($this, 'plugin_activation'));
		add_action('plugin_scheduled_event', array($this, 'plugin_authentication'));
		register_deactivation_hook(__FILE__, array($this, 'plugin_deactivation'));
		add_action( 'admin_menu', array( $this, 'add_itmooti_oap_custom_theme' ) );
		add_action( 'admin_notices', array( $this, 'show_license_info' ) );
		add_shortcode( 'custom_form_style', array($this, 'itmooti_oap_custom_theme'));
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array($this, 'itmooti_plugin_action_link'));
		add_filter( 'plugin_row_meta', array($this, 'itmooti_plugin_meta_link'), 10, 2);
    }
	public function is_authenticated(){
		if(get_option("oap_custom_theme_plugin_authenticated", "no")=="yes")
			return true;
		else
			return false;
	}
	public function plugin_activation(){
		wp_schedule_event(time(), 'twicedaily', 'plugin_scheduled_event');
	}
	public function plugin_deactivation(){
		wp_clear_scheduled_hook('plugin_scheduled_event');
	}
	public function plugin_authentication(){
		$isSecure = false;
		if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
			$isSecure = true;
		}
		elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
			$isSecure = true;
		}
		$this->url=($isSecure ? 'http' : 'http')."://app.itmooti.com/wp-plugins/oap-utm/api.php";
		$request= "plugin_links";
		$postargs = "plugin=itmooti-oap-themes&request=".urlencode($request);
		$session = curl_init($this->url);
		curl_setopt ($session, CURLOPT_POST, true);
		curl_setopt ($session, CURLOPT_POSTFIELDS, $postargs);
		curl_setopt($session, CURLOPT_HEADER, false);
		curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($session, CURLOPT_CONNECTTIMEOUT ,3); 
		curl_setopt($session, CURLOPT_TIMEOUT, 3);
		$response = json_decode(curl_exec($session));
		curl_close($session);
		if(isset($response->status) && $response->status=="success"){
			update_option("oap_custom_theme_plugin_link_support_link", $response->message->support_link);
			update_option("oap_custom_theme_plugin_link_license_link", $response->message->license_link);
		}
		$license_key=get_option('oap_custom_theme_license_key', "");
		if(!empty($license_key)){
			$request= "verify";
			$postargs = "plugin=itmooti-oap-themes&domain=".urlencode($_SERVER['HTTP_HOST'])."&license_key=".urlencode($license_key)."&request=".urlencode($request);
			$session = curl_init($this->url);
			curl_setopt ($session, CURLOPT_POST, true);
			curl_setopt ($session, CURLOPT_POSTFIELDS, $postargs);
			curl_setopt($session, CURLOPT_HEADER, false);
			curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($session, CURLOPT_CONNECTTIMEOUT ,3); 
			curl_setopt($session, CURLOPT_TIMEOUT, 3);
			$response = json_decode(curl_exec($session));
			curl_close($session);
			if(isset($response->status) && $response->status=="success"){
				update_option("oap_custom_theme_plugin_authenticated", "yes");
				if(isset($response->message))
					update_option("itmooti-oap-themes_message", $response->message);
			}
			else if(isset($response->status) && $response->status=="error"){
				update_option("oap_custom_theme_plugin_authenticated", "no");
				if(isset($response->message))
					update_option("itmooti-oap-themes_message", $response->message);
			}
		}
		else{
			update_option("oap_custom_theme_plugin_authenticated", "no");
			update_option("itmooti-oap-themes_message", "Please enter valid license key");
		}
	}
	function itmooti_plugin_action_link( $links ) {
		return array_merge(
			array(
				'settings' => '<a href="options-general.php?page=itmooti-oap-admin">Settings</a>',
				'support_link' => '<a href="'.$this->plugin_links->support_link.'" target="_blank">Support</a>'
			),
			$links
		);
	}
	function itmooti_plugin_meta_link( $links, $file ) {
		$plugin = plugin_basename(__FILE__);
		if ( $file == $plugin ) {
			return array_merge(
				$links,
				array(
					'settings' => '<a href="options-general.php?page=itmooti-oap-admin">Settings</a>',
					'support_link' => '<a href="'.$this->plugin_links->support_link.'" target="_blank">Support</a>'
				)
			);
		}
		return $links;
	}
	 
	public function itmooti_oap_custom_theme( $atts ) {
		if($this->is_authenticated()){
			$atts = shortcode_atts( array(
				'theme' => 'Amber'
			), $atts );
			$atts["theme"]=strtolower($atts["theme"]);
			add_action( 'wp_enqueue_scripts', array($this, 'itmooti_oap_custom_js'));
			return '<script>var itmooti_oap_custom_theme_path="'.plugins_url('themes/'.$atts["theme"].'/', __FILE__).'";</script><script src="'.plugins_url('themes/'.$atts["theme"].'/js.js', __FILE__).'"></script><link href="'.plugins_url('themes/'.$atts["theme"].'/style.css', __FILE__).'" type="text/css" rel="stylesheet" />';
		}
		return "<!-- Wrong API Credentials -->";
	}
	
	function itmooti_oap_custom_js(){
		wp_enqueue_script('jquery');
	}

	public function show_license_info(){
		$license_key=get_option('oap_custom_theme_license_key', "");
		if(empty($license_key)){
			echo '<div class="updated">
        		<p><strong>Theme My Smartforms:</strong> How do I get License Key?<br />Please visit this URL <a target="_blank" href="'.$this->plugin_links->license_link.'">'.$this->plugin_links->license_link.'</a> to get a License Key .</p>
	    	</div>';
		}
		$message=get_option("itmooti-oap-themes_message", "");
		if($message!=""){
			echo '<div class="error">
        		<p><strong>Theme My Smartforms:</strong> '.$message.'</p>
	    	</div>';
		}
	}
	
	public function add_itmooti_oap_custom_theme(){
        // This page will be under "Settings"
        add_options_page(
            'Settings Admin', 
            'Theme My Smartforms', 
            'manage_options', 
            'itmooti-oap-admin', 
            array( $this, 'create_admin_page' )
        );
    }
	
	 public function create_admin_page(){
        if(isset($_POST["oap_custom_theme_license_key"])){
			add_option("oap_custom_theme_license_key", $_POST["oap_custom_theme_license_key"]) or update_option("oap_custom_theme_license_key", $_POST["oap_custom_theme_license_key"]);
			$this->plugin_authentication();
		}
		?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2>Theme My Smartforms Settings</h2>           
            <form method="post">
           	  	<h3>Plugin Credentials</h3>
                Provide Plugin Credentials below:
                <?php $license_key=get_option('oap_custom_theme_license_key', "");?>
                <table class="form-table">
                	<tr>
                    	<th scope="row">License Key</th>
                        <td><input type="text" name="oap_custom_theme_license_key" id="oap_custom_theme_license_key" value="<?php echo $license_key?>" /></td>
                   	</tr>
              	</table>
				<?php				
				if($this->is_authenticated()){
					echo "Successfully authenticated. You can use the Custom themes now.";
				}
				else{
					$message=get_option("itmooti-oap-themes_message", "");
					if($message!=""){
						echo $message;
					}
				}
				?>
                <h3>How to Use</h3>
                <p>Use shortcode <strong>[custom_form_style]</strong> in post or page content to include the Theme files.</p>
                <p>All themes shortcode are listed below</p>
                <?php
				$dir=plugin_dir_path(__FILE__)."themes/";
                foreach(scandir($dir) as $theme){
					if(!in_array($theme, array(".", ".."))){
						echo '<strong>[custom_form_style theme="'.$theme.'"]</strong><br />';
					}
				}
				?>
                <?php
                submit_button(); 
            ?>
            </form>
        </div>
        <?php
    }
}
$itmooti_oap_custom_theme=new itmooti_oap_custom_theme();