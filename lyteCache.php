<?php
/*
 * simple proxy for YouTube images
 * 
 * @param string origThumbUrl
 * @return image
 * 
 * assumption 1: thumbnails are served from known domain ("ytimg.com","youtube.com","youtu.be")
 * assumption 2: thumbnails are always jpg
 * 
 */

// no error reporting, those break header() output but only if standalone to avoid impact on other plugins.
if ( ! defined( 'ABSPATH' ) ) {
    error_reporting( 0 );
}

// include our custom configuration file in case it exists
$wp_root_path = dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
if ( file_exists( $wp_root_path . 'lyteCache-config.php' ) ) {
    require_once( $wp_root_path . 'lyteCache-config.php' );
}

/* 
 * step 0: set constant for dir where thumbs are stored + declaring some variables
 */

if ( ! defined( 'LYTE_CACHE_DIR' ) ) {
    define( 'WP_CONTENT_DIR', dirname( dirname( __DIR__ ) ) );
    define( 'LYTE_CACHE_CHILD_DIR', 'cache/lyteCache' );
    define( 'LYTE_CACHE_DIR', WP_CONTENT_DIR .'/'. LYTE_CACHE_CHILD_DIR );
}

$lyte_thumb_error      = '';
$lyte_thumb_dontsave   = '';
$thumbContents         = '';
$lyte_thumb_report_err = false;

/*
 * step 1: get vid ID (or full thumbnail URL) from request and validate
 */

$origThumbURL = get_origThumbUrl();

// should we output debug info in a header?
if ( array_key_exists( 'reportErr', $_GET ) ) {
    $lyte_thumb_report_err = true;
}

/*
 * step 2: check if it is safe to serve from cache and redirect if not.
 */
if ( ! file_exists( LYTE_CACHE_DIR ) || ( file_exists( LYTE_CACHE_DIR . '/doubleCheckLyteThumbnailCache.txt' ) && ! array_key_exists( 'lyteCookie', $_COOKIE ) ) ) {
    // local thumbnail caching not on or no cookie found, redirect to original at youtube.
    $lyte_thumb_error = 'possible hotlinker/';
    lyte_thumb_fallback();
}

/*
 * step 3: check for directory wp-content/cache/lyteCache
 */

if ( lyte_check_cache_dir( LYTE_CACHE_DIR ) === false ) {
    $lyte_thumb_dontsave = true;
    $lyte_thumb_error   .= 'checkcache fail/ ';
}

/* 
 * step 4: if not in cache: fetch from YT and store in cache
 */

if ( strpos( $origThumbURL, 'http' ) !== 0 && strpos( $origThumbURL, '//' ) === 0 ) {
    $origThumbURL = 'https:' . $origThumbURL;
}

$localThumb = LYTE_CACHE_DIR . '/' . md5( $origThumbURL ) . '.jpg';
$expiryTime = filemtime( $localThumb ) + 3 * 24 * 60 * 60; // images can be cached for 3 days.
$now        = time();

if ( ! file_exists( $localThumb ) || $lyte_thumb_dontsave || ( file_exists( $localThumb ) && $expiryTime < $now ) ) {
    $thumbContents = lyte_get_thumb( $origThumbURL );

    if ( $thumbContents != '' && ! $lyte_thumb_dontsave ) {
        // save file but immediately check if it is a jpeg and delete if not.
        file_put_contents( $localThumb, $thumbContents );
        if ( ! is_jpeg( $localThumb ) ) {
            unlink( $localThumb );
            $thumbContents     = '';
            $lyte_thumb_error .= 'deleted as not jpeg/ ';
        }
    }
}

/*
 * step 5: serve img
 */

if ( $thumbContents == '' && ! $lyte_thumb_dontsave && file_exists( $localThumb ) && is_jpeg( $localThumb ) ) {
    $thumbContents = file_get_contents( $localThumb );
} else {
    $lyte_thumb_error .= 'not from cache/ ';
}

if ( $thumbContents != '') {
    lyte_output_image( $thumbContents );
} else {
    $lyte_thumb_error .= 'no thumbContent/ ';
    lyte_thumb_fallback();
}

function lyte_output_image( $thumbContents, $contentType = 'image/jpeg' ) {
    global $lyte_thumb_error, $lyte_thumb_report_err;

    if ( $lyte_thumb_error !== '' && $lyte_thumb_report_err ) {
        header('X-lyte-error:  '.$lyte_thumb_error);
    }

    $modTime = filemtime( $localThumb );

    date_default_timezone_set( 'UTC' );
    $modTimeMatch = ( isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) && strtotime( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) === $modTime );

    if ( $modTimeMatch ) {
        header( 'HTTP/1.1 304 Not Modified' );
        header( 'Connection: close' );
    } else {
        // send all sorts of headers
        if ( ! defined( 'LYTE_CACHE_EXPIRE_TIME' ) ) {
            $expireTime = 60 * 60 * 24 * 7; // 1w
        } else {
            $expireTime = LYTE_CACHE_EXPIRE_TIME;
        }
        header( 'Content-Length: '. strlen( $thumbContents) );
        header( 'Cache-Control: max-age=' . $expireTime . ', public, immutable' );
        header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $expireTime ).' GMT' );
        header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $modTime ) . ' GMT' );
        header( 'Content-Type: ' . $contentType );
        echo $thumbContents;
    }
    exit;
}

