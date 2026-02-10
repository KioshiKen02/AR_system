<template>
    <Transition name="modal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 backdrop-blur-sm bg-black/60">
            <!-- Modal Container -->
            <div
                class="w-full max-w-4xl overflow-hidden rounded-2xl bg-[var(--color-bg-secondary)] border border-[var(--color-border)]">
                <!-- Content -->
                <div class="relative p-6">
                    <!-- Close X Icon -->
                    <button type="button" @click="closeModal"
                        class="absolute top-4 right-4 text-[var(--color-text-primary)] hover:text-red-500 transition group"
                        title="Close">
                        <div class="flex justify-center items-center gap-2">
                            <span class="transition-transform duration-300 group-hover:rotate-180">
                                <svg-icon type="mdi" :path="mdiClose" />
                            </span>
                        </div>
                    </button>
                    <!-- Header -->
                    <div class="mb-6 text-center">
                        <h2 class="text-2xl font-bold text-[var(--color-text-primary)] tracking-wide">
                            ACCOUNT CODE LIST
                        </h2>
                        <div
                            class="mt-2 h-0.5 bg-gradient-to-r from-transparent via-[var(--color-border)] to-transparent">
                        </div>
                    </div>

                    <!-- Search Input -->
                    <div class="mb-4 relative">
                        <input v-model="searchQuery" type="text" placeholder="Search..." ref="searchInput"
                            class="w-full rounded-md px-4 py-2 text-[var(--color-text-primary)] border border-[var(--color-border)]"
                            :class="{
                                '!border-red-400 !ring-2 !ring-red-500/50 bg-red-900/10':
                                    filteredData.length === 0,
                            }" />
                        <button v-if="searchQuery" @click="clearSearch"
                            class="absolute top-1/2 right-2 transform -translate-y-1/2 text-[var(--color-text-primary)] hover:text-red-500">
                            <svg-icon type="mdi" :path="mdiClose" class="w-5 h-5 hover:text-red-500" />
                        </button>
                        <div v-else
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-[var(--color-text-secondary)]">
                            <svg-icon type="mdi" :path="mdiMagnify" size="20" />
                        </div>
                    </div>

                    <!-- TABLE -->
                    <div
                        class="w-full rounded-xl overflow-hidden border border-[var(--color-border)] backdrop-blur-sm pl-2">
                        <div class="sticky top-0 z-10 pr-2">
                            <table class="w-full text-[var(--color-text-primary)]">
                                <thead class="border-b border-[var(--color-border)]/50">
                                    <tr>
                                        <th class="px-5 py-3.5 text-left w-[40%]">
                                            Account Code
                                        </th>
                                        <th class="px-5 py-3.5 text-left w-[60%]">
                                            Name
                                        </th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                        <div class="relative overflow-hidden">
                            <div
                                class="max-h-72 overflow-y-auto relative scrollbar-thin scrollbar-thumb-[var(--color-scrollbar-track)] scrollbar-track-[var(--color-primary)]/20 scrollbar-stable [scrollbar-gutter:stable] scrollbar-thumb-rounded-full">
                                <table class="w-full text-[var(--color-text-primary)] text-sm">
                                    <!-- Loading State -->
                                    <tbody v-if="isLoading">
                                        <tr>
                                            <td colspan="5" class="text-center py-8">
                                                <div class="flex justify-center items-center">
                                                    <svg width="30" height="30" viewBox="0 0 24 24"
                                                        xmlns="http://www.w3.org/2000/svg" fill="var(--color-icon)">
                                                        <rect class="spinner_jCIR" x="1" y="6" width="2.8"
                                                            height="12" />
                                                        <rect class="spinner_jCIR spinner_upm8" x="5.8" y="6"
                                                            width="2.8" height="12" />
                                                        <rect class="spinner_jCIR spinner_2eL5" x="10.6" y="6"
                                                            width="2.8" height="12" />
                                                        <rect class="spinner_jCIR spinner_Rp9l" x="15.4" y="6"
                                                            width="2.8" height="12" />
                                                        <rect class="spinner_jCIR spinner_dy3W" x="20.2" y="6"
                                                            width="2.8" height="12" />
                                                    </svg>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>

                                    <!-- Data Rows -->
                                    <tbody v-else class="divide-y divide-[var(--color-border)]/50 rounded-xl">
                                        <tr v-for="(
code, index
                                            ) in filteredData" :key="index" @click="submitSelected(code)"
                                            class="rounded-xl hover:bg-[var(--color-primary)]/20 transition-colors duration-150 group cursor-pointer">
                                            <td class="px-5 py-2 font-medium w-[40%]">
                                                {{ code.gl_account_navcode }}
                                            </td>
                                            <td class="px-5 py-2 w-[60%]">
                                                {{ code.gl_account_name }}
                                            </td>
                                        </tr>

                                        <!-- Empty State -->
                                        <tr v-if="
                                            filteredData.length === 0 &&
                                            !isLoading
                                        ">
                                            <td colspan="5" class="px-5 py-6 text-center">
                                                <div
                                                    class="flex flex-col items-center justify-center text-[var(--color-text-primary)]">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mb-2"
                                                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="1.5"
                                                            d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                    </svg>
                                                    <p class="font-medium">
                                                        No Account Code found
                                                    </p>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { usePage } from "@inertiajs/vue3";
