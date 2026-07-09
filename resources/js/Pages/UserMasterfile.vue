<template>
    <div>

        <Head :title="` | ${$page.component}`" />
        <div class="flex justify-between pb-3 pt-1">
            <div class="w-1/4">
                <div class="relative w-full">
                    <input type="search" id="Search Employee Name To Be Added" v-model="search_employee"
                        @input="onSearchEmployee" placeholder=" " class="peer" ref="searchEmployeeInput"
                        autocomplete="off" />
                    <!-- The sibling button's styles change based on the input's state -->
                    <button v-if="search_employee" @click="clearAddUserSearch"
                        class="absolute top-1/2 right-2 transform -translate-y-1/2 text-[var(--color-text-primary)] hover:text-red-500">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            class="w-5 h-5">
                            <path d="M6 18L18 6M6 6l12 12" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>
                    </button>
                    <div v-else class="absolute right-3 top-1/2 -translate-y-1/2 text-[var(--color-text-secondary)]">
                        <svg-icon type="mdi" :path="mdiMagnify" size="20" />
                    </div>

                    <label for="Search Employee Name To Be Added"
                        class="absolute left-0 -top-2 rounded px-1 text-sm text-[var(--color-text-primary)] transition-all peer-placeholder-shown:top-2.5 peer-placeholder-shown:text-sm peer-placeholder-shown:text-[var(--color-text-primary)] peer-focus:-top-2 peer-focus:text-sm peer-focus:text-[var(--color-text-primary)] cursor-text">
                        Search for Employee to Add ...
                    </label>
                    <!-- Dropdown for search results -->
                    <Transition enter-active-class="transition ease-out duration-200"
                        enter-from-class="opacity-0 translate-y-2" enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition ease-in duration-150"
                        leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 translate-y-2">
                        <div v-if="employeeResults.length && showDropdown"
                            class="absolute z-50 w-full mt-2 rounded-xl bg-[var(--color-bg-secondary)] border border-[var(--color-border)] shadow-lg shadow-[#131313a2] overflow-hidden">
                            <div
                                class="max-h-64 overflow-y-auto scrollbar-thin scrollbar-thumb-[var(--color-scrollbar-track)] scrollbar-thumb-rounded-full scrollbar-track-rounded-full">
                                <ul>
                                    <li v-for="employee in employeeResults" :key="employee.id" @click.prevent="
                                        !isAlreadyAdded(employee) &&
                                        selectEmployee(employee)
                                        " :class="[
                                            'px-4 py-2 text-[var(--color-text-primary)] hover:bg-[var(--color-primary)] text-sm transition-all duration-200 rounded-md mx-1 my-1 flex justify-between items-center',
                                            isAlreadyAdded(employee)
                                                ? 'cursor-not-allowed' // Disabled style for already added employees
                                                : 'cursor-pointer',
                                        ]">
                                        <span>{{
                                            employee.employee_name
                                            }}</span>

                                        <!-- Display "Already Added" indicator -->
                                        <span v-if="isAlreadyAdded(employee)" class="text-xs text-red-500 italic ml-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="green"
                                                class="size-6">
                                                <path fill-rule="evenodd"
                                                    d="M8.603 3.799A4.49 4.49 0 0 1 12 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 0 1 3.498 1.307 4.491 4.491 0 0 1 1.307 3.497A4.49 4.49 0 0 1 21.75 12a4.49 4.49 0 0 1-1.549 3.397 4.491 4.491 0 0 1-1.307 3.497 4.491 4.491 0 0 1-3.497 1.307A4.49 4.49 0 0 1 12 21.75a4.49 4.49 0 0 1-3.397-1.549 4.49 4.49 0 0 1-3.498-1.306 4.491 4.491 0 0 1-1.307-3.498A4.49 4.49 0 0 1 2.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 0 1 1.307-3.497 4.49 4.49 0 0 1 3.497-1.307Zm7.007 6.387a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </Transition>

                    <!-- No Results Message -->
                    <Transition enter-active-class="transition ease-out duration-200"
                        enter-from-class="opacity-0 translate-y-2" enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition ease-in duration-150"
                        leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 translate-y-2">
                        <div v-if="!employeeResults.length && showDropdown"
                            class="absolute z-50 w-full mt-2 rounded-xl bg-[var(--color-bg-secondary)] border border-[var(--color-border)] shadow-lg shadow-[#131313a2] overflow-hidden">
                            <div
                                class="max-h-64 overflow-y-auto scrollbar-thin scrollbar-thumb-[var(--color-scrollbar-track)] scrollbar-track-[var(--color-scrollbar-track)] scrollbar-stable [scrollbar-gutter:stable] scrollbar-thumb-rounded-full">
                                <ul>
                                    <div class="flex flex-col items-center justify-center p-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                            fill="var(--color-icon)" class="size-6">
                                            <path fill-rule="evenodd"
                                                d="M10.5 3.75a6.75 6.75 0 1 0 0 13.5 6.75 6.75 0 0 0 0-13.5ZM2.25 10.5a8.25 8.25 0 1 1 14.59 5.28l4.69 4.69a.75.75 0 1 1-1.06 1.06l-4.69-4.69A8.25 8.25 0 0 1 2.25 10.5Z"
                                                clip-rule="evenodd" />
                                        </svg>
                                        <p class="text-[var(--color-text-primary)] font-extrabold text-xs">
                                            No Results Found
                                        </p>
                                    </div>
                                </ul>
                            </div>
                        </div>
                    </Transition>
                    <!-- Show loading indicator -->
                    <Transition enter-active-class="transition ease-out duration-200"
                        enter-from-class="opacity-0 translate-y-2" enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition ease-in duration-150"
                        leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 translate-y-2">
                        <div v-if="showDropdown && loading"
                            class="absolute z-50 w-full mt-2 rounded-xl bg-[var(--color-bg-secondary)] border border-[var(--color-border)] shadow-lg shadow-[#131313a2] overflow-hidden">
                            <div
                                class="max-h-64 overflow-y-auto scrollbar-thin scrollbar-thumb-[var(--color-scrollbar-track)] scrollbar-track-[var(--color-scrollbar-track)] scrollbar-stable [scrollbar-gutter:stable] scrollbar-thumb-rounded-full">
                                <ul>
                                    <div class="flex flex-col items-center justify-center p-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                            fill="var(--color-icon)" class="size-6">
                                            <path fill-rule="evenodd"
                                                d="M10.5 3.75a6.75 6.75 0 1 0 0 13.5 6.75 6.75 0 0 0 0-13.5ZM2.25 10.5a8.25 8.25 0 1 1 14.59 5.28l4.69 4.69a.75.75 0 1 1-1.06 1.06l-4.69-4.69A8.25 8.25 0 0 1 2.25 10.5Z"
                                                clip-rule="evenodd" />
                                        </svg>
                                        <p class="text-[var(--color-text-primary)] font-extrabold text-xs">
                                            Loading...
                                        </p>
                                    </div>
                                </ul>
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
            <div class="w-1/3">
                <div class="relative w-full">
                    <input type="search" id="Search" v-model="search" placeholder=" " class="peer" ref="searchInput"
                        autocomplete="off" />
                    <button v-if="search" @click="clearSearch"
                        class="absolute top-1/2 right-2 transform -translate-y-1/2 text-[var(--color-text-primary)] hover:text-red-500">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            class="w-5 h-5">
                            <path d="M6 18L18 6M6 6l12 12" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>
                    </button>
                    <div v-else class="absolute right-3 top-1/2 -translate-y-1/2 text-[var(--color-text-secondary)]">
                        <svg-icon type="mdi" :path="mdiMagnify" size="20" />
                    </div>
                    <label for="Search"
                        class="absolute left-0 -top-2 rounded px-1 text-sm text-[var(--color-text-primary)] transition-all peer-placeholder-shown:top-2.5 peer-placeholder-shown:text-sm peer-placeholder-shown:text-[var(--color-text-primary)] peer-focus:-top-2 peer-focus:text-sm peer-focus:text-[var(--color-text-primary)] cursor-text">
                        Search Here ...
                    </label>
                </div>
            </div>
        </div>

        <!-- Employee Modal -->
        <Transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
            <AddUserMasterfileModal v-if="showModal" :show="showModal" :employeeData="selectedEmployee"
                :appSettingOptions="appSettingOptions" @close="closeModal" @closeSuccess="closeSuccessModal" />
        </Transition>
        <Transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
            <EditUserMasterfileModal v-if="showEditModal" :show="showEditModal" :user="selectedUser"
                :appSettingOptions="appSettingOptions" @close="closeEditModal" @closeSuccess="closeEditSuccessModal" />
        </Transition>
        <Transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
            <RoleModal v-if="showRoleModal" :show="showRoleModal" :userId="selectedRoleUser.id"
                :permissions="userPermissions" @close="closeRoleModal" @closeSuccess="closeRoleSuccessModal" />
        </Transition>
        <ToastAlert :show="showToast" :message="toastMessage" />
        <Transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
            <ConfirmationDialog :show="showDialog" message="Are you sure about deleting this user?"
                @close="handleConfirm" />
        </Transition>

        <div class="bg-[var(--color-bg-secondary)]/20 p-4 rounded-md shadow-[0_0_20px_var(--color-shadow)]/20 mt-4">
            <table class="w-full text-sm text-[var(--color-text-primary)] rounded-xl overflow-hidden mb-2">
                <!-- Modern Header -->
                <thead class="sticky top-0 z-10">
                    <tr>
                        <th class="px-3 py-2 w-[25%] text-left font-semibold tracking-wider">
                            NAME
                        </th>
                        <th class="px-3 py-2 w-[15%] text-left font-semibold tracking-wider">
                            USERNAME
                        </th>
                        <th class="px-3 py-2 w-[15%] text-left font-semibold tracking-wider">
                            DATABASE (WEB-APP SETTING)
                        </th>
                        <th class="px-3 py-2 w-[15%] text-center font-semibold tracking-wider">
                            ROLE
                        </th>
                        <th class="px-3 py-2 w-[15%] text-center font-semibold tracking-wider">
                            STATUS
                        </th>
                        <th class="px-3 py-2 w-[10%] text-center font-semibold tracking-wider">
                            HRMS BYPASS
                        </th>
                        <th class="px-3 py-2 w-[15%] text-center font-semibold tracking-wider">
                            ACTION
                        </th>
                    </tr>
                </thead>

                <!-- Loading State -->
                <tbody v-if="isLoading">
                    <tr>
                        <td colspan="7" class="text-center py-8">
                            <div class="flex justify-center items-center">
                                <svg width="30" height="30" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"
                                    fill="var(--color-icon)">
                                    <rect class="spinner_jCIR" x="1" y="6" width="2.8" height="12" />
                                    <rect class="spinner_jCIR spinner_upm8" x="5.8" y="6" width="2.8" height="12" />
                                    <rect class="spinner_jCIR spinner_2eL5" x="10.6" y="6" width="2.8" height="12" />
                                    <rect class="spinner_jCIR spinner_Rp9l" x="15.4" y="6" width="2.8" height="12" />
                                    <rect class="spinner_jCIR spinner_dy3W" x="20.2" y="6" width="2.8" height="12" />
                                </svg>
                            </div>
                        </td>
                    </tr>
                </tbody>

                <!-- MAIN BODY -->
                <tbody v-else>
                    <tr v-for="user in users.data" :key="user.id"
                        class="hover:bg-[var(--color-primary)]/20 transition-colors duration-150 group h-10">
                        <td class="px-3 py-2">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex-shrink-0 w-9 h-9 rounded-full bg-[var(--color-bg-avatar)] flex items-center justify-center text-white">
                                    <img v-if="
                                        showImage &&
                                        user.name !== 'Administrator'
                                    " :src="profilePhotoUrl(user.name)" alt="Employee Photo"
                                        class="rounded-full border border-[var(--color-border)] object-cover"
                                        @error="showImage = false" />
                                    <div v-else>
                                        {{ getFirstValidChar(user.name) }}
                                    </div>
                                </div>
                                <div>
                                    <div class="font-medium">
                                        {{ user.name }}
                                    </div>
                                    <div class="text-xs text-[var(--color-text-secondary)] mt-0.5">
                                        {{
                                            userPositions[user.id] ||
                                            "Loading..."
                                        }}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-2">
                            <span class="font-medium">{{ user.username }}</span>
                        </td>
                        <td class="px-3 py-2">
                            <span class="font-medium text-xs">
                                {{ user.app_settings && user.app_settings.length > 0 
                                    ? user.app_settings.map(s => s.app_name).join(', ') 
                                    : (user.app_setting?.app_name || 'N/A') }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium uppercase"
                                :class="{
                                    'bg-cyan-700 text-cyan-300':
                                        user.role === 'Admin',
                                    'bg-teal-700 text-teal-300':
                                        user.role === 'Invoicing',
                                    'bg-amber-700 text-amber-300':
                                        user.role === 'Accounting',
                                    'bg-pink-700 text-pink-300':
                                        user.role === 'Bookkeeper',
                                    'bg-emerald-700 text-emerald-300':
                                        user.role === 'IAD',
                                }">
                                {{ user.role }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <span class="inline-flex items-center" :title="user.status">
                                <span class="relative flex h-2.5 w-2.5 mr-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full" :class="user.status === 'Active'
                                            ? 'bg-green-400'
                                            : 'bg-red-400'
                                        "></span>
                                    <span class="relative inline-flex rounded-full h-2.5 w-2.5" :class="user.status === 'Active'
                                            ? 'bg-green-500'
                                            : 'bg-red-500'
                                        "></span>
                                </span>
                                <span class="capitalize">
                                    {{ user.status }}
                                </span>
                            </span>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <span v-if="user.allow_hrms_bypass" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 border border-red-200">
                                Yes
                            </span>
                            <span v-else class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 border border-gray-200">
                                No
                            </span>
                        </td>

                        <!-- Actions -->
                        <td class="px-3 py-2 text-center">
                            <div class="flex justify-center gap-2">
                                <!-- Edit -->
                                <button @click="openEditModal(user)"
                                    class="p-1.5 cursor-pointer rounded-lg transition-all duration-200 bg-[var(--color-primary)]/30 hover:bg-[var(--color-primary)]/50 hover:shadow-lg group-hover:opacity-100">
                                    <svg-icon type="mdi" :path="mdiPencil"
                                        class="w-4 h-4 text-[var(--color-primary)]" />
                                </button>

                                <!-- Delete -->
                                <button @click="deleteUser(user)"
                                    class="p-1.5 cursor-pointer rounded-lg transition-all duration-200 bg-red-500/30 hover:bg-red-500/50 hover:shadow-lg group-hover:opacity-100">
                                    <svg class="w-4 h-4 text-red-600" fill="currentColor" stroke="currentColor"
                                        stroke-width="2" viewBox="0 0 24 24">
                                        <path
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7h6"
                                            stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>

                    <!-- Empty State -->
                    <tr v-if="!isLoading && users.data.length === 0">
                        <td colspan="7" class="px-5 py-6 text-center">
                            <div class="flex flex-col items-center justify-center text-[var(--color-text-primary)]">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mb-2" fill="none"
                                    viewBox="0 0 24 24" stroke="var(--color-icon)">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p class="font-medium">No data found</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination Links -->
            <div v-if="isLoading || users.data.length === 0" />
            <div v-else>
                <PaginationLinks :paginator="users" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch, onMounted, nextTick, onUnmounted } from "vue";
