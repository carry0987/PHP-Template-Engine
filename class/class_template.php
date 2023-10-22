<?php
class Template
{
    private static $instance;
    private $replacecode = array('search' => array(), 'replace' => array());
    private $blocks = array();
    private $options = array();
    private $place = '';
    private $compress = array('html' => false, 'css' => true);
    private $connectdb = null;
    const DIR_SEP = DIRECTORY_SEPARATOR;

    //Get Instance
    public static function getInstance()
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    //Construct options
    private function __construct()
    {
        $this->options = array(
            'template_dir' => 'templates'.self::DIR_SEP,
            'css_dir' => 'css'.self::DIR_SEP,
            'js_dir' => 'js'.self::DIR_SEP,
            'static_dir' => 'static'.self::DIR_SEP,
            'cache_dir' => 'templates'.self::DIR_SEP.'cache'.self::DIR_SEP,
            'auto_update' => false,
            'cache_lifetime' => 0,
            'cache_db' => false
        );
    }

    //Set template parameter array
    public function setOptions(array $options)
    {
        foreach ($options as $name => $value) {
            $this->setTemplate($name, $value);
        }
    }

    //Set template parameter
    private function setTemplate($name, $value)
    {
        switch ($name) {
            case 'template_dir':
                $value = $this->trimPath($value);
                if (!file_exists($value)) {
                    $this->throwError('Couldn\'t found the specified template folder', $value);
                }
                $this->options['template_dir'] = $value;
                break;
            case 'css_dir':
                if ($value !== false) {
                    $value = $this->trimPath($value);
                    if (!file_exists($value)) {
                        $this->throwError('Couldn\'t found the specified css folder', $value);
                    }
                }
                $this->options['css_dir'] = $value;
                break;
            case 'js_dir':
                if ($value !== false) {
                    $value = $this->trimPath($value);
                    if (!file_exists($value)) {
                        $this->throwError('Couldn\'t found the specified js folder', $value);
                    }
                }
                $this->options['js_dir'] = $value;
                break;
            case 'static_dir':
                if ($value !== false) {
                    $value = $this->trimPath($value);
                    if (!file_exists($value)) {
                        $this->throwError('Couldn\'t found the specified static folder', $value);
                    }
                }
                $this->options['static_dir'] = $value;
                break;
            case 'cache_dir':
                $value = $this->trimPath($value);
                if (!file_exists($value)) {
                    $makepath = $this->makePath($value);
                    if ($makepath !== true) {
                        $this->throwError('Couldn\'t build template folder', $makepath);
                    }
                }
                $this->options['cache_dir'] = $value;
                break;
            case 'auto_update':
                $this->options['auto_update'] = (boolean) $value;
                break;
            case 'cache_lifetime':
                $this->options['cache_lifetime'] = (float) $value;
                break;
            case 'cache_db':
                if ($value === false) {
                    $this->options['cache_db'] = false;
                } else {
                    $this->connectdb = $value;
                }
                break;
            default:
                $this->throwError('Unknown template setting options', $name);
                break;
        }
    }

    public function __set($name, $value)
    {
        $this->setTemplate($name, $value);
    }

    public function compressHTML($html)
    {
        $this->compress['html'] = $html;
    }

    public function compressCSS($css)
    {
        $this->compress['css'] = $css;
    }

    private function generateRandom($length, $numeric = 0)
    {
        $seed = base_convert(md5(microtime().$_SERVER['DOCUMENT_ROOT']), 16, $numeric ? 10 : 35);
        $seed = $numeric ? (str_replace('0', '', $seed).'012340567890') : ($seed.'zZ'.strtoupper($seed));
        $hash = '';
        if (!$numeric) {
            $hash = chr(rand(1, 26) + rand(0, 1) * 32 + 64);
            $length--;
        }
        $max = strlen($seed) - 1;
        for ($i = 0; $i < $length; $i++) {
            $hash = $hash.$seed[mt_rand(0, $max)];
        }
        return $hash;
    }

    private function trimRelativePath($path)
    {
        $hash = substr_count($path, '../');
        $hash = ($hash !== 0) ? substr(md5($hash), 0, 6).'/' : '';
        $path = str_replace('../', '', $path);
        return $hash.$path;
    }

    /* Static file cache */
    //Get CSS file path
    private function trimCSSName($file)
    {
        return str_replace('.css', '', $file);
    }

    private function placeCSSName($place)
    {
        return (is_array($place)) ? substr(md5(implode('-', $place)), 0, 6) : $place;
    }

    private function getCSSFile($file)
    {
        return $this->trimPath($this->options['css_dir'].self::DIR_SEP.$file);
    }

    private function getCSSCache($file, $place)
    {
        $file = $this->trimRelativePath($file);
        $place = $this->placeCSSName($place);
        $file = preg_replace('/\.[a-z0-9\-_]+$/i', '_'.$place.'.css', $file);
        return $this->trimPath($this->options['cache_dir'].self::DIR_SEP.'css'.self::DIR_SEP.$file);
    }

    //Get CSS version file path
    private function getCSSVersionFile($file)
    {
        $file = $this->trimRelativePath($file);
        $file = preg_replace('/\.[a-z0-9\-_]+$/i', '.cssversion.json', $file);
        return $this->trimPath($this->options['cache_dir'].self::DIR_SEP.$file);
    }

