<template>
    <div>
        <ToastAlertWarning :show="showToast" :message="toastMessage" />
        <ToastAlert :show="showSToast" :message="toastSMessage" />
        <!-- Export Type Selection Modal -->
        <div v-if="showExportTypeModal"
            class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-[9999]">
            <div
                class="bg-[var(--color-bg-secondary)] p-6 rounded-2xl border border-[var(--color-border)] max-w-sm w-full text-center shadow-2xl">
                <h2 class="text-lg font-bold mb-6 text-[var(--color-text-primary)]">
                    Select Export Format
                </h2>
                
                <div class="flex flex-col gap-3">
                    <button @click="handleExportChoice('txt')"
                        class="w-full py-3 px-4 rounded-xl border border-[var(--color-border)] hover:bg-[var(--color-bg-primary)] hover:border-[var(--color-primary)] transition-all flex items-center justify-center gap-3 group">
                        <div class="p-2 rounded-lg bg-gray-100 group-hover:bg-[var(--color-primary)]/10 text-gray-600 group-hover:text-[var(--color-primary)] transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <span class="font-medium text-[var(--color-text-primary)]">Text File (.txt)</span>
                    </button>

                    <button @click="handleExportChoice('csv')"
                        class="w-full py-3 px-4 rounded-xl border border-[var(--color-border)] hover:bg-[var(--color-bg-primary)] hover:border-[var(--color-primary)] transition-all flex items-center justify-center gap-3 group">
                        <div class="p-2 rounded-lg bg-green-50 group-hover:bg-green-500/10 text-green-600 group-hover:text-green-600 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                        </div>
                        <span class="font-medium text-[var(--color-text-primary)]">CSV File (.csv)</span>
                    </button>
                </div>

                <button @click="showExportTypeModal = false"
                    class="mt-6 text-sm text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] underline">
                    Cancel
                </button>
            </div>
        </div>
        <div class="min-h-[82vh] p-6 rounded-xl mt-6 flex items-center justify-center w-full">
            <div
                class="p-5 rounded-lg bg-[var(--color-bg-secondary)]/20 backdrop-blur-sm border border-[var(--color-border)]/30 hover:border-[var(--color-primary)]/50 shadow-[0_4px_30px_var(--color-shadow)]/20 transition-all w-full">
                <h3
                    class="font-semibold text-lg pb-3 mb-3 border-b border-[var(--color-border)]/30 flex items-center gap-2">
                    <svg-icon type="mdi" :path="mdiInvoiceTextSendOutline"
                        class="w-8 h-8 text-[var(--color-primary)]" />
                    Export To GL (Nav Feedmill)
                </h3>
                <div
                    v-if="isGenerating || generationStatus"
                    class="mb-4 p-3 rounded-lg bg-[var(--color-bg-secondary)]/40 border border-[var(--color-border)]/40 flex items-center gap-3 transition-all">
                    <div
                        class="w-5 h-5 border-2 border-[var(--color-primary)] border-t-transparent rounded-full animate-spin" />
                    <div class="flex flex-col">
                        <span class="text-sm font-medium text-[var(--color-text-primary)]">
                            {{ generationStatus || "Preparing to generate report..." }}
                        </span>
                        <div v-if="generationProgress !== null" class="mt-1 w-full h-1.5 rounded-full bg-[var(--color-bg-secondary)] overflow-hidden">
                            <div
                                class="h-full bg-[var(--color-primary)] transition-[width] duration-300"
                                :style="{ width: `${generationProgress}%` }" />
                        </div>
                    </div>
                    <span
                        v-if="generationProgress !== null"
                        class="ml-auto text-xs font-semibold text-[var(--color-text-secondary)] min-w-[3rem] text-right">
                        {{ generationProgress }}%
                    </span>
                </div>
                <form @submit.prevent="submit">
                    <div class="p-5 rounded-lg transition-all">
                        <div class="flex flex-col gap-10">
                            <div>
                                <label class="block text-md font-bold">Please Select Export Type Below</label>
                                <div class="w-full flex gap-4 mt-4">
                                    <!-- Other Income Option -->
                                    <label class="w-full inline-flex items-center cursor-pointer group">
                                        <input type="radio" v-model="form.export_type" value="Other Income"
                                            class="hidden peer" />
                                        <div class="w-full relative flex items-center justify-center p-2">
                                            <!-- Hover circle -->
                                            <div class="absolute -inset-1 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-200 bg-[var(--color-border)]/40"
                                                :class="{
                                                    'opacity-100':
                                                        form.export_type ===
                                                        'Other Income',
                                                }"></div>
                                            <!-- Radio button -->
                                            <div class="relative w-5 h-5 mr-2 rounded-full border-2 border-[var(--color-bg-avatar)] transition-colors z-10 group-hover:border-[var(--color-border)]"
                                                :class="{
                                                    'border-[var(--color-border)]':
                                                        form.export_type ===
                                                        'Other Income',
                                                }">
                                                <div class="absolute inset-0 m-auto w-2.5 h-2.5 rounded-full bg-[var(--color-border)] transition-opacity"
                                                    :class="{
                                                        'opacity-100':
                                                            form.export_type ===
                                                            'Other Income',
                                                        'opacity-0':
                                                            form.export_type !==
                                                            'Other Income',
                                                    }"></div>
                                            </div>
                                            <span class="text-sm font-medium z-10">Other Income/Charge
                                                Invoice</span>
                                        </div>
                                    </label>

                                    <!-- Adjustment Option -->
                                    <label class="w-full inline-flex items-center cursor-pointer group">
                                        <input type="radio" v-model="form.export_type" value="Adjustment"
                                            class="hidden peer" />
                                        <div class="w-full relative flex items-center justify-center p-2">
                                            <!-- Hover circle -->
                                            <div class="absolute -inset-1 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-200 bg-[var(--color-border)]/40"
                                                :class="{
                                                    'opacity-100':
                                                        form.export_type ===
                                                        'Adjustment',
                                                }"></div>
                                            <!-- Radio button -->
                                            <div class="relative w-5 h-5 mr-2 rounded-full border-2 border-[var(--color-bg-avatar)] transition-colors z-10 group-hover:border-[var(--color-border)]"
                                                :class="{
                                                    'border-[var(--color-border)]':
                                                        form.export_type ===
                                                        'Adjustment',
                                                }">
                                                <div class="absolute inset-0 m-auto w-2.5 h-2.5 rounded-full bg-[var(--color-border)] transition-opacity"
                                                    :class="{
                                                        'opacity-100':
                                                            form.export_type ===
                                                            'Adjustment',
                                                        'opacity-0':
                                                            form.export_type !==
                                                            'Adjustment',
                                                    }"></div>
                                            </div>
                                            <span class="text-sm font-medium z-10">Adjustment</span>
                                        </div>
                                    </label>

                                    <!-- Payment Option -->
                                    <label class="w-full inline-flex items-center cursor-pointer group">
                                        <input type="radio" v-model="form.export_type" value="Payment"
                                            class="hidden peer" />
                                        <div class="w-full relative flex items-center justify-center p-2">
                                            <!-- Hover circle -->
                                            <div class="absolute -inset-1 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-200 bg-[var(--color-border)]/40"
                                                :class="{
                                                    'opacity-100':
                                                        form.export_type ===
                                                        'Payment',
                                                }"></div>
                                            <!-- Radio button -->
                                            <div class="relative w-5 h-5 mr-2 rounded-full border-2 border-[var(--color-bg-avatar)] transition-colors z-10 group-hover:border-[var(--color-border)]"
                                                :class="{
                                                    'border-[var(--color-border)]':
                                                        form.export_type ===
                                                        'Payment',
                                                }">
                                                <div class="absolute inset-0 m-auto w-2.5 h-2.5 rounded-full bg-[var(--color-border)] transition-opacity"
                                                    :class="{
                                                        'opacity-100':
                                                            form.export_type ===
                                                            'Payment',
                                                        'opacity-0':
                                                            form.export_type !==
                                                            'Payment',
                                                    }"></div>
                                            </div>
                                            <span class="text-sm font-medium z-10">Payment</span>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-1">
                                    <label
                                        class="block text-sm font-medium text-[var(--color-text-secondary)] mb-2">Start
                                        Date</label>
                                    <DatePicker v-model="form.start_date" placeholder="Select Date" format="MM-DD-YYYY"
                                        :message="form.errors.start_date" />
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-sm font-medium text-[var(--color-text-secondary)] mb-2">End
                                        Date</label>
                                    <DatePicker v-model="form.end_date" placeholder="Select Date" format="MM-DD-YYYY"
                                        :message="form.errors.end_date" />
                                </div>
                            </div>

                            <div class="w-full flex justify-center items-center gap-4">
                                <button v-if="canUpdate('0404-EXPRTGL')" type="submit" @click="submitType = 'untag'"
                                    class="submitButton w-full !flex !justify-center !items-center"
                                    :disabled="form.processing || isGenerating">
                                    <span>{{
                                        form.processing
                                            ? "Untagging Text File..."
                                            : "Untag Selected Export Type"
                                    }}</span>
                                </button>
                                <button type="submit" @click="submitType = 'generate'"
                                    class="submitButton w-full !flex !justify-center !items-center"
                                    :disabled="form.processing || isGenerating">
                                    <span>{{
                                        isGenerating
                                            ? "Generating your report..."
                                            : "Generate Selected Export Type"
                                    }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useForm, usePage } from "@inertiajs/vue3";
