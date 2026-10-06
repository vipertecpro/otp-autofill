<?php

namespace Vipertecpro\OtpAutofill\Elements;

use Native\Mobile\Edge\CallbackRegistry;
use Native\Mobile\Edge\Element;

/**
 * `<native:otp-field>` — a row of digit boxes backed by one native text field
 * that the system can autofill: `textContentType = .oneTimeCode` on iOS and
 * the `SmsOtpCode` autofill hint on Android.
 *
 *     <native:otp-field :length="6" native:model="code" @complete="verify" autofocus />
 *
 * Attributes: `length` (4–10, default 6), `value`, `autofocus`, `disabled`,
 * `is-error`, `a11y-label`, `a11y-hint`; events `@change` (every edit,
 * `native:model` friendly) and `@complete` (the code is full; receives it).
 * Colours come from the NativeUI theme tokens.
 */
class OtpField extends Element
{
    protected string $type = 'otp_field';

    /** @var array<string, mixed> */
    protected array $fieldProps = ['length' => 6, 'value' => ''];

    protected ?string $changeCallback = null;

    protected ?string $completeCallback = null;

    public static function make(): static
    {
        return new static;
    }

    /** @return list<string> */
    public static function elementEvents(): array
    {
        return ['complete'];
    }

    public function applyAttributes(array $attrs): void
    {
        if (isset($attrs['length'])) {
            $this->length((int) $attrs['length']);
        }
        if (array_key_exists('value', $attrs)) {
            $this->value((string) ($attrs['value'] ?? ''));
        }
        if (! empty($attrs['autofocus'])) {
            $this->autofocus();
        }
        if (! empty($attrs['disabled'])) {
            $this->disabled();
        }
        if (! empty($attrs['is-error']) || ! empty($attrs['isError'])) {
            $this->error();
        }
        if (isset($attrs['sync-mode']) || isset($attrs['syncMode'])) {
            $this->fieldProps['sync_mode'] = (string) ($attrs['sync-mode'] ?? $attrs['syncMode']);
        }

        $this->applyA11yAttributes($attrs);
    }

    public function length(int $digits): static
    {
        $this->fieldProps['length'] = max(4, min(10, $digits));

        return $this;
    }

    public function value(string $value): static
    {
        $this->fieldProps['value'] = substr(preg_replace('/[^0-9]/', '', $value) ?? '', 0, 10);

        return $this;
    }

    public function autofocus(bool $value = true): static
    {
        $this->fieldProps['autofocus'] = $value;

        return $this;
    }

    public function disabled(bool $value = true): static
    {
        $this->fieldProps['disabled'] = $value;

        return $this;
    }

    public function error(bool $value = true): static
    {
        $this->fieldProps['is_error'] = $value;

        return $this;
    }

    public function onChange(string $method): static
    {
        $this->changeCallback = $method;

        return $this;
    }

    public function onComplete(string $method): static
    {
        $this->completeCallback = $method;

        return $this;
    }

    protected function resolveProps(CallbackRegistry $registry): array
    {
        $props = $this->fieldProps;
        $props['value'] = substr((string) $props['value'], 0, (int) $props['length']);

        if ($this->changeCallback !== null) {
            $props['on_change'] = $registry->register($this->changeCallback);
        }

        if ($this->completeCallback !== null) {
            $props['on_complete'] = $registry->register($this->completeCallback);
        }

        return $props;
    }
}