    //Store CSS version value
    private function cssSaveVersion($file, $css_md5 = null)
    {
        //Get CSS file
        $css_file = $this->getCSSFile($file);
        //Check file if readable
        if (!is_readable($css_file)) {
            $this->throwError('CSS file not found or couldn\'t be opened', $css_file);
        }
        //Add md5 check
        $md5data = ($css_md5 === null) ? md5_file($css_file) : $css_md5;
        //Random length random()
        $verhash = $this->generateRandom(7);
        //Insert md5 & verhash
        $expire_time = time();
        if ($this->connectdb !== null) {
            $trimed_name = $this->trimCSSName($file);
            if (!empty($this->place)) {
                $trimed_name .= '::'.$this->placeCSSName($this->place);
            }
            if ($this->getVersion($this->dashPath($this->options['css_dir']), $trimed_name, 'css') !== false) {
                $this->updateVersion($this->dashPath($this->options['css_dir']), $trimed_name, 'css', $md5data, $expire_time, $verhash);
            } else {
                $this->createVersion($this->dashPath($this->options['css_dir']), $trimed_name, 'css', $md5data, $expire_time, $verhash);
            }
        } else {
            $versionFile = $this->getCSSVersionFile($file);
            if (file_exists($versionFile)) {
                $versionContent = json_decode(file_get_contents($versionFile), true);
            } else {
                $versionContent = array(
                    'main' => array(
                        'md5' => $md5data,
                        'verhash' => $verhash,
                        'expire_time' => $expire_time
                    )
                );
            }
            if (!empty($this->place)) {
                $versionContent['::'.$this->placeCSSName($this->place)] = array(
                    'md5' => $md5data,
                    'verhash' => $verhash,
                    'expire_time' => $expire_time
                );
            }
            $versionContent = json_encode($versionContent);
            //Write version file
            $makepath = $this->makePath($versionFile);
            if ($makepath !== true) {
                $this->throwError('Couldn\'t build CSS version folder', $makepath);
            }
            file_put_contents($versionFile, $versionContent);
        }
        return $verhash;
    }

    //Check CSS file's change
    private function cssVersionCheck($file, $css_md5 = null)
    {
        $result = array();
        $result['update'] = false;
        if ($this->connectdb !== null) {
            $css_file = $this->trimCSSName($file);
            if (!empty($this->place)) {
                $css_file .= '::'.$this->placeCSSName($this->place);
            }
            $static_data = $this->getVersion($this->dashPath($this->options['css_dir']), $css_file, 'css');
            $md5data = $static_data['tpl_md5'];
            $verhash = $static_data['tpl_verhash'];
            $expire_time = $static_data['tpl_expire_time'];
        } else {
            $versionfile = $this->getCSSVersionFile($file);
            //Get file contents
            $versionContent = json_decode(file_get_contents($versionfile), true);
            if (!empty($this->place) && isset($versionContent['::'.$this->placeCSSName($this->place)])) {
                $versionContent = $versionContent['::'.$this->placeCSSName($this->place)];
            } else {
                $versionContent = $versionContent['main'];
            }
            $md5data = $versionContent['md5'];
            $verhash = $versionContent['verhash'];
            $expire_time = $versionContent['expire_time'];
        }
        //Check CSS md5
        $css_md5 = ($css_md5 === null) ? md5_file($this->getCSSFile($file)) : $css_md5;
        if ($this->options['auto_update'] === true && $css_md5 !== $md5data) {
            $result['update'] = true;
        }
        if ($this->options['cache_lifetime'] != 0 && (time() - $expire_time >= $this->options['cache_lifetime'] * 60)) {
            $result['update'] = ($css_md5 !== $md5data) ? true : false;
        }
        $result['verhash'] = ($result['update'] === true) ? $this->cssSaveVersion($file, $css_md5) : $verhash;
        return $result;
    }

    //Load CSS files
    public function loadCSSFile($file)
    {
        $place = 'minified';
        if ($this->connectdb !== null) {
            $css_file = $this->trimCSSName($file);
            $css_version = $this->getVersion($this->dashPath($this->options['css_dir']), $css_file, 'css');
        } else {
            $versionfile = $this->getCSSVersionFile($file);
            $css_version = (!file_exists($versionfile)) ? false : true;
        }
        if ($css_version === false) $this->cssSaveVersion($file);
        $css_version_check = $this->cssVersionCheck($file);
        if ($this->compress['css'] === true && strpos($file, '.min.css') === false) {
            $css_cache_file = $this->getCSSCache($file, $place);
            if (!file_exists($css_cache_file) || $css_version_check['update'] === true || $css_version === false) {
                $this->parseCSSFile($file, $place);
            }
            $file = $css_cache_file;
        } else {
            $file = $this->getCSSFile($file);
        }
        return $file.'?v='.$css_version_check['verhash'];
    }

