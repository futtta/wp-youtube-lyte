<?php
/*
Plugin Name: WP YouTube Lyte
Plugin URI: http://blog.futtta.be/wp-youtube-lyte/
Description: Lite and accessible YouTube audio and video embedding.
Author: Frank Goossens (futtta)
Version: 1.7.19
Author URI: http://blog.futtta.be/
Text Domain: wp-youtube-lyte
*/

// fixme: add logic to remove settings/ cache/ ... when removing plugin

if ( ! defined( 'ABSPATH' ) ) exit;

$debug           = false;
$lyte_version    = '1.7.19';
$lyte_db_version = get_option( 'lyte_version', 'none' );

/** have we updated? */
if ($lyte_db_version !== $lyte_version) {
    switch( $lyte_db_version ) {
        case '1.5.0':
            lyte_rm_cache();
            break;
        case '1.4.2':
        case '1.4.1':
        case '1.4.0':
            lyte_rm_cache();
            lyte_not_greedy();
            break;
        case '1.7.0':
        case '1.7.1':
        case '1.7.2':
        case '1.7.3':
        case '1.7.4':
        case '1.7.5':
        case '1.7.6':
        case '1.7.7':
        case '1.7.8':
        case '1.7.9':
        case '1.7.10':
        case '1.7.11':
            lyte_mv_cache();
            break;
    }
    update_option( 'lyte_version', $lyte_version );
    $lyte_db_version = $lyte_version;
}

/** are we in debug-mode */

if (!$debug) {
    $wyl_version       = $lyte_version;
    $wyl_file          = 'lyte-min.js';
    $wyl_file_lazyload = 'bg-image-layzload.min.js';
} else {
    $wyl_version       = rand()/1000;
    $wyl_file          = 'lyte.js';
    $wyl_file_lazyload = 'bg-image-layzload.js';
    lyte_rm_cache();
}

/** get paths, language and includes */
$plugin_dir = basename( dirname( __FILE__ ) ) . '/languages';
load_plugin_textdomain( 'wp-youtube-lyte', null, $plugin_dir );
require_once( dirname( __FILE__ ) . '/player_sizes.inc.php' );
require_once( dirname( __FILE__ ) . '/widget.php' );

/** get default embed size and build array to change size later if requested */
$oSize = (int) get_option( 'lyte_size' );
if ( (is_bool( $oSize ) ) || ( $pSize[$oSize]['a'] === false ) ) {
    $sel = (int) $pDefault;
} else {
    $sel=$oSize;
}

$pSizeFormat = $pSize[$sel]['f'];
$j           = 0;

foreach ( $pSizeOrder[$pSizeFormat] as $sizeId ) {
    $sArray[$j]['w'] = (int) $pSize[$sizeId]['w'];
    $sArray[$j]['h'] = (int) $pSize[$sizeId]['h'];
    if ( $sizeId === $sel ) {
        $selSize=$j;
    }
    $j++;
}

/** get other options and push in array*/
$lyteSettings['sizeArray']                    = $sArray;
$lyteSettings['selSize']                      = $selSize;
$lyteSettings['links']                        = get_option( 'lyte_show_links' );
$lyteSettings['file']                         = $wyl_file . '?wyl_version=' . $wyl_version;
$lyteSettings['file_lazyload']                = $wyl_file_lazyload . '?wyl_version=' . $wyl_version;
$lyteSettings['ratioClass']                   = ( $pSizeFormat === '43' ) ? ' fourthree' : '';
$lyteSettings['pos']                          = ( get_option( 'lyte_position', '0' ) === '1' ) ? 'margin:5px auto;' : 'margin:5px;';
$lyteSettings['microdata']                    = get_option( 'lyte_microdata', '1' );
$lyteSettings['hidef']                        = get_option( 'lyte_hidef', 0 );
$lyteSettings['scheme']                       = ( is_ssl() ) ? 'https' : 'http';

/** API: filter hook to alter $lyteSettings */
function lyte_settings_enforcer() {
    global $lyteSettings;
    $lyteSettings['lyte_use_internal_lazyloader'] = apply_filters( 'lyte_use_internal_lazyloader', false );
    $lyteSettings = apply_filters( 'lyte_settings', $lyteSettings );
}
add_action( 'after_setup_theme', 'lyte_settings_enforcer' );

