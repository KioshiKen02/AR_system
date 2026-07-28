<template>
    <Transition name="modal">
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-4 backdrop-blur-sm bg-black/60 overflow-y-auto scrollbar-thin scrollbar-thumb-[var(--color-scrollbar-track)] scrollbar-track-[var(--color-scrollbar-track)] scrollbar-stable [scrollbar-gutter:stable] scrollbar-thumb-rounded-full">
            <Transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                <ConfirmationDialog :show="showDialog" :message="confirmationMessage" @close="handleConfirm" />
            </Transition>
            <Transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                <ConfirmationDialog :show="showDialogDoc" :message="confirmationMessageDoc" @close="handleConfirmDoc" />
            </Transition>
            <Transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                <InformationDialog :show="showInfoDialog" :message="infoDialogMessage"
                    @close="showInfoDialog = false" />
            </Transition>

            <ToastAlertWarning :show="showToast" :message="toastMessage" />
            <!-- Modal Container -->
            <div
                class="flex max-h-[calc(100vh-4rem)] w-full max-w-[95vw] flex-col overflow-hidden rounded-2xl text-[var(--color-text-primary)] bg-[var(--color-bg-secondary)] border border-[var(--color-border)]">
                <!-- Content -->
                <div class="flex min-h-0 flex-1 flex-col p-4 sm:p-5">
                    <!-- Header -->
                    <div class="mb-3 text-center">
                        <h2 class="text-2xl font-bold tracking-wide">
                            DOCUMENT NUMBER
                        </h2>
                        <div
                            class="mt-2 h-0.5 bg-gradient-to-r from-transparent via-[var(--color-border)] to-transparent">
                        </div>
                    </div>

                    <div class="mb-4 grid grid-cols-1 xl:grid-cols-3 gap-4 items-stretch">
                        <div class="flex flex-col gap-2 rounded-xl border border-[var(--color-border)]/60 p-4">
                            <label class="block text-md font-bold">PAYMENT MODE</label>
                            <div class="flex gap-4">
                                <label class="w-full inline-flex items-center cursor-pointer group">
                                    <input type="radio" v-model="payment_mode" value="Oldest to Newest"
                                        class="hidden peer" />
                                    <div class="w-full relative flex items-center justify-start p-2">
                                        <div class="absolute -inset-1 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-200 bg-[var(--color-border)]/30"
                                            :class="{
                                                'opacity-100':
                                                    payment_mode ===
                                                    'Oldest to Newest',
                                            }"></div>
                                        <div class="relative w-5 h-5 mr-2 rounded-full border-2 border-[var(--color-border)] transition-colors z-10"
                                            :class="{
                                                'border-[var(--color-border)]':
                                                    payment_mode ===
                                                    'Oldest to Newest',
                                            }">
                                            <div class="absolute inset-0 m-auto w-2.5 h-2.5 rounded-full bg-[var(--color-border)] transition-opacity"
                                                :class="{
                                                    'opacity-100':
                                                        payment_mode ===
                                                        'Oldest to Newest',
                                                    'opacity-0':
                                                        payment_mode !==
                                                        'Oldest to Newest',
                                                }"></div>
                                        </div>
                                        <span class="text-sm z-10">Oldest to Newest</span>
                                    </div>
                                </label>

                                <label class="w-full inline-flex items-center cursor-pointer group">
                                    <input type="radio" v-model="payment_mode" value="Manual Select"
                                        class="hidden peer" />
                                    <div class="w-full relative flex items-center justify-start p-2">
                                        <div class="absolute -inset-1 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-200 bg-[var(--color-border)]/30"
                                            :class="{
                                                'opacity-100':
                                                    payment_mode ===
                                                    'Manual Select',
                                            }"></div>
                                        <div class="relative w-5 h-5 mr-2 rounded-full border-2 border-[var(--color-border)] transition-colors z-10"
                                            :class="{
                                                'border-[var(--color-border)]':
                                                    payment_mode ===
                                                    'Manual Select',
                                            }">
                                            <div class="absolute inset-0 m-auto w-2.5 h-2.5 rounded-full bg-[var(--color-border)] transition-opacity"
                                                :class="{
                                                    'opacity-100':
                                                        payment_mode ===
                                                        'Manual Select',
                                                    'opacity-0':
                                                        payment_mode !==
                                                        'Manual Select',
                                                }"></div>
                                        </div>
                                        <span class="text-sm z-10">Manual Select</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 rounded-xl border border-[var(--color-border)]/60 p-4">
                            <label class="block text-md font-bold">FILTER BY TYPE</label>
                            <div class="relative flex-1 flex items-center">
                                <DropdownInput data-testid="type-filter" v-model="selectedTypeFilter" :options="[
                                    'All Types',
                                    'Sales Invoice',
                                    'Charge Invoice',
                                    'Merchandise Transfer Out',
                                    'Merchandise Charge Invoice',
                                    'Sales Charge Invoice',
                                    'Payment',
                                    'BG',
                                ]" placeholder="Click to Select" />
                            </div>
                        </div>

                        <div v-if="props.whtEnabled" class="flex flex-col gap-2 rounded-xl border border-[var(--color-border)]/60 p-4">
                            <label class="block text-md font-bold">TAX</label>
                            <div class="flex flex-1 flex-wrap items-center gap-4">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="text-sm font-bold">Tax rate (optional):</span>
                                    <DropdownInput data-testid="tax-rate-select" v-model="taxRate" :options="['None', '1%', '2%', '5%']" class="w-28" />
                                </div>
                                <label class="flex items-center gap-2 cursor-pointer text-sm font-bold min-h-[42px]">
                                    <span class="relative w-5 h-5">
                                        <input data-testid="apply-bir-2307" type="checkbox" v-model="applyBir2307" class="peer appearance-none w-5 h-5 border-2 rounded-sm border-[var(--color-border)] bg-[var(--color-bg-secondary)] checked:bg-[var(--color-primary)] checked:!border-[var(--color-primary)] focus:outline-none transition-colors duration-200 cursor-pointer" />
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                            class="absolute inset-0 p-0.5 text-white hidden peer-checked:block pointer-events-none"
                                            fill="currentColor">
                                            <path
                                                d="M9,20.42L2.79,14.21L5.62,11.38L9,14.77L18.88,4.88L21.71,7.71L9,20.42Z" />
                                        </svg>
                                    </span>
                                    <span>Apply BIR 2307</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Search Input -->
                    <div class="mb-4 relative">
                        <input v-model="searchQuery" type="text" placeholder="Search..." ref="searchInput"
                            :readonly="payment_mode === 'Oldest to Newest'"
                            class="w-full rounded-md px-4 py-2 text-[var(--color-text-primary)] border border-[var(--color-border)] read-only:cursor-not-allowed"
                            :class="{
                                '!border-red-400 !ring-2 !ring-red-500/50 bg-red-900/10':
                                    filteredData.length === 0 &&
                                    searchQuery !== '',
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
                    <div v-if="payment_mode === 'Oldest to Newest'"
                        class="flex min-h-[140px] flex-1 rounded-xl overflow-hidden border border-[var(--color-border)] backdrop-blur-sm pl-2">
                        <div class="w-full flex flex-col justify-center items-center gap-2">
                            <span><svg-icon type="mdi" :path="mdiCalendarClock" class="w-20 h-20" /></span>
                            <span v-if="filteredData.length !== 0" class="font-extrabold text-2xl">OLDEST TO NEWEST
                                SELECTED</span>
                            <span v-else class="font-extrabold text-2xl">{{ emptyStateMessage }}</span>
                        </div>
                    </div>
                    <div v-if="payment_mode === 'Manual Select'"
                        class="flex min-h-0 flex-1 rounded-xl overflow-hidden border border-[var(--color-border)] backdrop-blur-sm">
                        <!-- <div
                            class="grid grid-cols-2 gap-3 border-b border-[var(--color-border)]/60 bg-[var(--color-bg-secondary)]/50 p-3 text-xs text-[var(--color-text-secondary)]">
                            <div>
                                <span class="font-semibold text-[var(--color-text-primary)]">SI/CI Amount:</span>
                                Original document amount.
                            </div>
                            <div>
                                <span class="font-semibold text-[var(--color-text-primary)]">Cash/Cleared Paid:</span>
                                Posted cash paid only, excluding floating WHT.
                            </div>
                            <div>
                                <span class="font-semibold text-[var(--color-text-primary)]">Floating Credit:</span>
                                Reserved WHT, PDC, or DC not yet cleared.
                            </div>
                            <div>
                                <span class="font-semibold text-[var(--color-text-primary)]">Collectible Balance:</span>
                                Current balance after floating credit deduction.
                            </div>
                        </div> -->
                        <div class="relative min-h-0 flex-1 overflow-x-auto">
                            <div
                                class="relative h-full max-h-full min-h-[200px] overflow-y-auto scrollbar-thin scrollbar-thumb-[var(--color-scrollbar-track)] scrollbar-track-[var(--color-primary)]/20 scrollbar-stable [scrollbar-gutter:stable] scrollbar-thumb-rounded-full sm:min-h-[240px]">
                                <table class="w-full min-w-[1340px] table-fixed text-[var(--color-text-primary)] text-sm">
                                    <thead class="sticky top-0 z-10 bg-[var(--color-bg-secondary)] border-b border-[var(--color-border)]/50">
                                        <tr>
                                            <th class="px-2 py-1.5 text-center w-[70px]">
                                                SELECT
                                            </th>
                                            <th class="px-2 py-1.5 text-left w-[130px]"> 
                                                DOCUMENT NO
                                            </th>
                                            <th class="px-2 py-1.5 text-left w-[100px]">
                                                DATE
                                            </th>
                                            <th class="px-2 py-1.5 text-center w-[120px]">
                                                TYPE
                                            </th>
                                            <th class="px-2 py-1.5 text-center w-[95px]">
                                                TRADE TYPE
                                            </th>
                                            <th class="px-2 py-1.5 text-right w-[110px]">
                                                SI/CI AMOUNT
                                            </th>
                                            <th class="px-2 py-1.5 text-right w-[110px]">
                                                CASH/CLEARED PAID
                                            </th>
                                            <th class="px-2 py-1.5 text-right w-[110px]">
                                                COLLECTIBLE BALANCE
                                            </th>
                                            <th class="px-2 py-1.5 text-center w-[150px]">
                                                AMOUNT TO PAY
                                            </th>
                                            <th v-if="props.whtEnabled" class="px-2 py-1.5 text-center w-[110px]">
                                                <div class="flex flex-col items-center">
                                                    <span>WHT</span>
                                                    <span class="text-xs font-medium text-[var(--color-text-secondary)]">Optional</span>
                                                </div>
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody v-if="isLoading">
                                        <tr>
                                            <td :colspan="props.whtEnabled ? 12 : 11" class="text-center py-10">
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

                                    <tbody v-else class="divide-y divide-[var(--color-border)]/50 rounded-xl">
                                        <tr v-for="(invoice, index
                                        ) in filteredData" :key="index"
                                            class="rounded-xl hover:bg-[var(--color-primary)]/20 transition-colors duration-150 group cursor-pointer">
                                            <td class="px-2 py-1.5 text-center w-[70px]">
                                                <label class="relative inline-block w-5 h-5">
                                                    <input type="checkbox" :checked="isInvoiceSelected(
                                                        invoice
                                                    )
                                                        " @click="
                                                            (e) =>
                                                                handleCheckboxClick(
                                                                    invoice,
                                                                    e
                                                                )
                                                        "
                                                        class="peer appearance-none w-5 h-5 border-2 rounded-sm border-[var(--color-border)] bg-transparent checked:bg-[var(--color-primary)] checked:!border-[var(--color-primary)] focus:outline-none transition-colors duration-200 cursor-pointer"
                                                        @click.stop />
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                        class="absolute p-0.5 top-0 left-0 w-5 h-5 text-[var(--color-bg-primary)] hidden peer-checked:block pointer-events-none"
                                                        fill="white">
                                                        <path
                                                            d="M9,20.42L2.79,14.21L5.62,11.38L9,14.77L18.88,4.88L21.71,7.71L9,20.42Z" />
                                                    </svg>
                                                </label>
                                            </td>
                                            <td class="px-2 py-1.5 font-medium w-[130px]" @click="
                                                (e) =>
                                                    handleCheckboxClick(
                                                        invoice,
                                                        e
                                                    )
                                            ">
                                                {{ invoice.docunumber }}
                                            </td>
                                            <td class="px-2 py-1.5 w-[100px]" @click="
                                                (e) =>
                                                    handleCheckboxClick(
                                                        invoice,
                                                        e
                                                    )
                                            ">
                                                {{ formatDate(invoice.date) }}
                                            </td>
                                            <td class="px-2 py-1.5 text-center w-[120px]" @click="
                                                (e) =>
                                                    handleCheckboxClick(
                                                        invoice,
                                                        e
                                                    )
                                            ">
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                                    :class="{
                                                        'bg-emerald-700 text-emerald-300':
                                                            invoice.type ===
                                                            'Sales Invoice',
                                                        'bg-cyan-700 text-cyan-300':
                                                            invoice.type ===
                                                            'Charge Invoice',
                                                        'bg-indigo-700 text-indigo-300':
                                                            invoice.type ===
                                                            'Merchandise Transfer Out',
                                                        'bg-sky-700 text-sky-300':
                                                            invoice.type ===
                                                            'Merchandise Charge Invoice',
                                                        'bg-teal-700 text-teal-300':
                                                            invoice.type ===
                                                            'Sales Charge Invoice',
                                                        'bg-pink-700 text-pink-300':
                                                            invoice.type ===
                                                            'Payment',
                                                        'bg-purple-700 text-purple-300':
                                                            invoice.type ===
                                                            'BG' ||
                                                            invoice.type ===
                                                            'Beginning Balance',
                                                    }">
                                                    {{ invoice.type === 'Beginning Balance' ? 'BG' : invoice.type }}
                                                </span>
                                            </td>
                                            <td class="px-2 py-1.5 text-center w-[95px]" @click="
                                                (e) =>
                                                    handleCheckboxClick(
                                                        invoice,
                                                        e
                                                    )
                                            ">
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                                    :class="[
                                                        invoice.trade_type ===
                                                            'Trade'
                                                            ? 'bg-lime-700 text-lime-300'
                                                            : invoice.trade_type ===
                                                                'Non-Trade'
                                                                ? 'bg-green-700 text-green-300'
                                                                : 'bg-emerald-700 text-emerald-300',
                                                    ]">
                                                    {{
                                                        invoice.trade_type ||
                                                        "N/A"
                                                    }}
                                                </span>
                                            </td>
                                            <td class="px-2 py-1.5 text-right font-medium w-[110px]" @click="
                                                (e) =>
                                                    handleCheckboxClick(
                                                        invoice,
                                                        e
                                                    )
                                            ">
                                                {{
                                                    formatCurrency(
                                                        invoice.amount
                                                    )
                                                }}
                                            </td>
                                            <td class="px-2 py-1.5 text-right font-medium w-[110px]" @click="
                                                (e) =>
                                                    handleCheckboxClick(
                                                        invoice,
                                                        e
                                                    )
                                            ">
                                                <div class="flex flex-col items-end leading-tight">
                                                    <div class="font-medium">
                                                        {{
                                                            formatCurrency(
                                                                parseFloat(
                                                                    invoice.amount_paid
                                                                ) || 0
                                                            )
                                                        }}
                                                    </div>
                                                    <div v-if="invoice.wht_floating_amount > 0" class="text-xs text-[var(--color-text-secondary)]">
                                                        Floating Credit - WHT {{ formatCurrency(invoice.wht_floating_amount) }}
                                                    </div>
                                                    <div v-if="invoice.pdc_floating_amount > 0" class="text-xs text-[var(--color-text-secondary)]">
                                                        Floating Credit - PDC {{ formatCurrency(invoice.pdc_floating_amount) }}
                                                    </div>
                                                    <div v-if="invoice.dc_floating_amount > 0" class="text-xs text-[var(--color-text-secondary)]">
                                                        Floating Credit - DC {{ formatCurrency(invoice.dc_floating_amount) }}
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-2 py-1.5 text-right font-medium w-[110px]" @click="
                                                (e) =>
                                                    handleCheckboxClick(
                                                        invoice,
                                                        e
                                                    )
                                            ">
                                                {{
                                                    formatCurrency(
                                                        getRealBalance(
                                                            invoice
                                                        )
                                                    )
                                                }}
                                            </td>
                                            <td class="px-2 py-1.5 text-right font-medium w-[150px]">
                                                <TextInput type="decimal" v-model="invoice.amountToPay
                                                    " :readonly="!isInvoiceSelected(invoice)
                                                        " :validation="isInvoiceSelected(
                                                            invoice
                                                        )
                                                            ? 'yes'
                                                            : 'no'
                                                            " @input="syncManualAmountToPay(invoice)" />
                                            </td>

                                            <td v-if="props.whtEnabled" class="px-2 py-1.5 text-right font-medium w-[110px]">
                                                <div v-if="isInvoiceSelected(invoice)">
                                                    <div v-if="taxRate === 'None'" class="flex flex-col items-end gap-1">
                                                        <input data-testid="wht-manual-input" type="text" inputmode="decimal" v-model="invoice.wht_amount"
                                                            placeholder="Optional" @input="(e) => onManualWhtInput(invoice, e.target.value)" class="form-input hide-arrows text-right w-full"
                                                            :class="invoice.wht_error
                                                                ? '!border-red-400 !ring-2 !ring-red-500/50 bg-red-900/10'
                                                                : 'border-[var(--color-border)]'" />
                                                        <div v-if="invoice.wht_error" class="text-xs text-red-500 text-right">
                                                            {{ invoice.wht_error }}
                                                        </div>
                                                    </div>
                                                    <div v-else>
                                                        {{ formatCurrency(invoice.wht_amount) }}
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>

                                        <tr v-if="
                                            filteredData.length === 0 &&
                                            !isLoading
                                        ">
                                            <td :colspan="props.whtEnabled ? 12 : 11" class="px-5 py-10 text-center">
                                                <div
                                                    class="flex flex-col items-center justify-center text-[var(--color-text-primary)]">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mb-2"
                                                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="1.5"
                                                            d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                    </svg>
                                                    <p class="font-medium">
                                                        {{ emptyStateMessage }}
                                                    </p>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="mt-4 pt-2 border-t border-[var(--color-border)] flex justify-end gap-2">
                        <button @click="closeModal" class="closeButton group">
                            <div class="flex justify-center items-center gap-2">
                                <span class="transition-transform duration-300 group-hover:rotate-180">
                                    <svg-icon type="mdi" :path="mdiClose" class="w-5 h-5" />
                                </span>
                                Close
                            </div>
                        </button>
                        <button @click="submitSelected" class="submitButton group">
                            <div class="flex justify-center items-center gap-2">
                                <span class="transition-transform duration-300 group-hover:rotate-405">
                                    <svg-icon type="mdi" :path="mdiNavigationVariantOutline" class="w-5 h-5" />
                                </span>
                                <span v-if="payment_mode === 'Manual Select'">Select</span>
                                <span v-else>Confirm</span>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from "vue";
