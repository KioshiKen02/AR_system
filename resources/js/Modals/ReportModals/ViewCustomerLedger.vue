<template>
    <div v-if="show" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center px-4">
        <div
            class="bg-[var(--color-bg-secondary)] text-[var(--color-text-primary)] w-full max-w-[95%] rounded-2xl border border-[var(--color-border)] h-[90vh] flex flex-col">
            <!-- Show spinner while loading -->
            <div v-if="modalLoading" class="flex justify-center items-center py-20 flex-1">
                <svg width="40" height="40" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"
                    fill="var(--color-icon)">
                    <rect class="spinner_jCIR" x="1" y="6" width="2.8" height="12" />
                    <rect class="spinner_jCIR spinner_upm8" x="5.8" y="6" width="2.8" height="12" />
                    <rect class="spinner_jCIR spinner_2eL5" x="10.6" y="6" width="2.8" height="12" />
                    <rect class="spinner_jCIR spinner_Rp9l" x="15.4" y="6" width="2.8" height="12" />
                    <rect class="spinner_jCIR spinner_dy3W" x="20.2" y="6" width="2.8" height="12" />
                </svg>
            </div>
            <div v-else class="flex flex-col h-full overflow-hidden">
                <!-- Header -->
                <div class="px-8 pt-6 pb-4 flex-shrink-0">
                    <h2 class="text-2xl font-bold text-center">
                        VIEW CUSTOMER LEDGER
                    </h2>
                    <div class="mt-2 h-0.5 bg-gradient-to-r from-transparent via-[var(--color-border)] to-transparent">
                    </div>
                </div>

                <!-- Top Info Section -->
                <div class="px-8 pb-4 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 border-b border-[var(--color-border)] flex-shrink-0">
                    <TextInput label="Customer Code" type="text" v-model="form.customer_code" readonly />
                    <TextInput label="Customer Name" v-model="form.name" type="text" readonly />
                    <TextInput label="Document Number" v-model="form.invoice_number" type="text" readonly />
                    <TextInput label="Date" v-model="form.date" type="date" readonly />
                    <TextInput label="Type" v-model="form.type" type="text" readonly />
                    <TextInput label="Currency" v-model="form.currency" type="text" readonly />
                </div>

                <!-- Main Content Area (Scrollable) -->
                <div class="flex flex-col flex-1 px-8 py-4 overflow-y-auto gap-6">
                    <div class="flex flex-col md:flex-row gap-6">
                        <!-- Left: Summary / Computations -->
                        <div class="w-full md:w-1/3 flex flex-col gap-3">
                            <TextInput label="Beginning Balance/Amount" v-model="form.amount" type="text" readonly />
                            <TextInput label="Neg. Adjustment (Credit)" v-model="form.neg_adjustment" type="text" readonly />
                            <TextInput label="Pos. Adjustment (Debit)" v-model="form.pos_adjustment" type="text" readonly />
                            <TextInput label="Overage" v-model="form.overage" type="text" readonly />
                            <TextInput label="Shrinkage" v-model="form.shrinkage" type="text" readonly />
                            <TextInput label="Return" v-model="form.return" type="text" readonly />
                            <TextInput label="WHT Amount" v-model="form.wht_amount" type="text" readonly />
                        </div>

                        <!-- Right: Transaction History -->
                        <div class="w-full md:w-2/3 flex flex-col h-full">
                            <h3 class="text-lg font-semibold mb-2">Transaction History</h3>
                            <div class="overflow-hidden rounded-lg border border-[var(--color-border)] flex flex-col flex-1 min-h-[300px]">
                                <div class="overflow-y-auto flex-1 scrollbar-thin scrollbar-thumb-[var(--color-scrollbar-track)] scrollbar-track-[var(--color-primary)]/20">
                                    <table class="w-full text-sm text-left text-[var(--color-text-primary)]">
                                        <thead class="text-xs text-[var(--color-text-secondary)] uppercase bg-[var(--color-bg-primary)] sticky top-0 z-10">
                                            <tr>
                                                <th scope="col" class="px-4 py-3">Date</th>
                                                <th scope="col" class="px-4 py-3">Ref No.</th>
                                                <th scope="col" class="px-4 py-3">Description</th>
                                                <th scope="col" class="px-4 py-3 text-right">Debit</th>
                                                <th scope="col" class="px-4 py-3 text-right">Credit</th>
                                                <th scope="col" class="px-4 py-3 text-right">Balance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(item, index) in detailedTransactions" :key="index"
                                                class="border-b border-[var(--color-border)] hover:bg-[var(--color-bg-primary)]">
                                                <td class="px-4 py-3">{{ item.date }}</td>
                                                <td class="px-4 py-3">{{ item.transaction_no }}</td>
                                                <td class="px-4 py-3">{{ item.description }}</td>
                                                <td class="px-4 py-3 text-right">{{ item.debit > 0 ? formatCurrency(item.debit) : '-' }}</td>
                                                <td class="px-4 py-3 text-right">{{ item.credit > 0 ? formatCurrency(item.credit) : '-' }}</td>
                                                <td class="px-4 py-3 text-right font-semibold">{{ formatCurrency(item.balance) }}</td>
                                            </tr>
                                            <tr v-if="detailedTransactions.length === 0">
                                                <td colspan="6" class="px-4 py-8 text-center text-[var(--color-text-muted)]">
                                                    No transactions found.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Totals Section -->
                    <div class="grid grid-cols-3 gap-4 border-t border-[var(--color-border)] pt-4">
                        <TextInput label="Adjusted Begining Balance Amount" v-model="form.adjusted_amount" type="text" readonly />
                        <TextInput label="Total Payments" v-model="form.amount_paid" type="text" readonly />
                        <TextInput label="Running Balance" v-model="form.running_balance" type="text" readonly 
                            class="font-bold text-[var(--color-primary)]" />
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="px-8 pb-6 pt-4 flex justify-end gap-2 border-t border-[var(--color-border)] flex-shrink-0">
                    <button type="button" @click="closeModal" class="closeButton group">
                        <div class="flex justify-center items-center gap-2">
                            <span class="transition-transform duration-300 group-hover:rotate-180">
                                <svg-icon type="mdi" :path="mdiClose" class="w-5 h-5" />
                            </span>
                            Close
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import {
    computed,
    ref,
    watch,
    onMounted,
    onUnmounted,
    onBeforeUnmount,
} from "vue";
import { route } from "../../../../vendor/tightenco/ziggy/src/js";
import TextInput from "../../Pages/Components/TextInput.vue";
import { useForm, usePage } from "@inertiajs/vue3";
import axios from "axios";
import { mdiClose } from "@mdi/js";

