## vipertecpro/otp-autofill

A NativePHP Mobile plugin for SMS one-time codes: a native `<native:otp-field>`
element (iOS `.oneTimeCode` keyboard autofill, Android `SmsOtpCode` hint) and
the Android SMS User Consent / SMS Retriever APIs, which deliver the code as an
event. Works with NativePHP Mobile v4 and NativePHP UI.

### What it does / does not do

- iOS never lets apps read SMS. On iOS the code arrives through the keyboard
  suggestion into the otp-field; `start()` returns `keyboard` and no event fires.
- Android reads ONE SMS within five minutes after `start()`, via Google Play
  services, without the SMS permission, and dispatches `OtpReceived` / `OtpFailed`.
- It does not send SMS. Your server sends the code.

### Facade methods

`use Vipertecpro\OtpAutofill\Facades\OtpAutofill;`

- `OtpAutofill::start(array $options = []): string` — options `mode` (`consent` default, or `retriever`), `sender` (consent only), `length` (4–10). Returns `consent`, `retriever`, `keyboard` (iOS) or `unavailable`.
- `OtpAutofill::stop(): void`
- `OtpAutofill::appHash(): ?string` — the 11-character hash the SMS must contain for retriever mode (Android; differs per signing key).
- `OtpAutofill::extractCode(string $message, ?int $length = null): ?string`

### Element

```blade
<native:otp-field :length="6" native:model="code" @complete="verify" autofocus :is-error="$wrong" a11y-label="Verification code" />
```

Attributes: `length`, `value` / `native:model`, `@change`, `@complete` (receives the full code), `autofocus`, `is-error`, `disabled`, `a11y-label`, `a11y-hint`.

### Events

- `Vipertecpro\OtpAutofill\Events\OtpReceived(string $code, ?string $message, string $source)`
- `Vipertecpro\OtpAutofill\Events\OtpFailed(string $reason, ?string $message)` — `timeout`, `denied`, `no_code`, `unavailable`, `error`.

### Example

```php
public function sendCode(): void
{
    // ask your API to text the code, then:
    OtpAutofill::start(['length' => 6]);
}

#[On(OtpReceived::class)]
public function onCode(string $code): void
{
    $this->code = $code;   // fills the field
    $this->verify($code);
}
```

### Do

- Always keep the field typeable; autofill is a convenience, not a requirement.
- Call `start()` again when the user asks for a new code.
- For web views use `<input autocomplete="one-time-code" inputmode="numeric">`.

### Don't

- Don't request `READ_SMS` / `RECEIVE_SMS`.
- Don't hard-code one app hash for all builds.