import PaginationLinks from "./Components/PaginationLinks.vue";
import { router, usePage } from "@inertiajs/vue3";
import { debounce } from "lodash";
import axios from "axios";
import AddUserMasterfileModal from "../Modals/MasterfileModals/AddUserMasterfileModal.vue";
import EditUserMasterfileModal from "../Modals/MasterfileModals/EditUserMasterfileModal.vue";
import RoleModal from "../Modals/MasterfileModals/RoleModal.vue";
import { route } from "../../../vendor/tightenco/ziggy/src/js";
import ToastAlert from "./Components/ToastAlert.vue";
import { Transition } from "vue";
import ConfirmationDialog from "./Components/ConfirmationDialog.vue";
import { mdiMagnify, mdiPencil } from "@mdi/js";
import SvgIcon from "@jamescoyle/vue-icon";

const props = defineProps({
    users: Object,
    searchTerm: String,
    appSettings: Array,
});

const page = usePage();

const appSettingOptions = computed(() => {
    return props.appSettings?.map((s) => ({
        value: s.id,
        label: s.app_name,
    })) || [];
});

const search = ref(props.searchTerm);
const search_employee = ref("");
const employeeResults = ref([]);
const showDropdown = ref(false);
const loading = ref(false);
const showModal = ref(false);
const selectedEmployee = ref(null);
const showEditModal = ref(false);
const selectedUser = ref(null);
const showRoleModal = ref(false);
const selectedRoleUser = ref(null);
const userPermissions = ref({});
const showToast = ref(false);
const toastMessage = ref("");
const showDialog = ref(false);
const pendingDeleteID = ref(null);
const userPositions = ref({});
const positionCache = new Map();