function lyte_parse( $the_content, $doExcerpt = false ) {
    /** bail if AMP or if LYTE feed disabled and is_feed */
    if ( is_amp() || ( apply_filters( 'lyte_filter_dofeed', true ) === false && is_feed() ) ) { 
        return str_replace( 'httpv://', 'https://', $the_content );
    }

    /** main function to parse the content, searching and replacing httpv-links */
    global $lyteSettings, $toCache_index, $postID, $cachekey;
    $lyteSettings['path'] = plugins_url() . '/' . dirname( plugin_basename( __FILE__ ) ) . '/lyte/';
    $urlArr               = parse_url( $lyteSettings['path'] );
    $origin               = $urlArr['scheme'] . '://' . $urlArr['host'];

    /** API: filter hook to preparse the_content, e.g. to force normal youtube links to be parsed */
    $the_content = apply_filters( 'lyte_content_preparse', $the_content );

    if ( get_option( 'lyte_greedy', '1' ) === '1' && strpos( $the_content, 'youtu' ) !== false ){
        // new: also replace original YT embed code (iframes)
        if ( apply_filters( 'lyte_eats_yframes', true ) && preg_match_all( '#<iframe(?:[^<]*)?\ssrc=["|\'](?:http(?:s)?:)?\/\/www\.youtube(?:-nocookie)?\.com\/embed\/(.*)["|\'](?:.*)><\/iframe>#Usm', $the_content, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $match ) {
                if ( strpos( $match[1], 'videoseries' ) === false) {
                    $the_content = str_replace( $match[0], 'httpv://youtu.be/'.$match[1], $the_content );
                } else {
                    $the_content = str_replace( $match[0], 'httpv://youtube.com/playlist?list=' . str_replace( 'videoseries?list=', '', $match[1] ), $the_content );
                }
            }
        }
    }

    if ( ( strpos( $the_content, 'httpv' ) !== false ) || ( strpos( $the_content, 'httpa' ) !== false ) ) {
        if ( apply_filters( 'lyte_remove_wpautop', false ) ) {
            remove_filter( 'the_content', 'wpautop' );
        }

        if ( apply_filters( 'lyte_kinda_textureize', true ) ) {
            $char_codes   = array( '&#215;', '&#8211;', '\u002d' );
            $replacements = array( 'x', '--', '-');
            $the_content  = str_replace( $char_codes, $replacements, $the_content );
        }

        $lyte_feed     = is_feed();
        $hidefClass    = ( $lyteSettings['hidef'] === '1') ? ' hidef' : '';
        $postID        = get_the_ID();
        $toCache_index = array();
        $lytes_regexp  = '/(?:<p>)?http(v|a):\/\/([a-zA-Z0-9\-\_]+\.|)(youtube|youtu)(\.com|\.be)\/(((watch(\?v\=|\/v\/)|.+?v\=|)([a-zA-Z0-9\-\_]{11}))|(playlist\?list\=([a-zA-Z0-9\-\_]*)))([^\s<]*)(<?:\/p>)?/';

        preg_match_all( $lytes_regexp, $the_content, $matches, PREG_SET_ORDER );

        foreach( $matches as $match ) {
            /** API: filter hook to preparse fragment in a httpv-url, e.g. to force hqThumb=1 or showinfo=0 */
            $match[12] = apply_filters( 'lyte_match_preparse_fragment', $match[12] );

            preg_match( '/stepSize\=([\+\-0-9]{2})/', $match[12], $sMatch );
            preg_match( '/showinfo\=([0-1]{1})/', $match[12], $showinfo );
            preg_match( '/start\=([0-9]*)/', $match[12], $start );
            preg_match( '/enablejsapi\=([0-1]{1})/', $match[12], $jsapi );
            preg_match( '/hqThumb\=([0-1]{1})/', $match[12], $hqThumb );
            preg_match( '/noMicroData\=([0-1]{1})/', $match[12], $microData );

            $thumb = 'normal';
            if ( $lyteSettings['hidef'] === '1' ) {
                $thumb = 'highres';
            } else if ( ! empty( $hqThumb ) ) {
                if ( $hqThumb[0] === 'hqThumb=1' ) {
                    $thumb = 'highres';
                }
            }

            $noMicroData = '0';
            if ( ! empty( $microData ) ) {
                if ( $microData[0] === 'noMicroData=1' ) {
                    $noMicroData = '1';
                }
            }

            $qsa='';
            if ( ! empty( $showinfo[0] ) ) {
                $qsa = '&amp;' . $showinfo[0];
                $titleClass = ' hidden';
            } else {
                $titleClass = '';
            }
            if ( ! empty( $start[0] ) ) {
                $qsa = $qsa . '&amp;' . $start[0];
            }
            if ( ! empty( $jsapi[0] ) ) {
                $qsa = $qsa . '&amp;' . $jsapi[0] . '&amp;origin=' . $origin;
            }
            if ( ! empty( $qsa ) ) {
                $esc_arr  = array( '&' => '\&', '?' => '\?', '=' => '\=');
                $qsaClass = ' qsa_' . strtr( $qsa, $esc_arr ) ;
            } else {
                $qsaClass = '';
            }

            if ( ! empty( $sMatch ) ) {
                $newSize = (int) $sMatch[1];
                $newPos  = (int) $lyteSettings['selSize'] + $newSize;
                if ( $newPos < 0 ) {
                    $newPos = 0;
                } else if ( $newPos > count( $lyteSettings['sizeArray'] ) - 1 ) {
                    $newPos = count($lyteSettings['sizeArray']) - 1;
                }
                $lyteSettings[2] = $lyteSettings['sizeArray'][$newPos]['w'];
                $lyteSettings[3] = $lyteSettings['sizeArray'][$newPos]['h'];
            } else {
                $lyteSettings[2] = $lyteSettings['sizeArray'][$lyteSettings['selSize']]['w'];
                $lyteSettings[3] = $lyteSettings['sizeArray'][$lyteSettings['selSize']]['h'];
            }

            if ( $match[1] !== 'a' ) {
                $divHeight  = $lyteSettings[3];
                $audioClass = '';
                $audio      = false;
            } else {
                $audio      = true;
                $audioClass = ' lyte-audio';
                $divHeight  = 38;
            }

            $NSimgHeight = $divHeight - 20;

            if ( $match[11] != '' ) {
                $plClass = ' playlist';
                $vid     = $match[11];
                switch ( $lyteSettings['links'] ) {
                    case '0':
                        $noscript_post = '<br />' . __( 'Watch this playlist on YouTube', 'wp-youtube-lyte' );
                        $noscript      = '<noscript><a href="' . $lyteSettings['scheme'] . '://youtube.com/playlist?list=' . $vid . '">' . $noscript_post . '</a></noscript>';
                        $lytelinks_txt = '';
                        break;
                    default:
                        $noscript      = '';
                        $lytelinks_txt = '<div class="lL" style="max-width:100%;width:' . $lyteSettings[2] . 'px;' . $lyteSettings['pos'] . '">' . __( 'Watch this playlist', 'wp-youtube-lyte') . ' <a href="' . $lyteSettings['scheme'] . '://www.youtube.com/playlist?list=' . $vid . '">' . __( 'on YouTube', 'wp-youtube-lyte') . '</a></div>';
                }
            } else if ($match[9]!="") {
                $plClass="";
                $vid=$match[9];
                switch ($lyteSettings['links']) {
                    case "0":
                        $noscript_post = '<br />' . __( 'Watch this video on YouTube', 'wp-youtube-lyte' );
                        $lytelinks_txt = '<div class="lL" style="max-width:100%;width:' . $lyteSettings[2] . 'px;' . $lyteSettings['pos'] .'"></div>';
                        break;
                    default:
                        $noscript_post = '';
                        $lytelinks_txt = '<div class="lL" style="max-width:100%;width:' . $lyteSettings[2] . 'px;' . $lyteSettings['pos'] . '">' . __( 'Watch this video', 'wp-youtube-lyte' ) . ' <a href="' . $lyteSettings['scheme'] . '://youtu.be/' . $vid . '">' . __( 'on YouTube', 'wp-youtube-lyte' ) . '</a>.</div>';
                }
                $thumbUrl = $lyteSettings['scheme'] . '://i.ytimg.com/vi/' . $vid . '/0.jpg';
                if ( get_option( 'lyte_local_thumb', '0' ) === '1' ) {
                        $thumbUrl = plugins_url( 'lyteCache.php?origThumbUrl=' . urlencode($thumbUrl) , __FILE__   );
                }
                $thumbUrl = apply_filters( 'lyte_match_thumburl', $thumbUrl, $vid );
                $noscript = '<noscript><a href="' . $lyteSettings['scheme'] . '://youtu.be/' . $vid . '"><img src="' . $thumbUrl . '" alt="" width="' . $lyteSettings[2] . '" height="' . $NSimgHeight . '" />' . $noscript_post . '</a></noscript>';
            }

            // add disclaimer to lytelinks
            $disclaimer = wp_kses_data( get_option( 'lyte_disclaimer', '') );
	    $disclaimer = apply_filters('lyte_disclaimer', $disclaimer);
            if ( !empty( $disclaimer ) ) {
                $disclaimer = '<span class="lyte_disclaimer">' . $disclaimer . '</span>';
            }

            if ( $disclaimer && empty( $lytelinks_txt ) ) {
                $lytelinks_txt = '<div class="lL" style="max-width:100%;width:' . $lyteSettings[2] . 'px;' . $lyteSettings['pos'] . '">' . $disclaimer . '</div>';
            } else if ( $disclaimer ) {
                $lytelinks_txt = str_replace( '</div>', '<br/>' . $disclaimer . '</div>', $lytelinks_txt );
            }

            // fetch data from YT api (v2 or v3)
            $isPlaylist = false;
            if ( $plClass ===' playlist' ) {
                $isPlaylist = true;
            }
            $cachekey      = '_lyte_' . $vid;
            $yt_resp_array = lyte_get_YT_resp( $vid, $isPlaylist, $cachekey );

            // If there was a result from youtube or from cache, use it
            if ( $yt_resp_array && is_array( $yt_resp_array ) ) {
                if ( $isPlaylist !== true ) {
                    // captions
                    $captionsMeta = '';
                    $doCaptions   = true;

                    /** API: filter hook to disable captions */
                    $doCaptions = apply_filters( 'lyte_docaptions', $doCaptions );

                    if ( ( $lyteSettings['microdata'] === "1" ) && ( $noMicroData !== "1" ) && ( $doCaptions === true ) ) {
                        if ( array_key_exists( 'captions_data', $yt_resp_array ) && $yt_resp_array['captions_data'] == 'true') {
                            $captionsMeta = '<meta itemprop="accessibilityFeature" content="captions" />';
                        }
                    }
                }

                $thumbUrl = '';
                if ( ( $thumb === 'highres' ) && ( ! empty( $yt_resp_array['HQthumbUrl'] ) ) ){
                    $thumbUrl = $yt_resp_array['HQthumbUrl'];
                } else {
                    if ( ! empty( $yt_resp_array['thumbUrl'] ) ) {
                        $thumbUrl = $yt_resp_array['thumbUrl'];
                    } else {
                        $thumbUrl = '//i.ytimg.com/vi/' . $vid . '/hqdefault.jpg';
                    }
                }
                if ( strpos( $noscript, 'alt=""' ) !== false && array_key_exists( 'title', $yt_resp_array ) ) {
                    $noscript = str_replace( 'alt=""', 'alt="' . htmlentities( $yt_resp_array["title"] ). '"', $noscript );
                }
            } else {
                $thumbUrl = "//i.ytimg.com/vi/".$vid."/hqdefault.jpg";
            }

            // do we have to serve the thumbnail from local cache?
            if ( get_option( 'lyte_local_thumb', '0' ) === '1' ) {
                $thumbUrl = plugins_url( 'lyteCache.php?origThumbUrl=' . urlencode( $thumbUrl ) , __FILE__  );
            }

            /** API: filter hook to override thumbnail URL */
            $thumbUrl = apply_filters( 'lyte_match_thumburl', $thumbUrl, $vid );

            if ( isset( $yt_resp_array ) && ! empty( $yt_resp_array ) && ! empty( $yt_resp_array['title'] ) ) {
                $_this_title     = $yt_resp_array['title'];
                $_this_title_tag = ' title="' . htmlentities( $_this_title ) . '"';
            } else {
                $_this_title     = false;
                $_this_title_tag = '';
            }

            if ( $audio === true ) {
                $wrapper = '<div class="lyte-wrapper-audio"' . $_this_title_tag . ' style="width:' . $lyteSettings[2] . 'px;max-width:100%;overflow:hidden;height:38px;' . $lyteSettings['pos'] . '">';
            } else {
                $wrapper = '<div class="lyte-wrapper' . $lyteSettings['ratioClass'] . '"' . $_this_title_tag . ' style="width:' . $lyteSettings[2] . 'px;max-width:100%;' . $lyteSettings['pos'] . '">';
            }

            // do we have usable microdata fiels from the YT API, if not no microdata below.
            foreach ( array( 'title', 'description', 'dateField' ) as $resp_key ) {
                if ( empty( $yt_resp_array[$resp_key] ) ) {
                    $noMicroData = '1';
                    break;
                }
            }

            // do we have a YT API key, if not; no microdata below.
            $lyte_yt_api_key = apply_filters( 'lyte_filter_yt_api_key', get_option( 'lyte_yt_api_key', '' ) );
            if ( $lyte_yt_api_key === "none" || empty( $lyte_yt_api_key ) ) {
                $noMicroData = '1';
            }

            if ( $doExcerpt ) {
                $lytetemplate = '';
                $templateType = 'excerpt';
            } elseif ( $lyte_feed ) {
                $postURL      = get_permalink( $postID );
                $textLink     = ( $lyteSettings['links'] ===0 ) ? '' : '<br />' . strip_tags( $lytelinks_txt, '<a>' ) . '<br />';
                $lytetemplate = '<a href="' . $postURL . '"><img src="' . $thumbUrl . '" alt="YouTube Video"></a>' . $textLink;
                $templateType = 'feed';
            } elseif ( $audio !== true && $plClass !== " playlist" && $lyteSettings['microdata'] === "1" && $noMicroData !== "1" ) {
                $lytetemplate = $wrapper . '<div class="lyMe' . $audioClass . $hidefClass . $plClass . $qsaClass . '" id="WYL_' . $vid . '" itemprop="video" itemscope itemtype="https://schema.org/VideoObject"><div><meta itemprop="thumbnailUrl" content="' . $thumbUrl . '" /><meta itemprop="embedURL" content="https://www.youtube.com/embed/' . $vid . '" /><meta itemprop="duration" content="' . $yt_resp_array['duration'] . '" /><meta itemprop="uploadDate" content="' . $yt_resp_array["dateField"] . '" /></div>' . $captionsMeta. '<div id="lyte_' . $vid . '" data-src="' . $thumbUrl . '" class="pL'.($lyteSettings['lyte_use_internal_lazyloader'] ? " wyl-lazy" : "").'"><div class="tC' . $titleClass . '"><div class="tT" itemprop="name">' . $yt_resp_array["title"] . '</div></div><div class="play"></div><div class="ctrl"><div class="Lctrl"></div><div class="Rctrl"></div></div></div>' . $noscript . '<meta itemprop="description" content="' . $yt_resp_array["description"] . '"></div></div>' . $lytelinks_txt;
                $templateType = 'postMicrodata';
            } else {
                $lytetemplate = $wrapper . '<div class="lyMe' . $audioClass . $hidefClass . $plClass . $qsaClass . '" id="WYL_' . $vid . '"><div id="lyte_' . $vid . '" data-src="' . $thumbUrl . '" class="pL'.($lyteSettings['lyte_use_internal_lazyloader'] ? " wyl-lazy" : "").'">';
                if ( isset( $_this_title ) ) {
                    $lytetemplate .= '<div class="tC' . $titleClass . '"><div class="tT">' . $_this_title . '</div></div>';
                }

                $lytetemplate .= '<div class="play"></div><div class="ctrl"><div class="Lctrl"></div><div class="Rctrl"></div></div></div>' . $noscript . '</div></div>' . $lytelinks_txt;
                $templateType="post";
            }

            /** API: filter hook to parse template before being applied */
            $lytetemplate = str_replace( '$', '&#36;', $lytetemplate );

            // make sure *something* is passed as $yt_resp_array to avoid notices in below filter.
            if ( ! isset( $yt_resp_array ) ) {
                $yt_resp_array = '';
            }
            $lytetemplate = apply_filters( 'lyte_match_postparse_template', $lytetemplate, $templateType, $yt_resp_array );
            $the_content  = preg_replace( $lytes_regexp, $lytetemplate, $the_content, 1 );
        }

        // update lyte_cache_index
        if ( ( is_array( $toCache_index ) ) && ( ! empty( $toCache_index ) ) ) {
            $lyte_cache          = json_decode( get_option( 'lyte_cache_index' ), true );
            $lyte_cache[$postID] = $toCache_index;
            update_option( 'lyte_cache_index', json_encode( $lyte_cache ) );
        }

        if ( ! $lyte_feed ) {
            lyte_initer();
        }
    }

    if ( apply_filters( 'lyte_kinda_textureize', true ) ) {
        // replace remaining double dash but restore it in comment tags (this is getting ugly though).
        $the_content = str_replace( array( ' -- ', '-- ', ' --' ), ' &#8211; ', $the_content );
        $the_content = str_replace( '<! &#8211;', '<!--', $the_content );
        $the_content = str_replace( '&#8211; >', '-->', $the_content );
    }

    /** API: filter hook to postparse the_content before returning */
    $the_content = apply_filters( 'lyte_content_postparse', $the_content );

    return $the_content;
}