    //Load CSS Template
    public function loadCSSTemplate($file, $place)
    {
        if (is_array($place)) {
            $place = (count($place) > 1) ? $place : $place[0];
        }
        $this->place = $place;
        if ($this->connectdb !== null) {
            $css_file = $this->trimCSSName($file);
            if (!empty($place)) {
                $css_file .= '::'.$this->placeCSSName($place);
            }
            $css_version = $this->getVersion($this->dashPath($this->options['css_dir']), $css_file, 'css');
        } else {
            $versionfile = $this->getCSSVersionFile($file);
            $css_version = (!file_exists($versionfile)) ? false : true;
        }
        //Get CSS model md5
        $css_md5 = $this->parseCSSTemplate($file, $place, true);
        //Check the need of saving version
        if ($css_version === false) {
            $this->cssSaveVersion($file, $css_md5);
        }
        //Get CSS cache file path
        $css_cache_file = $this->getCSSCache($file, $place);
        $css_version_check = $this->cssVersionCheck($file, $css_md5);
        $verhash = $css_version_check['verhash'];
        if (!file_exists($css_cache_file) || $css_version_check['update'] === true || $css_version === false) {
            $this->parseCSSTemplate($file, $place);
        }
        return $css_cache_file.'?v='.$verhash;
    }

    //Get JS file path
    private function trimJSName($file)
    {
        return str_replace('.js', '', $file);
    }

    private function getJSFile($file)
    {
        return $this->trimPath($this->options['js_dir'].self::DIR_SEP.$file);
    }

    //Get JS version file path
    private function getJSVersionFile($file)
    {
        $file = $this->trimRelativePath($file);
        $file = preg_replace('/\.[a-z0-9\-_]+$/i', '.jsversion.txt', $file);
        return $this->trimPath($this->options['cache_dir'].self::DIR_SEP.$file);
    }

    //Store JS version value
    private function jsSaveVersion($file)
    {
        //Get JS file
        $js_file = $this->getJSFile($file);
        //Check file if readable
        if (!is_readable($js_file)) {
            $this->throwError('JS file not found or couldn\'t be opened', $js_file);
        }
        //Add md5 check
        $md5data = md5_file($js_file);
        //Random length random()
        $verhash = $this->generateRandom(7);
        //Insert md5 & verhash
        $expire_time = time();
        if ($this->connectdb !== null) {
            if ($this->getVersion($this->dashPath($this->options['js_dir']), $this->trimJSName($file), 'js') !== false) {
                $this->updateVersion($this->dashPath($this->options['js_dir']), $this->trimJSName($file), 'js', $md5data, $expire_time, $verhash);
            } else {
                $this->createVersion($this->dashPath($this->options['js_dir']), $this->trimJSName($file), 'js', $md5data, $expire_time, $verhash);
            }
        } else {
            $versionContent = $md5data."\r\n".$verhash."\r\n".$expire_time;
            //Write version file
            $versionfile = $this->getJSVersionFile($file);
            $makepath = $this->makePath($versionfile);
            if ($makepath !== true) {
                $this->throwError('Couldn\'t build JS version folder', $makepath);
            }
            file_put_contents($versionfile, $versionContent);
        }
        return $verhash;
    }

    //Check JS file's change
    private function jsVersionCheck($file)
    {
        $result = array();
        $result['update'] = false;
        if ($this->connectdb !== null) {
            $js_file = $this->trimJSName($file);
            $static_data = $this->getVersion($this->dashPath($this->options['js_dir']), $js_file, 'js');
            $md5data = $static_data['tpl_md5'];
            $verhash = $static_data['tpl_verhash'];
            $expire_time = $static_data['tpl_expire_time'];
        } else {
            $versionfile = $this->getJSVersionFile($file);
            //Get file contents
            $versionContent = file($versionfile, FILE_IGNORE_NEW_LINES);
            $md5data = $versionContent[0];
            $verhash = $versionContent[1];
            $expire_time = $versionContent[2];
        }
        if ($this->options['auto_update'] === true && md5_file($this->getJSFile($file)) !== $md5data) {
            $result['update'] = true;
        }
        if ($this->options['cache_lifetime'] != 0 && (time() - $expire_time >= $this->options['cache_lifetime'] * 60)) {
            $result['update'] = (md5_file($this->getJSFile($file)) !== $md5data) ? true : false;
        }
        $result['verhash'] = ($result['update'] === true) ? $this->jsSaveVersion($file) : $verhash;
        return $result;
    }

    //Load JS files
    public function loadJSFile($file)
    {
        if ($this->connectdb !== null) {
            $js_file = $this->trimJSName($file);
            $js_version = $this->getVersion($this->dashPath($this->options['js_dir']), $js_file, 'js');
        } else {
            $versionfile = $this->getJSVersionFile($file);
            $js_version = (!file_exists($versionfile)) ? false : true;
        }
        if ($js_version === false) $this->jsSaveVersion($file);
        $js_version_check = $this->jsVersionCheck($file);
        $file = $this->getJSFile($file);
        return $file.'?v='.$js_version_check['verhash'];
    }

    /* Template file cache */
    public function loadTemplate($file)
    {
        if ($this->connectdb !== null) {
            $versionContent = $this->getVersion($this->dashPath($this->options['template_dir']), $file, 'html');
            if ($versionContent === false) {
                $this->parseTemplate($file);
            }
            $this->checkTemplate($file);
            $cachefile = $this->getTplCache($file);
            if (!file_exists($cachefile)) {
                $this->parseTemplate($file);
            }
        } else {
            $versionfile = $this->getTplVersionFile($file);
            if (!file_exists($versionfile)) {
                $this->parseTemplate($file);
            }
            $this->checkTemplate($file);
            $cachefile = $this->getTplCache($file);
            if (!file_exists($cachefile)) {
                $this->parseTemplate($file);
            }
        }
        return $cachefile;
    }

