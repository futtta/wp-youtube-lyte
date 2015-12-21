<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$plugin_dir = basename(dirname(__FILE__)).'/languages';
load_plugin_textdomain( 'wp-youtube-lyte', false, $plugin_dir );

add_action('admin_menu', 'lyte_create_menu');

if (get_option('lyte_emptycache','0')==="1") {
	$emptycache=lyte_rm_cache();
	if ($emptycache==="OK") {
		add_action('admin_notices', 'lyte_cacheclear_ok_notice');
	} else {
		add_action('admin_notices', 'lyte_cacheclear_fail_notice');
	}
	update_option('lyte_emptycache','0');
}

function lyte_cacheclear_ok_notice() {
	echo '<div class="updated"><p>';
	_e('Your WP YouTube Lyte cache has been succesfully cleared.', 'wp-youtube-lyte' );
	echo '</p></div>';
}

function lyte_cacheclear_fail_notice() {
	echo '<div class="error"><p>';
	_e('There was a problem, the WP YouTube Lyte cache could not be cleared.', 'wp-youtube-lyte' );
	echo '</p></div>';
}

function lyte_create_menu() {
        $hook=add_options_page( 'WP YouTube Lyte settings', 'WP YouTube Lyte', 'manage_options', 'lyte_settings_page', 'lyte_settings_page');
        add_action( 'admin_init', 'register_lyte_settings' );
        add_action( 'admin_print_scripts-'.$hook, 'lyte_admin_scripts' );
        add_action( 'admin_print_styles-'.$hook, 'lyte_admin_styles' );
}

function register_lyte_settings() {
	register_setting( 'lyte-settings-group', 'lyte_show_links' );
	register_setting( 'lyte-settings-group', 'lyte_size' );
	register_setting( 'lyte-settings-group', 'lyte_hidef' );
	register_setting( 'lyte-settings-group', 'lyte_position' );
	register_setting( 'lyte-settings-group', 'lyte_notification' );
	register_setting( 'lyte-settings-group', 'lyte_microdata' );
	register_setting( 'lyte-settings-group', 'lyte_emptycache' );
	register_setting( 'lyte-settings-group', 'lyte_greedy' );
	register_setting( 'lyte-settings-group', 'lyte_yt_api_key' );
}

function lyte_admin_scripts() {
	wp_enqueue_script('jqzrssfeed', plugins_url('/external/jquery.zrssfeed.min.js', __FILE__), array('jquery'),null,true);
	wp_enqueue_script('jqcookie', plugins_url('/external/jquery.cookie.min.js', __FILE__), array('jquery'),null,true);
}

function lyte_admin_styles() {
	wp_enqueue_style('zrssfeed', plugins_url('/external/jquery.zrssfeed.css', __FILE__));
}

function lyte_admin_nag_apikey() {
	echo "<div class=\"update-nag\">";
	_e('For WP YouTube Lyte to function optimally, you should enter an YouTube API key ', 'wp-youtube-lyte');
	echo " <a href=\"options-general.php?page=lyte_settings_page\">";
	_e('in the settings screen.','wp-youtube-lyte');
	echo "</a>.</div>";
}

$lyte_yt_api_key=get_option('lyte_yt_api_key','');
$lyte_yt_api_key=apply_filters('lyte_filter_yt_api_key', $lyte_yt_api_key);
if (empty($lyte_yt_api_key)) {
	add_action('admin_notices', 'lyte_admin_nag_apikey');
	}

function lyte_admin_api_error(){
	$yt_error=json_decode(get_option('lyte_api_error'),1);
	echo '<div class="error"><p>';
	_e('WP YouTube Lyte got the following error back from the YouTube API: ','wp-youtube-lyte');
	echo "<strong>".$yt_error["reason"]."</strong>";
	echo " (".date("r",$yt_error["timestamp"]).").";
	echo '</a>.</p></div>';
	update_option('lyte_api_error','');
}

if (get_option('lyte_api_error','')!=='') {
	add_action('admin_notices', 'lyte_admin_api_error');
	}

