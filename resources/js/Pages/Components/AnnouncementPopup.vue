<template>
    <div
        v-if="show && announcement"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4"
    >
        <div
            class="w-full max-w-4xl bg-[var(--color-bg-secondary)] text-[var(--color-text-primary)] rounded-2xl border border-[var(--color-border)] shadow-[0_0_30px_var(--color-shadow)]/30 overflow-hidden"
        >
            <div class="px-5 sm:px-8 py-5 border-b border-[var(--color-border)]">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="text-lg sm:text-2xl font-bold leading-snug">
                        {{ announcement.title }}
                    </h2>
                    <button
                        type="button"
                        @click="handleClose()"
                        class="p-2 rounded-lg hover:bg-[var(--color-primary)]/15 transition-colors"
                    >
                        <span class="sr-only">Dismiss</span>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            class="w-6 h-6"
                        >
                            <path
                                d="M6 18L18 6M6 6l12 12"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="px-5 sm:px-8 py-6 max-h-[75vh] overflow-y-auto whitespace-pre-line text-[12px] sm:text-[14px] leading-relaxed">
                {{ announcement.message }}
            </div>

            <div class="px-5 sm:px-8 py-5 border-t border-[var(--color-border)] flex justify-end">
                <button
                    type="button"
                    @click="handleClose()"
                    class="px-5 py-2.5 rounded-lg bg-[var(--color-primary)] text-white hover:opacity-90 transition-opacity text-[12px] sm:text-sm font-semibold"
                >
                    {{ announcement?.is_dismissible ? "Dismiss" : "Close" }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    show: Boolean,
    announcement: Object,
});

const emit = defineEmits(["close", "dismiss"]);

const handleClose = () => {
    if (props.announcement?.is_dismissible) {
        emit("dismiss");
        return;
    }
    emit("close");
};
</script>
