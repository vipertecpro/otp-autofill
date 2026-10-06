package com.vipertecpro.plugins.otp_autofill

// =============================================================================
// OtpAutofill — Android native side (bridge functions)
// =============================================================================
//
// Reads one verification SMS without the SMS permission, through Google Play
// services (com.google.android.gms:play-services-auth-api-phone):
//
//   • consent   — SMS User Consent API. Any SMS (optionally from one sender)
//                 that contains a 4–10 digit code; Android shows a one-time
//                 "Allow <app> to read this message?" sheet.
//   • retriever — SMS Retriever API. No prompt, but the SMS must end with the
//                 app hash returned by AppHash.
//
// Either way Play services waits up to five minutes for ONE message. The
// result arrives as OtpReceived { code, message, source } or
// OtpFailed { reason, message }. The code is extracted here, natively, so the
// full SMS never has to be parsed in PHP.
// =============================================================================

import android.app.Activity
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.util.Base64
import android.util.Log
import androidx.activity.result.ActivityResultLauncher
import androidx.activity.result.contract.ActivityResultContracts
import androidx.core.content.ContextCompat
import androidx.fragment.app.Fragment
import androidx.fragment.app.FragmentActivity
import com.google.android.gms.auth.api.phone.SmsRetriever
import com.google.android.gms.common.api.CommonStatusCodes
import com.google.android.gms.common.api.Status
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.utils.NativeActionCoordinator
import org.json.JSONObject
import java.nio.charset.StandardCharsets
import java.security.MessageDigest

object OtpAutofillFunctions {

    internal const val TAG = "OtpAutofill"
    private const val EVENT_RECEIVED = "Vipertecpro\\OtpAutofill\\Events\\OtpReceived"
    private const val EVENT_FAILED = "Vipertecpro\\OtpAutofill\\Events\\OtpFailed"

