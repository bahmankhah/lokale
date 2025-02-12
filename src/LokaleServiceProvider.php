<?php

namespace Hsm\Lokale;

use Hsm\Lokale\Console\MakeLocale;
use Illuminate\Support\ServiceProvider;

class LokaleServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->commands([
            MakeLocale::class,
        ]);
    }

    public function boot()
    {
        // You can add package-specific boot logic here
    }
}