function lyte_get_YT_resp( $vid, $playlist=false, $cachekey='', $apiTestKey='', $isWidget=false ) {
    /** logic to get video info from cache or get it from YouTube and set it */
    global $postID, $cachekey, $toCache_index;

    $_thisLyte = array();
    $yt_error  = array();

    if ( $postID && empty( $apiTestKey ) && ! $isWidget ) {
        $cache_resp = get_post_meta( $postID, $cachekey, true );
        if ( ! empty( $cache_resp ) ) {
            $_thisLyte = json_decode( gzuncompress( base64_decode( $cache_resp ) ), 1 );
            // make sure there are not old APIv2 full responses in cache
            if ( array_key_exists( 'entry', $_thisLyte ) ) {
                if ( $_thisLyte['entry']['xmlns$yt'] === 'http://gdata.youtube.com/schemas/2007' ) {
                    $_thisLyte = array();
                }
            }
        }
    } else if ( $isWidget ) {
        $cache_resp = get_option( 'lyte_widget_cache' );
        if ( ! empty( $cache_resp ) ) {
            $widget_cache = json_decode( gzuncompress( base64_decode( $cache_resp ) ), 1 );
            $_thisLyte    = $widget_cache[$vid];
        }
    }

    // set caching duration used to check if data is fresh enough, default max cachetime is 2 months.
    $_lyte_cache_gracetime    = apply_filters( 'lyte_ytapi_cache_gracetime', 2 * 30 * 24 * 60 * 60 );
    $_lyte_cache_time_expired = time() - $_lyte_cache_gracetime;

    if ( empty( $_thisLyte ) || ( apply_filters( 'lyte_ytapi_check_cache', true ) && $_thisLyte['lyte_date_added'] < $_lyte_cache_time_expired ) ) {
        // no result from cache or expired so fetch info from youtube
        // first get yt api key
        $lyte_yt_api_key = get_option('lyte_yt_api_key','');
        $lyte_yt_api_key = apply_filters('lyte_filter_yt_api_key', $lyte_yt_api_key);
        if ( ! empty( $apiTestKey ) ) {
            $lyte_yt_api_key = $apiTestKey;
        }

        if ( ( $lyte_yt_api_key === 'none' ) || ( empty( $lyte_yt_api_key ) ) ) {
            $_thisLyte['title'] = '';
            if ( $playlist ) {
                $_thisLyte['thumbUrl']   = '';
                $_thisLyte['HQthumbUrl'] = '';
            } else {
                $_thisLyte['thumbUrl']   = '//i.ytimg.com/vi/' . $vid . '/hqdefault.jpg';
                $_thisLyte['HQthumbUrl'] = '//i.ytimg.com/vi/' . $vid . '/maxresdefault.jpg';
            }
            $_thisLyte['dateField']     = '';
            $_thisLyte['duration']      = '';
            $_thisLyte['description']   = '';
            $_thisLyte['captions_data'] = 'false';
            return $_thisLyte;
        } else {
            // v3, feeling somewhat lonely now v2 has gently been put to sleep
            $yt_api_base = 'https://www.googleapis.com/youtube/v3/';

            if ( $playlist ) {
                // filter to allow extra data to be requested from YT API, eg. statistics (to get view count).
                $yt_api_target = apply_filters( 'lyte_filter_ytapi_playlist_params', 'playlists?part=snippet%2C+id&id=' . $vid . '&key=' . $lyte_yt_api_key );
            } else {
                // filter to allow extra data to be requested from YT API, eg. statistics (to get view count).
                $yt_api_target = apply_filters( 'lyte_filter_ytapi_video_params', 'videos?part=id%2C+snippet%2C+contentDetails&id=' . $vid . '&key=' . $lyte_yt_api_key );
            }
        }

        $yt_api_url = $yt_api_base . $yt_api_target;
        $yt_resp    = wp_remote_get( $yt_api_url, array(
            'headers' => array(
                'referer' => home_url()
                )
            )
        );

        // check if we got through
        if ( is_wp_error( $yt_resp ) ) {
            $yt_error['code']      = 408;
            $yt_error['reason']    = $yt_resp->get_error_message();
            $yt_error['timestamp'] = strtotime('now');
            if ( ! empty( $apiTestKey ) ) {
                return $yt_error;
            }
        } else {
            $yt_resp_array = (array) json_decode( wp_remote_retrieve_body( $yt_resp ), true );
            if ( is_array( $yt_resp_array ) ) {
                // extract relevant data
                // v3
                if ( in_array( wp_remote_retrieve_response_code( $yt_resp ), array( 400, 403, 404 ) ) ) {
                    $yt_error['code']      = wp_remote_retrieve_response_code( $yt_resp );
                    $yt_error['reason']    = $yt_resp_array['error']['errors'][0]['reason'];
                    $yt_error['timestamp'] = strtotime('now');
                    if ( empty( $apiTestKey ) ) {
                        update_option('lyte_api_error', json_encode( $yt_error ) );
                    } else {
                        return $yt_error;
                    }
                } else {
                    if ($playlist) {
                        $_thisLyte['title']         = 'Playlist: ' . esc_attr( sanitize_text_field( @$yt_resp_array['items'][0]['snippet']['title'] ) );
                        $_thisLyte['thumbUrl']      = esc_url(@$yt_resp_array['items'][0]['snippet']['thumbnails']['high']['url']);
                        $_thisLyte['HQthumbUrl']    = esc_url(@$yt_resp_array['items'][0]['snippet']['thumbnails']['maxres']['url']);
                        $_thisLyte['dateField']     = sanitize_text_field( @$yt_resp_array['items'][0]['snippet']['publishedAt'] );
                        $_thisLyte['duration']      = '';
                        $_thisLyte['description']   = esc_attr(sanitize_text_field(@$yt_resp_array['items'][0]['snippet']['description']) );
                        $_thisLyte['captions_data'] = 'false';
                    } else {
                        $_thisLyte['title']         = esc_attr( sanitize_text_field( @$yt_resp_array['items'][0]['snippet']['title'] ) );
                        $_thisLyte['thumbUrl']      = esc_url( @$yt_resp_array['items'][0]['snippet']['thumbnails']['high']['url'] );
                        $_thisLyte['HQthumbUrl']    = esc_url( @$yt_resp_array['items'][0]['snippet']['thumbnails']['maxres']['url'] );
                        $_thisLyte['dateField']     = sanitize_text_field( @$yt_resp_array['items'][0]['snippet']['publishedAt'] );
                        $_thisLyte['duration']      = sanitize_text_field( @$yt_resp_array['items'][0]['contentDetails']['duration'] );
                        $_thisLyte['description']   = esc_attr(sanitize_text_field( @$yt_resp_array['items'][0]['snippet']['description'] ) );
                        $_thisLyte['captions_data'] = sanitize_text_field( @$yt_resp_array['items'][0]['contentDetails']['caption'] );
                    }
                }

                // try to ensure description is never empty to avoid Google structured data test tool complaining about it missing.
                if ( ! array_key_exists( 'description', $_thisLyte ) || empty( $_thisLyte['description'] ) ) {
                    $_thisLyte['description'] = $_thisLyte['title'];
                }
                $_thisLyte['description'] = apply_filters( 'lyte_ytapi_description', $_thisLyte['description'] );

                // add filter to alter what data is cached (adding/ removing).
                $_thisLyte = apply_filters( 'lyte_filter_ytapi_pre_store_results', $_thisLyte, $yt_resp_array );

                // try to cache the result
                if ( ( ( $postID ) || ( $isWidget ) ) && ! empty( $_thisLyte ) && empty( $apiTestKey ) ) {
                    $_thisLyte['lyte_date_added'] = time();

                    if ( $postID && !$isWidget ) {
                        $yt_resp_precache = json_encode( $_thisLyte );

                        // then gzip + base64 (to limit amount of data + solve problems with wordpress removing slashes)
                        $yt_resp_precache = base64_encode( gzcompress( $yt_resp_precache ) );

                        // and do the actual caching
                        $toCache = ( $yt_resp_precache ) ? $yt_resp_precache : '{{unknown}}';

                        update_post_meta( $postID, $cachekey, $toCache );
                        // and finally add new cache-entry to toCache_index which will be added to lyte_cache_index pref
                        $toCache_index[] = $cachekey;
                    } else if ( $isWidget ) {
                        $widget_cache[$vid] = $_thisLyte;
                        update_option( 'lyte_widget_cache', base64_encode( gzcompress( json_encode( $widget_cache ) ) ) );
                    }
                }
            }
        }
    }
    foreach ( array( 'title', 'thumbUrl', 'HQthumbUrl', 'dateField', 'duration', 'description', 'captions_data' ) as $key ) {
            if ( ! array_key_exists( $key, $_thisLyte ) ) {
                    $_thisLyte[$key] = '';
            }
    }
    return $_thisLyte;
}

