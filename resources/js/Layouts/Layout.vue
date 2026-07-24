<template>
    <Transition name="fade" appear>
        <div class="min-h-screen bg-[var(--color-bg-primary)] text-[var(--color-text-primary)]">
            <!-- Database Switching Loading Screen -->
            <div v-if="isSwitchingTenant" class="fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-black/80 backdrop-blur-sm text-white transition-opacity duration-300">
                <div class="relative flex items-center justify-center">
                    <div class="animate-spin rounded-full h-20 w-20 border-t-4 border-b-4 border-[var(--color-primary)] opacity-75"></div>
                    <div class="absolute animate-pulse">
                        <svg-icon type="mdi" :path="mdiDatabaseSync" class="w-8 h-8 text-white" />
                    </div>
                </div>
                <h2 class="text-2xl font-bold mt-6 tracking-wide">Switching Connection</h2>
                <p class="text-base text-gray-300 mt-3 animate-pulse">Connecting to {{ switchingToTenantName }}...</p>
                <p class="text-xs text-gray-400 mt-8">Please wait while we refresh your session data</p>
            </div>

            <Notifications v-if="showNotifications" :show="showNotifications" @close="showNotifications = false" />
            <Messages
                v-if="showMessages"
                :show="showMessages"
                :users="users"
                @close="showMessages = false"
                @refresh-users="fetchUsers"
            />
            <ToastAlertWarning :show="showToast" :message="toastMessage" />
            <AnnouncementPopup
                :show="showAnnouncementModal"
                :announcement="selectedAnnouncement"
                @close="closeAnnouncementModal"
                @dismiss="dismissAnnouncement(selectedAnnouncement)"
            />
            <div id="app" class="flex h-screen transition-all duration-200" :style="layoutShellStyle" v-cloak>
                    <!-- Sidebar -->
                    <aside
                        class="bg-[var(--color-bg-secondary)] border-r border-[var(--color-border)] backdrop-blur-sm flex flex-col z-30 relative shadow-[6px_0_12px_-2px_rgba(0,0,0,0.3)] transition-all duration-300 ease-in-out h-full"
                        :class="{
                            'w-60': !sidebarCollapsed,
                            'w-20': sidebarCollapsed,
                        }">
                    <!-- Header and Logo -->
                    <div class="h-[67px] p-2 backdrop-blur-sm flex items-center justify-between pl-4" :class="{
                        'pr-4': sidebarCollapsed,
                        'pr-6': !sidebarCollapsed,
                    }">
                        <img :src="'/storage/images/mflogo.png'" alt="Logo"
                            class="w-12 h-12 object-contain transition-all duration-300" />
                        <div class="text-xl font-extrabold whitespace-nowrap overflow-hidden transition-all duration-300 text-[var(--color-text-primary)]"
                            :class="{
                                'w-0 h-0 opacity-0': sidebarCollapsed,
                                'w-auto h-auto opacity-100 ml-2':
                                    !sidebarCollapsed,
                            }">
                            Marcela Farms
                        </div>
                    </div>
                    
                    <!-- Tenant Switcher Dropdown (Only visible if multiple accessible tenants exist) -->
                    <div v-if="availableTenants.length > 1 && !sidebarCollapsed" class="px-4 mb-2">
                         <div class="relative">
                            <button @click="toggleTenantDropdown" 
                                class="w-full flex items-center justify-between p-2 rounded-md bg-[var(--color-bg-primary)] border border-[var(--color-border)] text-xs font-semibold text-[var(--color-text-primary)] hover:border-[var(--color-primary)] transition-colors">
                                <span class="truncate">{{ appName['bu_name'] ?? 'Select Tenant' }}</span>
                                <ChevronDownIcon class="w-3 h-3 ml-1" />
                            </button>
                            
                            <div v-if="showTenantDropdown" class="absolute z-50 w-full mt-1 bg-[var(--color-bg-secondary)] border border-[var(--color-border)] rounded-md shadow-lg max-h-48 overflow-y-auto">
                                <a v-for="tenant in availableTenants" :key="tenant.id" 
                                   :href="`/${tenant.base_url}/dashboard`"
                                   @click="handleTenantSwitch(tenant)"
                                   class="block px-3 py-2 text-xs text-[var(--color-text-primary)] hover:bg-[var(--color-primary)] hover:text-white truncate transition-colors cursor-pointer">
                                    {{ tenant.app_name }}
                                </a>
                            </div>
                         </div>
                    </div>

                    <!-- Scrollable Menu Section -->
                    <div class="flex-1 pl-4 scrollbar-thin scrollbar-thumb-[var(--color-primary)]/50 scrollbar-track-[var(--color-scrollbar-track)] scrollbar-stable [scrollbar-gutter:stable] scrollbar-thumb-rounded-full scrollbar-track-rounded-full"
                        :class="{
                            'overflow-y-auto overflow-x-hidden pr-2':
                                !sidebarCollapsed,
                            'pr-4': sidebarCollapsed,
                        }">
                        <hr class="border-[var(--color-border)] mb-2" />

                        <!-- User Info -->
                        <ul>
                            <li v-for="(menuUser, indexUser) in user_menus" :key="indexUser"
                                @mouseenter="handleMouseEnter(indexUser)" @mouseleave="handleMouseLeave(indexUser)"
                                class="relative">
                                <button
                                    class="text-left rounded space-x-2 hover:bg-[var(--color-primary)] transition-all ease-linear duration-200 cursor-pointer w-full flex items-center justify-between p-1 py-2"
                                    :class="{
                                        'bg-[var(--color-primary)]':
                                            activeUserMenu === indexUser,
                                    }" @click="toggleUserSubMenu(indexUser)">
                                    <div
                                        class="flex-shrink-0 flex justify-between items-center w-10 h-10 rounded-full overflow-hidden bg-[var(--color-bg-avatar)] border-2 border-[var(--color-primary)]">
                                        <img v-if="showImage" :src="profilePhotoUrl" alt="User"
                                            class="rounded-full w-10 h-10 object-contain" @error="showImage = false" />
                                        <div v-else
                                            class="w-10 h-10 flex items-center justify-center text-white text-sm font-semibold rounded-full">
                                            {{ userInitials }}
                                        </div>
                                    </div>

                                    <div class="font-semibold text-left truncate text-[var(--color-text-primary)]"
                                        :class="{
                                            'w-0 opacity-0': sidebarCollapsed,
                                            'w-auto opacity-100':
                                                !sidebarCollapsed,
                                        }">
                                        {{
                                            props.auth.user.name === "Administrator" ? "Administrator" : firstName
                                        }}

                                    </div>

                                    <span :class="{
                                        'transition-transform duration-200': true,
                                        'rotate-180':
                                            activeUserMenu === indexUser,
                                        'rotate-0':
                                            activeUserMenu !== indexUser,
                                        'w-0 opacity-0': sidebarCollapsed,
                                        'w-auto opacity-100':
                                            !sidebarCollapsed,
                                    }">
                                        <ChevronDownIcon class="size-4 text-[var(--color-text-primary)]" />
                                    </span>
                                </button>

                                <!-- User Sub Menu -->
                                <transition enter-active-class="transition duration-300 ease-out"
                                    enter-from-class="opacity-0 transform -translate-y-2"
                                    enter-to-class="opacity-100 transform translate-y-0"
                                    leave-active-class="transition duration-200 ease-in"
                                    leave-from-class="opacity-100 transform translate-y-0"
                                    leave-to-class="opacity-0 transform -translate-y-2">
                                    <ul v-if="activeUserMenu === indexUser"
                                        class="bg-[var(--color-bg-secondary)] rounded-lg p-2 text-sm space-y-1" :class="{
                                            'absolute left-full top-0 ml-5 w-48 rounded-lg bg-[var(--color-bg-secondary)] border border-[var(--color-border)] overflow-hidden':
                                                sidebarCollapsed,
                                            'text-sm p-2 mt-1 relative':
                                                !sidebarCollapsed,
                                        }">
                                        <li v-for="(
