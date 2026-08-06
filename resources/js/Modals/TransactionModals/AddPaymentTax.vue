<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 backdrop-blur-sm bg-black/60"
    >
        <ToastAlertWarning :show="showToast" :message="toastMessage" />
        <transition
            @before-enter="beforeEnter"
            @enter="enter"
            @after-enter="afterEnter"
            @before-leave="beforeLeave"
            @leave="leave"
        >
            <form
                v-if="isExpanded"
                ref="formElement"
                @submit.prevent="submit"
                class="bg-[var(--color-bg-secondary)] text-[var(--color-text-primary)] w-full max-w-[108rem] h-[calc(100vh-4rem)] rounded-2xl border border-[var(--color-border)] overflow-hidden flex flex-col"
            >
                <div v-if="modalLoading" class="flex justify-center items-center py-20">
                    <svg
                        width="40"
                        height="40"
                        viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="var(--color-icon)"
                    >
                        <rect class="spinner_jCIR" x="1" y="6" width="2.8" height="12" />
                        <rect class="spinner_jCIR spinner_upm8" x="5.8" y="6" width="2.8" height="12" />
                        <rect class="spinner_jCIR spinner_2eL5" x="10.6" y="6" width="2.8" height="12" />
                        <rect class="spinner_jCIR spinner_Rp9l" x="15.4" y="6" width="2.8" height="12" />
                        <rect class="spinner_jCIR spinner_dy3W" x="20.2" y="6" width="2.8" height="12" />
                    </svg>
                </div>

                <div v-else class="p-4 lg:p-5 flex-1 min-h-0 flex flex-col">
                    <div class="px-2 pb-3 shrink-0">
                        <h2 class="text-2xl font-bold text-center">ADD TAX</h2>
                        <div
                            class="mt-2 h-0.5 bg-gradient-to-r from-transparent via-[var(--color-border)] to-transparent"
                        ></div>
                    </div>

                    <div class="grid grid-cols-1 xl:grid-cols-[1.85fr_.9fr] gap-4 h-full min-h-0 overflow-hidden">
                        <div class="space-y-3 h-full min-h-0 flex flex-col overflow-hidden">
                            <div class="grid grid-cols-1 md:grid-cols-[1fr_15rem_auto] gap-2 items-center">
                                <div class="relative flex-1">
                                    <input
                                        v-model="search"
                                        type="search"
                                        placeholder="Search Payment No, Customer Code, or Customer Name..."
                                        class="form-input pr-10"
                                    />
                                    <button
                                        v-if="search"
                                        type="button"
                                        @click="clearSearch"
                                        class="absolute top-1/2 right-3 -translate-y-1/2 text-[var(--color-text-secondary)] hover:text-red-500"
                                    >
                                        <svg-icon type="mdi" :path="mdiClose" class="w-5 h-5" />
                                    </button>
                                </div>
                                <select
                                    v-model="typeFilter"
                                    class="form-select"
                                >
                                    <option value="">All Types</option>
                                    <option
                                        v-for="option in typeOptions"
                                        :key="option"
                                        :value="option"
                                    >
                                        {{ option }}
                                    </option>
                                </select>
                                <button
                                    type="button"
                                    @click="fetchCandidates"
                                    class="submitButton group whitespace-nowrap"
                                >
                                    <div class="flex justify-center items-center gap-2">
                                        <span class="transition-transform duration-300 group-hover:rotate-360">
                                            <svg-icon type="mdi" :path="mdiRefresh" class="w-5 h-5" />
                                        </span>
                                        Refresh
                                    </div>
                                </button>
                            </div>

                            <div
                                class="rounded-xl border border-[var(--color-border)] overflow-hidden flex-1 min-h-0"
                            >
                                <div class="h-full min-h-0 overflow-y-auto overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="sticky top-0 bg-[var(--color-bg-secondary)] z-10">
                                            <tr class="border-b border-[var(--color-border)]">
                                                <th class="px-3 py-2 text-left">Payment No</th>
                                                <th class="px-3 py-2 text-left">Customer</th>
                                                <th class="px-3 py-2 text-left">Document</th>
                                                <th class="px-3 py-2 text-right">Amount Paid</th>
                                                <th class="px-3 py-2 text-right">Balance</th>
                                            </tr>
                                        </thead>
                                        <tbody v-if="isLoading">
                                            <tr>
                                                <td colspan="5" class="py-8 text-center">
                                                    Loading payment details...
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tbody v-else-if="filteredCandidates.length === 0">
                                            <tr>
                                                <td colspan="5" class="py-8 text-center">
                                                    No eligible payment details found.
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tbody v-else>
                                            <tr
                                                v-for="candidate in filteredCandidates"
                                                :key="candidate.id"
                                                @click="selectCandidate(candidate)"
                                                class="border-b border-[var(--color-border)]/40 cursor-pointer transition-colors duration-150"
                                                :class="selectedCandidate?.id === candidate.id
                                                    ? 'bg-[var(--color-primary)]/20'
                                                    : 'hover:bg-[var(--color-primary)]/10'"
                                            >
                                                <td class="px-3 py-2 font-medium">{{ candidate.payment_no }}</td>
                                                <td class="px-3 py-2">
                                                    <div class="font-medium">{{ candidate.customer_name }}</div>
                                                    <div class="text-xs text-[var(--color-text-secondary)]">
                                                        {{ candidate.customer_code }}
                                                    </div>
                                                </td>
                                                <td class="px-3 py-2">
                                                    <div class="font-medium">{{ candidate.document_no }}</div>
                                                    <div class="text-xs text-[var(--color-text-secondary)]">
                                                        {{ candidate.type }}
                                                    </div>
                                                </td>
                                                <td class="px-3 py-2 text-right font-semibold">
                                                    {{ formatCurrency(toNumber(candidate.amount_paid)) }}
                                                </td>
                                                <td class="px-3 py-2 text-right font-semibold">
                                                    {{ formatCurrency(toNumber(candidate.balance)) }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-[var(--color-border)] p-3 lg:p-4 space-y-2 min-h-0">
                            <TextInput label="Payment No" type="text" v-model="detail.payment_no" readonly />
                            <TextInput label="Customer" type="text" v-model="detail.customer_name" readonly />
                            <div class="grid grid-cols-2 gap-3">
                                <TextInput label="Document No" type="text" v-model="detail.document_no" readonly />
                                <TextInput label="Type" type="text" v-model="detail.type" readonly />
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <TextInput
                                    label="Gross Applied Amount"
                                    type="text"
                                    v-model="detail.gross_applied"
                                    readonly
                                />
                                <TextInput
                                    label="Available WHT Balance"
                                    type="text"
                                    v-model="detail.balance"
                                    readonly
                                />
                            </div>
                            <TextInput
                                label="WHT Amount"
                                type="decimal"
                                v-model="form.wht_amount"
                                :message="form.errors.wht_amount"
                            />

                            <div class="flex items-center gap-2 pt-1">
                                <label class="relative inline-block w-5 h-5">
                                    <input
                                        type="checkbox"
                                        v-model="form.bir_2307_received"
                                        class="peer appearance-none w-5 h-5 border-2 rounded-sm border-[var(--color-border)] bg-transparent checked:bg-[var(--color-primary)] checked:!border-[var(--color-primary)] focus:outline-none transition-colors duration-200"
                                    />
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        class="absolute p-0.5 top-0.5 left-0 right-0 bottom-0 w-5 h-5 text-[var(--color-bg-primary)] hidden peer-checked:block pointer-events-none"
                                        fill="currentColor"
                                    >
                                        <path d="M9,20.42L2.79,14.21L5.62,11.38L9,14.77L18.88,4.88L21.71,7.71L9,20.42Z" />
                                    </svg>
                                </label>
                                <span class="text-sm">BIR 2307 received?</span>
                            </div>

                            <div v-if="form.bir_2307_received" class="space-y-2">
                                <div class="mb-2">
                                    <label class="block text-sm font-medium text-[var(--color-text-secondary)] mb-2">
                                        Status
                                    </label>
                                    <div class="form-input bg-[var(--color-bg-primary)]/40 font-medium">
                                        Cleared
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <TextInput
                                        label="WHT Transaction Date"
                                        type="date"
                                        v-model="form.wht_date"
                                        :message="form.errors.wht_date"
                                    />
                                    <TextInput
                                        label="Clearing Date"
                                        type="date"
                                        v-model="form.clearing_date"
                                        :message="form.errors.clearing_date"
                                    />
                                </div>

                                <div class="mb-2">
                                    <label class="block text-sm font-medium text-[var(--color-text-secondary)] mb-2">
                                        Remarks
                                    </label>
                                    <textarea
                                        v-model="form.remarks"
                                        rows="1"
                                        class="form-textarea !min-h-[3rem] h-12 py-2"
                                        :class="form.errors.remarks ? '!border-red-400 !ring-2 !ring-red-500/50 bg-red-900/10' : ''"
                                        placeholder="Add remarks"
                                    ></textarea>
                                </div>
                            </div>

                            <div
                                v-if="selectedCandidate"
                                class="rounded-xl bg-[var(--color-primary)]/10 border border-[var(--color-primary)]/20 p-3 text-sm"
                            >
                                Selected line balance in `payment_details` is
                                <span class="font-semibold">
                                    {{ formatCurrency(toNumber(selectedCandidate.balance)) }}
                                </span>.
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-[var(--color-border)] flex justify-end gap-2 shrink-0">
                        <button type="button" @click="closeModal" class="closeButton group">
                            <div class="flex justify-center items-center gap-2">
                                <span class="transition-transform duration-300 group-hover:rotate-180">
                                    <svg-icon type="mdi" :path="mdiClose" class="w-5 h-5" />
                                </span>
                                Close
                            </div>
                        </button>
                        <button
                            type="submit"
                            class="submitButton group"
                            :disabled="form.processing || !selectedCandidate"
                        >
                            <div class="flex justify-center items-center gap-2">
                                <span class="transition-transform duration-300 group-hover:rotate-180">
                                    <svg-icon type="mdi" :path="mdiPlus" class="w-5 h-5" />
                                </span>
                                <span v-if="form.processing">Saving...</span>
                                <span v-else>Apply Tax</span>
                            </div>
                        </button>
                    </div>
                </div>
            </form>
        </transition>
    </div>
</template>

<script setup>
import axios from "axios";
import { computed, ref, watch } from "vue";
import { useForm, usePage } from "@inertiajs/vue3";
import TextInput from "../../Pages/Components/TextInput.vue";
import ToastAlertWarning from "../../Pages/Components/ToastAlertWarning.vue";
import { route } from "../../../../vendor/tightenco/ziggy/src/js";
import { mdiClose, mdiPlus, mdiRefresh } from "@mdi/js";
import { debounce } from "lodash";

const props = defineProps({
    show: Boolean,
});

const emit = defineEmits(["close", "closeSuccess"]);
const page = usePage();
const tenant = page.props.tenant;

const modalLoading = ref(false);
const isLoading = ref(false);
const search = ref("");
const typeFilter = ref("");
const candidates = ref([]);
const selectedCandidate = ref(null);
const showToast = ref(false);
const toastMessage = ref("");
let toastTimeout = null;

const form = useForm({
    payment_detail_id: null,
    wht_amount: null,
    wht_status: "Floating",
    bir_2307_received: false,
    wht_date: null,
    clearing_date: null,
    remarks: null,
});

const detail = ref({
    payment_no: null,
    customer_name: null,
    document_no: null,
    type: null,
    gross_applied: null,
    balance: null,
});

const toNumber = (value) => {
    if (value === null || value === undefined) return 0;
    if (typeof value === "number") return Number.isFinite(value) ? value : 0;
    const parsed = parseFloat(String(value).replace(/,/g, ""));
    return Number.isFinite(parsed) ? parsed : 0;
};

const formatCurrency = (amount) =>
    new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
    }).format(toNumber(amount));

