<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
        <section ref="dialog" role="dialog" aria-modal="true" aria-labelledby="receipt-edit-title"
            tabindex="-1" class="w-full max-w-xl max-h-[90vh] overflow-y-auto rounded-xl border border-[var(--color-border)] bg-[var(--color-bg-secondary)] text-[var(--color-text-primary)] shadow-2xl"
            @keydown="handleKeydown">
            <header class="flex items-start justify-between gap-4 border-b border-[var(--color-border)] p-6">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-[var(--color-primary)]">Audited Correction</p>
                    <h2 id="receipt-edit-title" class="mt-2 text-xl font-semibold">Edit {{ kind }} Receipt</h2>
                    <p class="mt-1 font-mono text-sm text-[var(--color-text-secondary)]">{{ transactionNo }}</p>
                </div>
                <button type="button" aria-label="Close editor" :disabled="form.processing" @click="emit('close')"
                    class="rounded-lg px-3 py-2 hover:bg-[var(--color-primary)]/20 disabled:opacity-50">Close</button>
            </header>
            <form @submit.prevent="save" class="space-y-5 p-6">
                <p class="text-sm text-[var(--color-text-secondary)]">
                    {{ kind === 'Payment' ? 'Receipt date changes apply to the payment, its details, and linked transfer ledger.' : 'Only the adjustment receipt date will change.' }}
                    Amounts and clearing dates will not change.
                </p>
                <p v-if="form.errors.general" role="alert" class="rounded-lg bg-red-500/10 p-3 text-sm text-red-400">{{ form.errors.general }}</p>
                <div>
                    <label for="edit-receipt-date" class="mb-2 block text-sm font-medium">Receipt Date</label>
                    <input id="edit-receipt-date" type="date" v-model="form.receipt_date" :max="today" required
                        :disabled="form.processing" class="form-input w-full" />
                    <p v-if="form.errors.receipt_date" class="mt-1 text-sm text-red-400">{{ form.errors.receipt_date }}</p>
                </div>
                <div v-if="kind === 'Payment'">
                    <label for="edit-ds-no" class="mb-2 block text-sm font-medium">DS No</label>
                    <input id="edit-ds-no" type="text" v-model="form.ds_no" maxlength="255"
                        :disabled="form.processing" class="form-input w-full" />
                    <p v-if="form.errors.ds_no" class="mt-1 text-sm text-red-400">{{ form.errors.ds_no }}</p>
                </div>
                <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-bg-primary)]/50 p-4">
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-[var(--color-text-secondary)]">Change Preview</h3>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div><p class="text-xs text-[var(--color-text-secondary)]">Previous Receipt Date</p><p class="mt-1 font-mono">{{ form.original_receipt_date }}</p></div>
                        <div><p class="text-xs text-[var(--color-text-secondary)]">New Receipt Date</p><p class="mt-1 font-mono text-[var(--color-primary)]">{{ form.receipt_date || '-' }}</p></div>
                        <template v-if="kind === 'Payment'">
                            <div><p class="text-xs text-[var(--color-text-secondary)]">Previous DS No</p><p class="mt-1 break-all">{{ form.original_ds_no || '(empty)' }}</p></div>
                            <div><p class="text-xs text-[var(--color-text-secondary)]">New DS No</p><p class="mt-1 break-all text-[var(--color-primary)]">{{ form.ds_no || '(empty)' }}</p></div>
                        </template>
                    </div>
                </div>
                <div>
                    <label for="edit-receipt-remarks" class="mb-2 block text-sm font-medium">Reason / Remarks <span class="text-red-400">*</span></label>
                    <textarea id="edit-receipt-remarks" v-model="form.remarks" rows="3" maxlength="2000" required
                        :disabled="form.processing" placeholder="Explain why this correction is needed." class="form-input w-full"></textarea>
                    <p v-if="form.errors.remarks" class="mt-1 text-sm text-red-400">{{ form.errors.remarks }}</p>
                    <p class="mt-2 text-xs text-[var(--color-text-secondary)]">Your identity, date/time, remarks, and every old/new value will be recorded.</p>
                </div>
                <footer class="flex justify-end gap-3 border-t border-[var(--color-border)] pt-4">
                    <button type="button" :disabled="form.processing" @click="emit('close')" class="rounded-lg border border-[var(--color-border)] px-4 py-2 disabled:opacity-50">Cancel</button>
                    <button type="submit" :disabled="form.processing || !changed || !form.remarks.trim()"
                        class="rounded-lg bg-[var(--color-primary)] px-4 py-2 font-medium text-white disabled:cursor-not-allowed disabled:opacity-50">
                        {{ form.processing ? 'Saving...' : 'Save Correction' }}
                    </button>
                </footer>
            </form>
        </section>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";
import { useForm, usePage } from "@inertiajs/vue3";
import { route } from "../../../../vendor/tightenco/ziggy/src/js";

const props = defineProps({ kind: { type: String, required: true }, selected: { type: Object, required: true } });
const emit = defineEmits(["close", "saved"]);
const page = usePage();
const dialog = ref(null);
const originalDate = String(props.selected.receipt_date || "").slice(0, 10);
const form = useForm({
    receipt_date: originalDate,
    original_receipt_date: originalDate,
    ds_no: props.selected.ds_no || "",
    original_ds_no: props.selected.ds_no || "",
    remarks: "",
});
const transactionNo = computed(() => props.selected.payment_no || props.selected.adjustment_no);
const changed = computed(() => form.receipt_date !== form.original_receipt_date ||
    (props.kind === "Payment" && form.ds_no !== form.original_ds_no));
const now = new Date();
const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}-${String(now.getDate()).padStart(2, "0")}`;
const save = () => form.put(route(props.kind === "Payment" ? "payment.updateReceipt" : "adjustment.updateReceipt", {
    tenant: page.props.tenant, id: props.selected.id,
}), { preserveScroll: true, onSuccess: () => emit("saved") });
const handleKeydown = (event) => {
    if (event.key === "Escape" && !form.processing) emit("close");
    if (event.key !== "Tab") return;
    const elements = [...dialog.value.querySelectorAll('button:not(:disabled), input:not(:disabled), textarea:not(:disabled)')];
    const first = elements[0];
    const last = elements[elements.length - 1];
    if (event.shiftKey && (document.activeElement === first || document.activeElement === dialog.value)) {
        event.preventDefault(); last?.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault(); first?.focus();
    }
};
let previousFocus;
let previousOverflow;
onMounted(() => {
    previousFocus = document.activeElement;
    previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    dialog.value.querySelector("input")?.focus();
});
onUnmounted(() => {
    document.body.style.overflow = previousOverflow;
    previousFocus?.focus();
});
</script>