import ConfirmationDialog from "../../Pages/Components/ConfirmationDialog.vue";
import InformationDialog from "../../Pages/Components/InformationDialog.vue";
import {
    mdiAlert,
    mdiCalendarClock,
    mdiClose,
    mdiMagnify,
    mdiNavigationVariantOutline,
    mdiSignCaution,
} from "@mdi/js";
import ToastAlertWarning from "../../Pages/Components/ToastAlertWarning.vue";
import TextInput from "../../Pages/Components/TextInput.vue";
import { isEmpty } from "lodash";
import DropdownInput from "../../Pages/Components/DropdownInput.vue";
import { route } from "../../../../vendor/tightenco/ziggy/src/js";
import { usePage } from "@inertiajs/vue3";

const props = defineProps({
    customer_code: String,
    date: String,
    paymentType: String,
    editable_wht: Boolean,
    whtEnabled: {
        type: Boolean,
        default: false,
    },
    autoApplyWht: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["close", "submit"]);
const page = usePage();

const selectedInvoiceNumbers = ref([]);
const selectedInvoiceType = ref([]);
const selectedTotalAmount = ref(0); // Now only stores the invoice amount
const selectedTotalAmountPaid = ref(0); // Now only stores the invoice amount
const selectedDate = ref(null); // Now only stores the invoice date
const pdcfloatingAmount = ref(0);
const haspdcFloating = ref(false);
const dcfloatingAmount = ref(0);
const hasdcFloating = ref(false);
const whtfloatingAmount = ref(0);
const haswhtFloating = ref(false);
const totalFloatingAmount = ref(0);
const isLoading = ref(false);
const payment_mode = ref("Manual Select");
const searchQuery = ref("");
const filteredData = ref([]);
const searchInput = ref(null);
const documents = ref([]);
let debounceTimeout = null;
const showToast = ref(false);
const toastMessage = ref("");
let toastTimeout = null; // to keep track of the timeout
const selectedInvoices = ref([]);
const selectedTypeFilter = ref("All Types");
const whtIsActive = ref(false);
const pendingSubmitPayload = ref(null);

const taxRate = ref("None");
const applyBir2307 = ref(false);
const allowOverpayment = computed(
    () => page.props.tenantSettings?.allow_overpayment ?? true
);
const emptyStateMessage = computed(() =>
    props.date
        ? "No transactions available on or before the selected receipt date."
        : "No data found for this customer."
);

const isWithinSelectedReceiptDate = (documentDate) => {
    if (!props.date || !documentDate) return true;

    const selectedDate = new Date(props.date);
    const transactionDate = new Date(documentDate);

    if (Number.isNaN(selectedDate.getTime()) || Number.isNaN(transactionDate.getTime())) {
        return true;
    }

    selectedDate.setHours(23, 59, 59, 999);
    transactionDate.setHours(0, 0, 0, 0);

    return transactionDate <= selectedDate;
};

watch([taxRate, applyBir2307], () => {
    if (props.whtEnabled) {
        selectedInvoices.value.forEach((inv) => {
            const matched = documents.value.find(
                (doc) => doc.docunumber === inv.docunumber && doc.type === inv.type
            );
            if (matched) {
                setWhtType(matched, "auto", false);
            }
        });
    }
});

const getRealBalance = (invoice) => {
    const rawBalance = parseFloat(invoice.balance) || 0;
    return Math.max(0, rawBalance - getFloatingPaidAmount(invoice));
};

const infoDialogMessage = computed(() => {
    if (totalFloatingAmount.value > 0) {
        return `This document already has a total floating credit of ${formatCurrency(
            totalFloatingAmount.value
        )}. Further payment is not allowed because the current collectible balance is already zero.`;
    }

    return "This document is already fully paid. Further payment is not allowed.";
});

// Watch for changes in customer_code
watch(
    () => [props.customer_code, props.date],
    async ([newCode, newDate]) => {
        if (!newCode || !newDate) {
            resetValues();
            filteredData.value = [];
            documents.value = [];
            return;
        }

        try {
            isLoading.value = true;
            resetValues();

            const response = await axios.get(
                route("getInvoiceListForPayment", { tenant: page.props.tenant }),
                {
                    params: {
                        customer_code: newCode,
                        date: props.date,
                    },
                }
            );

            documents.value = response.data
                .filter((invoice) => invoice.running_balance !== "0.00")
                .map((invoice) => ({
                    docunumber: invoice.invoice_no,
                    date: invoice.receipt_date,
                    type: invoice.type,
                    amount: invoice.amount,
                    amount_paid: invoice.amount_paid,
                    applied_wht_amount: invoice.applied_wht_amount || 0,
                    balance: invoice.running_balance,
                    amountToPay: 0.0,
                    wht_amount: 0.00,
                    wht_error: "",
                    total_amount_less_wht: 0.00,
                    trade_type: invoice.trade_type,
                    pdc_floating_amount: invoice.pdc_floating_amount,
                    has_pdc_floating_payments:
                        invoice.has_pdc_floating_payments,
                    dc_floating_amount: invoice.dc_floating_amount,
                    has_dc_floating_payments: invoice.has_dc_floating_payments,
                    wht_floating_amount: invoice.wht_floating_amount,
                    has_wht_floating_payments:
                        invoice.has_wht_floating_payments,
                    has_wht_transaction: invoice.has_wht_transaction,
                    wht_transaction_count: invoice.wht_transaction_count,
                }))
                .filter((invoice) => isWithinSelectedReceiptDate(invoice.date));
            filteredData.value = documents.value;
        } catch (error) {
            console.error("Error fetching invoices:", error);
            filteredData.value = [];
            documents.value = [];
        } finally {
            isLoading.value = false;
        }
    },
    { immediate: true }
);

const formatDate = (dateString) => {
    const options = { year: "numeric", month: "short", day: "numeric" };
    return new Date(dateString).toLocaleDateString(undefined, options);
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
    }).format(amount);
};