subUser, subUserIndex
                                            ) in menuUser.subUserMenus" :key="subUserIndex">
                                            <Link :href="subUser.link"
                                                class="w-full text-left cursor-pointer block py-2 px-4 text-[var(--color-text-primary)] rounded hover:bg-[var(--color-primary)] relative overflow-hidden"
                                                :class="{
                                                    'bg-[var(--color-primary)] rounded':
                                                        activeUserSubmenu ===
                                                        subUser.link,
                                                }" :method="subUser.name === 'Logout'
                                                    ? 'post'
                                                    : ''
                                                    " :as="subUser.name === 'Logout'
                                                        ? 'button'
                                                        : 'a'
                                                        " @click="
                                                            handleLinkClick(
                                                                subUser,
                                                                indexUser,
                                                                subUser.link,
                                                                'Profile',
                                                                subUser.name
                                                            )
                                                            ">
                                                <span>{{ subUser.name }}</span>
                                            </Link>
                                        </li>
                                    </ul>
                                </transition>
                            </li>
                        </ul>

                        <hr class="border-[var(--color-border)] my-2" />

                        <div class="my-0.5 text-[var(--color-text-secondary)] font-semibold text-sm">
                            <span>MENU</span>
                        </div>

                        <!-- Navigation Menu -->
                        <nav class="flex-1">
                            <ul class="space-y-2">
                                <li>
                                    <Link :href="route('dashboard', { tenant: page.props.tenant })"
                                        class="text-left rounded p-2 flex items-center space-x-2 transition-all ease-linear duration-200 cursor-pointer relative overflow-hidden text-[var(--color-text-primary)] hover:text-[var(--color-text-primary)] hover:bg-[var(--color-primary)] group"
                                        :class="{
                                            'bg-[var(--color-primary)]':
                                                activeMenu === 'dashboard' ||
                                                (realActiveMenu === null &&
                                                    activeSubmenu === ''),
                                        }" @click="
                                            setActive('dashboard', 'Dashboard')
                                            ">
                                        <RectangleGroupIcon
                                            class="icon flex-shrink-0 text-[var(--color-icon)] transition-colors duration-200 group-hover:text-[var(--color-icon-hovered)]"
                                            :class="{
                                                'text-[var(--color-icon-hovered)]':
                                                    activeMenu ===
                                                    'dashboard' ||
                                                    (realActiveMenu === null &&
                                                        activeSubmenu === ''),
                                            }" />
                                        <span :class="{
                                            hidden: sidebarCollapsed,
                                        }">Dashboard</span>
                                    </Link>
                                </li>

                                <li v-for="(menu, index) in filteredMenus" :key="index"
                                    @mouseenter="handleMouseEnterMenu(index)" @mouseleave="handleMouseLeaveMenu(index)"
                                    class="relative">
                                    <button
                                        class="text-left rounded p-2 space-x-2 transition-all ease-linear duration-200 cursor-pointer w-full flex items-center justify-between relative overflow-hidden text-[var(--color-text-primary)] hover:bg-[var(--color-primary)] group"
                                        :class="{
                                            'bg-[var(--color-primary)]':
                                                activeMenu === index ||
                                                isSubmenuActive(index),
                                        }" @click="toggleSubMenu(index)">
                                        <div class="flex items-center">
                                            <component :is="iconComponents[menu.icon]" v-if="menu.icon"
                                                class="icon flex-shrink-0 text-[var(--color-icon)] transition-colors duration-200 group-hover:text-[var(--color-icon-hovered)]"
                                                :class="{
                                                    'text-[var(--color-icon-hovered)]':
                                                        activeMenu === index ||
                                                        isSubmenuActive(index),
                                                }" />
                                            <span :class="{
                                                hidden: sidebarCollapsed,
                                            }">{{ menu.name }}</span>
                                        </div>
                                        <span :class="{
                                            'transition-transform duration-200': true,
                                            'rotate-180':
                                                activeMenu === index,
                                            'rotate-0':
                                                activeMenu !== index,
                                            hidden: sidebarCollapsed,
                                        }">
                                            <ChevronDownIcon class="size-4" />
                                        </span>
                                    </button>

                                    <transition enter-active-class="transition duration-300 ease-out"
                                        enter-from-class="opacity-0 transform -translate-y-2"
                                        enter-to-class="opacity-100 transform translate-y-0"
                                        leave-active-class="transition duration-200 ease-in"
                                        leave-from-class="opacity-100 transform translate-y-0"
                                        leave-to-class="opacity-0 transform -translate-y-2">
                                        <ul v-if="activeMenu === index"
                                            class="bg-[var(--color-bg-secondary)] rounded-lg p-2 text-sm space-y-1"
                                            :class="{
                                                'absolute left-full top-0 ml-5 w-48 rounded-lg bg-[var(--color-bg-secondary)] border border-[var(--color-border)] backdrop-blur-sm overflow-hidden':
                                                    sidebarCollapsed,
                                                'text-sm p-2 mt-1 relative':
                                                    !sidebarCollapsed,
                                            }">
                                            <li>
                                                <div :class="{
                                                    hidden: !sidebarCollapsed,
                                                }">
                                                    <span
                                                        class="font-bold uppercase text-[var(--color-text-secondary)]">{{
                                                            menu.name }}</span>
                                                </div>
                                            </li>
                                            <li v-for="(
