# OTP Autofill for NativePHP — SMS one-time codes that fill themselves

Phone verification and sign-in codes without copy-and-paste. OTP Autofill gives
you a native **`<native:otp-field>`** — a row of digit boxes backed by a real
system text field — and the platform machinery that fills it:

- **iOS**: the field is a `.oneTimeCode` field, so when the SMS arrives the
  keyboard offers **"From Messages: 482913"** and one tap fills every box.
- **Android**: the plugin reads the code straight from the incoming SMS with
  Google's **SMS User Consent API** (one "Allow" tap, works with any SMS) or
  the **SMS Retriever API** (no prompt at all, the SMS carries your app hash),
  and hands it to PHP as an `OtpReceived` event.

No SMS permission on either platform, nothing for app review to question.
Hand-written Swift and Kotlin; the only dependency is Google Play services'
`play-services-auth-api-phone`, which is where Android's SMS APIs live.

## Features

- **Native code field** — `<native:otp-field>` with 4–10 boxes, `native:model` binding, `@change` and `@complete`, error and disabled states, theme colours, light and dark
- ⌨️ **iOS keyboard autofill** — `textContentType = .oneTimeCode` and the number pad, so Messages' code suggestion just works
- **Android SMS User Consent** — any verification SMS, optionally only from your sender; the user taps Allow once
- **Android SMS Retriever** — zero-tap: the SMS ends with your 11-character app hash, which `OtpAutofill::appHash()` computes for you
- **Code extraction** — the first 4–8 digit run (or exactly N digits) that is not part of a longer number, on the device and in PHP
- **Events** — `OtpReceived` (code, message, source) and `OtpFailed` (timeout, denied, no_code, unavailable)
- **No SMS permission** — never `READ_SMS` or `RECEIVE_SMS`
- **Accessible** — one labelled field for screen readers, the boxes are decoration

## Requirements

- PHP 8.4+
- NativePHP Mobile v4 (`nativephp/mobile: ^4.0`) with `nativephp/mobile-ui` 0.8+ for the theme tokens — tested against 4.6 and 0.8.0
- iOS 15+ / Android 10+ (API 29) with Google Play services for the SMS APIs

## Installation

```bash
composer require vipertecpro/otp-autofill
php artisan vendor:publish --tag=nativephp-plugins-provider   # once per app
php artisan native:plugin:register vipertecpro/otp-autofill
php artisan native:plugin:list       # verify "OtpAutofill", its three functions and the otp_field component
php artisan native:run ios           # or: android — rebuild so the native code compiles in
```

> Requiring with Composer is **not** enough — an unregistered plugin does
> nothing. Always run `native:plugin:register` and confirm with
> `native:plugin:list`. A new UI component always needs a rebuild.

## Permissions and project setup

| Platform | What the plugin adds | Why |
|---|---|---|
| iOS | nothing | Keyboard code suggestions need no entitlement or Info.plist key |
| Android | `com.google.android.gms:play-services-auth-api-phone` (Gradle) | The SMS User Consent and SMS Retriever APIs ship in Google Play services |
| Android | a runtime broadcast receiver, registered only while waiting | Receives `SMS_RETRIEVED_ACTION`, protected by the Play services send permission |

No `AndroidManifest.xml` permissions are added. The plugin never asks for
`READ_SMS` or `RECEIVE_SMS`, which Google Play restricts.

## Usage

```php
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;
use Vipertecpro\OtpAutofill\Events\OtpFailed;
use Vipertecpro\OtpAutofill\Events\OtpReceived;
use Vipertecpro\OtpAutofill\Facades\OtpAutofill;

class VerifyPhone extends NativeComponent
{
    public string $code = '';
    public string $error = '';

    public function sendCode(): void
    {
        Http::post(config('services.api.url').'/verify/send', ['phone' => $this->phone]);

        OtpAutofill::start(['length' => 6]);      // Android starts listening; iOS: "keyboard"
    }

    #[On(OtpReceived::class)]
    public function onCode(string $code): void
    {
        $this->code = $code;                      // fills the boxes
        $this->verify($code);
    }

    #[On(OtpFailed::class)]
    public function onFailed(string $reason): void
    {
        $this->error = $reason === 'denied' ? '' : 'Type the code from the SMS.';
    }

    public function verify(string $code): void
    {
        // check it with your API
    }
}
```

