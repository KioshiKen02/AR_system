<template>
    <div class="fixed inset-0 z-60 flex items-center justify-center">
        <ToastAlert :show="showToast" :message="toastMessage" />
        <ToastAlertWarning :show="showWToast" :message="toastWMessage" />
        <div class="relative w-full h-full p-4">
            <div v-if="loading"
                class="fixed inset-0 bg-opacity-50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
                <div
                    class="w-full max-w-md bg-[var(--color-bg-secondary)] border border-[var(--color-border)] rounded-2xl shadow-xl overflow-hidden transition-all duration-300">
                    <div class="p-6 space-y-4">
                        <div class="flex items-center justify-center">
                            <h3 class="text-lg font-semibold text-[var(--color-text-primary)]">
                                {{ formData.file_type === 'Excel' ? 'Generating your Excel document...' : 'Generating your PDF document...' }}
                            </h3>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Modal content -->
            <div v-else
                class="relative flex flex-col h-full w-full bg-[var(--color-bg-secondary)] rounded-lg p-6 shadow-xl overflow-hidden">
                <!-- Modal header -->
                <div class="text-center" v-if="!formData.file_type || formData.file_type !== 'Excel'">
                    <h2 class="text-2xl font-bold text-[var(--color-text-primary)] tracking-wide">
                        DOCUMENT PREVIEW
                    </h2>
                    <div class="mt-2 h-0.5 bg-gradient-to-r from-transparent via-[var(--color-border)] to-transparent">
                    </div>
                </div>

                <!-- Modal body -->
                <div class="flex-1 overflow-auto p-4">
                    <!-- PDF preview -->
                    <iframe v-if="pdfUrl && !loading" :src="pdfUrl"
                        class="w-full h-full min-h-[500px] border border-[var(--color-border)] rounded-md"
                        frameborder="0"></iframe>

                    <!-- Error state -->
                    <div v-if="error" class="flex flex-col justify-center items-center h-full text-red-500 py-8">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-red-500" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <p class="mt-2">Failed To Load Document Preview.</p>
                        <p class="text-sm text-[var(--color-text-primary)]">
                            {{ error }}
                        </p>
                        <button @click="retryFetch"
                            class="mt-4 px-4 py-2 bg-[var(--color-primary)] text-white rounded hover:bg-[var(--color-primary-dark)] transition">
                            Retry
                        </button>
                    </div>
                </div>

                <!-- Modal footer -->
                <div class="mt-4 pt-2 border-t gap-2 border-[var(--color-border)] flex justify-end">
                    <button v-if="!loading" type="button" @click="closeModal" class="closeButton group">
                        <div class="flex justify-center items-center gap-2">
                            <span class="transition-transform duration-300 group-hover:rotate-180">
                                <svg-icon type="mdi" :path="mdiClose" class="w-5 h-5" />
                            </span>
                            Close
                        </div>
                    </button>
                    <button v-if="!loading" type="button" @click="printPdf()" class="submitButton group"
                        :disabled="!pdfUrl || loading">
                        <div class="flex justify-center items-center gap-2">
                            <span class="transition-transform duration-300 group-hover:rotate-360">
                                <svg-icon type="mdi" :path="mdiPrinterOutline" class="w-5 h-5" />
                            </span>
                            <span v-if="!pdfUrl">Printing...</span>
                            <span v-else>Print</span>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { mdiClose, mdiPrinterOutline } from "@mdi/js";
import { ref, watch, onMounted, onUnmounted } from "vue";
import { route } from "../../../vendor/tightenco/ziggy/src/js";
import { usePage } from "@inertiajs/vue3";
import { saveAs } from "file-saver"; // Import file-saver
import ExcelJS from "exceljs";
import ToastAlert from "../Pages/Components/ToastAlert.vue";
import ToastAlertWarning from "../Pages/Components/ToastAlertWarning.vue";

const props = defineProps({
    show: Boolean,
    formData: {
        type: Object,
        required: true,
    },
    apiEndpoint: {
        type: String,
    },
});

const emit = defineEmits(["close", "closeSuccess"]);

const pdfUrl = ref(null);
const loading = ref(false);
const error = ref(null);
const channel = ref(null);
let echo = null;
const userId = ref(null);
const page = usePage();
let channelInstance = null;
const pathDelete = ref(null);

//WARNING TOAST
const showToast = ref(false);
const toastMessage = ref("");
const showWToast = ref(false);
const toastWMessage = ref("");

let toastTimeout = null;
let toastWTimeout = null;
const showWarningToast = (message) => {
    toastWMessage.value = message;
    showWToast.value = false;
    if (toastWTimeout) clearTimeout(toastWTimeout);

    setTimeout(() => {
        showWToast.value = true;
    }, 0);

    toastWTimeout = setTimeout(() => {
        showWToast.value = false;
        toastWTimeout = null;
    }, 3000);
};

const showSuccessToast = (message) => {
    toastMessage.value = message;
    showToast.value = true;

    if (toastTimeout) clearTimeout(toastTimeout);

    setTimeout(() => {
        showToast.value = true;
    }, 0);

    toastTimeout = setTimeout(() => {
        showToast.value = false;
        toastTimeout = null;
    }, 3000);
};

const closeModal = async () => {
    await deletePdf();

    loading.value = false;
    error.value = null;
    emit("close");
};

const retryFetch = () => {
    error.value = null;
    startPdfGeneration();
};

const startPdfGeneration = async () => {
    try {
        loading.value = true;
        error.value = null;
        const response = await axios.post(route(props.apiEndpoint, { tenant: page.props.tenant }), {
            ...props.formData,
        });

        if (response.data.excelData) {
            loading.value = false;
            const endpoint = props.apiEndpoint;
            
            if (endpoint === 'invoiceReport') {
                await generateInvoiceProoflistExcelFile(response.data.excelData);
            } else if (endpoint === 'invoiceReportSummary') {
                await generateInvoiceSummaryExcelFile(response.data.excelData);
            } else if (endpoint === 'adjustmentReport') {
                 await generateAdjustmentProoflistExcelFile(response.data.excelData);
            } else if (endpoint === 'paymentReport') {
                 if (props.formData.paymentProoflistType === 'Detailed') {
                     await generatePaymentProoflistDetailedExcelFile(response.data.excelData);
                 } else {
                     await generatePaymentProoflistSummaryExcelFile(response.data.excelData);
                 }
            } else if (endpoint === 'pdcDcReport') {
                 await generatePdcDcReportExcelFile(response.data.excelData);
            } else if (endpoint === 'customerArAging') {
                 await generateCustomerArAgingExcelFile(response.data.excelData);
            } else if (endpoint === 'begBalProoflist') {
                 await generateBegBalProoflistExcelFile(response.data.excelData);
            } else if (endpoint === 'arOutstandingBalanceAO') {
                 await generateAROutstandingExcelFile(response.data.excelData);
            } else if (endpoint === 'arOutstandingBalanceDR') {
                 await generateAROutstandingExcelFile(response.data.excelData);
            } else if (endpoint === 'salesPerItem') {
                 await generateSalesPerItemExcelFile(response.data.excelData);
            } else if (endpoint === 'overageShortage') {
                 await generateOverageShortageExcelFile(response.data.excelData);
            } else if (endpoint === 'statementOfAccount') {
                 await generateStatementOfAccountExcelFile(response.data.excelData);
            } else if (endpoint === 'statementOfAccountSummary') {
                 await generateStatementOfAccountSummaryExcelFile(response.data.excelData);
            } else {
                error.value = "Excel generation for this report is not yet supported via direct download.";
                return;
            }
            emit("closeSuccess");
            return;
        }

        // Check for HTML response (redirect to login/dashboard)
        if (response.headers['content-type'] && response.headers['content-type'].includes('text/html')) {
             throw new Error("Session expired or invalid request. Please refresh the page and try again.");
        }

        if (response.data.url) {
            loading.value = false;
            pdfUrl.value = response.data.url;
            pathDelete.value = response.data.url; 

            return;
        }

        if (response.data.channel && !response.data.url) {
            error.value = "Server did not return a download URL. Please contact support.";
            loading.value = false;
        }
    } catch (err) {
        console.error("Error starting PDF generation:", err);
        
        // Handle 404 specifically for route not found issues
        if (err.response && err.response.status === 404) {
             error.value = "Report generation endpoint not found. Please check your network connection or contact support.";
        } else {
            error.value =
                err.response?.data?.message ||
                err.message ||
                "Failed to start PDF generation";
        }
        
        loading.value = false;
    }
};