    /* Check template expiration and md5 */
    private function checkTemplate($file)
    {
        $check_tpl = false;
        if ($this->connectdb !== null) {
            $versionContent = $this->getVersion($this->dashPath($this->options['template_dir']), $file, 'html');
            if ($versionContent !== false) {
                $md5data = $versionContent['tpl_md5'];
                $expire_time = $versionContent['tpl_expire_time'];
            } else {
                $this->parseTemplate($file);
                $check_tpl = true;
            }
        } else {
            $versionfile = $this->getTplVersionFile($file);
            $versionContent = file($versionfile, FILE_IGNORE_NEW_LINES);
            $md5data = $versionContent[0];
            $expire_time = $versionContent[1];
        }
        if ($check_tpl === false) {
            if ($this->options['auto_update'] === true && md5_file($this->getTplFile($file)) !== $md5data) {
                $this->parseTemplate($file);
            }
            if ($this->options['cache_lifetime'] != 0 && (time() - $expire_time >= $this->options['cache_lifetime'] * 60)) {
                if (md5_file($this->getTplFile($file)) !== $md5data) $this->parseTemplate($file);
            }
        }
    }

    //Parse template file
    private function parseTemplate($file)
    {
        $tplfile = $this->getTplFile($file);
        if (!is_readable($tplfile)) {
            $this->throwError('Template file can\'t be found or opened', $tplfile);
        }

        //Get template contents
        $template = file_get_contents($tplfile);
        $preserve_regexp_html = '/\<\!\-\-\{PRESERVE\}\-\-\>(.*?)\<\!\-\-\\{\/PRESERVE\}\-\-\>/s';
        $preserve_regexp = '/\/\*\{PRESERVE\}\*\/(.*?)\/\*\{\/PRESERVE\}\*\//s';
        $var_simple_regexp = "(\\\$[a-zA-Z0-9_\-\>\[\]\'\"\$\.\x7f-\xff]+)";
        $var_regexp = "((\\\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(\-\>)?[a-zA-Z0-9_\x7f-\xff]*)(\[[a-zA-Z0-9_\-\.\"\'\[\]\$\x7f-\xff]+\])*)";
        $const_regexp = "([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)";
        $template = preg_replace("/([\n\r]+)\t+/s", "\\1", $template);

        //Preserve specific block
        preg_match_all($preserve_regexp_html, $template, $preserves_html);
        $template = preg_replace($preserve_regexp_html, '##PRESERVE##', $template);
        preg_match_all($preserve_regexp, $template, $preserves);
        $template = preg_replace($preserve_regexp, '##PRESERVE##', $template);

        //Filter <!--{}-->
        $template = preg_replace("/\h*\<\!\-\-\{(.+?)\}\-\-\>/s", "{\\1}", $template);

        //Language
        $template = preg_replace_callback("/\{lang\s+(\S+)\s+(\S+)\}/is", array($this, 'parse_language_var_1'), $template);

        //Replace block tag
        //$template = preg_replace_callback("/\{block\/(\d+?)\}/i", array($this, 'parse_blocktags_1'), $template);

        //Replace eval function
        $template = preg_replace_callback("/\{eval\}\s*(\<\!\-\-)*(.+?)(\-\-\>)*\s*\{\/eval\}/is", array($this, 'parse_evaltags_2'), $template);
        $template = preg_replace_callback("/\{eval\s+(.+?)\s*\}/is", array($this, 'parse_evaltags_1'), $template);

        //Replace direct variable output
        $template = preg_replace("/\{\h*(\\\$[a-zA-Z0-9_\-\>\[\]\'\"\$\.\x7f-\xff]+)\h*\}/s", "<?=\\1?>", $template);
        $template = preg_replace_callback("/\<\?\=\<\?\=$var_regexp\?\>\?\>/s", array($this, 'parse_addquote_1'), $template);

        //Replace $var
        //$template = preg_replace_callback("/$var_regexp/s", array($this, 'parse_addquote_1'), $template);

        //Replace template loading function
        $template = preg_replace_callback("/\{template\s+([a-z0-9_:\/]+)\}/is", array($this, 'parse_stripvtags_template1'), $template);
        $template = preg_replace_callback("/\{template\s+(.+?)\}/is", array($this, 'parse_stripvtags_template1'), $template);

        //Replace echo function
        $template = preg_replace_callback("/\{echo\s+(.+?)\}/is", array($this, 'parse_stripvtags_echo1'), $template);

        //Replace cssloader
        $template = preg_replace_callback("/\{loadcss\s+(\S+)\}/is", array($this, 'parse_stripvtags_css1'), $template);
        $template = preg_replace_callback("/\{loadcss\s+(\S+)\s+([a-z0-9_-]+)\}/is", array($this, 'parse_stripvtags_csstpl_1'), $template);
        $template = preg_replace_callback("/\{loadcss\s+(\S+)\s+$var_simple_regexp\}/is", array($this, 'parse_stripvtags_csstpl_2'), $template);

        //Replace jsloader
        $template = preg_replace_callback("/\{loadjs\s+(\S+)\}/is", array($this, 'parse_stripvtags_js1'), $template);

        //Replace static file loader
        $template = preg_replace_callback("/\{static\s+(\S+)\}/is", array($this, 'parse_stripvtags_static1'), $template);

        //Replace if/else script
        $template = preg_replace_callback("/\{if\s+(.+?)\}/is", array($this, 'parse_stripvtags_if1'), $template);
        $template = preg_replace_callback("/\{elseif\s+(.+?)\}/is", array($this, 'parse_stripvtags_elseif1'), $template);
        $template = preg_replace("/\{else\}/i", "<?php } else { ?>", $template);
        $template = preg_replace("/\{\/if\}/i", "<?php } ?>", $template);

        //Replace loop script
        $template = preg_replace_callback("/\{loop\s+(\S+)\s+(\S+)\}/is", array($this, 'parse_stripvtags_loop12'), $template);
        $template = preg_replace_callback("/\{loop\s+(\S+)\s+(\S+)\s+(\S+)\}/is", array($this, 'parse_stripvtags_loop123'), $template);
        $template = preg_replace("/\{\/loop\}/i", "<?php } ?>", $template);

        //Replace constant
        $template = preg_replace("/\{\h*$const_regexp\h*\}/s", "<?=\\1?>", $template);
        if (!empty($this->replacecode)) {
            $template = str_replace($this->replacecode['search'], $this->replacecode['replace'], $template);
        }

        //Remove php extra space and newline
        $template = preg_replace("/ \?\>[\n\r]*\<\? /s", " ", $template);

        //Other replace
        $template = preg_replace_callback("/\"(http)?[\w\.\/:]+\?[^\"]+?&[^\"]+?\"/", array($this, 'parse_transamp_0'), $template);
        $template = preg_replace_callback("/\<script[^\>]*?src=\"(.+?)\"(.*?)\>\s*\<\/script\>/is", array($this, 'parse_stripscriptamp_12'), $template);
        $template = preg_replace_callback("/\{block\s+(.+?)\}(.+?)\{\/block\}/is", array($this, 'parse_stripblock_12'), $template);
        $template = preg_replace("/\<\?(\s{1})/is", "<?php\\1", $template);
        $template = preg_replace("/\<\?\=(.+?)\?\>/is", "<?=\\1;?>", $template);

        //Protect cache file
        $template = '<?php if (!class_exists(\'Template\')) die(\'Access Denied\');?>'."\r\n".$template;

        //Minify HTML
        if ($this->compress['html'] === true) {
            $template = preg_replace_callback("/\<style type=\"text\/css\"\>(.*?)\<\/style\>/s", array($this, 'parse_css_minify'), $template);
            $template = $this->minifyHTML($template);
        }

        foreach ($preserves_html[1] as $preserve) {
            $template = preg_replace('/##PRESERVE##/', trim($preserve), $template, 1);
        }

        foreach ($preserves[1] as $preserve) {
            $template = preg_replace('/##PRESERVE##/', trim($preserve), $template, 1);
        }

        //Write into cache file
        $cachefile = $this->getTplCache($file);
        $makepath = $this->makePath($cachefile);
        if ($makepath !== true) {
            $this->throwError('Can\'t build template folder', $makepath);
        } else {
            file_put_contents($cachefile, $template."\n");
        }

        if ($this->connectdb !== null) {
            //Insert md5 and expiretime into cache database
            $md5data = md5_file($tplfile);
            $expire_time = time();
            $versionContent['tpl_md5'] = $md5data;
            $versionContent['tpl_expire_time'] = $expire_time;
            if ($this->getVersion($this->dashPath($this->options['template_dir']), $file, 'html') !== false) {
                $this->updateVersion($this->dashPath($this->options['template_dir']), $file, 'html', $versionContent['tpl_md5'], $versionContent['tpl_expire_time'], '0');
            } else {
                $this->createVersion($this->dashPath($this->options['template_dir']), $file, 'html', $versionContent['tpl_md5'], $versionContent['tpl_expire_time'], '0');
            }
        } else {
            //Add md5 and expiretime check
            $md5data = md5_file($tplfile);
            $expire_time = time();
            $versionContent = "$md5data\r\n$expire_time";
            $versionfile = $this->getTplVersionFile($file);
            file_put_contents($versionfile, $versionContent);
        }
    }