const documentKey = (doc) => `${doc.docunumber}::${doc.type}`;

const findMatchedDocument = (doc) => {
    return documents.value.find(
        (item) => item.docunumber === doc.docunumber && item.type === doc.type
    );
};

const getRequestedDocumentAmount = (doc) => {
    return (parseFloat(doc?.amountToPay) || 0) + (parseFloat(doc?.wht_amount) || 0);
};

const isSingleUseWhtDocument = (invoice) =>
    invoice.type === "Sales Invoice" ||
    invoice.type === "Charge Invoice" ||
    invoice.type === "Merchandise Transfer Out" ||
    invoice.type === "Merchandise Charge Invoice" ||
    invoice.type === "Sales Charge Invoice";

const canApplyWhtToInvoice = (invoice, showMessage = true) => {
    if (!props.whtEnabled) {
        return true;
    }

    if (!isSingleUseWhtDocument(invoice) || !invoice.has_wht_transaction) {
        return true;
    }

    if (showMessage) {
        showWarningToast(
            "WHT can only be applied once for Sales/Charge Invoice"
        );
    }

    return false;
};

const resetInvoiceWht = (invoice) => {
    const balance = getRealBalance(invoice);
    invoice.wht_error = "";
    invoice.apply_wht = "manual";
    invoice.wht_amount = 0;
    invoice.total_amount_less_wht = balance.toFixed(2);
    invoice.amountToPay = balance.toFixed(2);
};

