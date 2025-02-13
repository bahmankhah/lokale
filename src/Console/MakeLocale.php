<?php

namespace Hsm\Lokale\Console;

use Illuminate\Console\Command;

class MakeLocale extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'locale:make {--locale=} {--src=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scans the application for translation keys and generates language files for a specified locale.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $locale = $this->option('locale');
        if(!$locale){
            $locale = config('app.locale');
        }

        $files = [];
        $appDir = $this->option('src');
        if($appDir){
            $appDir = realpath($appDir);
        }else{
            $appDir = app_path();
        }
        $this->getFiles($appDir, $files);

        $allKeys = [];
        foreach ($files as $file) {
            $allKeys = array_merge($allKeys, $this->extractTranslationKeys($file));
        }
        $allKeys = array_unique($allKeys);

        foreach ($allKeys as $key) {
            $arr = [];
            $temp = explode('.', $key);
            $first = array_shift($temp);
            $this->makeArray(implode('.', $temp), $arr);
            $this->createLanguageFile($first, $locale, $arr);
        }

    }

    private function createLanguageFile($name, $locale, $array) {
        $langMain = resource_path('lang');
        $langFile = sprintf('%s/%s/%s.php', $langMain, $locale, $name);
        if (!file_exists(dirname($langFile))) {
            mkdir(dirname($langFile), 0777, true);
        }
        if (!file_exists($langFile)) {
            $content = [];
        }else{
            $content = require $langFile;
            if(!is_array($content)){
                $content = [];
            }
        }
        $array = $this->recursiveMerge($content, $array);
        $this->createPhpArrayFile($langFile, $array);
    }

    private function createPhpArrayFile($filePath, array $data) {
        $content = "<?php\n/**\n*\n*/\nreturn " . var_export($data, true) . ";\n";
        $content = str_replace(["array (", ")"], ["[", "]"], $content); // Replace `array (` with `[` and `)` with `]`
        $content = preg_replace('/=>\s+\[/', '=> [', $content); // Ensure proper spacing for `=> [` pairs
    
        file_put_contents($filePath, $content);
    }
    

    private function recursiveMerge(array $array1, array $array2)
    {
        foreach ($array2 as $key => $value) {
            if (isset($array1[$key]) && is_array($array1[$key]) && is_array($value)) {
                $array1[$key] = $this->recursiveMerge($array1[$key], $value);
            }
            elseif (!isset($array1[$key]) || $array1[$key] === '') {
                $array1[$key] = $value;
            }
        }
        return $array1;
    }

    private function makeArray($path, &$arr)
    {
        $parts = explode('.', $path);

        if (empty($parts)) {
            return;
        }

        $key = array_shift($parts); 
        if (!isset($arr[$key])) {
            $arr[$key] = []; 
        }

        if (!empty($parts)) {
            $this->makeArray(implode('.', $parts), $arr[$key]); // Recurse on the rest
        } else {
            $arr[$key] = ''; 
        }
    }

    private function extractTranslationKeys($filePath)
    {
        $content = file_get_contents($filePath);
    
        $pattern = '/
            __\(\s*[\'"]([^\'"]+)[\'"]\s*\)     |  
            trans_choice\(\s*[\'"]([^\'"]+)[\'"]\s*, |
            Lang::get\(\s*[\'"]([^\'"]+)[\'"]\s*\) |  
            Lang::choice\(\s*[\'"]([^\'"]+)[\'"]\s*, |
            @lang\(\s*[\'"]([^\'"]+)[\'"]\s*\) |  
            @choice\(\s*[\'"]([^\'"]+)[\'"]\s*,
        /x';
    
        preg_match_all($pattern, $content, $matches);
    
        return array_unique(array_filter(array_merge(
            $matches[1], // __('key')
            $matches[2], // trans_choice('key', count)
            $matches[3], // Lang::get('key')
            $matches[4], // Lang::choice('key', count)
            $matches[5], // @lang('key')
            $matches[6]  // @choice('key', count)
        )));
    }
    


    private function getFiles($path, &$files = [])
    {
        if (is_file($path)) {
            $files[] = $path;
            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $entries = scandir($path); 

        foreach ($entries as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->getFiles($path . DIRECTORY_SEPARATOR . $entry, $files);
            }
        }

        return $files; 
    }

}
