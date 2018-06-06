<?php
class WYLWidget extends WP_Widget {
    public function __construct() {
        parent::__construct(false, $name = 'WP YouTube Lyte');
    }

    function widget($args, $instance) {
        extract( $args );
        global $wSize, $wyl_version, $wp_lyte_plugin_url, $lyteSettings;
        $lyteSettings['path']= plugins_url() . "/" . dirname(plugin_basename(__FILE__)) . '/lyte/';
        $qsa="";

        $WYLtitle = apply_filters('widget_title', $instance['WYLtitle']);
        $WYLtext = apply_filters( 'widget_text', $instance['WYLtext'], $instance );
        $WYLsize = apply_filters( 'widget_text', $instance['WYLsize'], $instance );
        if ($WYLsize=="") $WYLsize=$wDefault;
        $WYLaudio = apply_filters( 'widget_text', $instance['WYLaudio'], $instance );
        if ($WYLaudio!=="audio") {
            $wrapperClass = " lidget";
            $audioClass = "";
            $wHeight = $wSize[$WYLsize]['h'];
        } else {
            $wrapperClass = "-audio lidget";
            $audioClass = " lyte-audio";
            $wHeight = "38";
        }
        $WYLurl=str_replace("httpv://","https://",trim($instance['WYLurl']));
        $WYLqs=substr(strstr($WYLurl,'?'),1);
        parse_str($WYLqs,$WYLarr);

        if (strpos($WYLurl,'youtu.be')) {
            $WYLid=substr(parse_url($WYLurl,PHP_URL_PATH),1,11);
            $PLClass="";
            $WYLthumb="https://img.youtube.com/vi/".$WYLid."/hqdefault.jpg";
        } else {
            if (isset($WYLarr['v'])) {
                $WYLid=$WYLarr['v'];
                $PLClass="";
                $WYLthumb="https://img.youtube.com/vi/".$WYLid."/hqdefault.jpg";
            } else if (isset($WYLarr['list'])) {
                $WYLid=$WYLarr['list'];
                $yt_resp=lyte_get_YT_resp($WYLid,true,"","",true);
                $WYLthumb=$yt_resp["thumbUrl"];
                $PLClass=" playlist";
            }
        }

        // do we have to serve the thumbnail from local cache?
        if (get_option('lyte_local_thumb','0') === '1') {
                $WYLthumb = plugins_url( 'lyteThumbs.php?origThumbUrl=' . urlencode($WYLthumb) , __FILE__   );
        }

        // filter to alter the thumbnail
        $WYLthumb = apply_filters( "lyte_filter_widget_thumb", $WYLthumb, $WYLid );

        if (isset($WYLarr['start'])) $qsa="&amp;start=".$WYLarr['start'];
        if (isset($WYLarr['enablejsapi'])) {
            $urlArr=parse_url($lyteSettings['path']);
            $origin=$urlArr[scheme]."://".$urlArr[host]."/";
            $qsa.="&amp;enablejsapi=".$WYLarr['enablejsapi']."&amp;origin=".$origin;
        }

        if (!empty($qsa)) {
            $esc_arr=array("&" => "\&", "?" => "\?", "=" => "\=");
            $qsaClass=" qsa_".strtr($qsa,$esc_arr);
        } else {
            $qsaClass="";
        }

        $WYL_dom_id="YLW_".$WYLid;

        ?>
        <?php echo $before_widget; ?>
        <?php if ( $WYLtitle ) echo $before_title . $WYLtitle . $after_title; ?>
        <div class="lyte-wrapper<?php echo $wrapperClass; ?>" style="width:<?php echo $wSize[$WYLsize]['w']; ?>px; height:<?php echo $wHeight; ?>px; min-width:200px; max-width:100%;"><div class="lyMe<?php echo $PLClass; echo $audioClass; echo $qsaClass; ?>" id="<?php echo $WYL_dom_id; ?>"><div id="lyte_<?php echo $WYLid; ?>" data-src="<?php echo $WYLthumb;?>" class="pL"><div class="play"></div><div class="ctrl"><div class="Lctrl"></div></div></div></div><noscript><a href="https://youtu.be/<?php echo $WYLid;?>"><img src="<?php echo $WYLthumb; ?>" alt="" /></a></noscript></div>
        <div><?php echo $WYLtext ?></div>
        <?php echo $after_widget; ?>
        <?php
        lyte_initer();
    }

