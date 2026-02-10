<template>
    <div v-if="show" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <ToastAlertWarning :show="showToast" :message="toastMessage" />

        <form @submit.prevent="submit" class="w-full max-w-4xl flex flex-col">
            <div
                class="bg-[var(--color-bg-secondary)] text-[var(--color-text-primary)] rounded-2xl border border-[var(--color-border)] overflow-hidden flex flex-col h-full">
                <!-- Header -->
                <div class="px-4 sm:px-8 py-4 sm:py-6 flex-shrink-0">
                    <h2 class="text-xl sm:text-2xl font-bold text-center">
                        EDIT USER
                    </h2>
                    <div class="mt-2 h-0.5 bg-gradient-to-r from-transparent via-[var(--color-border)] to-transparent">
                    </div>
                </div>

                <!-- Scrollable Content Area -->

                <div v-if="modalLoading" class="flex justify-center items-center py-20">
                    <svg width="40" height="40" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"
                        fill="var(--color-icon)">
                        <rect class="spinner_jCIR" x="1" y="6" width="2.8" height="12" />
                        <rect class="spinner_jCIR spinner_upm8" x="5.8" y="6" width="2.8" height="12" />
                        <rect class="spinner_jCIR spinner_2eL5" x="10.6" y="6" width="2.8" height="12" />
                        <rect class="spinner_jCIR spinner_Rp9l" x="15.4" y="6" width="2.8" height="12" />
                        <rect class="spinner_jCIR spinner_dy3W" x="20.2" y="6" width="2.8" height="12" />
                    </svg>
                </div>

                <!-- Content -->
                <div v-else class="flex flex-col lg:flex-row">
                    <!-- Left Column: Employee Info -->
                    <div
                        class="w-full lg:w-[40%] p-4 sm:p-6 border-b lg:border-b-0 lg:border-r border-[var(--color-border)]">
                        <div class="flex justify-center -mt-4 sm:-mt-6 mb-4">
                            <!-- Debug info in comment: {{ profilePhotoUrl }} -->
                            <img v-if="showImage" :src="profilePhotoUrl" alt="Employee Photo"
                                class="w-32 h-32 sm:w-40 sm:h-40 rounded-full border-4 border-[var(--color-border)] object-cover"
                                @error="handleImageError" />
                            <div v-else
                                class="w-32 h-32 sm:w-40 sm:h-40 rounded-full border-4 border-[var(--color-border)] flex items-center justify-center text-4xl font-bold text-white">
                                {{ userInitials }}
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div
                                class="bg-[var(--color-bg-secondary)] p-3 flex flex-col items-center rounded-lg border border-[var(--color-border)]">
                                <p class="text-sm font-bold text-[var(--color-text-primary)]">
                                    {{ employeeData.employee_name }}
                                </p>
                                <p class="text-sm text-[var(--color-text-secondary)] font-medium">
                                    ({{ employeeData.employee_id }})
                                </p>
                            </div>

                            <div
                                class="bg-[var(--color-bg-secondary)] text-sm p-3 rounded-lg border border-[var(--color-border)] space-y-2">
                                <p class="text-[var(--color-text-primary)]">
                                    <span class="font-medium text-[var(--color-text-secondary)]">Position:</span>
                                    {{ employeeData.employee_position }}
                                </p>

                                <p class="text-[var(--color-text-primary)]">
                                    <span class="font-medium text-[var(--color-text-secondary)]">Company:</span>
                                    {{ employeeData.employee_company }}
                                </p>
                                <p class="text-[var(--color-text-primary)]">
                                    <span class="font-medium text-[var(--color-text-secondary)]">BU:</span>
                                    {{ formattedBunit }}
                                </p>
                                <p class="text-[var(--color-text-primary)]">
                                    <span class="font-medium text-[var(--color-text-secondary)]">Dept:</span>
                                    {{ employeeData.employee_dept }}
                                </p>
                                <p class="text-[var(--color-text-primary)]">
                                    <span class="font-medium text-[var(--color-text-secondary)]">Section:</span>
                                    {{ employeeData.employee_section }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Form Inputs -->
                    <div class="w-full lg:w-[60%] p-4 sm:pl-6 sm:pr-6 sm:pb-6 sm:pt-2">
                        <div class="grid grid-cols-1 gap-4">
                            <TextInput label="Username" v-model="form.username" type="text"
                                :message="form.errors.username" />
                            <TextInput label="Password" v-model="form.password" type="password"
                                :message="form.errors.password" />
                            <TextInput label="Confirm Password" v-model="form.password_confirmation" type="password" />
                            <DropdownInput label="Role" v-model="form.role" :options="[
                                'Admin',
                                'Invoicing',
                                'Accounting',
                                'Bookkeeper',
                                'IAD',
                            ]" placeholder="Click to Select" :message="form.errors.role" />
                            <DropdownInput label="Status" v-model="form.status" :options="['Active', 'Not Active']"
                                placeholder="Click to Select" :message="form.errors.status" />

                            <!-- <DropdownInputObject label="App Setting (Database)" v-model="form.app_setting_id"
                                :options="appSettingOptions" placeholder="Select App Setting"
                                :message="form.errors.app_setting_id" /> -->

                            <!-- <DropdownInputObject label="BU Assigned" v-model="form.bu_assigned" :options="listOfBu"
                                placeholder="Click to select BU assigned" :message="form.errors.bu_assigned" /> -->
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-4 sm:px-8 py-4 flex justify-between items-center flex-shrink-0">
                    <div class="flex items-center">
                        <p class="text-xs">
                            Updated By : {{ props.user.created_by }}
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" @click="closeModal" class="closeButton group">
                            <div class="flex justify-center items-center gap-2">
                                <span class="transition-transform duration-300 group-hover:rotate-180">
                                    <svg-icon type="mdi" :path="mdiClose" class="w-5 h-5" />
                                </span>
                                Close
                            </div>
                        </button>
                        <button type="submit" :disabled="form.processing" class="submitButton group">
                            <div class="flex justify-center items-center gap-2">
                                <span class="transition-transform duration-300 group-hover:rotate-405">
                                    <svg-icon type="mdi" :path="mdiNavigationVariantOutline" class="w-5 h-5" />
                                </span>
                                <span v-if="form.processing">Saving...</span>
                                <span v-else>Save Changes</span>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</template>