const showImage = ref(true);
const profilePhotoUrl = (name) => {
    return route("userPhoto", { tenant: page.props.tenant, name: name });
};

// ... (Existing Position fetching logic) ...
// Copied similar logic for brevity, assuming mixins or composables would be better but keeping inline for now.
async function fetchUserPosition(user) {
    if (positionCache.has(user.id)) {
        userPositions.value = { ...userPositions.value, [user.id]: positionCache.get(user.id) };
        return;
    }
    try {
        const response = await axios.get(`http://172.16.161.34/api/farms/filter/employee/name?q=${encodeURIComponent(user.name)}`);
        const position = user.name === "Administrator" ? "Administrator" : (response.data.data.employee?.[0]?.employee_position || "Position not available");
        positionCache.set(user.id, position);
        userPositions.value = { ...userPositions.value, [user.id]: position };
    } catch (error) {
        positionCache.set(user.id, "Error loading position");
        userPositions.value = { ...userPositions.value, [user.id]: "Error loading position" };
    }
}

onMounted(() => {
    if (props.users?.data) {
        props.users.data.forEach(user => fetchUserPosition(user));
    }
});

watch(() => props.users?.data, (newUsers) => {
    if (newUsers) {
        newUsers.forEach(user => {
            if (!userPositions.value[user.id]) fetchUserPosition(user);
        });
    }
});