sub, subIndex
                                                ) in menu.subMenus" :key="subIndex">
                                                <Link :href="sub.link"
                                                    class="text-left py-2 px-4 rounded w-full flex relative overflow-hidden text-[var(--color-text-primary)] hover:bg-[var(--color-primary)]"
                                                    :class="{
                                                        'bg-[var(--color-primary)]':
                                                            activeSubmenu ===
                                                            sub.link,
                                                    }" @click="
                                                        setActiveSubmenu(
                                                            index,
                                                            sub.link,
                                                            menu.name,
                                                            sub.name
                                                        )
                                                        " v-if="canView(sub?.roleId)">
                                                    <ArrowTurnDownRightIcon class="submenuicon flex-shrink-0" />
                                                    <span>{{ sub.name }}</span>
                                                </Link>
                                            </li>
                                        </ul>
                                    </transition>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    <div class="p-4 shrink-0 space-y-2">
                        <button type="button" @click="showMessageModal()"
                            class="text-left rounded p-2 flex items-center space-x-2 transition-all ease-linear duration-200 cursor-pointer overflow-hidden relative text-[var(--color-text-primary)] hover:bg-[var(--color-primary)] group w-full">
                            <svg-icon type="mdi" :path="mdiMessage"
                                class="icon flex-shrink-0 text-[var(--color-icon)] transition-colors duration-200 group-hover:text-[var(--color-icon-hovered)]" />
                            <span :class="{ hidden: sidebarCollapsed }">Messages</span>

                            <span v-if="totalUnreadCount > 0"
                                class="absolute bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs transition-all"
                                :class="[
                                    sidebarCollapsed
                                        ? 'top-1 right-1'
                                        : 'top-3 right-3',
                                ]">
                                {{ totalUnreadCount }}
                            </span>
                        </button>
                        <button type="button" @click="showNotificationModal()"
                            class="text-left rounded p-2 flex items-center space-x-2 transition-all ease-linear duration-200 cursor-pointer overflow-hidden relative text-[var(--color-text-primary)] hover:bg-[var(--color-primary)] group w-full">
                            <svg-icon type="mdi" :path="mdiBell"
                                class="icon flex-shrink-0 text-[var(--color-icon)] transition-colors duration-200 group-hover:text-[var(--color-icon-hovered)]" />
                            <span :class="{ hidden: sidebarCollapsed }">Notifications</span>

                            <span v-if="unreadCount > 0"
                                class="absolute bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs transition-all"
                                :class="[
                                    sidebarCollapsed
                                        ? 'top-1 right-1'
                                        : 'top-3 right-3',
                                ]">
                                {{ unreadCount }}
                            </span>
                        </button>
                    </div>
                </aside>

                    <!-- Main content -->
                    <main class="flex-1 flex flex-col min-h-0 transition-all duration-300 ease-in-out">
                    <!-- Sticky Header -->
                    <header
                        class="sticky top-0 z-10 bg-[var(--color-bg-secondary)] backdrop-blur-sm p-2 border-b border-[var(--color-border)] shadow-[0_6px_12px_-2px_rgba(0,0,0,0.3)]">
                        <div class="flex justify-between items-center max-w-[1800px] mx-auto">
                            <div
                                class="relative flex justify-between w-full items-center px-1 pb-1 rounded-lg overflow-hidden">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 rounded-md flex-shrink-0 transition-all ease-linear duration-200 cursor-pointer hover:bg-[var(--color-primary)] group"
                                        @click="
                                            sidebarCollapsed = !sidebarCollapsed
                                            ">
                                        <Bars3Icon
                                            class="size-6 text-[var(--color-icon)] transition-colors duration-200 group-hover:text-[var(--color-icon-hovered)]" />
                                    </div>
                                    <h2 class="text-lg font-semibold text-[var(--color-text-primary)] truncate">
                                        {{ currentPageTitle }}
                                    </h2>
                                </div>
                                <div class="relative flex gap-10 items-center">
                                    <!-- Left Column: Day Strip + Date Info -->
                                    <!-- <div class="flex flex-col items-center gap-2"> -->
                                    <!-- Day Strip with Progress Indicator -->
                                    <!-- <div class="relative w-full flex justify-between px-1 gap-2">
                                            <div v-for="(day, index) in dayNames" :key="day" :ref="(el) =>
                                                (dayRefs[index] = el)
                                                "
                                                class="text-xs text-center font-medium flex-1 transition-all duration-300"
                                                :class="{
                                                    'text-[var(--color-text-primary)] font-semibold scale-110':
                                                        index ===
                                                        currentDayIndex,
                                                    'text-[var(--color-text-secondary)]':
                                                        index !==
                                                        currentDayIndex,
                                                }">
                                                {{ day }}
                                            </div> -->

                                    <!-- Highlight Bar -->
                                    <!-- <div class="absolute bottom-0 h-0.5 bg-[var(--color-primary)] transition-all duration-500 ease-out"
                                                :style="{
                                                    width: `${indicatorWidth}px`,
                                                    left: `${indicatorLeft}px`,
                                                }"></div>
                                        </div> -->

                                    <!-- Date and Week Number -->
                                    <!-- <div
                                            class="flex items-center gap-2 text-xs font-medium text-[var(--color-text-primary)]">
                                            <span>{{ formattedDate }}</span>
                                            <span class="h-1 w-1 rounded-full bg-[var(--color-text-primary)]"></span>
                                            <span>Week {{ weekNumber }}</span>
                                        </div>
                                    </div> -->

                                    <span class="text-md font-semibold"> {{ appName['bu_code'] ?? 'Local' }} - {{
                                        appName['bu_name'] ?? 'BU'
                                        }}</span>


                                    <!-- Right Column: Clock + Location -->
                                    <div class="flex flex-col items-center px-1">
                                        <!-- Live Clock -->
                                        <div class="relative flex items-center">
                                            <!-- Clock Digits -->
                                            <div
                                                class="text-2xl font-mono font-bold text-[var(--color-text-primary)] tracking-tighter">
                                                <span class="inline-block min-w-[1.5rem] text-center">{{ hours }}</span>
                                                <span class="text-[var(--color-primary)] mx-1">:</span>
                                                <span class="inline-block min-w-[1.5rem] text-center">{{ minutes
                                                }}</span>
                                                <span class="text-[var(--color-primary)] mx-1">:</span>
                                                <span class="inline-block min-w-[1.5rem] text-center">{{ seconds
                                                }}</span>
                                            </div>

                                            <!-- AM/PM beside the clock -->
                                            <div class="ml-2 text-sm font-medium text-[var(--color-primary)]/90">
                                                {{ ampm }}
                                            </div>
                                        </div>

                                        <!-- Location Tags -->
                                        <div class="flex gap-2">
                                            <!-- Bohol -->
                                            <div
                                                class="text-[10px] font-medium text-[var(--color-text-primary)] flex items-center gap-1">
                                                <ClockIcon class="h-3 w-3 text-[var(--color-icon)]" />
                                                Bohol
                                            </div>

                                            <!-- Tagbilaran -->
                                            <div
                                                class="text-[10px] font-medium text-[var(--color-text-primary)] flex items-center gap-1">
                                                <ClockIcon class="h-3 w-3 text-[var(--color-icon)]" />
                                                Tagbilaran
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </header>

                    <!-- Content Area -->
                    <div
                        class="flex-1 overflow-y-auto pl-5 pr-2 pt-2 pb-6 scrollbar-thin rounded-xl scrollbar-thumb-[var(--color-scrollbar-track)] scrollbar-track-[var(--color-scrollbar-track)] scrollbar-stable [scrollbar-gutter:stable] scrollbar-thumb-rounded-full scrollbar-track-rounded-full">
                        <div class="max-w-[1800px] mx-auto">
                            <slot />
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import {
    computed,
    provide,
    ref,
    onMounted,
    onBeforeUnmount,
    watch,
    nextTick,
    onUnmounted,
} from "vue";
import { usePage, Link } from "@inertiajs/vue3";
import axios from "axios";
import { route } from "../../../vendor/tightenco/ziggy/src/js";
import {
    ArrowTurnDownRightIcon,
    RectangleGroupIcon,
    Square3Stack3DIcon,
    DocumentCurrencyDollarIcon,
    ClipboardDocumentListIcon,
    Cog6ToothIcon,
    ChevronDownIcon,
    Bars3Icon,
    ClockIcon,
    InformationCircleIcon,
} from "@heroicons/vue/24/solid";
import useTheme from "../Pages/Composables/useTheme";
import usePermissions from "../Pages/Composables/usePermissions";
import { mdiBell, mdiMessage, mdiDatabaseSync } from "@mdi/js";
import Notifications from "../Modals/Notifications.vue";
import Messages from "../Modals/Messages.vue";
import ToastAlertWarning from "../Pages/Components/ToastAlertWarning.vue";
import AnnouncementPopup from "../Pages/Components/AnnouncementPopup.vue";
import { first } from "lodash";

