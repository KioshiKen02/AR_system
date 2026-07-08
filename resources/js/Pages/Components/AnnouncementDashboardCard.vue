<template>
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-2"
    >
        <div
            v-if="currentAnnouncement"
            role="alert"
            aria-live="polite"
            aria-roledescription="carousel"
            @mouseenter="stopAutoplay"
            @mouseleave="startAutoplay"
            class="w-full relative bg-white border border-slate-200 rounded-md shadow-sm overflow-hidden"
        >
            <div class="absolute top-0 left-0 h-[3px] w-full bg-[var(--color-primary)] z-10"></div>
            <div v-if="hasMultipleAnnouncements" class="absolute right-5 sm:right-6 top-1/2 -translate-y-1/2 flex items-center gap-2 z-20">
                <button
                    type="button"
                    @click="prevAnnouncement"
                    class="flex items-center justify-center w-8 h-8 sm:w-9 sm:h-9 rounded-md border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]"
                    aria-label="Previous announcement"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6" />
                    </svg>
                </button>
                <button
                    type="button"
                    @click="nextAnnouncement"
                    class="flex items-center justify-center w-8 h-8 sm:w-9 sm:h-9 rounded-md border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]"
                    aria-label="Next announcement"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                </button>
            </div>
            <Transition
                mode="out-in"
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 translate-x-4"
                enter-to-class="opacity-100 translate-x-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 translate-x-0"
                leave-to-class="opacity-0 -translate-x-4"
            >
                <div
                    :key="currentAnnouncement.id"
                    class="relative z-10 flex flex-col justify-center p-5 sm:p-6 pr-24 sm:pr-32 min-h-[130px]"
                >
                    <div class="flex items-center gap-1.5 text-[var(--color-primary)] mb-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            <path d="M9 12l2 2 4-4"/>
                        </svg>
                        <span class="text-[11px] font-bold uppercase tracking-wider">
                            Announcement
                        </span>
                    </div>

                    <h3 class="text-lg sm:text-xl font-bold text-slate-900 mb-1 leading-tight">
                        {{ currentAnnouncement.title }}
                    </h3>

                    <p class="text-sm text-slate-600 mb-4 line-clamp-2">
                        {{ currentAnnouncement.message }}
                    </p>

                    <div class="flex items-center gap-4 mt-auto">
                        <button
                            type="button"
                            @click="$emit('open', currentAnnouncement)"
                            class="bg-[var(--color-primary)] text-white px-4 py-1.5 rounded-sm text-sm font-semibold hover:opacity-90 transition-opacity focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[var(--color-primary)]"
                        >
                            Read Full Details
                        </button>

                        <div v-if="hasMultipleAnnouncements" class="flex items-center gap-1">
                            <button
                                v-for="(announcement, index) in normalizedAnnouncements"
                                :key="announcement.id"
                                type="button"
                                @click="goToAnnouncement(index)"
                                class="w-1.5 h-1.5 rounded-full transition-colors focus:outline-none"
                                :class="index === currentIndex ? 'bg-[var(--color-primary)]' : 'bg-slate-300 hover:bg-slate-400'"
                                :aria-label="`Go to announcement ${index + 1}`"
                                :aria-current="index === currentIndex ? 'true' : 'false'"
                            />
                        </div>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";

const props = defineProps({
    announcement: {
        type: Object,
        default: null,
    },
    announcements: {
        type: Array,
        default: () => [],
    },
});

defineEmits(["open"]);

const currentIndex = ref(0);
let autoplayTimer = null;

const normalizedAnnouncements = computed(() => {
    if (Array.isArray(props.announcements) && props.announcements.length > 0) {
        return props.announcements.filter(Boolean);
    }

    return props.announcement ? [props.announcement] : [];
});

const currentAnnouncement = computed(() => {
    if (!normalizedAnnouncements.value.length) return null;

    return (
        normalizedAnnouncements.value[currentIndex.value] ??
        normalizedAnnouncements.value[0]
    );
});

const hasMultipleAnnouncements = computed(
    () => normalizedAnnouncements.value.length > 1
);

const stopAutoplay = () => {
    if (autoplayTimer && typeof window !== "undefined") {
        window.clearInterval(autoplayTimer);
        autoplayTimer = null;
    }
};

const startAutoplay = () => {
    stopAutoplay();
    if (typeof window === "undefined" || !hasMultipleAnnouncements.value) return;

    autoplayTimer = window.setInterval(() => {
        currentIndex.value =
            (currentIndex.value + 1) % normalizedAnnouncements.value.length;
    }, 6000);
};

const nextAnnouncement = () => {
    if (!normalizedAnnouncements.value.length) return;

    currentIndex.value =
        (currentIndex.value + 1) % normalizedAnnouncements.value.length;
    startAutoplay();
};

const prevAnnouncement = () => {
    if (!normalizedAnnouncements.value.length) return;

    currentIndex.value =
        (currentIndex.value - 1 + normalizedAnnouncements.value.length) %
        normalizedAnnouncements.value.length;
    startAutoplay();
};

const goToAnnouncement = (index) => {
    currentIndex.value = index;
    startAutoplay();
};

watch(
    () =>
        normalizedAnnouncements.value
            .map((announcement) => announcement?.id)
            .join(","),
    () => {
        if (!normalizedAnnouncements.value.length) {
            currentIndex.value = 0;
            stopAutoplay();
            return;
        }

        if (currentIndex.value >= normalizedAnnouncements.value.length) {
            currentIndex.value = 0;
        }

        startAutoplay();
    },
    { immediate: true }
);

onMounted(() => {
    startAutoplay();
});

onBeforeUnmount(() => {
    stopAutoplay();
});
</script>
