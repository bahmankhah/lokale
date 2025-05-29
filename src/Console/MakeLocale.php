<?php

namespace Hsm\Lokale\Console;

use Hsm\Lokale\FileService;
use Hsm\Lokale\TranslationCollection;
use Illuminate\Console\Command;

class MakeLocale extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'locale:make {--locale=} {--src=app} {--default=default} {--comment} {--output=lang} {--no-placeholder}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $fileService = new FileService($this->option('output'), $this->option('comment'), !$this->option('no-placeholder'), $this->option('default'));
        $locale = $this->option('locale');
        if (!$locale) {
            $locale = config('app.locale');
        }

        $files = [];
        $appDir = $this->option('src');
        $appDir = realpath($appDir);
        $fileService->getFiles($appDir, $files);
        $allKeys = new TranslationCollection();
        foreach ($files as $file) {
            $allKeys->appendCollection($fileService->extractTranslationKeysWithParser($file));
        }
        $grouped = $allKeys->groupByFile();
        foreach($grouped as $name=>$translationCollection){
            $fileService->createLanguageFileFromCollection($name, $locale, $translationCollection);
        }

    }

}