const { canView } = usePermissions();

const { props } = usePage();
const page = usePage();

const showAnnouncementModal = ref(false);
const selectedAnnouncement = ref(null);
const layoutScale = ref(1);
const dismissedAnnouncementIds = ref(
    Array.isArray(page.props.dismissedAnnouncementIds)
        ? page.props.dismissedAnnouncementIds
        : []
);

const activeAnnouncement = computed(() => page.props.activeAnnouncement ?? null);
const activeAnnouncements = computed(() => {
    const announcements = page.props.activeAnnouncements;
    if (Array.isArray(announcements) && announcements.length > 0) {
        return announcements;
    }

    return activeAnnouncement.value ? [activeAnnouncement.value] : [];
});

const getDismissKey = (announcementId) => {
    if (!announcementId) return null;
    return `announcement_dismissed:${page.props.tenant}:${announcementId}`;
};

const updateLayoutScale = () => {
    if (typeof window === "undefined") return;

    if (window.innerWidth <= 1024) {
        layoutScale.value = 0.7;
        return;
    }

    if (window.innerWidth <= 1280) {
        layoutScale.value = 0.8;
        return;
    }

    if (window.innerWidth <= 1440) {
        layoutScale.value = 0.9;
        return;
    }
    if (window.innerWidth <= 1600) {
        layoutScale.value = 1;
        return;
    }
    
    

    layoutScale.value = 1;
};

const layoutShellStyle = computed(() => {
    if (layoutScale.value === 1) {
        return {};
    }

    return {
        zoom: String(layoutScale.value),
    };
});