/* only add js/css once and only if needed */
function lyte_initer() {
    global $lynited;
    if ( ! $lynited ) {
        $lynited = true;
        add_action( 'wp_footer', 'lyte_init' );
    }
}

/* actual initialization */
function lyte_init( $echo = true ) {
    global $lyteSettings;
    $lyte_css = '.lyte-wrapper-audio div, .lyte-wrapper div {margin:0px; overflow:hidden;} .lyte,.lyMe{position:relative;padding-bottom:56.25%;height:0;overflow:hidden;background-color:#777;} .fourthree .lyMe, .fourthree .lyte {padding-bottom:75%;} .lidget{margin-bottom:5px;} .lidget .lyte, .widget .lyMe {padding-bottom:0!important;height:100%!important;} .lyte-wrapper-audio .lyte{height:38px!important;overflow:hidden;padding:0!important} .lyMe iframe, .lyte iframe,.lyte .pL{position:absolute !important;top:0;left:0;width:100%;height:100%!important;background:no-repeat scroll center #000;background-size:cover;cursor:pointer} .tC{left:0;position:absolute;top:0;width:100%} .tC{background-image:linear-gradient(to bottom,rgba(0,0,0,0.6),rgba(0,0,0,0))} .tT{color:#FFF;font-family:Roboto,sans-serif;font-size:16px;height:auto;text-align:left;padding:5px 10px 50px 10px} .play{background:no-repeat scroll 0 0 transparent;width:88px;height:63px;position:absolute;left:43%;left:calc(50% - 44px);left:-webkit-calc(50% - 44px);top:38%;top:calc(50% - 31px);top:-webkit-calc(50% - 31px);} .widget .play {top:30%;top:calc(45% - 31px);top:-webkit-calc(45% - 31px);transform:scale(0.6);-webkit-transform:scale(0.6);-ms-transform:scale(0.6);} .lyte:hover .play{background-position:0 -65px;} .lyte-audio .pL{max-height:38px!important} .lyte-audio iframe{height:438px!important} .ctrl{background:repeat scroll 0 -220px rgba(0,0,0,0.3);width:100%;height:40px;bottom:0px;left:0;position:absolute;} .lyte-wrapper .ctrl{display:none}.Lctrl{background:no-repeat scroll 0 -137px transparent;width:158px;height:40px;bottom:0;left:0;position:absolute} .Rctrl{background:no-repeat scroll -42px -179px transparent;width:117px;height:40px;bottom:0;right:0;position:absolute;padding-right:10px;}.lyte-audio .play{display:none}.lyte-audio .ctrl{background-color:rgba(0,0,0,1)}.lyte .hidden{display:none}';

    // by default show lyte vid on mobile (requiring user clicking play two times)
    // but can be overruled by this filter
    // also "do lyte mobile" when option to cache thumbnails is on to ensure privacy (gdpr)
    $mobLyte = apply_filters( 'lyte_do_mobile', false );
    if ( $mobLyte || get_option( 'lyte_local_thumb', 0 ) ) {
        $mobJS = 'var mOs=null;';
    } else {
        $mobJS = 'var mOs=navigator.userAgent.match(/(iphone|ipad|ipod|android)/i);';
    }

    // if we're caching local thumbnails and filter says so, create lyteCookie cookie to prevent image hotlinking.
    if ( get_option( 'lyte_local_thumb', '0' ) === '1' && apply_filters( 'lyte_filter_local_thumb_doublecheck', false ) ) {
        $doublecheck_thumb_cookie = 'document.cookie="lyteCookie=1;path=/;samesite=strict";';
    } else {
        $doublecheck_thumb_cookie = '';
    }

    /** API: filter hook to change css */
    $lyte_css = apply_filters( 'lyte_css', $lyte_css );


    /** using esc_js on script tags breaks the JS as quotes are escaped, seems esc_js indeed is only to be used for onclick/ onfocus/ ... attributes.
    echo '<script id="fgo1">' . esc_js('alert("boe")') . '</script>'; -> <script id="fgo1">alert(&quot;boe&quot;)</script>
    echo '<script id="fgo2">' . esc_js("alert('boe')") . '</script>'; -> <script id="fgo2">alert(\'boe\')</script>
    */
    
    $inline_js = '<script type="text/javascript" data-cfasync="false">var bU="' . $lyteSettings['path'] . '";' . $mobJS . $doublecheck_thumb_cookie . 'style = document.createElement("style");style.type = "text/css";rules = document.createTextNode("' . $lyte_css . '");if(style.styleSheet) { style.styleSheet.cssText = rules.nodeValue;} else {style.appendChild(rules);}document.getElementsByTagName("head")[0].appendChild(style);</script>';
    $linked_js = '<script type="text/javascript" data-cfasync="false" async src="' . $lyteSettings['path'] . $lyteSettings['file'] . '"></script>';
    if ( $lyteSettings['lyte_use_internal_lazyloader'] ) {
        $linked_js .= '<script type="text/javascript" data-cfasync="false" async src="'.$lyteSettings['path'].$lyteSettings['file_lazyload'].'"></script>';
    }

    if ( false !== $echo ) {
        echo $inline_js . $linked_js;
    } else {
        return $inline_js . $linked_js;
    }
}

