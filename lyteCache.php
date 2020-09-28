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

// no error reporting, those break header() output
error_reporting(0);

/* 
 * step 0: set constant for dir where thumbs are stored + declaring some variables
 */

if ( ! defined( 'LYTE_CACHE_DIR' ) ) {
    define( 'WP_CONTENT_DIR', dirname( dirname( __DIR__ ) ) );
    define( 'LYTE_CACHE_CHILD_DIR', 'cache/lyteCache' );
    define( 'LYTE_CACHE_DIR', WP_CONTENT_DIR .'/'. LYTE_CACHE_CHILD_DIR );
}

$lyte_thumb_error = '';
$lyte_thumb_dontsave = '';
$thumbContents = '';
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
 * step 3: check for and if need be create wp-content/cache/lyte_thumbs
 */

if ( lyte_check_cache_dir( LYTE_CACHE_DIR ) === false ) {
    $lyte_thumb_dontsave = true;
    $lyte_thumb_error .= 'checkcache fail/ ';
}

/* 
 * step 4: if not in cache: fetch from YT and store in cache
 */

if ( strpos($origThumbURL,'http') !== 0 && strpos($origThumbURL,'//') === 0 ) {
    $origThumbURL = 'https:'.$origThumbURL;
}

$localThumb = LYTE_CACHE_DIR . '/' . md5($origThumbURL) . '.jpg';
$expiryTime = filemtime( $localThumb ) + 3*24*60*60; // images can be cached for 3 days.
$now        = time();

if ( !file_exists( $localThumb ) || $lyte_thumb_dontsave || ( file_exists( $localThumb ) && $expiryTime < $now ) ) {
    $thumbContents = lyte_get_thumb( $origThumbURL );
    
    if ( $thumbContents != '' && ! $lyte_thumb_dontsave ) {
        // save file but immediately check if it is a jpeg and delete if not.
        file_put_contents( $localThumb, $thumbContents );
        if ( ! is_jpeg( $localThumb ) ) {
            unlink( $localThumb );
            $thumbContents = '';
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
    if ( $lyte_thumb_error !== '' && $lyte_thumb_report_err ) {
        header('X-lyte-error:  '.$lyte_thumb_error);
    }

    $modTime = filemtime($localThumb);

    date_default_timezone_set('UTC');
    $modTimeMatch = ( isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) && strtotime( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) === $modTime );

    if ( $modTimeMatch ) {
        header('HTTP/1.1 304 Not Modified');
        header('Connection: close');
    } else {
        // send all sorts of headers
        $expireTime = 60 * 60 * 24 * 7; // 1w
        header( 'Content-Length: '. strlen( $thumbContents) );
        header( 'Cache-Control: max-age=' . $expireTime . ', public, immutable' );
        header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $expireTime).' GMT' );
        header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $modTime) . ' GMT' );
        header( 'Content-type:image/jpeg' );
        echo $thumbContents;
    }
} else {
    $lyte_thumb_error .= 'no thumbContent/ ';
    lyte_thumb_fallback();
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
    // Try creating the dir if it doesn't exist.
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
        curl_close( $curl );
        if ( ! $err && $str != '' ) {
            return $str;
        } else {
            $lyte_thumb_error .= 'curl err: ' . $err . '/ ';
        }
    } else {
        $lyte_thumb_error .= 'no curl/ ';
    }

    // if no curl or if curl error.
    $resp = file_get_contents( $thumbUrl );
    return $resp;
}

function get_origThumbURL() {
    $invalid = false;

    // get thumbnail-url from request
    if ( array_key_exists( 'origThumbUrl', $_GET ) && $_GET['origThumbUrl'] !== '' ) {
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
    if ( strpos( $origThumbURL, 'http' ) !== 0) {
            $origThumbURL = 'https:' . $origThumbURL;              
    }
    if ( $lyte_thumb_report_err ) {
        header('X-lyte-error:  '.$lyte_thumb_error);
    }
    header('HTTP/1.1 301 Moved Permanently');
    header('Location:  '.  $origThumbURL );
}
