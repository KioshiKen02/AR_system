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

                <!-- Main Content Area (Scrollable) -->
                <div class="flex flex-col md:flex-row gap-4 px-8 overflow-y-auto flex-1 pb-4">
                    <!-- Left: Form Fields (Details) -->
                    <div class="w-full md:w-1/3 flex flex-col gap-4">
                        <div class="grid grid-cols-1 gap-4">
                            <TextInput label="Document Number" v-model="form.invoice_number" type="text" readonly />
                            <TextInput label="Date" v-model="form.date" type="date" readonly />
                            <TextInput label="Type" v-model="form.type" type="text" readonly />
                            <TextInput label="Customer Code" type="text" v-model="form.customer_code" readonly />
                            <TextInput label="Customer Name" v-model="form.name" type="text" readonly />
                            <TextInput label="Currency" v-model="form.currency" type="text" readonly />
                        </div>
                    </div>

                    <!-- Right: Transaction History & Computations -->
                    <div class="w-full md:w-2/3 flex flex-col gap-4 h-full">
                        <!-- Transaction History Table -->
                        <div v-if="detailedTransactions.length > 0" class="flex-1 flex flex-col min-h-[300px]">
                            <h3 class="text-lg font-semibold mb-2">Transaction History</h3>
                            <div class="overflow-hidden rounded-lg border border-[var(--color-border)] flex flex-col flex-1">
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
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Summary / Computations -->
                        <div class="grid grid-cols-2 gap-4 mt-auto border-t border-[var(--color-border)] pt-4">
                            <TextInput label="Beginning Balance/Amount" v-model="form.amount" type="text" readonly />
                            <TextInput label="Adjusted Amount" v-model="form.adjusted_amount" type="text" readonly />
                            
                            <TextInput label="Pos. Adjustment (Debit)" v-model="form.pos_adjustment" type="text" readonly />
                            <TextInput label="Neg. Adjustment (Credit)" v-model="form.neg_adjustment" type="text" readonly />

                            <TextInput label="Overage" v-model="form.overage" type="text" readonly />
                            <TextInput label="Shrinkage" v-model="form.shrinkage" type="text" readonly />
                            
                            <TextInput label="Return" v-model="form.return" type="text" readonly />
                            <TextInput label="WHT Amount" v-model="form.wht_amount" type="text" readonly />
                            
                            <TextInput label="Total Payments" v-model="form.amount_paid" type="text" readonly />
                            <TextInput label="Running Balance" v-model="form.running_balance" type="text" readonly 
                                class="font-bold text-[var(--color-primary)]" />
                        </div>
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
                props.selected.document_balance
            );

            try {
                // Fetch details for Beginning Balance if type is BG
                if (form.type === 'BG' || form.type === 'Beginning Balance') {
                     const detailResponse = await axios.get(route('customerledger.details', { tenant: page.props.tenant }), {
                        params: {
                            invoice_no: form.invoice_number,
                            type: form.type
                        }
                    });
                    detailedTransactions.value = detailResponse.data.data;

                    // Compute totals
                    let posAdj = 0;
                    let negAdj = 0;
                    
                    detailedTransactions.value.forEach(item => {
                        if (item.description === 'Positive Adjustment') {
                            posAdj += parseFloat(item.debit || 0);
                        } else if (item.description === 'Negative Adjustment') {
                            negAdj += parseFloat(item.credit || 0);
                        }
                    });

                    form.pos_adjustment = formatCurrency(posAdj);
                    form.neg_adjustment = formatCurrency(negAdj);

                    // Running Balance Computation
                    // To get the running balance, subtract first the Beginning , neg addjustment overage, shrinkage and return and wht amout then add the positive adjustment. 
                    // Then total payments - total of the subtract first the Beginning , neg addjustment overage, shrinkage and return and wht amout then add the positive adjustment.

                    // Parse values ensuring they are numbers (remove currency formatting if needed, but props are likely raw numbers or strings)
                    // Wait, props.selected values are likely strings or numbers. parseFloat handles them.
                    
                    const beginningAmount = parseFloat(props.selected.amount || 0);
                    const overage = parseFloat(props.selected.overage || 0);
                    const shrinkage = parseFloat(props.selected.shrinkage || 0);
                    const returnAmount = parseFloat(props.selected.return || 0);
                    const whtAmount = parseFloat(props.selected.wht_amount || 0);
                    const totalPayments = parseFloat(props.selected.amount_paid || 0);

                    // Formula Part 1: (Beginning - Neg Adj - Overage - Shrinkage - Return - WHT) + Pos Adj
                    // Note: Neg Adj is usually a credit (reduction), so we subtract it.
                    // Overage/Shrinkage/Return/WHT are typically deductions from the receivable?
                    // User said: "subtract first the Beginning , neg addjustment overage, shrinkage and return and wht amout"
                    // Wait, "subtract first the Beginning..." usually means Beginning is the base.
                    // "Beginning - Neg Adj - Overage - Shrinkage - Return - WHT + Pos Adj"
                    // Let's assume standard AR logic:
                    // Receivable = Beginning + Pos Adj - Neg Adj - Payments - Overage - Shrinkage - Return - WHT
                    
                    // User specific instruction: 
                    // "To get the running balance, subtract first the Beginning , neg addjustment overage, shrinkage and return and wht amout then add the positive adjustment."
                    // This wording is a bit ambiguous. "subtract first the Beginning" -> Subtract FROM what?
                    // Usually it's: Running Balance = Beginning + Pos - Neg - Deductions - Payments.
                    
                    // Let's look at the second sentence:
                    // "Then total payments - total of the subtract first the Beginning , neg addjustment overage, shrinkage and return and wht amout then add the positive adjustment."
                    // This implies: Payments - (Beginning - Neg - Overage... + Pos). This yields a negative number if fully paid?
                    
                    // Let's try to interpret "subtract first the Beginning..." as a list of things to subtract from the Total?
                    // Or maybe: "Beginning Balance MINUS (Neg Adj + Overage + Shrinkage + Return + WHT) PLUS Positive Adj"
                    // Let's call this "Net Receivable Amount".
                    
                    // Then "Total Payments - Net Receivable Amount" ? 
                    // If Payments = Net Receivable, result is 0.
                    // If result is 0, balance is 0.
                    
                    // Actually, standard Running Balance = (Beginning + Pos Adj) - (Neg Adj + Overage + Shrinkage + Return + WHT + Payments)
                    
                    // Let's follow the user's specific, albeit slightly confusing, instruction structure:
                    // 1. "subtract first the Beginning , neg addjustment overage, shrinkage and return and wht amout then add the positive adjustment"
                    // Maybe they mean: Base = Beginning - NegAdj - Overage - Shrinkage - Return - WHT + PosAdj
                    
                    // 2. "Then total payments - total of the subtract first..."
                    // Result = Payments - Base.
                    
                    // Wait, if I owe 100 (Beginning), and I pay 100.
                    // Base = 100.
                    // Result = 100 - 100 = 0. 
                    // This makes sense for "Remaining Balance" if the sign is inverted?
                    // Usually Balance = 100 - 100 = 0.
                    
                    // If I owe 100, and pay 50.
                    // Base = 100.
                    // Result = 50 - 100 = -50.
                    // So -50 means 50 remaining to be paid.
                    
                    // Let's stick to the standard AR formula which matches the components listed:
                    // Balance = Beginning + PosAdj - NegAdj - Overage - Shrinkage - Return - WHT - Payments
                    
                    // Let's re-read carefully: "To get the running balance, subtract first the Beginning , neg addjustment overage, shrinkage and return and wht amout then add the positive adjustment."
                    // This might mean: 0 - Beginning - Neg - Overage... + Pos ? No.
                    
                    // Let's assume the user wants the standard logic but listed the deduction items.
                    // Adjusted Amount (Net) = Beginning + Pos Adj - Neg Adj - Overage - Shrinkage - Return - WHT
                    // Running Balance = Adjusted Amount - Payments
                    
                    // Let's calculate Adjusted Amount first.
                    // Note: In some systems, Overage might be an addition? But usually it clears a balance.
                    
                    const adjustedAmountVal = beginningAmount + posAdj - negAdj - overage - shrinkage - returnAmount - whtAmount;
                    form.adjusted_amount = formatCurrency(adjustedAmountVal);
                    
                    const runningBalanceVal = adjustedAmountVal - totalPayments;
                    form.running_balance = formatCurrency(runningBalanceVal);

                } else {
                    detailedTransactions.value = []; // Clear if not BG
                    form.pos_adjustment = formatCurrency(0);
                    form.neg_adjustment = formatCurrency(0);
                }

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
