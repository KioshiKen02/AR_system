<template>
    <div v-if="show" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <ToastAlertWarning :show="showToast" :message="toastMessage" />

        <form @submit.prevent="submit" class="w-full max-w-2xl flex flex-col">
            <div
                class="bg-[var(--color-bg-secondary)] text-[var(--color-text-primary)] rounded-2xl border border-[var(--color-border)] flex flex-col h-full max-h-[90vh]">
                <!-- Header -->
                <div class="px-4 sm:px-8 py-4 sm:py-6 flex-shrink-0">
                    <h2 class="text-xl sm:text-2xl font-bold text-center">
                        {{ isEdit ? 'EDIT APP SETTING' : 'ADD NEW APP SETTING' }}
                    </h2>
                    <div class="mt-2 h-0.5 bg-gradient-to-r from-transparent via-[var(--color-border)] to-transparent">
                    </div>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-y-auto p-4 sm:px-8">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <TextInput label="App Name" v-model="form.app_name" type="text" :message="form.errors.app_name" />
                        <TextInput label="Base URL" v-model="form.base_url" type="text" :message="form.errors.base_url" />
                        
                        <div class="sm:col-span-2">
                             <div class="mt-2 mb-4 h-px bg-[var(--color-border)]"></div>
                             <h3 class="font-semibold mb-2">Database Configuration</h3>
                        </div>

                        <TextInput label="DB Driver" v-model="form.db_driver" type="text" :message="form.errors.db_driver" />
                        <TextInput label="DB Host" v-model="form.db_host" type="text" :message="form.errors.db_host" />
                        <TextInput label="DB Port" v-model="form.db_port" type="text" :message="form.errors.db_port" />
                        <TextInput label="DB Database" v-model="form.db_database" type="text" :message="form.errors.db_database" />
                        <TextInput label="DB Username" v-model="form.db_username" type="text" :message="form.errors.db_username" />
                        <TextInput label="DB Password" v-model="form.db_password" type="password" :message="form.errors.db_password" placeholder="Leave blank to keep unchanged" />

                        <div class="sm:col-span-2">
                            <TextInput label="Description" v-model="form.description" type="text" :message="form.errors.description" />
                        </div>
                        
                        <div class="sm:col-span-2">
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" v-model="form.is_active" class="form-checkbox h-5 w-5 text-[var(--color-primary)] rounded border-[var(--color-border)] bg-[var(--color-bg-primary)] focus:ring-[var(--color-primary)]">
                                <span class="text-sm font-medium text-[var(--color-text-primary)]">Is Active</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-4 sm:px-8 py-4 flex justify-end gap-2 flex-shrink-0 border-t border-[var(--color-border)]">
                    <button type="button" @click="closeModal" class="closeButton group">
                        <div class="flex justify-center items-center gap-2">
                            <span class="transition-transform duration-300 group-hover:rotate-180">
                                <svg-icon type="mdi" :path="mdiClose" class="w-5 h-5" />
                            </span>
                            Close
                        </div>
                    </button>
                    <button type="submit" :disabled="form.processing" class="submitButton group">
                        <div class="flex justify-center items-center gap-2">
                            <span class="transition-transform duration-300 group-hover:rotate-405">
                                <svg-icon type="mdi" :path="mdiNavigationVariantOutline" class="w-5 h-5" />
                            </span>
                            <span v-if="form.processing">Submitting...</span>
                            <span v-else>Submit</span>
                        </div>
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>

<script setup>
import { ref, watch } from "vue";
import { route } from "../../../../vendor/tightenco/ziggy/src/js";
import TextInput from "../../Pages/Components/TextInput.vue";
import { useForm, usePage } from "@inertiajs/vue3";
import ToastAlertWarning from "../../Pages/Components/ToastAlertWarning.vue";
import SvgIcon from "@jamescoyle/vue-icon";
import { mdiClose, mdiNavigationVariantOutline } from "@mdi/js";

const props = defineProps({
    show: Boolean,
    appSetting: Object, // If provided, we are in edit mode
});

const page = usePage();

const isEdit = ref(false);

const form = useForm({
    app_name: null,
    base_url: null,
    db_driver: 'mysql',
    db_host: '127.0.0.1',
    db_port: '3306',
    db_database: null,
    db_username: 'root',
    db_password: null,
    description: null,
    is_active: true,
});

const emit = defineEmits(["close", "closeSuccess"]);

const closeModal = () => {
    emit("close");
};

//////////////////////////////////////// SHOW TOAST /////////////////////////////////////////////////////////////////////////////////////////
const showToast = ref(false);
const toastMessage = ref("");
let toastTimeout = null;

const showWarningToast = (message) => {
    toastMessage.value = message;
    showToast.value = false;
    if (toastTimeout) clearTimeout(toastTimeout);

    setTimeout(() => {
        showToast.value = true;
    }, 0);

    toastTimeout = setTimeout(() => {
        showToast.value = false;
        toastTimeout = null;
    }, 3000);
};
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

const submit = () => {
    const url = isEdit.value 
        ? route("app-settings.update", { tenant: page.props.tenant, appSetting: props.appSetting.id })
        : route("app-settings.store", { tenant: page.props.tenant });
        
    const method = isEdit.value ? 'put' : 'post';

    // If edit mode and password is empty, remove it from payload so we don't overwrite with null/empty
    // But useForm helper manages data directly. We might need to handle this.
    // However, the backend validation says 'nullable', and if we send null, it might update to null.
    // The controller logic I wrote: $appSetting->update($validated). 
    // If I want to avoid clearing password when empty, I should handle it in controller or here.
    // Let's rely on backend logic or just send what we have.
    // Actually, for security, usually we don't send the password field if we don't want to change it.
    
    // Let's clear password if it is null/empty string to avoid sending it?
    // useForm transform is useful here.
    
    form.transform((data) => {
        if (isEdit.value && !data.db_password) {
            const { db_password, ...rest } = data;
            return rest;
        }
        return data;
    }).submit(method, url, {
        onSuccess: () => {
            form.reset();
            emit("closeSuccess");
        },
        onError: (error) => {
            showToast.value = false;
            if (Object.keys(error).length === 1) {
                const firstError = Object.values(error)[0];
                showWarningToast(firstError);
            } else if (Object.keys(error).length !== 1) {
                showWarningToast("Please Fill Up Necessary Fields");
            }
        },
    });
};

watch(
    () => props.appSetting,
    (newVal) => {
        if (newVal) {
            isEdit.value = true;
            form.app_name = newVal.app_name;
            form.base_url = newVal.base_url;
            form.db_driver = newVal.db_driver;
            form.db_host = newVal.db_host;
            form.db_port = newVal.db_port;
            form.db_database = newVal.db_database;
            form.db_username = newVal.db_username;
            form.db_password = null; // Don't show existing password
            form.description = newVal.description;
            form.is_active = !!newVal.is_active;
        } else {
            isEdit.value = false;
            form.reset();
            form.is_active = true;
            form.db_driver = 'mysql';
            form.db_host = '127.0.0.1';
            form.db_port = '3306';
            form.db_username = 'root';
        }
    },
    { immediate: true }
);
</script>