const mergeDismissedFromLocalStorage = () => {
    if (typeof window === "undefined") return;
    const localDismissed = activeAnnouncements.value
        .map((a) => a?.id)
        .filter(Boolean)
        .filter((id) => {
            const key = getDismissKey(id);
            return key ? !!window.localStorage.getItem(key) : false;
        });

    dismissedAnnouncementIds.value = Array.from(
        new Set([
            ...(Array.isArray(page.props.dismissedAnnouncementIds)
                ? page.props.dismissedAnnouncementIds
                : []),
            ...localDismissed,
        ])
    );
};

watch(
    () => page.props.dismissedAnnouncementIds,
    () => {
        mergeDismissedFromLocalStorage();
    },
    { immediate: true }
);

watch(
    () => activeAnnouncements.value.map((a) => a?.id).join(","),
    () => {
        mergeDismissedFromLocalStorage();
        showAnnouncementModal.value = false;
        selectedAnnouncement.value = null;
    },
    { immediate: true }
);

const openAnnouncementModal = (announcement) => {
    if (!announcement) return;
    selectedAnnouncement.value = announcement;
    showAnnouncementModal.value = true;
};

const dismissAnnouncement = async (announcement) => {
    const id = announcement?.id;
    if (!id) return;

    if (typeof window !== "undefined") {
        const key = getDismissKey(id);
        if (key) {
            window.localStorage.setItem(key, "1");
        }
    }

    try {
        await axios.post(
            route("announcements.dismiss", {
                tenant: page.props.tenant,
                announcement: id,
            })
        );
    } catch (e) {
    }

    if (!dismissedAnnouncementIds.value.includes(id)) {
        dismissedAnnouncementIds.value = [...dismissedAnnouncementIds.value, id];
    }

    if (selectedAnnouncement.value?.id === id) {
        selectedAnnouncement.value = null;
    }
    showAnnouncementModal.value = false;
};

const closeAnnouncementModal = () => {
    if (selectedAnnouncement.value?.show_modal) {
        dismissAnnouncement(selectedAnnouncement.value);
        return;
    }
    showAnnouncementModal.value = false;
    selectedAnnouncement.value = null;
};

provide("openAnnouncementModal", openAnnouncementModal);

const autoModalAnnouncement = computed(() => {
    const announcement = activeAnnouncements.value.find((a) => a?.show_modal);
    if (!announcement) return null;

    if (!announcement.is_dismissible) {
        return dismissedAnnouncementIds.value.includes(announcement.id)
            ? null
            : announcement;
    }

    return dismissedAnnouncementIds.value.includes(announcement.id)
        ? null
        : announcement;
});

const hasAutoShownModal = ref(false);
watch(
    () => autoModalAnnouncement.value?.id ?? null,
    () => {
        if (hasAutoShownModal.value) return;
        if (showAnnouncementModal.value) return;
        if (!autoModalAnnouncement.value) return;

        openAnnouncementModal(autoModalAnnouncement.value);
        hasAutoShownModal.value = true;
    },
    { immediate: true }
);

// const appName = page.props.appName;

const firstName = computed(() => {
    const nameParts = props.auth.user.name.split(" ");
    return nameParts.slice(1, 3).join(" ");
});

const dayNames = ["SUN", "MON", "TUE", "WED", "THU", "FRI", "SAT"];
const currentDayIndex = ref(0);
const hours = ref("");
const minutes = ref("");
const seconds = ref("");
const ampm = ref("");
const formattedDate = ref("");
const weekNumber = ref(0);

const dayRefs = ref([]);
const indicatorLeft = ref(0);
const indicatorWidth = ref(0);

// --- Update Time and Highlight ---
const updateDateTime = () => {
    const now = new Date();

    // Time
    const rawHours = now.getHours();
    hours.value = String(rawHours % 12 || 12).padStart(2, "0");
    minutes.value = String(now.getMinutes()).padStart(2, "0");
    seconds.value = String(now.getSeconds()).padStart(2, "0");
    ampm.value = rawHours >= 12 ? "PM" : "AM";

    // Date
    formattedDate.value = now
        .toLocaleDateString("en-US", {
            month: "short",
            day: "numeric",
            year: "numeric",
        })
        .toUpperCase();

    // Week
    const firstDayOfYear = new Date(now.getFullYear(), 0, 1);
    const pastDaysOfYear = (now - firstDayOfYear) / 86400000;
    weekNumber.value = Math.ceil(
        (pastDaysOfYear + firstDayOfYear.getDay() + 1) / 7
    );

    // Day highlight
    currentDayIndex.value = now.getDay();
};

// --- Update highlight position ---
const updateIndicator = () => {
    nextTick(() => {
        const el = dayRefs.value[currentDayIndex.value];
        if (el) {
            indicatorLeft.value = el.offsetLeft;
            indicatorWidth.value = el.offsetWidth + 1;
        }
    });
};

// --- Mount + Interval ---
let timeInterval;
onMounted(() => {
    updateDateTime();
    timeInterval = setInterval(updateDateTime, 1000);
    updateLayoutScale();
    window.addEventListener("resize", updateLayoutScale);
});
onBeforeUnmount(() => {
    clearInterval(timeInterval);
    window.removeEventListener("resize", updateLayoutScale);
});

// --- Reactively update indicator
watch(currentDayIndex, updateIndicator);
onMounted(updateIndicator);

//////////////////////////////////SCRIPT
// Component registration
const iconComponents = {
    Square3Stack3DIcon,
    DocumentCurrencyDollarIcon,
    ClipboardDocumentListIcon,
    Cog6ToothIcon,
    InformationCircleIcon,
};

//WARNING TOAST
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

//notif
const showNotifications = ref(false);
const showMessages = ref(false);
const notifications = ref([]);

const loading = ref(false);
const error = ref(null);
const channel = ref(null);
const appName = ref({});
const availableTenants = ref([]);
const showTenantDropdown = ref(false);
const isSwitchingTenant = ref(false);
const switchingToTenantName = ref("");
let channelInstance = null;

const toggleTenantDropdown = () => {
    showTenantDropdown.value = !showTenantDropdown.value;
};

