<template>
    <div
        v-if="announcement"
        class="w-full border-b border-[var(--color-border)] bg-gradient-to-r from-[var(--color-primary)]/15 via-[var(--color-bg-secondary)] to-[var(--color-bg-secondary)] backdrop-blur-md"
        role="status"
        aria-live="polite"
    >
        <div class="max-w-[1800px] mx-auto px-5 pr-3 py-3 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 sm:gap-4">
            <div class="flex items-start gap-3 min-w-0">
                <div class="mt-0.5 flex-shrink-0">
                    <div class="h-9 w-9 rounded-xl bg-[var(--color-primary)]/15 border border-[var(--color-primary)]/30 flex items-center justify-center shadow-[0_0_0_3px_rgba(0,0,0,0.06)]">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            class="w-5 h-5 text-[var(--color-primary)]"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M10.34 3.94a1.05 1.05 0 011.32 0l8.08 6.06a1.05 1.05 0 01-.63 1.89H4.89a1.05 1.05 0 01-.63-1.89l8.08-6.06z"
                            />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M6 12v6a2 2 0 002 2h8a2 2 0 002-2v-6"
                            />
                        </svg>
                    </div>
                </div>

                <div class="min-w-0">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <span class="relative flex h-2.5 w-2.5">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[var(--color-primary)]/60"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-[var(--color-primary)]"></span>
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold tracking-wide bg-[var(--color-primary)]/15 text-[var(--color-primary)] border border-[var(--color-primary)]/25">
                                What's New
                            </span>
                            <span
                                v-if="isNew"
                                class="px-2 py-0.5 rounded-full text-[11px] font-semibold tracking-wide bg-green-500/15 text-green-600 border border-green-500/25"
                            >
                                New
                            </span>
                        </div>
                        <div class="font-semibold text-sm sm:text-base text-[var(--color-text-primary)] truncate">
                            {{ announcement.title }}
                        </div>
                        <div v-if="createdLabel" class="text-xs text-[var(--color-text-secondary)] flex-shrink-0">
                            {{ createdLabel }}
                        </div>
                    </div>
                    <div class="mt-1 text-xs sm:text-sm text-[var(--color-text-secondary)] line-clamp-2 whitespace-pre-line">
                        {{ announcement.message }}
                    </div>
                </div>
            </div>

            <div class="flex-shrink-0 flex items-center gap-2 sm:pt-0 pt-1">
                <button
                    type="button"
                    @click="$emit('open')"
                    class="px-3 py-1.5 rounded-lg bg-[var(--color-primary)] text-white hover:opacity-90 transition-opacity shadow-sm"
                >
                    View details
                </button>

                <button
                    v-if="announcement.is_dismissible"
                    type="button"
                    @click="$emit('dismiss')"
                    class="px-3 py-1.5 rounded-lg border border-[var(--color-border)] text-[var(--color-text-primary)] hover:bg-[var(--color-primary)]/10 transition-colors"
                >
                    Dismiss
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from "vue";

defineEmits(["open", "dismiss"]);

const props = defineProps({
    announcement: Object,
});

const createdLabel = computed(() => {
    const raw = props.announcement?.created_at;
    if (!raw) return "";
    try {
        return new Date(raw).toLocaleString();
    } catch {
        return "";
    }
});

const isNew = computed(() => {
    const raw = props.announcement?.created_at;
    if (!raw) return false;
    try {
        const created = new Date(raw).getTime();
        if (!Number.isFinite(created)) return false;
        const days = (Date.now() - created) / (1000 * 60 * 60 * 24);
        return days <= 7;
    } catch {
        return false;
    }
});
</script>