/** override default wp_trim_excerpt to have lyte_parse remove the httpv-links */
function lyte_trim_excerpt($text = '', $post = null) {
	$raw_excerpt = $text;

	if ( '' === trim( $text ) ) {
		$post = get_post( $post );
		$text = get_the_content( '', false, $post );
        $text = lyte_parse($text, true);
        $text = strip_shortcodes( $text );
        if ( function_exists( 'excerpt_remove_blocks' ) ) {
            $text = excerpt_remove_blocks( $text );
        }
		$text           = apply_filters( 'the_content', $text );
		$text           = str_replace( ']]>', ']]&gt;', $text );
		$excerpt_length = intval( _x( '55', 'excerpt_length' ) );
		$excerpt_length = (int) apply_filters( 'excerpt_length', $excerpt_length );
		$excerpt_more   = apply_filters( 'excerpt_more', ' ' . '[&hellip;]' );
		$text           = wp_trim_words( $text, $excerpt_length, $excerpt_more );
	}

	return apply_filters( 'wp_trim_excerpt', $text, $raw_excerpt );
}

/** Lyte shortcode */
function shortcode_lyte( $atts ) {
    extract( shortcode_atts( array(
        'id'       => '',
        'audio'    => '',
        'playlist' => '',
        'start'    => '',
        'showinfo' => '',
        'stepsize' => '',
        'hqthumb'  => '',
    ), $atts ) );

    $qs = '';

    if ($audio) { $proto = 'httpa'; } else { $proto = 'httpv'; }
    if ( $start !== '' ) { $qs .= '&amp;start=' . $start; }
    if ( $showinfo === 'false' ) { $qs .= '&amp;showinfo=0'; }
    if ( $hqthumb ) { $qs .= '&amp;hqThumb=1'; }
    if ( $stepsize ) { $qs .= '#stepSize=' . $stepsize; }
    if ( $playlist ) { $action = 'playlist?list=';} else { $action = 'watch?v='; }

    return lyte_parse( $proto . '://www.youtube.com/' . $action . $id . $qs );
}

