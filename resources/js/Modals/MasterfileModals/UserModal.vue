<template>
    <div
        v-if="show"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4"
    >
        <ToastAlertWarning :show="showToast" :message="toastMessage" />

        <form @submit.prevent="submit" class="w-full max-w-4xl flex flex-col">
            <div
                class="bg-(--color-bg-secondary) text-(--color-text-primary) rounded-2xl border border-(--color-border) flex flex-col h-full"
            >
                <!-- Header -->
                <div class="px-4 sm:px-8 py-4 sm:py-6 flex-shrink-0">
                    <h2 class="text-xl sm:text-2xl font-bold text-center">
                        ADD NEW USER
                    </h2>
                    <div
                        class="mt-2 h-0.5 bg-gradient-to-r from-transparent via-[var(--color-border)] to-transparent"
                    ></div>
                </div>

                <div
                    v-if="modalLoading"
                    class="flex justify-center items-center py-20"
                >
                    <svg
                        width="40"
                        height="40"
                        viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="var(--color-icon)"
                    >
                        <rect
                            class="spinner_jCIR"
                            x="1"
                            y="6"
                            width="2.8"
                            height="12"
                        />
                        <rect
                            class="spinner_jCIR spinner_upm8"
                            x="5.8"
                            y="6"
                            width="2.8"
                            height="12"
                        />
                        <rect
                            class="spinner_jCIR spinner_2eL5"
                            x="10.6"
                            y="6"
                            width="2.8"
                            height="12"
                        />
                        <rect
                            class="spinner_jCIR spinner_Rp9l"
                            x="15.4"
                            y="6"
                            width="2.8"
                            height="12"
                        />
                        <rect
                            class="spinner_jCIR spinner_dy3W"
                            x="20.2"
                            y="6"
                            width="2.8"
                            height="12"
                        />
                    </svg>
                </div>

                <!-- Content -->
                <div v-else class="flex flex-col lg:flex-row">
                    <!-- Left Column: Employee Info -->
                    <div
                        class="w-full lg:w-[40%] p-4 sm:p-6 border-b lg:border-b-0 lg:border-r border-(--color-border)"
                    >
                        <div class="flex justify-center -mt-4 sm:-mt-6 mb-4">
                            <img
                                v-if="showImage"
                                :src="profilePhotoUrl"
                                alt="Employee Photo"
                                class="w-32 h-32 sm:w-40 sm:h-40 rounded-full border-4 border-(--color-border) object-cover"
                                @error="showImage = false"
                            />
                            <div
                                v-else
                                class="w-32 h-32 sm:w-40 sm:h-40 rounded-full border-4 border-(--color-border) flex items-center justify-center text-4xl font-bold text-white"
                            >
                                {{ userInitials }}
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div
                                class="bg-(--color-bg-secondary) p-3 flex flex-col items-center rounded-lg border border-(--color-border)"
                            >
                                <p
                                    class="text-sm font-bold text-(--color-text-primary)"
                                >
                                    {{ employeeData.employee_name }}
                                </p>
                                <p
                                    class="text-xs text-(--color-text-secondary) font-medium"
                                >
                                    ({{ employeeData.employee_id }})
                                </p>
                            </div>

                            <div
                                class="bg-(--color-bg-secondary) text-sm p-3 rounded-lg border border-(--color-border) space-y-2"
                            >
                                <p class="text-(--color-text-primary)">
                                    <span
                                        class="font-medium text-(--color-text-secondary)"
                                        >Position:</span
                                    >
                                    {{ employeeData.employee_position }}
                                </p>

                                <p class="text-(--color-text-primary)">
                                    <span
                                        class="font-medium text-(--color-text-secondary)"
                                        >Company:</span
                                    >
                                    {{ employeeData.employee_company }}
                                </p>
                                <p class="text-(--color-text-primary)">
                                    <span
                                        class="font-medium text-(--color-text-secondary)"
                                        >BU:</span
                                    >
                                    {{ formattedBunit }}
                                </p>
                                <p class="text-(--color-text-primary)">
                                    <span
                                        class="font-medium text-(--color-text-secondary)"
                                        >Dept:</span
                                    >
                                    {{ employeeData.employee_dept }}
                                </p>
                                <p class="text-(--color-text-primary)">
                                    <span
                                        class="font-medium text-(--color-text-secondary)"
                                        >Section:</span
                                    >
                                    {{ employeeData.employee_section }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Form Inputs -->
                    <div
                        class="w-full lg:w-[60%] p-4 sm:pl-6 sm:pr-6 sm:pb-6 sm:pt-2"
                    >
                        <div class="grid grid-cols-1 gap-4">
                            <TextInput
                                label="Username"
                                v-model="form.username"
                                type="text"
                                :message="form.errors.username"
                                readonly
                            />
                            <TextInput
                                label="Password"
                                v-model="form.password"
                                type="password"
                                :message="form.errors.password"
                                readonly
                            />
                            <TextInput
                                label="Confirm Password"
                                v-model="form.password_confirmation"
                                type="password"
                                readonly
                            />
                            <DropdownInput
                                label="Role"
                                v-model="form.role"
                                :options="[
                                    'Admin',
                                    'Invoicing',
                                    'Accounting',
                                    'Bookkeeper',
                                    'IAD',
                                ]"
                                placeholder="Click to Select"
                                :message="form.errors.role"
                            />
                            <DropdownInput
                                label="Status"
                                v-model="form.status"
                                :options="['Active', 'Not Active']"
                                placeholder="Click to Select"
                                :message="form.errors.status"
                            />

                            <!-- <DropdownInputObject label="App Setting (Database)" v-model="form.app_setting_id"
                                :options="appSettingOptions" placeholder="Select App Setting"
                                :message="form.errors.app_setting_id" /> -->
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-4 sm:px-8 py-4 flex justify-end gap-2 shrink-0">
                    <button
                        type="button"
                        @click="closeModal"
                        class="closeButton group"
                    >
                        <div class="flex justify-center items-center gap-2">
                            <span
                                class="transition-transform duration-300 group-hover:rotate-180"
                            >
                                <svg-icon
                                    type="mdi"
                                    :path="mdiClose"
                                    class="w-5 h-5"
                                />
                            </span>
                            Close
                        </div>
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="submitButton group"
                    >
                        <div class="flex justify-center items-center gap-2">
                            <span
                                class="transition-transform duration-300 group-hover:rotate-405"
                            >
                                <svg-icon
                                    type="mdi"
                                    :path="mdiNavigationVariantOutline"
                                    class="w-5 h-5"
                                />
                            </span>
                            <span v-if="form.processing">Submitting...</span>
                            <span v-else>Submit</span>
                        </div>
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { route } from "../../../../vendor/tightenco/ziggy/src/js";
import TextInput from "../../Pages/Components/TextInput.vue";
import { useForm, usePage } from "@inertiajs/vue3";
import ToastAlertWarning from "../../Pages/Components/ToastAlertWarning.vue";
import SvgIcon from "@jamescoyle/vue-icon";
import { mdiClose, mdiNavigationVariantOutline } from "@mdi/js";
import DropdownInput from "../../Pages/Components/DropdownInput.vue";
import DropdownInputObject from "../../Pages/Components/DropdownInputObject.vue";