import { mdiClose, mdiMagnify } from "@mdi/js";
import { nextTick, ref, watch } from "vue";

const props = defineProps({
    show: Boolean,
});

const emit = defineEmits(["close", "submit"]);

const glAccCodeResults = ref([]);
const selectedAccCode = ref(null);
const selectedAccName = ref(null);
const isLoading = ref(false);
const searchQuery = ref("");
const filteredData = ref([]);
const searchInput = ref(null);
let debounceTimeout = null;

const page = usePage();
const appName = page.props.appName;

watch(
    () => props.show,
    async (newCode) => {
        if (newCode) {
            try {
                isLoading.value = true;
                selectedAccCode.value = null;

                //DYNAMIC API LINK
                let baseUrl = "";
                switch (appName) {
                    case "Bilar Breeder Local":
                        baseUrl = "http://172.16.43.148/centralized_invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=13";
                        break;
                    case "Bilar Breeder":
                        baseUrl = "http://172.16.220.1:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=13";
                        break;
                    case "Gp Jagna":
                        baseUrl = "http://172.16.220.1:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=50";
                        break;
                    case "Ice Plant":
                        baseUrl = "http://172.16.184.49:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=25";
                        break;
                    case "Peanut Kisses":
                        baseUrl = "http://172.16.184.49:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=26";
                        break;
                    case "Cortes Poultry":
                        baseUrl = "http://172.16.192.68:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=12";
                        break;
                    case "Cortes Piggery":
                        baseUrl = "http://172.16.192.68:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=11";
                        break;
                    case "Canhayupon Breeder":
                        baseUrl = "http://172.16.220.223:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=15";
                        break;
                    case "Bilar Hatchery":
                        baseUrl = "http://172.16.219.200:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=14";
                        break;
                    case "Lapsaon Breeder":
                        baseUrl = "http://172.16.220.222:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=16";
                        break;
                    case "Rizal Breeder":
                        baseUrl = "http://172.16.217.11:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=43";
                        break;
                    // ubay server 
                    case "Feedmill":
                        baseUrl = "http://172.16.105.1:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=19";
                        break;
                    case "Growout":
                        baseUrl = "http://172.16.105.1:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=20";
                        break;
                    case "Cortes Fertilizer":
                        baseUrl = "http://172.16.105.1:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=42";
                        break;
                    case "Ubay Fertilizer":
                        baseUrl = "http://172.16.105.1:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=22";
                        break;
                    case "Piggery Untaga":
                        baseUrl = "http://172.16.105.1:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=23";
                        break;
                    case "Demo Farm":
                        baseUrl = "http://172.16.105.1:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=21";
                        break;
                    case "Dressing Plant":
                        baseUrl = "http://172.16.105.1:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=17";
                        break;
                    case "Farmers Market":
                        baseUrl = "http://172.16.105.1:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=41";
                        break;
                    case "Meat Processing":
                        baseUrl = "http://172.16.105.1:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=46";
                        break;
                    case "Rendering":
                        baseUrl = "http://172.16.105.1:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=18";
                        break;
                    default:
                        console.error(`Unknown app name: ${appName}`);
                }
                const response = await axios.get(
                    `${baseUrl}`
                );

                // Handle different response formats
                glAccCodeResults.value = Array.isArray(
                    response.data.gl_account_code
                )
                    ? response.data.gl_account_code.map((code) => ({
                        gl_account_navcode: code.gl_account_navcode,
                        gl_account_name: code.gl_account_name,
                    }))
                    : response.data.gl_account_code?.data?.map((code) => ({
                        gl_account_navcode: code.gl_account_navcode,
                        gl_account_name: code.gl_account_name,
                    })) || [];

                filteredData.value = glAccCodeResults.value;
            } catch (error) {
                console.error("Error fetching account codes:", error);
                glAccCodeResults.value = [];
                filteredData.value = [];
            } finally {
                isLoading.value = false;
            }
        } else {
            glAccCodeResults.value = [];
            filteredData.value = [];
        }
    },
    { immediate: true }
);

const handleCheckboxChange = (code) => {
    if (selectedAccCode.value === code.docunumber) {
        selectedAccCode.value = null;
        selectedAccName.value = null;
        selectedDate.value = null;
    } else {
        selectedAccCode.value = code.gl_account_navcode;
        selectedAccName.value = code.gl_account_name;
    }
};
const clearSearch = () => {
    searchQuery.value = ""; // Clear search input

    nextTick(() => {
        searchInput.value?.focus();
    });
};

watch(
    () => searchQuery.value,
    (query) => {
        isLoading.value = true;
        if (debounceTimeout) clearTimeout(debounceTimeout);

        debounceTimeout = setTimeout(() => {
            if (!query.trim()) {
                filteredData.value = glAccCodeResults.value;
            } else {
                filteredData.value = glAccCodeResults.value.filter((code) =>
                    code.gl_account_name
                        ?.toString()
                        .toLowerCase()
                        .includes(query.toLowerCase())
                );
            }
            isLoading.value = false;
        }, 400); // 400ms debounce
    }
);

const submitSelected = (code) => {
    emit("submit", {
        listaccCode: code.gl_account_navcode,
        accName: code.gl_account_name,
    });
};

const closeModal = () => {
    emit("close");
};
</script>
<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>