<script setup>
import { computed, ref, watch, onMounted } from "vue";
import { route } from "../../../../vendor/tightenco/ziggy/src/js";
import TextInput from "../../Pages/Components/TextInput.vue";
import { useForm, usePage } from "@inertiajs/vue3";
import ToastAlertWarning from "../../Pages/Components/ToastAlertWarning.vue";
import SvgIcon from "@jamescoyle/vue-icon";
import { mdiClose, mdiNavigationVariantOutline } from "@mdi/js";
import DropdownInput from "../../Pages/Components/DropdownInput.vue";
import DropdownInputObject from "../../Pages/Components/DropdownInputObject.vue";
import axios from "axios";

const props = defineProps({
    show: Boolean,
    user: Object,
    appSettingOptions: { type: Array, default: () => [] },
});

const page = usePage();

const form = useForm({
    name: null,
    username: null,
    password: null,
    password_confirmation: null,
    role: null,
    status: null,
    bu_assigned: null,
    app_setting_id: null,
});

const modalLoading = ref(false);

const showImage = ref(false);
const employeeData = ref({});

const profilePhotoUrl = computed(() => {

    const fetchedName = employeeData.value?.employee_name;
    let nameToUse = fetchedName;

    if (!nameToUse) {
        // Fallback to props.user.name, but filter out system names
        const candidateName = props.user?.name;
      
        const isSystemAccount = 
            candidateName === page.props.tenant || 
            candidateName === 'Administrator' ||
            !candidateName.includes(' '); 
            
        if (candidateName && !isSystemAccount) {
            nameToUse = candidateName;
        }
    }

    if (!nameToUse) return "";
    
    return `${route("userPhoto", { tenant: page.props.tenant })}?name=${encodeURIComponent(nameToUse)}&t=${Date.now()}`;
});

