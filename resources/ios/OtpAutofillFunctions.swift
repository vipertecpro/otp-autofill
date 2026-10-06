import Foundation

// =============================================================================
// OtpAutofill — iOS native side (bridge functions)
// =============================================================================
//
// iOS does not let apps read SMS. One-time codes reach the app through the
// keyboard: a text field with textContentType = .oneTimeCode (the
// <native:otp-field> element, OtpFieldRenderer.swift) shows "From Messages:
// 123456" above the keyboard and fills the field when tapped.
//
// So the bridge functions only report that:
//   • Start   — {"mode": "keyboard"}: nothing to listen to, no event follows.
//   • Stop    — nothing to stop.
//   • AppHash — the SMS Retriever hash is an Android concept: {}.
// =============================================================================

enum OtpAutofillFunctions {
    class Start: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            ["mode": "keyboard"]
        }
    }

    class Stop: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            [:]
        }
    }

    class AppHash: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            [:]
        }
    }
}