const BUSINESS_UNIT_NAMES = {
    HO: "Head Office",
    ICM: "Island City Mall",
    "Acctng / Franchise": "Accounting/Franchise",
    "CK - Admin": "Admin-Chowking",
    "Aggregates & Cons": "Aggregates and Construction Materials",
    AMall: "ASC Main",
    "ASC Tal": "Alturas Talibon",
    DSG: "Distribution Sales Group",
    "ASC Tubigon": "Alturas Tubigon",
    "ASC Tech - Tagb": "ASC Tech - Tagbilaran",
    SlaughterhouseII: "MFI Slaughterhouse & Meat Cutting Plant II",
    "BS Commi": "Bakeshop Commissary",
    ORPB: "Oceanica Resort Panglao Bohol",
    "Cold Storage": "Cold Storage Commissary",
    BMC: "Bohol Milkfish Corporation",
    "Marcela Farms Lab": "Marcela Farms Laboratory",
    Catagbacan: "Catagbacan Farms",
    "COL-C": "Colonnade - Colon",
    "COL-M": "Colonnade - Mandaue",
    "Commi Compound": "Commissary Compound",
    TPF: "The Prawn Farm",
    "FS Commi": "Food Service Commissary",
    "Glass Tagb": "Glass Service Tagbilaran",
    "Glass Tal": "Glass Service Talibon",
    "Rizal Breeder": "MFI Poultry Broiler - Rizal Breeder",
    "GW - AMall": "Greenwhich Alturas Mall",
    "GW - ICM": "Greenwhich ICM",
    "Group 1": "Group 1 - Grocery Group Management",
    "Group 2": "Group 2 - Home and Fashion, Fixrite",
    "Group 3": "Group 3 - Food Group Management",
    "Group 4": "Group 4 - Farms",
    Ortigas: "Ortigas Farms",
    PK: "Peanut Kisses",
    WDG: "Wholesale Distribution Group",
    "JB - AMall": "Jollibee Alturas Mall",
    "JB - PM": "Jollibee Plaza Marcela",
    "JB - Alta Citta": "Jollibee Alta Citta",
    "JB - ICM": "Jollibee ICM",
    "RR - Tubigon": "Red Ribbon Tubigon",
    Maribojoc: "Maribojoc Farms",
    "Dressing Plant": "MFI Dressing Plant",
    "Meat Processing": "MFI Meat Processing",
    "Piggery (Cortes)": "MFI Piggery (Cortes)",
    Canhayupon: "MFI Poultry Broiler - Canhayupon Breeder",
    Lapsaon: "MFI Poultry Broiler - Lapsaon Breeder",
    Growout: "MFI Poultry Broiler - Growout",
    Hatchery: "MFI Poultry Broiler - Hatchery",
    "Bilar Breeder": "MFI Poultry Broiler - Bilar Breeder",
    "Poultry Layer": "MFI Poultry Layer",
    "Repacking Srvcs": "MFI Repacking Services",
    "Tilapia Breeder": "MFI Tilapia Breeder",
    "Piggery (Alicia)": "MFI Piggery (Untaga, Alicia)",
    Noodles: "Noodles Factory",
    Tipcan: "Tipcan Farms",
    PM: "Plaza Marcela",
    "RR - ICM": "Red Ribbon - ICM",
};
const props = defineProps({
    show: Boolean,
    employeeData: Object,
    appSettingOptions: { type: Array, default: () => [] },
});

