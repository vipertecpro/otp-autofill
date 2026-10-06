# Changelog

All notable changes to `vipertecpro/otp-autofill` are documented here.
The format is based on Keep a Changelog, and this project adheres to
Semantic Versioning.

## [1.0.0] - 2026-10-07

First release. Verified on the iOS Simulator (iPhone 17 Pro, iOS 26.5) and an
Android emulator with Google Play (Pixel 9, API 36) against NativePHP Mobile
4.6 and NativePHP UI 0.8; the iOS Messages code suggestion needs a physical
iPhone and was not exercised.

### Added
- **`<native:otp-field>`** — a native UI element: 4–10 digit boxes over one
  system text field with `.oneTimeCode` (iOS) and the `SmsOtpCode` autofill
  hint (Android); `native:model`, `@change`, custom `@complete`, `autofocus`,
  `is-error`, `disabled`, accessibility labels and NativeUI theme colours.
- **Android SMS User Consent** — `OtpAutofill::start()` reads the next
  verification SMS after one Allow tap, optionally only from one sender.
- **Android SMS Retriever** — `start(['mode' => 'retriever'])` reads an SMS
  that carries the app hash, with no prompt; `appHash()` computes the hash.
- **Code extraction** — on the device and in `OtpAutofill::extractCode()`.
- **Events** — `OtpReceived` and `OtpFailed` (`timeout`, `denied`,
  `no_code`, `unavailable`, `error`).
- A JS bridge for legacy web-view apps, web `one-time-code` guidance and
  Laravel Boost guidelines.

### Notes
- No SMS permission on either platform.
- Android depends on `com.google.android.gms:play-services-auth-api-phone`
  18.2.0 (the newest release compatible with the Kotlin 2.0 toolchain of
  NativePHP Mobile 4.6).