    function update($new_instance, $old_instance) {                
        $instance = $old_instance;
        $instance['WYLtitle'] = strip_tags($new_instance['WYLtitle']);
        $instance['WYLurl'] = strip_tags($new_instance['WYLurl']);
        $instance['WYLsize'] = strip_tags($new_instance['WYLsize']);
        $instance['WYLaudio'] = strip_tags($new_instance['WYLaudio']);

        if ( current_user_can('unfiltered_html') ) {
            $instance['WYLtext'] = $new_instance['WYLtext'];
        } else {
                $instance['WYLtext'] = stripslashes( wp_filter_post_kses( addslashes($new_instance['WYLtext']) ) );
        }

        return $instance;
    }

    function form($instance) {
        global $wSize, $wDefault;

        if (isset($instance['WYLtitle'])) {
            $WYLtitle = esc_attr($instance['WYLtitle']);
        } else {
            $WYLtitle = "";
        }

        if (isset($instance['WYLurl'])) {
            $WYLurl = esc_attr($instance['WYLurl']);
        } else {
            $WYLurl = "";
        }

        if (isset($instance['WYLtext'])) {
            $WYLtext = format_to_edit($instance['WYLtext']);
        } else {
            $WYLtext = "";
        }

        if (isset($instance['WYLaudio'])) {
            $WYLaudio = esc_attr($instance['WYLaudio']);
        } else {
            $WYLaudio = "";
        }
        if ($WYLaudio!=="audio") $WYLaudio="";

        if (isset($instance['WYLsize'])) {
            $WYLsize = esc_attr($instance['WYLsize']);
        } else {
            $WYLsize = "";
        }

        if ($WYLsize=="") $WYLsize=$wDefault;
        ?>
        <p><label for="<?php echo $this->get_field_id('WYLtitle'); ?>"><?php _e("Title:","wp-youtube-lyte") ?> <input class="widefat" id="<?php echo $this->get_field_id('WYLtitle'); ?>" name="<?php echo $this->get_field_name('WYLtitle'); ?>" type="text" value="<?php echo $WYLtitle; ?>" /></label></p>
        <p><label for="<?php echo $this->get_field_id('WYLsize'); ?>"><?php _e("Size:","wp-youtube-lyte") ?>
        <select class="widefat" id="<?php echo $this->get_field_id('WYLsize'); ?>" name="<?php echo $this->get_field_name('WYLsize'); ?>">
            <?php
                foreach ($wSize as $x => $size) {
                    if ($x==$WYLsize) {
                        $selected=" selected=\"true\"";
                    } else {
                        $selected="";
                    }
                    
                    if ($wSize[$x]['depr']!==true) {
                        echo "<option value=\"".$x."\"".$selected.">".$wSize[$x]['w']."X".$wSize[$x]['h']."</option>";
                    }
                    $x++;
                }
            ?>
        </select>
        </label></p>
        <p><label for="<?php echo $this->get_field_id('WYLaudio'); ?>"><?php _e("Type:","wp-youtube-lyte") ?>
                <select class="widefat" id="<?php echo $this->get_field_id('WYLaudio'); ?>" name="<?php echo $this->get_field_name('WYLaudio'); ?>">
                <?php
                if($WYLaudio==="audio") {
                    $aselected=" selected=\"true\"";
                    $vselected="";
                } else {
                    $vselected=" selected=\"true\"";
                    $aselected="";
                }
                echo "<option value=\"audio\"".$aselected.">".__("audio","wp-youtube-lyte")."</option>";
                echo "<option value=\"video\"".$vselected.">".__("video","wp-youtube-lyte")."</option>";
                ?>
                </select>
            </label></p>
        <p><label for="<?php echo $this->get_field_id('WYLurl'); ?>"><?php _e("Youtube-URL:","wp-youtube-lyte") ?> <input class="widefat" id="<?php echo $this->get_field_id('WYLurl'); ?>" name="<?php echo $this->get_field_name('WYLurl'); ?>" type="text" value="<?php echo $WYLurl; ?>" /></label></p>
        <p><label for="<?php echo $this->get_field_id('WYLtext'); ?>"><?php _e("Text:","wp-youtube-lyte") ?> <textarea class="widefat" id="<?php echo $this->get_field_id('WYLtext'); ?>" name="<?php echo $this->get_field_name('WYLtext'); ?>" rows="16" cols="20"><?php echo $WYLtext; ?></textarea></label></p>
        <?php 
    }
} 

function lyte_register_widget() {
    register_widget('WYLWidget');
}

add_action('widgets_init', 'lyte_register_widget');
