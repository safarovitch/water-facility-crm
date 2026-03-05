<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import Input from '@/components/ui/input/Input.vue';
import Label from '@/components/ui/label/Label.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Form, useForm } from '@inertiajs/vue3';
import { BreadcrumbItem } from '@/types';
import { computed } from 'vue';
import { index, store } from '@/routes/permissions/';
import { cn } from '@/lib/utils';

const form = useForm({
    name: '',
    guard_name: ''
});

const isFormValid = computed(() => {
    return form.name.length > 0 && form.guard_name.length > 0
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Permissions',
        href: index().url,
    },
    {
        title: 'New permission',
        href: '#',
    },
];

defineProps<{
    guards: Array<string>
}>()

</script>
<template>

    <Head title="New role" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative overflow-x-auto sm:rounded-lg">
            <!-- Header -->
            <div class="p-4 flex items-center justify-between flex-column flex-wrap md:flex-row space-y-4 md:space-y-0 pb-6 bg-white dark:bg-gray-900">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Create new permission</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Add a new permission to organization</p>
                </div>
            </div>

            <!-- Form -->
            <Form v-bind="store.form()" class="space-y-6" v-slot="{ errors, processing, recentlySuccessful }">
                <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <!-- Name -->
                            <div class="grid gap-2">
                                <Label for="name">Name</Label>
                                <Input id="name" class="mt-1 block w-full" name="name" v-model="form.name" required autocomplete="name" placeholder="Role name" />
                                <InputError class="mt-2" :message="errors.name" />
                            </div>

                            <!-- Guard -->
                            <div class="grid gap-2">
                                <Label for="guard">Choose guard</Label>
                                <select id="guard" name="guard_name" v-model="form.guard_name" required :class="cn(
                                    'mt-1 cursor-pointer file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-auto w-full min-w-0 rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                                    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                                    'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
                                )">
                                    <option v-for="guard in guards" :key="guard" :value="guard">{{ guard }}</option>
                                </select>
                                <InputError class="mt-2" :message="errors.guard_name" />
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-3 pt-4">
                    <Button type="button" @click="$inertia.visit(index().url)" class="cursor-pointer inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="processing || !isFormValid" class="cursor-pointer inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed" :class="{ 'opacity-50 cursor-not-allowed': processing || !isFormValid }">
                        <span v-if="processing" class="mr-2">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>
                        {{ processing ? 'Creating...' : 'Create permission' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>