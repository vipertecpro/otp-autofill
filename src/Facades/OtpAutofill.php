<?php

namespace Vipertecpro\OtpAutofill\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string start(array $options = [])
 * @method static void stop()
 * @method static string|null appHash()
 * @method static string|null extractCode(string $message, ?int $length = null)
 *
 * @see \Vipertecpro\OtpAutofill\OtpAutofill
 */
class OtpAutofill extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Vipertecpro\OtpAutofill\OtpAutofill::class;
    }
}
