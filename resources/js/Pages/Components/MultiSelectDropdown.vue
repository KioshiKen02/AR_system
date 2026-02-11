<template>
    <div class="relative w-full mb-2" ref="dropdown">
        <label class="block text-sm font-medium text-[var(--color-text-secondary)] mb-2">
            {{ label }}
        </label>

        <button type="button" @click="toggle" :disabled="disabled"
            class="block w-full text-left rounded-md border border-[var(--color-border)] bg-[var(--color-bg-secondary)] px-4 py-2.5 text-sm text-[var(--color-text-primary)] font-semibold placeholder-[var(--color-text-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/70 focus:border-[var(--color-primary)] transition-all duration-200 disabled:cursor-not-allowed group"
            :class="[
                modelValue && modelValue.length > 0
                    ? 'border-[var(--color-border)]'
                    : '!border-red-400 !ring-2 !ring-red-500/50',
                localMessage ? '!border-red-400 !ring-2 !ring-red-500/50' : '',
            ]">
            <div class="flex justify-between items-center gap-2">
                <span :class="(!modelValue || modelValue.length === 0) ? 'text-[var(--color-text-secondary)]' : ''" class="truncate">
                    {{
                        selectedLabel ||
                        (disabled ? disabledPlaceholder : placeholder)
                    }}
                </span>
                <span :class="[
                    'transition-transform duration-300',
                    open ? 'rotate-180' : '',
                ]">
                    <svg class="inline w-5 h-5 float-right" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 011.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.23 8.27a.75.75 0 01.02-1.06z"
                            clip-rule="evenodd" />
                    </svg>
                </span>
            </div>
        </button>

        <ul v-if="open" ref="dropdownMenu"
            class="absolute z-50 mt-1 w-full bg-[var(--color-bg-secondary)] border border-[var(--color-border)] rounded-md shadow-lg max-h-48 overflow-y-auto scrollbar-thin scrollbar-thumb-rounded-full scrollbar-track-rounded-full"
            :class="dropUp ? 'bottom-full mb-1' : 'mt-1'" :style="menuMaxHeightStyle">
            <li v-for="option in options" :key="option.value" @click.stop="toggleSelection(option)"
                class="px-4 py-2 hover:bg-[var(--color-primary)]/10 cursor-pointer text-sm border-b border-[var(--color-border)]/30 last:border-b-0 flex justify-between items-center">
                <span>{{ option.label }}</span>
                <div v-if="isSelected(option.value)" class="flex items-center text-[var(--color-primary)]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
            </li>
        </ul>

        <p v-if="localMessage" class="text-sm text-red-500 mt-1">
            {{ localMessage }}
        </p>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from "vue";

const props = defineProps({
    label: String,
    modelValue: { type: Array, default: () => [] },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: "Select options" },
    disabledPlaceholder: { type: String, default: "Disabled" },
    message: { type: String, default: "" },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);

const open = ref(false);
const dropdown = ref(null);
const dropdownMenu = ref(null);
const dropUp = ref(false);
const observer = ref(null);
const menuMaxHeightStyle = ref(null);
const localMessage = ref(props.message);

const toggle = () => {
    if (props.disabled) return;
    open.value = !open.value;
    if (open.value) checkDropdownPosition();
};

const isSelected = (value) => {
    return props.modelValue.includes(value);
};

const toggleSelection = (option) => {
    let newValue = [...props.modelValue];
    if (isSelected(option.value)) {
        newValue = newValue.filter(v => v !== option.value);
    } else {
        newValue.push(option.value);
    }
    emit("update:modelValue", newValue);
    // Don't close on selection for multi-select
};

const selectedLabel = computed(() => {
    if (!props.modelValue || props.modelValue.length === 0) return null;
    
    // If all selected, maybe show "All Selected" or count?
    if (props.modelValue.length === props.options.length && props.options.length > 0) {
        return "All Selected";
    }

    const labels = props.options
        .filter(opt => props.modelValue.includes(opt.value))
        .map(opt => opt.label);
    
    if (labels.length > 2) {
        return `${labels.length} Selected`;
    }
    
    return labels.join(", ");
});

const checkDropdownPosition = () => {
    if (!open.value || !dropdownMenu.value || !dropdown.value) return;
    const dropdownRect = dropdown.value.getBoundingClientRect();
    // Use a default estimated height if menu isn't fully rendered yet, or force a reflow?
    // In multi-select, height might change as we scroll? No, max-height handles it.
    // We'll use a standard max height for calculation if scrollHeight isn't available immediately.
    const menuHeight = dropdownMenu.value.scrollHeight || 192; 
    const defaultMaxHeight = 192;
    const spaceBelow = window.innerHeight - dropdownRect.bottom;
    const spaceAbove = dropdownRect.top;

    dropUp.value = !(spaceBelow >= menuHeight || spaceBelow >= spaceAbove);

    const availableSpace = dropUp.value
        ? dropdownRect.top - 10
        : window.innerHeight - dropdownRect.bottom - 10;

    menuMaxHeightStyle.value =
        availableSpace < defaultMaxHeight
            ? { maxHeight: `${Math.max(availableSpace, 100)}px` }
            : null;
};

const handleClickOutside = (event) => {
    if (dropdown.value && !dropdown.value.contains(event.target)) {
        open.value = false;
    }
};

onMounted(() => {
    observer.value = new ResizeObserver(checkDropdownPosition);
    observer.value.observe(document.body);
    window.addEventListener("resize", checkDropdownPosition);
});

onBeforeUnmount(() => {
    if (observer.value) observer.value.disconnect();
    document.removeEventListener("click", handleClickOutside);
    window.removeEventListener("resize", checkDropdownPosition);
});

watch(open, (newVal) => {
    if (newVal) {
        document.addEventListener("click", handleClickOutside);
        setTimeout(checkDropdownPosition, 0);
    } else {
        document.removeEventListener("click", handleClickOutside);
    }
});

watch(
    () => props.modelValue,
    (newVal, oldVal) => {
        // Validation logic can go here if needed
        if (newVal && newVal.length > 0) localMessage.value = "";
    },
    { deep: true }
);

watch(
    () => props.message,
    (newVal) => {
        localMessage.value = newVal;
    }
);
</script>