/*
 * helper functions
 */
function is_jpeg( $in ) {
    // reliable checks based on exif/ mime type.
    if ( function_exists( 'exif_imagetype' ) && exif_imagetype( $in ) === IMAGETYPE_JPEG ) {
        return true;
    } else if ( function_exists( 'mime_content_type' ) && mime_content_type( $in ) === 'image/jpeg' ) {
        return true;
    }

    // only rely on file extension if exif/mime functions are not available.
    if ( ! function_exists( 'exif_imagetype' ) && ! function_exists( 'mime_content_type' ) && ( strpos( $in, '.jpg' ) === strlen( $in ) -4 ) ) {
        return true;
    } else {
        return false;
    }
}

function lyte_check_cache_dir( $dir ) {
    if ( ! file_exists( $dir ) || ! is_writable( $dir ) ) {
        return false;
    }

    return true;
}

function lyte_get_thumb( $thumbUrl ) {
    global $lyte_thumb_error;
    if ( function_exists( 'curl_init' ) ) {
        $curl = curl_init();
        curl_setopt( $curl, CURLOPT_URL, $thumbUrl );
        curl_setopt( $curl, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.2; .NET CLR 1.1.4322; .NET CLR 2.0.50727)');
        curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 5 );
        $str = curl_exec( $curl );
        $err = curl_error( $curl );
        
        // fallback to hqdefault.jpg if maxresdefault.jpg is missing
        $info = curl_getinfo($curl);
        if ( 404 === $info['http_code'] && false !== strpos( $thumbUrl, 'maxresdefault.jpg' ) ) {
            curl_setopt( $curl, CURLOPT_URL, str_replace( 'maxresdefault.jpg', 'hqdefault.jpg', $thumbUrl ) );
            $str = curl_exec( $curl );
            $err = curl_error( $curl );
        }
        
        curl_close( $curl );
        if ( ! $err && $str != '' ) {
            return $str;
        } else {
            $lyte_thumb_error .= 'curl err: ' . $err . '/ ';
        }
    } else {
        $lyte_thumb_error .= 'no curl/ ';
    }

    // if no curl or if curl error
    // consider switching to alternative approach (fsockopen/ streams)?
    return file_get_contents( $thumbUrl );
}

function get_origThumbURL() {
    $invalid = false;

    // if origThumbUrl has no scheme prepend it with "https:" (else filter_var fails).
    if ( 0 === strpos( $_GET['origThumbUrl'], '//' ) ) {
        $_GET['origThumbUrl'] = 'https:' . $_GET['origThumbUrl'];
    }

    // get thumbnail-url from request.
    if ( array_key_exists( 'origThumbUrl', $_GET ) && $_GET['origThumbUrl'] !== '' && 0 === strpos( $_GET['origThumbUrl'], 'https://' ) && false !== filter_var( $_GET['origThumbUrl'], FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED ) ) {
        $origThumbURL = urldecode( $_GET['origThumbUrl'] );
    } else {
        $invalid = true;
    }

    // break URL in parts to investigate.
    $origThumbURL_parts = parse_url( $origThumbURL );

    // make sure the thumbnail-domain is for youtube.
    $origThumbDomain = $origThumbURL_parts['host'];
    if ( ! $invalid && str_replace( array( 'ytimg.com','youtube.com','youtu.be' ), '', $origThumbDomain ) === $origThumbDomain ) {
        $invalid = true;
    }

    // and make sure the thumbnail-url is for an image (.jpg)
    $origThumbPath = $origThumbURL_parts['path'];
    if ( ! $invalid && strpos( $origThumbPath, '.jpg' ) !== strlen( $origThumbPath ) - 4 ) {
        $invalid = true;
    }

    // one of the above checks was not OK, so replace with fallback thumb URL (grey background).
    if ( $invalid ) {
        $origThumbURL = 'https://i.ytimg.com/vi/thisisnotavalidvid/hqdefault.jpg';
    }
    
    return $origThumbURL;
}

function lyte_thumb_fallback() {
    global $origThumbURL, $lyte_thumb_error, $lyte_thumb_report_err;
    // if for any reason we can't show a local thumbnail, we redirect to the original one

    if ( file_exists( LYTE_CACHE_DIR . '/disableThumbFallback.txt' ) ) {
        // This is a 10x10 Pixel GIF with grey background
        $thumb_error =  base64_decode('R0lGODdhCgAKAIABAMzMzP///ywAAAAACgAKAAACCISPqcvtD2MrADs=');
        lyte_output_image( $thumb_error, 'image/gif' );
    }

    if ( strpos( $origThumbURL, 'http' ) !== 0) {
            $origThumbURL = 'https:' . $origThumbURL;
    }
    if ( $lyte_thumb_report_err ) {
        header( 'X-lyte-error:  '.$lyte_thumb_error );
    }
    // avoid caching and use a "soft" redirect, as we might have got here due to some temporary issue
    header('Cache-Control: no-cache');
    header('HTTP/1.1 302 Moved Temporarily');
    header('Location:  '.  $origThumbURL );
    exit;
}