    private function dashPath($path)
    {
        $path = ltrim($path, '/\\');
        $path = rtrim($path, '/\\');
        return str_replace(array('/', '\\', '//', '\\\\'), '::', $path);
    }

    private function trimTplName($file)
    {
        return str_replace('.html', '', $file);
    }

    private function trimPath($path)
    {
        return str_replace(array('/', '\\', '//', '\\\\'), self::DIR_SEP, $path);
    }

    private function getTplFile($file)
    {
        return $this->trimPath($this->options['template_dir'].self::DIR_SEP.$file);
    }

    private function getTplCache($file)
    {
        $file = preg_replace('/\.[a-z0-9\-_]+$/i', '.cache.php', $file);
        return $this->trimPath($this->options['cache_dir'].self::DIR_SEP.$file);
    }

    private function getTplVersionFile($file)
    {
        $file = preg_replace('/\.[a-z0-9\-_]+$/i', '.htmlversion.txt', $file);
        return $this->trimPath($this->options['cache_dir'].self::DIR_SEP.$file);
    }

    private function getVersion($get_tpl_path, $get_tpl_name, $get_tpl_type)
    {
        $get_tpl_name = $this->trimTplName($get_tpl_name);
        $tpl_query = 'SELECT tpl_md5, tpl_expire_time, tpl_verhash FROM template WHERE tpl_path = ? AND tpl_name = ? AND tpl_type = ?';
        $tpl_row = array();
        try {
            $tpl_stmt = $this->connectdb->prepare($tpl_query);
            $tpl_stmt->bindValue(1, $get_tpl_path, PDO::PARAM_STR);
            $tpl_stmt->bindValue(2, $get_tpl_name, PDO::PARAM_STR);
            $tpl_stmt->bindValue(3, $get_tpl_type,PDO::PARAM_STR);
            $tpl_stmt->execute();
            $tpl_row = $tpl_stmt->fetch(PDO::FETCH_ASSOC);
            if (!empty($tpl_row)) {
                return $tpl_row;
            }
            return false;
        } catch (PDOException $e) {
            echo $this->throwDBError($e->getMessage(), $e->getCode());
            exit();
        }
    }

