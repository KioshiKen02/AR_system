import { mount } from "@vue/test-utils";
import { describe, expect, it, vi } from "vitest";
import CustomerLedger from "@/Pages/CustomerLedger.vue";

let lastWorksheet = null;

vi.mock("@inertiajs/vue3", () => {
    return {
        router: {
            get: vi.fn(),
            delete: vi.fn(),
        },
        usePage: () => ({
            props: {
                tenant: "test",
                auth: { user: { id: 1 } },
            },
        }),
    };
});

const { saveAs } = vi.hoisted(() => ({ saveAs: vi.fn() }));
vi.mock("file-saver", () => ({ saveAs }));

vi.mock("exceljs", () => {
    class WorksheetStub {
        constructor() {
            this._rows = [];
            this.columns = [];
            this.views = [];
            this._cells = new Map();
        }
        addRow(values) {
            this._rows.push(values);
            return {
                eachCell: (cb) => {
                    values.forEach(() =>
                        cb({
                            font: null,
                            fill: null,
                            border: null,
                            alignment: null,
                            value: null,
                            numFmt: null,
                        })
                    );
                },
            };
        }
        insertRow() {}
        getColumn() {
            return { numFmt: null, alignment: null };
        }
        getCell(key) {
            if (!this._cells.has(key)) {
                this._cells.set(key, { value: null, numFmt: null, font: null, fill: null, alignment: null });
            }
            return this._cells.get(key);
        }
        mergeCells() {}
    }

    class WorkbookStub {
        constructor() {
            this.xlsx = {
                writeBuffer: vi.fn().mockResolvedValue(new ArrayBuffer(0)),
            };
        }
        addWorksheet() {
            lastWorksheet = new WorksheetStub();
            return lastWorksheet;
        }
    }

    return { default: { Workbook: WorkbookStub } };
});

const mountLedger = (overrides = {}) => {
    const customerledgers =
        overrides.customerledgers ??
        {
            data: [
                {
                    id: 1,
                    invoice_number: "INV-001",
                    date: "2025-01-01",
                    customer_name: "Alice",
                    customer_code: "C-001",
                    type: "Payment",
                    debit_amount: 0,
                    amount_paid: 100,
                    running_balance: 900,
                    floating_amount: 0,
                    has_floating_deduction: false,
                },
                {
                    id: 2,
                    invoice_number: "INV-002",
                    date: "2025-01-02",
                    customer_name: "Alice",
                    customer_code: "C-001",
                    type: "Payment",
                    debit_amount: 0,
                    amount_paid: 150,
                    running_balance: 750,
                    floating_amount: 25,
                    has_floating_deduction: true,
                },
                {
                    id: 3,
                    invoice_number: "INV-003",
                    date: "2025-01-03",
                    customer_name: "Alice",
                    customer_code: "C-001",
                    type: "Sales Invoice",
                    debit_amount: 200,
                    amount_paid: 0,
                    running_balance: 950,
                    floating_amount: 0,
                    has_floating_deduction: false,
                },
            ],
            total: 3,
        };

    return mount(CustomerLedger, {
        props: {
            customerledgers,
            searchTerm: "",
            paymentForwarded: 0,
            can: {},
            filters: {
                customer_code: "C-001",
                date_start: "2025-01-01",
                date_end: "2025-01-31",
            },
            generateTableData: true,
            broadcastChannel: "customerledgers",
            ...overrides,
        },
        global: {
            mocks: {
                $page: { component: "CustomerLedger" },
            },
            stubs: {
                Head: { template: "<div />" },
                Transition: { template: "<div><slot /></div>" },
                ToastAlert: { template: "<div />" },
                ToastAlertWarning: { template: "<div />" },
                ConfirmationDialog: { template: "<div />" },
                TextInput: { template: "<input />" },
                DatePicker: { template: "<input />" },
                ViewCustomerLedger: { template: "<div />" },
                PaymenHistory: { template: "<div />" },
                CustomerListModal: { template: "<div />" },
                FunnelIcon: { template: "<div />" },
                "svg-icon": { template: "<i />" },
            },
        },
    });
};

describe("CustomerLedger client-side filtering", () => {
    it("renders all records when floating rows are not hidden", () => {
        const wrapper = mountLedger();
        expect(wrapper.findAll('[data-testid="ledger-row"]').length).toBe(3);
        expect(wrapper.text()).toContain("INV-001");
        expect(wrapper.text()).toContain("INV-002");
        expect(wrapper.text()).toContain("INV-003");
    });

    it("hides floating-deducted rows when the hide checkbox is toggled", async () => {
        const wrapper = mountLedger();
        await wrapper.find('[data-testid="hide-floating"]').setValue(true);
        expect(wrapper.findAll('[data-testid="ledger-row"]').length).toBe(2);
        expect(wrapper.text()).toContain("INV-001");
        expect(wrapper.text()).toContain("INV-003");
        expect(wrapper.text()).not.toContain("INV-002");
    });

    it("exports only the currently visible rows", async () => {
        saveAs.mockClear();
        lastWorksheet = null;

        const wrapper = mountLedger();
        await wrapper.find('[data-testid="hide-floating"]').setValue(true);

        const exportButton = wrapper
            .findAll("button")
            .find((b) => b.text().includes("Export To Excel"));
        expect(exportButton).toBeTruthy();

        await exportButton.trigger("click");
        await new Promise((r) => setTimeout(r, 0));

        expect(saveAs).toHaveBeenCalledTimes(1);
        expect(lastWorksheet).not.toBeNull();

        const dataRowCount = lastWorksheet._rows.length - 1;
        expect(dataRowCount).toBe(2);
        expect(lastWorksheet._rows[1][0]).toBe("INV-001");
        expect(lastWorksheet._rows[2][0]).toBe("INV-003");
    });
});