const buildSelectedDocumentsPayload = () => {
    return selectedInvoices.value.map((inv) => {
        const matched = findMatchedDocument(inv);

        return {
            docunumber: inv.docunumber,
            type: inv.type,
            date: inv.date,
            amount: inv.amount,
            balance:
                matched && typeof matched.balance !== "undefined"
                    ? getRealBalance(matched)
                    : getRealBalance(inv),
            amountToPay: parseFloat(matched?.amountToPay) || 0,
            wht_amount: parseFloat(matched?.wht_amount) || 0,
            total_amount_less_wht:
                parseFloat(matched?.total_amount_less_wht) || 0,
        };
    });
};

const buildManualSubmitPayload = () => ({
    invoiceNumber: selectedInvoiceNumbers.value.join(", "),
    totalAmount: selectedTotalAmount.value,
    date: selectedDate.value,
    type: selectedInvoiceType.value.join(", "),
    totalAmountPaid: selectedTotalAmountPaid.value,
    editable_wht_mode: props.editable_wht,
    apply_bir_2307: applyBir2307.value,
    tax_rate: taxRate.value,
    selectedDocuments: buildSelectedDocumentsPayload(),
});

const getOverpaidDocuments = (payload) => {
    if (!Array.isArray(payload?.selectedDocuments)) {
        return [];
    }

    return payload.selectedDocuments
        .map((doc) => {
            const matched = findMatchedDocument(doc) || doc;
            const availableBalance =
                typeof matched.balance !== "undefined"
                    ? getRealBalance(matched)
                    : parseFloat(doc.balance) || 0;
            const requestedAmount = getRequestedDocumentAmount(doc);

            return {
                ...doc,
                availableBalance,
                requestedAmount,
            };
        })
        .filter(
            (doc) => doc.requestedAmount > (parseFloat(doc.availableBalance) || 0) + 0.009
        );
};

