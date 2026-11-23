<template>
    <div class="min-w-0 w-full space-y-5 pb-8 text-[var(--color-text-primary)]">
        <Head :title="isUntagPage ? ' | Untag Transaction Export' : ' | Transaction Audit Trail'" />
        <ToastAlert :key="successToastKey" :show="successToastVisible" :message="successMessage" />
        <header class="space-y-4">
            <div>
                <h1 class="text-2xl font-semibold">{{ isUntagPage ? 'Untag Transaction Export' : 'Transaction Audit Trail' }}</h1>
                <p class="mt-1 text-sm text-[var(--color-text-secondary)]">
                    {{ isUntagPage ? 'Allow a payment or adjustment to be edited and exported again.' : 'See what changed, who changed it, and why.' }}
                </p>
            </div>
        </header>
        <p v-if="!auditReady" role="alert" class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-4 text-sm">
            Audit logging is not installed for this BU. Apply the transaction audit migration before editing or untagging transactions.
        </p>
        <p v-if="successMessage" role="status" class="rounded-lg bg-emerald-500/10 p-4 text-sm text-emerald-500">{{ successMessage }}</p>

        <section v-if="isUntagPage" class="grid items-start gap-5 lg:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
            <form @submit.prevent="untag" class="min-w-0 overflow-hidden rounded-xl border border-[var(--color-border)] bg-[var(--color-bg-secondary)]">
                <header class="border-b border-[var(--color-border)] px-5 py-4 sm:px-6">
                    <h2 class="text-lg font-semibold">Remove an export tag</h2>
                    <p class="mt-1 text-sm text-[var(--color-text-secondary)]">One transaction at a time. All changes are recorded.</p>
                </header>
                <div class="space-y-6 p-5 sm:p-6">
                    <p v-if="untagForm.errors.general" role="alert" class="rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-500">{{ untagForm.errors.general }}</p>
                    <p v-if="!canUpdate('0405-UNTAG')" class="rounded-lg border border-[var(--color-border)] p-3 text-sm text-[var(--color-text-secondary)]">View-only access. Untag Update permission is required to remove an export tag.</p>
                    <fieldset :disabled="untagForm.processing || !auditReady || !canUpdate('0405-UNTAG')" class="space-y-6 disabled:opacity-60">
                        <div>
                            <h3 class="mb-4 flex items-center gap-2 text-sm font-semibold"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-[var(--color-primary)]/15 text-xs text-[var(--color-primary)]" aria-hidden="true">1</span> Select the transaction</h3>
                            <div class="grid gap-4 sm:grid-cols-[180px_minmax(0,1fr)]">
                                <div>
                                    <label for="untag-type" class="mb-2 block text-sm font-medium">Transaction Type</label>
                                    <select id="untag-type" v-model="untagForm.transaction_type" :class="untagInputClass">
                                        <option>Payment</option><option>Adjustment</option>
                                    </select>
                                    <p v-if="untagForm.errors.transaction_type" class="mt-1 text-sm text-red-500">{{ untagForm.errors.transaction_type }}</p>
                                </div>
                                <div>
                                    <label for="untag-number" class="mb-2 block text-sm font-medium">{{ untagForm.transaction_type }} No <span class="text-red-500">*</span></label>
                                    <input id="untag-number" v-model="untagForm.transaction_no" type="text" maxlength="255" required autocomplete="off"
                                        :placeholder="`Enter exact ${untagForm.transaction_type.toLowerCase()} number`"
                                        :class="[untagInputClass, 'font-mono']" aria-describedby="untag-number-help" />
                                    <p id="untag-number-help" class="mt-2 text-xs text-[var(--color-text-secondary)]">Use the transaction number, not an invoice or DS number.</p>
                                    <p v-if="untagForm.errors.transaction_no" class="mt-1 text-sm text-red-500">{{ untagForm.errors.transaction_no }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="border-t border-[var(--color-border)] pt-5">
                            <h3 class="mb-4 flex items-center gap-2 text-sm font-semibold"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-[var(--color-primary)]/15 text-xs text-[var(--color-primary)]" aria-hidden="true">2</span> Explain the correction</h3>
                            <label for="untag-remarks" class="mb-2 block text-sm font-medium">Reason for Untagging <span class="text-red-500">*</span></label>
                            <textarea id="untag-remarks" v-model="untagForm.remarks" rows="4" maxlength="2000" required
                                placeholder="What needs to be corrected, and why is another export needed?" :class="[untagInputClass, 'resize-y']"></textarea>
                            <div class="mt-2 flex justify-between gap-3 text-xs text-[var(--color-text-secondary)]">
                                <span>This reason will be saved with your name and the date/time.</span>
                                <span class="whitespace-nowrap">{{ untagForm.remarks.length }} / 2000</span>
                            </div>
                            <p v-if="untagForm.errors.remarks" class="mt-1 text-sm text-red-500">{{ untagForm.errors.remarks }}</p>
                        </div>
                        <div class="border-t border-[var(--color-border)] pt-5">
                            <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-[var(--color-primary)]/15 text-xs text-[var(--color-primary)]" aria-hidden="true">3</span> Review and confirm</h3>
                            <div class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-bg-primary)]/40 px-4 py-3">
                                <span class="text-xs text-[var(--color-text-secondary)]">Entered {{ untagForm.transaction_type.toLowerCase() }} number</span>
                                <span class="break-all font-mono text-sm font-semibold">{{ untagForm.transaction_no.trim() || 'Not entered yet' }}</span>
                            </div>
                            <label class="flex cursor-pointer items-start gap-3 text-sm leading-relaxed">
                                <input id="untag-confirm" type="checkbox" v-model="untagForm.confirm_reexport" required class="mt-1 h-4 w-4 shrink-0 accent-[var(--color-primary)]" />
                                <span>I understand this allows re-export and does not reverse existing GL postings.</span>
                            </label>
                            <p v-if="untagForm.errors.confirm_reexport" class="mt-1 text-sm text-red-500">{{ untagForm.errors.confirm_reexport }}</p>
                        </div>
                    </fieldset>
                </div>
                <footer class="flex flex-col-reverse gap-3 border-t border-[var(--color-border)] px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <button type="button" @click="clearUntagForm" :disabled="untagForm.processing" class="rounded-lg border border-[var(--color-border)] px-4 py-2.5 text-sm hover:bg-[var(--color-primary)]/5 disabled:opacity-50">Clear Form</button>
                    <button type="submit" :disabled="!untagReady"
                        class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)] disabled:cursor-not-allowed disabled:opacity-50">
                        {{ untagForm.processing ? 'Removing Export Tag...' : 'Remove Export Tag' }}
                    </button>
                </footer>
            </form>
            <aside class="space-y-4" aria-label="Export correction guidance">
                <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-5">
                    <h2 class="text-sm font-semibold">Check with accounting first</h2>
                    <p class="mt-2 text-sm leading-relaxed text-[var(--color-text-secondary)]">Untagging makes a transaction eligible for another export. Any previous GL posting still exists.</p>
                    <p class="mt-3 text-sm font-medium">Arrange the GL correction before exporting again to avoid duplicate postings.</p>
                </div>
                <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg-secondary)] p-5">
                    <h2 class="text-sm font-semibold">What changes?</h2>
                    <p class="mt-2 text-sm leading-relaxed text-[var(--color-text-secondary)]">Only the export tag is removed. The transaction can then be corrected from Payments or Adjustments.</p>
                    <h3 class="mt-5 text-sm font-semibold">What stays unchanged?</h3>
                    <ul class="mt-2 list-disc space-y-2 pl-4 text-sm text-[var(--color-text-secondary)]">
                        <li>Transaction amounts and payment details.</li>
                        <li>Existing export files and GL entries.</li>
                        <li>Existing WHT export markers remain unchanged.</li>
                    </ul>
                    <p class="mt-5 border-t border-[var(--color-border)] pt-4 text-xs leading-relaxed text-[var(--color-text-secondary)]">The transaction is not deleted or cancelled. Untagging is recorded in the audit trail.</p>
                </div>
            </aside>
        </section>

        <section v-else class="space-y-4">
            <form @submit.prevent="applyFilters" class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg-secondary)] p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="min-w-0 flex-1">
                    <label for="audit-search" class="mb-1 block text-xs font-medium">Search Audit Trail</label>
                    <input id="audit-search" v-model="searchFilters.search" type="search" maxlength="255"
                        placeholder="Transaction number, person, or reason" class="form-input w-full" />
                </div>
                <div class="sm:w-44">
                    <label for="audit-type" class="mb-1 block text-xs">Transaction Type</label>
                    <select id="audit-type" v-model="searchFilters.transaction_type" class="form-input w-full">
                        <option value="">All Types</option><option>Payment</option><option>Adjustment</option>
                    </select>
                </div>
                <button type="submit" :disabled="filterLoading" class="rounded-lg bg-[var(--color-primary)] px-5 py-2 text-sm font-medium text-white disabled:opacity-50">{{ filterLoading ? 'Searching...' : 'Search' }}</button>
                <button type="button" @click="showFilters = !showFilters" :aria-expanded="showFilters" aria-controls="audit-more-filters"
                    class="rounded-lg border border-[var(--color-border)] px-4 py-2 text-sm hover:bg-[var(--color-primary)]/10">
                    {{ showFilters ? 'Less Filters' : 'More Filters' }}
                </button>
                <button v-if="hasFilters" type="button" @click="resetFilters" :disabled="filterLoading" class="px-2 py-2 text-sm text-[var(--color-text-secondary)] hover:text-[var(--color-primary)]">Clear</button>
                </div>
                <div id="audit-more-filters" v-show="showFilters" class="mt-4 grid gap-3 border-t border-[var(--color-border)] pt-4 sm:grid-cols-3">
                <div>
                    <label for="audit-action" class="mb-1 block text-xs">Action</label>
                    <select id="audit-action" v-model="searchFilters.action" class="form-input w-full">
                        <option value="">All Actions</option><option value="receipt_correction">Receipt Correction</option><option value="export_untag">Export Untag</option>
                    </select>
                </div>
                <div>
                    <label for="audit-from" class="mb-1 block text-xs">Changed From</label>
                    <input id="audit-from" v-model="searchFilters.date_start" type="date" class="form-input w-full" />
                </div>
                <div>
                    <label for="audit-to" class="mb-1 block text-xs">Changed To</label>
                    <input id="audit-to" v-model="searchFilters.date_end" type="date" :min="searchFilters.date_start || undefined" class="form-input w-full" />
                </div>
                </div>
                <p v-if="filterError" role="alert" class="mt-3 text-sm text-red-400">{{ filterError }}</p>
            </form>
            <div class="flex flex-wrap justify-between gap-2 text-xs text-[var(--color-text-secondary)]" aria-live="polite">
                <span>{{ logs?.total || 0 }} {{ logs?.total === 1 ? 'record' : 'records' }} found</span>
                <span>Newest first | {{ auditTimezone }}</span>
            </div>
            <div class="overflow-x-auto rounded-xl border border-[var(--color-border)] bg-[var(--color-bg-secondary)]">
                <table class="w-full text-left text-sm">
                    <caption class="sr-only">Transaction audit records</caption>
                    <thead class="border-b border-[var(--color-border)] bg-[var(--color-bg-primary)]/40 text-xs uppercase text-[var(--color-text-secondary)]">
                        <tr>
                            <th scope="col" class="whitespace-nowrap px-4 py-3">Date &amp; Time</th>
                            <th scope="col" class="px-4 py-3">Type</th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3">Transaction No</th>
                            <th scope="col" class="px-4 py-3">Action</th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3">Changed By</th>
                            <th scope="col" class="w-1/4 px-4 py-3">Remarks</th>
                            <th scope="col" class="px-4 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--color-border)]">
                        <tr v-for="log in logs?.data || []" :key="log.id" class="hover:bg-[var(--color-primary)]/5">
                            <td class="whitespace-nowrap px-4 py-3 text-xs"><time :datetime="log.occurred_at">{{ timestamp(log.occurred_at) }}</time></td>
                            <td class="px-4 py-3">{{ log.transaction_type }}</td>
                            <td class="px-4 py-3 font-mono">{{ log.transaction_no }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ log.action === 'export_untag' ? 'Export tag removed' : 'Receipt updated' }}</td>
                            <td class="px-4 py-3">{{ log.actor_name }}</td>
                            <td class="px-4 py-3"><p class="line-clamp-2 break-words [overflow-wrap:anywhere]" :title="log.remarks">{{ log.remarks }}</p></td>
                            <td class="px-4 py-3 text-center">
                                <button type="button" @click="selectedLog = log"
                                    :aria-label="`View details for ${log.transaction_type} ${log.transaction_no}`"
                                    class="whitespace-nowrap rounded-lg border border-[var(--color-primary)]/40 px-3 py-1.5 text-xs font-semibold text-[var(--color-primary)] hover:bg-[var(--color-primary)]/10">
                                    View Details
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!logs?.data?.length">
                            <td colspan="7" class="px-4 py-10 text-center">
                                <p class="font-medium">{{ !auditReady ? 'Audit logging is not installed' : hasFilters ? 'No matching changes' : 'No changes recorded yet' }}</p>
                                <p v-if="auditReady" class="mt-1 text-sm text-[var(--color-text-secondary)]">{{ hasFilters ? 'Try another transaction number or clear your filters.' : 'Receipt edits and export untagging will appear here.' }}</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <PaginationLinks v-if="logs?.data?.length" :paginator="logs" />
            <ViewTransactionAudit v-if="selectedLog" :log="selectedLog" :timezone="auditTimezone" @close="selectedLog = null" />
        </section>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, reactive, ref } from "vue";