/** update functions */
/** upgrade, so lyte should not be greedy */
function lyte_not_greedy() {
    update_option( 'lyte_greedy', '0' );
}

/** function to flush YT responses from cache */
function lyte_rm_cache() {
    // remove thumbnail cache
    if ( get_option( 'lyte_local_thumb', '0' ) === '1' ) {
        if ( ! defined( 'LYTE_CACHE_DIR' ) ) {
            define( 'LYTE_CACHE_CHILD_DIR', 'cache/lyteCache' );
            define( 'LYTE_CACHE_DIR', WP_CONTENT_DIR .'/'. LYTE_CACHE_CHILD_DIR );
        }
        array_map('unlink', glob( LYTE_CACHE_DIR . "/*" ) );
    }

    // and remove cached YT data from postmeta
    try {
        ini_set( 'max_execution_time', 90 ); // give PHP some more time for this, post-meta can be sloooooow

        // cache in post_meta, for posts
        $lyte_posts         = json_decode( get_option( 'lyte_cache_index'), true );
        $lyteCacheIterator  = 0;
        $lytePurgeThreshold = 500;
        $returnCode         = 'OK';
        if ( is_array( $lyte_posts ) ) {
            foreach ( $lyte_posts as $postID => $lyte_post ) {
                foreach ( $lyte_post as $cachekey ) {
                    delete_post_meta( $postID, $cachekey );
                }
                unset ( $lyte_posts[$postID] );
                $lyteCacheIterator++;
                if ( $lyteCacheIterator > ( $lytePurgeThreshold-1 ) ) {
                    $returnCode = 'PART';
                    break;
                }
            }
            update_option( 'lyte_cache_index', json_encode( $lyte_posts ) );
        }

        // and the widget cache which isn't in post_meta
        update_option( 'lyte_widget_cache', '' );

        return $returnCode;
    } catch(Exception $e) {
        return $e->getMessage();
    }
}

