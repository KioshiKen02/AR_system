<template>
    <div v-show="show"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center px-4 overflow-y-auto">
        <transition @before-enter="beforeEnter" @enter="enter" @after-enter="afterEnter" @before-leave="beforeLeave"
            @leave="leave">
            <div
                class="bg-[var(--color-bg-secondary)] text-[var(--color-text-primary)] w-full max-w-xl rounded-2xl border border-[var(--color-border)] px-8 pb-4 mt-4">
                <!-- Header -->
                <div class="pb-5 mt-5">
                    <h2 class="text-2xl font-bold text-center">
                        VAT & Freight Adjustment
                    </h2>
                    <div class="mt-2 h-0.5 bg-gradient-to-r from-transparent via-[var(--color-border)] to-transparent">
                    </div>
                </div>

                <!-- Invoice Total -->
                <div class="flex justify-center items-center gap-2 mb-6 text-lg font-semibold">
                    <h1>Original Invoice Total:</h1>
                    <span class="text-green-700">
                        {{ formatCurrency(Number(props.data)) }}
                    </span>
                </div>

                <!-- Selection Buttons -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <button @click="enabled.addVat = !enabled.addVat"
                        :class="enabled.addVat ? activeGreen : inactiveBtn" :disabled="enabled.deductVat"
                        class="px-3 py-2 rounded-md">
                        Add VAT
                    </button>

                    <button @click="enabled.deductVat = !enabled.deductVat" :disabled="enabled.addVat"
                        :class="enabled.deductVat ? activeRed : inactiveBtn" class="px-3 py-2 rounded-md">
                        Deduct VAT
                    </button>

                    <button @click="enabled.freight = !enabled.freight"
                        :class="enabled.freight ? activeBlue : inactiveBtn" class="px-3 py-2 rounded-md">
                        Freight
                    </button>
                </div>

                <div>
                    <!-- Add VAT -->
                    <div v-if="enabled.addVat" class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <TextInput label="Add VAT Amount" v-model="form.addVat" type="number" />
                        <TextInput label="Add VAT (%)" v-model="form.addVatPercent" type="number" />
                    </div>

                    <!-- Deduct VAT -->
                    <div v-if="enabled.deductVat" class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <TextInput label="Deduct VAT Amount" v-model="form.deductVat" type="number" />
                        <TextInput label="Deduct VAT (%)" v-model="form.deductVatPercent" type="number" />
                    </div>

                    <!-- Freight -->
                    <div v-if="enabled.freight" class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                        <TextInput label="Freight Amount" v-model="form.freight" type="number" />
                        <TextInput label="Freight (%)" v-model="form.freightPercent" type="number" />
                    </div>
                </div>

                <!-- Net Total -->
                <div class="flex justify-center items-center gap-2 mt-4 mb-6 text-xl font-bold">
                    <h1>Final Net Total:</h1>
                    <span class="text-green-700">
                        {{ formatCurrency(netTotal) }}
                    </span>
                </div>

                <!-- Actions -->
                <div class="flex justify-end gap-2 pt-3 border-t border-[var(--color-border)]">
                    <button @click="closeModal" class="closeButton">
                        <span class="transition-transform duration-300 group-hover:rotate-180">
                            <svg-icon type="mdi" :path="mdiClose" class="w-5 h-5" />
                        </span>
                        Cancel
                    </button>

                    <button @click="submit" class="submitButton" :disabled="submitDisable">
                        <span class="transition-transform duration-300 group-hover:rotate-405">
                            <svg-icon type="mdi" :path="mdiNavigationVariantOutline" class="w-5 h-5" />
                        </span>
                        Submit
                    </button>
                </div>
            </div>
        </transition>
    </div>
</template>

<script setup>
import { computed, watch, reactive } from "vue";
import { useForm } from "@inertiajs/vue3";
import TextInput from "../../Pages/Components/TextInput.vue";
import {
    mdiClose,
    mdiNavigationVariantOutline,
} from "@mdi/js";

/* =====================
   PROPS & EMITS
===================== */
const props = defineProps({
    show: Boolean,
    data: {
        type: [Number, String],
        default: 0,
    },
});

const emit = defineEmits(["close", "closeSuccess"]);

/* =====================
   FORM
===================== */
const form = useForm({
    addVat: 0,
    addVatPercent: 0,
    deductVat: 0,
    deductVatPercent: 0,
    freight: 0,
    freightPercent: 0,
});

/* =====================
   ENABLE FLAGS
===================== */
const enabled = reactive({
    addVat: false,
    deductVat: false,
    freight: false,
});

/* =====================
   STYLES
===================== */
const inactiveBtn =
    "bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200";
const activeGreen = "bg-green-600 text-white";
const activeRed = "bg-red-600 text-white";
const activeBlue = "bg-blue-600 text-white";

/* =====================
   FORMATTER
===================== */
const formatCurrency = (amount) => {
    if (isNaN(amount)) return "";
    return new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
    }).format(amount);
};

/* =====================
   WATCHERS (PERCENT → AMOUNT)
===================== */
watch([() => form.addVatPercent, () => props.data], () => {
    if (!enabled.addVat) return;
    form.addVat = (Number(props.data) * Number(form.addVatPercent || 0)) / 100;
});

watch([() => form.deductVatPercent, () => props.data], () => {
    if (!enabled.deductVat) return;
    form.deductVat =
        (Number(props.data) * Number(form.deductVatPercent || 0)) / 100;
});

watch([() => form.freightPercent, () => props.data], () => {
    if (!enabled.freight) return;
    form.freight =
        (Number(props.data) * Number(form.freightPercent || 0)) / 100;
});

/* =====================
   NET TOTAL
===================== */
const netTotal = computed(() => {
    let total = Number(props.data || 0);

    if (enabled.addVat) total += Number(form.addVat || 0);
    if (enabled.deductVat) total -= Number(form.deductVat || 0);
    if (enabled.freight) total += Number(form.freight || 0);

    return total;
});

/* =====================
   RESET ON OPEN
===================== */
watch(() => props.show, (val) => {
    if (val) {
        enabled.addVat = false;
        enabled.deductVat = false;
        enabled.freight = false;

        form.reset();
    }
});

/* =====================
   SUBMIT
===================== */
const submitDisable = computed(() => {
    return !enabled.addVat && !enabled.deductVat && !enabled.freight;
});

const submit = () => {
    emit("closeSuccess", {
        addVat: enabled.addVat ? Number(form.addVat) : 0,
        addVatPercent: Number(form.addVatPercent),
        deductVat: enabled.deductVat ? Number(form.deductVat) : 0,
        deductVatPercent: Number(form.deductVatPercent),
        freight: enabled.freight ? Number(form.freight) : 0,
        freightPercent: Number(form.freightPercent),
        totalAmount: netTotal.value,
    });
};

const closeModal = () => emit("close");

/* =====================
   ANIMATIONS
===================== */
const beforeEnter = (el) => {
    el.style.height = "0";
    el.style.overflow = "hidden";
};
const enter = (el) => {
    el.style.height = `${el.scrollHeight}px`;
};
const afterEnter = (el) => {
    el.style.height = "";
    el.style.overflow = "";
};
const beforeLeave = (el) => {
    el.style.height = `${el.scrollHeight}px`;
    el.style.overflow = "hidden";
};
const leave = (el) => {
    requestAnimationFrame(() => {
        el.style.height = "0";
    });
};
</script>
