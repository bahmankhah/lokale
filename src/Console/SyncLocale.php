<?php

namespace Hsm\Lokale\Console;

use Hsm\Lokale\FileService;
use Hsm\Lokale\TranslationCollection;
use Illuminate\Console\Command;

class SyncLocale extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'locale:sync {--from=} {--to=} {--output=lang} {--comment} {--no-placeholder}';

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
        $fileService = new FileService($this->option('output'), $this->option('comment'), !$this->option('no-placeholder'));
        $from = $this->option('from');
        $to = $this->option('to');
        if (is_null($from) || is_null($to)) {
            $this->error('the --from and --to options are required');
            return 1;
        }


        $files = [];
        $fileService->getFiles(base_path($this->option('output')) . '/' . $from, $files);
        foreach ($files as $file) {
            $arr = require $file;
            $fileName = preg_replace('/\.[^.]+$/', '', basename($file));
            $collection = TranslationCollection::fromArray($fileName, $arr);
            $fileService->createLanguageFileFromCollection($fileName, $to, $collection);
        }
    }

}