import { mdiInvoiceTextSendOutline } from "@mdi/js";
import { route } from "../../../vendor/tightenco/ziggy/src/js";
import ToastAlertWarning from "./Components/ToastAlertWarning.vue";
import { onMounted, onUnmounted, onUnmounted as vueOnUnmounted, ref } from "vue";
import DatePicker from "./Components/DatePicker.vue";
import ToastAlert from "./Components/ToastAlert.vue";
import usePermissions from "./Composables/usePermissions";

const { canUpdate } = usePermissions();

const form = useForm({
    export_type: "Other Income",
    start_date: null,
    end_date: null,
    file_format: 'csv',
});

const showExportTypeModal = ref(false);

const submitType = ref(null);
const error = ref(null);
const userId = ref(null);
const page = usePage();
const pathDelete = ref(null);

const isGenerating = ref(false);
const generationProgress = ref(null);
const generationStatus = ref("");

const showToast = ref(false);
const toastMessage = ref("");
let toastTimeout = null;

const showSToast = ref(false);
const toastSMessage = ref("");
let toastSTimeout = null;

const showSuccessToast = (message) => {
    toastSMessage.value = message;
    showSToast.value = false;
    if (toastSTimeout) clearTimeout(toastSTimeout);

    setTimeout(() => {
        showSToast.value = true;
    }, 0);

    toastSTimeout = setTimeout(() => {
        showSToast.value = false;
        toastSTimeout = null;
    }, 3000);
};

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

