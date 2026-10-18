<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { useAuthenticationLockout } from '@/Composables/useAuthenticationLockout';
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed, ref, onMounted, nextTick } from 'vue';
import QRCode from 'qrcode';

const props = defineProps({
    lockout: Object,
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

const { isLocked, message: lockoutMessage } = useAuthenticationLockout(
    () => props.lockout,
    'Too many invalid verification attempts.',
);
const codeError = computed(() => props.lockout ? lockoutMessage.value : form.errors.code);

const digits = ref(['', '', '', '', '', '']);
const digitInputs = ref([]);
const qrCodeUrl = ref('');
const copied = ref(false);
const copyError = ref('');
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
        if (!setupMode.value) digitInputs.value[0]?.focus();
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
        } else {
            digitInputs.value[pasted.length]?.focus();
        }
    }
};

const syncCode = () => {
    form.code = digits.value.join('');
};

const submit = () => {
    if (form.processing || isLocked.value) return;

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
    copyError.value = '';
    try {
        await navigator.clipboard.writeText(props.secret);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        copied.value = false;
        copyError.value = 'Select the setup key and copy it manually.';
    }
};

const cancel = () => {
    router.post(route('mfa.cancel'));
};
</script>

<template>
    <GuestLayout :variant="setupMode ? 'setup' : 'default'">
        <Head :title="setupMode ? 'Set Up Authenticator' : 'Two-Factor Authentication'" />

        <!-- Status Notification -->
        <div v-if="status" class="mb-4 rounded-md bg-green-50 p-2.5 text-center text-xs font-medium text-green-700 border border-green-200">
            {{ status }}
        </div>

        <div v-if="setupMode">
            <header>
                <h1 class="text-2xl font-semibold tracking-tight text-gray-900">Set up your authenticator</h1>
                <p class="mt-1 break-all text-sm text-gray-600">{{ email }}</p>
                <p class="mt-5 text-sm leading-6 text-gray-700">
                    Open Microsoft Authenticator, Google Authenticator, or another authenticator app. Add an account, then scan this QR code.
                </p>
            </header>

            <div class="my-5 flex justify-center">
                <img
                    v-if="qrCodeUrl"
                    :src="qrCodeUrl"
                    alt="Scan this QR code with your authenticator app"
                    width="200"
                    height="200"
                    class="h-[200px] w-[200px]"
                />
                <div v-else role="status" class="flex h-[200px] w-[200px] items-center justify-center text-sm text-gray-600">
                    Loading QR code...
                </div>
            </div>

            <div>
                <p class="text-sm text-gray-600">Can't scan? Enter this setup key manually.</p>
                <div class="mt-1 flex flex-wrap items-center justify-between gap-x-3">
                    <code class="select-all break-all font-mono text-base tracking-wide text-gray-900">{{ secret }}</code>
                    <button
                        type="button"
                        @click="copySecret"
                        class="min-h-11 rounded-sm px-2 text-sm font-medium text-[#0067b8] hover:underline focus:outline-none focus:ring-2 focus:ring-[#0067b8] focus:ring-offset-2"
                    >
                        {{ copied ? 'Copied' : 'Copy key' }}
                    </button>
                </div>
                <p role="status" class="sr-only">{{ copied ? 'Setup key copied.' : '' }}</p>
                <p v-if="copyError" role="status" class="mt-1 text-sm text-gray-600">{{ copyError }}</p>
            </div>

            <form @submit.prevent="submit" class="mt-6">
                <p id="setup-code-label" class="text-sm font-semibold text-gray-900">Enter verification code</p>
                <p id="setup-code-help" class="mt-1 text-sm text-gray-600">Enter the 6-digit code from your authenticator app.</p>
                <div role="group" aria-labelledby="setup-code-label" aria-describedby="setup-code-help" class="mt-3 flex gap-2" @paste="handlePaste">
                    <input
                        v-for="(digit, idx) in digits"
                        :key="'setup-' + idx"
                        ref="digitInputs"
                        type="text"
                        inputmode="numeric"
                        maxlength="1"
                        autocomplete="off"
                        :disabled="form.processing || isLocked"
                        :aria-label="'Verification code digit ' + (idx + 1)"
                        :aria-invalid="!!codeError"
                        :aria-describedby="codeError ? 'setup-code-error' : undefined"
                        class="h-11 w-9 rounded-sm border border-gray-400 text-center font-mono text-xl text-gray-900 focus:border-[#0067b8] focus:ring-1 focus:ring-[#0067b8] sm:w-11"
                        v-model="digits[idx]"
                        @input="handleInput(idx, $event)"
                        @keydown="handleKeyDown(idx, $event)"
                    />
                </div>

                <InputError id="setup-code-error" role="alert" class="mt-2" :message="codeError" />

                <div class="mt-7 flex justify-end gap-3">
                    <button
                        type="button"
                        @click="cancel"
                        class="min-h-11 rounded-sm border border-gray-300 bg-gray-100 px-5 text-sm font-medium text-gray-900 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing || isLocked || form.code.length !== 6"
                        class="min-h-11 rounded-sm bg-[#0067b8] px-5 text-sm font-medium text-white hover:bg-[#005a9e] focus:outline-none focus:ring-2 focus:ring-[#0067b8] focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <span>{{ form.processing ? 'Verifying...' : 'Finish setup' }}</span>
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
                        :disabled="form.processing || isLocked"
                        class="h-14 w-12 rounded-md border-2 border-gray-300 text-center font-mono text-2xl font-bold text-gray-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition shadow-inner bg-white"
                        v-model="digits[idx]"
                        @input="handleInput(idx, $event)"
                        @keydown="handleKeyDown(idx, $event)"
                    />
                </div>

                <InputError class="text-center" :message="codeError" />

                <!-- Action Buttons: Clean Checkmark OK and Cross Cancel -->
                <div class="flex items-center justify-center gap-4 pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing || isLocked || form.code.length !== 6"
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
