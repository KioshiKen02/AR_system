<template>
    <Transition name="modal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 backdrop-blur-sm bg-black/60">
            <!-- Modal Container -->
            <div
                class="w-full max-w-[90vw] overflow-hidden rounded-2xl bg-[var(--color-bg-secondary)] border border-[var(--color-border)]">
                <!-- Content -->
                <div class="p-6">
                    <!-- Header -->
                    <div class="mb-6 text-center">
                        <h2 class="text-2xl font-bold text-[var(--color-text-primary)] tracking-wide">
                            PAYMENT HISTORY
                        </h2>
                        <div
                            class="mt-2 h-0.5 bg-gradient-to-r from-transparent via-[var(--color-border)] to-transparent">
                        </div>
                    </div>

                    <!-- Search Input -->
                    <div class="mb-4 relative">
                        <input v-model="searchQuery" type="text" placeholder="Search..." ref="searchInput"
                            class="w-full rounded-md px-4 py-2 text-[var(--color-text-primary)] border border-[var(--color-border)]"
                            :class="{
                                '!border-red-400 !ring-2 !ring-red-500/50 bg-red-900/10':
                                    filteredData.length === 0 &&
                                    allResults.length !== 0,
                            }" />
                        <button v-if="searchQuery" @click="clearSearch"
                            class="absolute top-1/2 right-2 transform -translate-y-1/2 text-[var(--color-text-primary)] hover:text-red-500">
                            <svg-icon type="mdi" :path="mdiClose" class="w-5 h-5 hover:text-red-500" />
                        </button>
                        <div v-else
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-[var(--color-text-secondary)]">
                            <svg-icon type="mdi" :path="mdiMagnify" size="20" />
                        </div>
                    </div>

                    <!-- TABLE -->
                    <div
                        class="w-full rounded-xl overflow-hidden border border-[var(--color-border)] backdrop-blur-sm">
                        <div class="relative overflow-x-auto">
                            <div
                                class="max-h-72 overflow-y-auto relative scrollbar-thin scrollbar-thumb-[var(--color-scrollbar-track)] scrollbar-track-[var(--color-primary)]/20 scrollbar-stable [scrollbar-gutter:stable] scrollbar-thumb-rounded-full">
                                <table class="w-full table-fixed text-[var(--color-text-primary)] text-[11px]">
                                    <thead class="sticky top-0 z-10 bg-[var(--color-bg-secondary)] border-b border-[var(--color-border)]/50">
                                        <tr>
                                            <th class="px-1.5 py-1 text-left w-[82px]">
                                            PAYMENT NO
                                            </th>
                                            <th class="px-1.5 py-1 text-left w-[82px]">
                                            DOCUMENT NO
                                            </th>
                                            <th class="px-1.5 py-1 text-left w-[92px]">
                                            DOCUMENT DATE
                                            </th>
                                            <th class="px-1.5 py-1 text-left w-[92px]">
                                            PAYMENT DATE
                                            </th>
                                            <th class="px-1.5 py-1 text-center w-[100px]">
                                            PAYMENT TYPE
                                            </th>
                                            <th class="px-1.5 py-1 text-center w-[100px]">
                                            CHECK TYPE
                                            </th>
                                            <th class="px-1.5 py-1 text-right w-[96px]">
                                            TOTAL AMOUNT
                                            </th>
                                            <th class="px-1.5 py-1 text-right w-[96px]">
                                            AMOUNT (w/o WHT)
                                            </th>
                                            <th class="px-1.5 py-1 text-right w-[90px]">
                                            OVERPAYMENT
                                            </th>
                                            <th class="px-1.5 py-1 text-right w-[90px]">
                                            WHT AMOUNT
                                            </th>
                                            <th class="px-1.5 py-1 text-left w-[82px]">
                                            STATUS
                                            </th>
                                        </tr>
                                    </thead>
                                    <!-- Loading State -->
                                    <tbody v-if="isLoading">
                                        <tr>
                                            <td colspan="11" class="text-center py-8">
                                                <div class="flex justify-center items-center">
                                                    <svg width="30" height="30" viewBox="0 0 24 24"
                                                        xmlns="http://www.w3.org/2000/svg" fill="var(--color-icon)">
                                                        <rect class="spinner_jCIR" x="1" y="6" width="2.8"
                                                            height="12" />
                                                        <rect class="spinner_jCIR spinner_upm8" x="5.8" y="6"
                                                            width="2.8" height="12" />
                                                        <rect class="spinner_jCIR spinner_2eL5" x="10.6" y="6"
                                                            width="2.8" height="12" />
                                                        <rect class="spinner_jCIR spinner_Rp9l" x="15.4" y="6"
                                                            width="2.8" height="12" />
                                                        <rect class="spinner_jCIR spinner_dy3W" x="20.2" y="6"
                                                            width="2.8" height="12" />
                                                    </svg>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>

                                    <!-- Data Rows -->
                                    <tbody v-else class="divide-y divide-[var(--color-border)]/50 rounded-xl">
                                        <tr v-for="(
