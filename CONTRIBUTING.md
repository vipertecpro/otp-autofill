# Contributing

Thanks for helping improve **OTP Autofill**. This is a NativePHP Mobile plugin
with a thin PHP layer, a native UI element and hand-written native code (Swift
on iOS, Kotlin on Android). Contributions of all kinds are welcome — bug
reports, docs, and code.

## Getting set up

```json
"repositories": [
    { "type": "path", "url": "../otp-autofill" }
]
```

```bash
composer require vipertecpro/otp-autofill:@dev
php artisan native:plugin:register vipertecpro/otp-autofill
php artisan native:run android   # or: ios — a UI component always needs a rebuild
```

## Running the tests

```bash
vendor/bin/pest
```

The PHP tests cover option validation, code extraction, the element's props
and the events; native behaviour is verified on a simulator / emulator.

## Project layout

```
src/OtpAutofill.php                          entry point — start, stop, appHash, extractCode
src/Facades/OtpAutofill.php                  the OtpAutofill facade
src/Elements/OtpField.php                    the otp_field element: attributes → props, callbacks
src/Components/OtpField.php                  Blade component for <native:otp-field>
src/Events/OtpReceived.php                   code read from an SMS (code, message, source)
src/Events/OtpFailed.php                     waiting ended without a code (reason, message)
resources/ios/OtpFieldRenderer.swift         SwiftUI renderer (.oneTimeCode text field + boxes)
resources/ios/OtpAutofillFunctions.swift     iOS bridge functions (report "keyboard")
resources/android/OtpFieldRenderer.kt        Compose renderer (SmsOtpCode hint + boxes)
resources/android/OtpAutofillFunctions.kt    SMS User Consent / SMS Retriever, app hash
resources/js/otpAutofill.js                  JS bridge for legacy web-view apps
resources/boost/guidelines/core.blade.php    Laravel Boost / AI usage guidelines
nativephp.json                               manifest: bridge functions, component, dependency, events
```

## How it works

```
PHP  OtpAutofill::start(['mode' => 'consent', 'length' => 6])
  └─ nativephp_call("OtpAutofill.Start") → {"mode": "consent"}       ← returns at once
        ├─ iOS:     {"mode": "keyboard"} — the field's .oneTimeCode does the rest
        └─ Android: SmsRetriever.startSmsUserConsent(sender) / startSmsRetriever()
                    → BroadcastReceiver(SMS_RETRIEVED_ACTION)
                    → consent sheet (Allow / Deny) or the message directly
                    → extract code → dispatch OtpReceived { code, message, source }
                                     or OtpFailed { reason, message }

Blade <native:otp-field native:model="code" @complete="verify" />
  └─ every edit → TEXT_CHANGE (native:model sync); full → SUBMIT on the @complete callback
```

## Verifying native changes

1. Android emulator with Google Play: tap Send code in the demo, then
   `adb emu sms send 5551234 "Your code is 123456"`; the consent sheet appears;
   Allow fills the field, Deny reports `denied`.
2. Retriever mode: append the hash shown in the demo to the SMS; no sheet.
3. iOS Simulator: autofocus, typing, the error state and `@complete`. On a
   device, send a real SMS and tap the keyboard suggestion.