// updating LYTE thumbnail cache directory to avoid blocking by firewalls.
function lyte_mv_cache() {
    if ( get_option( 'lyte_local_thumb', '0' ) === '1' ) {
        $old_cache_dir = WP_CONTENT_DIR . '/cache/lyteThumbs';
        $new_cache_dir = WP_CONTENT_DIR . '/cache/lyteCache';
        if ( file_exists( $old_cache_dir ) ) {
            rename( $old_cache_dir, $new_cache_dir );
        }
        add_action( 'admin_notices', 'lyte_thumbcache_moved' );
    }
}

// notice to ask people to purge page caches.
function lyte_thumbcache_moved() {
    echo '<div class="notice notice-warning"><p>';
    _e( 'WP YouTube Lyte: the name of the thumbnail cache script has changed, clear any page cache(s) you might have.', 'wp-youtube-lyte' );
    echo '</p></div>';
}

/** function to call from within themes */
/* use with e.g. : <?php if(function_exists('lyte_preparse')) { echo lyte_preparse($videoId); } ?> */
function lyte_preparse( $videoId ) {
    return lyte_parse( 'httpv://www.youtube.com/watch?v=' . $videoId );
}

function lyte_add_action_link( $links ) {
    $links[] = '<a href="' . admin_url( 'options-general.php?page=lyte_settings_page' ) . '">' . __( 'Settings' ) . '</a>';
    return $links;
}

/** is_amp, but I shouldn't have to do this, should I? */
if ( ! function_exists('is_amp') ) {
    function is_amp() {
        if ( function_exists( 'is_amp_endpoint' ) ) {
            return is_amp_endpoint();
        } else if ( function_exists( 'ampforwp_is_amp_endpoint' ) ) {
            return ampforwp_is_amp_endpoint();
        } else if ( ( strpos( $_SERVER['REQUEST_URI'], '?amp' ) !== false) || ( strpos( $_SERVER['REQUEST_URI'], '/amp/' ) !== false ) ) {
            return true;
        } else {
            return false;
        }
    }
}