paymentdetail, index
                                            ) in filteredData" :key="index"
                                            class="rounded-xl hover:bg-[var(--color-primary)]/20 transition-colors duration-150 group">
                                            <td class="px-1.5 py-1 font-medium w-[82px]">
                                                {{ paymentdetail.payment_no }}
                                            </td>
                                            <td class="px-1.5 py-1 font-medium w-[82px]">
                                                {{ paymentdetail.document_no }}
                                            </td>
                                            <td class="px-1.5 py-1 w-[92px]">
                                                {{
                                                    formatDate(
                                                        paymentdetail.document_date
                                                    )
                                                }}
                                            </td>
                                            <td class="px-1.5 py-1 w-[92px]">
                                                {{
                                                    formatDate(
                                                        paymentdetail.payment_date
                                                    )
                                                }}
                                            </td>
                                            <td class="px-1.5 py-1 text-center w-[100px]">
                                                <span
                                                    class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-medium"
                                                    :class="{
                                                        'bg-amber-700 text-amber-300':
                                                            paymentdetail.payment_type ===
                                                            'Cash',
                                                        'bg-lime-700 text-lime-300':
                                                            paymentdetail.payment_type ===
                                                            'Journal Voucher',
                                                        'bg-teal-700 text-teal-300':
                                                            paymentdetail.payment_type ===
                                                            'Online Deposit',
                                                        'bg-sky-700 text-sky-300':
                                                            paymentdetail.payment_type ===
                                                            'Check',
                                                    }">
                                                    {{
                                                        paymentdetail.payment_type
                                                    }}
                                                </span>
                                            </td>
                                            <td class="px-1.5 py-1 text-center w-[100px]">
                                                <span
                                                    class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-medium"
                                                    :class="{
                                                        'bg-cyan-700 text-cyan-300':
                                                            paymentdetail.check_type ===
                                                            'Dated Check',
                                                        'bg-yellow-700 text-yellow-300':
                                                            paymentdetail.check_type ===
                                                            'Post Dated Check',
                                                        'bg-zinc-700 text-zinc-300':
                                                            paymentdetail.check_type ===
                                                            'N/A',
                                                    }">
                                                    {{
                                                        paymentdetail.check_type
                                                    }}
                                                </span>
                                            </td>
                                            <td class="px-1.5 py-1 text-right font-medium w-[96px]">
                                                {{
                                                    formatCurrency(
                                                        paymentdetail.amount_paid
                                                    )
                                                }}
                                            </td>
                                            <td class="px-1.5 py-1 text-right font-medium w-[96px]">
                                                {{
                                                    formatCurrency(
                                                        (Number(paymentdetail.amount_paid) || 0) -
                                                        (Number(paymentdetail.wht_amount) || 0)
                                                    )
                                                }}
                                            </td>
                                            <td class="px-1.5 py-1 text-right font-medium w-[90px]">
                                                {{
                                                    Number(paymentdetail.overpayment_amount) > 0
                                                        ? formatCurrency(
                                                            paymentdetail.overpayment_amount
                                                        )
                                                        : "-"
                                                }}
                                            </td>
                                            <td class="px-1.5 py-1 text-right font-medium w-[90px]">
                                                {{
                                                    formatCurrency(
                                                        paymentdetail.wht_amount
                                                    )
                                                }}
                                            </td>
                                            <td class="px-1.5 py-1 text-left w-[82px]">
                                                <span class="inline-flex items-center">
                                                    <span class="relative flex h-2.5 w-2.5">
                                                        <span
                                                            class="animate-ping absolute inline-flex h-full w-full rounded-full"
                                                            :class="paymentdetail.status ===
                                                                'Paid'
                                                                ? 'bg-emerald-500'
                                                                : paymentdetail.status ===
                                                                    'Floating'
                                                                    ? 'bg-amber-500'
                                                                    : paymentdetail.status ===
                                                                        'Cancelled'
                                                                        ? 'bg-zinc-500'
                                                                        : 'bg-lime-500'
                                                                "></span>
                                                        <span class="relative inline-flex rounded-full h-2.5 w-2.5"
                                                            :class="paymentdetail.status ===
                                                                'Paid'
                                                                ? 'bg-emerald-600'
                                                                : paymentdetail.status ===
                                                                    'Floating'
                                                                    ? 'bg-amber-600'
                                                                    : paymentdetail.status ===
                                                                        'Cancelled'
                                                                        ? 'bg-zinc-600'
                                                                        : 'bg-lime-600'
                                                                "></span>
                                                    </span>
                                                    <span
                                                        class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-medium"
                                                        :class="{
                                                            ' text-emerald-500':
                                                                paymentdetail.status ===
                                                                'Paid',
                                                            ' text-amber-500':
                                                                paymentdetail.status ===
                                                                'Floating',
                                                            ' text-zinc-500':
                                                                paymentdetail.status ===
                                                                'Cancelled',
                                                            ' text-lime-500':
                                                                paymentdetail.status ===
                                                                'Cleared',
                                                        }">
                                                        {{
                                                            paymentdetail.status
                                                        }}
                                                    </span>
                                                </span>
                                            </td>
                                        </tr>

                                        <!-- Empty State -->
                                        <tr v-if="
                                            filteredData.length === 0 &&
                                            !isLoading
                                        ">
                                            <td colspan="11" class="px-5 py-6 text-center">
                                                <div
                                                    class="flex flex-col items-center justify-center text-[var(--color-text-primary)]">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mb-2"
                                                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="1.5"
                                                            d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                    </svg>
                                                    <p class="font-medium">
                                                        No data found for this
                                                        customer
                                                    </p>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="mt-4 pt-2 border-t border-[var(--color-border)] flex justify-end gap-2">
                        <button @click="closeModal" class="closeButton">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { nextTick, ref, watch } from "vue";