const handleTenantSwitch = (tenant) => {
    // If we are already on this tenant, do nothing or just close dropdown
    if (appName.value['bu_code'] === tenant.bu_code) { // Assuming bu_code is unique or use ID
        showTenantDropdown.value = false;
        return;
    }
    
    switchingToTenantName.value = tenant.app_name;
    isSwitchingTenant.value = true;
    showTenantDropdown.value = false;
    
    // We don't need to manually navigate here because the <a> tag's href will take over.
    // However, if we want to ensure the loading screen shows for a bit before the browser takes over:
    // The default behavior of <a> will run immediately. 
};

// Close dropdown when clicking outside
const closeTenantDropdown = (e) => {
    if (showTenantDropdown.value && !e.target.closest('.relative')) {
        showTenantDropdown.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', closeTenantDropdown);
});

onUnmounted(() => {
    document.removeEventListener('click', closeTenantDropdown);
});

const showNotificationModal = () => {
    showNotifications.value = true;
};

// Fetching the users bu assign 
const fetchingUserBuAssign = async () => {
    const response = await axios.get(route('UserBuAssign', { tenant: page.props.tenant }));
    if (response.data.success) {
        appName.value = response.data.data
        availableTenants.value = response.data.available_tenants || [];
    }
};

onMounted(() => {
    fetchingUserBuAssign()
});

const unreadCount = ref(0);

async function fetchNotifications() {
    const { data } = await axios.get(route("getNotificationsCount", { tenant: page.props.tenant }));
    unreadCount.value = data.unread_count;
}

onMounted(() => {
    fetchNotifications();
});

//message
const users = ref([]);
const currentUser = page.props.auth.user;

const totalUnreadCount = computed(() => {
    return users.value.reduce((total, user) => {
        return total + (user.unread_count || 0);
    }, 0);
});

const fetchUsers = async () => {
    try {
        const { data } = await axios.get(
            route("messages.users", {
                tenant: page.props.tenant,
                _t: Date.now(),
            })
        );
        users.value = Array.isArray(data) ? data : [];
    } catch (error) {
        console.error("Error fetching users:", error);
        showWarningToast("Failed to load users");
    }
};

const showMessageModal = async () => {
    await fetchUsers();
    showMessages.value = true;
};

onMounted(async () => {
    await fetchUsers();
});

const isLoggingOut = ref(false);

const markUserOffline = () => {
    if (isLoggingOut.value) return;

    const url = route("user.markOffline", { tenant: page.props.tenant });
    const data = new FormData();
    data.append(
        "_token",
        document.querySelector('meta[name="csrf-token"]').content
    );

    navigator.sendBeacon(url, data);
};

// Runs when Vue layout unmounts (SPA navigation away)
onUnmounted(() => {
    if (!window.echoSubscribed) return;

    if (!echo || !echo.leave) {
        return;
    }

    try {
        echo.leave(`user.${currentUser.id}`);
        echo.leave("users");
        window.echoSubscribed = false;

        markUserOffline();
    } catch (error) {
        console.error("Error cleaning up Echo in layout:", error);
    }
});

// Runs when tab is closed or browser is refreshed
window.addEventListener("beforeunload", markUserOffline);

// Reactive state
const sidebarCollapsed = ref(false);
const leaveTimeout = ref(null);
const leaveTimeoutMenu = ref(null);
const activeMenu = ref(null);
const activeSubmenu = ref("");
const activeUserMenu = ref(null);
const activeUserSubmenu = ref("");
// const pageTitle = ref("Dashboard");
const realActiveMenu = ref(null);

const menus = ref([
    {
        name: "Masterfile",
        icon: "Square3Stack3DIcon",
        subMenus: [
            { name: "Customers", link: route("customer", { tenant: page.props.tenant }), roleId: "0101-CUST" },
            { name: "Users", link: route("user", { tenant: page.props.tenant }), roleId: "0102-USER" },
            // { name: "Checkers", link: route("checker", { tenant: page.props.tenant }), roleId: "0103-CHKR" },
            { name: "Item", link: route("item", { tenant: page.props.tenant }), roleId: "0104-ITEM" },
            {
                name: "Adjustment Reason Setup",
                link: route("adjustmentreasonsetup", { tenant: page.props.tenant }),
                roleId: "0105-ADJRS",
            },
            {
                name: "Cash in Bank",
                link: route("cashinbank", { tenant: page.props.tenant }),
                roleId: "0106-CAB",
            },
            {
                name: "Charge Invoice Type",
                link: route("chargeinvoicetype", { tenant: page.props.tenant }),
                roleId: "0107-CIT",
            },
            {
                name: "Packing Type",
                link: route("packingtype", { tenant: page.props.tenant }),
                roleId: "0108-PCKT",
            },
            {
                name: "Shortage Amount",
                link: route("shortageamount", { tenant: page.props.tenant }),
                roleId: "0109-SAMNT",
            },
        ],
    },
    {
        name: "Transactions",
        icon: "DocumentCurrencyDollarIcon",
        subMenus: [
            { name: "Invoice", link: route("invoice", { tenant: page.props.tenant }), roleId: "0201-CIT" },
            {
                name: "Adjustment",
                link: route("adjustment", { tenant: page.props.tenant }),
                roleId: "0202-ADT",
            },
            { name: "Payment", link: route("payment", { tenant: page.props.tenant }), roleId: "0203-PAYT" },
            {
                name: "AR Beginning Balance",
                link: route("beginningbalance", { tenant: page.props.tenant }),
                roleId: "0204-BGBLT",
            },
        ],
    },
    {
        name: "Reports",
        icon: "ClipboardDocumentListIcon",
        subMenus: [
            {
                name: "Generate Report",
                link: route("generatereport", { tenant: page.props.tenant }),
                roleId: "0301-GNRPRT",
            },
            {
                name: "Customer Ledger",
                link: route("customerledger", { tenant: page.props.tenant }),
                roleId: "0302-CUSLED",
            },
        ],
    },
    {
        name: "Utility",
        icon: "Cog6ToothIcon",
        subMenus: [
            {
                name: "Check Clearing",
                link: route("clearing", { tenant: page.props.tenant }),
                roleId: "0401-CHKCLR",
            },
            {
                name: "WHT Clearing",
                link: route("withholdingtaxclearing", { tenant: page.props.tenant }),
                roleId: "0402-WHTCLR",
            },
            {
                name: "Cancel Payment",
                link: route("cancelpayment", { tenant: page.props.tenant }),
                roleId: "0403-CNCLPY",
            },
            {
                name: "Export to GL",
                link: route("exporttogl", { tenant: page.props.tenant }),
                roleId: "0404-EXPRTGL",
            },
        ],
    },
    {
        name: "System Info",
        icon: "InformationCircleIcon",
        subMenus: [
            {
                name: "About Us",
                link: route("aboutus", { tenant: page.props.tenant }),
                roleId: "AboutUs",
            },
            {
                name: "User Guide",
                link: route("userguide", { tenant: page.props.tenant }),
                roleId: "AboutUs",
            },
        ],
    },
]);