function lyte_prepare( $the_content ) {
    /** bail if AMP or if LYTE feed disabled and is_feed */
    if ( is_amp() || ( apply_filters( 'lyte_filter_dofeed', true ) === false && is_feed() ) ) { return str_replace( 'httpv://', 'https://', $the_content ); }

    // catch gutenberg blocks before being rendered.
    if ( apply_filters( 'lyte_filter_do_gutenberg', true ) && strpos( $the_content, '<!-- wp:' ) !== false  && strpos( $the_content, 'youtu' ) !== false ) {
        /*
         * do Gutenberg stuff here, playlists if needed first and then single videos
         *
         * having Gutenberg markup in HTML comments is ugly as hell
         * esp. for 3rd parties such as Lyte who have to parse info out of that
         *
         * Luke Cavanagh; thanks for the Gutenbeard reference and for the funny animated gif :)
         * https://media1.giphy.com/media/l2QZTNMFTQ2Z00zHG/giphy.gif
         */
        if ( strpos( $the_content, '/playlist?list=' ) !== false ) {
            $gutenbeard_playlist_regex = '%<\!--\s?wp:(?:core[-|/])?embed(?:/youtube)?\s?{"url":"https://www.youtube.com/playlist\?list=(.*)"(?:.*)?}\s?-->.*(?:(?:<figcaption>(.*)</figcaption>).*)?<\!--\s?/wp:(?:core[-|/])?embed(?:/youtube)?\s?-->%Us';
            $the_content               = preg_replace( $gutenbeard_playlist_regex, '<figure class="wp-block-embed-youtube wp-block-embed is-type-video is-provider-youtube">httpv://www.youtube.com/playlist?list=\1<figcaption>\2</figcaption></figure>', $the_content );
        }
        $gutenbeard_single_regex = '%<\!--\s?wp:(?:core[-|/])?embed(?:/youtube)?\s?{"url":"https?://(?:www\.)?youtu(?:be\.com/watch\?v=|.be/)(.*)"(?:.*)?}\s?-->.*(?:(?:<figcaption>(.*)</figcaption>).*)?<\!--\s?/wp:(?:core[-|/])?embed(?:/youtube)?\s?-->%Us';
        $the_content             = preg_replace( $gutenbeard_single_regex, '<figure class="wp-block-embed-youtube wp-block-embed is-type-video is-provider-youtube">httpv://www.youtube.com/watch?v=\1<figcaption>\2</figcaption></figure>', $the_content );
    }

    // do the most of the greedy part early.
    if ( get_option( 'lyte_greedy', '1' ) === '1' && strpos( $the_content, 'youtu' ) !== false ) {
        // only preg_replace if "youtu" (as part of youtube.com or youtu.be) is found.
        if ( strpos( $the_content, '/playlist?list=' ) !== false ) {
            // only preg_replace for playlists if there are playlists to be parsed.
            $the_content = preg_replace( '/^(?:<p>)?https?:\/\/(www.)?youtu(be.com|.be)\/playlist\?list=/m', 'httpv://www.youtube.com/playlist?list=', $the_content );
        }
        // and lastly normal single embeds.
        $the_content = preg_replace( '/^(?:<p>)?https?:\/\/(www.)?youtu(be.com|.be)\/(watch\?v=)?/m', 'httpv://www.youtube.com/watch?v=', $the_content );
    }
    return $the_content;
}

function lytecache_doublecheck_activator() {
    // image hotlinking protection: conditionally (by filter, off by default)
    // create a file telling lyteCache to check for a cookie before serving thumbnail.
    // file is also set if local thumbnail caching is not active, rendering lyteCache harmless.
    if ( ! defined( 'LYTE_CACHE_DIR' ) ) {
        define( 'LYTE_CACHE_DIR', WP_CONTENT_DIR .'/cache/lyteCache' );
    }
    if ( ! file_exists( LYTE_CACHE_DIR ) ) {
        // create LYTE cache dir (and index.html) if it doesn't exist yet.
        @mkdir( LYTE_CACHE_DIR, 0775, true );
        @file_put_contents( LYTE_CACHE_DIR . '/index.html', '<html><head><meta name="robots" content="noindex, nofollow"></head><body>Generated by <a href="http://wordpress.org/extend/plugins/wp-youtube-lyte/" rel="nofollow">WP YouTube Lyte</a></body></html>' );
    }

    $_doublecheck_activator_file = LYTE_CACHE_DIR . '/doubleCheckLyteThumbnailCache.txt';
    if ( ( get_option( 'lyte_local_thumb', '0' ) === '1' && apply_filters( 'lyte_filter_local_thumb_doublecheck', false ) ) || get_option( 'lyte_local_thumb', '0' ) !== '1' ) {
        if ( ! file_exists( $_doublecheck_activator_file ) ) {
            // file needed but not found, create it.
            @file_put_contents( $_doublecheck_activator_file, 'This file is used to ensure lyteCache.php is not abused (prevent hotlinking of cached YouTube thumbnails or lyteCache.php being accessed when local thumbnail caching is not active).' );
        }
    } elseif ( file_exists( $_doublecheck_activator_file ) ) {
        // file exists but not needed (any more), delete it.
        @unlink( $_doublecheck_activator_file );
    }

    $_disable_thumb_fallback_file = LYTE_CACHE_DIR . '/disableThumbFallback.txt';
     if ( ( get_option( 'lyte_local_thumb', '0' ) === '1' && apply_filters( 'lyte_filter_disable_thumb_fallback', false ) ) || get_option( 'lyte_local_thumb', '0' ) !== '1' ) {
        if ( ! file_exists( $_disable_thumb_fallback_file ) ) {
            // file needed but not found, create it.
            @file_put_contents( $_disable_thumb_fallback_file, 'This file is used to ensure lyteCache.php will never redirect the client to the YouTube servers, even if there is an error serving or caching the thumbnail).' );
        }
    } elseif ( file_exists( $_disable_thumb_fallback_file ) ) {
        // file exists but not needed (any more), delete it.
        @unlink( $_disable_thumb_fallback_file );
    }
}

/** hooking it all up to wordpress */
if ( is_admin() ) {
    require_once(dirname(__FILE__).'/options.php');
    add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'lyte_add_action_link' );
    add_action( 'admin_init', 'lytecache_doublecheck_activator' );
} else {
    add_filter( 'the_content', 'lyte_prepare', 4 );
    add_filter( 'the_content', 'lyte_parse', 10 );
    add_shortcode( 'lyte', 'shortcode_lyte' );
    remove_filter( 'get_the_excerpt', 'wp_trim_excerpt' );
    add_filter( 'get_the_excerpt', 'lyte_trim_excerpt', 10, 2 );
    add_action( 'init', 'lytecache_doublecheck_activator' );

    /** API: action hook to allow extra actions or filters to be added */
    do_action('lyte_actionsfilters');
}
?>
