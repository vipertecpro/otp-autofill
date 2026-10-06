<?php

use Native\Mobile\Edge\CallbackRegistry;
use Vipertecpro\OtpAutofill\Elements\OtpField;
use Vipertecpro\OtpAutofill\Events\OtpFailed;
use Vipertecpro\OtpAutofill\Events\OtpReceived;
use Vipertecpro\OtpAutofill\OtpAutofill;

/**
 * Behaviour of the PHP layer. nativephp_call() is absent here, so start()
 * reports "unavailable" and appHash() null; validation still throws.
 */
describe('extractCode()', function () {
    it('finds the first 4–8 digit code that is not part of a longer number', function (string $message, ?string $code) {
        expect((new OtpAutofill)->extractCode($message))->toBe($code);
    })->with([
        ['Your code is 482913', '482913'],
        ['G-1234 is your Google verification code', '1234'],
        ['Code: 12345678. Expires in 10 minutes', '12345678'],
        ['Call +15550100199 if this was not you', null],
        ['Your code is 12', null],
        ['Use 4829 13 to sign in', '4829'],
        ["Your Free Plugins Demo code is 004213. Don't share it.\n\nFA+9qCX9VSu", '004213'],
    ]);

    it('honours an exact length', function () {
        $kit = new OtpAutofill;

        expect($kit->extractCode('Order 1234, code 567890', 6))->toBe('567890')
            ->and($kit->extractCode('Order 1234, code 567890', 4))->toBe('1234')
            ->and($kit->extractCode('Code 12345', 6))->toBeNull();
    });
});

describe('start()', function () {
    it('reports unavailable outside a native app', function () {
        expect((new OtpAutofill)->start())->toBe('unavailable')
            ->and((new OtpAutofill)->start(['mode' => 'retriever', 'sender' => '+1 555 0100', 'length' => 6]))->toBe('unavailable')
            ->and((new OtpAutofill)->appHash())->toBeNull();

        (new OtpAutofill)->stop();
    });

    it('rejects bad options before the bridge', function (array $options, string $message) {
        expect(fn () => (new OtpAutofill)->start($options))->toThrow(InvalidArgumentException::class, $message);
    })->with([
        [['mode' => 'push'], 'mode must be'],
        [['length' => 3], 'between 4 and 10'],
        [['length' => 11], 'between 4 and 10'],
        [['sender' => 'drop table; --'], 'not a valid sender'],
    ]);
});

describe('<native:otp-field>', function () {
    it('normalises its attributes into props', function () {
        $field = OtpField::make();
        $field->applyAttributes(['length' => '12', 'value' => '12 34-56', 'autofocus' => true, 'is-error' => true]);
        $field->onChange('__syncProperty(\'code\')');
        $field->onComplete('verify');

        $resolve = new ReflectionMethod($field, 'resolveProps');
        $props = $resolve->invoke($field, new CallbackRegistry);

        expect($props['length'])->toBe(10)
            ->and($props['value'])->toBe('123456')
            ->and($props['autofocus'])->toBeTrue()
            ->and($props['is_error'])->toBeTrue()
            ->and($props['on_change'])->toBeInt()
            ->and($props['on_complete'])->toBeInt()
            ->and($props['on_complete'])->not->toBe($props['on_change']);
    });

    it('clamps a short length and trims the value to it', function () {
        $field = OtpField::make()->length(2)->value('123456');
        $props = (new ReflectionMethod($field, 'resolveProps'))->invoke($field, new CallbackRegistry);

        expect($props['length'])->toBe(4)->and($props['value'])->toBe('1234');
    });

    it('declares the custom @complete event', function () {
        expect(OtpField::elementEvents())->toBe(['complete']);
    });
});

describe('events', function () {
    it('carry the native payload by name', function () {
        $received = new OtpReceived(code: '482913', message: 'Your code is 482913', source: 'retriever');
        $failed = new OtpFailed(reason: OtpFailed::TIMEOUT);

        expect($received->code)->toBe('482913')
            ->and($received->source)->toBe('retriever')
            ->and($failed->reason)->toBe('timeout')
            ->and($failed->message)->toBeNull();
    });
});