const clearPendingSubmission = () => {
    pendingSubmitPayload.value = null;
};

const submitNormalizedPayload = (payload) => {
    const overpaidDocuments = getOverpaidDocuments(payload);

    if (overpaidDocuments.length > 0 && !allowOverpayment.value) {
        return showWarningToast(
            "Overpayment is not allowed for this tenant."
        );
    }

    const overpaidDocumentMap = new Map(
        overpaidDocuments.map((doc) => [documentKey(doc), doc])
    );

    const normalizedPayload = {
        ...payload,
        selectedDocuments: (payload.selectedDocuments || []).map((doc) => {
            const overpaidDoc = overpaidDocumentMap.get(documentKey(doc));

            if (!overpaidDoc) {
                return {
                    ...doc,
                    overpayment_amount: Number(doc.overpayment_amount) || 0,
                };
            }

            return {
                ...doc,
                overpayment_amount: parseFloat(
                    Math.max(
                        0,
                        (overpaidDoc?.requestedAmount || 0) -
                            (overpaidDoc?.availableBalance || 0)
                    ).toFixed(2)
                ),
            };
        }),
    };

    clearPendingSubmission();
    emit("submit", normalizedPayload);
    closeModal();
};

const getFloatingPaidAmount = (invoice) => {
    const pdc = parseFloat(invoice.pdc_floating_amount) || 0;
    const dc = parseFloat(invoice.dc_floating_amount) || 0;
    const wht = parseFloat(invoice.wht_floating_amount) || 0;
    return pdc + dc + wht;
};

const getPaymentStatus = (invoice) => {
    const tags = [];
    if (invoice.has_pdc_floating_payments) tags.push("PDC");
    if (invoice.has_dc_floating_payments) tags.push("DC");
    if (invoice.has_wht_floating_payments) tags.push("WHT");

    if (tags.length === 0) return "Cleared";
    return `Floating (${tags.join("/")})`;
};

const getStatusBadgeClass = (invoice) => {
    const hasFloating =
        invoice.has_pdc_floating_payments ||
        invoice.has_dc_floating_payments ||
        invoice.has_wht_floating_payments;
    return hasFloating
        ? "bg-amber-700 text-amber-200"
        : "bg-emerald-700 text-emerald-200";
};

