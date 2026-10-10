<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({ logs: Array });
</script>

<template>
    <Head title="Security audit log" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800">Security audit log</h2></template>
        <div class="py-10"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-5 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Logs record security-relevant actions without storing passwords or session tokens.</div>
            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="p-4">Time</th><th class="p-4">Event</th><th class="p-4">Actor</th><th class="p-4">IP address</th><th class="p-4">Subject</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        <tr v-for="log in logs" :key="log.id">
                            <td class="whitespace-nowrap p-4">{{ new Date(log.created_at).toLocaleString() }}</td>
                            <td class="p-4 font-mono text-xs">{{ log.event }}</td>
                            <td class="p-4">{{ log.actor?.name ?? 'Unauthenticated user' }}</td>
                            <td class="p-4 font-mono text-xs">{{ log.ip_address ?? 'n.a.' }}</td>
                            <td class="p-4">{{ log.subject_type ? `${log.subject_type.split('\\').pop()} #${log.subject_id}` : 'n.a.' }}</td>
                        </tr>
                        <tr v-if="logs.length === 0"><td colspan="5" class="p-8 text-center text-gray-500">No audit events recorded yet.</td></tr>
                    </tbody>
                </table>
            </div>
        </div></div>
    </AuthenticatedLayout>
</template>
