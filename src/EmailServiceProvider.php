<?php

namespace Waxis\Email;

use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;

class EmailServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {

        $this->publishes([
            __DIR__.'/migrations' => 'database/migrations',
            __DIR__.'/config/mail.php' => 'config/mail.php',
        ]);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
    }
}
