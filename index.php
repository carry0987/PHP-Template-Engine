<?php
require dirname(__FILE__).'/class/class_template.php';

//Template setting
$options = array(
    'template_dir' => 'template',
    'css_dir' => 'static/css/', //Set css file's directory
    'js_dir' => 'static/js/', //Set js file's directory
    'cache_dir' => 'cache', //Set cache file's directory
    'auto_update' => true, //Set 'false' to turn off auto update template
    'cache_lifetime' => 0, //Set cache file's lifetime (minute)
    'cache_db' => false //Set 'false' to save cache version at local directory
    //'cache_db' => $connectdb
);

$template = Template::getInstance();
$template->setOptions($options);
$template->compressHTML(false);

$array = array('testa' => 'a', 'testb' => 'b');
//Include template file
include($template->loadTemplate('template.html'));
