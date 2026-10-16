<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({ logs: Array });

const formatField = (field) => field.replaceAll('_', ' ');

const formatValue = (value) => value === null || value === '' ? 'empty' : String(value);
</script>

<template>
    <Head title="Security audit log" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800">Security audit log</h2></template>
        <div class="py-10"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-5 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Logs record security-relevant actions without storing passwords or session tokens.</div>
            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="p-4">Time</th><th class="p-4">Event</th><th class="p-4">Actor</th><th class="p-4">IP address</th><th class="p-4">Subject</th><th class="p-4">Details</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        <tr v-for="log in logs" :key="log.id">
                            <td class="whitespace-nowrap p-4">{{ new Date(log.created_at).toLocaleString() }}</td>
                            <td class="p-4 font-mono text-xs">{{ log.event }}</td>
                            <td class="p-4">{{ log.actor?.name ?? 'Unauthenticated user' }}</td>
                            <td class="p-4 font-mono text-xs">{{ log.ip_address ?? 'n.a.' }}</td>
                            <td class="p-4">{{ log.subject_type ? `${log.subject_type.split('\\').pop()} #${log.subject_id}` : 'n.a.' }}</td>
                            <td class="min-w-64 p-4 text-xs text-gray-700">
                                <div v-if="log.metadata?.employee_number" class="mb-1 font-semibold">
                                    {{ log.metadata.employee_number }}
                                </div>
                                <ul v-if="log.metadata?.changes" class="space-y-1">
                                    <li v-for="(change, field) in log.metadata.changes" :key="field">
                                        <span class="capitalize">{{ formatField(field) }}</span>:
                                        <span class="text-red-700">{{ formatValue(change.before) }}</span>
                                        →
                                        <span class="text-green-700">{{ formatValue(change.after) }}</span>
                                    </li>
                                </ul>
                                <span v-else-if="log.metadata?.security_note">{{ log.metadata.security_note }}</span>
                                <span v-else>—</span>
                            </td>
                        </tr>
                        <tr v-if="logs.length === 0"><td colspan="6" class="p-8 text-center text-gray-500">No audit events recorded yet.</td></tr>
                    </tbody>
                </table>
            </div>
        </div></div>
    </AuthenticatedLayout>
</template>
