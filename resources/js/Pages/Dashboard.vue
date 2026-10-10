<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ record: Object, isAdmin: Boolean });
</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800">Employee Portal</h2></template>
        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-6 md:grid-cols-3">
                    <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg md:col-span-2">
                        <p class="text-sm font-medium uppercase tracking-wide text-indigo-600">Authenticated dashboard</p>
                        <h3 class="mt-2 text-2xl font-semibold text-gray-900">Welcome, {{ $page.props.auth.user.name }}</h3>
                        <p class="mt-2 text-gray-600">You are signed in as <strong>{{ $page.props.auth.user.role }}</strong>. Access to protected pages is checked on the server.</p>
                    </div>
                    <div class="rounded-lg bg-indigo-700 p-6 text-white shadow-sm">
                        <p class="text-sm text-indigo-100">Security status</p>
                        <p class="mt-2 text-lg font-semibold">Protected session</p>
                        <p class="mt-1 text-sm text-indigo-100">15-minute idle lifetime and logout invalidation enabled.</p>
                    </div>
                </div>
                <div v-if="record" class="mt-6 overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="border-b border-gray-200 px-6 py-4"><h3 class="font-semibold text-gray-900">My employee record</h3></div>
                    <dl class="grid gap-6 p-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div><dt class="text-sm text-gray-500">Employee number</dt><dd class="font-medium">{{ record.employee_number }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Department</dt><dd class="font-medium">{{ record.department }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Position</dt><dd class="font-medium">{{ record.position }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Hire date</dt><dd class="font-medium">{{ record.hire_date }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Leave balance</dt><dd class="font-medium">{{ record.leave_balance }} days</dd></div>
                    </dl>
                </div>
                <div v-if="isAdmin" class="mt-6 flex flex-wrap gap-3">
                    <Link :href="route('admin.employees.index')" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">Manage employees</Link>
                    <Link :href="route('admin.audit-logs.index')" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-800">View audit log</Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