import { router, useForm, usePage } from "@inertiajs/vue3";
import { route } from "../../../vendor/tightenco/ziggy/src/js";
import PaginationLinks from "./Components/PaginationLinks.vue";
import usePermissions from "./Composables/usePermissions";
import ViewTransactionAudit from "../Modals/ReportModals/ViewTransactionAudit.vue";
import ToastAlert from "./Components/ToastAlert.vue";

const props = defineProps({ logs: Object, filters: Object, auditReady: Boolean, auditTimezone: String });
const page = usePage();
const { canUpdate } = usePermissions();
const isUntagPage = computed(() => props.filters?.tab === "untag");
const selectedLog = ref(null);
const successMessage = ref("");
const successToastVisible = ref(false);
const successToastKey = ref(0);
let successToastTimeout;
const clearSuccessNotification = () => {
    clearTimeout(successToastTimeout);
    successToastVisible.value = false;
    successMessage.value = "";
};
const notifySuccess = (message) => {
    clearTimeout(successToastTimeout);
    successMessage.value = message;
    successToastKey.value++;
    successToastVisible.value = true;
    successToastTimeout = setTimeout(() => { successToastVisible.value = false; }, 3000);
};
onBeforeUnmount(() => clearTimeout(successToastTimeout));
const filterError = ref("");
const searchFilters = reactive({
    search: props.filters?.search || "",
    transaction_type: props.filters?.transaction_type || "",
    action: props.filters?.action || "",
    date_start: props.filters?.date_start || "",
    date_end: props.filters?.date_end || "",
});
const showFilters = ref(Boolean(searchFilters.action || searchFilters.date_start || searchFilters.date_end));
const filterLoading = ref(false);
const hasFilters = computed(() => Object.values(searchFilters).some((filter) => Boolean(filter)));
const untagForm = useForm({ transaction_type: "Payment", transaction_no: "", remarks: "", confirm_reexport: false });
const untagInputClass = "w-full rounded-lg border border-[var(--color-border)] bg-[var(--color-bg-primary)] px-3 py-2.5 text-sm text-[var(--color-text-primary)] outline-none transition focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/15";
const untagReady = computed(() => canUpdate("0405-UNTAG") && props.auditReady && !untagForm.processing
    && untagForm.confirm_reexport && Boolean(untagForm.remarks.trim()) && Boolean(untagForm.transaction_no.trim()));
