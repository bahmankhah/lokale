<?php

namespace Hsm\Lokale\Console;

use Hsm\Lokale\FileService;
use Illuminate\Console\Command;
use PhpParser\Comment;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

class MakeLocale extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'locale:make {--locale=} {--src=app} {--default=default} {--comment} {--output=lang}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    private $keyComments = [];
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $fileService = new FileService(output: $this->option('output'), comment: $this->option('comment'), keyComments: $this->keyComments);
        $locale = $this->option('locale');
        $default = $this->option('default');
        if (!$locale) {
            $locale = config('app.locale');
        }

        $files = [];
        $appDir = $this->option('src');
        $appDir = realpath($appDir);
        $fileService->getFiles($appDir, $files);
        $allKeys = [];
        foreach ($files as $file) {
            $allKeys = array_merge($allKeys, $fileService->extractTranslationKeysWithParser($file));
        }
        $allKeys = array_unique($allKeys);
        // print_r($allKeys);
        // die();

        foreach ($allKeys as $key) {
            $arr = [];
            $temp = explode('.', $key);
            $first = array_shift($temp);
            $fileService->makeArray(implode('.', $temp), $arr);
            if (empty($arr)) {
                $fileService->createLanguageFile($default, $locale, [$fileService->makeKey($first) => $first]);
            } else {
                $fileService->createLanguageFile($first, $locale, $arr);
            }
        }
    }

}
