<?php

require plugin_dir_path(__FILE__) . "settings-defaults.php";

/* Global settings: general */
$ab_setting_displayauthorboxonposts = get_option("ab_setting_displayauthorboxonposts", $ab_setting_default_displayauthorboxonposts);
$ab_setting_displayauthorboxonpages = get_option("ab_setting_displayauthorboxonpages", $ab_setting_default_displayauthorboxonpages);
$ab_setting_hidewordpressauthorbox = get_option("ab_setting_hidewordpressauthorbox", $ab_setting_default_hidewordpressauthorbox);

/* Global settings: layout */
$ab_setting_font = get_option("ab_setting_font", $ab_setting_default_font);
$ab_setting_showshadow = get_option("ab_setting_showshadow", $ab_setting_default_showshadow);
$ab_setting_showborder = get_option("ab_setting_showborder", $ab_setting_default_showborder);
$ab_setting_bordercolor = get_option("ab_setting_bordercolor", $ab_setting_default_bordercolor);
$ab_setting_bordersize = get_option("ab_setting_bordersize", $ab_setting_default_bordersize);
$ab_setting_avatarsize = get_option("ab_setting_avatarsize", $ab_setting_default_avatarsize);
$ab_setting_circleavatar = get_option("ab_setting_circleavatar", $ab_setting_default_circleavatar);
$ab_setting_headline = get_option("ab_setting_headline", $ab_setting_default_headline);
$ab_setting_fontsizeheadline = get_option("ab_setting_fontsizeheadline", $ab_setting_default_fontsizeheadline);
$ab_setting_fontsizeposition = get_option("ab_setting_fontsizeposition", $ab_setting_default_fontsizeposition);
$ab_setting_fontsizebio = get_option("ab_setting_fontsizebio", $ab_setting_default_fontsizebio);
$ab_setting_fontsizelinks = get_option("ab_setting_fontsizelinks", $ab_setting_default_fontsizelinks);
$ab_setting_displayauthorsarchive = get_option("ab_setting_displayauthorsarchive", $ab_setting_default_displayauthorsarchive);

?>