    private function createVersion($tpl_path, $tpl_name, $tpl_type, $tpl_md5, $tpl_expire_time, $tpl_verhash)
    {
        $tpl_name = $this->trimTplName($tpl_name);
        $tpl_query = 'INSERT INTO template (tpl_path, tpl_name, tpl_type, tpl_md5, tpl_expire_time, tpl_verhash) VALUES (?,?,?,?,?,?)';
        try {
            $tpl_stmt = $this->connectdb->prepare($tpl_query);
            $tpl_stmt->bindValue(1, $tpl_path, PDO::PARAM_STR);
            $tpl_stmt->bindValue(2, $tpl_name, PDO::PARAM_STR);
            $tpl_stmt->bindValue(3, $tpl_type, PDO::PARAM_STR);
            $tpl_stmt->bindValue(4, $tpl_md5, PDO::PARAM_STR);
            $tpl_stmt->bindValue(5, $tpl_expire_time, PDO::PARAM_INT);
            $tpl_stmt->bindValue(6, $tpl_verhash, PDO::PARAM_STR);
            $tpl_stmt->execute();
        } catch (PDOException $e) {
            echo $this->throwDBError($e->getMessage(), $e->getCode());
            exit();
        }
    }

    private function updateVersion($tpl_path, $tpl_name, $tpl_type, $tpl_md5, $tpl_expire_time, $tpl_verhash)
    {
        $tpl_name = $this->trimTplName($tpl_name);
        $tpl_query = 'UPDATE template SET tpl_md5 = ?, tpl_expire_time = ?, tpl_verhash = ? WHERE tpl_path = ? AND tpl_name = ? AND tpl_type = ?';
        try {
            $tpl_stmt = $this->connectdb->prepare($tpl_query);
            $tpl_stmt->bindValue(1, $tpl_md5, PDO::PARAM_STR);
            $tpl_stmt->bindValue(2, $tpl_expire_time, PDO::PARAM_INT);
            $tpl_stmt->bindValue(3, $tpl_verhash, PDO::PARAM_STR);
            $tpl_stmt->bindValue(4, $tpl_path, PDO::PARAM_STR);
            $tpl_stmt->bindValue(5, $tpl_name, PDO::PARAM_STR);
            $tpl_stmt->bindValue(6, $tpl_type, PDO::PARAM_STR);
            $tpl_stmt->execute();
        } catch (PDOException $e) {
            echo $this->throwDBError($e->getMessage(), $e->getCode());
            exit();
        }
    }

    private function makePath($path)
    {
        $dirs = explode(self::DIR_SEP, dirname($this->trimPath($path)));
        if (!is_writeable($dirs[0])) {
            return false;
        }
        $tmp = '';
        foreach ($dirs as $dir) {
            $tmp = $tmp.$dir.self::DIR_SEP;
            if (!file_exists($tmp) && !mkdir($tmp, 0755, true)) {
                return $tmp;
            }
        }
        return true;
    }

    private function minifyHTML($html)
    {
        $search = array(
            '/\>[^\S ]+/s',
            '/[^\S ]+\</s',
            '/(\s)+/s'
        );
        /*
        $search = array(
            '/\>[^\S ]+/s',     // Strip whitespaces after tags, except space
            '/[^\S ]+\</s',     // Strip whitespaces before tags, except space
            '/(\s)+/s',         // Shorten multiple whitespace sequences
            '/<!--(.|\s)*?-->/' // Remove HTML comments
        );*/
        $replace = array('>', '<', '\\1', '');
        $html = preg_replace($search, $replace, $html);
        return $html;
    }

    private function parse_language_var_1($matches)
    {
        return $this->stripvTags('<? echo Template::langParam('.$matches[1].', '.$matches[2].');?>');
    }

/*
    private function parse_blocktags_1($matches)
    {
        return $this->blockTags($matches[1]);
    }
*/

    private function parse_evaltags_1($matches)
    {
        return $this->evalTags($matches[1]);
    }

    private function parse_evaltags_2($matches)
    {
        return $this->evalTags($matches[2]);
    }

    private function parse_addquote_1($matches)
    {
        return $this->addQuote('<?='.$matches[1].'?>');
    }

    private function parse_stripvtags_template1($matches)
    {
        return $this->stripvTags('<? include(Template::getInstance()->loadTemplate(\''.$matches[1].'.html\'));?>');
    }

    private function parse_stripvtags_css1($matches)
    {
        if ($this->options['css_dir'] === false) return $matches[1];
        return $this->stripvTags('<? echo Template::getInstance()->loadCSSFile(\''.$matches[1].'\');?>');
    }