const props = defineProps({
    show: Boolean,
    selected: Object,
    selectedcustname: String,
});

const page = usePage();

const form = useForm({
    invoice_number: null,
    date: null,
    type: null,
    customer_code: null,
    name: null,
    currency: null,
    amount: null,
    adjusted_amount: null,
    overage: null,
    shrinkage: null,
    return: null,
    amount_paid: null,
    wht_amount: null,
    running_balance: null,
    pos_adjustment: null,
    neg_adjustment: null,
});

const adjustmentreasonOptions = ref([]);
const detailedTransactions = ref([]);

const emit = defineEmits(["close"]);

const closeModal = () => {
    emit("close");
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
    }).format(amount);
};

///////////////////////////// WATCH //////////////////////////////////////////
const modalLoading = ref(false);
watch(
    () => props.show,
    async (visible) => {
        if (visible) {
            modalLoading.value = true;

            form.invoice_number = props.selected.invoice_number;
            form.date = props.selected.date;
            form.type = props.selected.type;
            form.customer_code = props.selected.customer_code;
            form.name = props.selectedcustname;
            form.currency = props.selected.currency;
            form.amount = formatCurrency(props.selected.amount);
            form.adjusted_amount = formatCurrency(
                props.selected.adjusted_amount
            );

            form.overage = formatCurrency(props.selected.overage);
            form.shrinkage = formatCurrency(props.selected.shrinkage);
            form.return = props.selected.return
                ? formatCurrency(props.selected.return)
                : formatCurrency("0.00");
            form.amount_paid = formatCurrency(props.selected.amount_paid);
            form.wht_amount = formatCurrency(props.selected.wht_amount);
            form.running_balance = formatCurrency(
            );

            try {
                // Fetch details for ALL types
                const detailResponse = await axios.get(route('customerledger.details', { tenant: page.props.tenant }), {
                    params: {
                        invoice_no: form.invoice_number,
                        type: form.type
                    }
                });
                detailedTransactions.value = detailResponse.data.data;
                const summary = detailResponse.data.summary || {};
                form.pos_adjustment = formatCurrency(summary.pos_adjustment || 0);
                form.neg_adjustment = formatCurrency(summary.neg_adjustment || 0);
                if (form.type === 'BG' || form.type === 'Beginning Balance') {
                    form.amount_paid = formatCurrency(summary.payments_total || 0);
                } else {
                    form.amount_paid = formatCurrency(props.selected.amount_paid || 0);
                }
                form.amount = formatCurrency(summary.beginning_amount ?? parseFloat(props.selected.amount || 0));
                form.adjusted_amount = formatCurrency(summary.adjusted_amount ?? 0);
                form.running_balance = formatCurrency(summary.running_balance ?? 0);

                const response = await axios.get(
                    route("getAdjustmentReasonSetup", { tenant: page.props.tenant }),
                    {
                        params: {
                            type: form.type,
                        },
                    }
                );
                adjustmentreasonOptions.value = response.data;
                modalLoading.value = false;
            } catch (error) {
                console.error("Failed to fetch data:", error);
                modalLoading.value = false;
            }
        }
    },
    { immediate: true }
);
</script>

<style scoped>
@keyframes fadeIn {
    0% {
        opacity: 0;
        transform: translateY(10px);
    }

    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in {
    animation: fadeIn 0.4s ease-out;
}
</style>
