<template>
    <div>
        <Head :title="` | ${$page.component}`" />

        <div class="flex justify-between pb-3 pt-1">
            <div class="w-1/4">
                <h1 class="text-2xl font-bold text-[var(--color-text-primary)]">
                    Announcements
                </h1>
            </div>

            <div class="w-1/2 flex gap-2">
                <button
                    @click="openModal()"
                    class="bg-[var(--color-primary)] text-white px-4 py-2 rounded-lg hover:opacity-90 transition-opacity flex items-center gap-2 whitespace-nowrap"
                >
                    <svg-icon type="mdi" :path="mdiPlus" size="20" />
                    Add Announcement
                </button>

                <div class="relative w-full">
                    <input
                        type="search"
                        id="Search"
                        v-model="search"
                        placeholder=" "
                        class="peer"
                        ref="searchInput"
                        autocomplete="off"
                    />
                    <button
                        v-if="search"
                        @click="clearSearch"
                        class="absolute top-1/2 right-2 transform -translate-y-1/2 text-[var(--color-text-primary)] hover:text-red-500"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            class="w-5 h-5"
                        >
                            <path
                                d="M6 18L18 6M6 6l12 12"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                    </button>
                    <div
                        v-else
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-[var(--color-text-secondary)]"
                    >
                        <svg-icon type="mdi" :path="mdiMagnify" size="20" />
                    </div>
                    <label
                        for="Search"
                        class="absolute left-0 -top-2 rounded px-1 text-sm text-[var(--color-text-primary)] transition-all peer-placeholder-shown:top-2.5 peer-placeholder-shown:text-sm peer-placeholder-shown:text-[var(--color-text-primary)] peer-focus:-top-2 peer-focus:text-sm peer-focus:text-[var(--color-text-primary)] cursor-text"
                    >
                        Search Title / Message ...
                    </label>
                </div>
            </div>
        </div>

        <Transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <AnnouncementFormModal
                v-if="showModal"
                :show="showModal"
                :announcement="selectedAnnouncement"
                :tenants="props.tenants"
                @close="closeModal"
                @closeSuccess="closeSuccessModal"
            />
        </Transition>

        <ToastAlert :show="showToast" :message="toastMessage" />

        <Transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <ConfirmationDialog
                :show="showDialog"
                message="Are you sure about deleting this announcement?"
                @close="deleteAnnouncement"
            />
        </Transition>

        <div class="bg-[var(--color-bg-secondary)]/20 p-4 rounded-md shadow-[0_0_20px_var(--color-shadow)]/20 mt-4">
            <table class="w-full text-sm text-[var(--color-text-primary)] rounded-xl overflow-hidden mb-2">
                <thead class="sticky top-0 z-10">
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold tracking-wider">TITLE</th>
                        <th class="px-3 py-2 text-left font-semibold tracking-wider">DISPLAY</th>
                        <th class="px-3 py-2 text-left font-semibold tracking-wider">TARGET</th>
                        <th class="px-3 py-2 text-center font-semibold tracking-wider">STATUS</th>
                        <th class="px-3 py-2 text-left font-semibold tracking-wider">CREATED</th>
                        <th class="px-3 py-2 text-center font-semibold tracking-wider">ACTION</th>
                    </tr>
                </thead>

                <tbody v-if="isLoading">
                    <tr>
                        <td colspan="6" class="text-center py-8">
                            <div class="flex justify-center items-center">
                                <svg width="30" height="30" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="var(--color-icon)">
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

                <tbody v-else>
                    <tr
                        v-for="row in announcements.data"
                        :key="row.id"
                        class="hover:bg-[var(--color-primary)]/20 transition-colors duration-150 group h-10"
                    >
                        <td class="px-3 py-2 font-medium">
                            <div class="flex flex-col">
                                <span class="font-semibold">{{ row.title }}</span>
                                <span class="text-xs text-[var(--color-text-secondary)] line-clamp-2 whitespace-pre-line">{{ row.message }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-2">
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-if="row.show_banner"
                                    class="px-2 py-0.5 rounded-full text-xs bg-[var(--color-primary)]/20 text-[var(--color-text-primary)]"
                                    >Banner</span
                                >
                                <span
                                    v-if="row.show_modal"
                                    class="px-2 py-0.5 rounded-full text-xs bg-[var(--color-primary)]/20 text-[var(--color-text-primary)]"
                                    >Modal</span
                                >
                                <span
                                    v-if="row.is_dismissible"
                                    class="px-2 py-0.5 rounded-full text-xs bg-[var(--color-primary)]/20 text-[var(--color-text-primary)]"
                                    >Dismissible</span
                                >
                                <span
                                    v-if="!row.show_banner && !row.show_modal"
                                    class="px-2 py-0.5 rounded-full text-xs bg-red-500/20 text-red-500"
                                    >Hidden</span
                                >
                            </div>
                        </td>
                        <td class="px-3 py-2">
                            <span
                                v-if="row.applies_to_all"
                                class="px-2 py-0.5 rounded-full text-xs bg-[var(--color-primary)]/20 text-[var(--color-text-primary)]"
                                >All Tenants</span
                            >
                            <span
                                v-else
                                class="px-2 py-0.5 rounded-full text-xs bg-[var(--color-primary)]/20 text-[var(--color-text-primary)]"
                                >{{ getTargetLabel(row) }}</span
                            >
                        </td>
                        <td class="px-3 py-2 text-center">
                            <span class="inline-flex items-center">
                                <span class="relative flex h-2.5 w-2.5 mr-2">
                                    <span
                                        class="animate-ping absolute inline-flex h-full w-full rounded-full"
                                        :class="row.is_active ? 'bg-green-400' : 'bg-red-400'"
                                    ></span>
                                    <span
                                        class="relative inline-flex rounded-full h-2.5 w-2.5"
                                        :class="row.is_active ? 'bg-green-500' : 'bg-red-500'"
                                    ></span>
                                </span>
                                <span class="capitalize">
                                    {{ row.is_active ? "Active" : "Inactive" }}
                                </span>
                            </span>
                        </td>
                        <td class="px-3 py-2">
                            {{ formatDateTime(row.created_at) }}
                        </td>
                        <td class="px-3 py-2 text-center">
                            <div class="flex justify-center gap-2">
                                <button
                                    @click="openModal(row)"
                                    class="p-1.5 cursor-pointer rounded-lg transition-all duration-200 bg-[var(--color-primary)]/30 hover:bg-[var(--color-primary)]/50 hover:shadow-lg group-hover:opacity-100"
                                >
                                    <svg-icon type="mdi" :path="mdiPencil" class="w-4 h-4 text-[var(--color-primary)]" />
                                </button>

                                <button
                                    @click="openDeleteDialog(row)"
                                    class="p-1.5 cursor-pointer rounded-lg transition-all duration-200 bg-red-500/30 hover:bg-red-500/50 hover:shadow-lg group-hover:opacity-100"
                                >
                                    <svg class="w-4 h-4 text-red-600" fill="currentColor" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7h6"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="!isLoading && announcements.data.length === 0">
                        <td colspan="6" class="px-5 py-6 text-center">
                            <div class="flex flex-col items-center justify-center text-[var(--color-text-primary)]">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mb-2" fill="none" viewBox="0 0 24 24" stroke="var(--color-icon)">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                                <p class="font-medium">No data found</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="isLoading || announcements.data.length === 0" />
            <div v-else>
                <PaginationLinks :paginator="announcements" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, nextTick } from "vue";
