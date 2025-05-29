<?php

namespace Hsm\Lokale;

use Hsm\Lokale\Console\AttributesLocale;
use Hsm\Lokale\Console\MakeLocale;
use Hsm\Lokale\Console\SyncLocale;
use Illuminate\Support\ServiceProvider;

class LokaleServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->commands([
            MakeLocale::class,
            AttributesLocale::class,
            SyncLocale::class
        ]);
    }

    public function boot()
    {
        // You can add package-specific boot logic here
    }
}
