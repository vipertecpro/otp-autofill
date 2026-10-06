<?php

namespace Vipertecpro\OtpAutofill\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched on Android when waiting for the SMS ended without a code.
 *
 * @property string $reason `timeout` (no SMS within five minutes), `denied` (the user tapped Deny),
 *                          `no_code` (the SMS had no matching digits), `unavailable` (no Google Play
 *                          services) or `error`.
 * @property ?string $message Platform detail, or the SMS text for `no_code`.
 */
class OtpFailed
{
    use Dispatchable, SerializesModels;

    public const TIMEOUT = 'timeout';

    public const DENIED = 'denied';

    public const NO_CODE = 'no_code';

    public const UNAVAILABLE = 'unavailable';

    public const ERROR = 'error';

    public function __construct(
        public string $reason = self::ERROR,
        public ?string $message = null,
    ) {}
}