const user_menus = computed(() => [
    {
        subUserMenus: [
            { name: "My Profile", link: route("profile", { tenant: page.props.tenant }) },
            // Only show Masterfile and DB Setup to Admin
            ...(page.props.auth.user.role === 'Admin' ? [
                { name: "User Masterfile", link: route("user-masterfile.index", { tenant: page.props.tenant }) },
                { name: "Database Setup", link: route("app-settings.index", { tenant: page.props.tenant }) },
                { name: "Announcements", link: route("announcements.index", { tenant: page.props.tenant }) },
            ] : []),
            { name: "Logout", link: route("logout", { tenant: page.props.tenant }) },
        ],
    },
]);

// const canView = (roleId) => {
//     const perms = page.props.auth?.permissions || {};
//     return !!perms[roleId]?.can_view;
// };

const filteredMenus = computed(() =>
    menus.value.filter((m) => m?.subMenus?.some((sub) => canView(sub.roleId)))
);

// Methods
const toggleSubMenu = (index) => {
    activeMenu.value = activeMenu.value === index ? null : index;
    realActiveMenu.value = activeMenu.value === index ? index : null;
    localStorage.setItem("activeMenu", activeMenu.value);
};

const setActive = (menu, title) => {
    resetUserProfileMenu();
    activeMenu.value = menu;
    activeSubmenu.value = "";
    realActiveMenu.value = menu;
    // pageTitle.value = title;
    localStorage.setItem("activeMenu", activeMenu.value);
    localStorage.setItem("pageTitle", "Dashboard");
    localStorage.removeItem("activeSubmenu");
};

const setActiveSubmenu = (menuIndex, submenu, parentTitle, submenuTitle) => {
    resetUserProfileMenu();
    activeMenu.value = menuIndex;
    realActiveMenu.value = menuIndex;
    activeSubmenu.value = submenu;
    // pageTitle.value = `${parentTitle} > ${submenuTitle}`;
    localStorage.setItem("activeMenu", activeMenu.value);
    localStorage.setItem("activeSubmenu", activeSubmenu.value);
    localStorage.setItem("pageTitle", `${parentTitle} > ${submenuTitle}`);
};

const toggleUserSubMenu = (indexUser) => {
    activeUserMenu.value =
        activeUserMenu.value === indexUser ? null : indexUser;
    localStorage.setItem("activeUserMenu", activeUserMenu.value);
};

const setUserActive = (menuUser, title) => {
    resetMenu();
    activeUserMenu.value = menuUser;
    activeUserSubmenu.value = "";
    // pageTitle.value = title;
    localStorage.setItem("activeUserMenu", activeUserMenu.value);
    localStorage.setItem("pageTitle", "Profile");
    localStorage.removeItem("activeUserSubmenu");
};

const setUserActiveSubmenu = (
    menuUserIndex,
    submenuUser,
    parentTitle,
    submenuUserTitle
) => {
    resetMenu();
    activeUserMenu.value = menuUserIndex;
    activeUserSubmenu.value = submenuUser;
    // pageTitle.value = `${parentTitle} > ${submenuUserTitle}`;
    localStorage.setItem("activeUserMenu", activeUserMenu.value);
    localStorage.setItem("activeUserSubmenu", activeUserSubmenu.value);
    localStorage.setItem("pageTitle", `${parentTitle} > ${submenuUserTitle}`);
};

const handleLinkClick = (
    subUser,
    indexUser,
    submenuLink,
    menuUserName,
    subUserName
) => {
    if (subUserName === "Logout") {
        handleLogout(subUser);
    } else {
        setUserActiveSubmenu(indexUser, submenuLink, menuUserName, subUserName);
    }
};

const handleLogout = (subUser) => {
    isLoggingOut.value = true;
    // pageTitle.value = "";
    localStorage.removeItem("activeUserMenu");
    localStorage.removeItem("activeUserSubmenu");
    localStorage.removeItem("pageTitle");
    localStorage.removeItem("activeMenu");
    localStorage.removeItem("activeSubmenu");
    setActive("dashboard", "Dashboard");
    activeUserMenu.value = null;
};

const resetUserProfileMenu = () => {
    activeUserMenu.value = null;
    activeUserSubmenu.value = "";
    localStorage.removeItem("activeUserMenu");
    localStorage.removeItem("activeUserSubmenu");
};

const resetMenu = () => {
    activeMenu.value = null;
    activeSubmenu.value = "";
    localStorage.removeItem("activeMenu");
    localStorage.removeItem("activeSubmenu");
};

const toggleSidebar = () => {
    sidebarCollapsed.value = !sidebarCollapsed.value;
};

const handleMouseEnter = (index) => {
    if (sidebarCollapsed.value) {
        if (leaveTimeout.value) {
            clearTimeout(leaveTimeout.value);
        }
        activeUserMenu.value = index;
    }
};

