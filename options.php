<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require("lytePartners.php");

$plugin_dir = basename(dirname(__FILE__)).'/languages';
load_plugin_textdomain( 'wp-youtube-lyte', false, $plugin_dir );

add_action('admin_menu', 'lyte_create_menu');

if (get_option('lyte_emptycache','0')==="1") {
    $emptycache=lyte_rm_cache();
    update_option('lyte_emptycache','0');
    if ($emptycache==="OK") {
        add_action('admin_notices', 'lyte_cacheclear_ok_notice');
    } elseif ($emptycache==="PART") {
        add_action('admin_notices', 'lyte_cacheclear_part_notice');
        update_option('lyte_emptycache','1'); // to ensure cache-purging continues
    } else {
        add_action('admin_notices', 'lyte_cacheclear_fail_notice');
    }
}

function lyte_cacheclear_ok_notice() {
    echo '<div class="updated"><p>';
    _e('Your WP YouTube Lyte cache has been succesfully cleared.', 'wp-youtube-lyte' );
    echo '</p></div>';
}

function lyte_cacheclear_part_notice() {
    echo '<div class="error"><p>';
    _e('WP YouTube Lyte cache was partially cleared, refresh this page to continue purging.', 'wp-youtube-lyte' );
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
    register_setting( 'lyte-settings-group', 'lyte_local_thumb' );
    register_setting( 'lyte-settings-group', 'lyte_disclaimer' );
}

function lyte_admin_scripts() {
    wp_enqueue_script('jqcookie', plugins_url('/external/jquery.cookie.min.js', __FILE__), array('jquery'),null,true);
        wp_enqueue_script('unslider', plugins_url('/external/unslider-min.js', __FILE__), array('jquery'),null,true);
    }

