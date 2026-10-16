<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    isConfirmed: {
        type: Boolean,
        default: false,
    },
});

const form = useForm({});
const successMessage = ref('');

const resetMfa = () => {
    form.post(route('profile.mfa.reset'), {
        preserveScroll: true,
        onSuccess: () => {
            successMessage.value = 'MFA setup reset! You will see the QR code setup on your next login.';
            setTimeout(() => (successMessage.value = ''), 4000);
        },
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                Two-Factor Authentication (MFA)
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Manage your Google Authenticator or Microsoft Authenticator settings.
            </p>
        </header>

        <div class="mt-4 flex items-center gap-3">
            <span class="inline-flex items-center rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                <svg class="mr-1 h-3.5 w-3.5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                </svg>
                Authenticator Active
            </span>
        </div>

        <div class="mt-4">
            <p class="text-xs text-gray-500 mb-3">
                Need to re-scan your QR code on a new phone or reset your authenticator app? Clicking below will reset your confirmation status so the QR code setup appears again.
            </p>

            <form @submit.prevent="resetMfa">
                <PrimaryButton :disabled="form.processing" class="text-xs">
                    Reset & Re-scan QR Code
                </PrimaryButton>
            </form>

            <p v-if="successMessage" class="mt-2 text-xs font-semibold text-green-600">
                {{ successMessage }}
            </p>
        </div>
    </section>
</template>