const generateInvoiceProoflistExcelFile = async (excelData) => {
    try {
        const workbook = new ExcelJS.Workbook();
        const worksheet = workbook.addWorksheet("Invoice Prooflist");

        let currentRow = 1;

        // Top-right information
        worksheet.getCell("H1").value = `Run Date/Time: ${excelData.runDateTime}`;
        worksheet.getCell("H2").value = "Note: This document is not valid without complete signatory.";
        worksheet.getCell("H1").font = { size: 9 };
        worksheet.getCell("H2").font = { size: 9, color: { argb: "e74c3c" } };
        worksheet.getCell("H1").alignment = { horizontal: "right" };
        worksheet.getCell("H2").alignment = { horizontal: "right" };

        // Header section
        currentRow = 4;
        worksheet.getCell(`A${currentRow}`).value = excelData.reportName;
        worksheet.getCell(`A${currentRow}`).font = { bold: true, size: 16 };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Invoice Prooflist Report";
        worksheet.getCell(`A${currentRow}`).font = { size: 12 };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = `Date Range: ${excelData.dateRange}`;
        worksheet.getCell(`A${currentRow}`).font = { bold: true };

        currentRow += 2;

        // Process grouped data
        excelData.groupedData.forEach((group) => {
            // Customer header
            worksheet.getCell(`A${currentRow}`).value = `${group.customer_code} - ${group.customer_name}`;
            worksheet.getCell(`A${currentRow}`).font = { bold: true };
            worksheet.mergeCells(`A${currentRow}:H${currentRow}`);

            currentRow++;

            // Table headers
            const headers = [
                "INVOICE NO",
                "DATE",
                "REFERENCE NO",
                "PARTICULAR",
                "ITEMS",
                "CASH AMOUNT",
                "AR AMOUNT",
                "VAT",
            ];

            const headerRow = worksheet.getRow(currentRow);
            headers.forEach((header, index) => {
                const cell = headerRow.getCell(index + 1);
                cell.value = header;
                cell.font = { bold: true, size: 10 };
                cell.alignment = { horizontal: "center", vertical: "middle" };
                cell.border = {
                    top: { style: "thin" },
                    left: { style: "thin" },
                    bottom: { style: "thin" },
                    right: { style: "thin" },
                };
                cell.fill = {
                    type: "pattern",
                    pattern: "solid",
                    fgColor: { argb: "f0f0f0" },
                };
            });

            currentRow++;

            // Data rows
            group.invoices.forEach((invoice) => {
                const dataRow = worksheet.getRow(currentRow);
                
                dataRow.getCell(1).value = invoice.invoice_no;
                dataRow.getCell(2).value = new Date(invoice.transaction_date);
                dataRow.getCell(3).value = invoice.reference_no;
                dataRow.getCell(4).value = invoice.particular;
                
                // Format items
                const itemsText = invoice.items.map(item => `${item.item_name} (${item.item_code})`).join(', ');
                dataRow.getCell(5).value = itemsText;
                
                dataRow.getCell(6).value = parseFloat(invoice.cash_amount) || 0;
                dataRow.getCell(7).value = parseFloat(invoice.ar_amount) || 0;
                dataRow.getCell(8).value = parseFloat(invoice.vat_amount ?? 0) || 0;

                // Format cells
                dataRow.getCell(1).alignment = { horizontal: "center" };
                dataRow.getCell(2).alignment = { horizontal: "center" };
                dataRow.getCell(2).numFmt = "mm/dd/yyyy";
                dataRow.getCell(3).alignment = { horizontal: "center" };
                dataRow.getCell(4).alignment = { horizontal: "left" };
                dataRow.getCell(5).alignment = { horizontal: "left", wrapText: true };
                
                dataRow.getCell(6).numFmt = "#,##0.00";
                dataRow.getCell(6).alignment = { horizontal: "right" };
                dataRow.getCell(7).numFmt = "#,##0.00";
                dataRow.getCell(7).alignment = { horizontal: "right" };
                dataRow.getCell(8).numFmt = "#,##0.00";
                dataRow.getCell(8).alignment = { horizontal: "right" };

                // Borders
                dataRow.eachCell((cell) => {
                    cell.border = {
                        top: { style: "thin" },
                        left: { style: "thin" },
                        bottom: { style: "thin" },
                        right: { style: "thin" },
                    };
                });

                currentRow++;
            });

            // Customer Subtotal
            const subtotalRow = worksheet.getRow(currentRow);
            subtotalRow.getCell(5).value = "Sub Total :";
            subtotalRow.getCell(6).value = parseFloat(group.customer_cash_total) || 0;
            subtotalRow.getCell(7).value = parseFloat(group.customer_ar_total) || 0;
            subtotalRow.getCell(8).value = parseFloat(group.customer_vat_total ?? 0) || 0;

            subtotalRow.getCell(5).font = { bold: true };
            subtotalRow.getCell(5).alignment = { horizontal: "right" };
            
            [6, 7, 8].forEach(col => {
                subtotalRow.getCell(col).font = { bold: true };
                subtotalRow.getCell(col).numFmt = "#,##0.00";
                subtotalRow.getCell(col).alignment = { horizontal: "right" };
                subtotalRow.getCell(col).border = {
                     top: { style: "thin" },
                     bottom: { style: "double" }
                };
            });

            currentRow += 2;
        });

        // Grand Total
        const totalRow = worksheet.getRow(currentRow);
        totalRow.getCell(5).value = "Grand Total :";
        totalRow.getCell(6).value = parseFloat(excelData.grandTotalCash) || 0;
        totalRow.getCell(7).value = parseFloat(excelData.grandTotalAR) || 0;
        totalRow.getCell(8).value = parseFloat(excelData.grandTotalVat ?? 0) || 0;

        totalRow.getCell(5).font = { bold: true, size: 12 };
        totalRow.getCell(5).alignment = { horizontal: "right" };
        
        [6, 7, 8].forEach(col => {
            totalRow.getCell(col).font = { bold: true, size: 12 };
            totalRow.getCell(col).numFmt = "#,##0.00";
            totalRow.getCell(col).alignment = { horizontal: "right" };
             totalRow.getCell(col).border = {
                 top: { style: "thick" },
                 bottom: { style: "thick" }
            };
        });

        currentRow += 3;
        
        // Signatory section
        const signatoryStartRow = currentRow;

        // Headers for signatory section
        worksheet.getCell(`A${currentRow}`).value = "Prepared By:";
        worksheet.getCell(`E${currentRow}`).value = "Checked By:";
        worksheet.getCell(`I${currentRow}`).value = "Note By:";

        ["A", "E", "I"].forEach((col) => {
            worksheet.getCell(`${col}${currentRow}`).font = { bold: true };
        });

        currentRow += 2;

        // Prepared By section
        worksheet.getCell(`A${currentRow}`).value = excelData.preparedBy;
        worksheet.getCell(`A${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.mergeCells(`A${currentRow}:D${currentRow}`);
        worksheet.getCell(`A${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value =
            "(Signature Over Printed Name)";
        worksheet.getCell(`A${currentRow}`).alignment = {
            horizontal: "center",
        };
        worksheet.getCell(`A${currentRow}`).font = { size: 9 };
        worksheet.mergeCells(`A${currentRow}:D${currentRow}`);

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Date:";
        worksheet.getCell(`B${currentRow}`).value =
            new Date().toLocaleDateString();
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.getCell(`B${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Time:";
        worksheet.getCell(`B${currentRow}`).value =
            new Date().toLocaleTimeString();
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.getCell(`B${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Designation:";
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.mergeCells(`B${currentRow}:D${currentRow}`);

        // Add similar structure for "Checked By" and "Note By" columns
        const checkByRow = signatoryStartRow + 2;

        // Checked By and Note By sections
        ["E", "I"].forEach((col) => {
            worksheet.getCell(`${col}${checkByRow}`).border = {
                bottom: { style: "thin" },
            };
            worksheet.mergeCells(
                `${col}${checkByRow}:${String.fromCharCode(
                    col.charCodeAt(0) + 3
                )}${checkByRow}`
            );

            worksheet.getCell(`${col}${checkByRow + 1}`).value =
                "(Signature Over Printed Name)";
            worksheet.getCell(`${col}${checkByRow + 1}`).alignment = {
                horizontal: "center",
            };
            worksheet.getCell(`${col}${checkByRow + 1}`).font = { size: 9 };
            worksheet.mergeCells(
                `${col}${checkByRow + 1}:${String.fromCharCode(
                    col.charCodeAt(0) + 3
                )}${checkByRow + 1}`
            );

            worksheet.getCell(`${col}${checkByRow + 2}`).value = "Date:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 2}`
            ).border = { bottom: { style: "thin" } };

            worksheet.getCell(`${col}${checkByRow + 3}`).value = "Time:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 3}`
            ).border = { bottom: { style: "thin" } };

            worksheet.getCell(`${col}${checkByRow + 4}`).value = "Designation:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 4}`
            ).border = { bottom: { style: "thin" } };
            worksheet.mergeCells(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 4
                }:${String.fromCharCode(col.charCodeAt(0) + 3)}${checkByRow + 4
                }`
            );
        });

        // Set column widths
        worksheet.columns = [
            { width: 15 }, // Invoice No
            { width: 12 }, // Date
            { width: 15 }, // Ref No
            { width: 25 }, // Particular
            { width: 40 }, // Items
            { width: 15 }, // Cash
            { width: 15 }, // AR
            { width: 10 }, 
        ];

        // Generate filename
        const currentDate = new Date().toISOString().split("T")[0];
        const filename = `Invoice_Prooflist_${currentDate}.xlsx`;

        // Generate blob and download
        const buffer = await workbook.xlsx.writeBuffer();
        const blob = new Blob([buffer], {
            type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        });

        saveAs(blob, filename);
        showSuccessToast("Invoice Prooflist report exported successfully!");

    } catch (error) {
        console.error("Error generating Invoice Prooflist Excel file:", error);
        showWarningToast("Failed to generate Invoice Prooflist Excel file.");
        throw error;
    }
};

const generateInvoiceSummaryExcelFile = async (excelData) => {
    try {
        const workbook = new ExcelJS.Workbook();
        const worksheet = workbook.addWorksheet("Invoice Summary");

        let currentRow = 1;

        // Top-right information
        worksheet.getCell("K1").value = `Run Date/Time: ${excelData.runDateTime}`;
        worksheet.getCell("K2").value = "Note: This document is not valid without complete signatory.";
        worksheet.getCell("K1").font = { size: 9 };
        worksheet.getCell("K2").font = { size: 9, color: { argb: "e74c3c" } };
        worksheet.getCell("K1").alignment = { horizontal: "right" };
        worksheet.getCell("K2").alignment = { horizontal: "right" };

        // Header section
        currentRow = 4;
        worksheet.getCell(`A${currentRow}`).value = excelData.reportName;
        worksheet.getCell(`A${currentRow}`).font = { bold: true, size: 16 };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Invoice Summary Report";
        worksheet.getCell(`A${currentRow}`).font = { size: 12 };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = `Date Range: ${excelData.dateRange}`;
        worksheet.getCell(`A${currentRow}`).font = { bold: true };

        currentRow += 2;

        // Table headers
        const headers = [
            "CUSTOMER CODE",
            "CUSTOMER NAME",
            "INVOICE NO",
            "DATE",
            "REFERENCE NO",
            "PARTICULAR",
            "ITEMS",
            "BASE AMOUNT",
            "VAT AMOUNT",
            "AR NET AMOUNT",
        ];

        const headerRow = worksheet.getRow(currentRow);
        headers.forEach((header, index) => {
            const cell = headerRow.getCell(index + 1);
            cell.value = header;
            cell.font = { bold: true, size: 10 };
            cell.alignment = { horizontal: "center", vertical: "middle" };
            cell.border = {
                top: { style: "thin" },
                left: { style: "thin" },
                bottom: { style: "thin" },
                right: { style: "thin" },
            };
            cell.fill = {
                type: "pattern",
                pattern: "solid",
                fgColor: { argb: "f0f0f0" },
            };
        });

        currentRow++;

        // Data rows
        excelData.invoices.forEach((invoice) => {
            const dataRow = worksheet.getRow(currentRow);
            
            dataRow.getCell(1).value = invoice.customer_code;
            dataRow.getCell(2).value = invoice.customer_name;
            dataRow.getCell(3).value = invoice.invoice_no;
            dataRow.getCell(4).value = new Date(invoice.transaction_date);
            dataRow.getCell(5).value = invoice.reference_no;
            dataRow.getCell(6).value = invoice.particular;
            
            // Format items
            const itemsText = invoice.items.map(item => `${item.item_name} (${item.item_code})`).join(', ');
            dataRow.getCell(7).value = itemsText;
            
            dataRow.getCell(8).value = parseFloat(invoice.base_amount) || 0;
            dataRow.getCell(9).value = parseFloat(invoice.vat_amount) || 0;
            dataRow.getCell(10).value = parseFloat(invoice.ar_net_amount) || 0;

            // Format cells
            dataRow.getCell(1).alignment = { horizontal: "center" };
            dataRow.getCell(2).alignment = { horizontal: "left" };
            dataRow.getCell(3).alignment = { horizontal: "center" };
            dataRow.getCell(4).alignment = { horizontal: "center" };
            dataRow.getCell(4).numFmt = "mm/dd/yyyy";
            dataRow.getCell(5).alignment = { horizontal: "center" };
            dataRow.getCell(6).alignment = { horizontal: "left" };
            dataRow.getCell(7).alignment = { horizontal: "left", wrapText: true };
            
            [8, 9, 10].forEach(col => {
                dataRow.getCell(col).numFmt = "#,##0.00";
                dataRow.getCell(col).alignment = { horizontal: "right" };
            });

            // Borders
            dataRow.eachCell((cell) => {
                cell.border = {
                    top: { style: "thin" },
                    left: { style: "thin" },
                    bottom: { style: "thin" },
                    right: { style: "thin" },
                };
            });

            currentRow++;
        });

        // Grand Total
        const totalRow = worksheet.getRow(currentRow);
        totalRow.getCell(7).value = "Grand Total :";
        totalRow.getCell(8).value = parseFloat(excelData.grandTotalBase) || 0;
        totalRow.getCell(9).value = parseFloat(excelData.grandTotalVat) || 0;
        totalRow.getCell(10).value = parseFloat(excelData.grandTotalAR) || 0;

        totalRow.getCell(7).font = { bold: true, size: 12 };
        totalRow.getCell(7).alignment = { horizontal: "right" };
        
        [8, 9, 10, 11].forEach(col => {
            totalRow.getCell(col).font = { bold: true, size: 12 };
            totalRow.getCell(col).numFmt = "#,##0.00";
            totalRow.getCell(col).alignment = { horizontal: "right" };
             totalRow.getCell(col).border = {
                 top: { style: "thick" },
                 bottom: { style: "thick" }
            };
        });

        currentRow += 3;
        
        // Signatory section
        const signatoryStartRow = currentRow;

        // Headers for signatory section
        worksheet.getCell(`A${currentRow}`).value = "Prepared By:";
        worksheet.getCell(`E${currentRow}`).value = "Checked By:";
        worksheet.getCell(`I${currentRow}`).value = "Note By:";

        ["A", "E", "I"].forEach((col) => {
            worksheet.getCell(`${col}${currentRow}`).font = { bold: true };
        });

        currentRow += 2;

        // Prepared By section
        worksheet.getCell(`A${currentRow}`).value = excelData.preparedBy;
        worksheet.getCell(`A${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.mergeCells(`A${currentRow}:D${currentRow}`);
        worksheet.getCell(`A${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value =
            "(Signature Over Printed Name)";
        worksheet.getCell(`A${currentRow}`).alignment = {
            horizontal: "center",
        };
        worksheet.getCell(`A${currentRow}`).font = { size: 9 };
        worksheet.mergeCells(`A${currentRow}:D${currentRow}`);

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Date:";
        worksheet.getCell(`B${currentRow}`).value =
            new Date().toLocaleDateString();
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.getCell(`B${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Time:";
        worksheet.getCell(`B${currentRow}`).value =
            new Date().toLocaleTimeString();
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.getCell(`B${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Designation:";
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.mergeCells(`B${currentRow}:D${currentRow}`);

        // Add similar structure for "Checked By" and "Note By" columns
        const checkByRow = signatoryStartRow + 2;

        // Checked By and Note By sections
        ["E", "I"].forEach((col) => {
            worksheet.getCell(`${col}${checkByRow}`).border = {
                bottom: { style: "thin" },
            };
            worksheet.mergeCells(
                `${col}${checkByRow}:${String.fromCharCode(
                    col.charCodeAt(0) + 3
                )}${checkByRow}`
            );

            worksheet.getCell(`${col}${checkByRow + 1}`).value =
                "(Signature Over Printed Name)";
            worksheet.getCell(`${col}${checkByRow + 1}`).alignment = {
                horizontal: "center",
            };
            worksheet.getCell(`${col}${checkByRow + 1}`).font = { size: 9 };
            worksheet.mergeCells(
                `${col}${checkByRow + 1}:${String.fromCharCode(
                    col.charCodeAt(0) + 3
                )}${checkByRow + 1}`
            );

            worksheet.getCell(`${col}${checkByRow + 2}`).value = "Date:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 2}`
            ).border = { bottom: { style: "thin" } };

            worksheet.getCell(`${col}${checkByRow + 3}`).value = "Time:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 3}`
            ).border = { bottom: { style: "thin" } };

            worksheet.getCell(`${col}${checkByRow + 4}`).value = "Designation:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 4}`
            ).border = { bottom: { style: "thin" } };
            worksheet.mergeCells(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 4
                }:${String.fromCharCode(col.charCodeAt(0) + 3)}${checkByRow + 4
                }`
            );
        });

        // Set column widths
        worksheet.columns = [
            { width: 15 }, // Customer Code
            { width: 30 }, // Customer Name
            { width: 15 }, // Invoice No
            { width: 12 }, // Date
            { width: 15 }, // Ref No
            { width: 25 }, // Particular
            { width: 40 }, // Items
            { width: 15 }, // Base
            { width: 15 }, // VAT
            { width: 18 }, // AR Net
        ];

        // Generate filename
        const currentDate = new Date().toISOString().split("T")[0];
        const filename = `Invoice_Summary_${currentDate}.xlsx`;

        // Generate blob and download
        const buffer = await workbook.xlsx.writeBuffer();
        const blob = new Blob([buffer], {
            type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        });

        saveAs(blob, filename);
        showSuccessToast("Invoice Summary report exported successfully!");

    } catch (error) {
        console.error("Error generating Invoice Summary Excel file:", error);
        showWarningToast("Failed to generate Invoice Summary Excel file.");
        throw error;
    }
};

const generateAdjustmentProoflistExcelFile = async (excelData) => {
    try {
        const workbook = new ExcelJS.Workbook();
        const worksheet = workbook.addWorksheet("Adjustment Prooflist");

        let currentRow = 1;

        // Top-right information
        worksheet.getCell("I1").value = `Run Date/Time: ${excelData.runDateTime}`;
        worksheet.getCell("I2").value = "Note: This document is not valid without complete signatory.";
        worksheet.getCell("I1").font = { size: 9 };
        worksheet.getCell("I2").font = { size: 9, color: { argb: "e74c3c" } };
        worksheet.getCell("I1").alignment = { horizontal: "right" };
        worksheet.getCell("I2").alignment = { horizontal: "right" };

        // Header section
        currentRow = 4;
        worksheet.getCell(`A${currentRow}`).value = excelData.reportName;
        worksheet.getCell(`A${currentRow}`).font = { bold: true, size: 16 };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Adjustment Prooflist Report";
        worksheet.getCell(`A${currentRow}`).font = { size: 12 };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = `Date Range: ${excelData.dateRange}`;
        worksheet.getCell(`A${currentRow}`).font = { bold: true };

        currentRow += 2;

        // Process grouped data
        excelData.groupedData.forEach((group) => {
            // Customer header
            worksheet.getCell(`A${currentRow}`).value = `${group.customer_code} - ${group.customer_name}`;
            worksheet.getCell(`A${currentRow}`).font = { bold: true };
            worksheet.mergeCells(`A${currentRow}:I${currentRow}`);

            currentRow++;

            // Table headers
            const headers = [
                "ADJUSTMENT NO",
                "DATE",
                "TYPE",
                "APPLY TO",
                "INVOICE NO",
                "REASON",
                "PARTICULARS",
                "AMOUNT",
                "BALANCE",
            ];

            const headerRow = worksheet.getRow(currentRow);
            headers.forEach((header, index) => {
                const cell = headerRow.getCell(index + 1);
                cell.value = header;
                cell.font = { bold: true, size: 10 };
                cell.alignment = { horizontal: "center", vertical: "middle" };
                cell.border = {
                    top: { style: "thin" },
                    left: { style: "thin" },
                    bottom: { style: "thin" },
                    right: { style: "thin" },
                };
                cell.fill = {
                    type: "pattern",
                    pattern: "solid",
                    fgColor: { argb: "f0f0f0" },
                };
            });

            currentRow++;

            // Data rows
            group.adjustments.forEach((adj) => {
                const dataRow = worksheet.getRow(currentRow);
                
                dataRow.getCell(1).value = adj.adjustment_no;
                dataRow.getCell(2).value = new Date(adj.transaction_date);
                dataRow.getCell(3).value = adj.type;
                dataRow.getCell(4).value = adj.apply_to;
                dataRow.getCell(5).value = adj.invoice_no;
                dataRow.getCell(6).value = adj.adjustment_reason;
                dataRow.getCell(7).value = adj.particulars;
                
                dataRow.getCell(8).value = parseFloat(adj.amount) || 0;
                dataRow.getCell(9).value = parseFloat(adj.balance) || 0;

                // Format cells
                dataRow.getCell(1).alignment = { horizontal: "center" };
                dataRow.getCell(2).alignment = { horizontal: "center" };
                dataRow.getCell(2).numFmt = "mm/dd/yyyy";
                dataRow.getCell(3).alignment = { horizontal: "center" };
                dataRow.getCell(4).alignment = { horizontal: "center" };
                dataRow.getCell(5).alignment = { horizontal: "center" };
                dataRow.getCell(6).alignment = { horizontal: "left", wrapText: true };
                dataRow.getCell(7).alignment = { horizontal: "left", wrapText: true };
                
                dataRow.getCell(8).numFmt = "#,##0.00";
                dataRow.getCell(8).alignment = { horizontal: "right" };
                dataRow.getCell(9).numFmt = "#,##0.00";
                dataRow.getCell(9).alignment = { horizontal: "right" };

                // Borders
                dataRow.eachCell((cell) => {
                    cell.border = {
                        top: { style: "thin" },
                        left: { style: "thin" },
                        bottom: { style: "thin" },
                        right: { style: "thin" },
                    };
                });

                currentRow++;
            });

            // Customer Subtotal
            const subtotalRow = worksheet.getRow(currentRow);
            subtotalRow.getCell(7).value = "Sub Total :";
            subtotalRow.getCell(8).value = parseFloat(group.customerAmountTotal) || 0;

            subtotalRow.getCell(7).font = { bold: true };
            subtotalRow.getCell(7).alignment = { horizontal: "right" };
            
            subtotalRow.getCell(8).font = { bold: true };
            subtotalRow.getCell(8).numFmt = "#,##0.00";
            subtotalRow.getCell(8).alignment = { horizontal: "right" };
            subtotalRow.getCell(8).border = {
                 top: { style: "thin" },
                 bottom: { style: "double" }
            };

            currentRow += 2;
        });

        // Grand Total
        const totalRow = worksheet.getRow(currentRow);
        totalRow.getCell(7).value = "Grand Total :";
        totalRow.getCell(8).value = parseFloat(excelData.customerOverallAmountTotal) || 0;

        totalRow.getCell(7).font = { bold: true, size: 12 };
        totalRow.getCell(7).alignment = { horizontal: "right" };
        
        totalRow.getCell(8).font = { bold: true, size: 12 };
        totalRow.getCell(8).numFmt = "#,##0.00";
        totalRow.getCell(8).alignment = { horizontal: "right" };
         totalRow.getCell(8).border = {
             top: { style: "thick" },
             bottom: { style: "thick" }
        };

        currentRow += 3;
        
        // Signatory section
        const signatoryStartRow = currentRow;

        // Headers for signatory section
        worksheet.getCell(`A${currentRow}`).value = "Prepared By:";
        worksheet.getCell(`E${currentRow}`).value = "Checked By:";
        worksheet.getCell(`I${currentRow}`).value = "Note By:";

        ["A", "E", "I"].forEach((col) => {
            worksheet.getCell(`${col}${currentRow}`).font = { bold: true };
        });

        currentRow += 2;

        // Prepared By section
        worksheet.getCell(`A${currentRow}`).value = excelData.preparedBy;
        worksheet.getCell(`A${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.mergeCells(`A${currentRow}:D${currentRow}`);
        worksheet.getCell(`A${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value =
            "(Signature Over Printed Name)";
        worksheet.getCell(`A${currentRow}`).alignment = {
            horizontal: "center",
        };
        worksheet.getCell(`A${currentRow}`).font = { size: 9 };
        worksheet.mergeCells(`A${currentRow}:D${currentRow}`);

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Date:";
        worksheet.getCell(`B${currentRow}`).value =
            new Date().toLocaleDateString();
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.getCell(`B${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Time:";
        worksheet.getCell(`B${currentRow}`).value =
            new Date().toLocaleTimeString();
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.getCell(`B${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Designation:";
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.mergeCells(`B${currentRow}:D${currentRow}`);

        // Add similar structure for "Checked By" and "Note By" columns
        const checkByRow = signatoryStartRow + 2;

        // Checked By and Note By sections
        ["E", "I"].forEach((col) => {
            worksheet.getCell(`${col}${checkByRow}`).border = {
                bottom: { style: "thin" },
            };
            worksheet.mergeCells(
                `${col}${checkByRow}:${String.fromCharCode(
                    col.charCodeAt(0) + 3
                )}${checkByRow}`
            );

            worksheet.getCell(`${col}${checkByRow + 1}`).value =
                "(Signature Over Printed Name)";
            worksheet.getCell(`${col}${checkByRow + 1}`).alignment = {
                horizontal: "center",
            };
            worksheet.getCell(`${col}${checkByRow + 1}`).font = { size: 9 };
            worksheet.mergeCells(
                `${col}${checkByRow + 1}:${String.fromCharCode(
                    col.charCodeAt(0) + 3
                )}${checkByRow + 1}`
            );

            worksheet.getCell(`${col}${checkByRow + 2}`).value = "Date:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 2}`
            ).border = { bottom: { style: "thin" } };

            worksheet.getCell(`${col}${checkByRow + 3}`).value = "Time:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 3}`
            ).border = { bottom: { style: "thin" } };

            worksheet.getCell(`${col}${checkByRow + 4}`).value = "Designation:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 4}`
            ).border = { bottom: { style: "thin" } };
            worksheet.mergeCells(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 4
                }:${String.fromCharCode(col.charCodeAt(0) + 3)}${checkByRow + 4
                }`
            );
        });

        // Set column widths
        worksheet.columns = [
            { width: 15 }, // Adj No
            { width: 12 }, // Date
            { width: 15 }, // Type
            { width: 15 }, // Apply To
            { width: 15 }, // Invoice No
            { width: 30 }, // Reason
            { width: 30 }, // Particulars
            { width: 15 }, // Amount
            { width: 15 }, // Balance
        ];

        // Generate filename
        const currentDate = new Date().toISOString().split("T")[0];
        const filename = `Adjustment_Prooflist_${currentDate}.xlsx`;

        // Generate blob and download
        const buffer = await workbook.xlsx.writeBuffer();
        const blob = new Blob([buffer], {
            type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        });

        saveAs(blob, filename);
        showSuccessToast("Adjustment Prooflist report exported successfully!");

    } catch (error) {
        console.error("Error generating Adjustment Prooflist Excel file:", error);
        showWarningToast("Failed to generate Adjustment Prooflist Excel file.");
        throw error;
    }
};

const generatePaymentProoflistDetailedExcelFile = async (excelData) => {
    try {
        const workbook = new ExcelJS.Workbook();
        const worksheet = workbook.addWorksheet("Payment Prooflist (Detailed)");

        let currentRow = 1;

        // Top-right information
        worksheet.getCell("I1").value = `Run Date/Time: ${excelData.runDateTime}`;
        worksheet.getCell("I2").value = "Note: This document is not valid without complete signatory.";
        worksheet.getCell("I1").font = { size: 9 };
        worksheet.getCell("I2").font = { size: 9, color: { argb: "e74c3c" } };
        worksheet.getCell("I1").alignment = { horizontal: "right" };
        worksheet.getCell("I2").alignment = { horizontal: "right" };

        // Header section
        currentRow = 4;
        worksheet.getCell(`A${currentRow}`).value = excelData.reportName;
        worksheet.getCell(`A${currentRow}`).font = { bold: true, size: 16 };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Payment Prooflist (Detailed) Report";
        worksheet.getCell(`A${currentRow}`).font = { size: 12 };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = `Date Range: ${excelData.dateRange}`;
        worksheet.getCell(`A${currentRow}`).font = { bold: true };

        currentRow += 2;

        // Grouped by Payment Type
        excelData.groupedData.forEach((paymentTypeGroup) => {
             worksheet.getCell(`A${currentRow}`).value = paymentTypeGroup.payment_type;
             worksheet.getCell(`A${currentRow}`).font = { bold: true, size: 12 };
             currentRow++;

             // Grouped by Customer
             paymentTypeGroup.customers.forEach((customerGroup) => {
                 worksheet.getCell(`A${currentRow}`).value = `${customerGroup.customer_code} - ${customerGroup.customer_name}`;
                 worksheet.getCell(`A${currentRow}`).font = { bold: true };
                 currentRow++;

                 // Headers
                const headers = [
                    "PAYMENT NO",
                    "DATE",
                    "DOC NO",
                    "DOC DATE",
                    "TYPE",
                    "REF NO",
                    "DS NO",
                    "REMARKS",
                    "AMOUNT PAID",
                ];

                const headerRow = worksheet.getRow(currentRow);
                headers.forEach((header, index) => {
                    const cell = headerRow.getCell(index + 1);
                    cell.value = header;
                    cell.font = { bold: true, size: 10 };
                    cell.alignment = { horizontal: "center", vertical: "middle" };
                    cell.border = {
                        top: { style: "thin" },
                        left: { style: "thin" },
                        bottom: { style: "thin" },
                        right: { style: "thin" },
                    };
                    cell.fill = {
                        type: "pattern",
                        pattern: "solid",
                        fgColor: { argb: "f0f0f0" },
                    };
                });
                currentRow++;

                customerGroup.payments.forEach((payment) => {
                    payment.payment_details.forEach((detail, detailIndex) => {
                        const dataRow = worksheet.getRow(currentRow);
                        
                        // Show Payment No and Date only on the first line of the payment
                        if (detailIndex === 0) {
                            dataRow.getCell(1).value = payment.payment_no;
                            dataRow.getCell(2).value = new Date(payment.date);
                        }

                        dataRow.getCell(3).value = detail.document_no;
                        dataRow.getCell(4).value = detail.document_date ? new Date(detail.document_date) : '';
                        dataRow.getCell(5).value = detail.type;
                        dataRow.getCell(6).value = detail.reference_no;
                        dataRow.getCell(7).value = detail.ds_no;
                        dataRow.getCell(8).value = detail.remarks;
                        dataRow.getCell(9).value = parseFloat(detail.amount_paid) || 0;

                         // Format cells
                        dataRow.getCell(1).alignment = { horizontal: "center" };
                        dataRow.getCell(2).alignment = { horizontal: "center" };
                        dataRow.getCell(2).numFmt = "mm/dd/yyyy";
                        dataRow.getCell(3).alignment = { horizontal: "center" };
                        dataRow.getCell(4).alignment = { horizontal: "center" };
                        dataRow.getCell(4).numFmt = "mm/dd/yyyy";
                        dataRow.getCell(5).alignment = { horizontal: "center" };
                        dataRow.getCell(6).alignment = { horizontal: "center" };
                        dataRow.getCell(7).alignment = { horizontal: "center" };
                        dataRow.getCell(8).alignment = { horizontal: "left" };
                        dataRow.getCell(9).numFmt = "#,##0.00";
                        dataRow.getCell(9).alignment = { horizontal: "right" };

                        dataRow.eachCell((cell) => {
                            cell.border = {
                                top: { style: "thin" },
                                left: { style: "thin" },
                                bottom: { style: "thin" },
                                right: { style: "thin" },
                            };
                        });
                        currentRow++;
                    });
                });

                // Customer Total
                const subtotalRow = worksheet.getRow(currentRow);
                subtotalRow.getCell(8).value = "Customer Total :";
                subtotalRow.getCell(9).value = parseFloat(customerGroup.customer_total) || 0;

                subtotalRow.getCell(8).font = { bold: true };
                subtotalRow.getCell(8).alignment = { horizontal: "right" };
                
                subtotalRow.getCell(9).font = { bold: true };
                subtotalRow.getCell(9).numFmt = "#,##0.00";
                subtotalRow.getCell(9).alignment = { horizontal: "right" };
                subtotalRow.getCell(9).border = { top: { style: "thin" }, bottom: { style: "double" } };

                currentRow += 2;
             });

             // Payment Type Total
            const typeTotalRow = worksheet.getRow(currentRow);
            typeTotalRow.getCell(8).value = `${paymentTypeGroup.payment_type} Total :`;
            typeTotalRow.getCell(9).value = parseFloat(paymentTypeGroup.type_total) || 0;

            typeTotalRow.getCell(8).font = { bold: true };
            typeTotalRow.getCell(8).alignment = { horizontal: "right" };
            
            typeTotalRow.getCell(9).font = { bold: true };
            typeTotalRow.getCell(9).numFmt = "#,##0.00";
            typeTotalRow.getCell(9).alignment = { horizontal: "right" };
            typeTotalRow.getCell(9).border = { top: { style: "thin" }, bottom: { style: "double" } };

            currentRow += 2;
        });

         // Grand Total
        const totalRow = worksheet.getRow(currentRow);
        totalRow.getCell(8).value = "Grand Total :";
        totalRow.getCell(9).value = parseFloat(excelData.grandTotal) || 0;

        totalRow.getCell(8).font = { bold: true, size: 12 };
        totalRow.getCell(8).alignment = { horizontal: "right" };
        
        totalRow.getCell(9).font = { bold: true, size: 12 };
        totalRow.getCell(9).numFmt = "#,##0.00";
        totalRow.getCell(9).alignment = { horizontal: "right" };
         totalRow.getCell(9).border = {
             top: { style: "thick" },
             bottom: { style: "thick" }
        };

        currentRow += 3;
        
        // Signatory section
        const signatoryStartRow = currentRow;

        // Headers for signatory section
        worksheet.getCell(`A${currentRow}`).value = "Prepared By:";
        worksheet.getCell(`E${currentRow}`).value = "Checked By:";
        worksheet.getCell(`I${currentRow}`).value = "Note By:";

        ["A", "E", "I"].forEach((col) => {
            worksheet.getCell(`${col}${currentRow}`).font = { bold: true };
        });

        currentRow += 2;

        // Prepared By section
        worksheet.getCell(`A${currentRow}`).value = excelData.preparedBy;
        worksheet.getCell(`A${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.mergeCells(`A${currentRow}:D${currentRow}`);
        worksheet.getCell(`A${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value =
            "(Signature Over Printed Name)";
        worksheet.getCell(`A${currentRow}`).alignment = {
            horizontal: "center",
        };
        worksheet.getCell(`A${currentRow}`).font = { size: 9 };
        worksheet.mergeCells(`A${currentRow}:D${currentRow}`);

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Date:";
        worksheet.getCell(`B${currentRow}`).value =
            new Date().toLocaleDateString();
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.getCell(`B${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Time:";
        worksheet.getCell(`B${currentRow}`).value =
            new Date().toLocaleTimeString();
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.getCell(`B${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Designation:";
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.mergeCells(`B${currentRow}:D${currentRow}`);

        // Add similar structure for "Checked By" and "Note By" columns
        const checkByRow = signatoryStartRow + 2;

        // Checked By and Note By sections
        ["E", "I"].forEach((col) => {
            worksheet.getCell(`${col}${checkByRow}`).border = {
                bottom: { style: "thin" },
            };
            worksheet.mergeCells(
                `${col}${checkByRow}:${String.fromCharCode(
                    col.charCodeAt(0) + 3
                )}${checkByRow}`
            );

            worksheet.getCell(`${col}${checkByRow + 1}`).value =
                "(Signature Over Printed Name)";
            worksheet.getCell(`${col}${checkByRow + 1}`).alignment = {
                horizontal: "center",
            };
            worksheet.getCell(`${col}${checkByRow + 1}`).font = { size: 9 };
            worksheet.mergeCells(
                `${col}${checkByRow + 1}:${String.fromCharCode(
                    col.charCodeAt(0) + 3
                )}${checkByRow + 1}`
            );

            worksheet.getCell(`${col}${checkByRow + 2}`).value = "Date:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 2}`
            ).border = { bottom: { style: "thin" } };

            worksheet.getCell(`${col}${checkByRow + 3}`).value = "Time:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 3}`
            ).border = { bottom: { style: "thin" } };

            worksheet.getCell(`${col}${checkByRow + 4}`).value = "Designation:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 4}`
            ).border = { bottom: { style: "thin" } };
            worksheet.mergeCells(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 4
                }:${String.fromCharCode(col.charCodeAt(0) + 3)}${checkByRow + 4
                }`
            );
        });

        // Set column widths
        worksheet.columns = [
            { width: 15 }, // Payment No
            { width: 12 }, // Date
            { width: 15 }, // Doc No
            { width: 12 }, // Doc Date
            { width: 10 }, // Type
            { width: 15 }, // Ref No
            { width: 15 }, // DS No
            { width: 20 }, // Remarks
            { width: 15 }, // Amount Paid
        ];

        const currentDate = new Date().toISOString().split("T")[0];
        const filename = `Payment_Prooflist_Detailed_${currentDate}.xlsx`;

        const buffer = await workbook.xlsx.writeBuffer();
        const blob = new Blob([buffer], {
            type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        });

        saveAs(blob, filename);
        showSuccessToast("Payment Prooflist (Detailed) exported successfully!");

    } catch (error) {
        console.error("Error generating Payment Prooflist Excel file:", error);
        showWarningToast("Failed to generate Payment Prooflist Excel file.");
        throw error;
    }
};

const generatePaymentProoflistSummaryExcelFile = async (excelData) => {
    try {
        const workbook = new ExcelJS.Workbook();
        const worksheet = workbook.addWorksheet("Payment Prooflist (Summary)");

        let currentRow = 1;

        // Top-right information
        worksheet.getCell("I1").value = `Run Date/Time: ${excelData.runDateTime}`;
        worksheet.getCell("I2").value = "Note: This document is not valid without complete signatory.";
        worksheet.getCell("I1").font = { size: 9 };
        worksheet.getCell("I2").font = { size: 9, color: { argb: "e74c3c" } };
        worksheet.getCell("I1").alignment = { horizontal: "right" };
        worksheet.getCell("I2").alignment = { horizontal: "right" };

        // Header section
        currentRow = 4;
        worksheet.getCell(`A${currentRow}`).value = excelData.reportName;
        worksheet.getCell(`A${currentRow}`).font = { bold: true, size: 16 };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Payment Prooflist (Summary) Report";
        worksheet.getCell(`A${currentRow}`).font = { size: 12 };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = `Date Range: ${excelData.dateRange}`;
        worksheet.getCell(`A${currentRow}`).font = { bold: true };

        currentRow += 2;

        // Headers
        const headers = [
            "PAYMENT NO",
            "DATE",
            "CUSTOMER",
            "TYPE",
            "REF NO",
            "DS NO",
            "DOC NO",
            "DOC DATE",
            "AMOUNT PAID",
        ];

        const headerRow = worksheet.getRow(currentRow);
        headers.forEach((header, index) => {
            const cell = headerRow.getCell(index + 1);
            cell.value = header;
            cell.font = { bold: true, size: 10 };
            cell.alignment = { horizontal: "center", vertical: "middle" };
            cell.border = {
                top: { style: "thin" },
                left: { style: "thin" },
                bottom: { style: "thin" },
                right: { style: "thin" },
            };
            cell.fill = {
                type: "pattern",
                pattern: "solid",
                fgColor: { argb: "f0f0f0" },
            };
        });
        currentRow++;

        let grandTotal = 0;

        excelData.payments.forEach((payment) => {
            const dataRow = worksheet.getRow(currentRow);
            
            dataRow.getCell(1).value = payment.payment_no;
            dataRow.getCell(2).value = new Date(payment.date);
            dataRow.getCell(3).value = payment.customer;
            dataRow.getCell(4).value = payment.payment_type;
            dataRow.getCell(5).value = payment.reference_no;
            dataRow.getCell(6).value = payment.ds_no;
            dataRow.getCell(7).value = payment.document_no;
            dataRow.getCell(8).value = payment.document_date ? new Date(payment.document_date) : '';
            dataRow.getCell(9).value = parseFloat(payment.amount_paid) || 0;

            grandTotal += parseFloat(payment.amount_paid) || 0;

            // Format cells
            dataRow.getCell(1).alignment = { horizontal: "center" };
            dataRow.getCell(2).alignment = { horizontal: "center" };
            dataRow.getCell(2).numFmt = "mm/dd/yyyy";
            dataRow.getCell(3).alignment = { horizontal: "left" };
            dataRow.getCell(4).alignment = { horizontal: "center" };
            dataRow.getCell(5).alignment = { horizontal: "center" };
            dataRow.getCell(6).alignment = { horizontal: "center" };
            dataRow.getCell(7).alignment = { horizontal: "center" };
            dataRow.getCell(8).alignment = { horizontal: "center" };
            dataRow.getCell(8).numFmt = "mm/dd/yyyy";
            dataRow.getCell(9).numFmt = "#,##0.00";
            dataRow.getCell(9).alignment = { horizontal: "right" };

            dataRow.eachCell((cell) => {
                cell.border = {
                    top: { style: "thin" },
                    left: { style: "thin" },
                    bottom: { style: "thin" },
                    right: { style: "thin" },
                };
            });
            currentRow++;
        });

        // Grand Total
        const totalRow = worksheet.getRow(currentRow);
        totalRow.getCell(8).value = "Grand Total :";
        totalRow.getCell(9).value = grandTotal;

        totalRow.getCell(8).font = { bold: true, size: 12 };
        totalRow.getCell(8).alignment = { horizontal: "right" };
        
        totalRow.getCell(9).font = { bold: true, size: 12 };
        totalRow.getCell(9).numFmt = "#,##0.00";
        totalRow.getCell(9).alignment = { horizontal: "right" };
         totalRow.getCell(9).border = {
             top: { style: "thick" },
             bottom: { style: "thick" }
        };

        currentRow += 3;
        
        // Signatory section
        const signatoryStartRow = currentRow;

        // Headers for signatory section
        worksheet.getCell(`A${currentRow}`).value = "Prepared By:";
        worksheet.getCell(`E${currentRow}`).value = "Checked By:";
        worksheet.getCell(`I${currentRow}`).value = "Note By:";

        ["A", "E", "I"].forEach((col) => {
            worksheet.getCell(`${col}${currentRow}`).font = { bold: true };
        });

        currentRow += 2;

        // Prepared By section
        worksheet.getCell(`A${currentRow}`).value = excelData.preparedBy;
        worksheet.getCell(`A${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.mergeCells(`A${currentRow}:D${currentRow}`);
        worksheet.getCell(`A${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value =
            "(Signature Over Printed Name)";
        worksheet.getCell(`A${currentRow}`).alignment = {
            horizontal: "center",
        };
        worksheet.getCell(`A${currentRow}`).font = { size: 9 };
        worksheet.mergeCells(`A${currentRow}:D${currentRow}`);

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Date:";
        worksheet.getCell(`B${currentRow}`).value =
            new Date().toLocaleDateString();
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.getCell(`B${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Time:";
        worksheet.getCell(`B${currentRow}`).value =
            new Date().toLocaleTimeString();
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.getCell(`B${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Designation:";
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.mergeCells(`B${currentRow}:D${currentRow}`);

        // Add similar structure for "Checked By" and "Note By" columns
        const checkByRow = signatoryStartRow + 2;

        // Checked By and Note By sections
        ["E", "I"].forEach((col) => {
            worksheet.getCell(`${col}${checkByRow}`).border = {
                bottom: { style: "thin" },
            };
            worksheet.mergeCells(
                `${col}${checkByRow}:${String.fromCharCode(
                    col.charCodeAt(0) + 3
                )}${checkByRow}`
            );

            worksheet.getCell(`${col}${checkByRow + 1}`).value =
                "(Signature Over Printed Name)";
            worksheet.getCell(`${col}${checkByRow + 1}`).alignment = {
                horizontal: "center",
            };
            worksheet.getCell(`${col}${checkByRow + 1}`).font = { size: 9 };
            worksheet.mergeCells(
                `${col}${checkByRow + 1}:${String.fromCharCode(
                    col.charCodeAt(0) + 3
                )}${checkByRow + 1}`
            );

            worksheet.getCell(`${col}${checkByRow + 2}`).value = "Date:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 2}`
            ).border = { bottom: { style: "thin" } };

            worksheet.getCell(`${col}${checkByRow + 3}`).value = "Time:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 3}`
            ).border = { bottom: { style: "thin" } };

            worksheet.getCell(`${col}${checkByRow + 4}`).value = "Designation:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 4}`
            ).border = { bottom: { style: "thin" } };
            worksheet.mergeCells(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 4
                }:${String.fromCharCode(col.charCodeAt(0) + 3)}${checkByRow + 4
                }`
            );
        });

        // Set column widths
        worksheet.columns = [
            { width: 15 }, // Payment No
            { width: 12 }, // Date
            { width: 30 }, // Customer
            { width: 10 }, // Type
            { width: 15 }, // Ref No
            { width: 15 }, // DS No
            { width: 15 }, // Doc No
            { width: 12 }, // Doc Date
            { width: 15 }, // Amount Paid
        ];

        const currentDate = new Date().toISOString().split("T")[0];
        const filename = `Payment_Prooflist_Summary_${currentDate}.xlsx`;

        const buffer = await workbook.xlsx.writeBuffer();
        const blob = new Blob([buffer], {
            type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        });

        saveAs(blob, filename);
        showSuccessToast("Payment Prooflist (Summary) exported successfully!");

    } catch (error) {
        console.error("Error generating Payment Prooflist Excel file:", error);
        showWarningToast("Failed to generate Payment Prooflist Excel file.");
        throw error;
    }
};

const generatePdcDcReportExcelFile = async (excelData) => {
    try {
        const workbook = new ExcelJS.Workbook();
        const worksheet = workbook.addWorksheet("PDC DC Report");

        let currentRow = 1;

        // Top-right information
        worksheet.getCell("I1").value = `Run Date/Time: ${excelData.runDateTime}`;
        worksheet.getCell("I2").value = "Note: This document is not valid without complete signatory.";
        worksheet.getCell("I1").font = { size: 9 };
        worksheet.getCell("I2").font = { size: 9, color: { argb: "e74c3c" } };
        worksheet.getCell("I1").alignment = { horizontal: "right" };
        worksheet.getCell("I2").alignment = { horizontal: "right" };

        // Header section
        currentRow = 4;
        worksheet.getCell(`A${currentRow}`).value = excelData.reportName;
        worksheet.getCell(`A${currentRow}`).font = { bold: true, size: 16 };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Customer PDC & DC Report";
        worksheet.getCell(`A${currentRow}`).font = { size: 12 };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = `Date Range: ${excelData.dateRange}`;
        worksheet.getCell(`A${currentRow}`).font = { bold: true };

        currentRow += 2;

        // Process grouped data
        excelData.groupedData.forEach((group) => {
            // Customer header
            worksheet.getCell(`A${currentRow}`).value = `${group.customer_code} - ${group.customer_name}`;
            worksheet.getCell(`A${currentRow}`).font = { bold: true };
            worksheet.mergeCells(`A${currentRow}:M${currentRow}`);

            currentRow++;

            // Table headers
            const headers = [
                "PAYMENT NO",
                "CHECK NO",
                "DOC NO",
                "DOC DATE",
                "RECEIPT DATE",
                "PAYMENT DATE",
                "PAYMENT TYPE",
                "TYPE",
                "CHECK TYPE",
                "AMOUNT PAID",
                "DUE DATE",
                "CLEARING DATE",
                "STATUS",
            ];

            const headerRow = worksheet.getRow(currentRow);
            headers.forEach((header, index) => {
                const cell = headerRow.getCell(index + 1);
                cell.value = header;
                cell.font = { bold: true, size: 10 };
                cell.alignment = { horizontal: "center", vertical: "middle" };
                cell.border = {
                    top: { style: "thin" },
                    left: { style: "thin" },
                    bottom: { style: "thin" },
                    right: { style: "thin" },
                };
                cell.fill = {
                    type: "pattern",
                    pattern: "solid",
                    fgColor: { argb: "f0f0f0" },
                };
            });

            currentRow++;

            // Data rows
            group.paymentDetails.forEach((detail) => {
                const dataRow = worksheet.getRow(currentRow);
                
                dataRow.getCell(1).value = detail.payment_no;
                dataRow.getCell(2).value = detail.check_no;
                dataRow.getCell(3).value = detail.document_no;
                dataRow.getCell(4).value = detail.document_date ? new Date(detail.document_date) : '';
                dataRow.getCell(5).value = detail.payment_receipt_date ? new Date(detail.payment_receipt_date) : '';
                dataRow.getCell(6).value = detail.payment_date ? new Date(detail.payment_date) : '';
                dataRow.getCell(7).value = detail.payment_type;
                dataRow.getCell(8).value = detail.type;
                dataRow.getCell(9).value = detail.check_type;
                dataRow.getCell(10).value = parseFloat(detail.amount_paid) || 0;
                dataRow.getCell(11).value = detail.due_date ? new Date(detail.due_date) : '';
                dataRow.getCell(12).value = detail.clearing_date ? new Date(detail.clearing_date) : '';
                dataRow.getCell(13).value = detail.status;

                // Format cells
                dataRow.getCell(1).alignment = { horizontal: "center" };
                dataRow.getCell(2).alignment = { horizontal: "center" };
                dataRow.getCell(3).alignment = { horizontal: "center" };
                dataRow.getCell(4).numFmt = "mm/dd/yyyy"; dataRow.getCell(4).alignment = { horizontal: "center" };
                dataRow.getCell(5).numFmt = "mm/dd/yyyy"; dataRow.getCell(5).alignment = { horizontal: "center" };
                dataRow.getCell(6).numFmt = "mm/dd/yyyy"; dataRow.getCell(6).alignment = { horizontal: "center" };
                dataRow.getCell(7).alignment = { horizontal: "center" };
                dataRow.getCell(8).alignment = { horizontal: "center" };
                dataRow.getCell(9).alignment = { horizontal: "center" };
                dataRow.getCell(10).numFmt = "#,##0.00"; dataRow.getCell(10).alignment = { horizontal: "right" };
                dataRow.getCell(11).numFmt = "mm/dd/yyyy"; dataRow.getCell(11).alignment = { horizontal: "center" };
                dataRow.getCell(12).numFmt = "mm/dd/yyyy"; dataRow.getCell(12).alignment = { horizontal: "center" };
                dataRow.getCell(13).alignment = { horizontal: "center" };

                // Borders
                dataRow.eachCell((cell) => {
                    cell.border = {
                        top: { style: "thin" },
                        left: { style: "thin" },
                        bottom: { style: "thin" },
                        right: { style: "thin" },
                    };
                });

                currentRow++;
            });

            // Customer Subtotal
            const subtotalRow = worksheet.getRow(currentRow);
            subtotalRow.getCell(9).value = "Customer Total :";
            subtotalRow.getCell(10).value = parseFloat(group.customerAmountTotal) || 0;

            subtotalRow.getCell(9).font = { bold: true };
            subtotalRow.getCell(9).alignment = { horizontal: "right" };
            
            subtotalRow.getCell(10).font = { bold: true };
            subtotalRow.getCell(10).numFmt = "#,##0.00";
            subtotalRow.getCell(10).alignment = { horizontal: "right" };
            subtotalRow.getCell(10).border = {
                 top: { style: "thin" },
                 bottom: { style: "double" }
            };

            currentRow += 2;
        });

        // Grand Total
        const totalRow = worksheet.getRow(currentRow);
        totalRow.getCell(9).value = "Grand Total :";
        totalRow.getCell(10).value = parseFloat(excelData.customerOverallAmountTotal) || 0;

        totalRow.getCell(9).font = { bold: true, size: 12 };
        totalRow.getCell(9).alignment = { horizontal: "right" };
        
        totalRow.getCell(10).font = { bold: true, size: 12 };
        totalRow.getCell(10).numFmt = "#,##0.00";
        totalRow.getCell(10).alignment = { horizontal: "right" };
         totalRow.getCell(10).border = {
             top: { style: "thick" },
             bottom: { style: "thick" }
        };

        currentRow += 3;
        
        // Signatory section
        const signatoryStartRow = currentRow;

        // Headers for signatory section
        worksheet.getCell(`A${currentRow}`).value = "Prepared By:";
        worksheet.getCell(`E${currentRow}`).value = "Checked By:";
        worksheet.getCell(`I${currentRow}`).value = "Note By:";

        ["A", "E", "I"].forEach((col) => {
            worksheet.getCell(`${col}${currentRow}`).font = { bold: true };
        });

        currentRow += 2;

        // Prepared By section
        worksheet.getCell(`A${currentRow}`).value = excelData.preparedBy;
        worksheet.getCell(`A${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.mergeCells(`A${currentRow}:D${currentRow}`);
        worksheet.getCell(`A${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value =
            "(Signature Over Printed Name)";
        worksheet.getCell(`A${currentRow}`).alignment = {
            horizontal: "center",
        };
        worksheet.getCell(`A${currentRow}`).font = { size: 9 };
        worksheet.mergeCells(`A${currentRow}:D${currentRow}`);

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Date:";
        worksheet.getCell(`B${currentRow}`).value =
            new Date().toLocaleDateString();
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.getCell(`B${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Time:";
        worksheet.getCell(`B${currentRow}`).value =
            new Date().toLocaleTimeString();
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.getCell(`B${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Designation:";
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.mergeCells(`B${currentRow}:D${currentRow}`);

        // Add similar structure for "Checked By" and "Note By" columns
        const checkByRow = signatoryStartRow + 2;

        // Checked By and Note By sections
        ["E", "I"].forEach((col) => {
            worksheet.getCell(`${col}${checkByRow}`).border = {
                bottom: { style: "thin" },
            };
            worksheet.mergeCells(
                `${col}${checkByRow}:${String.fromCharCode(
                    col.charCodeAt(0) + 3
                )}${checkByRow}`
            );

            worksheet.getCell(`${col}${checkByRow + 1}`).value =
                "(Signature Over Printed Name)";
            worksheet.getCell(`${col}${checkByRow + 1}`).alignment = {
                horizontal: "center",
            };
            worksheet.getCell(`${col}${checkByRow + 1}`).font = { size: 9 };
            worksheet.mergeCells(
                `${col}${checkByRow + 1}:${String.fromCharCode(
                    col.charCodeAt(0) + 3
                )}${checkByRow + 1}`
            );

            worksheet.getCell(`${col}${checkByRow + 2}`).value = "Date:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 2}`
            ).border = { bottom: { style: "thin" } };

            worksheet.getCell(`${col}${checkByRow + 3}`).value = "Time:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 3}`
            ).border = { bottom: { style: "thin" } };

            worksheet.getCell(`${col}${checkByRow + 4}`).value = "Designation:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 4}`
            ).border = { bottom: { style: "thin" } };
            worksheet.mergeCells(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 4
                }:${String.fromCharCode(col.charCodeAt(0) + 3)}${checkByRow + 4
                }`
            );
        });

        // Set column widths
        worksheet.columns = [
            { width: 15 }, // Payment No
            { width: 15 }, // Check No
            { width: 15 }, // Doc No
            { width: 12 }, // Doc Date
            { width: 12 }, // Receipt Date
            { width: 12 }, // Payment Date
            { width: 12 }, // Payment Type
            { width: 10 }, // Type
            { width: 12 }, // Check Type
            { width: 15 }, // Amount Paid
            { width: 12 }, // Due Date
            { width: 12 }, // Clearing Date
            { width: 15 }, // Status
        ];

        // Generate filename
        const currentDate = new Date().toISOString().split("T")[0];
        const filename = `Customer_PDC_DC_Report_${currentDate}.xlsx`;

        // Generate blob and download
        const buffer = await workbook.xlsx.writeBuffer();
        const blob = new Blob([buffer], {
            type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        });

        saveAs(blob, filename);
        showSuccessToast("Customer PDC DC Report exported successfully!");

    } catch (error) {
        console.error("Error generating PDC DC Report Excel file:", error);
        showWarningToast("Failed to generate PDC DC Report Excel file.");
        throw error;
    }
};

const generateCustomerArAgingExcelFile = async (excelData) => {
    try {
        const workbook = new ExcelJS.Workbook();
        const worksheet = workbook.addWorksheet("Customer AR Aging");

        let currentRow = 1;

        // Top-right information
        worksheet.getCell("K1").value = `Run Date/Time: ${excelData.runDateTime}`;
        worksheet.getCell("K2").value = "Note: This document is not valid without complete signatory.";
        worksheet.getCell("K1").font = { size: 9 };
        worksheet.getCell("K2").font = { size: 9, color: { argb: "e74c3c" } };
        worksheet.getCell("K1").alignment = { horizontal: "right" };
        worksheet.getCell("K2").alignment = { horizontal: "right" };

        // Header
        currentRow = 4;
        worksheet.getCell(`A${currentRow}`).value = excelData.reportName;
        worksheet.getCell(`A${currentRow}`).font = { bold: true, size: 16 };
        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Customer AR Aging Report";
        worksheet.getCell(`A${currentRow}`).font = { size: 12 };
        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = `As of: ${excelData.dateRange}`;
        worksheet.getCell(`A${currentRow}`).font = { bold: true };
        currentRow += 2;

        // Headers
        const headers = [
            "CUSTOMER CODE", "CUSTOMER NAME", "TERMS", 
            "TOTAL BALANCE", "1 - 30 DAYS", "31 - 60 DAYS", 
            "61 - 90 DAYS", "91 - 360 DAYS", "ABOVE 1 YEAR"
        ];
        const headerRow = worksheet.getRow(currentRow);
        headers.forEach((h, i) => {
            const cell = headerRow.getCell(i + 1);
            cell.value = h;
            cell.font = { bold: true };
            cell.alignment = { horizontal: "center" };
            cell.border = { top: { style: "thin" }, bottom: { style: "thin" }, left: { style: "thin" }, right: { style: "thin" } };
            cell.fill = { type: "pattern", pattern: "solid", fgColor: { argb: "f0f0f0" } };
        });
        currentRow++;

        // Data
        excelData.groupedData.forEach(item => {
             const row = worksheet.getRow(currentRow);
             row.getCell(1).value = item.customer_code;
             row.getCell(2).value = item.customer_name;
             row.getCell(3).value = item.payment_terms;
             row.getCell(4).value = parseFloat(item.totals.total) || 0;
             row.getCell(5).value = parseFloat(item.totals['1_30']) || 0;
             row.getCell(6).value = parseFloat(item.totals['31_60']) || 0;
             row.getCell(7).value = parseFloat(item.totals['61_90']) || 0;
             row.getCell(8).value = parseFloat(item.totals['91_360']) || 0;
             row.getCell(9).value = parseFloat(item.totals['above_1_year']) || 0;

             // Formatting
             for(let i=4; i<=9; i++) {
                 row.getCell(i).numFmt = "#,##0.00";
                 row.getCell(i).alignment = { horizontal: "right" };
             }
             row.eachCell(cell => {
                 cell.border = { top: { style: "thin" }, bottom: { style: "thin" }, left: { style: "thin" }, right: { style: "thin" } };
             });
             currentRow++;
        });

        // Totals
        const totalRow = worksheet.getRow(currentRow);
        totalRow.getCell(3).value = "Grand Total:";
        totalRow.getCell(3).font = { bold: true };
        totalRow.getCell(3).alignment = { horizontal: "right" };
        
        totalRow.getCell(4).value = parseFloat(excelData.grandTotals.total) || 0;
        totalRow.getCell(5).value = parseFloat(excelData.grandTotals['1_30']) || 0;
        totalRow.getCell(6).value = parseFloat(excelData.grandTotals['31_60']) || 0;
        totalRow.getCell(7).value = parseFloat(excelData.grandTotals['61_90']) || 0;
        totalRow.getCell(8).value = parseFloat(excelData.grandTotals['91_360']) || 0;
        totalRow.getCell(9).value = parseFloat(excelData.grandTotals['above_1_year']) || 0;

        for(let i=4; i<=9; i++) {
             totalRow.getCell(i).numFmt = "#,##0.00";
             totalRow.getCell(i).font = { bold: true };
             totalRow.getCell(i).alignment = { horizontal: "right" };
             totalRow.getCell(i).border = { top: { style: "thick" }, bottom: { style: "thick" } };
        }
        
        currentRow += 3;
        
        // Signatory section
        const signatoryStartRow = currentRow;

        // Headers for signatory section
        worksheet.getCell(`A${currentRow}`).value = "Prepared By:";
        worksheet.getCell(`E${currentRow}`).value = "Checked By:";
        worksheet.getCell(`I${currentRow}`).value = "Note By:";

        ["A", "E", "I"].forEach((col) => {
            worksheet.getCell(`${col}${currentRow}`).font = { bold: true };
        });

        currentRow += 2;

        // Prepared By section
        worksheet.getCell(`A${currentRow}`).value = excelData.preparedBy;
        worksheet.getCell(`A${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.mergeCells(`A${currentRow}:D${currentRow}`);
        worksheet.getCell(`A${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value =
            "(Signature Over Printed Name)";
        worksheet.getCell(`A${currentRow}`).alignment = {
            horizontal: "center",
        };
        worksheet.getCell(`A${currentRow}`).font = { size: 9 };
        worksheet.mergeCells(`A${currentRow}:D${currentRow}`);

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Date:";
        worksheet.getCell(`B${currentRow}`).value =
            new Date().toLocaleDateString();
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.getCell(`B${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Time:";
        worksheet.getCell(`B${currentRow}`).value =
            new Date().toLocaleTimeString();
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.getCell(`B${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Designation:";
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.mergeCells(`B${currentRow}:D${currentRow}`);

        // Add similar structure for "Checked By" and "Note By" columns
        const checkByRow = signatoryStartRow + 2;

        // Checked By and Note By sections
        ["E", "I"].forEach((col) => {
            worksheet.getCell(`${col}${checkByRow}`).border = {
                bottom: { style: "thin" },
            };
            worksheet.mergeCells(
                `${col}${checkByRow}:${String.fromCharCode(
                    col.charCodeAt(0) + 3
                )}${checkByRow}`
            );

            worksheet.getCell(`${col}${checkByRow + 1}`).value =
                "(Signature Over Printed Name)";
            worksheet.getCell(`${col}${checkByRow + 1}`).alignment = {
                horizontal: "center",
            };
            worksheet.getCell(`${col}${checkByRow + 1}`).font = { size: 9 };
            worksheet.mergeCells(
                `${col}${checkByRow + 1}:${String.fromCharCode(
                    col.charCodeAt(0) + 3
                )}${checkByRow + 1}`
            );

            worksheet.getCell(`${col}${checkByRow + 2}`).value = "Date:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 2}`
            ).border = { bottom: { style: "thin" } };

            worksheet.getCell(`${col}${checkByRow + 3}`).value = "Time:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 3}`
            ).border = { bottom: { style: "thin" } };

            worksheet.getCell(`${col}${checkByRow + 4}`).value = "Designation:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 4}`
            ).border = { bottom: { style: "thin" } };
            worksheet.mergeCells(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 4
                }:${String.fromCharCode(col.charCodeAt(0) + 3)}${checkByRow + 4
                }`
            );
        });

        // Column widths
        worksheet.columns = [
            { width: 15 }, { width: 30 }, { width: 15 },
            { width: 15 }, { width: 15 }, { width: 15 },
            { width: 15 }, { width: 15 }, { width: 15 }
        ];

        const filename = `Customer_AR_Aging_${new Date().toISOString().split("T")[0]}.xlsx`;
        const buffer = await workbook.xlsx.writeBuffer();
        saveAs(new Blob([buffer]), filename);
        showSuccessToast("Customer AR Aging Report exported successfully!");
    } catch (error) {
        console.error(error);
        showWarningToast("Failed to generate Excel file.");
    }
};

const generateBegBalProoflistExcelFile = async (excelData) => {
    try {
        const workbook = new ExcelJS.Workbook();
        const worksheet = workbook.addWorksheet("Beg Bal Prooflist");
        let currentRow = 1;

        worksheet.getCell("C1").value = `Run Date/Time: ${excelData.runDateTime}`;
        worksheet.getCell("C1").alignment = { horizontal: "right" };
        
        currentRow = 4;
        worksheet.getCell(`A${currentRow}`).value = excelData.reportName;
        worksheet.getCell(`A${currentRow}`).font = { bold: true, size: 16 };
        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Beginning Balance Prooflist";
        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = `Date Range: ${excelData.dateRange}`;
        currentRow += 2;

        const headers = ["REF NO", "DATE", "CUSTOMER", "AMOUNT"];
        const headerRow = worksheet.getRow(currentRow);
        headers.forEach((h, i) => {
            headerRow.getCell(i + 1).value = h;
            headerRow.getCell(i + 1).font = { bold: true };
            headerRow.getCell(i + 1).border = { bottom: { style: "thin" } };
        });
        currentRow++;

        excelData.begBals.forEach(item => {
            const row = worksheet.getRow(currentRow);
            row.getCell(1).value = item.beginningbalance_no;
            row.getCell(2).value = new Date(item.date);
            row.getCell(2).numFmt = "mm/dd/yyyy";
            row.getCell(3).value = item.customer;
            row.getCell(4).value = parseFloat(item.amount) || 0;
            row.getCell(4).numFmt = "#,##0.00";
            currentRow++;
        });

        worksheet.getCell(`C${currentRow}`).value = "Grand Total:";
        worksheet.getCell(`D${currentRow}`).value = parseFloat(excelData.totalAmount) || 0;
        worksheet.getCell(`D${currentRow}`).numFmt = "#,##0.00";
        worksheet.getCell(`D${currentRow}`).font = { bold: true };
        worksheet.getCell(`D${currentRow}`).border = { bottom: { style: "double" } };

        currentRow += 3;

        // Signatory section
        const signatoryStartRow = currentRow;

        // Headers for signatory section
        worksheet.getCell(`A${currentRow}`).value = "Prepared By:";
        worksheet.getCell(`E${currentRow}`).value = "Checked By:";
        worksheet.getCell(`I${currentRow}`).value = "Note By:";

        ["A", "E", "I"].forEach((col) => {
            worksheet.getCell(`${col}${currentRow}`).font = { bold: true };
        });

        currentRow += 2;

        // Prepared By section
        worksheet.getCell(`A${currentRow}`).value = excelData.preparedBy;
        worksheet.getCell(`A${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.mergeCells(`A${currentRow}:D${currentRow}`);
        worksheet.getCell(`A${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value =
            "(Signature Over Printed Name)";
        worksheet.getCell(`A${currentRow}`).alignment = {
            horizontal: "center",
        };
        worksheet.getCell(`A${currentRow}`).font = { size: 9 };
        worksheet.mergeCells(`A${currentRow}:D${currentRow}`);

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Date:";
        worksheet.getCell(`B${currentRow}`).value =
            new Date().toLocaleDateString();
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.getCell(`B${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Time:";
        worksheet.getCell(`B${currentRow}`).value =
            new Date().toLocaleTimeString();
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.getCell(`B${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Designation:";
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.mergeCells(`B${currentRow}:D${currentRow}`);

        // Add similar structure for "Checked By" and "Note By" columns
        const checkByRow = signatoryStartRow + 2;

        // Checked By and Note By sections
        ["E", "I"].forEach((col) => {
            worksheet.getCell(`${col}${checkByRow}`).border = {
                bottom: { style: "thin" },
            };
            worksheet.mergeCells(
                `${col}${checkByRow}:${String.fromCharCode(
                    col.charCodeAt(0) + 3
                )}${checkByRow}`
            );

            worksheet.getCell(`${col}${checkByRow + 1}`).value =
                "(Signature Over Printed Name)";
            worksheet.getCell(`${col}${checkByRow + 1}`).alignment = {
                horizontal: "center",
            };
            worksheet.getCell(`${col}${checkByRow + 1}`).font = { size: 9 };
            worksheet.mergeCells(
                `${col}${checkByRow + 1}:${String.fromCharCode(
                    col.charCodeAt(0) + 3
                )}${checkByRow + 1}`
            );

            worksheet.getCell(`${col}${checkByRow + 2}`).value = "Date:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 2}`
            ).border = { bottom: { style: "thin" } };

            worksheet.getCell(`${col}${checkByRow + 3}`).value = "Time:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 3}`
            ).border = { bottom: { style: "thin" } };

            worksheet.getCell(`${col}${checkByRow + 4}`).value = "Designation:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 4}`
            ).border = { bottom: { style: "thin" } };
            worksheet.mergeCells(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 4
                }:${String.fromCharCode(col.charCodeAt(0) + 3)}${checkByRow + 4
                }`
            );
        });

        worksheet.columns = [{ width: 15 }, { width: 15 }, { width: 30 }, { width: 15 }];
        
        const buffer = await workbook.xlsx.writeBuffer();
        saveAs(new Blob([buffer]), `BegBal_Prooflist_${new Date().toISOString().split("T")[0]}.xlsx`);
        showSuccessToast("Report exported successfully!");
    } catch (e) {
        console.error(e);
        showWarningToast("Failed to generate Excel.");
    }
};

const generateSalesPerItemExcelFile = async (excelData) => {
    try {
        const workbook = new ExcelJS.Workbook();
        const worksheet = workbook.addWorksheet("Sales Per Item");
        let currentRow = 1;

        worksheet.getCell("F1").value = `Run Date/Time: ${excelData.runDateTime}`;
        worksheet.getCell("F1").alignment = { horizontal: "right" };
        
        currentRow = 4;
        worksheet.getCell(`A${currentRow}`).value = excelData.reportName;
        worksheet.getCell(`A${currentRow}`).font = { bold: true, size: 16 };
        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Sales Per Item Report";
        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = `Date Range: ${excelData.dateRange}`;
        currentRow += 2;

        const headers = ["DATE", "INV NO", "CUSTOMER", "ITEM CODE", "ITEM NAME", "QTY", "PRICE", "AMOUNT"];
        const headerRow = worksheet.getRow(currentRow);
        headers.forEach((h, i) => {
            headerRow.getCell(i + 1).value = h;
            headerRow.getCell(i + 1).font = { bold: true };
            headerRow.getCell(i + 1).border = { bottom: { style: "thin" } };
        });
        currentRow++;

        excelData.salesperItems.forEach(item => {
            const row = worksheet.getRow(currentRow);
            row.getCell(1).value = new Date(item.date);
            row.getCell(1).numFmt = "mm/dd/yyyy";
            row.getCell(2).value = item.invoice_no;
            row.getCell(3).value = item.customer_name;
            row.getCell(4).value = item.item_code;
            row.getCell(5).value = item.item_name;
            row.getCell(6).value = parseFloat(item.quantity) || 0;
            row.getCell(7).value = parseFloat(item.price) || 0;
            row.getCell(8).value = parseFloat(item.amount) || 0;
            
            row.getCell(7).numFmt = "#,##0.00";
            row.getCell(8).numFmt = "#,##0.00";
            currentRow++;
        });

        worksheet.getCell(`G${currentRow}`).value = "Grand Total:";
        worksheet.getCell(`H${currentRow}`).value = parseFloat(excelData.totalAmount) || 0;
        worksheet.getCell(`H${currentRow}`).numFmt = "#,##0.00";
        worksheet.getCell(`H${currentRow}`).font = { bold: true };
        worksheet.getCell(`H${currentRow}`).border = { bottom: { style: "double" } };

        currentRow += 3;

        // Signatory section
        const signatoryStartRow = currentRow;

        // Headers for signatory section
        worksheet.getCell(`A${currentRow}`).value = "Prepared By:";
        worksheet.getCell(`E${currentRow}`).value = "Checked By:";
        worksheet.getCell(`I${currentRow}`).value = "Note By:";

        ["A", "E", "I"].forEach((col) => {
            worksheet.getCell(`${col}${currentRow}`).font = { bold: true };
        });

        currentRow += 2;

        // Prepared By section
        worksheet.getCell(`A${currentRow}`).value = excelData.preparedBy;
        worksheet.getCell(`A${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.mergeCells(`A${currentRow}:D${currentRow}`);
        worksheet.getCell(`A${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value =
            "(Signature Over Printed Name)";
        worksheet.getCell(`A${currentRow}`).alignment = {
            horizontal: "center",
        };
        worksheet.getCell(`A${currentRow}`).font = { size: 9 };
        worksheet.mergeCells(`A${currentRow}:D${currentRow}`);

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Date:";
        worksheet.getCell(`B${currentRow}`).value =
            new Date().toLocaleDateString();
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.getCell(`B${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Time:";
        worksheet.getCell(`B${currentRow}`).value =
            new Date().toLocaleTimeString();
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.getCell(`B${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Designation:";
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.mergeCells(`B${currentRow}:D${currentRow}`);

        // Add similar structure for "Checked By" and "Note By" columns
        const checkByRow = signatoryStartRow + 2;

        // Checked By and Note By sections
        ["E", "I"].forEach((col) => {
            worksheet.getCell(`${col}${checkByRow}`).border = {
                bottom: { style: "thin" },
            };
            worksheet.mergeCells(
                `${col}${checkByRow}:${String.fromCharCode(
                    col.charCodeAt(0) + 3
                )}${checkByRow}`
            );

            worksheet.getCell(`${col}${checkByRow + 1}`).value =
                "(Signature Over Printed Name)";
            worksheet.getCell(`${col}${checkByRow + 1}`).alignment = {
                horizontal: "center",
            };
            worksheet.getCell(`${col}${checkByRow + 1}`).font = { size: 9 };
            worksheet.mergeCells(
                `${col}${checkByRow + 1}:${String.fromCharCode(
                    col.charCodeAt(0) + 3
                )}${checkByRow + 1}`
            );

            worksheet.getCell(`${col}${checkByRow + 2}`).value = "Date:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 2}`
            ).border = { bottom: { style: "thin" } };

            worksheet.getCell(`${col}${checkByRow + 3}`).value = "Time:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 3}`
            ).border = { bottom: { style: "thin" } };

            worksheet.getCell(`${col}${checkByRow + 4}`).value = "Designation:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 4}`
            ).border = { bottom: { style: "thin" } };
            worksheet.mergeCells(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 4
                }:${String.fromCharCode(col.charCodeAt(0) + 3)}${checkByRow + 4
                }`
            );
        });

        worksheet.columns = [
            { width: 12 }, { width: 15 }, { width: 30 }, 
            { width: 15 }, { width: 30 }, 
            { width: 10 }, { width: 12 }, { width: 15 }
        ];
        
        const buffer = await workbook.xlsx.writeBuffer();
        saveAs(new Blob([buffer]), `Sales_Per_Item_${new Date().toISOString().split("T")[0]}.xlsx`);
        showSuccessToast("Report exported successfully!");
    } catch (e) {
        console.error(e);
        showWarningToast("Failed to generate Excel.");
    }
};

const generateOverageShortageExcelFile = async (excelData) => {
    try {
        const workbook = new ExcelJS.Workbook();
        const worksheet = workbook.addWorksheet("Overage Shortage");
        let currentRow = 1;

        worksheet.getCell("E1").value = `Run Date/Time: ${excelData.runDateTime}`;
        worksheet.getCell("E1").alignment = { horizontal: "right" };
        
        currentRow = 4;
        worksheet.getCell(`A${currentRow}`).value = excelData.reportName;
        worksheet.getCell(`A${currentRow}`).font = { bold: true, size: 16 };
        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Overage/Shortage Report";
        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = `Date Range: ${excelData.dateRange}`;
        currentRow += 2;

        excelData.groupedData.forEach(typeGroup => {
            worksheet.getCell(`A${currentRow}`).value = typeGroup.payment_type;
            worksheet.getCell(`A${currentRow}`).font = { bold: true, size: 12 };
            worksheet.getCell(`A${currentRow}`).fill = { type: "pattern", pattern: "solid", fgColor: { argb: "e0e0e0" } };
            currentRow++;

            const headers = ["DATE", "DS NO", "CUSTOMER", "AMOUNT", "REMARKS"];
            const headerRow = worksheet.getRow(currentRow);
            headers.forEach((h, i) => {
                headerRow.getCell(i + 1).value = h;
                headerRow.getCell(i + 1).font = { bold: true };
                headerRow.getCell(i + 1).border = { bottom: { style: "thin" } };
            });
            currentRow++;

            typeGroup.customers.forEach(customer => {
                customer.payments.forEach(item => {
                    const row = worksheet.getRow(currentRow);
                    row.getCell(1).value = new Date(item.date);
                    row.getCell(1).numFmt = "mm/dd/yyyy";
                    row.getCell(2).value = item.ds_no;
                    row.getCell(3).value = customer.customer_name;
                    row.getCell(4).value = parseFloat(item.amount) || 0;
                    row.getCell(5).value = item.remarks;
                    
                    row.getCell(4).numFmt = "#,##0.00";
                    currentRow++;
                });
                
                // Optional: Customer Subtotal if desired, but maybe just skip to Type Total
            });

            worksheet.getCell(`C${currentRow}`).value = `Total ${typeGroup.payment_type}:`;
            worksheet.getCell(`D${currentRow}`).value = parseFloat(typeGroup.type_total) || 0;
            worksheet.getCell(`D${currentRow}`).numFmt = "#,##0.00";
            worksheet.getCell(`C${currentRow}`).font = { bold: true };
            worksheet.getCell(`D${currentRow}`).font = { bold: true };
            worksheet.getCell(`D${currentRow}`).border = { top: { style: "thin" } };
            currentRow += 2;
        });

        worksheet.getCell(`C${currentRow}`).value = "Grand Total:";
        worksheet.getCell(`D${currentRow}`).value = parseFloat(excelData.grandTotal) || 0;
        worksheet.getCell(`D${currentRow}`).numFmt = "#,##0.00";
        worksheet.getCell(`D${currentRow}`).font = { bold: true };
        worksheet.getCell(`D${currentRow}`).border = { bottom: { style: "double" } };

        currentRow += 3;

        // Signatory section
        const signatoryStartRow = currentRow;

        // Headers for signatory section
        worksheet.getCell(`A${currentRow}`).value = "Prepared By:";
        worksheet.getCell(`E${currentRow}`).value = "Checked By:";
        worksheet.getCell(`I${currentRow}`).value = "Note By:";

        ["A", "E", "I"].forEach((col) => {
            worksheet.getCell(`${col}${currentRow}`).font = { bold: true };
        });

        currentRow += 2;

        // Prepared By section
        worksheet.getCell(`A${currentRow}`).value = excelData.preparedBy;
        worksheet.getCell(`A${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.mergeCells(`A${currentRow}:D${currentRow}`);
        worksheet.getCell(`A${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value =
            "(Signature Over Printed Name)";
        worksheet.getCell(`A${currentRow}`).alignment = {
            horizontal: "center",
        };
        worksheet.getCell(`A${currentRow}`).font = { size: 9 };
        worksheet.mergeCells(`A${currentRow}:D${currentRow}`);

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Date:";
        worksheet.getCell(`B${currentRow}`).value =
            new Date().toLocaleDateString();
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.getCell(`B${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Time:";
        worksheet.getCell(`B${currentRow}`).value =
            new Date().toLocaleTimeString();
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.getCell(`B${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Designation:";
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.mergeCells(`B${currentRow}:D${currentRow}`);

        // Add similar structure for "Checked By" and "Note By" columns
        const checkByRow = signatoryStartRow + 2;

        // Checked By and Note By sections
        ["E", "I"].forEach((col) => {
            worksheet.getCell(`${col}${checkByRow}`).border = {
                bottom: { style: "thin" },
            };
            worksheet.mergeCells(
                `${col}${checkByRow}:${String.fromCharCode(
                    col.charCodeAt(0) + 3
                )}${checkByRow}`
            );

            worksheet.getCell(`${col}${checkByRow + 1}`).value =
                "(Signature Over Printed Name)";
            worksheet.getCell(`${col}${checkByRow + 1}`).alignment = {
                horizontal: "center",
            };
            worksheet.getCell(`${col}${checkByRow + 1}`).font = { size: 9 };
            worksheet.mergeCells(
                `${col}${checkByRow + 1}:${String.fromCharCode(
                    col.charCodeAt(0) + 3
                )}${checkByRow + 1}`
            );

            worksheet.getCell(`${col}${checkByRow + 2}`).value = "Date:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 2}`
            ).border = { bottom: { style: "thin" } };

            worksheet.getCell(`${col}${checkByRow + 3}`).value = "Time:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 3}`
            ).border = { bottom: { style: "thin" } };

            worksheet.getCell(`${col}${checkByRow + 4}`).value = "Designation:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 4}`
            ).border = { bottom: { style: "thin" } };
            worksheet.mergeCells(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 4
                }:${String.fromCharCode(col.charCodeAt(0) + 3)}${checkByRow + 4
                }`
            );
        });

        worksheet.columns = [{ width: 12 }, { width: 15 }, { width: 30 }, { width: 15 }, { width: 30 }];
        
        const buffer = await workbook.xlsx.writeBuffer();
        saveAs(new Blob([buffer]), `Overage_Shortage_${new Date().toISOString().split("T")[0]}.xlsx`);
        showSuccessToast("Report exported successfully!");
    } catch (e) {
        console.error(e);
        showWarningToast("Failed to generate Excel.");
    }
};

const generateStatementOfAccountExcelFile = async (excelData) => {
    try {
        const workbook = new ExcelJS.Workbook();
        const worksheet = workbook.addWorksheet("Statement of Account");
        let currentRow = 1;

        worksheet.getCell("D1").value = `Run Date/Time: ${excelData.runDateTime}`;
        worksheet.getCell("D1").alignment = { horizontal: "right" };
        
        currentRow = 4;
        worksheet.getCell(`A${currentRow}`).value = excelData.reportName;
        worksheet.getCell(`A${currentRow}`).font = { bold: true, size: 16 };
        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Statement of Account";
        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = `Statement Date: ${excelData.statement_date}`;
        currentRow += 2;

        excelData.groupedData.forEach(group => {
            worksheet.getCell(`A${currentRow}`).value = `Customer: ${group.customer_name} (${group.customer_code})`;
            worksheet.getCell(`A${currentRow}`).font = { bold: true };
            currentRow++;
            worksheet.getCell(`A${currentRow}`).value = `Address: ${group.address || ''}`;
            currentRow += 2;

            const headers = ["DATE", "INV NO", "PARTICULARS", "AMOUNT"];
            const headerRow = worksheet.getRow(currentRow);
            headers.forEach((h, i) => {
                headerRow.getCell(i + 1).value = h;
                headerRow.getCell(i + 1).font = { bold: true };
                headerRow.getCell(i + 1).border = { bottom: { style: "thin" } };
            });
            currentRow++;

            group.paymentDetails.forEach(item => {
                const row = worksheet.getRow(currentRow);
                row.getCell(1).value = new Date(item.date);
                row.getCell(1).numFmt = "mm/dd/yyyy";
                row.getCell(2).value = item.document_no;
                row.getCell(3).value = item.type;
                row.getCell(4).value = parseFloat(item.amount) || 0;
                row.getCell(4).numFmt = "#,##0.00";
                currentRow++;
            });

            worksheet.getCell(`C${currentRow}`).value = "Total Due:";
            worksheet.getCell(`D${currentRow}`).value = parseFloat(group.total_balance) || 0;
            worksheet.getCell(`D${currentRow}`).numFmt = "#,##0.00";
            worksheet.getCell(`C${currentRow}`).font = { bold: true };
            worksheet.getCell(`D${currentRow}`).font = { bold: true };
            worksheet.getCell(`D${currentRow}`).border = { top: { style: "thin" }, bottom: { style: "double" } };
            
            currentRow += 3; 
        });

        // Signatory section
        const signatoryStartRow = currentRow;

        // Headers for signatory section
        worksheet.getCell(`A${currentRow}`).value = "Prepared By:";
        worksheet.getCell(`E${currentRow}`).value = "Checked By:";
        worksheet.getCell(`I${currentRow}`).value = "Note By:";

        ["A", "E", "I"].forEach((col) => {
            worksheet.getCell(`${col}${currentRow}`).font = { bold: true };
        });

        currentRow += 2;

        // Prepared By section
        worksheet.getCell(`A${currentRow}`).value = excelData.preparedBy;
        worksheet.getCell(`A${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.mergeCells(`A${currentRow}:D${currentRow}`);
        worksheet.getCell(`A${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value =
            "(Signature Over Printed Name)";
        worksheet.getCell(`A${currentRow}`).alignment = {
            horizontal: "center",
        };
        worksheet.getCell(`A${currentRow}`).font = { size: 9 };
        worksheet.mergeCells(`A${currentRow}:D${currentRow}`);

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Date:";
        worksheet.getCell(`B${currentRow}`).value =
            new Date().toLocaleDateString();
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.getCell(`B${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Time:";
        worksheet.getCell(`B${currentRow}`).value =
            new Date().toLocaleTimeString();
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.getCell(`B${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Designation:";
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.mergeCells(`B${currentRow}:D${currentRow}`);

        // Add similar structure for "Checked By" and "Note By" columns
        const checkByRow = signatoryStartRow + 2;

        // Checked By and Note By sections
        ["E", "I"].forEach((col) => {
            worksheet.getCell(`${col}${checkByRow}`).border = {
                bottom: { style: "thin" },
            };
            worksheet.mergeCells(
                `${col}${checkByRow}:${String.fromCharCode(
                    col.charCodeAt(0) + 3
                )}${checkByRow}`
            );

            worksheet.getCell(`${col}${checkByRow + 1}`).value =
                "(Signature Over Printed Name)";
            worksheet.getCell(`${col}${checkByRow + 1}`).alignment = {
                horizontal: "center",
            };
            worksheet.getCell(`${col}${checkByRow + 1}`).font = { size: 9 };
            worksheet.mergeCells(
                `${col}${checkByRow + 1}:${String.fromCharCode(
                    col.charCodeAt(0) + 3
                )}${checkByRow + 1}`
            );

            worksheet.getCell(`${col}${checkByRow + 2}`).value = "Date:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 2}`
            ).border = { bottom: { style: "thin" } };

            worksheet.getCell(`${col}${checkByRow + 3}`).value = "Time:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 3}`
            ).border = { bottom: { style: "thin" } };

            worksheet.getCell(`${col}${checkByRow + 4}`).value = "Designation:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 4}`
            ).border = { bottom: { style: "thin" } };
            worksheet.mergeCells(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 4
                }:${String.fromCharCode(col.charCodeAt(0) + 3)}${checkByRow + 4
                }`
            );
        });

        worksheet.columns = [{ width: 15 }, { width: 15 }, { width: 40 }, { width: 15 }];
        
        const buffer = await workbook.xlsx.writeBuffer();
        saveAs(new Blob([buffer]), `SOA_${new Date().toISOString().split("T")[0]}.xlsx`);
        showSuccessToast("Report exported successfully!");
    } catch (e) {
        console.error(e);
        showWarningToast("Failed to generate Excel.");
    }
};

const generateStatementOfAccountSummaryExcelFile = async (excelData) => {
    try {
        const workbook = new ExcelJS.Workbook();
        const worksheet = workbook.addWorksheet("SOA Summary");
        let currentRow = 1;

        worksheet.getCell("D1").value = `Run Date/Time: ${excelData.runDateTime}`;
        worksheet.getCell("D1").alignment = { horizontal: "right" };
        
        currentRow = 4;
        worksheet.getCell(`A${currentRow}`).value = excelData.reportName;
        worksheet.getCell(`A${currentRow}`).font = { bold: true, size: 16 };
        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Statement of Account Summary";
        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = `Period: ${excelData.dateRange}`;
        currentRow += 2;

        const headers = ["CUSTOMER CODE", "CUSTOMER NAME", "TOTAL AMOUNT"];
        const headerRow = worksheet.getRow(currentRow);
        headers.forEach((h, i) => {
            headerRow.getCell(i + 1).value = h;
            headerRow.getCell(i + 1).font = { bold: true };
            headerRow.getCell(i + 1).border = { bottom: { style: "thin" } };
        });
        currentRow++;

        excelData.groupedData.forEach(item => {
            const row = worksheet.getRow(currentRow);
            row.getCell(1).value = item.customer_code;
            row.getCell(2).value = item.customer_name;
            row.getCell(3).value = parseFloat(item.customerAmountTotal) || 0;
            row.getCell(3).numFmt = "#,##0.00";
            currentRow++;
        });

        worksheet.getCell(`B${currentRow}`).value = "Grand Total:";
        worksheet.getCell(`C${currentRow}`).value = parseFloat(excelData.customerOverallAmountTotal) || 0;
        worksheet.getCell(`C${currentRow}`).numFmt = "#,##0.00";
        worksheet.getCell(`C${currentRow}`).font = { bold: true };
        worksheet.getCell(`C${currentRow}`).border = { top: { style: "thin" }, bottom: { style: "double" } };

        currentRow += 3;

        // Signatory section
        const signatoryStartRow = currentRow;

        // Headers for signatory section
        worksheet.getCell(`A${currentRow}`).value = "Prepared By:";
        worksheet.getCell(`E${currentRow}`).value = "Checked By:";
        worksheet.getCell(`I${currentRow}`).value = "Note By:";

        ["A", "E", "I"].forEach((col) => {
            worksheet.getCell(`${col}${currentRow}`).font = { bold: true };
        });

        currentRow += 2;

        // Prepared By section
        worksheet.getCell(`A${currentRow}`).value = excelData.preparedBy;
        worksheet.getCell(`A${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.mergeCells(`A${currentRow}:D${currentRow}`);
        worksheet.getCell(`A${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value =
            "(Signature Over Printed Name)";
        worksheet.getCell(`A${currentRow}`).alignment = {
            horizontal: "center",
        };
        worksheet.getCell(`A${currentRow}`).font = { size: 9 };
        worksheet.mergeCells(`A${currentRow}:D${currentRow}`);

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Date:";
        worksheet.getCell(`B${currentRow}`).value =
            new Date().toLocaleDateString();
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.getCell(`B${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Time:";
        worksheet.getCell(`B${currentRow}`).value =
            new Date().toLocaleTimeString();
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.getCell(`B${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Designation:";
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.mergeCells(`B${currentRow}:D${currentRow}`);

        // Add similar structure for "Checked By" and "Note By" columns
        const checkByRow = signatoryStartRow + 2;

        // Checked By and Note By sections
        ["E", "I"].forEach((col) => {
            worksheet.getCell(`${col}${checkByRow}`).border = {
                bottom: { style: "thin" },
            };
            worksheet.mergeCells(
                `${col}${checkByRow}:${String.fromCharCode(
                    col.charCodeAt(0) + 3
                )}${checkByRow}`
            );

            worksheet.getCell(`${col}${checkByRow + 1}`).value =
                "(Signature Over Printed Name)";
            worksheet.getCell(`${col}${checkByRow + 1}`).alignment = {
                horizontal: "center",
            };
            worksheet.getCell(`${col}${checkByRow + 1}`).font = { size: 9 };
            worksheet.mergeCells(
                `${col}${checkByRow + 1}:${String.fromCharCode(
                    col.charCodeAt(0) + 3
                )}${checkByRow + 1}`
            );

            worksheet.getCell(`${col}${checkByRow + 2}`).value = "Date:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 2}`
            ).border = { bottom: { style: "thin" } };

            worksheet.getCell(`${col}${checkByRow + 3}`).value = "Time:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 3}`
            ).border = { bottom: { style: "thin" } };

            worksheet.getCell(`${col}${checkByRow + 4}`).value = "Designation:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 4}`
            ).border = { bottom: { style: "thin" } };
            worksheet.mergeCells(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 4
                }:${String.fromCharCode(col.charCodeAt(0) + 3)}${checkByRow + 4
                }`
            );
        });

        worksheet.columns = [{ width: 15 }, { width: 40 }, { width: 20 }];
        
        const buffer = await workbook.xlsx.writeBuffer();
        saveAs(new Blob([buffer]), `SOA_Summary_${new Date().toISOString().split("T")[0]}.xlsx`);
        showSuccessToast("Report exported successfully!");
    } catch (e) {
        console.error(e);
        showWarningToast("Failed to generate Excel.");
    }
};

const generateAROutstandingExcelFile = async (excelData) => {
    try {
        const workbook = new ExcelJS.Workbook();
        const worksheet = workbook.addWorksheet("AR Outstanding Balance");

        let currentRow = 1;

        // Top-right information
        worksheet.getCell(
            "K1"
        ).value = `Run Date/Time: ${excelData.runDateTime}`;
        worksheet.getCell("K2").value =
            "Note: This document is not valid without complete signatory.";
        worksheet.getCell("K1").font = { size: 9 };
        worksheet.getCell("K2").font = { size: 9, color: { argb: "e74c3c" } };
        worksheet.getCell("K1").alignment = { horizontal: "right" };
        worksheet.getCell("K2").alignment = { horizontal: "right" };

        // Header section
        currentRow = 4;
        worksheet.getCell(`A${currentRow}`).value = excelData.reportName;
        worksheet.getCell(`A${currentRow}`).font = { bold: true, size: 16 };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value =
            "Accounts Receivable System";
        worksheet.getCell(`A${currentRow}`).font = { size: 12 };

        currentRow++;
        const label1 = (excelData.dateRange && excelData.dateRange.includes("to")) ? "DR" : "AO";
        worksheet.getCell(
            `A${currentRow}`
        ).value = `AR Outstanding Balances (${label1})`;
        worksheet.getCell(`A${currentRow}`).font = { size: 12 };

        currentRow += 2;
        const label2 = (excelData.dateRange && excelData.dateRange.includes("to"))
            ? "Date Range:"
            : "As of Date:";
        worksheet.getCell(
            `A${currentRow}`
        ).value = `${label2} ${excelData.dateRange}`;
        worksheet.getCell(`A${currentRow}`).font = { bold: true };

        currentRow += 2;

        // Process grouped data
        const groupedData = Array.isArray(excelData.groupedData) 
            ? excelData.groupedData 
            : (excelData.groupedData ? Object.values(excelData.groupedData) : []);

        groupedData.forEach((group, groupIndex) => {
            // Customer header
            worksheet.getCell(
                `A${currentRow}`
            ).value = `${group.customer_code} ${group.customer_name}`;
            worksheet.getCell(`A${currentRow}`).font = { bold: true };
            worksheet.mergeCells(`A${currentRow}:K${currentRow}`);

            currentRow++;

            // Table headers
            const headers = [
                "DOC. NO",
                "DOC. TYPE",
                "RECEIPT DATE",
                "GROSS AMOUNT",
                "S/O",
                "RETURN",
                "ADJUSTMENT",
                "PARTIAL PAYMENT",
                "FLOATING PDC/DC",
                "FLOATING WHT",
                "AR NET AMOUNT",
            ];

            const headerRow = worksheet.getRow(currentRow);
            headers.forEach((header, index) => {
                const cell = headerRow.getCell(index + 1);
                cell.value = header;

                // Apply styling to each cell individually
                cell.font = { bold: true, size: 10 };
                cell.alignment = { horizontal: "center", vertical: "middle" };
                cell.border = {
                    top: { style: "thin" },
                    left: { style: "thin" },
                    bottom: { style: "thin" },
                    right: { style: "thin" },
                };
                cell.fill = {
                    type: "pattern",
                    pattern: "solid",
                    fgColor: { argb: "f0f0f0" },
                };
            });
            // headers.forEach((header, index) => {
            //     headerRow.getCell(index + 1).value = header;
            // });

            // Style header row
            // headerRow.eachCell((cell) => {
            //     cell.font = { bold: true, size: 10 };
            //     cell.alignment = { horizontal: "center", vertical: "middle" };
            //     cell.border = {
            //         top: { style: "thin" },
            //         left: { style: "thin" },
            //         bottom: { style: "thin" },
            //         right: { style: "thin" },
            //     };
            //     cell.fill = {
            //         type: "pattern",
            //         pattern: "solid",
            //         fgColor: { argb: "f0f0f0" },
            //     };
            // });

            currentRow++;

            // Data rows for this customer
            group.outstandingBalances.forEach((balance) => {
                const dataRow = worksheet.getRow(currentRow);

                dataRow.getCell(1).value = balance.document_no;
                dataRow.getCell(2).value = balance.type;
                dataRow.getCell(3).value = new Date(balance.receipt_date);
                dataRow.getCell(4).value =
                    parseFloat(balance.gross_amount) || 0;
                dataRow.getCell(5).value =
                    parseFloat(balance.shrinkage_overage) || 0;
                dataRow.getCell(6).value = parseFloat(balance.return) || 0;
                dataRow.getCell(7).value = parseFloat(balance.adjustment) || 0;
                dataRow.getCell(8).value =
                    parseFloat(balance.partial_payment) || 0;
                dataRow.getCell(9).value =
                    parseFloat(balance.floating_pdc_dc) || 0;
                dataRow.getCell(10).value =
                    parseFloat(balance.floating_wht) || 0;
                dataRow.getCell(11).value =
                    parseFloat(balance.ar_net_amount) || 0;

                // Format cells
                dataRow.getCell(1).alignment = { horizontal: "center" };
                dataRow.getCell(2).alignment = { horizontal: "center" };
                dataRow.getCell(3).alignment = { horizontal: "center" };
                dataRow.getCell(3).numFmt = "mm/dd/yyyy";

                // Format currency columns (4-11)
                for (let col = 4; col <= 11; col++) {
                    dataRow.getCell(col).numFmt = "#,##0.00";
                    dataRow.getCell(col).alignment = { horizontal: "right" };
                }

                // Add thin borders
                dataRow.eachCell((cell) => {
                    cell.border = {
                        top: { style: "thin" },
                        left: { style: "thin" },
                        bottom: { style: "thin" },
                        right: { style: "thin" },
                    };
                });

                currentRow++;
            });

            // Subtotal row
            const subtotalRow = worksheet.getRow(currentRow);
            subtotalRow.getCell(10).value = "Sub Total :";
            subtotalRow.getCell(11).value =
                parseFloat(group.customerAmountTotal) || 0;

            // Style subtotal row
            subtotalRow.getCell(10).font = { bold: true };
            subtotalRow.getCell(10).alignment = { horizontal: "right" };
            subtotalRow.getCell(11).font = { bold: true };
            subtotalRow.getCell(11).numFmt = "#,##0.00";
            subtotalRow.getCell(11).alignment = { horizontal: "right" };

            // Add top border to subtotal
            subtotalRow.eachCell((cell, colNumber) => {
                if (colNumber >= 10) {
                    cell.border = {
                        top: { style: "thin" },
                        left: { style: "thin" },
                        bottom: { style: "thin" },
                        right: { style: "thin" },
                    };
                }
            });

            currentRow += 2; // Add space between customer groups
        });

        // Overall total
        const totalRow = worksheet.getRow(currentRow);
        totalRow.getCell(10).value = "Total Amount:";
        totalRow.getCell(11).value =
            parseFloat(excelData.customerOverallAmountTotal) || 0;

        totalRow.getCell(10).font = { bold: true, size: 12 };
        totalRow.getCell(10).alignment = { horizontal: "right" };
        totalRow.getCell(11).font = { bold: true, size: 12 };
        totalRow.getCell(11).numFmt = "#,##0.00";
        totalRow.getCell(11).alignment = { horizontal: "right" };

        // Add borders to total row
        totalRow.eachCell((cell, colNumber) => {
            if (colNumber >= 10) {
                cell.border = {
                    top: { style: "thick" },
                    left: { style: "thin" },
                    bottom: { style: "thick" },
                    right: { style: "thin" },
                };
            }
        });

        currentRow += 3;

        // Signatory section
        const signatoryStartRow = currentRow;

        // Headers for signatory section
        worksheet.getCell(`A${currentRow}`).value = "Prepared By:";
        worksheet.getCell(`E${currentRow}`).value = "Checked By:";
        worksheet.getCell(`I${currentRow}`).value = "Note By:";

        ["A", "E", "I"].forEach((col) => {
            worksheet.getCell(`${col}${currentRow}`).font = { bold: true };
        });

        currentRow += 2;

        // Prepared By section
        worksheet.getCell(`A${currentRow}`).value = excelData.preparedBy;
        worksheet.getCell(`A${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.mergeCells(`A${currentRow}:D${currentRow}`);
        worksheet.getCell(`A${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value =
            "(Signature Over Printed Name)";
        worksheet.getCell(`A${currentRow}`).alignment = {
            horizontal: "center",
        };
        worksheet.getCell(`A${currentRow}`).font = { size: 9 };
        worksheet.mergeCells(`A${currentRow}:D${currentRow}`);

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Date:";
        worksheet.getCell(`B${currentRow}`).value =
            new Date().toLocaleDateString();
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.getCell(`B${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Time:";
        worksheet.getCell(`B${currentRow}`).value =
            new Date().toLocaleTimeString();
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.getCell(`B${currentRow}`).alignment = {
            horizontal: "center",
        };

        currentRow++;
        worksheet.getCell(`A${currentRow}`).value = "Designation:";
        worksheet.getCell(`B${currentRow}`).border = {
            bottom: { style: "thin" },
        };
        worksheet.mergeCells(`B${currentRow}:D${currentRow}`);

        // Add similar structure for "Checked By" and "Note By" columns
        const checkByRow = signatoryStartRow + 2;

        // Checked By and Note By sections (simplified)
        ["E", "I"].forEach((col) => {
            worksheet.getCell(`${col}${checkByRow}`).border = {
                bottom: { style: "thin" },
            };
            worksheet.mergeCells(
                `${col}${checkByRow}:${String.fromCharCode(
                    col.charCodeAt(0) + 3
                )}${checkByRow}`
            );

            worksheet.getCell(`${col}${checkByRow + 1}`).value =
                "(Signature Over Printed Name)";
            worksheet.getCell(`${col}${checkByRow + 1}`).alignment = {
                horizontal: "center",
            };
            worksheet.getCell(`${col}${checkByRow + 1}`).font = { size: 9 };
            worksheet.mergeCells(
                `${col}${checkByRow + 1}:${String.fromCharCode(
                    col.charCodeAt(0) + 3
                )}${checkByRow + 1}`
            );

            worksheet.getCell(`${col}${checkByRow + 2}`).value = "Date:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 2}`
            ).border = { bottom: { style: "thin" } };

            worksheet.getCell(`${col}${checkByRow + 3}`).value = "Time:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 3}`
            ).border = { bottom: { style: "thin" } };

            worksheet.getCell(`${col}${checkByRow + 4}`).value = "Designation:";
            worksheet.getCell(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 4}`
            ).border = { bottom: { style: "thin" } };
            worksheet.mergeCells(
                `${String.fromCharCode(col.charCodeAt(0) + 1)}${checkByRow + 4
                }:${String.fromCharCode(col.charCodeAt(0) + 3)}${checkByRow + 4
                }`
            );
        });

        // Set column widths
        worksheet.columns = [
            { width: 12 }, // DOC. NO
            { width: 12 }, // DOC. TYPE
            { width: 12 }, // RECEIPT DATE
            { width: 15 }, // GROSS AMOUNT
            { width: 10 }, // S/O
            { width: 10 }, // RETURN
            { width: 12 }, // ADJUSTMENT
            { width: 15 }, // PARTIAL PAYMENT
            { width: 15 }, // FLOATING PDC/DC
            { width: 12 }, // FLOATING WHT
            { width: 15 }, // AR NET AMOUNT
        ];

        // Generate filename
        const currentDate = new Date().toISOString().split("T")[0];
        const filename = `AR_Outstanding_Balance_${currentDate}.xlsx`;

        // Generate blob and download
        const buffer = await workbook.xlsx.writeBuffer();
        const blob = new Blob([buffer], {
            type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        });

        saveAs(blob, filename);

        showSuccessToast(
            `AR Outstanding Balance report exported successfully!`
        );
    } catch (error) {
        console.error("Error generating AR Outstanding Excel file:", error);
        showWarningToast(
            "Failed to generate AR Outstanding Excel file. Please try again."
        );
        throw error;
    }
};

onMounted(() => {
    userId.value = page.props.auth.user.id || null;
});

onUnmounted(() => {
    if (echo) {
        echo.leave(channel.value);
    }
});

watch(
    () => props.show,
    (isShowing) => {
        if (isShowing) {
            startPdfGeneration();
        } else {
            if (echo) {
                echo.leave(channel.value);
            }
        }
    },
    { immediate: true }
);

const printPdf = async () => {
    if (!pdfUrl.value) return;

    const iframe = document.createElement("iframe");
    iframe.style.display = "none";
    document.body.appendChild(iframe);

    try {
        iframe.src = pdfUrl.value;

        await new Promise((resolve) => {
            iframe.onload = resolve;
        });

        let dialogClosed = false;
        const dialogCheckInterval = 500; // ms
        let printAttempted = false;

        const printPromise = new Promise((resolve) => {
            const handleAfterPrint = () => {
                if (printAttempted) {
                    dialogClosed = true;
                    window.removeEventListener("afterprint", handleAfterPrint);
                    resolve();
                }
            };

            const handleFocus = () => {
                if (printAttempted && !dialogClosed) {
                    dialogClosed = true;
                    window.removeEventListener("focus", handleFocus);
                    resolve();
                }
            };

            const fallbackTimeout = setTimeout(() => {
                if (!dialogClosed) {
                    dialogClosed = true;
                    window.removeEventListener("afterprint", handleAfterPrint);
                    window.removeEventListener("focus", handleFocus);
                    resolve();
                }
            }, 10000);

            window.addEventListener("afterprint", handleAfterPrint);
            window.addEventListener("focus", handleFocus);

            const originalPrint = iframe.contentWindow.print;
            iframe.contentWindow.print = function () {
                printAttempted = true;
                originalPrint.apply(this, arguments);
            };

            setTimeout(() => {
                iframe.contentWindow?.print();
            }, 100);

            Promise.resolve().then(() => {
                clearTimeout(fallbackTimeout);
            });
        });

        await printPromise;
        emit("closeSuccess");
    } catch (error) {
        console.error("Print error:", error);
        emit("printError", error.message);
    } finally {
        iframe.remove();
        await deletePdf();
    }
};

const deletePdf = async () => {
    if (!pathDelete.value) return;

    try {
        await axios.delete(route("pdf.delete", { tenant: page.props.tenant }), {
            data: { path: pathDelete.value.split("/storage/")[1] },
        });
    } catch (err) {
        console.warn("Failed to delete PDF:", err);
    } finally {
        URL.revokeObjectURL(pdfUrl.value);
        pdfUrl.value = null;
    }
};
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.slide-enter-active,
.slide-leave-active {
    transition: all 0.3s ease;
}

.slide-enter-from,
.slide-leave-to {
    transform: translateY(-20px);
    opacity: 0;
}
</style>
