<?php

$plugin_dir = basename(dirname(__FILE__)).'/languages';
load_plugin_textdomain( 'wp-youtube-lyte', false, $plugin_dir );

$pDefault=2;

$pSize[8]['a']=true;
$pSize[8]['w']=420;
$pSize[8]['h']=236;
$pSize[8]['t']=__("Mini 16:9 player","wp-youtube-lyte");
$pSize[8]['f']="169";

$pSize[0]['a']=true;
$pSize[0]['w']=420;
$pSize[0]['h']=315;
$pSize[0]['t']=__("Smaller 4:3 player","wp-youtube-lyte");
$pSize[0]['f']="43";

$pSize[1]['a']=true;
$pSize[1]['w']=560;
$pSize[1]['h']=315;
$pSize[1]['t']=__("Smaller 16:9 player","wp-youtube-lyte");
$pSize[1]['f']="169";

$pSize[2]['a']=true;
$pSize[2]['w']=480;
$pSize[2]['h']=360;
$pSize[2]['t']=__("Standard value, YouTube default for 4:3-ratio video","wp-youtube-lyte");
$pSize[2]['f']="43";

$pSize[3]['a']=true;
$pSize[3]['w']=640;
$pSize[3]['h']=360;
$pSize[3]['t']=__("YouTube default for 16:9-ratio video","wp-youtube-lyte");
$pSize[3]['f']="169";

$pSize[4]['a']=true;
$pSize[4]['w']=640;
$pSize[4]['h']=480;
$pSize[4]['t']=__("Larger 4:3 player","wp-youtube-lyte");
$pSize[4]['f']="43";

$pSize[5]['a']=true;
$pSize[5]['w']=853;
$pSize[5]['h']=480;
$pSize[5]['t']=__("Larger 16:9 player","wp-youtube-lyte");
$pSize[5]['f']="169";

$pSize[6]['a']=true;
$pSize[6]['w']=960;
$pSize[6]['h']=720;
$pSize[6]['t']=__("Maxi 4:3 player","wp-youtube-lyte");
$pSize[6]['f']="43";

$pSize[7]['a']=true;
$pSize[7]['w']=1280;
$pSize[7]['h']=720;
$pSize[7]['t']=__("Maxi 16:9 player","wp-youtube-lyte");
$pSize[7]['f']="169";

$pSizeOrder['169']=array(8,1,3,5,7);
$pSizeOrder['43']=array(0,2,4,6);

// widget sizes
$wDefault=4;
$wSize[1]['h']=125;
$wSize[1]['w']=150;
$wSize[1]['depr']=true;
$wSize[2]['h']=133;
$wSize[2]['w']=160;
$wSize[2]['depr']=true;
$wSize[3]['h']=150;
$wSize[3]['w']=180;
$wSize[3]['depr']=true;
$wSize[4]['h']=200;
$wSize[4]['w']=200;
$wSize[4]['depr']=false;
$wSize[5]['h']=200;
$wSize[5]['w']=250;
$wSize[5]['depr']=false;
$wSize[6]['h']=225;
$wSize[6]['w']=300;
$wSize[6]['depr']=false;
$wSize[7]['h']=300;
$wSize[7]['w']=400;
$wSize[7]['depr']=false;

// bigger widgets, for use in pagebuilders rather then sidebars
$wSize[8]['h']=400;
$wSize[8]['w']=711;
$wSize[8]['depr']=false;
$wSize[9]['h']=500;
$wSize[9]['w']=889;
$wSize[9]['depr']=false;
$wSize[10]['h']=600;
$wSize[10]['w']=1066;
$wSize[10]['depr']=false;
?>