function lyte_settings_page() {
	global $pSize, $pSizeOrder;
?>
<div class="wrap">
<h2><?php _e("WP YouTube Lyte Settings","wp-youtube-lyte") ?></h2>
<div style="float:left;width:70%;">
<form method="post" action="options.php">
    <?php settings_fields( 'lyte-settings-group' ); ?>
    <table class="form-table">
	<input type="hidden" name="lyte_notification" value="<?php echo get_option('lyte_notification','0'); ?>" />
		<tr valign="top">
			<th scope="row"><?php _e("Your YouTube API key.","wp-youtube-lyte") ?></th>
			<td>
			<?php // only show api key input field if there's no result from filter
			$filter_key=apply_filters('lyte_filter_yt_api_key','');
			if (empty($filter_key)) { ?>
				<fieldset>
					<legend class="screen-reader-text"><span><?php _e("Please enter your YouTube API key.","wp-youtube-lyte") ?></span></legend>
					<label title="<?php _e('API key','wp-youtube-lyte'); ?>"><input type="text" size="28" name="lyte_yt_api_key" id="lyte_yt_api_key" value="<?php echo get_option('lyte_yt_api_key',''); ?>"><span id="check_api_key" class="submit button-secondary" style="margin:0px 5px;"><?php _e("Test Key"); ?></span></label><br />
					<div id="lyte_key_check_output" style="display:none;margin-bottom:5px;background-color:white;border-left:solid;border-width:4px;border-color:#2ea2cc;padding:5px 5px 5px 15px;"></div>
					<?php _e("WP YouTube Lyte uses YouTube's API to fetch information on each video. For your site to use that API, you will have to <a href=\"https://console.developers.google.com/project/\" target=\"_blank\">register your site as a new application</a>, enable the YouTube API for it and get a server key and fill it out here.","wp-youtube-lyte"); ?>
				</fieldset>
			<?php } else { ?>
				<?php _e("Great, your YouTube API key has been taken care of!","wp-youtube-lyte"); ?>
			<?php } ?>
			</td>
	    </tr>
        <tr valign="top">
            <th scope="row"><?php _e("Player size","wp-youtube-lyte") ?>:</th>
            <td>
                <fieldset><legend class="screen-reader-text"><span><?php _e("Player size","wp-youtube-lyte") ?></span></legend>
		<?php
			if (is_bool(get_option('lyte_size'))) { $sel = (int) $pDefault; } else { $sel= (int) get_option('lyte_size'); }
			foreach (array("169","43") as $f) {
				foreach ($pSizeOrder[$f] as $i) {
					$pS=$pSize[$i];
					if ($pS['a']===true) {
						?>
						<label title="<?php echo $pS['w']."X".$pS['h']; ?>"><input type="radio" name="lyte_size" class="l_size" value="<?php echo $i."\"";if($i===$sel) echo " checked";echo " /> ".$pS['w']."X".$pS['h']." (".$pS['t'];?>)</label><br />
						<?php
					}
				}
				?><br /><?php
			}
		?>
                </fieldset>
             </td>
         </tr>
        <tr valign="top">
			<th scope="row"><?php _e("Add links below the embedded videos?","wp-youtube-lyte") ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text"><span><?php _e("Show links?","wp-youtube-lyte") ?></span></legend>
					<label title="<?php _e('Show YouTube-link','wp-youtube-lyte');?>"><input type="radio" name="lyte_show_links" value="1" <?php if (get_option('lyte_show_links')==="1") echo "checked" ?> /><?php _e(" Add YouTube-link.","wp-youtube-lyte") ?></label><br />
					<label title="<?php _e('Show YouTube and Ease YouTube link','wp-youtube-lyte');?>"><input type="radio" name="lyte_show_links" value="2" <?php if (get_option('lyte_show_links')==="2") echo "checked" ?> /><?php _e(" Add both a YouTube and an <a href=\"http://icant.co.uk/easy-youtube/docs/index.html\" target=\"_blank\">Easy YouTube</a>-link.","wp-youtube-lyte") ?></label><br />
					<label title="<?php _e('Don\'t include links.','wp-youtube-lyte');?>"><input type="radio" name="lyte_show_links" value="0" <?php if ((get_option('lyte_show_links')!=="1") && (get_option('lyte_show_links')!=="2")) echo "checked" ?> /><?php _e(" Don't add any links.","wp-youtube-lyte") ?></label>
				</fieldset>
			</td>
         </tr>
         <tr valign="top">
                <th scope="row"><?php _e("Player position:","wp-youtube-lyte") ?></th>
                <td>
                        <fieldset>
                                <legend class="screen-reader-text"><span><?php _e("Left, center or right?","wp-youtube-lyte"); ?></span></legend>
                                <label title="<?php _e('Left','wp-youtube-lyte');?>"><input type="radio" name="lyte_position" value="0" <?php if (get_option('lyte_position','0')==="0") echo "checked" ?> /><?php _e("Left","wp-youtube-lyte") ?></label><br />
				<label title="<?php _e('Center','wp-youtube-lyte');?>"><input type="radio" name="lyte_position" value="1" <?php if (get_option('lyte_position','0')==="1") echo "checked" ?> /><?php _e("Center","wp-youtube-lyte") ?></label>
                        </fieldset>
                </td>
         </tr>
         <tr valign="top">
                <th scope="row"><?php _e("Try to force HD (experimental)?","wp-youtube-lyte") ?></th>
                <td>
                        <fieldset>
                                <legend class="screen-reader-text"><span><?php _e("HD or not?","wp-youtube-lyte"); ?></span></legend>
                                <label title="<?php _e('Enable HD?','wp-youtube-lyte');?>"><input type="radio" name="lyte_hidef" value="1" <?php if (get_option('lyte_hidef','0')==="1") echo "checked" ?> /><?php _e("Enable HD","wp-youtube-lyte") ?></label><br />
                                <label title="<?php _e('Don\'t enable HD playback','wp-youtube-lyte');?>"><input type="radio" name="lyte_hidef" value="0" <?php if (get_option('lyte_hidef','0')!=="1") echo "checked" ?> /><?php _e("No HD (default)","wp-youtube-lyte") ?></label>
                        </fieldset>
                </td>
	</tr>
         <tr valign="top">
                <th scope="row"><?php _e("Add microdata?","wp-youtube-lyte") ?></th>
                <td>
                        <fieldset>
                                <legend class="screen-reader-text"><span><?php _e("Add video microdata to the HTML?","wp-youtube-lyte"); ?></span></legend>
                                <label title="<?php _e('Sure, add microdata!','wp-youtube-lyte');?>"><input type="radio" name="lyte_microdata" value="1" <?php if (get_option('lyte_microdata','1')==="1") echo "checked" ?> /><?php _e("Yes (default)","wp-youtube-lyte") ?></label><br />
                                <label title="<?php _e('No microdata in my HTML please.','wp-youtube-lyte');?>"><input type="radio" name="lyte_microdata" value="0" <?php if (get_option('lyte_microdata','1')!=="1") echo "checked" ?> /><?php _e("No microdata, thanks.","wp-youtube-lyte") ?></label>
                        </fieldset>
                </td>
        </tr>
        <tr valign="top">
                <th scope="row"><?php _e("Also act on normal YouTube links?","wp-youtube-lyte") ?></th>
                <td>
                        <fieldset>
                                <legend class="screen-reader-text"><span><?php _e("Also act on normal YouTube links?","wp-youtube-lyte") ?></span></legend>
                                <label title="<?php _e('That would be great!','wp-youtube-lyte');?>"><input type="radio" name="lyte_greedy" value="1" <?php if (get_option('lyte_greedy','1')==="1") echo "checked" ?> /><?php _e("Yes (default)","wp-youtube-lyte") ?></label><br />
                                <label title="<?php _e('No, I\'ll stick to httpv or shortcodes.','wp-youtube-lyte');?>"><input type="radio" name="lyte_greedy" value="0" <?php if (get_option('lyte_greedy','1')!=="1") echo "checked" ?> /><?php _e("No thanks.","wp-youtube-lyte") ?></label>
                        </fieldset>
                </td>
        </tr>
	<tr valign="top">
                <th scope="row"><?php _e("Empty WP YouTube Lyte's cache","wp-youtube-lyte") ?></th>
                <td>
                        <fieldset>
                                <legend class="screen-reader-text"><span>Remove WP YouTube Lyte's cache</span></legend>
                                <input type="checkbox" name="lyte_emptycache" value="1" />
                        </fieldset>
                </td>
        </tr>
    </table>
    
    <p class="submit">
    <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
    </p>