    private function parse_stripvtags_csstpl_1($matches)
    {
        if ($this->options['css_dir'] === false) return $matches[1];
        return $this->stripvTags('<? echo Template::getInstance()->loadCSSTemplate(\''.$matches[1].'\', \''.$matches[2].'\');?>');
    }

    private function parse_stripvtags_csstpl_2($matches)
    {
        if ($this->options['css_dir'] === false) return $matches[1];
        return $this->stripvTags('<? echo Template::getInstance()->loadCSSTemplate(\''.$matches[1].'\', '.$matches[2].');?>');
    }

    //Parse CSS File
    private function parseCSSFile($file, $place)
    {
        $css_tplfile = $this->getCSSFile($file);
        if (!is_readable($css_tplfile)) {
            $this->throwError('CSS file can\'t be found or opened', $css_tplfile);
        }
        //Get template contents
        $content = file_get_contents($css_tplfile);
        $content = $this->minifyCSS($content);
        //Write into cache file
        $cachefile = $this->getCSSCache($file, $place);
        $makepath = $this->makePath($cachefile);
        if ($makepath !== true) {
            $this->throwError('Can\'t create template folder', $makepath);
        } else {
            file_put_contents($cachefile, $content."\n");
        }
        return $cachefile;
    }

    //Parse CSS Template
    private function parseCSSTemplate($file, $place, $get_md5 = false)
    {
        $css_tplfile = $this->getCSSFile($file);
        if (!is_readable($css_tplfile)) {
            $this->throwError('Template file can\'t be found or opened', $css_tplfile);
        }
        //Get template contents
        $content = file_get_contents($css_tplfile);
        if (is_array($place)) {
            $place_array = array();
            foreach ($place as $value) {
                $contents = preg_match("/\/\*\[$value\]\*\/\s(.*?)\/\*\[\/$value\]\*\//is", $content, $matches);
                $place_array[$value] = $matches[1];
                if ($get_md5 === false) {
                    $place_array[$value] = $this->parse_csstpl($contents, $matches, $value);
                }
            }
            if ($get_md5 !== false) return md5(implode("\n", $place_array));
            $content = implode("\n", $place_array);
        } else {
            $content = preg_match("/\/\*\[$place\]\*\/\s(.*?)\/\*\[\/$place\]\*\//is", $content, $matches);
            if ($get_md5 !== false) return md5($matches[1]);
            $content = $this->parse_csstpl($content, $matches, $place);
        }
        //Write into cache file
        $cachefile = $this->getCSSCache($file, $place);
        $makepath = $this->makePath($cachefile);
        if ($makepath !== true) {
            $this->throwError('Can\'t build template folder', $makepath);
        } else {
            file_put_contents($cachefile, $content."\n");
        }
        return $cachefile;
    }

    //Minify CSS
    private function minifyCSS($content)
    {
        //Remove comments
        $content = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $content);
        //Backup values within single or double quotes
        preg_match_all('/(\'[^\']*?\'|"[^"]*?")/ims', $content, $hit, PREG_PATTERN_ORDER);
        for ($i = 0; $i < count($hit[1]); $i++) {
            $content = str_replace($hit[1][$i], '##########'.$i.'##########', $content);
        }
        //Remove trailing semicolon of selector's last property
        $content = preg_replace('/;[\s\r\n\t]*?}[\s\r\n\t]*/ims', "}\r\n", $content);
        //Remove any whitespace between semicolon and property-name
        $content = preg_replace('/;[\s\r\n\t]*?([\r\n]?[^\s\r\n\t])/ims', ';$1', $content);
        //Remove any whitespace surrounding property-colon
        $content = preg_replace('/[\s\r\n\t]*:[\s\r\n\t]*?([^\s\r\n\t])/ims', ':$1', $content);
        //Remove any whitespace surrounding selector-comma
        $content = preg_replace('/[\s\r\n\t]*,[\s\r\n\t]*?([^\s\r\n\t])/ims', ',$1', $content);
        //Remove any whitespace surrounding opening parenthesis
        $content = preg_replace('/[\s\r\n\t]*{[\s\r\n\t]*?([^\s\r\n\t])/ims', '{$1', $content);
        //Remove any whitespace between numbers and units
        $content = preg_replace('/([\d\.]+)[\s\r\n\t]+(px|em|pt|%)/ims', '$1$2', $content);
        //Constrain multiple whitespaces
        $content = preg_replace('/\p{Zs}+/ims', ' ', $content);
        //Remove newlines
        $content = str_replace(array("\r\n", "\r", "\n"), '', $content);
        //Restore backupped values within single or double quotes
        for ($i = 0; $i < count($hit[1]); $i++) {
            $content = str_replace('##########'.$i.'##########', $hit[1][$i], $content);
        }
        return $content;
    }

    private function parse_csstpl($result, $matches, $param)
    {
        $content = false;
        if ($result === 1) {
            $content = '/* '.$param.' */'."\n".$matches[1]."\r".'/* END '.$param.' */';
            if ($this->compress['css'] === true) {
                $matches[1] = $this->minifyCSS($matches[1]);
                $content = '/* '.$param.' */'."\n".$matches[1]."\n".'/* END '.$param.' */';
            }
        }
        return $content;
    }

    private function parse_stripvtags_js1($matches)
    {
        if ($this->options['js_dir'] === false) return $matches[1];
        return $this->stripvTags('<? echo Template::getInstance()->loadJSFile(\''.$matches[1].'\');?>');
    }

    private function parse_stripvtags_static1($matches)
    {
        if ($this->options['static_dir'] === false) return $matches[1];
        return $this->stripvTags($this->options['static_dir'].$matches[1]);
    }

    private function parse_stripvtags_echo1($matches)
    {
        return $this->stripvTags('<? echo '.$matches[1].';?>');
    }

    private function parse_stripvtags_if1($matches)
    {
        return $this->stripvTags('<? if ('.$matches[1].') { ?>');
    }

    private function parse_stripvtags_elseif1($matches)
    {
        return $this->stripvTags('<? } elseif ('.$matches[1].') { ?>');
    }

    private function parse_stripvtags_loop12($matches)
    {
        return $this->stripvTags('<? if (is_array('.$matches[1].')) foreach ('.$matches[1].' as '.$matches[2].') { ?>');
    }

    private function parse_stripvtags_loop123($matches)
    {
        return $this->stripvTags('<? if (is_array('.$matches[1].')) foreach ('.$matches[1].' as '.$matches[2].' => '.$matches[3].') { ?>');
    }

    private function parse_transamp_0($matches)
    {
        return $this->transAmp($matches[0]);
    }

    private function parse_css_minify($matches)
    {
        return $this->stripStyleTags($this->minifyCSS($matches[1]));
    }

    private function parse_stripscriptamp_12($matches)
    {
        return $this->stripScriptAmp($matches[1], $matches[2]);
    }

    private function parse_stripblock_12($matches)
    {
        return $this->stripBlock($matches[1], $matches[2]);
    }

    public static function langParam($value, $param)
    {
        foreach ($param as $index => $p) {
            $value = str_replace('{'.$index.'}', $p, $value);
        }
        return $value;
    }