function lyte_admin_styles() {
        wp_enqueue_style('unslider', plugins_url('/external/unslider.css', __FILE__));
        wp_enqueue_style('unslider-dots', plugins_url('/external/unslider-dots.css', __FILE__));
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
<style>
/* rss block */
#futtta_feed ul{list-style:outside;}
#futtta_feed {font-size:medium; margin:0px 20px;} 

/* banner + unslider */
.lyte_banner {
    margin: 0 38px;
    padding-bottom: 5px;
}
.lyte_banner ul li {
    font-size:medium;
    text-align:center;
}
.unslider {
    position:relative;
}
.unslider-arrow {
    display: block;
    left: unset;
    margin-top: -35px;
    margin-left: 7px;
    margin-right: 7px;
    border-radius: 32px;
    background: rgba(0, 0, 0, 0.10) no-repeat 50% 50%;
    color: rgba(255, 255, 255, 0.8);
    font: normal 20px/1 dashicons;
    speak: none;
    padding: 3px 2px 3px 4px;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}
.unslider-arrow:hover {
    background-color: rgba(0, 0, 0, 0.20);
    color: #FFF;
}
.unslider-arrow.prev {
    padding: 3px 4px 3px 2px;
}
.unslider-arrow.next {
    right: 0px;
}
.unslider-arrow.prev::before {
    content: "\f341";
}
.unslider-arrow.next::before {
    content: "\f345";
}
/* responsive stuff: hide admin-feed on smaller screens */
@media (min-width: 961px) {
    #lyte_main {float:left;width:69%;}
    #lyte_admin_feed{float:right;width:30%;display:block !important;}
    }
@media (max-width: 960px) {
    #lyte_main {width:100%;}
    #lyte_admin_feed {width:0%;display:none !important;}
}
@media (max-width: 782px) {
    #lyte_hide_adv span, #lyte_show_adv span {display: none;}
    #lyte_hide_adv,#lyte_show_adv {height: 34px;padding: 4px 12px 8px 8px;}
    #lyte_hide_adv:before,#lyte_show_adv:before {font-size: 25px;}
    #lyte_main input[type="checkbox"] {margin-left: 10px;}
    #lyte_main .cb_label {display: block; padding-left: 45px; text-indent: -45px;}
}
</style>
<div class="wrap">
<h2><?php _e("WP YouTube Lyte Settings","wp-youtube-lyte") ?></h2>
<div style="float:left;width:70%;">
<?php echo lyte_admin_tabs(); ?>
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
                    $sel = !is_bool(get_option('lyte_size')) ? (int) get_option('lyte_size') : 0;
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
                    <label title="<?php _e('Show YouTube-link','wp-youtube-lyte');?>"><input type="radio" name="lyte_show_links" value="1" <?php if (get_option('lyte_show_links')==="1" || get_option('lyte_show_links')==="2") echo "checked" ?> /><?php _e(" Add YouTube-link.","wp-youtube-lyte") ?></label><br />
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
                <th scope="row"><?php _e("Try to force HD?","wp-youtube-lyte") ?></th>
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
                <th scope="row"><?php _e("Also act on normal YouTube links and iframes?","wp-youtube-lyte") ?></th>
                <td>
                        <fieldset>
                                <legend class="screen-reader-text"><span><?php _e("Also act on normal YouTube links?","wp-youtube-lyte") ?></span></legend>
                                <label title="<?php _e('That would be great!','wp-youtube-lyte');?>"><input type="radio" name="lyte_greedy" value="1" <?php if (get_option('lyte_greedy','1')==="1") echo "checked" ?> /><?php _e("Yes (default)","wp-youtube-lyte") ?></label><br />
                                <label title="<?php _e('No, I\'ll stick to httpv or shortcodes.','wp-youtube-lyte');?>"><input type="radio" name="lyte_greedy" value="0" <?php if (get_option('lyte_greedy','1')!=="1") echo "checked" ?> /><?php _e("No thanks.","wp-youtube-lyte") ?></label>
                        </fieldset>
                </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e("Cache thumbnails locally?","wp-youtube-lyte"); ?></th>
            <td>
                    <fieldset>
                            <legend class="screen-reader-text"><span><?php _e("Cache thumbnails locally?","wp-youtube-lyte") ?></span></legend>
                            <label title="<?php _e('That would be great!','wp-youtube-lyte');?>"><input type="radio" name="lyte_local_thumb" value="1" <?php if (get_option('lyte_local_thumb','0')==="1") echo "checked" ?> /><?php _e("Yes.","wp-youtube-lyte") ?></label><br />
                            <label title="<?php _e('No, keep on using YouTube hosted thumbnails.','wp-youtube-lyte');?>"><input type="radio" name="lyte_local_thumb" value="0" <?php if (get_option('lyte_local_thumb','0')!=="1") echo "checked" ?> /><?php _e("No (default).","wp-youtube-lyte") ?></label>
                            <br />
                            <?php _e("Having the thumbnails cached locally can improve performance and will enhance visitor privacy as by default no requests will be sent to YouTube unless the video is played.","wp-youtube-lyte"); ?>
                    </fieldset>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e("Text to be added under every LYTE video.","wp-youtube-lyte"); ?></th>
            <td>
                <fieldset>
                    <legend class="screen-reader-text"><span><?php _e("Text (e.g. for disclaimer) to be added under every LYTE video.","wp-youtube-lyte") ?></span></legend>
                    <input type="text" style="width:100%;" name="lyte_disclaimer" placeholder="" value="<?php echo esc_textarea(get_option('lyte_disclaimer','')); ?>" /><br />
                    <br />
                    <?php _e("If you want to add e.g. a privacy disclaimer under every LYTE embedded video, you can do so here. Some HTML is allowed. Simply leave empty not to show anything.","wp-youtube-lyte"); ?>
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
<div id="lyte_admin_feed" class="">
    <div class="lyte_banner ">
        <ul>
        <?php
        if (apply_filters('wp-youtube-lyte_settingsscreen_remotehttp',true)) {
            $lyte_banner=get_transient("wp-youtube-lyte_banner");
            if (empty($lyte_banner)) {
                $banner_resp = wp_remote_get("http://misc.optimizingmatters.com/wp-youtube-lyte_news.html");
                if (!is_wp_error($banner_resp)) {
                    if (wp_remote_retrieve_response_code($banner_resp)=="200") {
                        $lyte_banner = wp_kses_post(wp_remote_retrieve_body($banner_resp));
                        set_transient("wp-youtube-lyte_banner",$lyte_banner,DAY_IN_SECONDS);
                    }
                }
            }
            echo $lyte_banner;
        }
        ?>
        <li><?php _e("Need help? <a href='https://wordpress.org/plugins/wp-youtube-lyte/faq/'>Check out the FAQ here</a>.","wp-youtube-lyte"); ?></li>
        <li><?php _e("Happy with wp-youtube-lyte?","wp-youtube-lyte"); ?><br /><a href="<?php echo network_admin_url(); ?>plugin-install.php?tab=search&type=author&s=optimizingmatters"><?php _e("Try my other plugins!","wp-youtube-lyte"); ?></a></li>
        </ul>
    </div>
    <div style="margin-left:10px;margin-top:-5px;">
        <h2>
            <?php _e("futtta about","wp-youtube-lyte") ?>
            <select id="feed_dropdown" >
                <option value="1"><?php _e("WP YouTube Lyte","wp-youtube-lyte") ?></option>
                <option value="2"><?php _e("WordPress","wp-youtube-lyte") ?></option>
                <option value="3"><?php _e("Web Technology","wp-youtube-lyte") ?></option>
            </select>
        </h2>
        <div id="futtta_feed">
            <div id="wp-youtube-lytefeed">
                <?php getFutttaFeeds("http://feeds.feedburner.com/futtta_wp-youtube-lyte"); ?>
            </div>
            <div id="wordpressfeed">
                <?php getFutttaFeeds("http://feeds.feedburner.com/futtta_wordpress"); ?>
            </div>
            <div id="webtechfeed">
                <?php getFutttaFeeds("http://feeds.feedburner.com/futtta_webtech"); ?>
            </div>
        </div>
    </div>
    <div style="float:right;margin:50px 15px;"><a href="http://blog.futtta.be/2013/10/21/do-not-donate-to-me/" target="_blank"><img width="100px" height="85px" src="<?php echo plugins_url().'/'.plugin_basename(dirname(__FILE__)).'/external/do_not_donate_smallest.png'; ?>" title="<?php _e("Do not donate for this plugin!","wp-youtube-lyte"); ?>"></a></div>
</div>

<script type="text/javascript">
    var feed = new Array;
    feed[1]="wp-youtube-lytefeed";
    feed[2]="wordpressfeed";
    feed[3]="webtechfeed";
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
        jQuery('#lyte_admin_feed').fadeTo("slow",1).show();
        jQuery('.lyte_banner').unslider({autoplay:true, delay:3500, infinite: false, arrows:{prev:'<a class="unslider-arrow prev"></a>', next:'<a class="unslider-arrow next"></a>'}}).fadeTo("slow",1).show();

        jQuery( "#feed_dropdown" ).change(function() {
            jQuery("#futtta_feed").fadeTo(0,0);
            jQuery("#futtta_feed").fadeTo("slow",1);
        });

        jQuery("#feed_dropdown").change(function() { show_feed(jQuery("#feed_dropdown").val()) });
        feedid=jQuery.cookie(cookiename);
        if(typeof(feedid) !== "string") feedid=1;
        show_feed(feedid);
    })

    function show_feed(id) {
        jQuery('#futtta_feed').children().hide();
        jQuery('#'+feed[id]).show();
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
    $vidToCheck=array("ZmnZHudtzXg","2_7oQcAkyl8","nOvv80wkSgI","pBCt5nfsZ30","KHw7gdJ14uQ","qJ_PMvjmC6M","DVwHCGAr_OE","LtOGa5M8AuU","VHO9uZX9FNU");
    $randVidIndex=array_rand($vidToCheck);
    
    $api_response = lyte_get_YT_resp($vidToCheck[$randVidIndex],false,"",$api_key);
    
    if (is_array($api_response)) {
        if (!empty($api_response["title"])) {
            _e("API seems OK, you can Save Changes below now.");
        } else if (!empty($api_response["reason"])) {
            $all_but_one = __("API key not OK, your key seems to ");
            switch ($api_response["reason"]) {
                case "keyInvalid":
                    echo $all_but_one;
                    _e("be invalid.",'wp-youtube-lyte');
                    break;
                case "ipRefererBlocked":
                    echo $all_but_one;
                    _e("be valid, but restricted to an IP-address which is not your server's.",'wp-youtube-lyte');
                    _e("Try changing the allowed IP for your API key to include this one: ",'wp-youtube-lyte');
                    echo $_SERVER["SERVER_ADDR"];
                    break;
                case "keyExpired":
                    echo $all_but_one;
                    _e("have expired, please check in the Google Developer Console.",'wp-youtube-lyte');
                    break;
                case "limitExceeded":
                case "quotaExceeded":
                case "rateLimitExceeded":
                case "userRateLimitExceeded":
                    echo $all_but_one;
                    _e("be correct, but seems to have exceeded the number of requests that can be made with it.",'wp-youtube-lyte');
                    break;
                case "videoNotFound":
                    echo $all_but_one;
                    _e("probably work, but as the video with id ",'wp-youtube-lyte');
                    echo $vidToCheck[$randVidIndex];
                    _e(" was not found we cannot be sure, please try again.",'wp-youtube-lyte');
                    break;
                default:
                    _e("Your API key might be OK, but the API call did not succeed or the response was not entirely expected. Technical error: ",'wp-youtube-lyte');
                    echo $api_response["reason"];
                }
        }
    } else {
        _e("Something went wrong, WP YouTube Lyte might not have been able to retrieve information from the YouTube API, got error: ",'wp-youtube-lyte');
        print_r($api_response);
    }
    wp_die();
}

