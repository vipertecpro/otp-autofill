<?php

namespace Vipertecpro\OtpAutofill;

use InvalidArgumentException;

/**
 * SMS one-time-code autofill for NativePHP Mobile.
 *
 * - iOS never lets apps read SMS. The code reaches the app through the
 *   keyboard: put a `<native:otp-field>` on screen and iOS offers the code
 *   from Messages above the keyboard ("From Messages: 123456").
 * - Android reads the code from the incoming SMS with Google Play services,
 *   without the SMS permission: the SMS User Consent API (any SMS, the user
 *   taps Allow once) or the SMS Retriever API (no prompt, but the SMS must end
 *   with the app hash from {@see appHash()}). The code arrives as an
 *   {@see Events\OtpReceived} event.
 */
class OtpAutofill
{
    public const MODE_CONSENT = 'consent';

    public const MODE_RETRIEVER = 'retriever';

    /** What {@see start()} reports on iOS: the code comes from the keyboard. */
    public const MODE_KEYBOARD = 'keyboard';

    public const MODE_UNAVAILABLE = 'unavailable';

    /**
     * Start waiting for one verification SMS (Android). Call it right before
     * or right after asking your server to send the code; Android waits up to
     * five minutes for one message.
     *
     * @param  array{mode?: string, sender?: string|null, length?: int|null}  $options
     *                                                                                  `mode`: `consent` (default) or `retriever`.
     *                                                                                  `sender`: only accept SMS from this number (consent mode).
     *                                                                                  `length`: the exact number of digits; otherwise 4–8.
     * @return string `consent` or `retriever` when Android is listening,
     *                `keyboard` on iOS, `unavailable` without Google Play
     *                services or outside a native app.
     */
    public function start(array $options = []): string
    {
        $mode = $options['mode'] ?? self::MODE_CONSENT;

        if (! in_array($mode, [self::MODE_CONSENT, self::MODE_RETRIEVER], true)) {
            throw new InvalidArgumentException("OtpAutofill: mode must be \"consent\" or \"retriever\", got \"{$mode}\".");
        }

        $length = $options['length'] ?? null;

        if ($length !== null && ($length < 4 || $length > 10)) {
            throw new InvalidArgumentException('OtpAutofill: length must be between 4 and 10 digits.');
        }

        $sender = isset($options['sender']) ? trim((string) $options['sender']) : null;

        if ($sender !== null && $sender !== '' && preg_match('/^\+?[0-9A-Za-z\s\-()]{2,20}$/', $sender) !== 1) {
            throw new InvalidArgumentException("OtpAutofill: \"{$sender}\" is not a valid sender.");
        }

        $result = $this->call('OtpAutofill.Start', [
            'mode' => $mode,
            'sender' => $sender === '' ? null : $sender,
            'length' => $length,
        ]);

        $reported = $result['mode'] ?? null;

        return in_array($reported, [self::MODE_CONSENT, self::MODE_RETRIEVER, self::MODE_KEYBOARD], true)
            ? $reported
            : self::MODE_UNAVAILABLE;
    }

    /** Stop waiting. Safe to call when nothing is pending. */
    public function stop(): void
    {
        $this->call('OtpAutofill.Stop');
    }

    /**
     * The 11-character hash an SMS must end with for the SMS Retriever API,
     * e.g. "Your code is 123456\n\nFA+9qCX9VSu". It depends on the package
     * name AND the signing key, so debug and release builds differ — read it
     * from each build you ship. Null on iOS and outside a native app.
     */
    public function appHash(): ?string
    {
        $hash = $this->call('OtpAutofill.AppHash')['hash'] ?? null;

        return is_string($hash) && preg_match('/^[A-Za-z0-9+\/]{11}$/', $hash) === 1 ? $hash : null;
    }

    /**
     * Pull the code out of an SMS. The first run of 4–8 digits (or exactly
     * `$length`) that is not part of a longer number, so "Your code: 482913"
     * gives "482913" and a phone number does not match.
     */
    public function extractCode(string $message, ?int $length = null): ?string
    {
        $digits = $length === null ? '4,8' : (string) $length;

        if (preg_match('/(?<![0-9])([0-9]{'.$digits.'})(?![0-9])/', $message, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    /**
     * Call a synchronous bridge function and decode its result; a missing
     * bridge (tests, web) or an error response yields an empty array.
     *
     * @return array<string, mixed>
     */
    protected function call(string $method, array $params = []): array
    {
        if (! function_exists('nativephp_call')) {
            return [];
        }

        $raw = nativephp_call($method, json_encode((object) $params));
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

        return is_array($decoded) && ($decoded['status'] ?? null) !== 'error' ? $decoded : [];
    }
}