const showWarningToast = (message) => {
    toastMessage.value = message;
    showToast.value = false;

    if (toastTimeout) {
        clearTimeout(toastTimeout);
    }

    setTimeout(() => {
        showToast.value = true;
    }, 0);

    toastTimeout = setTimeout(() => {
        showToast.value = false;
        toastTimeout = null;
    }, 3000);
};

const typeOptions = computed(() => {
    return [...new Set(candidates.value.map((item) => item.type).filter(Boolean))].sort();
});

const filteredCandidates = computed(() => {
    const searchValue = search.value.trim().toLowerCase();

    return candidates.value.filter((candidate) => {
        const matchesSearch =
            searchValue === "" ||
            String(candidate.payment_no ?? "").toLowerCase().includes(searchValue) ||
            String(candidate.customer_code ?? "").toLowerCase().includes(searchValue) ||
            String(candidate.customer_name ?? "").toLowerCase().includes(searchValue);

        const matchesType =
            !typeFilter.value || candidate.type === typeFilter.value;

        return matchesSearch && matchesType;
    });
});

const getTaxCandidatesUrl = () => {
    try {
        return route("payment.taxCandidates", { tenant });
    } catch {
        return `/${tenant}/payment-tax-candidates`;
    }
};

const getAddTaxUrl = () => {
    try {
        return route("payment.addTax", { tenant });
    } catch {
        return `/${tenant}/payment-add-tax`;
    }
};