const emit = defineEmits(["close", "closeSuccess"]);
const closeModal = () => {
    emit("close");
};

const userInitials = computed(() => {
    return (
        props.user.name
            ?.split(" ")
            .map((name) => name[0])
            .join("")
            .slice(1)
            .toUpperCase() || ""
    );
});

// fetching the user bu assign 
const getUserBuAssign = async () => {
    try {
        const response = await axios.get(route('getUserBuAssign', { tenant: page.props.tenant }), {
            params: {
                bu_assigned: props.user.bu_assign
            }
        });
        if (response.data.success) {
            const selectedBu = response.data.data;

            const match = listOfBu.value.find(bu => bu.value === selectedBu.value);
            form.bu_assigned = (match || selectedBu).value;

        }
    } catch (error) {
        console.error('Unable to fetch user BU assigned', error);
    }

};

// Fetching Bu list 
const listOfBu = ref([]);

const fetchBuList = async () => {
    try {
        const response = await axios.get(route('fetchBuList', { tenant: page.props.tenant }));
        if (response.data.success) {
            listOfBu.value = response.data.data
        }
    } catch (error) {
        console.error('Error fetching BU list', error);
    }
};

onMounted(async () => {
    await fetchBuList();
    await getUserBuAssign();
});

// fetching users data from hrms api 
const fetchEmployeeData = async () => {
    try {
        // We need to decide what to search for.
        // If props.user.name is "Feedmill", we might want to search by something else?
        // But what? Username?
        // If the user record has `username` that matches `employee_id` or similar?
        
        // Let's stick to name for now, but maybe try username if name fails?
        // Or if the user record has a dedicated `employee_name` field that is different from `name`?
        // Looking at User model might help, but for now let's assume `name` is the key.
        
        const response = await axios.get(
            route('getEmployeeData', { tenant: page.props.tenant, name: props.user.name })
        );
        if (response.data && (response.data.data?.employee?.length > 0 || response.data.employee?.length > 0)) {
             // Handle different API structures
            const employees = response.data.data?.employee || response.data.employee;
            employeeData.value = employees[0];

            // Prefill form fields
            form.name = employeeData.value.employee_name;
            form.username = props.user.username;
            form.role = props.user.role;
            form.status = props.user.status;
            form.app_setting_id = props.user.app_setting_id;
            
            // Re-enable image showing because now we have the correct employee name to fetch the photo
            showImage.value = true;
        } else {
            console.warn("No employee data found for this user name: " + props.user.name);
            
            // FALLBACK: Try searching by username if it looks like a name?
            // Or just clear data.
            employeeData.value = {}; 
            
            // Do NOT enable showImage if we didn't find an employee.
            // showImage.value = false; // It is already false by default now.
            
            form.username = props.user.username;
            form.role = props.user.role;
            form.status = props.user.status;
        }
        modalLoading.value = false;
    } catch (err) {
        console.error("Error fetching employee data:", err);
        modalLoading.value = false;
    }
};