const submit = async () => {
    if (submitType.value === "untag") {
        untagExport();
    } else {
        if (!form.export_type || !form.start_date || !form.end_date) {
            showWarningToast("Please Fill In All Required Fields");
            return;
        }

        if (new Date(form.start_date) > new Date(form.end_date)) {
            showWarningToast("End Date Must Be After Start Date");
            return;
        }
        showExportTypeModal.value = true;
    }
};

const handleExportChoice = (format) => {
    form.file_format = format;
    showExportTypeModal.value = false;
    generateExport();
};

const generateExport = async () => {
    if (isGenerating.value) {
        return;
    }

    if (!form.export_type || !form.start_date || !form.end_date) {
        showWarningToast("Please Fill In All Required Fields");
        return;
    }

    if (new Date(form.start_date) > new Date(form.end_date)) {
        showWarningToast("End Date Must Be After Start Date");
        return;
    }

    isGenerating.value = true;
    generationProgress.value = 1;
    generationStatus.value = "Preparing to generate report...";

    const channelName = `textfile-generation.${userId.value}`;

    let echoChannel = null;
    try {
        if (window.Echo && window.Echo.private) {
            echoChannel = window.Echo.private(channelName)
                .listen(".App\\Events\\ExportTextFileGenerationProgress", (event) => {
                    if (typeof event.progress === "number") {
                        generationProgress.value = Math.min(
                            100,
                            Math.max(0, event.progress)
                        );
                    }
                    if (event.message) {
                        generationStatus.value = event.message;
                    }
                })
                .listen(".App\\Events\\ExportTextFileGenerated", () => {
                    generationProgress.value = 100;
                    generationStatus.value = "Report ready!";
                })
                .error(() => {});
        }
    } catch (e) {}

    try {
        const response = await axios.post(
            route("generateTextFile", { tenant: page.props.tenant }),
            form.data(),
            {}
        );

        if (response.data.success === false) {
            showWarningToast(
                response.data.message ||
                "No data found for the selected date range"
            );
            return;
        }

        showSuccessToast(
            "Report ready!"
        );
        form.reset();
    } catch (err) {
        if (err.response?.status === 422 && err.response?.data?.errors) {
            const validationErrors = err.response.data.errors;

            if (Object.keys(validationErrors).length === 1) {
                const firstError = Object.values(validationErrors)[0][0]; // Get first error message
                showWarningToast(firstError);
            } else {
                showWarningToast(
                    "Please fill in all required fields correctly"
                );
            }
        } else {
            const message =
                err.response?.data?.message ||
                err.message ||
                "Failed to start TextFile generation";
            error.value = message;
            showWarningToast(message);
        }

    } finally {
        isGenerating.value = false;

        if (echoChannel && window.Echo && window.Echo.leave) {
            try {
                window.Echo.leave(channelName);
            } catch (e) {}
        }

        setTimeout(() => {
            generationProgress.value = null;
            generationStatus.value = "";
        }, 1500);
    }
};

const untagExport = () => {
    form.post(route("untagExport", { tenant: page.props.tenant }), {
        onSuccess: () => {
            showSuccessToast("Untagged Successfully");
            form.reset();
        },
        onError: (error) => {
            showToast.value = false;
            if (Object.keys(error).length === 1) {
                const firstError = Object.values(error)[0];
                showWarningToast(firstError);
            } else if (Object.keys(error).length !== 1) {
                showWarningToast("Please Fill In All Required Fields");
            }
        },
    });
};

const deletePdf = async () => {
    if (!pathDelete.value) return;

    try {
        await axios.delete(route("pdf.delete", { tenant: page.props.tenant }), {
            data: { path: pathDelete.value.split("/storage/")[1] },
        });
    } catch (err) {
        console.warn("Failed to delete TextFile:", err);
    }
};

onMounted(() => {
    userId.value = page.props.auth.user.id || null;
});

onUnmounted(() => {
    const channelName = `textfile-generation.${userId.value}`;
    if (window.Echo && window.Echo.leave) {
        try {
            window.Echo.leave(channelName);
        } catch (e) {}
    }
});
</script>