    class Start(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val mode = if (parameters["mode"] as? String == "retriever") "retriever" else "consent"
            val sender = (parameters["sender"] as? String)?.takeIf { it.isNotBlank() }
            val length = (parameters["length"] as? Number)?.toInt()?.takeIf { it in 4..10 }

            if (!hasPlayServices(activity)) {
                return mapOf("mode" to "unavailable")
            }

            Handler(Looper.getMainLooper()).post {
                SmsListener.start(activity, mode, sender, length)
            }

            return mapOf("mode" to mode)
        }
    }

    class Stop(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            Handler(Looper.getMainLooper()).post { SmsListener.stop(activity) }
            return emptyMap()
        }
    }

    class AppHash(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> =
            appHash(activity)?.let { mapOf("hash" to it) } ?: emptyMap()
    }

    // ------------------------------------------------------------ Listener

    /** One pending wait at a time; a new start() replaces the old one. */
    internal object SmsListener {
        private var receiver: BroadcastReceiver? = null
        private var mode = "consent"
        private val timeout = Handler(Looper.getMainLooper())

        fun start(activity: FragmentActivity, mode: String, sender: String?, length: Int?) {
            stop(activity)
            this.mode = mode
            SmsListenerLength.value = length

            val client = SmsRetriever.getClient(activity)
            val task = if (mode == "retriever") client.startSmsRetriever() else client.startSmsUserConsent(sender)

            task.addOnFailureListener { e ->
                Log.e(TAG, "could not start $mode: ${e.message}", e)
                stop(activity)
                failed(activity, "unavailable", e.message)
            }

            val listener = object : BroadcastReceiver() {
                override fun onReceive(context: Context, intent: Intent) {
                    if (intent.action != SmsRetriever.SMS_RETRIEVED_ACTION) return
                    val extras = intent.extras ?: return
                    @Suppress("DEPRECATION")
                    val status = extras.get(SmsRetriever.EXTRA_STATUS) as? Status ?: return

                    stop(activity)
                    when (status.statusCode) {
                        CommonStatusCodes.SUCCESS -> if (this@SmsListener.mode == "retriever") {
                            deliver(activity, extras.getString(SmsRetriever.EXTRA_SMS_MESSAGE), "retriever")
                        } else {
                            val consent = consentIntent(extras)
                            if (consent == null) {
                                failed(activity, "error", "The consent prompt was missing.")
                            } else {
                                OtpCoordinator.install(activity).askConsent(consent)
                            }
                        }
                        CommonStatusCodes.TIMEOUT -> failed(activity, "timeout", "No SMS arrived within five minutes.")
                        else -> failed(activity, "error", status.statusMessage)
                    }
                }
            }

            ContextCompat.registerReceiver(
                activity,
                listener,
                IntentFilter(SmsRetriever.SMS_RETRIEVED_ACTION),
                SmsRetriever.SEND_PERMISSION,
                null,
                ContextCompat.RECEIVER_EXPORTED,
            )
            receiver = listener

            // Play services times out after five minutes; this guard only
            // covers the rare case where its TIMEOUT broadcast never comes.
            timeout.postDelayed({
                if (receiver === listener) {
                    stop(activity)
                    failed(activity, "timeout", "No SMS arrived within five minutes.")
                }
            }, 5 * 60 * 1000L + 15_000L)
        }

        fun stop(context: Context) {
            timeout.removeCallbacksAndMessages(null)
            receiver?.let {
                try {
                    context.unregisterReceiver(it)
                } catch (_: IllegalArgumentException) {
                    // Already unregistered.
                }
            }
            receiver = null
        }

        @Suppress("DEPRECATION")
        private fun consentIntent(extras: Bundle): Intent? =
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                extras.getParcelable(SmsRetriever.EXTRA_CONSENT_INTENT, Intent::class.java)
            } else {
                extras.getParcelable(SmsRetriever.EXTRA_CONSENT_INTENT)
            }
    }

    // ------------------------------------------------------------ Results

    internal fun deliver(activity: FragmentActivity, message: String?, source: String, length: Int? = SmsListenerLength.value) {
        if (message.isNullOrBlank()) {
            failed(activity, "no_code", null)
            return
        }
        val code = extractCode(message, length)
        if (code == null) {
            failed(activity, "no_code", message)
            return
        }
        dispatch(activity, EVENT_RECEIVED, JSONObject().apply {
            put("code", code)
            put("message", message)
            put("source", source)
        })
    }

    internal fun failed(activity: FragmentActivity, reason: String, message: String?) {
        dispatch(activity, EVENT_FAILED, JSONObject().apply {
            put("reason", reason)
            message?.takeIf { it.isNotBlank() }?.let { put("message", it) }
        })
    }

    /** Same rule as OtpAutofill::extractCode() in PHP: the first 4–8 (or exactly N) digit run. */
    internal fun extractCode(message: String, length: Int?): String? {
        val digits = length?.toString() ?: "4,8"
        return Regex("(?<![0-9])([0-9]{$digits})(?![0-9])").find(message)?.groupValues?.get(1)
    }

    private fun dispatch(activity: FragmentActivity, event: String, payload: JSONObject) {
        NativeActionCoordinator.dispatchEvent(activity, event, payload.toString())
    }

    private fun hasPlayServices(context: Context): Boolean = try {
        context.packageManager.getPackageInfo("com.google.android.gms", 0)
        true
    } catch (_: PackageManager.NameNotFoundException) {
        false
    }

    // ------------------------------------------------------------ App hash

    /**
     * The SMS Retriever hash: base64(SHA-256("<package> <signing cert hex>"))
     * truncated to 9 bytes → 11 characters. Google's documented algorithm.
     */
    internal fun appHash(context: Context): String? = try {
        val packageName = context.packageName
        val signatures = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
            val info = context.packageManager.getPackageInfo(packageName, PackageManager.GET_SIGNING_CERTIFICATES)
            val signing = info.signingInfo ?: return null
            if (signing.hasMultipleSigners()) signing.apkContentsSigners else signing.signingCertificateHistory
        } else {
            @Suppress("DEPRECATION")
            context.packageManager.getPackageInfo(packageName, PackageManager.GET_SIGNATURES).signatures
        } ?: return null

        signatures.firstOrNull()?.let { signature ->
            val input = "$packageName ${signature.toCharsString()}"
            val digest = MessageDigest.getInstance("SHA-256").digest(input.toByteArray(StandardCharsets.UTF_8))
            Base64.encodeToString(digest.copyOfRange(0, 9), Base64.NO_PADDING or Base64.NO_WRAP).substring(0, 11)
        }
    } catch (e: Exception) {
        Log.e(TAG, "app hash failed: ${e.message}", e)
        null
    }
}

/** The code length requested by the pending start(), read when the SMS arrives. */
internal object SmsListenerLength {
    var value: Int? = null
}

/**
 * Headless fragment that owns the ActivityResultLauncher for the consent
 * sheet, so the user's Allow / Deny comes back here. Installed once per
 * activity.
 */
class OtpCoordinator : Fragment() {

    companion object {
        private const val FRAGMENT_TAG = "VipertecproOtpAutofill"

        fun install(activity: FragmentActivity): OtpCoordinator {
            val fm = activity.supportFragmentManager
            (fm.findFragmentByTag(FRAGMENT_TAG) as? OtpCoordinator)?.let { return it }
            val coordinator = OtpCoordinator()
            fm.beginTransaction().add(coordinator, FRAGMENT_TAG).commitNow()
            return coordinator
        }
    }

    private lateinit var launcher: ActivityResultLauncher<Intent>

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        launcher = registerForActivityResult(ActivityResultContracts.StartActivityForResult()) { result ->
            val host = activity ?: return@registerForActivityResult
            if (result.resultCode == Activity.RESULT_OK) {
                OtpAutofillFunctions.deliver(host, result.data?.getStringExtra(SmsRetriever.EXTRA_SMS_MESSAGE), "consent")
            } else {
                OtpAutofillFunctions.failed(host, "denied", "The user did not allow reading the message.")
            }
        }
    }

    fun askConsent(intent: Intent) {
        try {
            launcher.launch(intent)
        } catch (e: Exception) {
            activity?.let { OtpAutofillFunctions.failed(it, "error", e.message) }
        }
    }
}