import { mdiClose, mdiMagnify } from "@mdi/js";
import { usePage } from "@inertiajs/vue3";

const props = defineProps({
    customer_code: String,
    document_no: String,
    type: String,
});

const page = usePage();

const emit = defineEmits(["close", "submit"]);

const allResults = ref([]);
const isLoading = ref(false);
const filteredData = ref([]);
const searchQuery = ref("");
const searchInput = ref(null);
let debounceTimeout = null;

watch(
    () => props.customer_code,
    async (newCode) => {
        if (newCode) {
            try {
                isLoading.value = true;

                const response = await axios.get(route("paymentHistory", { tenant: page.props.tenant }), {
                    params: {
                        customer_code: newCode,
                    },
                });
                allResults.value = response.data
                    .filter(
                        (paymentdetail) => paymentdetail.type === props.type
                    )
                    .filter(
                        (paymentdetail) =>
                            paymentdetail.document_no === props.document_no
                    )
                    .map((paymentdetail) => ({
                        payment_no: paymentdetail.payment_no,
                        document_no: paymentdetail.document_no,
                        document_date: paymentdetail.document_date,
                        payment_date: paymentdetail.payment_date,
                        payment_type: paymentdetail.payment_type,
                        check_type: paymentdetail.check_type,
                        amount_paid: paymentdetail.amount_paid,
                        wht_amount: paymentdetail.wht_amount,
                        overpayment_amount:
                            paymentdetail.overpayment_amount ?? 0,
                        status: paymentdetail.status,
                    }));
                filteredData.value = allResults.value;
            } catch (error) {
                console.error("Error fetching invoices:", error);
                allResults.value = [];
            } finally {
                isLoading.value = false;
            }
        } else {
            allResults.value = [];
        }
    },
    { immediate: true }
);

const clearSearch = () => {
    searchQuery.value = ""; // Clear search input

    nextTick(() => {
        searchInput.value?.focus();
    });
};

// Debounced search
watch(
    () => searchQuery.value,
    (query) => {
        isLoading.value = true;
        if (debounceTimeout) clearTimeout(debounceTimeout);

        debounceTimeout = setTimeout(() => {
            if (!query.trim()) {
                filteredData.value = allResults.value;
            } else {
                filteredData.value = allResults.value.filter((customer) =>
                    customer.customer_name
                        ?.toString()
                        .toLowerCase()
                        .includes(query.toLowerCase())
                );
            }
            isLoading.value = false;
        }, 400); // 400ms debounce
    }
);

const formatDate = (dateString) => {
    const options = { year: "numeric", month: "short", day: "numeric" };
    return new Date(dateString).toLocaleDateString(undefined, options);
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
    }).format(amount);
};

const closeModal = () => {
    emit("close");
};
</script>
<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>