```blade
<native:otp-field :length="6" native:model="code" @complete="verify" autofocus :is-error="$error !== ''" a11y-label="Verification code" />
```

`@complete` receives the code as soon as every box is filled — by typing, by
the iOS keyboard suggestion, by pasting, or by `OtpReceived` setting
`$code`. Bind with `native:model` so the field and your property stay in sync.

### The field

| Attribute | Values | Default |
|---|---|---|
| `length` | 4–10 digits | 6 |
| `native:model` / `value` | the digits so far | `""` |
| `@complete` | method, receives the full code | — |
| `@change` | method, receives the digits on every edit | — |
| `autofocus` | focus and show the number pad when it appears | off |
| `is-error` | red boxes, e.g. after a wrong code | off |
| `disabled` | read-only, dimmed | off |
| `a11y-label`, `a11y-hint` | screen-reader text | "Verification code" |

Use normal layout classes around it (`w-full`, padding on the parent). Colours
come from your NativeUI theme: `primary` for the active box, `outline`,
`surface`, `on-surface` and `destructive`.

### Android: consent or retriever

```php
OtpAutofill::start();                                     // consent, any sender, 4–8 digits
OtpAutofill::start(['sender' => '+15550100', 'length' => 6]);
OtpAutofill::start(['mode' => 'retriever', 'length' => 6]);
OtpAutofill::stop();
```

| | SMS User Consent | SMS Retriever |
|---|---|---|
| User action | taps **Allow** on a system sheet showing the SMS | none |
| SMS format | anything with a 4–10 character code | must contain your app hash |
| Server change | none | add the hash to the message |

`start()` returns `consent` or `retriever` when Android is listening,
`keyboard` on iOS, and `unavailable` without Google Play services or outside
a native app. Android waits up to five minutes for one message; call
`start()` again for a resend.

### The app hash (retriever mode)

```php
OtpAutofill::appHash();   // "FA+9qCX9VSu" — Android only, null on iOS
```

Your server appends it to the SMS, e.g. `Your code is 482913.\n\nFA+9qCX9VSu`.
The hash is derived from the package name **and the signing key**, so your
debug build, your upload key and Play App Signing each have a different one.
Read it from the build you ship (log it once from a release build) and send
the right one from your server.

### Web-view screens

For a Livewire or Inertia web view, use a plain input with the standard
hints — iOS and Chrome then offer the code themselves:

```html
<input type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="6">
```

On Android you can still call `OtpAutofill::start()` and put the received code
into the input. To let browsers match the code to your site, end the SMS
with a WebOTP line: an `@` followed by your domain, a space, and `#` plus the
code.

### API

```php
OtpAutofill::start(array $options = []): string;   // mode, sender, length
OtpAutofill::stop(): void;
OtpAutofill::appHash(): ?string;
OtpAutofill::extractCode(string $message, ?int $length = null): ?string;
```

Invalid options throw `InvalidArgumentException`: a mode other than
`consent` / `retriever`, a length outside 4–10, or a malformed sender.

### Events

| Event | Payload | Fired when |
|---|---|---|
| `Vipertecpro\OtpAutofill\Events\OtpReceived` | `string $code`, `?string $message`, `string $source` | Android read the SMS and found a code. `$source` is `consent` or `retriever`. |
| `Vipertecpro\OtpAutofill\Events\OtpFailed` | `string $reason`, `?string $message` | `timeout` (no SMS in five minutes), `denied` (the user tapped Deny), `no_code`, `unavailable`, `error`. |

iOS fires no events: the code goes into the field through the keyboard and
reaches you through `native:model` and `@complete`.

