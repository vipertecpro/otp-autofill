<?php

namespace Vipertecpro\OtpAutofill\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched on Android when the verification SMS arrived and a code was
 * found in it. (On iOS the code goes straight into the otp-field instead.)
 *
 * @property string $code The digits found in the message.
 * @property ?string $message The full SMS text — consent mode, and retriever mode.
 * @property string $source `consent` or `retriever`.
 */
class OtpReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $code,
        public ?string $message = null,
        public string $source = 'consent',
    ) {}
}
