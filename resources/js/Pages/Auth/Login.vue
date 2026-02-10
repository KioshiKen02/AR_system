<template>

    <Head title=" | Login" />
    <ToastAlertWarning :show="showToast" :message="toastMessage" />
    <Transition name="fade" @before-leave="startFadeOut" appear>
        <div class="min-h-screen flex bg-[var(--color-bg-primary)] p-4 sm:p-6 md:p-8 lg:p-10">
            <!-- Left Column - Image Section -->
            <div
                class="hidden lg:flex w-1/2 bg-gradient-to-br from-[var(--color-primary)] to-[var(--color-primary-hover)] rounded-l-xl border-l border-y border-[var(--color-border)]">
                <div class="relative w-full h-full overflow-hidden rounded-l-xl">
                    <img :src="'/storage/images/card-bg.jpg'" alt="Marcela Farms Poultry"
                        class="w-full h-full object-cover opacity-90" />
                    <div class="absolute inset-0 bg-black/20"></div>
                    <div class="absolute inset-0 flex items-center justify-center p-8 xl:p-12">
                        <div class="text-white max-w-md">
                            <img :src="'/storage/images/mflogo.png'" alt="Logo"
                                class="w-40 xl:w-60 h-auto mb-4 xl:mb-6 mx-auto" />
                            <h2 class="text-3xl xl:text-4xl font-bold text-center mb-3 xl:mb-4">
                                Welcome Back
                            </h2>
                            <p class="text-base xl:text-lg font-medium text-center text-white text-opacity-90">
                                Access your {{ $appName }} Account Receivable
                                System Account to continue operations.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Login Form -->
            <div
                class="w-full lg:w-1/2 bg-[var(--color-bg-secondary)] flex items-center justify-center p-4 sm:p-6 md:p-8 lg:p-10 rounded-lg lg:rounded-r-xl lg:rounded-l-none border border-[var(--color-border)]">
                <div class="w-full max-w-sm sm:max-w-md">
                    <div class="text-center mb-6 sm:mb-8">
                        <h1 class="text-2xl sm:text-3xl font-bold text-[var(--color-text-primary)] mb-1 sm:mb-2">
                            Account Login
                        </h1>
                        <p class="text-sm sm:text-base text-[var(--color-text-secondary)]">
                            Enter your credentials to access the system
                        </p>
                    </div>

                    <form @submit.prevent="submit" class="space-y-4 sm:space-y-6">
                        <div class="space-y-3 sm:space-y-4">
                            <TextInput label="Username" v-model="form.username" type="text"
                                :message="form.errors.username"
                                icon="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z" />
                            <TextInput label="Password" v-model="form.password" type="password"
                                :message="form.errors.password"
                                icon="M12 1a11 11 0 0 0-11 11c0 3.55 1.61 6.74 4.16 8.84l.16.12V21h1.67l.34-.16c1.44-.72 3.08-1.16 4.83-1.16s3.39.44 4.83 1.16l.34.16H23v-1.04l.16-.12C25.39 18.74 27 15.55 27 12a11 11 0 0 0-11-11zm0 2a9 9 0 0 1 9 9c0 2.38-1.19 4.47-3 5.74V17a7 7 0 0 0-12-4.94A7 7 0 0 0 6 17v1.74A8.985 8.985 0 0 1 3 12a9 9 0 0 1 9-9zm0 6a3 3 0 1 0 0 6 3 3 0 0 0 0-6z" />
                        </div>

                        <button type="submit"
                            class="relative overflow-hidden w-full bg-[var(--color-primary)] text-white py-2 sm:py-3 rounded-lg font-semibold transition-all duration-300 hover:bg-[var(--color-primary-hover)] hover:shadow-lg disabled:cursor-wait disabled:opacity-50 text-sm sm:text-base"
                            :disabled="form.processing">
                            <span class="relative z-10 flex items-center justify-center">
                                <svg v-if="form.processing"
                                    class="animate-spin -ml-1 mr-3 h-4 w-4 sm:h-5 sm:w-5 text-white"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                                <span>{{
                                    form.processing ? "Logging in..." : "Log In"
                                    }}</span>
                            </span>
                        </button>
                    </form>

                    <div class="mt-6 sm:mt-8 text-center text-xs sm:text-sm text-[var(--color-text-secondary)]">
                        <p>Having trouble accessing your account?</p>
                        <p class="mt-1">
                            Contact Corp IT at
                            <span class="font-bold">501-3000</span>, Local
                            <span class="font-bold">1953 / 1847</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { useForm, usePage } from "@inertiajs/vue3";
import TextInput from "../Components/TextInput.vue";
import ToastAlertWarning from "../Components/ToastAlertWarning.vue";
import { ref } from "vue";
import { route } from "../../../../vendor/tightenco/ziggy/src/js";

defineOptions({
    layout: false,
});

const page = usePage();

const form = useForm({
    username: null,
    password: null,
});

const submit = () => {
    form.post(route("authLogin", { tenant: page.props.tenant }), {
        onError: (error) => {
            if (Object.keys(error).length === 1) {
                const firstError = Object.values(error)[0];
                showWarningToast(firstError);
            } else if (Object.keys(error).length !== 1) {
                showWarningToast("Please Fill Up Necessary Fields");
            }
            form.reset("password");
        },
    });
};

const startFadeOut = () => {
    document.body.style.overflow = "hidden";
};

const showToast = ref(false);
const toastMessage = ref("");
let toastTimeout = null;

const showWarningToast = (message) => {
    toastMessage.value = message;
    showToast.value = false;
    if (toastTimeout) clearTimeout(toastTimeout);

    setTimeout(() => {
        showToast.value = true;
    }, 0);

    toastTimeout = setTimeout(() => {
        showToast.value = false;
        toastTimeout = null;
    }, 3000);
};
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.6s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Animation for form elements */
form>* {
    view-timeline-name: --form;
    view-timeline-axis: block;

    animation-timeline: --form;
    animation-name: fadeInUp;

    animation-range: entry 25% cover 50%;
    animation-fill-mode: both;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
