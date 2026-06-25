<template>
    <div
        v-if="show"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4"
    >
        <ToastAlertWarning :show="showToast" :message="toastMessage" />

        <form @submit.prevent="submit" class="w-full max-w-2xl flex flex-col">
            <div
                class="bg-[var(--color-bg-secondary)] text-[var(--color-text-primary)] rounded-2xl border border-[var(--color-border)] flex flex-col h-full max-h-[90vh]"
            >
                <div class="px-4 sm:px-8 py-4 sm:py-6 flex-shrink-0">
                    <h2 class="text-xl sm:text-2xl font-bold text-center">
                        {{ isEdit ? "EDIT ANNOUNCEMENT" : "ADD ANNOUNCEMENT" }}
                    </h2>
                    <div
                        class="mt-2 h-0.5 bg-gradient-to-r from-transparent via-[var(--color-border)] to-transparent"
                    ></div>
                </div>

                <div class="flex-1 overflow-y-auto p-4 sm:px-8">
                    <div class="grid grid-cols-1 gap-4">
                        <TextInput
                            label="Title"
                            v-model="form.title"
                            type="text"
                            :message="form.errors.title"
                        />
                        <TextInput
                            label="Message"
                            v-model="form.message"
                            type="textarea"
                            :rows="6"
                            :message="form.errors.message"
                        />

                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="form.applies_to_all"
                                class="form-checkbox h-5 w-5 text-[var(--color-primary)] rounded border-[var(--color-border)] bg-[var(--color-bg-primary)] focus:ring-[var(--color-primary)]"
                            />
                            <span class="text-sm font-medium text-[var(--color-text-primary)]">
                                Show to all tenants
                            </span>
                        </label>

                        <MultiSelectDropdown
                            v-if="!form.applies_to_all"
                            label="Select Tenants"
                            v-model="form.app_setting_ids"
                            :options="tenantOptions"
                            placeholder="Select tenants"
                            :message="form.errors.app_setting_ids"
                        />

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="form.is_active"
                                    class="form-checkbox h-5 w-5 text-[var(--color-primary)] rounded border-[var(--color-border)] bg-[var(--color-bg-primary)] focus:ring-[var(--color-primary)]"
                                />
                                <span
                                    class="text-sm font-medium text-[var(--color-text-primary)]"
                                    >Active</span
                                >
                            </label>

                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="form.show_banner"
                                    class="form-checkbox h-5 w-5 text-[var(--color-primary)] rounded border-[var(--color-border)] bg-[var(--color-bg-primary)] focus:ring-[var(--color-primary)]"
                                />
                                <span
                                    class="text-sm font-medium text-[var(--color-text-primary)]"
                                    >Show Banner</span
                                >
                            </label>

                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="form.show_modal"
                                    class="form-checkbox h-5 w-5 text-[var(--color-primary)] rounded border-[var(--color-border)] bg-[var(--color-bg-primary)] focus:ring-[var(--color-primary)]"
                                />
                                <span
                                    class="text-sm font-medium text-[var(--color-text-primary)]"
                                    >Show Modal</span
                                >
                            </label>
                        </div>

                        <label
                            v-if="form.show_banner"
                            class="flex items-center space-x-2 cursor-pointer"
                        >
                            <input
                                type="checkbox"
                                v-model="form.is_dismissible"
                                class="form-checkbox h-5 w-5 text-[var(--color-primary)] rounded border-[var(--color-border)] bg-[var(--color-bg-primary)] focus:ring-[var(--color-primary)]"
                            />
                            <span class="text-sm font-medium text-[var(--color-text-primary)]">
                                Banner is dismissible
                            </span>
                        </label>
                    </div>
                </div>

                <div
                    class="px-4 sm:px-8 py-4 flex justify-end gap-2 flex-shrink-0 border-t border-[var(--color-border)]"
                >
                    <button
                        type="button"
                        @click="closeModal"
                        class="closeButton group"
                    >
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
                                <svg-icon
                                    type="mdi"
                                    :path="mdiNavigationVariantOutline"
                                    class="w-5 h-5"
                                />
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
import { computed, ref, watch } from "vue";
import { route } from "../../../../vendor/tightenco/ziggy/src/js";
import TextInput from "../../Pages/Components/TextInput.vue";
import MultiSelectDropdown from "../../Pages/Components/MultiSelectDropdown.vue";
import { useForm, usePage } from "@inertiajs/vue3";
import ToastAlertWarning from "../../Pages/Components/ToastAlertWarning.vue";
import SvgIcon from "@jamescoyle/vue-icon";
import { mdiClose, mdiNavigationVariantOutline } from "@mdi/js";

const props = defineProps({
    show: Boolean,
    announcement: Object,
    tenants: Array,
});

const page = usePage();
const isEdit = ref(false);

const form = useForm({
    title: "",
    message: "",
    applies_to_all: true,
    app_setting_ids: [],
    is_active: true,
    show_banner: true,
    show_modal: true,
    is_dismissible: false,
});

const emit = defineEmits(["close", "closeSuccess"]);

const closeModal = () => {
    emit("close");
};

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

const submit = () => {
    const url = isEdit.value
        ? route("announcements.update", {
              tenant: page.props.tenant,
              announcement: props.announcement.id,
          })
        : route("announcements.store", { tenant: page.props.tenant });

    const method = isEdit.value ? "put" : "post";

    form.submit(method, url, {
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
                showWarningToast("Please fill up necessary fields");
            }
        },
    });
};

watch(
    () => props.announcement,
    (newVal) => {
        if (newVal) {
            isEdit.value = true;
            form.title = newVal.title ?? "";
            form.message = newVal.message ?? "";
            form.applies_to_all = newVal.applies_to_all ?? true;
            form.app_setting_ids = (newVal.app_settings ?? []).map((t) => t.id);
            form.is_active = !!newVal.is_active;
            form.show_banner = !!newVal.show_banner;
            form.show_modal = !!newVal.show_modal;
            form.is_dismissible = !!newVal.is_dismissible;
        } else {
            isEdit.value = false;
            form.reset();
            form.applies_to_all = true;
            form.app_setting_ids = [];
            form.is_active = true;
            form.show_banner = true;
            form.show_modal = true;
            form.is_dismissible = false;
        }
    },
    { immediate: true }
);

const tenantOptions = computed(() =>
    (props.tenants ?? []).map((t) => ({
        value: t.id,
        label: t.app_name,
    }))
);

watch(
    () => form.applies_to_all,
    (val) => {
        if (val) {
            form.app_setting_ids = [];
        }
    }
);

watch(
    () => form.show_banner,
    (val) => {
        if (!val) {
            form.is_dismissible = false;
        }
    }
);
</script>