</form>
</div>
<div style="float:right;width:30%" id="lyte_admin_feed">
        <div style="margin:0px 15px 15px 15px;font-size:larger;"><?php _e("Need help? <a href='https://wordpress.org/plugins/wp-youtube-lyte/faq/' target='_blank'>Check out the FAQ</a> or post your question on <a href='http://wordpress.org/support/plugin/wp-youtube-lyte' target='_blank'>the support-forum</a>."); ?></div>
	<div style="margin:0px 15px 15px 15px;font-size:larger;"><a href="<?php echo network_admin_url(); ?>plugin-install.php?tab=search&type=author&s=futtta"><?php _e("Happy with WP YouTube Lyte? Try my other plugins!"); ?></a></div>
        <div style="margin-left:10px;margin-top:-5px;">
                <h3>
                        <?php _e("futtta about","wp-youtube-lyte") ?>
                        <select id="feed_dropdown" >
                                <option value="1"><?php _e("WP YouTube Lyte","wp-youtube-lyte") ?></option>
                                <option value="2"><?php _e("WordPress","wp-youtube-lyte") ?></option>
                                <option value="3"><?php _e("Web Technology","wp-youtube-lyte") ?></option>
                        </select>
                </h3>
                <div id="futtta_feed"></div>
		<div style="float:right;margin:50px 15px;"><a href="http://blog.futtta.be/2013/10/21/do-not-donate-to-me/" target="_blank"><img width="100px" height="85px" src="<?php echo content_url(); ?>/plugins/wp-youtube-lyte/external/do_not_donate_smallest.png" title="<?php _e("Do not donate for this plugin!"); ?>"></a></div>
        </div>
