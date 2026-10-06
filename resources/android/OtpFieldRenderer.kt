package com.vipertecpro.plugins.otp_autofill

// =============================================================================
// <native:otp-field> — Android renderer (Jetpack Compose)
// =============================================================================
//
// One BasicTextField carries the input, the number keyboard and the autofill
// hint (ContentType.SmsOtpCode, so Google autofill / Gboard can offer the
// code); its decoration draws a row of digit boxes. Colours come from the
// NativeUI theme tokens.
// =============================================================================

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.autofill.ContentType
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.focus.onFocusChanged
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.contentType
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.semantics.stateDescription
import androidx.compose.ui.text.TextRange
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.TextFieldValue
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.nativephp.mobile.ui.nativerender.NativeUIBridge
import com.nativephp.mobile.ui.nativerender.NativeUINode
import com.nativephp.plugins.native_ui.NativeUITheme
import kotlinx.coroutines.delay

object OtpFieldRenderer {
    @Composable
    fun Render(node: NativeUINode, modifier: Modifier) {
        val p = node.props
        val length = p.getInt("length", 6).coerceIn(4, 10)
        val serverValue = p.getString("value").filter { it.isDigit() }.take(length)
        val disabled = p.getBool("disabled")
        val isError = p.getBool("is_error")
        val autofocus = p.getBool("autofocus")
        val onChangeCb = p.getCallbackId("on_change")
        val onCompleteCb = p.getCallbackId("on_complete")
        val a11yLabel = p.getString("a11y_label").ifBlank { "Verification code" }
        val a11yHint = p.getString("a11y_hint").ifBlank { "Enter the $length-digit code" }

        val theme = if (isSystemInDarkTheme()) NativeUITheme.dark else NativeUITheme.light
        val focusRequester = remember { FocusRequester() }

        var field by remember(node.id) { mutableStateOf(TextFieldValue(serverValue, TextRange(serverValue.length))) }
        var lastSent by remember(node.id) { mutableStateOf(serverValue) }
        // Values sent to PHP whose echo has not come back yet. With live
        // native:model sync every keystroke round-trips, and an older echo
        // ("12345") can land after the user typed the next digit ("123456").
        val pendingEchoes = remember(node.id) { mutableSetOf<String>() }
        var lastCompleted by remember(node.id) { mutableStateOf("") }
        var focused by remember { mutableStateOf(false) }

        fun completeIfFull(digits: String) {
            if (digits.length < length) {
                lastCompleted = ""
                return
            }
            if (digits == lastCompleted) return
            lastCompleted = digits
            if (onCompleteCb != 0) NativeUIBridge.sendSubmitEvent(onCompleteCb, node.id, digits)
        }

        // Programmatic value from PHP (the code from the SMS consent sheet, or
        // a reset to ""). Echoes of our own edits are ignored.
        LaunchedEffect(serverValue) {
            when {
                serverValue == lastSent -> pendingEchoes.clear()
                serverValue in pendingEchoes -> Unit
                else -> {
                    field = TextFieldValue(serverValue, TextRange(serverValue.length))
                    lastSent = serverValue
                    pendingEchoes.clear()
                    if (serverValue.length < length) lastCompleted = ""
                }
            }
        }

        LaunchedEffect(autofocus, disabled) {
            if (autofocus && !disabled) {
                delay(300)
                runCatching { focusRequester.requestFocus() }
            }
        }

        BasicTextField(
            value = field,
            onValueChange = { next ->
                val digits = next.text.filter { it.isDigit() }.take(length)
                field = TextFieldValue(digits, TextRange(digits.length))
                if (digits != lastSent) {
                    lastSent = digits
                    pendingEchoes.add(digits)
                    if (onChangeCb != 0) NativeUIBridge.sendTextChangeEvent(onChangeCb, node.id, digits)
                    completeIfFull(digits)
                }
            },
            enabled = !disabled,
            singleLine = true,
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.NumberPassword),
            textStyle = TextStyle(color = Color.Transparent),
            cursorBrush = SolidColor(Color.Transparent),
            modifier = modifier
                .fillMaxWidth()
                .heightIn(min = 56.dp)
                .focusRequester(focusRequester)
                .onFocusChanged { focused = it.isFocused }
                .semantics {
                    contentType = ContentType.SmsOtpCode
                    contentDescription = "$a11yLabel. $a11yHint"
                    stateDescription = if (field.text.isEmpty()) "Empty" else field.text.toList().joinToString(" ")
                },
            decorationBox = { _ ->
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(56.dp)
                        .alpha(if (disabled) 0.5f else 1f),
                    horizontalArrangement = Arrangement.spacedBy(8.dp, Alignment.CenterHorizontally),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    val digits = field.text
                    for (index in 0 until length) {
                        val digit = digits.getOrNull(index)?.toString() ?: ""
                        val active = focused && index == minOf(digits.length, length - 1)
                        val border = when {
                            isError -> theme.destructive
                            active -> theme.primary
                            else -> theme.outline
                        }
                        val shape = RoundedCornerShape(theme.radiusMd)

                        Box(
                            modifier = Modifier
                                .weight(1f)
                                .widthIn(max = 52.dp)
                                .height(56.dp)
                                .background(theme.surface, shape)
                                .border(BorderStroke(if (active || isError) 2.dp else 1.dp, border), shape),
                            contentAlignment = Alignment.Center,
                        ) {
                            if (digit.isEmpty()) {
                                if (active) {
                                    Box(
                                        modifier = Modifier
                                            .width(2.dp)
                                            .height(22.dp)
                                            .background(theme.primary, RoundedCornerShape(1.dp)),
                                    )
                                }
                            } else {
                                Text(
                                    text = digit,
                                    color = theme.onSurface,
                                    fontSize = 24.sp,
                                    fontWeight = FontWeight.SemiBold,
                                )
                            }
                        }
                    }
                }
            },
        )
    }
}