const syncDetailCard = (candidate) => {
    if (!candidate) {
        detail.value = {
            payment_no: null,
            customer_name: null,
            document_no: null,
            type: null,
            gross_applied: null,
            balance: null,
        };
        return;
    }

    detail.value = {
        payment_no: candidate.payment_no,
        customer_name: candidate.customer_name,
        document_no: candidate.document_no,
        type: candidate.type,
        gross_applied: formatCurrency(candidate.amount_paid),
        balance: formatCurrency(candidate.balance),
    };
};

const fetchCandidates = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get(
            getTaxCandidatesUrl(),
            {
                params: {
                    search: search.value || undefined,
                },
            }
        );

        const payload = response?.data;
        const normalizedCandidates = Array.isArray(payload)
            ? payload
            : Array.isArray(payload?.data)
                ? payload.data
                : [];

        candidates.value = normalizedCandidates;

        if (!candidates.value.find((item) => item.id === selectedCandidate.value?.id)) {
            selectedCandidate.value = filteredCandidates.value[0] ?? null;
        }

        if (selectedCandidate.value) {
            selectCandidate(selectedCandidate.value, false);
        } else {
            syncDetailCard(null);
        }
    } catch (error) {
        console.error("Failed to fetch payment tax candidates:", error);
        candidates.value = [];
        selectedCandidate.value = null;
        syncDetailCard(null);
    } finally {
        isLoading.value = false;
    }
};