</div>

<script type="text/javascript">
	var feed = new Array;
	feed[1]="http://feeds.feedburner.com/futtta_wp-youtube-lyte";
	feed[2]="http://feeds.feedburner.com/futtta_wordpress";
	feed[3]="http://feeds.feedburner.com/futtta_webtech";
	cookiename="wp-youtube-lyte_feed";

        jQuery(document).ready(function() {
		jQuery( "#check_api_key" ).click(function() {
			jQuery("#lyte_key_check_output").show();
			jQuery("#lyte_key_check_output").append('<p><?php _e("Checking your key ..."); ?></p>');

			lyte_yt_api_key=jQuery("input#lyte_yt_api_key").val();			
			if ((lyte_yt_api_key.length>9) &&(lyte_yt_api_key.length<99)) {
				var data = {
					'action': 'lyte_check_yt_api_key',
					'lyte_nonce': '<?php echo wp_create_nonce( "lyte_check_api_key" );?>',
					'lyte_yt_api_key': jQuery("input#lyte_yt_api_key").val()
				};

				jQuery.post(ajaxurl, data, function(response) {
					jQuery("#lyte_key_check_output").append('<p>'+response+'</p>');
				});
			} else {
				jQuery("#lyte_key_check_output").append('<p><?php _e("That does not seem to be a correct API key!"); ?></p>');
			}		
		})

		jQuery("#feed_dropdown").change(function() { show_feed(jQuery("#feed_dropdown").val()) });

		feedid=jQuery.cookie(cookiename);
		if(typeof(feedid) !== "string") feedid=1;

		show_feed(feedid);
		})

	function show_feed(id) {
  		jQuery('#futtta_feed').rssfeed(feed[id], {
			<?php if ( is_ssl() ) echo "ssl: true,"; ?>
    			limit: 4,
			date: true,
			header: false
  		});
		jQuery("#feed_dropdown").val(id);
		jQuery.cookie(cookiename,id,{ expires: 365 });
	}
</script>

</div>
<?php }

// ajax receiver for YT API key check
add_action( 'wp_ajax_lyte_check_yt_api_key', 'lyte_check_yt_api_key_callback' );
function lyte_check_yt_api_key_callback() {
	check_ajax_referer( "lyte_check_api_key", 'lyte_nonce' );
	$api_key = strip_tags($_POST['lyte_yt_api_key']);

	// use random video to make sure a cache is not spoiling things
	$vidToCheck=array("jLOnUWJTCG0","ZmnZHudtzXg","2_7oQcAkyl8","nOvv80wkSgI","pBCt5nfsZ30","KHw7gdJ14uQ","qJ_PMvjmC6M","DVwHCGAr_OE","LtOGa5M8AuU","VHO9uZX9FNU");
	$randVidIndex=array_rand($vidToCheck);
	
	$api_response = lyte_get_YT_resp($vidToCheck[$randVidIndex],false,"",$api_key);
	
	if (is_array($api_response)) {
		if (!empty($api_response["title"])) {
			_e("API seems OK, you can Save Changes below now.");
		} else if (!empty($api_response["reason"])) {
			_e("API key not OK, your key seems to ");
			switch ($api_response["reason"]) {
				case "keyInvalid":
					_e("be invalid.",'wp-youtube-lyte');
					break;
				case "ipRefererBlocked":
					_e("be valid, but restricted to an IP-address which is not your server's.",'wp-youtube-lyte');
					_e("Try changing the allowed IP for your API key to include this one: ",'wp-youtube-lyte');
					echo $_SERVER["SERVER_ADDR"];
					break;
				case "keyExpired":
					_e("have expired, please check in the Google Developer Console.",'wp-youtube-lyte');
					break;
				case "limitExceeded":
				case "quotaExceeded":
				case "rateLimitExceeded":
				case "userRateLimitExceeded":
					_e("be correct, but seems to have exceeded the number of requests that can be made with it.",'wp-youtube-lyte');
					break;
				case "videoNotFound":
					_e("probably work, but as the video with id ",'wp-youtube-lyte');
					echo $vidToCheck[$randVidIndex];
					_e(" was not found we cannot be sure, please try again.",'wp-youtube-lyte');
					break;
				default:
					_e("be faulty, with YouTube API returning reason: ",'wp-youtube-lyte');
					echo $api_response["reason"];
				}
		}
	} else {
		_e("Something went wrong, WP YouTube Lyte might not have been able to retrieve information from the YouTube API, got error: ",'wp-youtube-lyte');
		print_r($api_response);
	}
	wp_die();
}
?>
