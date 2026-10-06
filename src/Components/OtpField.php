<?php

namespace Vipertecpro\OtpAutofill\Components;

use Native\Mobile\Edge\Components\Native\NativeBladeComponent;

class OtpField extends NativeBladeComponent
{
    protected bool $isSelfClosing = true;

    protected function elementType(): string
    {
        return 'otp_field';
    }
}
