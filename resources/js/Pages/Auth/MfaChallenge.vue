<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, onMounted, nextTick } from 'vue';
import QRCode from 'qrcode';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    secret: {
        type: String,
        required: true,
    },
    isSetup: {
        type: Boolean,
        default: false,
    },
    otpAuthUri: {
        type: String,
        required: true,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    code: '',
});

const digits = ref(['', '', '', '', '', '']);
const digitInputs = ref([]);
const qrCodeUrl = ref('');
const copied = ref(false);
const setupMode = ref(props.isSetup);

onMounted(async () => {
    try {
        qrCodeUrl.value = await QRCode.toDataURL(props.otpAuthUri, {
            width: 200,
            margin: 1,
            color: {
                dark: '#0f172a',
                light: '#ffffff',
            },
        });
    } catch (err) {
        console.error('Failed to generate QR code', err);
    }

    nextTick(() => {
        digitInputs.value[0]?.focus();
    });
});

const handleInput = (index, event) => {
    const val = event.target.value;
    const char = val.slice(-1);

    if (!/^\d$/.test(char)) {
        digits.value[index] = '';
        syncCode();
        return;
    }

    digits.value[index] = char;
    syncCode();

    if (index < 5) {
        digitInputs.value[index + 1]?.focus();
    }

    if (form.code.length === 6) {
        submit();
    }
};

const handleKeyDown = (index, event) => {
    if (event.key === 'Backspace') {
        if (!digits.value[index] && index > 0) {
            digits.value[index - 1] = '';
            syncCode();
            digitInputs.value[index - 1]?.focus();
        } else {
            digits.value[index] = '';
            syncCode();
        }
    } else if (event.key === 'ArrowLeft' && index > 0) {
        digitInputs.value[index - 1]?.focus();
    } else if (event.key === 'ArrowRight' && index < 5) {
        digitInputs.value[index + 1]?.focus();
    }
};

const handlePaste = (event) => {
    event.preventDefault();
    const pasted = (event.clipboardData || window.clipboardData)
        .getData('text')
        .replace(/\D/g, '')
        .slice(0, 6);

    if (pasted) {
        for (let i = 0; i < 6; i++) {
            digits.value[i] = pasted[i] || '';
        }
        syncCode();

        if (pasted.length === 6) {
            digitInputs.value[5]?.focus();
            submit();
        } else {
            digitInputs.value[pasted.length]?.focus();
        }
    }
};

const syncCode = () => {
    form.code = digits.value.join('');
};

const submit = () => {
    syncCode();
    form.post(route('mfa.verify'), {
        onFinish: () => {
            digits.value = ['', '', '', '', '', ''];
            form.reset('code');
            nextTick(() => digitInputs.value[0]?.focus());
        },
    });
};