import PaginationLinks from "./Components/PaginationLinks.vue";
import { router, usePage, Head } from "@inertiajs/vue3";
import { debounce } from "lodash";
import AnnouncementFormModal from "../Modals/MasterfileModals/AnnouncementFormModal.vue";
import { route } from "../../../vendor/tightenco/ziggy/src/js";
import ToastAlert from "./Components/ToastAlert.vue";
import { Transition } from "vue";
import ConfirmationDialog from "./Components/ConfirmationDialog.vue";
import { mdiMagnify, mdiPencil, mdiPlus } from "@mdi/js";
import SvgIcon from "@jamescoyle/vue-icon";

const props = defineProps({
    announcements: Object,
    searchTerm: String,
    tenants: Array,
});

const page = usePage();
const search = ref(props.searchTerm);
const showModal = ref(false);
const selectedAnnouncement = ref(null);
const showToast = ref(false);
const toastMessage = ref("");
const showDialog = ref(false);
const pendingDeleteID = ref(null);
const isLoading = ref(false);

const openModal = (announcement = null) => {
    selectedAnnouncement.value = announcement;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    selectedAnnouncement.value = null;
};

const closeSuccessModal = () => {
    showModal.value = false;
    selectedAnnouncement.value = null;
    showSuccessToast("Announcement saved successfully");
};

const showSuccessToast = (message) => {
    toastMessage.value = message;
    showToast.value = true;
    setTimeout(() => {
        showToast.value = false;
    }, 3000);
};

const openDeleteDialog = (announcement) => {
    pendingDeleteID.value = announcement.id;
    showDialog.value = true;
};

const deleteAnnouncement = async (confirmed) => {
    showDialog.value = false;
    if (confirmed && pendingDeleteID.value) {
        await router.delete(
            route("announcements.destroy", {
                tenant: page.props.tenant,
                announcement: pendingDeleteID.value,
            }),
            {
                onSuccess: () => {
                    showSuccessToast("Announcement deleted successfully");
                },
            }
        );
    }
    pendingDeleteID.value = null;
};

const searchInput = ref(null);
const clearSearch = () => {
    search.value = "";
    nextTick(() => {
        searchInput.value?.focus();
    });
};

const performSearch = debounce((q) => {
    router.get(
        route("announcements.index", { tenant: page.props.tenant }),
        { search: q },
        {
            preserveState: true,
            replace: true,
            onFinish: () => {
                isLoading.value = false;
            },
        }
    );
}, 500);

watch(search, (q) => {
    isLoading.value = true;
    performSearch(q);
});

const formatDateTime = (value) => {
    if (!value) return "";
    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
};

const getTargetLabel = (row) => {
    const targets = row.app_settings ?? [];
    if (!targets.length) return "Selected (0)";
    if (targets.length === 1) return targets[0].app_name;
    return `Selected (${targets.length})`;
};
</script>
