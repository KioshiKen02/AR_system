<template>
    <Transition
        enter-active-class="transition-all duration-500 ease-out"
        enter-from-class="opacity-0 -translate-y-8 scale-95"
        enter-to-class="opacity-100 translate-y-0 scale-100"
        leave-active-class="transition-all duration-300 ease-in"
        leave-from-class="opacity-100 translate-y-0 scale-100"
        leave-to-class="opacity-0 -translate-y-8 scale-95"
    >
        <div
            v-if="props.announcement"
            role="alert"
            aria-live="polite"
            class="announcement-card w-full relative rounded-xl overflow-hidden shadow-2xl border border-[var(--color-border)]/40"
        >
            <!-- Animated Shimmer Overlay (light sweep) -->
            <div class="shimmer-overlay"></div>

            <!-- Main Content -->
            <div class="relative z-10 p-5 sm:p-6 flex items-start gap-4">
                <!-- Floating Emoji Icon -->
                <div class="flex-shrink-0 mt-1 float-icon">
                    <span class="text-4xl sm:text-5xl drop-shadow-lg" aria-hidden="true">📢</span>
                </div>

                <!-- Text & Actions -->
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                        <!-- Pulsing "NEW" Badge -->
                        <span class="badge-pulse inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-[11px] font-extrabold uppercase tracking-wider bg-white text-[var(--color-primary)] shadow-lg">
                            <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
                            Latest
                        </span>
                        <h3 class="text-base sm:text-lg font-bold text-white drop-shadow-md truncate">
                            {{ props.announcement.title }}
                        </h3>
                    </div>

                    <p class="mt-2 text-sm sm:text-base text-white/90 leading-relaxed drop-shadow line-clamp-3 whitespace-pre-line">
                        {{ props.announcement.message }}
                    </p>

                    <div class="mt-4 flex items-center gap-3">
                        <button
                            type="button"
                            @click="$emit('open')"
                            class="cta-button group inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-white text-[var(--color-primary)] font-bold text-sm shadow-lg hover:shadow-xl transition-all duration-200 hover:scale-105 active:scale-95"
                        >
                            View details
                            <svg class="w-4 h-4 transition-transform duration-200 group-hover:translate-x-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12" />
                                <polyline points="12 5 19 12 12 19" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Dismiss Button -->
                <button
                    v-if="props.announcement.is_dismissible"
                    type="button"
                    @click="$emit('dismiss')"
                    class="flex-shrink-0 p-1.5 rounded-full text-white/60 hover:text-white hover:bg-white/20 transition-colors backdrop-blur-sm"
                    aria-label="Dismiss announcement"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
        </div>
    </Transition>
</template>

<script setup>
const props = defineProps({
    announcement: {
        type: Object,
        default: null,
    },
});

defineEmits(["open", "dismiss"]);
</script>

<style scoped>
/* ----------------------------------------------
   Catchy Animated Gradient Background
   ---------------------------------------------- */
.announcement-card {
    background: linear-gradient(135deg, #8bf8ac 0%, #1a441a 40%, #4facfe 100%);
    background-size: 300% 300%;
    animation: gradientShift 5s ease-in-out infinite;
    box-shadow: 0 0 40px rgba(245, 87, 108, 0.4);
}

@keyframes gradientShift {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

/* ----------------------------------------------
   Shimmer (Light Sweep)
   ---------------------------------------------- */
.shimmer-overlay {
    position: absolute;
    inset: 0;
    z-index: 0;
    background: linear-gradient(
        105deg,
        transparent 40%,
        rgba(255, 255, 255, 0.25) 50%,
        transparent 60%
    );
    background-size: 200% 100%;
    animation: shimmer 4s ease-in-out infinite;
    pointer-events: none;
}

@keyframes shimmer {
    0% { background-position: -200% 0%; }
    100% { background-position: 200% 0%; }
}

/* ----------------------------------------------
   Floating Emoji
   ---------------------------------------------- */
.float-icon {
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0px) rotate(-2deg); }
    50% { transform: translateY(-10px) rotate(2deg); }
}

/* ----------------------------------------------
   Pulsing Badge (Extra attention)
   ---------------------------------------------- */
.badge-pulse {
    animation: badgeGlow 2s ease-in-out infinite;
}

@keyframes badgeGlow {
    0%, 100% { box-shadow: 0 0 10px rgba(255, 255, 255, 0.3); }
    50% { box-shadow: 0 0 25px rgba(255, 255, 255, 0.7); }
}

/* ----------------------------------------------
   Accessibility: Respect reduced-motion
   ---------------------------------------------- */
@media (prefers-reduced-motion: reduce) {
    .announcement-card {
        animation: none;
        background: linear-gradient(135deg, #58f54a 0%, hsl(108, 86%, 61%) 100%);
        box-shadow: 0 0 30px rgba(245, 87, 108, 0.3);
    }
    .shimmer-overlay {
        display: none;
    }
    .float-icon {
        animation: none;
    }
    .badge-pulse {
        animation: none;
    }
    .cta-button {
        transition: none;
    }
    .cta-button:hover {
        transform: none !important;
    }
}
</style>