const handleCheckboxClick = (invoice, event) => {
    const balance = getRealBalance(invoice);
    const pdcfloating = parseFloat(invoice.pdc_floating_amount) || 0;
    const dcfloating = parseFloat(invoice.dc_floating_amount) || 0;
    const whtfloating = parseFloat(invoice.wht_floating_amount) || 0;

    totalFloatingAmount.value = pdcfloating + dcfloating + whtfloating;

    if (balance <= 0) {
        event.preventDefault();
        return (showInfoDialog.value = true);
    }

    // Only proceed with normal handling if validation passes
    handleCheckboxChange(invoice);
};

const isInvoiceSelected = (invoice) => {
    return selectedInvoices.value.some(
        (inv) =>
            inv.docunumber === invoice.docunumber && inv.type === invoice.type
    );
};

const handleCheckboxChange = (invoice) => {
    const realBalance = getRealBalance(invoice);

    invoice.amountToPay = realBalance.toFixed(2);
    invoice.total_amount_less_wht = realBalance.toFixed(2);
    if (/^\d+\.\d{3}$/.test(invoice.amountToPay)) {
        invoice.amountToPay = parseFloat((+invoice.amountToPay).toFixed(2));
        invoice.total_amount_less_wht = invoice.amountToPay;
    }

    const existingIndex = selectedInvoices.value.findIndex(
        (inv) =>
            inv.docunumber === invoice.docunumber && inv.type === invoice.type
    );

    if (existingIndex > -1) {
        // Remove from selection
        selectedInvoices.value.splice(existingIndex, 1);
        invoice.amountToPay = 0.0;
        invoice.total_amount_less_wht = 0.0;
    } else {
        // Add to selection
        selectedInvoices.value.push({
            docunumber: invoice.docunumber,
            type: invoice.type,
            date: invoice.date,
            amount: invoice.amount,
            amount_paid: invoice.amount_paid,
            wht_amount: invoice.wht_amount,
            total_amount_less_wht: invoice.total_amount_less_wht,
            balance: parseFloat(invoice.balance),
            amountToPay: parseFloat(invoice.amountToPay),
            pdc_floating_amount: parseFloat(invoice.pdc_floating_amount || 0),
            dc_floating_amount: parseFloat(invoice.dc_floating_amount || 0),
            wht_floating_amount: parseFloat(invoice.wht_floating_amount || 0),
            has_pdc_floating_payments: invoice.has_pdc_floating_payments,
            has_dc_floating_payments: invoice.has_dc_floating_payments,
            has_wht_floating_payments: invoice.has_wht_floating_payments,
        });

        if (props.whtEnabled && props.autoApplyWht) {
            setWhtType(invoice, "auto");
            return;
        }
    }

    updateSelectedValues();
};

const setWhtType = (invoice, type, showMessage = true) => {
    if (!isInvoiceSelected(invoice)) return;
    if (!props.whtEnabled) return;
    whtIsActive.value = true;

    invoice.wht_error = "";

    const balance = getRealBalance(invoice);

    if (type === "auto") {
        if (taxRate.value === "None") {
            invoice.apply_wht = "manual";
            onManualWhtInput(invoice);
            return;
        }

        if (!canApplyWhtToInvoice(invoice, showMessage)) {
            resetInvoiceWht(invoice);
            updateSelectedValues();
            return;
        }

        invoice.apply_wht = "auto";
        let rate = 0.01;
        if (taxRate.value === "2%") rate = 0.02;
        if (taxRate.value === "5%") rate = 0.05;

        const computedWht = balance * rate;
        const whtRounded = Math.round(computedWht * 100) / 100;
        const net = Math.max(0, balance - whtRounded);
        invoice.wht_amount = whtRounded.toFixed(2);
        invoice.total_amount_less_wht = net.toFixed(2);
        invoice.amountToPay = net.toFixed(2);
    }
    else {
        whtIsActive.value = false;

        // auto unchecked → reset
        invoice.apply_wht = "manual";
        invoice.wht_amount = 0;
        invoice.total_amount_less_wht = balance.toFixed(2);
        invoice.amountToPay = balance.toFixed(2);
    }

    updateSelectedValues();
};


const onManualWhtInput = (invoice, rawValue = invoice.wht_amount) => {
    if (!isInvoiceSelected(invoice)) return;
    if (!props.whtEnabled) return;
    if (taxRate.value !== "None") return;

    invoice.wht_error = "";
    whtIsActive.value = true;

    // force manual mode
    invoice.apply_wht = "manual";

    const balance = getRealBalance(invoice);

    if (rawValue === "" || rawValue === null || typeof rawValue === "undefined") {
        rawValue = "";
    }

    const cleaned = String(rawValue).replace(/,/g, "").trim();
    const normalized = cleaned.startsWith(".") ? `0${cleaned}` : cleaned;
    if (normalized !== "" && !/^\d*(\.\d*)?$/.test(normalized)) {
        invoice.wht_error = "Invalid tax amount";
        return;
    }

    let manualWht = normalized === "" ? 0 : parseFloat(normalized);
    if (!Number.isFinite(manualWht) || Number.isNaN(manualWht)) {
        manualWht = 0;
    }

    if (manualWht < 0) {
        invoice.wht_error = "Tax must be 0 or greater";
        showWarningToast("Tax must be 0 or greater");
        manualWht = 0;
    }

    if (invoice.wht_error === "") {
        invoice.wht_amount = normalized;
    }

    if (manualWht > 0 && !canApplyWhtToInvoice(invoice)) {
        resetInvoiceWht(invoice);
        updateSelectedValues();
        return;
    }

    // Safety: WHT must not exceed balance
    if (manualWht > balance) {
        manualWht = balance;
        invoice.wht_amount = balance.toFixed(2);
        showWarningToast("Tax exceeds balance. Automatically adjusted.");
    } else if (manualWht === 0) {
        whtIsActive.value = false;
        invoice.wht_amount = normalized;
    }
    invoice.total_amount_less_wht = Math.max(0, balance - manualWht).toFixed(2);
    invoice.amountToPay = Math.max(0, balance - manualWht).toFixed(2);

    updateSelectedValues();
};