## What you can build

OTP Autofill is a building block: the code field, the iOS keyboard suggestion and
the Android SMS reading are done, and the product around them is yours. These
ideas sit comfortably inside store policy because the plugin only reads the
one-time code that your own service just sent to the person who is using the app,
with no SMS permission. It cannot read other messages and it must never be used
to look at anyone else's texts. Your server has to send the SMS through your own
SMS provider; the plugin does not send anything.

**Sign-in and sign-up**

- **Phone-number login.** Ask for a number, send a six-digit code from your
  backend, call `OtpAutofill::start()` and let the field fill itself on both
  platforms. Verify the code with your API in `@complete`.
- **Passwordless accounts for consumer apps.** Skip passwords entirely for a
  shopping, travel or community app, with the code field as the whole sign-in
  screen.
- **A second step for an existing account.** Ask for a code before a sensitive
  action, such as changing a phone number or an email address.

**Money and deliveries**

- **Confirming a payment or a transfer.** The bank or wallet backend sends the
  code; the field takes it from the SMS. The payment itself stays with your
  payment provider.
- **Delivery handover.** A customer gives the courier a code from their own
  phone to confirm the parcel arrived. Your backend creates and checks the code.

**Services and workplaces**

- **Clinic and appointment check-in.** A patient confirms their number when
  booking. Keep the message free of medical detail, and follow the stores'
  health-data rules if the app holds health information.
- **Staff and gig-worker apps.** A one-time code to verify a phone when someone
  joins a shift roster or clocks in from their own device.

For zero-tap reading on Android, use retriever mode and add your app hash to the
SMS; the hash is different for debug builds, your upload key and Play App
Signing. On iOS the person taps the keyboard suggestion, and Android with Google
Play services is required for SMS reading. Do not use the codes for anything but
verifying the person who asked for them, and keep resend limits on your backend.

## Limitations

- **iOS cannot read SMS.** Autofill needs the user to tap the keyboard
  suggestion, and it needs the code to arrive in Messages on the same device.
  It does not appear on the iOS Simulator, which has no Messages.
- **Android needs Google Play services.** Devices without it (some Huawei
  models, de-Googled ROMs) get `unavailable`; the field still works for typing.
- **One message per `start()`**, for up to five minutes.
- **Retriever hashes differ per signing key** — the most common setup mistake.
- **Digits only.** Alphanumeric codes are not supported by the field.

## Verified on

- iOS Simulator, iPhone 17 Pro (iOS 26.5): the field in light and dark mode,
  autofocus, typing, the wrong-code error state, clearing, `@complete` and
  verification. The Messages code suggestion itself needs a physical iPhone and
  was not exercised.
- Android emulator, Pixel 9 (API 36) with Google Play: an SMS sent to the
  emulator opened the consent sheet, Allow filled the field and verified, Deny
  produced `OtpFailed` (`denied`), and retriever mode read an SMS ending with
  the computed app hash with no prompt — in light and dark mode.

## Demo

The companion demo app **free-plugins-demo** contains a complete "Verify your
phone" screen — send, listen, autofill, verify, wrong-code and failure states —
in one small `NativeComponent` you can copy from.

## Contributing

Issues and pull requests are welcome. See the `CONTRIBUTING.md` file included
with the package for local setup, the project layout and how it works.

## Changelog

See the `CHANGELOG.md` file included with the package for the full version history.

## Licence

MIT — see the `LICENSE` file included with the package.

vipertecpro is an independent developer. NativePHP, Laravel, Apple, Google, Firebase and other names are trademarks of their respective owners; this package is not affiliated with or endorsed by them. iOS and Apple are trademarks of Apple Inc. Android, Google Play and Firebase are trademarks of Google LLC.

OTP Autofill is a free plugin from vipertecpro.com, home of the paid plugins for NativePHP Mobile: Rich-Text Editor, Onboarding & Tours, Health Data, Native Charts, Paywalls & Purchases and Pausewall.
