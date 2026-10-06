<?php

/**
 * Plugin structure tests for OTP Autofill — manifest, native files, PHP classes.
 *
 * Run with: ./vendor/bin/pest
 */
beforeEach(function () {
    $this->pluginPath = dirname(__DIR__);
    $this->manifest = json_decode(file_get_contents($this->pluginPath.'/nativephp.json'), true);
    $this->swift = file_get_contents($this->pluginPath.'/resources/ios/OtpAutofillFunctions.swift')
        .file_get_contents($this->pluginPath.'/resources/ios/OtpFieldRenderer.swift');
    $this->kotlin = file_get_contents($this->pluginPath.'/resources/android/OtpAutofillFunctions.kt')
        .file_get_contents($this->pluginPath.'/resources/android/OtpFieldRenderer.kt');
});

describe('Plugin Manifest', function () {
    it('has required fields', function () {
        expect(json_last_error())->toBe(JSON_ERROR_NONE);
        expect($this->manifest['name'])->toBe('vipertecpro/otp-autofill');
        expect($this->manifest['namespace'])->toBe('OtpAutofill');
        expect($this->manifest['version'])->toBe('1.0.0');
    });

    it('exposes the three bridge functions', function () {
        foreach ($this->manifest['bridge_functions'] as $function) {
            expect($function)->toHaveKeys(['name', 'android', 'ios', 'description']);
        }

        expect(array_column($this->manifest['bridge_functions'], 'name'))->toBe(['OtpAutofill.Start', 'OtpAutofill.Stop', 'OtpAutofill.AppHash']);
    });

    it('declares the otp_field UI component with its renderers and @complete event', function () {
        expect($this->manifest['components'])->toHaveCount(1);
        $component = $this->manifest['components'][0];

        expect($component)->toMatchArray([
            'type' => 'otp_field',
            'element' => 'Vipertecpro\\OtpAutofill\\Elements\\OtpField',
            'blade' => 'Vipertecpro\\OtpAutofill\\Components\\OtpField',
            'android_renderer' => 'com.vipertecpro.plugins.otp_autofill.OtpFieldRenderer',
            'ios_renderer' => 'OtpFieldRenderer',
            'self_closing' => true,
            'element_events' => ['complete'],
        ]);
        expect(class_exists($component['element']))->toBeTrue();
        expect(class_exists($component['blade']))->toBeTrue();
        expect($this->kotlin)->toContain('object OtpFieldRenderer');
        expect($this->swift)->toContain('struct OtpFieldRenderer: View');
    });

    it('has marketplace metadata filled in', function () {
        expect($this->manifest['keywords'])->toBeArray()->not->toBeEmpty();
        expect($this->manifest['category'])->toBe('authentication');
        expect($this->manifest['pricing']['type'])->toBe('free');
        expect($this->manifest['platforms'])->toBe(['android', 'ios']);
        expect(array_slice(getimagesize($this->pluginPath.'/resources/icon.png'), 0, 2))->toBe([512, 512]);
    });

    it('needs no SMS permission and only the Play services SMS API', function () {
        expect($this->manifest['android']['permissions'])->toBe([]);
        expect($this->manifest['android']['dependencies']['implementation'])->toBe(['com.google.android.gms:play-services-auth-api-phone:18.2.0']);
        expect($this->kotlin)->not->toContain('READ_SMS')->not->toContain('RECEIVE_SMS');
    });

    it('declares the two events and sends them from Android', function () {
        expect($this->manifest['events'])->toBe([
            'Vipertecpro\\OtpAutofill\\Events\\OtpReceived',
            'Vipertecpro\\OtpAutofill\\Events\\OtpFailed',
        ]);

        foreach ($this->manifest['events'] as $event) {
            expect(class_exists($event))->toBeTrue();
            expect($this->kotlin)->toContain(str_replace('\\', '\\\\', $event));
        }
    });
});

describe('Native Code', function () {
    it('has every bridge class on both platforms', function () {
        foreach ($this->manifest['bridge_functions'] as $function) {
            $android = explode('.', $function['android']);
            $ios = explode('.', $function['ios']);
            expect($this->kotlin)->toContain('class '.end($android).'(');
            expect($this->swift)->toContain('class '.end($ios).':');
        }
    });

    it('uses the platform autofill hooks', function () {
        expect($this->swift)->toContain('.textContentType(.oneTimeCode)')->toContain('.keyboardType(.numberPad)');
        expect($this->kotlin)->toContain('ContentType.SmsOtpCode')
            ->toContain('startSmsUserConsent')
            ->toContain('startSmsRetriever')
            ->toContain('SmsRetriever.SEND_PERMISSION');
    });

    it('extracts codes with the same rule as PHP', function () {
        expect($this->kotlin)->toContain('(?<![0-9])([0-9]{$digits})(?![0-9])');
        expect(file_get_contents($this->pluginPath.'/src/OtpAutofill.php'))->toContain("'/(?<![0-9])([0-9]{'.\$digits.'})(?![0-9])/'");
    });
});

describe('Documentation', function () {
    it('ships the product files', function () {
        foreach (['README.md', 'CHANGELOG.md', 'CONTRIBUTING.md', 'RELEASING.md', 'LICENSE', 'resources/boost/guidelines/core.blade.php', 'resources/js/otpAutofill.js'] as $file) {
            expect(file_exists($this->pluginPath.'/'.$file))->toBeTrue("missing {$file}");
        }
    });

    it('keeps the README free of links and ends with the store line', function () {
        $readme = file_get_contents($this->pluginPath.'/README.md');

        expect($readme)->not->toMatch('/\]\(/')->not->toMatch('/https?:\/\//')->not->toContain('<a ');
        expect(trim(last(explode("\n", trim($readme)))))->toContain('vipertecpro.com');
    });

    it('lists the 1.0.0 release in the changelog', function () {
        expect(file_get_contents($this->pluginPath.'/CHANGELOG.md'))->toContain('## [1.0.0]');
    });
});