const copySecret = async () => {
    try {
        await navigator.clipboard.writeText(props.secret);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch (e) {
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    }
};

const cancel = () => {
    router.post(route('mfa.cancel'));
};
</script>

<template>
    <GuestLayout>
        <Head :title="setupMode ? 'Set Up Authenticator' : 'Two-Factor Authentication'" />

        <!-- Status Notification -->
        <div v-if="status" class="mb-4 rounded-md bg-green-50 p-2.5 text-center text-xs font-medium text-green-700 border border-green-200">
            {{ status }}
        </div>

        <!-- ============================================================== -->
        <!-- VIEW 1: FIRST-TIME SETUP (Google / Modern Setup with QR Code) -->
        <!-- ============================================================== -->
        <div v-if="setupMode">
            <div class="mb-4 text-center">
                <div class="mx-auto mb-2 flex h-11 w-11 items-center justify-center rounded-full bg-blue-50 text-blue-600 ring-4 ring-blue-50/50">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                    </svg>
                </div>
                <h1 class="text-xl font-bold tracking-tight text-gray-900">Set Up Authenticator App</h1>
                <p class="mt-1 text-xs text-gray-500">
                    Scan this QR code using Google Authenticator, Microsoft Authenticator, or any TOTP app on your phone.
                </p>
            </div>

            <!-- QR Code Card -->
            <div class="mb-4 rounded-xl border border-gray-200 bg-gray-50/80 p-3 text-center">
                <div class="flex justify-center">
                    <div class="inline-block rounded-xl border border-gray-200 bg-white p-2 shadow-sm">
                        <img
                            v-if="qrCodeUrl"
                            :src="qrCodeUrl"
                            alt="Authenticator QR Code"
                            class="h-40 w-40 rounded-lg"
                        />
                        <div v-else class="flex h-40 w-40 items-center justify-center text-xs text-gray-400">
                            Loading QR code...
                        </div>
                    </div>
                </div>

                <!-- Manual Entry Key -->
                <div class="mt-3 flex items-center justify-between rounded-lg bg-white p-2 border border-gray-200 text-xs">
                    <div class="text-left">
                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-400">Setup Key</span>
                        <span class="font-mono text-xs font-bold text-gray-800 tracking-wider">{{ secret }}</span>
                    </div>
                    <button
                        type="button"
                        @click="copySecret"
                        class="rounded bg-blue-50 px-2 py-1 text-[11px] font-medium text-blue-700 hover:bg-blue-100 border border-blue-200 transition"
                    >
                        {{ copied ? 'Copied!' : 'Copy Key' }}
                    </button>
                </div>
            </div>

            <div class="mb-3 text-center text-xs text-gray-600 font-medium">
                Enter the 6-digit code shown in your Authenticator app:
            </div>

            <!-- 6 Individual Digit Boxes (Setup confirmation) -->
            <form @submit.prevent="submit" class="space-y-4">
                <div class="flex justify-center gap-2" @paste="handlePaste">
                    <input
                        v-for="(digit, idx) in digits"
                        :key="'setup-' + idx"
                        ref="digitInputs"
                        type="text"
                        inputmode="numeric"
                        maxlength="1"
                        autocomplete="off"
                        class="h-12 w-11 rounded-lg border-2 border-gray-300 text-center font-mono text-2xl font-bold text-gray-800 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 transition shadow-sm"
                        v-model="digits[idx]"
                        @input="handleInput(idx, $event)"
                        @keydown="handleKeyDown(idx, $event)"
                    />
                </div>

                <InputError class="text-center" :message="form.errors.code" />

                <div class="flex items-center justify-between pt-2">
                    <button
                        type="button"
                        @click="cancel"
                        class="text-xs text-gray-500 hover:text-gray-700 underline focus:outline-none"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        :disabled="form.processing || form.code.length !== 6"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-40 transition"
                    >
                        <span>Confirm & Complete Setup</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- ============================================================== -->
        <!-- VIEW 2: SUBSEQUENT LOGINS (GitHub-Style Two-Factor Authentication Modal) -->
        <!-- ============================================================== -->
        <div v-else class="text-center">
            <!-- App Logo / Header -->
            <div class="mb-2">
                <div class="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-800">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <h1 class="text-2xl font-normal text-gray-900 tracking-tight">Two-factor authentication</h1>
                <div class="mx-auto my-3 h-px w-24 bg-gray-200"></div>
                <p class="text-xs text-gray-600 max-w-xs mx-auto leading-relaxed">
                    Open the two-factor authentication app on your device to view your authentication code.
                </p>
            </div>

            <!-- 6 Individual Digit Inputs (GitHub Style) -->
            <form @submit.prevent="submit" class="mt-5 space-y-5">
                <div class="flex justify-center gap-2.5" @paste="handlePaste">
                    <input
                        v-for="(digit, idx) in digits"
                        :key="'verify-' + idx"
                        ref="digitInputs"
                        type="text"
                        inputmode="numeric"
                        maxlength="1"
                        autocomplete="off"
                        class="h-14 w-12 rounded-md border-2 border-gray-300 text-center font-mono text-2xl font-bold text-gray-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition shadow-inner bg-white"
                        v-model="digits[idx]"
                        @input="handleInput(idx, $event)"
                        @keydown="handleKeyDown(idx, $event)"
                    />
                </div>

                <InputError class="text-center" :message="form.errors.code" />

                <!-- Action Buttons: Clean Checkmark OK and Cross Cancel -->
                <div class="flex items-center justify-center gap-4 pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing || form.code.length !== 6"
                        class="inline-flex items-center gap-1.5 rounded-full border border-blue-400 bg-white px-5 py-1.5 text-sm font-medium text-blue-600 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-400 disabled:opacity-40 transition shadow-sm"
                    >
                        <svg class="h-4 w-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Ok</span>
                    </button>

                    <button
                        type="button"
                        @click="cancel"
                        class="inline-flex items-center gap-1.5 rounded-full border border-gray-300 bg-white px-4 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300 transition shadow-sm"
                    >
                        <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        <span>Cancel</span>
                    </button>
                </div>
            </form>

            <!-- Re-scan QR Option -->
            <div class="mt-6 border-t border-gray-100 pt-3">
                <button
                    type="button"
                    @click="setupMode = true"
                    class="text-[11px] text-gray-400 hover:text-blue-600 underline transition"
                >
                    Lost your authenticator app? View setup QR code
                </button>
            </div>
        </div>
    </GuestLayout>
</template>
