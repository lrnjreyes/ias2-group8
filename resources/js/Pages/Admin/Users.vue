<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({ users: Array });

const createForm = useForm({
    name: '', email: '', password: '', password_confirmation: '', role: 'employee',
    employee_number: '', department: '', position: '', hire_date: '', leave_balance: 10,
});

const createUser = () => createForm.post(route('admin.users.store'), {
    preserveScroll: true,
    onSuccess: () => createForm.reset(),
});

const saveUser = (user) => {
    const record = user.employee_record ?? {};
    useForm({
        name: user.name, email: user.email, role: user.role,
        employee_number: record.employee_number, department: record.department,
        position: record.position, hire_date: record.hire_date?.slice(0, 10),
        leave_balance: record.leave_balance, password: '', password_confirmation: '',
    }).patch(route('admin.users.update', user.id), { preserveScroll: true });
};

const deleteUser = (user) => {
    if (window.confirm(`Delete ${user.name}? This cannot be undone.`)) {
        useForm({}).delete(route('admin.users.destroy', user.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="User administration" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800">User administration</h2></template>
        <div class="py-10"><div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <form @submit.prevent="createUser" class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold">Add user</h3>
                <p class="mt-1 text-sm text-gray-600">Only administrators can create accounts. The user can later use Forgot Password with this email address.</p>
                <div class="mt-5 grid gap-4 md:grid-cols-3">
                    <label class="text-sm">Name<input v-model="createForm.name" required class="mt-1 w-full rounded-md border-gray-300" /></label>
                    <label class="text-sm">Email<input v-model="createForm.email" type="email" required class="mt-1 w-full rounded-md border-gray-300" /></label>
                    <label class="text-sm">Role<select v-model="createForm.role" class="mt-1 w-full rounded-md border-gray-300"><option value="employee">Employee</option><option value="admin">Administrator</option></select></label>
                    <label class="text-sm">Temporary password<input v-model="createForm.password" type="password" required class="mt-1 w-full rounded-md border-gray-300" /></label>
                    <label class="text-sm">Confirm password<input v-model="createForm.password_confirmation" type="password" required class="mt-1 w-full rounded-md border-gray-300" /></label>
                    <label class="text-sm">Employee number<input v-model="createForm.employee_number" required class="mt-1 w-full rounded-md border-gray-300" /></label>
                    <label class="text-sm">Department<input v-model="createForm.department" required class="mt-1 w-full rounded-md border-gray-300" /></label>
                    <label class="text-sm">Position<input v-model="createForm.position" required class="mt-1 w-full rounded-md border-gray-300" /></label>
                    <label class="text-sm">Hire date<input v-model="createForm.hire_date" type="date" required class="mt-1 w-full rounded-md border-gray-300" /></label>
                    <label class="text-sm">Leave balance<input v-model="createForm.leave_balance" type="number" min="0" max="365" required class="mt-1 w-full rounded-md border-gray-300" /></label>
                </div>
                <InputError class="mt-3" :message="Object.values(createForm.errors)[0]" />
                <button :disabled="createForm.processing" class="mt-5 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Add user</button>
            </form>

            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="p-3">Account</th><th class="p-3">Role</th><th class="p-3">Employee no.</th><th class="p-3">Department</th><th class="p-3">Position</th><th class="p-3">Hire date</th><th class="p-3">Leave</th><th class="p-3">Actions</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="user in users" :key="user.id">
                            <td class="p-3"><input v-model="user.name" class="w-44 rounded-md border-gray-300" /><input v-model="user.email" type="email" class="mt-2 w-52 rounded-md border-gray-300" /></td>
                            <td class="p-3"><select v-model="user.role" class="rounded-md border-gray-300"><option value="employee">Employee</option><option value="admin">Admin</option></select></td>
                            <td class="p-3"><input v-model="user.employee_record.employee_number" class="w-28 rounded-md border-gray-300" /></td>
                            <td class="p-3"><input v-model="user.employee_record.department" class="w-36 rounded-md border-gray-300" /></td>
                            <td class="p-3"><input v-model="user.employee_record.position" class="w-36 rounded-md border-gray-300" /></td>
                            <td class="p-3"><input v-model="user.employee_record.hire_date" type="date" class="rounded-md border-gray-300" /></td>
                            <td class="p-3"><input v-model="user.employee_record.leave_balance" type="number" min="0" max="365" class="w-20 rounded-md border-gray-300" /></td>
                            <td class="p-3"><div class="flex gap-2"><button @click="saveUser(user)" class="rounded bg-indigo-600 px-3 py-2 text-white">Save</button><button @click="deleteUser(user)" class="rounded bg-red-600 px-3 py-2 text-white">Delete</button></div></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div></div>
    </AuthenticatedLayout>
</template>
