import SwiftUI
import UIKit

// =============================================================================
// <native:otp-field> — iOS renderer
// =============================================================================
//
// One real UITextField-backed SwiftUI TextField carries the input, the
// keyboard and the system autofill (.oneTimeCode + .numberPad); it sits
// transparently on top of a row of digit boxes that only draw. Tapping
// anywhere on the row focuses it, and the code suggestion from Messages fills
// every box at once. Colours come from the NativeUI theme tokens.
// =============================================================================

struct OtpFieldRenderer: View {
    let node: NativeUINode

    @ObservedObject private var themeStore = NativeUITheme.shared
    @Environment(\.colorScheme) private var colorScheme

    @State private var text = ""
    @State private var lastSent = ""
    /// Values sent to PHP whose echo has not come back yet. With live
    /// `native:model` sync every keystroke round-trips, and an older echo
    /// ("12345") can land after the user typed the next digit ("123456").
    @State private var pendingEchoes: Set<String> = []
    @State private var lastCompleted = ""
    @FocusState private var focused: Bool

    var body: some View {
        let theme = themeStore.resolve(for: colorScheme)
        let p = node.props
        let length = max(4, min(10, p.getInt("length", default: 6)))
        let serverValue = String(p.getString("value").filter(\.isNumber).prefix(length))
        let disabled = p.getBool("disabled")
        let isError = p.getBool("is_error")
        let autofocus = p.getBool("autofocus")
        let onChange = p.getCallbackId("on_change")
        let onComplete = p.getCallbackId("on_complete")
        let a11yLabel = p.getString("a11y_label")
        let a11yHint = p.getString("a11y_hint")

        let binding = Binding<String>(
            get: { text },
            set: { newValue in
                let digits = String(newValue.filter(\.isNumber).prefix(length))
                guard digits != text else { return }
                text = digits
                lastSent = digits
                pendingEchoes.insert(digits)
                if onChange != 0 {
                    NativeElementBridge.sendTextChangeEvent(onChange, nodeId: node.id, text: digits)
                }
                completeIfFull(digits, length: length, callback: onComplete)
            }
        )

        ZStack {
            HStack(spacing: 8) {
                ForEach(0..<length, id: \.self) { index in
                    box(index: index, length: length, theme: theme, isError: isError, disabled: disabled)
                }
            }
            .accessibilityHidden(true)

            TextField("", text: binding)
                .keyboardType(.numberPad)
                .textContentType(.oneTimeCode)
                .focused($focused)
                .disabled(disabled)
                .foregroundColor(.clear)
                .accentColor(.clear)
                .font(.system(size: 1))
                .frame(maxWidth: .infinity, maxHeight: .infinity)
                .contentShape(Rectangle())
                .opacity(0.02)
                .accessibilityLabel(a11yLabel.isEmpty ? "Verification code" : a11yLabel)
                .accessibilityHint(a11yHint.isEmpty ? "Enter the \(length)-digit code" : a11yHint)
                .accessibilityValue(text.isEmpty ? "Empty" : text.map(String.init).joined(separator: " "))
        }
        .frame(height: 56)
        .contentShape(Rectangle())
        .onTapGesture { if !disabled { focused = true } }
        .task(id: serverValue) {
            // Programmatic value from PHP (e.g. a code received on another
            // path, or a reset to ""). Echoes of our own edits are ignored.
            if serverValue == lastSent {
                pendingEchoes.removeAll()
            } else if pendingEchoes.contains(serverValue) {
                return
            } else {
                text = serverValue
                lastSent = serverValue
                pendingEchoes.removeAll()
                if serverValue.count < length { lastCompleted = "" }
            }
        }
        .task(id: autofocus && !disabled) {
            if autofocus && !disabled {
                try? await Task.sleep(nanoseconds: 350_000_000)
                focused = true
            }
        }
    }

    private func completeIfFull(_ digits: String, length: Int, callback: Int) {
        if digits.count < length {
            lastCompleted = ""
            return
        }
        guard digits != lastCompleted else { return }
        lastCompleted = digits
        if callback != 0 {
            NativeElementBridge.sendSubmitEvent(callback, nodeId: node.id, text: digits)
        }
    }

    @ViewBuilder
    private func box(index: Int, length: Int, theme: NativeUITokens, isError: Bool, disabled: Bool) -> some View {
        let characters = Array(text)
        let digit = index < characters.count ? String(characters[index]) : ""
        let isActive = focused && index == min(characters.count, length - 1)
        let border: Color = isError ? theme.destructive : (isActive ? theme.primary : theme.outline)

        ZStack {
            RoundedRectangle(cornerRadius: theme.radiusMd, style: .continuous)
                .fill(theme.surface)
            RoundedRectangle(cornerRadius: theme.radiusMd, style: .continuous)
                .strokeBorder(border, lineWidth: isActive || isError ? 2 : 1)
            if digit.isEmpty {
                if isActive {
                    Capsule()
                        .fill(theme.primary)
                        .frame(width: 2, height: 22)
                }
            } else {
                Text(digit)
                    .font(.system(size: 24, weight: .semibold, design: .rounded))
                    .monospacedDigit()
                    .foregroundColor(theme.onSurface)
            }
        }
        .frame(maxWidth: 52)
        .frame(maxWidth: .infinity)
        .opacity(disabled ? 0.5 : 1)
    }
}
