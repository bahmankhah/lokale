<?php

namespace Hsm\Lokale\Console;

use Hsm\Lokale\FileService;
use Hsm\Lokale\TranslationCollection;
use Illuminate\Console\Command;

class AttributesLocale extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'locale:attributes {--locale=} {--src=app/Http/Requests} {--output=lang} {--comment} {--no-placeholder}';

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
        $files = [];
        $fileService->getFiles(base_path($this->option('src')), $files);

        foreach ($files as $file) {
            $className = $fileService->getClassFullNameFromFile($file);
            if ($className) {
                $classObject = new $className();
                if (method_exists($classObject, 'rules')) {
                    try {
                        $rules = $classObject->rules();
                        $rules = array_map(function ($rule) {
                            return '';
                        }, $rules);
                        $collection = TranslationCollection::fromArray('attributes', $rules);
                        $fileService->createLanguageFileFromCollection('attributes', $this->option('locale'), $collection);
                    } catch (\Exception $e) {
                        // ignore
                    }
                }
            }
        }
    }
}