const onSearchEmployee = debounce(async () => {
    if (search_employee.value.trim()) {
        loading.value = true;
        showDropdown.value = true;
        try {
            const response = await axios.get(`http://172.16.161.34/api/farms/filter/employee/name?q=${search_employee.value}`);
            employeeResults.value = response.data.data.employee || [];
        } catch (error) {
            employeeResults.value = [];
        } finally {
            loading.value = false;
        }
    } else {
        employeeResults.value = [];
        showDropdown.value = false;
        loading.value = false;
    }
}, 500);

const showSuccessToast = (message) => {
    toastMessage.value = message;
    showToast.value = true;
    setTimeout(() => { showToast.value = false; }, 3000);
};

const selectEmployee = (employee) => {
    selectedEmployee.value = employee;
    showModal.value = true;
    showDropdown.value = false;
    search_employee.value = "";
    employeeResults.value = [];
};

const isAlreadyAdded = (employee) => {
    const usersArray = Array.isArray(props.users.data) ? props.users.data : [];
    return usersArray.some((user) => user.name.trim() === employee.employee_name.trim());
};

const openEditModal = (user) => {
    selectedUser.value = user;
    showEditModal.value = true;
};

const closeModal = () => showModal.value = false;
const closeSuccessModal = () => { showModal.value = false; showSuccessToast("User created successfully"); };
const closeEditModal = () => showEditModal.value = false;
const closeEditSuccessModal = () => { showEditModal.value = false; showSuccessToast("User updated successfully"); };

