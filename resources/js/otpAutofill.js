/**
 * OTP Autofill Plugin for NativePHP Mobile — JavaScript bridge (legacy web-view apps).
 *
 * NOTE: the primary consumer in v4 is a SuperNative screen with the
 * `<native:otp-field>` element. In a Livewire/Inertia web view use a plain
 * `<input autocomplete="one-time-code" inputmode="numeric">` (iOS and Chrome
 * offer the code themselves) and, on Android, start the SMS listener from JS
 * and fill the input from the `OtpReceived` event.
 *
 * @example
 *   import { otpAutofill } from '@vipertecpro/otp-autofill';
 *   import { On } from '#nativephp';
 *
 *   On('native:Vipertecpro\\OtpAutofill\\Events\\OtpReceived', ({ code }) => {
 *       document.querySelector('#code').value = code;
 *   });
 *
 *   await otpAutofill.start({ length: 6 });   // 'consent' | 'retriever' | 'keyboard' | 'unavailable'
 */

const baseUrl = '/_native/api/call';

/**
 * Internal bridge call function.
 * @private
 */
async function bridgeCall(method, params = {}) {
    const response = await fetch(baseUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        body: JSON.stringify({ method, params })
    });

    const result = await response.json();

    if (result.status === 'error') {
        throw new Error(result.message || 'Native call failed');
    }

    return result.data ?? result;
}

/**
 * Start waiting for one verification SMS (Android).
 *
 * @param {Object} [options]
 * @param {'consent'|'retriever'} [options.mode='consent']
 * @param {string} [options.sender] - Only accept SMS from this number (consent mode).
 * @param {number} [options.length] - Exact number of digits (4–10).
 * @returns {Promise<'consent'|'retriever'|'keyboard'|'unavailable'>}
 */
export async function start(options = {}) {
    const result = await bridgeCall('OtpAutofill.Start', {
        mode: options.mode === 'retriever' ? 'retriever' : 'consent',
        sender: options.sender || null,
        length: options.length || null,
    });

    return ['consent', 'retriever', 'keyboard'].includes(result.mode) ? result.mode : 'unavailable';
}

/** Stop waiting. */
export async function stop() {
    await bridgeCall('OtpAutofill.Stop');
}

/**
 * The SMS Retriever app hash (Android), or null.
 * @returns {Promise<string|null>}
 */
export async function appHash() {
    const result = await bridgeCall('OtpAutofill.AppHash');

    return typeof result.hash === 'string' ? result.hash : null;
}

/**
 * The first 4–8 digit run (or exactly `length` digits) in a message.
 * @param {string} message
 * @param {number} [length]
 * @returns {string|null}
 */
export function extractCode(message, length) {
    const digits = length ? `${length}` : '4,8';
    const match = new RegExp(`(?<![0-9])([0-9]{${digits}})(?![0-9])`).exec(message);

    return match ? match[1] : null;
}

export const otpAutofill = { start, stop, appHash, extractCode };

export default otpAutofill;