function getFutttaFeeds($url) {
if (apply_filters('lyte_settingsscreen_remotehttp',true)) {
    $rss = fetch_feed( $url );
    $maxitems = 0;

    if ( ! is_wp_error( $rss ) ) {
        $maxitems = $rss->get_item_quantity( 7 ); 
        $rss_items = $rss->get_items( 0, $maxitems );
    }
    ?>
    <ul>
        <?php if ( $maxitems == 0 ) : ?>
            <li><?php _e( 'No items', 'autoptimize' ); ?></li>
        <?php else : ?>
            <?php foreach ( $rss_items as $item ) : ?>
                <li>
                    <a href="<?php echo esc_url( $item->get_permalink() ); ?>"
                        title="<?php printf( __( 'Posted %s', 'autoptimize' ), $item->get_date('j F Y | g:i a') ); ?>">
                        <?php echo esc_html( $item->get_title() ); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>
    </ul>
    <?php
}
}

// based on http://wordpress.stackexchange.com/a/58826
function lyte_admin_tabs(){
        $tabs = apply_filters('wp-youtube-lyte_filter_settingsscreen_tabs',array('lyte_settings_page' => __('Main','wp-youtube-lyte')));
        $tabContent="";
        if (count($tabs) >= 1) {
            if(isset($_GET['page'])){
                $currentId = $_GET['page'];
            } else {
                $currentId = "wp-youtube-lyte";
            }
            $tabContent .= "<h2 class=\"nav-tab-wrapper\">";
            foreach($tabs as $tabId => $tabName){
                if($currentId == $tabId){
                    $class = " nav-tab-active";
                } else{
                    $class = "";
                }
                $tabContent .= '<a class="nav-tab'.$class.'" href="?page='.$tabId.'">'.$tabName.'</a>';
            }
            $tabContent .= "</h2>";
        } else {
            $tabContent = "<hr/>";
        }

        return $tabContent;
}
?>