const clearAddUserSearch = () => {
    search_employee.value = "";
    employeeResults.value = [];
    showDropdown.value = false;
};

const searchInput = ref(null);
const clearSearch = () => {
    search.value = "";
    nextTick(() => searchInput.value?.focus());
};

const deleteUser = async (userID) => {
    pendingDeleteID.value = userID;
    showDialog.value = true;
};

const handleConfirm = async (confirmed) => {
    showDialog.value = false;
    if (confirmed && pendingDeleteID.value) {
        try {
            await router.delete(route("user-masterfile.destroy", { tenant: page.props.tenant, id: pendingDeleteID.value.id || pendingDeleteID.value }), {
                onSuccess: () => showSuccessToast("User deleted successfully"),
                onError: (errors) => console.error(errors),
            });
        } catch (error) {
            console.error(error);
        }
    }
    pendingDeleteID.value = null;
};

const getFirstValidChar = (name) => {
    if (!name) return "";
    const trimmedName = name.trim();
    for (let i = 0; i < trimmedName.length; i++) {
        if (trimmedName[i] !== " ") return trimmedName[i].toUpperCase();
    }
    return "";
};

const isLoading = ref(false);
const performSearch = debounce((q) => {
    router.get(route("user-masterfile.index", { tenant: page.props.tenant }), { search: q }, {
        preserveState: true, replace: true, onFinish: () => isLoading.value = false,
    });
}, 1000);

watch(search, (q) => {
    isLoading.value = true;
    performSearch(q);
});
</script>
