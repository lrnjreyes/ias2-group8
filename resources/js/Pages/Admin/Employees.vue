<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({ employees: Array });

const save = (employee) => {
    useForm({
        department: employee.department,
        position: employee.position,
        leave_balance: employee.leave_balance,
    }).patch(route('admin.employees.update', employee.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Employee administration" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800">Employee administration</h2></template>
        <div class="py-10"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-5 rounded-md border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-900">Administrator-only area. Saving a record creates an audit-log entry.</div>
            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="p-4">Employee</th><th class="p-4">Department</th><th class="p-4">Position</th><th class="p-4">Leave</th><th class="p-4"></th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="employee in employees" :key="employee.id">
                            <td class="p-4"><div class="font-medium">{{ employee.user.name }}</div><div class="text-sm text-gray-500">{{ employee.employee_number }}</div></td>
                            <td class="p-4"><input v-model="employee.department" class="rounded-md border-gray-300 text-sm" /></td>
                            <td class="p-4"><input v-model="employee.position" class="rounded-md border-gray-300 text-sm" /></td>
                            <td class="p-4"><input v-model="employee.leave_balance" type="number" min="0" max="365" class="w-20 rounded-md border-gray-300 text-sm" /></td>
                            <td class="p-4"><button @click="save(employee)" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white">Save</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div></div>
    </AuthenticatedLayout>
</template>
