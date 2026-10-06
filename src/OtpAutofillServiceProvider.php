<?php

namespace Vipertecpro\OtpAutofill;

use Illuminate\Support\ServiceProvider;

class OtpAutofillServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OtpAutofill::class, function () {
            return new OtpAutofill;
        });
    }
}