const clearUntagForm = () => {
    Object.assign(untagForm, { transaction_type: "Payment", transaction_no: "", remarks: "", confirm_reexport: false });
    untagForm.clearErrors?.();
    clearSuccessNotification();
};
const untag = () => {
    if (!untagReady.value) return;
    const successText = `${untagForm.transaction_type} ${untagForm.transaction_no.trim()} export tag removed successfully.`;
    untagForm.post(route("transactionAudit.untag", { tenant: page.props.tenant }), {
        preserveScroll: true,
        preserveState: true,
        onStart: clearSuccessNotification,
        onSuccess: (response) => {
            notifySuccess(response?.props?.flash?.success || successText);
            untagForm.reset("transaction_no", "remarks", "confirm_reexport");
        },
    });
};
const applyFilters = () => router.get(route("transactionAudit", { tenant: page.props.tenant }),
    { ...searchFilters, tab: "logs" }, {
        preserveState: true, preserveScroll: true, replace: true,
        onStart: () => { filterError.value = ""; filterLoading.value = true; },
        onError: (errors) => { filterError.value = Object.values(errors).join(" "); },
        onFinish: () => { filterLoading.value = false; },
    });
const resetFilters = () => {
    Object.keys(searchFilters).forEach((key) => { searchFilters[key] = ""; });
    applyFilters();
};
const timestamp = (raw) => new Intl.DateTimeFormat("en-PH", {
    timeZone: props.auditTimezone || "UTC", year: "numeric", month: "short", day: "2-digit",
    hour: "2-digit", minute: "2-digit", second: "2-digit",
}).format(new Date(raw));
</script>
