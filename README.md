# Template-Engine
Small &amp; fast php template engine

## Requires
PHP 7.0 or newer

## Features
- Support pure html as template
- Support CSS, JS file cache
- Support CSS model cache
- Auto minify CSS cache
- Cache lifetime

## Usage
Now you can choose saving version of template file to local or database  

Save to local
```php
//Template setting
$options = array(
    'template_dir' => 'template',
    'css_dir' => 'static/css/', //Set css file's cache
    'js_dir' => 'static/js/', //Set js file's cache
    'auto_update' => true, //Set 'false' to turn off auto update template
    'cache_lifetime' => 0, //Set cache file's lifetime (minute)
    'cache_db' => false //Set 'false' to save cache version at local directory
);
```
Save to database
```php
//Connect to Database
$connectdb = new mysqli('localhost', 'root', 'root', 'template');

//Template setting
$options = array(
    'template_dir' => 'template',
    'css_dir' => 'static/css/', //Set css file's cache
    'js_dir' => 'static/js/', //Set js file's cache
    'auto_update' => true, //Set 'false' to turn off auto update template
    'cache_lifetime' => 0, //Set cache file's lifetime (minute)
    'cache_db' => $connectdb //Give connection variable to save cache version into database
);
```
## Cache CSS &amp; JS File
#### CSS Cache
Cache specific part of CSS  
html
```html
<link href="{loadcss common.css index}" rel="stylesheet" type="text/css">
```
You can also use variable as `specific part`
```html
<!--{eval $current_page = basename($_SERVER['SCRIPT_NAME'], '.php')}-->
<link href="{loadcss common.css $current_page}" rel="stylesheet" type="text/css">
```

CSS
```css
/*[index]*/
.header {
    display: block;
}

.link {
    color: blue;
}
/*[/index]*/
```
Output:
```html
<link href="cache/common_index.css?v=Ad0Dwf8" rel="stylesheet" type="text/css">
```

Directly cache CSS file  
html
```html
<link href="{loadcss common.css}" rel="stylesheet" type="text/css">
```
Output:
```html
<link href="static/css/common.css?v=Ad0Dwf8" rel="stylesheet" type="text/css">
```

#### JS Cache
html
```html
<script src="{loadjs jquery.min.js}" type="text/javascript"></script>
```
Output:
```html
<script src="static/js/jquery.min.js?v=B22PE8W" type="text/javascript"></script>
```

## Functions
#### **`echo`** function
html
```html
<span>{$value}</span>
```
PHP
```php
<span><?php echo $value; ?></span>
```

#### **`assign variable`** function
>Note: don't put any php script into **`block`** tag

html
```html
<!--{block test}-->
<span>html content</span>
<!--{/block}-->
```
PHP
```php
<?php
$test = <<<EOF

<span>html content</span>

EOF;
?>
```

#### **`if`** function
html
```html
<!--{if expr1}-->
    statement1
<!--{elseif expr2}-->
    statement2
<!--{else}-->
    statement3
<!--{/if}-->
```
PHP
```php
<?php if(expr1) { ?>
    statement1
<?php } elseif(expr2) { ?>
    statement2
<?php } else { ?>
    statement3
<?php } ?>
```

#### **`loop`** function (without key)
html
```html
<!--{loop $array $value}-->
    <span>username</span>
<!--{/loop}-->
```
PHP
```php
<?php foreach($array as $value) {?>
    <span>username</span>
<?php } ?>
```

#### **`loop`** function (with key)
html
```html
<!--{loop $array $key $value}-->
    <span>{$key} = {$value}</span>
<!--{/loop}-->
```
PHP
```php
<?php foreach($array as $key => $value) {?>
    <span><?php echo $key; ?> = <?php echo $value; ?></span>
<?php } ?>
```

#### **`eval`** function
html
```html
<!--{eval $value = 1+2}-->
<span>{$value}</span>
```
PHP
```php
<?php eval $value = 1+2;?>
<span><?php echo $value; ?></span>
```

## Thanks
Template **regex function** &amp; **cache method** with big thanks to **[TXGZ](https://github.com/txgz999)**
