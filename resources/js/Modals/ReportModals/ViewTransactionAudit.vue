<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
        <section ref="dialog" role="dialog" aria-modal="true" aria-labelledby="audit-details-title" tabindex="-1"
            class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-xl border border-[var(--color-border)] bg-[var(--color-bg-secondary)] text-[var(--color-text-primary)] shadow-xl"
            @keydown="handleKeydown">
            <header class="flex items-start justify-between gap-4 border-b border-[var(--color-border)] p-5">
                <div>
                    <h2 id="audit-details-title" class="text-lg font-semibold">Audit Details</h2>
                    <p class="mt-1 text-sm text-[var(--color-text-secondary)]">{{ log.transaction_type }} <span class="font-mono">{{ log.transaction_no }}</span></p>
                </div>
                <button type="button" @click="emit('close')" class="rounded-lg border border-[var(--color-border)] px-3 py-1.5 text-sm hover:bg-[var(--color-primary)]/10">Close</button>
            </header>
            <div class="space-y-5 p-5">
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs text-[var(--color-text-secondary)]">Action</dt><dd class="mt-1 font-medium">{{ log.action === 'export_untag' ? 'Export tag removed' : 'Receipt updated' }}</dd></div>
                    <div><dt class="text-xs text-[var(--color-text-secondary)]">Date &amp; Time ({{ timezone }})</dt><dd class="mt-1">{{ timestamp(log.occurred_at) }}</dd></div>
                    <div><dt class="text-xs text-[var(--color-text-secondary)]">Changed By</dt><dd class="mt-1">{{ log.actor_name }}</dd></div>
                    <div><dt class="text-xs text-[var(--color-text-secondary)]">User / Employee ID</dt><dd class="mt-1">{{ log.actor_username || log.actor_id }}{{ log.actor_employee_id ? ` / ${log.actor_employee_id}` : '' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs text-[var(--color-text-secondary)]">Remarks</dt><dd class="mt-1 whitespace-pre-wrap break-words">{{ log.remarks }}</dd></div>
                </dl>
                <div class="overflow-x-auto rounded-lg border border-[var(--color-border)]">
                    <table class="w-full text-left text-sm">
                        <caption class="sr-only">Every recorded old and new value</caption>
                        <thead class="bg-[var(--color-bg-primary)]/40 text-xs uppercase text-[var(--color-text-secondary)]">
                            <tr>
                                <th scope="col" class="px-4 py-3">Table / Record</th>
                                <th scope="col" class="px-4 py-3">Field</th>
                                <th scope="col" class="px-4 py-3">Old Value</th>
                                <th scope="col" class="px-4 py-3">New Value</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--color-border)]">
                            <tr v-for="(change, index) in log.changes || []" :key="index">
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs">{{ change.table }} #{{ change.record_id }}</td>
                                <td class="px-4 py-3">{{ fieldLabel(change.field) }}</td>
                                <td class="break-all px-4 py-3">{{ value(change.old, change.field) }}</td>
                                <td class="break-all px-4 py-3 font-semibold text-[var(--color-primary)]">{{ value(change.new, change.field) }}</td>
                            </tr>
                            <tr v-if="!log.changes?.length"><td colspan="4" class="px-4 py-6 text-center text-[var(--color-text-secondary)]">No field details recorded.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from "vue";

const props = defineProps({ log: { type: Object, required: true }, timezone: { type: String, default: "UTC" } });
const emit = defineEmits(["close"]);
const dialog = ref(null);
const fieldLabel = (field) => ({
    receipt_date: "Receipt Date", payment_receipt_date: "Payment Receipt Date",
    date: "Ledger Date", ds_no: "DS No", exported: "Export Status",
})[field] || String(field).replaceAll("_", " ");
const value = (raw, field) => {
    if (field === "exported") return Number(raw) ? "Exported" : "Not Exported";
    if (raw === null || raw === undefined || raw === "") return "(empty)";
    if (["receipt_date", "payment_receipt_date", "date"].includes(field) && /^\d{4}-\d{2}-\d{2}$/.test(String(raw))) {
        const date = new Date(`${raw}T00:00:00`);
        if (!Number.isNaN(date.getTime())) {
            return new Intl.DateTimeFormat("en-PH", { year: "numeric", month: "short", day: "2-digit" }).format(date);
        }
    }
    return raw;
};
const timestamp = (raw) => new Intl.DateTimeFormat("en-PH", {
    timeZone: props.timezone || "UTC", year: "numeric", month: "short", day: "2-digit",
    hour: "2-digit", minute: "2-digit", second: "2-digit",
}).format(new Date(raw));
const handleKeydown = (event) => {
    if (event.key === "Escape") emit("close");
    if (event.key === "Tab") {
        event.preventDefault();
        dialog.value.querySelector("button")?.focus();
    }
};
let previousFocus;
let previousOverflow;
onMounted(() => {
    previousFocus = document.activeElement;
    previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    dialog.value.querySelector("button")?.focus();
});
onUnmounted(() => {
    document.body.style.overflow = previousOverflow;
    previousFocus?.focus();
});
</script>