const updateSelectedValues = () => {
    if (selectedInvoices.value.length === 0) {
        resetValues();
        return;
    }
    // Calculate total amount
    selectedTotalAmount.value = selectedInvoices.value.reduce(
        (sum, invoice) => sum + getRealBalance(invoice),
        0
    );

    // Find earliest date
    selectedDate.value = selectedInvoices.value.reduce(
        (earliest, invoice) =>
            new Date(invoice.date) < new Date(earliest)
                ? invoice.date
                : earliest,
        selectedInvoices.value[0].date
    );

    // Calculate floating amounts
    pdcfloatingAmount.value = selectedInvoices.value.reduce(
        (sum, invoice) => sum + invoice.pdc_floating_amount,
        0
    );
    dcfloatingAmount.value = selectedInvoices.value.reduce(
        (sum, invoice) => sum + invoice.dc_floating_amount,
        0
    );
    whtfloatingAmount.value = selectedInvoices.value.reduce(
        (sum, invoice) => sum + invoice.wht_floating_amount,
        0
    );

    // Check floating payments
    haspdcFloating.value = selectedInvoices.value.some(
        (invoice) => invoice.has_pdc_floating_payments
    );
    hasdcFloating.value = selectedInvoices.value.some(
        (invoice) => invoice.has_dc_floating_payments
    );
    haswhtFloating.value = selectedInvoices.value.some(
        (invoice) => invoice.has_wht_floating_payments
    );

    // Update the simple arrays for display
    selectedInvoiceNumbers.value = selectedInvoices.value.map(
        (inv) => inv.docunumber
    );
    const seenTypes = new Set();
    selectedInvoiceType.value = selectedInvoices.value
        .filter((inv) => {
            if (seenTypes.has(inv.type)) return false;
            seenTypes.add(inv.type);
            return true;
        })
        .map((inv) => inv.type);
};

function resetValues() {
    selectedInvoices.value = [];
    selectedInvoiceNumbers.value = [];
    selectedInvoiceType.value = [];
    selectedTotalAmount.value = 0;
    selectedDate.value = null;
    pdcfloatingAmount.value = 0;
    haspdcFloating.value = false;
    dcfloatingAmount.value = 0;
    hasdcFloating.value = false;
    whtfloatingAmount.value = 0;
    haswhtFloating.value = false;
}

const validateAmount = (invoice) => {
    if (!isInvoiceSelected(invoice)) return;

    const amount = parseFloat(invoice.amountToPay);
    const realBalance = getRealBalance(invoice);

    if (!Number.isFinite(amount) || Number.isNaN(amount) || amount < 0) {
        invoice.amountToPay = 0;
    }

    if (/^\d+\.\d{3}$/.test(invoice.amountToPay)) {
        invoice.amountToPay = parseFloat((+invoice.amountToPay).toFixed(2));
    }

    if ((parseFloat(invoice.amountToPay) || 0) > realBalance) {
        if (!allowOverpayment.value) {
            invoice.amountToPay = realBalance.toFixed(2);
            invoice.total_amount_less_wht = realBalance.toFixed(2);

            return showWarningToast(
                "Overpayment is not allowed. Amount adjusted to collectible balance."
            );
        }

        showWarningToast(
            "This amount exceeds the collectible balance and will be treated as overpayment."
        );
    }

    invoice.total_amount_less_wht = (
        parseFloat(invoice.amountToPay) || 0
    ).toFixed(2);
};

const syncManualAmountToPay = (invoice) => {
    if (!isInvoiceSelected(invoice)) return;

    validateAmount(invoice);
    invoice.total_amount_less_wht = (
        parseFloat(invoice.amountToPay) || 0
    ).toFixed(2);
    updateSelectedValues();
};

// watch(
//     () => documents.value?.map((inv) => inv.amountToPay),
//     () => {
//         documents.value?.forEach(validateAmount);
//     },
//     { deep: true }
// );

////////SHOW DOCU DIALOG ///////////////////////////
const confirmationMessageDoc = ref(null);
const showDialogDoc = ref(false);
const inv = ref(null);
const eve = ref(null);
const showDocMessage = async (invoice, event) => {
    if (
        invoice.amount_paid !== "0.00" ||
        invoice.has_dc_floating_payments ||
        invoice.has_pdc_floating_payments ||
        invoice.has_wht_floating_payments
    ) {
        handleCheckboxClick(invoice, event);
    } else {
        confirmationMessageDoc.value =
            "No WHT payment has been made for this document. Do you want to proceed?";
        showDialogDoc.value = true;
        inv.value = invoice;
        eve.value = event;
    }
};
const handleConfirmDoc = async (confirmed) => {
    showDialogDoc.value = false;
    if (confirmed) {
        if (!isInvoiceSelected(inv.value)) {
            handleCheckboxClick(inv.value, eve.value);
        }
    } else {
        handleCheckboxClick(inv.value, eve.value);
    }
    inv.value = "";
    eve.value = "";
};

const confirmationMessage = computed(() => {
    const messages = [];

    if (pdcfloatingAmount.value) {
        messages.push(
            `Total PDC Floating Payment of ${formatCurrency(
                pdcfloatingAmount.value
            )}`
        );
    }
    if (dcfloatingAmount.value) {
        messages.push(
            `Total DC Floating Payment of ${formatCurrency(
                dcfloatingAmount.value
            )}`
        );
    }
    if (whtfloatingAmount.value) {
        messages.push(
            `Total WHT Floating Payment of ${formatCurrency(
                whtfloatingAmount.value
            )}`
        );
    }

    if (payment_mode.value === "Manual Select") {
        return messages.length > 0
            ? `This Document Number/s Has Existing ${messages.join(
                " and "
            )}. Proceed?`
            : "";
    } else {
        return messages.length > 0
            ? `This Customer Has Existing Documents with ${messages.join(
                " and "
            )}. Proceed?`
            : "";
    }
});

