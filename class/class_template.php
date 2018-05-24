<?php
class Template
{
    public $replacecode = array('search' => array(), 'replace' => array());
    public $blocks = array();
    const DIR_SEP = DIRECTORY_SEPARATOR;
    protected static $instance;
    protected $options = array();

    public static function getInstance()
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->options = array(
            'template_dir' => 'templates' . self::DIR_SEP,
            'css_dir' => 'css' . self::DIR_SEP,
            'js_dir' => 'js' . self::DIR_SEP,
            'cache_dir' => 'templates' . self::DIR_SEP . 'cache' . self::DIR_SEP,
            'auto_update' => false,
            'cache_lifetime' => 0,
        );
    }

    //Set template parameter array
    public function setOptions(array $options)
    {
        foreach ($options as $name => $value) {
            $this->set($name, $value);
        }
    }

    //Set template parameter
    public function set($name, $value)
    {
        switch ($name) {
            case 'template_dir':
                $value = $this->trimPath($value);
                if (!file_exists($value)){
                    $this->throwException("Couldn't found the specified template folder \"$value\"");
                }
                $this->options['template_dir'] = $value;
                break;
            case 'css_dir':
                $value = $this->trimPath($value);
                if (!file_exists($value)) {
                    $this->throwException("Couldn't found the specified css folder \"$value\"");
                }
                $this->options['css_dir'] = $value;
                break;
            case 'js_dir':
                $value = $this->trimPath($value);
                if (!file_exists($value)) {
                    $this->throwException("Couldn't found the specified js folder \"$value\"");
                }
                $this->options['js_dir'] = $value;
                break;
            case 'cache_dir':
                $value = $this->trimPath($value);
                if (!file_exists($value)){
                    $this->throwException("Couldn't found the specified cache folder \"$value\"");
                }
                $this->options['cache_dir'] = $value;
                break;
            case 'auto_update':
                $this->options['auto_update'] = (boolean) $value;
                break;
            case 'cache_lifetime':
                $this->options['cache_lifetime'] = (float) $value;
                break;
            default:
                $this->throwException("Unknow template setting options \"$name\"");
        }
    }

    public function __set($name, $value)
    {
        $this->set($name, $value);
    }

    private function random($length, $numeric = 0)
    {
        $seed = base_convert(md5(microtime().$_SERVER['DOCUMENT_ROOT']), 16, $numeric ? 10 : 35);
        $seed = $numeric ? (str_replace('0', '', $seed).'012340567890') : ($seed.'zZ'.strtoupper($seed));
        if($numeric) {
            $hash = '';
        } else {
            $hash = chr(rand(1, 26) + rand(0, 1) * 32 + 64);
            $length--;
        }
        $max = strlen($seed) - 1;
        for($i = 0; $i < $length; $i++) {
            $hash = $hash.$seed{mt_rand(0, $max)};
        }
        return $hash;
    }

    /* Static file cache */
    //Get CSS file path
    protected function getCSSFile($file)
    {
        return $this->trimPath($this->options['css_dir'] . self::DIR_SEP . $file);
    }

    //Get CSS version file path
    protected function getCSSVersionFile($file)
    {
        $file = preg_replace('/\.[a-z0-9\-_]+$/i', '.cssversion.txt', $file);
        return $this->trimPath($this->options['cache_dir'] . self::DIR_SEP . $file);
    }

    //Store CSS version value
    protected function cssSaveVersion($file)
    {
        $cssfile = $this->getCSSFile($file);

        if (!is_readable($cssfile)) {
            $this->throwException("CSS file \"$cssfile\" not found or couldn't be opened !");
        }

        //Add md5 check
        $md5data = md5_file($cssfile);
        //Random length random()
        $verhash = $this->random(7);
        $versionContent = "$md5data\r\n$verhash";

        //Write version file
        $versionfile = $this->getCSSVersionFile($file);
        $makepath = $this->makePath($versionfile);
        if ($makepath !== true) {
            $this->throwException("Couldn't build CSS version folder \"$makepath\"");
        }
        file_put_contents($versionfile, $versionContent);
        return $verhash;
    }

    //Check CSS file's change
    protected function cssVersionCheck($file)
    {
        $versionfile = $this->getCSSVersionFile($file);
        //Get file contents
        $versionContent = file($versionfile, FILE_IGNORE_NEW_LINES);
        $md5data = $versionContent[0];
        $verhash = $versionContent[1];
        if (md5_file($this->getCSSFile($file)) != $md5data) {
            $verhash = $this->cssSaveVersion($file);
        }
        return $verhash;
    }

    //Load CSS files
    public function loadCSSFile($file)
    {
        $versionfile = $this->getCSSVersionFile($file);
        if (!file_exists($versionfile)) {
            $this->cssSaveVersion($file);
        }
        $verhash = $this->cssVersionCheck($file);
        $file = $this->getCSSFile($file);
        return $file.'?'.$verhash;
    }

    //Get JS file path
    protected function getJSFile($file)
    {
        return $this->trimPath($this->options['js_dir'] . self::DIR_SEP . $file);
    }

    //Get JS version file path
    protected function getJSVersionFile($file)
    {
        $file = preg_replace('/\.[a-z0-9\-_]+$/i', '.jsversion.txt', $file);
        return $this->trimPath($this->options['cache_dir'] . self::DIR_SEP . $file);
    }

    //Store JS version value
    protected function jsSaveVersion($file)
    {
        $jsfile = $this->getJSFile($file);

        if (!is_readable($jsfile)) {
            $this->throwException("JS file \"$jsfile\" not found or couldn't be opened !");
        }

        //Add md5 check
        $md5data = md5_file($jsfile);
        //Random length random()
        $verhash = $this->random(7);
        $versionContent = "$md5data\r\n$verhash";

        //Write version file
        $versionfile = $this->getJSVersionFile($file);
        $makepath = $this->makePath($versionfile);
        if ($makepath !== true) {
            $this->throwException("Couldn't build JS version folder \"$makepath\"");
        }
        file_put_contents($versionfile, $versionContent);
        return $verhash;
    }

    //Check JS file's change
    protected function jsVersionCheck($file)
    {
        $versionfile = $this->getJSVersionFile($file);
        //Get file contents
        $versionContent = file($versionfile, FILE_IGNORE_NEW_LINES);
        $md5data = $versionContent[0];
        $verhash = $versionContent[1];
        if (md5_file($this->getJSFile($file)) != $md5data) {
            $verhash = $this->jsSaveVersion($file);
        }
        return $verhash;
    }

    //Load JS files
    public function loadJSFile($file)
    {
        $versionfile = $this->getJSVersionFile($file);
        if (!file_exists($versionfile)) {
            $this->jsSaveVersion($file);
        }
        $verhash = $this->jsVersionCheck($file);
        $file = $this->getJSFile($file);
        return $file.'?'.$verhash;
    }

    /* Template file cache */
    public function loadTemplate($file)
    {
        $versionfile = $this->getTplVersionFile($file);
        if (!file_exists($versionfile)) {
            $this->cache($file);
        }
        $this->check($file);
        $cachefile = $this->getTplCache($file);
        return $cachefile;
    }

    public function check($file)
    {
        $versionfile = $this->getTplVersionFile($file);
        $versionContent = file($versionfile, FILE_IGNORE_NEW_LINES);
        $md5data = $versionContent[0];
        $expireTime = $versionContent[1];

        if ($this->options['auto_update']
        && md5_file($this->getTplFile($file)) != $md5data) {
            $this->cache($file);
        }
        if ($this->options['cache_lifetime'] != 0
        && (time() - $expireTime >= $this->options['cache_lifetime'] * 60)) {
            $this->cache($file);
        }
    }

    //Cache template file
    public function cache($file)
    {
        $tplfile = $this->getTplFile($file);
        if (!is_readable($tplfile)) {
            $this->throwException("Template file \"$tplfile\" can't find or can't open");
        }

        //Get template contents
        $template = file_get_contents($tplfile);
        $var_regexp = "((\\\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(\-\>)?[a-zA-Z0-9_\x7f-\xff]*)(\[[a-zA-Z0-9_\-\.\"\'\[\]\$\x7f-\xff]+\])*)";
        $const_regexp = "([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)";
        $template = preg_replace("/([\n\r]+)\t+/s", "\\1", $template);

        //Filter <!--{}-->
        $template = preg_replace("/\<\!\-\-\{(.+?)\}\-\-\>/s", "{\\1}", $template);
        $template = preg_replace_callback("/[\n\r\t]*\{block\/(\d+?)\}[\n\r\t]*/i", array($this, 'parse_blocktags_1'), $template);
        $template = preg_replace_callback("/[\n\r\t]*\{date\((.+?)\)\}[\n\r\t]*/i", array($this, 'parse_datetags_1'), $template);

        //Replace special variable
        $template = preg_replace_callback("/[\n\r\t]*\{eval\}\s*(\<\!\-\-)*(.+?)(\-\-\>)*\s*\{\/eval\}[\n\r\t]*/is", array($this, 'parse_evaltags_2'), $template);
        $template = preg_replace_callback("/[\n\r\t]*\{eval\s+(.+?)\s*\}[\n\r\t]*/is", array($this, 'parse_evaltags_1'), $template);
        $template = preg_replace_callback("/[\n\r\t]*\{echo\s+(.+?)\}[\n\r\t]*/is", array($this, 'parse_stripvtags_echo1'), $template);

        //Replace direct variable
        $template = preg_replace("/\{(\\\$[a-zA-Z0-9_\-\>\[\]\'\"\$\.\x7f-\xff]+)\}/s", "<?=\\1?>", $template);
        $template = preg_replace_callback("/$var_regexp/s", array($this, 'parse_addquote_1'), $template);
        $template = preg_replace_callback("/\<\?\=\<\?\=$var_regexp\?\>\?\>/s", array($this, 'parse_addquote_1'), $template);

        //Replace template loading function
        $template = preg_replace_callback("/[\n\r\t]*\{template\s+([a-z0-9_:\/]+)\}[\n\r\t]*/is", array($this, 'parse_stripvtags_template1'), $template);
        $template = preg_replace_callback("/[\n\r\t]*\{template\s+(.+?)\}[\n\r\t]*/is", array($this, 'parse_stripvtags_template1'), $template);

        //Replace cssloader
        $template = preg_replace_callback("/[\n\r\t]*\{loadcss\s+(.+?)\}[\n\r\t]*/is", array($this, 'parse_stripvtags_css1'), $template);

        //Replace jsloader
        //Replace cssloader
        $template = preg_replace_callback("/[\n\r\t]*\{loadjs\s+(.+?)\}[\n\r\t]*/is", array($this, 'parse_stripvtags_js1'), $template);

        //Replace if/else script
        $template = preg_replace_callback("/([\n\r\t]*)\{if\s+(.+?)\}([\n\r\t]*)/is", array($this, 'parse_stripvtags_if123'), $template);
        $template = preg_replace_callback("/([\n\r\t]*)\{elseif\s+(.+?)\}([\n\r\t]*)/is", array($this, 'parse_stripvtags_elseif123'), $template);
        $template = preg_replace("/\{else\}/i", "<?php } else { ?>", $template);
        $template = preg_replace("/\{\/if\}/i", "<?php } ?>", $template);

        //Replace loop script
        $template = preg_replace_callback("/[\n\r\t]*\{loop\s+(\S+)\s+(\S+)\}[\n\r\t]*/is", array($this, 'parse_stripvtags_loop12'), $template);
        $template = preg_replace_callback("/[\n\r\t]*\{loop\s+(\S+)\s+(\S+)\s+(\S+)\}[\n\r\t]*/is", array($this, 'parse_stripvtags_loop123'), $template);
        $template = preg_replace("/\{\/loop\}/i", "<?php } ?>", $template);

        //Replace constant
        $template = preg_replace("/\{$const_regexp\}/s", "<?=\\1?>", $template);
        if (!empty($this->replacecode)) {
            $template = str_replace($this->replacecode['search'], $this->replacecode['replace'], $template);
        }

        //Remove php extra space and newline
        $template = preg_replace("/ \?\>[\n\r]*\<\? /s", " ", $template);

        //Other replace
        $template = preg_replace_callback("/\"(http)?[\w\.\/:]+\?[^\"]+?&[^\"]+?\"/", array($this, 'parse_transamp_0'), $template);
        $template = preg_replace_callback("/\<script[^\>]*?src=\"(.+?)\"(.*?)\>\s*\<\/script\>/is", array($this, 'parse_stripscriptamp_12'), $template);
        $template = preg_replace_callback("/[\n\r\t]*\{block\s+([a-zA-Z0-9_\[\]]+)\}(.+?)\{\/block\}/is", array($this, 'parse_stripblock_12'), $template);
        $template = preg_replace("/\<\?(\s{1})/is", "<?php\\1", $template);
        $template = preg_replace("/\<\?\=(.+?)\?\>/is", "<?=\\1;?>", $template);

        //Write cache file
        $cachefile = $this->getTplCache($file);
        $makepath = $this->makePath($cachefile);
        if ($makepath !== true) {
            $this->throwException("Can't build template folder \"$makepath\"");
        }
        file_put_contents($cachefile, $template);

        //Add md5 and expiretime check
        $md5data = md5_file($tplfile);
        $expireTime = time();
        $versionContent = "$md5data\r\n$expireTime";
        $versionfile = $this->getTplVersionFile($file);
        file_put_contents($versionfile, $versionContent);
    }

    protected function trimPath($path)
    {
        return str_replace(array('/', '\\', '//', '\\\\'), self::DIR_SEP, $path);
    }

    protected function getTplFile($file)
    {
        return $this->trimPath($this->options['template_dir'] . self::DIR_SEP . $file);
    }

    protected function getTplCache($file)
    {
        $file = preg_replace('/\.[a-z0-9\-_]+$/i', '.cache.php', $file);
        return $this->trimPath($this->options['cache_dir'] . self::DIR_SEP . $file);
    }

    protected function getTplVersionFile($file)
    {
        $file = preg_replace('/\.[a-z0-9\-_]+$/i', '.cache.version.txt', $file);
        return $this->trimPath($this->options['cache_dir'] . self::DIR_SEP . $file);
    }

    protected function makePath($path)
    {
        $dirs = explode(self::DIR_SEP, dirname($this->trimPath($path)));
        $tmp = '';
        foreach ($dirs as $dir) {
            $tmp = $tmp . $dir . self::DIR_SEP;
            if (!file_exists($tmp) && !mkdir($tmp, 0777))
                return $tmp;
        }
        return true;
    }

    //Throw error excetpion
    protected function throwException($message)
    {
        throw new Exception($message);
    }

    function parse_blocktags_1($matches) {
        return $this->blocktags($matches[1]);
    }

    function parse_datetags_1($matches) {
        return $this->datetags($matches[1]);
    }

    function parse_evaltags_2($matches) {
        return $this->evaltags($matches[2]);
    }

    function parse_evaltags_1($matches) {
        return $this->evaltags($matches[1]);
    }

    function parse_addquote_1($matches) {
        return $this->addquote('<?='.$matches[1].'?>');
    }

    function parse_stripvtags_template1($matches) {
        return $this->stripvtags("\n".'<?php include($template->loadTemplate(\''.$matches[1].'.html\')); ?>'."\r");
    }

    function parse_stripvtags_css1($matches) {
        return $this->stripvtags('<?php echo $template->loadCSSFile(\''.$matches[1].'\'); ?>');
    }

    function parse_stripvtags_js1($matches) {
        return $this->stripvtags('<?php echo $template->loadJSFile(\''.$matches[1].'\'); ?>');
    }

    function parse_stripvtags_echo1($matches) {
        return $this->stripvtags($matches[1]);
    }

    function parse_stripvtags_if123($matches) {
        return $this->stripvtags($matches[1].'<?php if ('.$matches[2].') { ?>'.$matches[3]);
    }

    function parse_stripvtags_elseif123($matches) {
        return $this->stripvtags($matches[1].'<?php } elseif ('.$matches[2].') { ?>'.$matches[3]);
    }

    function parse_stripvtags_loop12($matches) {
        return $this->stripvtags("\n".'<?php if (is_array('.$matches[1].')) foreach('.$matches[1].' as '.$matches[2].') { ?>'."\n");
    }

    function parse_stripvtags_loop123($matches) {
        return $this->stripvtags("\n".'<?php if (is_array('.$matches[1].')) foreach('.$matches[1].' as '.$matches[2].' => '.$matches[3].') { ?>'."\n");
    }

    function parse_transamp_0($matches) {
        return $this->transamp($matches[0]);
    }

    function parse_stripscriptamp_12($matches) {
        return $this->stripscriptamp($matches[1], $matches[2]);
    }

    function parse_stripblock_12($matches) {
        return $this->stripblock($matches[1], $matches[2]);
    }

    function blocktags($parameter) {
        $bid = intval(trim($parameter));
        $this->blocks[] = $bid;
        $i = count($this->replacecode['search']);
        $this->replacecode['search'][$i] = $search = "<!--BLOCK_TAG_$i-->";
        $this->replacecode['replace'][$i] = "<?php block_display('$bid');?>";
        return $search;
    }

    function datetags($parameter) {
        $parameter = stripslashes($parameter);
        $i = count($this->replacecode['search']);
        $this->replacecode['search'][$i] = $search = "<!--DATE_TAG_$i-->";
        $this->replacecode['replace'][$i] = "<?php echo dgmdate($parameter);?>";
        return $search;
    }

    function evaltags($php) {
        $i = count($this->replacecode['search']);
        $this->replacecode['search'][$i] = $search = "<!--EVAL_TAG_$i-->";
        $this->replacecode['replace'][$i] = "<?php $php;?>";
        return $search;
    }

    function stripphpcode($type, $code) {
        $this->phpcode[$type][] = $code;
        return '{phpcode:'.$type.'/'.(count($this->phpcode[$type]) - 1).'}';
    }

    function getphptemplate($content) {
        $pos = strpos($content, "\n");
        return $pos !== false ? substr($content, $pos + 1) : $content;
    }

    function transamp($str) {
        $str = str_replace('&', '&amp;', $str);
        $str = str_replace('&amp;amp;', '&amp;', $str);
        return $str;
    }

    function addquote($var) {
        return str_replace("\\\"", "\"", preg_replace("/\[([a-zA-Z0-9_\-\.\x7f-\xff]+)\]/s", "['\\1']", $var));
    }

    function stripvtags($expr, $statement = '') {
        $expr = str_replace('\\\"', '\"', preg_replace("/\<\?\=(\\\$.+?)\?\>/s", "\\1", $expr));
        $statement = str_replace('\\\"', '\"', $statement);
        return $expr.$statement;
    }

    function stripscriptamp($s, $extra) {
        $s = str_replace('&amp;', '&', $s);
        return "<script src=\"$s\"$extra></script>";
    }

    function stripblock($var, $s) {
        $s = preg_replace("/<\?=\\\$(.+?)\?>/", "{\$\\1}", $s);
        preg_match_all("/<\?=(.+?)\?>/", $s, $constary);
        $constadd = '';
        $constary[1] = array_unique($constary[1]);
        foreach($constary[1] as $const) {
            $constadd = $constadd.'$__'.$const.' = '.$const.';';
        }
        $s = preg_replace("/<\?=(.+?)\?>/", "{\$__\\1}", $s);
        $s = str_replace('?>', "\n\$$var = $var.<<<EOF\n", $s);
        $s = str_replace('<?', "\nEOF;\n", $s);
        $s = str_replace("\nphp ", "\n", $s);
        return "<?\n$constadd\$$var = <<<EOF\n".$s."\nEOF;\n?>";
    }
}