const page = usePage();

const form = useForm({
    employee_id: null,
    name: null,
    username: null,
    password: null,
    password_confirmation: null,
    role: null,
    status: null,
    app_setting_id: null,
});

const showImage = ref(true);
const profilePhotoUrl = computed(() =>
    route("userPhoto", {
        tenant: page.props.tenant,
        name: props.employeeData.employee_name,
    }),
);

const modalLoading = ref(false);

const emit = defineEmits(["close", "closeSuccess"]);

const closeModal = () => {
    emit("close");
};

const userInitials = computed(() => {
    return (
        props.employeeData.employee_name
            ?.split(" ")
            .map((name) => name[0])
            .join("")
            .slice(1)
            .toUpperCase() || ""
    );
});

//////////////////////////////////////// SHOW TOAST /////////////////////////////////////////////////////////////////////////////////////////
const showToast = ref(false);
const toastMessage = ref("");
let toastTimeout = null; // to keep track of the timeout

const showWarningToast = (message) => {
    toastMessage.value = message;
    showToast.value = false; // Hide first to trigger reactivity if the same toast shows again
    if (toastTimeout) clearTimeout(toastTimeout); // Clear any previous timeout

    // Trigger reactivity again on next tick
    setTimeout(() => {
        showToast.value = true;
    }, 0);

    toastTimeout = setTimeout(() => {
        showToast.value = false;
        toastTimeout = null;
    }, 3000);
};

////////////  //////////////////////////////////////////////////////////////////////////////////////////////////////////

const submit = () => {
    form.employee_id = props.employeeData.employee_id;
    form.post(route("addUser", { tenant: page.props.tenant }), {
        onSuccess: () => {
            form.reset(); // clear on success
            emit("closeSuccess");
        },
        onError: (error) => {
            showToast.value = false;
            if (Object.keys(error).length === 1) {
                const firstError = Object.values(error)[0];
                showWarningToast(firstError);
            } else if (Object.keys(error).length !== 1) {
                showWarningToast("Please Fill Up Necessary Fields");
            }
        },
    });
};

watch(
    () => props.employeeData,
    (newVal) => {
        modalLoading.value = true;
        if (newVal) {
            form.name = newVal.employee_name;
            form.username = newVal.employee_id;
            form.password = newVal.employee_id;
            form.password_confirmation = newVal.employee_id;
            modalLoading.value = false;
        }
    },
    { immediate: true },
);

const formattedBunit = computed(() => {
    return (
        BUSINESS_UNIT_NAMES[props.employeeData.employee_bunit] ??
        props.employeeData.employee_bunit
    );
});
</script>
