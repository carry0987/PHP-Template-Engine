<?php
require dirname(__FILE__).'/class/class_template.php';
//Template setting
$options = array(
    'template_dir' => 'template',
    'css_dir' => 'static/css/', //Set css file's cache
    'js_dir' => 'static/js/', //Set js file's cache
    'cache_dir' => 'cache',
    'auto_update' => true,
    'cache_lifetime' => 0,
);

$template = Template::getInstance();
$template->setOptions($options);

$array = array('testa' => 'a', 'testb' => 'b');
//Include template file
include($template->loadTemplate('template.html'));

