# Template-Engine
Small &amp; fast php template engine

## Thanks
Template **regex function** &amp; **cache method** with big thanks to **[TXGZ](https://github.com/txgz999)**

## Usage
Now you can choose saving version of template file to local or database  

Save to local
```php
//Template setting
$options = array(
    'template_dir' => 'template',
    'css_dir' => 'static/css/', //Set css file's cache
    'js_dir' => 'static/js/', //Set js file's cache
    'cache_dir' => 'cache',
    'cache_db' => false
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
    'cache_dir' => 'cache',
    'cache_db' => $connectdb
);
```
## Cache CSS &amp; JS File
#### CSS
html
```html
<link href="{loadcss common.css}" rel="stylesheet" type="text/css">
```
Output:
```html
<link href="static/css/common.css?v=Ad0Dwf8" rel="stylesheet" type="text/css">
```

#### JS
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