const handleMouseLeave = (index) => {
    if (sidebarCollapsed.value) {
        if (leaveTimeout.value) {
            clearTimeout(leaveTimeout.value);
        }
        leaveTimeout.value = setTimeout(() => {
            if (activeUserMenu.value === index) {
                activeUserMenu.value = null;
            }
        }, 300);
    }
};

const handleMouseEnterMenu = (index) => {
    if (sidebarCollapsed.value) {
        if (leaveTimeoutMenu.value) {
            clearTimeout(leaveTimeoutMenu.value);
        }
        activeMenu.value = index;
    }
};

const handleMouseLeaveMenu = (index) => {
    if (sidebarCollapsed.value) {
        if (leaveTimeoutMenu.value) {
            clearTimeout(leaveTimeoutMenu.value);
        }
        leaveTimeoutMenu.value = setTimeout(() => {
            if (activeMenu.value === index) {
                activeMenu.value = null;
            }
        }, 300);
    }
};

const currentPageTitle = computed(() => {
    const currentPath = page.url; // Use Inertia's reactive URL

    // Check dashboard first
    if (currentPath === "/dashboard") {
        return "Dashboard";
    }

    // Check main menus
    for (const menu of filteredMenus.value) {
        for (const sub of menu.subMenus) {
            const cleanedLink = sub.link.replace(
                /^https?:\/\/\d+\.\d+\.\d+\.\d+:\d+/,
                ""
            );
            if (cleanedLink === currentPath) {
                return `${menu.name} > ${sub.name}`;
            }
        }
    }

    // Check user menus
    for (const menuUser of user_menus.value) {
        for (const subUser of menuUser.subUserMenus) {
            if (subUser.link === currentPath) {
                return subUser.name === "Logout"
                    ? "Logout"
                    : `Profile > ${subUser.name}`;
            }
        }
    }

    // Fallback to localStorage title or default
    return localStorage.getItem("pageTitle") || "Dashboard";
});

// Lifecycle hooks
onMounted(() => {
    // SideBar Menu
    const savedMenu = localStorage.getItem("activeMenu");
    const savedSubmenu = localStorage.getItem("activeSubmenu");
    const savedPageTitle = localStorage.getItem("pageTitle");
    const currentPath = window.location.pathname;

    if (currentPath === "/dashboard") {
        setActive("dashboard", "Dashboard");
    } else {
        filteredMenus.value.forEach((menu, menuIndex) => {
            menu.subMenus.forEach((sub) => {
                const cleanedLink = sub.link.replace(
                    /^https?:\/\/\d+\.\d+\.\d+\.\d+:\d+/,
                    ""
                );

                if (cleanedLink === currentPath) {
                    setActiveSubmenu(menuIndex, sub.link, menu.name, sub.name);
                }
            });
        });

        if (!activeSubmenu.value) {
            if (savedMenu !== null && savedMenu !== "null") {
                activeMenu.value = isNaN(savedMenu)
                    ? savedMenu
                    : parseInt(savedMenu);
            }
            if (savedSubmenu) {
                activeSubmenu.value = savedSubmenu;
            }
            // if (savedPageTitle) {
            //     pageTitle.value = savedPageTitle;
            // }
            // pageTitle.value = currentPageTitle;
        }
    }

    // User Profile Menu
    const savedUserMenu = localStorage.getItem("activeUserMenu");
    const savedUserSubmenu = localStorage.getItem("activeUserSubmenu");
    const currentUserPath = window.location.pathname;

    user_menus.value.forEach((menuUser, menuUserIndex) => {
        menuUser.subUserMenus.forEach((subUser) => {
            if (subUser.link === currentUserPath) {
                setUserActiveSubmenu(
                    menuUserIndex,
                    subUser.link,
                    menuUser.name,
                    subUser.name
                );
            }
        });
    });

    if (!activeUserSubmenu.value) {
        if (savedUserMenu !== null && savedUserMenu !== "null") {
            activeUserMenu.value = isNaN(savedUserMenu)
                ? savedUserMenu
                : parseInt(savedUserMenu);
        }
        if (savedUserSubmenu) {
            activeUserSubmenu.value = savedUserSubmenu;
        }
        if (savedPageTitle) {
            // pageTitle.value = savedPageTitle;
        }
    }

    const savedSidebarState = localStorage.getItem("sidebarCollapsed");
    if (savedSidebarState !== null) {
        sidebarCollapsed.value = savedSidebarState === "true";
        activeMenu.value = null;
        activeUserMenu.value = null;
    }
});

// Watcher
watch(sidebarCollapsed, (newVal) => {
    localStorage.setItem("sidebarCollapsed", newVal);
    if (newVal) {
        activeMenu.value = null;
        activeUserMenu.value = null;
    }
});

//computed
const isSubmenuActive = computed(() => (index) => {
    return (
        filteredMenus.value[index].subMenus.some(
            (sub) => sub.link === activeSubmenu.value
        ) ||
        (realActiveMenu.value === index && activeSubmenu.value !== "")
    );
});

const showImage = ref(true);
const profilePhotoUrl = computed(() =>
    showImage.value ? `${route("profilePhoto", { tenant: page.props.tenant })}?t=${Date.now()}` : ""
);
// Get user initials for avatar
const userInitials = computed(() => {
    if (props.auth.user.name === "Administrator") {
        return "A";
    } else {
        return (
            firstName.value
                ?.split(" ")
                .map((name) => name[0])
                .join("")
                .toUpperCase() || ""
        );
    }
});

const { setTheme, initTheme } = useTheme();

onMounted(() => {
    // Init theme on load
    initTheme();

    // Listen for changes from other tabs
    const handleStorageChange = (e) => {
        if (e.key === "theme" && e.newValue) {
            setTheme(e.newValue);
        }
    };

    window.addEventListener("storage", handleStorageChange);

    onBeforeUnmount(() => {
        window.removeEventListener("storage", handleStorageChange);
    });
});
</script>

<style scoped>
.fade-enter-active {
    transition: opacity 1.6s ease;
}

.fade-enter-from {
    opacity: 0;
}
</style>