////////SHOW DIALOG ///////////////////////////
const showDialog = ref(false);
const handleConfirm = async (confirmed) => {
    showDialog.value = false;
    if (confirmed) {
        if (payment_mode.value === "Manual Select") {
            const payload = {
                ...buildManualSubmitPayload(),
                floatingAmount: totalFloatingAmount.value,
            };
            submitNormalizedPayload(payload);
        } else {
            emit("submit", {
                invoiceNumber: selectedInvoiceNumbers.value.join(", "),
                totalAmount: selectedTotalAmount.value,
                date: selectedDate.value,
                floatingAmount: totalFloatingAmount.value,
                type: selectedInvoiceType.value.join(", "),
            });
        }

        if (payment_mode.value !== "Manual Select") {
            closeModal();
        }
    }
};
////////SHOW INFO DIALOG///////////////////////////
const showInfoDialog = ref(false);

const submitSelected = () => {
    if (payment_mode.value === "Manual Select") {
        if (selectedInvoices.value.length === 0) {
            return showWarningToast(
                "Please Select At Least One Document Number"
            );
        }

        const hasZeroAmount = selectedInvoices.value.some((inv) => {
            const matched = documents.value.find(
                (doc) =>
                    doc.docunumber === inv.docunumber && doc.type === inv.type
            );
            return parseFloat(matched?.amountToPay || 0) === 0;
        });

        if (hasZeroAmount) {
            return showWarningToast(
                "Please Provide An Amount To Pay For Every Selected Invoice"
            );
        }

        selectedTotalAmountPaid.value = selectedInvoices.value.reduce((sum, invoice) => {
            const matched = findMatchedDocument(invoice) || invoice;
            return (
                sum +
                (parseFloat(matched.amountToPay) || 0) +
                (parseFloat(matched.wht_amount) || 0)
            );
        }, 0);

        totalFloatingAmount.value = parseFloat(
            pdcfloatingAmount.value +
            dcfloatingAmount.value +
            whtfloatingAmount.value
        );
        if (
            haspdcFloating.value ||
            hasdcFloating.value ||
            haswhtFloating.value
        ) {
            // const remainingBalance =
            //     selectedTotalAmountPaid.value - totalFloatingAmount.value;
            //     console.log(remainingBalance)
            // if (remainingBalance === 0) {
            //     showInfoDialog.value = true;
            // } else {
            showDialog.value = true;
            // }
        } else {
            submitNormalizedPayload(buildManualSubmitPayload());
            // closeModal();
        }
    } else {
        const invoicesToSelect = documents.value;

        if (invoicesToSelect.length === 0) {
            return showWarningToast("No Documents Available For This Customer");
        }

        selectedInvoiceNumbers.value = ["Oldest to Newest Applied"];

        const seenTypes = new Set();

        selectedInvoiceType.value = invoicesToSelect
            .filter((inv) => {
                if (seenTypes.has(inv.type)) return false;
                seenTypes.add(inv.type);
                return true;
            })
            .map((inv) => inv.type);

        selectedTotalAmount.value = invoicesToSelect.reduce(
            (sum, invoice) => sum + getRealBalance(invoice),
            0
        );

        selectedDate.value = invoicesToSelect.reduce(
            (earliest, invoice) =>
                new Date(invoice.date) < new Date(earliest)
                    ? invoice.date
                    : earliest,
            invoicesToSelect[0].date
        );

        // Calculate floating amounts
        pdcfloatingAmount.value = invoicesToSelect.reduce(
            (sum, invoice) =>
                sum + parseFloat(invoice.pdc_floating_amount || 0),
            0
        );
        dcfloatingAmount.value = invoicesToSelect.reduce(
            (sum, invoice) =>
                sum + parseFloat(invoice.dc_floating_amount || 0),
            0
        );
        whtfloatingAmount.value = invoicesToSelect.reduce(
            (sum, invoice) =>
                sum + parseFloat(invoice.wht_floating_amount || 0),
            0
        );

        // Check floating payments
        haspdcFloating.value = invoicesToSelect.some(
            (invoice) => invoice.has_pdc_floating_payments
        );
        hasdcFloating.value = invoicesToSelect.some(
            (invoice) => invoice.has_dc_floating_payments
        );
        haswhtFloating.value = invoicesToSelect.some(
            (invoice) => invoice.has_wht_floating_payments
        );

        totalFloatingAmount.value = parseFloat(
            pdcfloatingAmount.value +
            dcfloatingAmount.value +
            whtfloatingAmount.value
        );

        // Handle floating payments confirmation
        if (
            haspdcFloating.value ||
            hasdcFloating.value ||
            haswhtFloating.value
        ) {
            if (selectedTotalAmount.value <= 0) {
                showInfoDialog.value = true;
            } else {
                showDialog.value = true;
            }
        } else {
            emit("submit", {
                invoiceNumber: selectedInvoiceNumbers.value.join(", "),
                totalAmount: selectedTotalAmount.value,
                date: selectedDate.value,
                type: selectedInvoiceType.value.join(", "),
            });
            closeModal();
        }
    }
};

////////// SHOW TOAST ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

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

const closeModal = () => {
    emit("close");
};

//SEARCH FUNCTION
const clearSearch = () => {
    searchQuery.value = ""; // Clear search input

    nextTick(() => {
        searchInput.value?.focus();
    });
};

// Debounced search
watch(
    () => [searchQuery.value, selectedTypeFilter.value],
    ([query, typeFilter]) => {
        isLoading.value = true;
        if (debounceTimeout) clearTimeout(debounceTimeout);

        debounceTimeout = setTimeout(() => {
            let filtered = documents.value.filter((document) =>
                isWithinSelectedReceiptDate(document.date)
            );

            // Apply search filter
            if (query && query.trim()) {
                filtered = filtered.filter((document) =>
                    document.docunumber
                        ?.toString()
                        .toLowerCase()
                        .includes(query.toLowerCase())
                );
            }

            // Apply type filter
            if (typeFilter !== "All Types") {
                filtered = filtered.filter(
                    (document) =>
                        typeFilter === "BG"
                            ? ["BG", "Beginning Balance"].includes(
                                  document.type
                              )
                            : document.type === typeFilter
                );
            }

            filteredData.value = filtered;
            isLoading.value = false;
        }, 400); // 400ms debounce
    }
);
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