watch(
    () => props.show,
    (newVal, oldVal) => {
        if (newVal) {
            showImage.value = true;
            modalLoading.value = true;
            fetchEmployeeData();
        }
    },
    { immediate: true } // This ensures that it runs once on mount
);

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
    // Get raw form data as a plain object
    const payload = form.data();

    // Remove password fields if they are empty
    if (!payload.password) {
        delete payload.password;
        delete payload.password_confirmation;
    }

    form.put(route("updateUser", { tenant: page.props.tenant, id: props.user.id }), {
        preserveScroll: true,
        data: payload,
        onSuccess: () => {
            form.reset("password", "password_confirmation"); // Reset only password fields
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

const formattedBunit = computed(() => {
    return employeeData.value.employee_bunit === "HO"
        ? "Head Office"
        : employeeData.value.employee_bunit === "ICM"
            ? "Island City Mall"
            : employeeData.value.employee_bunit === "Acctng / Franchise"
                ? "Accounting/Franchise"
                : employeeData.value.employee_bunit === "CK - Admin"
                    ? "Admin-Chowking"
                    : employeeData.value.employee_bunit === "Aggregates & Cons"
                        ? "Aggregates and Construction Materials"
                        : employeeData.value.employee_bunit === "AMall"
                            ? "ASC Main"
                            : employeeData.value.employee_bunit === "ASC Tal"
                                ? "Alturas Talibon"
                                : employeeData.value.employee_bunit === "DSG"
                                    ? "Distribution Sales Group"
                                    : employeeData.value.employee_bunit === "ASC Tubigon"
                                        ? "Alturas Tubigon"
                                        : employeeData.value.employee_bunit === "ASC Tech - Tagb"
                                            ? "ASC Tech - Tagbilaran"
                                            : employeeData.value.employee_bunit === "SlaughterhouseII"
                                                ? "MFI Slaughterhouse & Meat Cutting Plant II"
                                                : employeeData.value.employee_bunit === "BS Commi"
                                                    ? "Bakeshop Commissary"
                                                    : employeeData.value.employee_bunit === "ORPB"
                                                        ? "Oceanica Resort Panglao Bohol"
                                                        : employeeData.value.employee_bunit === "Cold Storage"
                                                            ? "Cold Storage Commissary"
                                                            : employeeData.value.employee_bunit === "BMC"
                                                                ? "Bohol Milkfish Corporation"
                                                                : employeeData.value.employee_bunit === "Marcela Farms Lab"
                                                                    ? "Marcela Farms Laboratory"
                                                                    : employeeData.value.employee_bunit === "Catagbacan"
                                                                        ? "Catagbacan Farms"
                                                                        : employeeData.value.employee_bunit === "COL-C"
                                                                            ? "Colonnade - Colon"
                                                                            : employeeData.value.employee_bunit === "COL-M"
                                                                                ? "Colonnade - Mandaue"
                                                                                : employeeData.value.employee_bunit === "Commi Compound"
                                                                                    ? "Commissary Compound"
                                                                                    : employeeData.value.employee_bunit === "TPF"
                                                                                        ? "The Prawn Farm"
                                                                                        : employeeData.value.employee_bunit === "FS Commi"
                                                                                            ? "Food Service Commissary"
                                                                                            : employeeData.value.employee_bunit === "Glass Tagb"
                                                                                                ? "Glass Service Tagbilaran"
                                                                                                : employeeData.value.employee_bunit === "Glass Tal"
                                                                                                    ? "Glass Service Talibon"
                                                                                                    : employeeData.value.employee_bunit === "Rizal Breeder"
                                                                                                        ? "MFI Poultry Broiler - Rizal Breeder"
                                                                                                        : employeeData.value.employee_bunit === "GW - AMall"
                                                                                                            ? "Greenwhich Alturas Mall"
                                                                                                            : employeeData.value.employee_bunit === "GW - ICM"
                                                                                                                ? "Greenwhich ICM"
                                                                                                                : employeeData.value.employee_bunit === "Group 1"
                                                                                                                    ? "Group 1 - Grocery Group Management"
                                                                                                                    : employeeData.value.employee_bunit === "Group 2"
                                                                                                                        ? "Group 2 - Home and Fashion, Fixrite"
                                                                                                                        : employeeData.value.employee_bunit === "Group 3"
                                                                                                                            ? "Group 3 - Food Group Management"
                                                                                                                            : employeeData.value.employee_bunit === "Group 4"
                                                                                                                                ? "Group 4 - Farms"
                                                                                                                                : employeeData.value.employee_bunit === "Ortigas"
                                                                                                                                    ? "Ortigas Farms"
                                                                                                                                    : employeeData.value.employee_bunit === "PK"
                                                                                                                                        ? "Peanut Kisses"
                                                                                                                                        : employeeData.value.employee_bunit === "WDG"
                                                                                                                                            ? "Wholesale Distribution Group"
                                                                                                                                            : employeeData.value.employee_bunit === "JB - AMall"
                                                                                                                                                ? "Jollibee Alturas Mall"
                                                                                                                                                : employeeData.value.employee_bunit === "JB - PM"
                                                                                                                                                    ? "Jollibee Plaza Marcela"
                                                                                                                                                    : employeeData.value.employee_bunit === "JB - Alta Citta"
                                                                                                                                                        ? "Jollibee Alta Citta"
                                                                                                                                                        : employeeData.value.employee_bunit === "JB - ICM"
                                                                                                                                                            ? "Jollibee ICM"
                                                                                                                                                            : employeeData.value.employee_bunit === "RR - Tubigon"
                                                                                                                                                                ? "Red Ribbon Tubigon"
                                                                                                                                                                : employeeData.value.employee_bunit === "Maribojoc"
                                                                                                                                                                    ? "Maribojoc Farms"
                                                                                                                                                                    : employeeData.value.employee_bunit === "Dressing Plant"
                                                                                                                                                                        ? "MFI Dressing Plant"
                                                                                                                                                                        : employeeData.value.employee_bunit === "Meat Processing"
                                                                                                                                                                            ? "MFI Meat Processing"
                                                                                                                                                                            : employeeData.value.employee_bunit === "Piggery (Cortes)"
                                                                                                                                                                                ? "MFI Piggery (Cortes)"
                                                                                                                                                                                : employeeData.value.employee_bunit === "Canhayupon"
                                                                                                                                                                                    ? "MFI Poultry Broiler - Canhayupon Breeder"
                                                                                                                                                                                    : employeeData.value.employee_bunit === "Lapsaon"
                                                                                                                                                                                        ? "MFI Poultry Broiler - Lapsaon Breeder"
                                                                                                                                                                                        : employeeData.value.employee_bunit === "Growout"
                                                                                                                                                                                            ? "MFI Poultry Broiler - Growout"
                                                                                                                                                                                            : employeeData.value.employee_bunit === "Hatchery"
                                                                                                                                                                                                ? "MFI Poultry Broiler - Hatchery"
                                                                                                                                                                                                : employeeData.value.employee_bunit === "Bilar Breeder"
                                                                                                                                                                                                    ? "MFI Poultry Broiler - Bilar Breeder"
                                                                                                                                                                                                    : employeeData.value.employee_bunit === "Poultry Layer"
                                                                                                                                                                                                        ? "MFI Poultry Layer"
                                                                                                                                                                                                        : employeeData.value.employee_bunit === "Repacking Srvcs"
                                                                                                                                                                                                            ? "MFI Repacking Services"
                                                                                                                                                                                                            : employeeData.value.employee_bunit === "Tilapia Breeder"
                                                                                                                                                                                                                ? "MFI Tilapia Breeder"
                                                                                                                                                                                                                : employeeData.value.employee_bunit === "Piggery (Alicia)"
                                                                                                                                                                                                                    ? "MFI Piggery (Untaga, Alicia)"
                                                                                                                                                                                                                    : employeeData.value.employee_bunit === "Noodles"
                                                                                                                                                                                                                        ? "Noodles Factory"
                                                                                                                                                                                                                        : employeeData.value.employee_bunit === "Tipcan"
                                                                                                                                                                                                                            ? "Tipcan Farms"
                                                                                                                                                                                                                            : employeeData.value.employee_bunit === "PM"
                                                                                                                                                                                                                                ? "Plaza Marcela"
                                                                                                                                                                                                                                : employeeData.value.employee_bunit === "RR - ICM"
                                                                                                                                                                                                                                    ? "Red Ribbon - ICM"
                                                                                                                                                                                                                                    : employeeData.value.employee_bunit;
});
</script>