/*
    private function blockTags($parameter)
    {
        $bid = intval(trim($parameter));
        $this->blocks[] = $bid;
        $i = count($this->replacecode['search']);
        $this->replacecode['search'][$i] = $search = '<!--BLOCK_TAG_'.$i.'-->';
        $this->replacecode['replace'][$i] = '<?php block_display(\''.$bid.'\');?>';
        return $search;
    }

*/
    private function stripBlock($var, $s)
    {
        $s = preg_replace("/<\?=\\\$(.+?)\?>/", "{\$\\1}", $s);
        preg_match_all("/<\?=(.+?)\?>/", $s, $constary);
        $constadd = '';
        $constary[1] = array_unique($constary[1]);
        foreach ($constary[1] as $const) {
            $constadd .= '$__'.$const.' = '.$const.';';
        }
        $s = preg_replace("/<\?=(.+?)\?>/", "{\$__\\1}", $s);
        $s = str_replace('?>', "\n\$$var .= <<<EOF\n", $s);
        $s = str_replace('<?', "\nEOF;\n", $s);
        $s = str_replace("\nphp ", "\n", $s);
        return "<?\n$constadd\$$var = <<<EOF".$s."EOF;\n?>";
    }

    private function evalTags($php)
    {
        $php = str_replace('\"', '"', $php);
        $i = count($this->replacecode['search']);
        $this->replacecode['search'][$i] = $search = '<!--EVAL_TAG_'.$i.'-->';
        $this->replacecode['replace'][$i] = '<? '."\n".$php."\n".'?>';
        return $search;
    }

    private function stripPHPCode($type, $code)
    {
        $this->phpcode[$type][] = $code;
        return '{phpcode:'.$type.'/'.(count($this->phpcode[$type]) - 1).'}';
    }

    private function getPHPTemplate($content)
    {
        $pos = strpos($content, "\n");
        return $pos !== false ? substr($content, $pos + 1) : $content;
    }

    private function transAmp($str)
    {
        $str = str_replace('&', '&amp;', $str);
        $str = str_replace('&amp;amp;', '&amp;', $str);
        return $str;
    }

    private function addQuote($var)
    {
        return str_replace("\\\"", "\"", preg_replace("/\[([a-zA-Z0-9_\-\.\x7f-\xff]+)\]/s", "['\\1']", $var));
    }

    private function stripvTags($expr, $statement = '')
    {
        $expr = str_replace('\\\"', '\"', preg_replace("/\<\?\=(\\\$.+?)\?\>/s", "\\1", $expr));
        $statement = str_replace('\\\"', '\"', $statement);
        return $expr.$statement;
    }

    private function stripStyleTags($css)
    {
        return '<style type="text/css">'.$css.'</style>';
    }

    private function stripScriptAmp($s, $extra)
    {
        $s = str_replace('&amp;', '&', $s);
        return "<script src=\"$s\"$extra></script>";
    }

    //Throw error excetpion
    private function throwError($message, $tplname)
    {
        throw new \Exception($tplname.' : '.$message);
        exit();
    }

    //Throw database error excetpion
    private function throwDBError($errorMessage, $errorCode)
    {
        $error = '<h1>Service unavailable</h1>'."\n";
        $error .= '<h2>Error Info :'.$errorMessage.'</h2>'."\n";
        $error .= '<h3>Error Code :'.$errorCode.'</h3>'."\n";
        return $error;
    }
}