const debouncedFetch = debounce(fetchCandidates, 500);

const clearSearch = () => {
    search.value = "";
    fetchCandidates();
};

const selectCandidate = (candidate, resetFields = true) => {
    selectedCandidate.value = candidate;
    form.payment_detail_id = candidate.id;
    syncDetailCard(candidate);

    if (resetFields) {
        form.wht_amount = candidate.balance ? Number(candidate.balance).toFixed(2) : null;
        form.wht_status = "Floating";
        form.bir_2307_received = false;
        form.wht_date = null;
        form.clearing_date = null;
        form.remarks = candidate.remarks || null;
    }
};

const closeModal = () => {
    emit("close");
};

const validateWhtAmount = (showToastMessage = false) => {
    const enteredAmount = toNumber(form.wht_amount);
    const availableBalance = toNumber(selectedCandidate.value?.balance);

    if (enteredAmount > 0 && availableBalance > 0 && enteredAmount > availableBalance) {
        form.errors.wht_amount = "WHT amount cannot be greater than the available balance.";

        if (showToastMessage) {
            showWarningToast("WHT amount exceeds the available balance.");
        }

        return false;
    }

    if (form.errors.wht_amount === "WHT amount cannot be greater than the available balance.") {
        form.errors.wht_amount = "";
    }

    return true;
};

const submit = () => {
    if (!selectedCandidate.value) {
        return;
    }

    if (!validateWhtAmount(true)) {
        return;
    }

    form.payment_detail_id = selectedCandidate.value.id;
    form.post(getAddTaxUrl(), {
        preserveScroll: true,
        onSuccess: () => {
            emit("closeSuccess");
        },
    });
};

watch(search, () => {
    debouncedFetch();
});

watch(filteredCandidates, (items) => {
    if (!items.find((item) => item.id === selectedCandidate.value?.id)) {
        selectedCandidate.value = items[0] ?? null;
        if (selectedCandidate.value) {
            selectCandidate(selectedCandidate.value, false);
        } else {
            syncDetailCard(null);
        }
    }
});

watch(
    () => form.wht_amount,
    (newValue, oldValue) => {
        const availableBalance = toNumber(selectedCandidate.value?.balance);
        const newAmount = toNumber(newValue);
        const oldAmount = toNumber(oldValue);

        validateWhtAmount(false);

        if (
            availableBalance > 0 &&
            newAmount > availableBalance &&
            oldAmount <= availableBalance
        ) {
            showWarningToast("WHT amount exceeds the available balance.");
        }
    }
);

watch(
    () => form.bir_2307_received,
    (received) => {
        if (received) {
            form.wht_status = "Cleared";
            if (!form.clearing_date) {
                form.clearing_date = new Date().toISOString().split("T")[0];
            }
            if (!form.wht_date) {
                form.wht_date = new Date().toISOString().split("T")[0];
            }
            return;
        }

        form.wht_status = "Floating";
        form.wht_date = null;
        form.clearing_date = null;
        form.remarks = null;
    }
);

watch(
    () => props.show,
    async (visible, oldVisible) => {
        if (visible && !oldVisible) {
            modalLoading.value = true;
            form.reset();
            form.wht_status = "Floating";
            form.bir_2307_received = false;
            search.value = "";
            typeFilter.value = "";
            candidates.value = [];
            selectedCandidate.value = null;
            syncDetailCard(null);
            await fetchCandidates();
            modalLoading.value = false;
        }
    },
    { immediate: true }
);

const formElement = ref(null);
const isExpanded = ref(true);

const beforeEnter = (el) => {
    el.style.height = "0";
    el.style.overflow = "hidden";
};

const enter = (el) => {
    el.style.height = `${el.scrollHeight}px`;
};

const afterEnter = (el) => {
    el.style.height = "";
    el.style.overflow = "";
};

const beforeLeave = (el) => {
    el.style.height = `${el.scrollHeight}px`;
    el.style.overflow = "hidden";
};

const leave = (el) => {
    requestAnimationFrame(() => {
        el.style.height = "0";
    });
};
</script>
