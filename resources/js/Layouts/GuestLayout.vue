<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    variant: {
        type: String,
        default: 'default',
    },
});
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:justify-center sm:pt-0"
    >
        <div>
            <Link href="/">
                <ApplicationLogo class="h-20 w-20 fill-current text-gray-500" />
            </Link>
        </div>

        <div
            :class="variant === 'setup'
                ? 'mt-6 w-full overflow-hidden rounded border border-gray-200 bg-white px-6 py-7 shadow-sm sm:max-w-lg sm:px-10 sm:py-8'
                : 'mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg'"
        >
            <slot />
        </div>
    </div>
</template>
