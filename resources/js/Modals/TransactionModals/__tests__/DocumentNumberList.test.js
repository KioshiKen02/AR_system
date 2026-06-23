import { mount, flushPromises } from "@vue/test-utils";
import { describe, expect, it, vi, beforeEach } from "vitest";
import DocumentNumberList from "../DocumentNumberList.vue";
import axios from "axios";

// Mock global axios
window.axios = {
    get: vi.fn(),
    post: vi.fn(),
};

// Mock dependencies
vi.mock("@inertiajs/vue3", () => {
    return {
        usePage: () => ({
            props: {
                tenant: "test",
            },
        }),
    };
});

vi.mock("../../../../../vendor/tightenco/ziggy/src/js", () => {
    return {
        route: (name, params) => `/${name}`,
    };
});

const mountModal = (props = {}) => {
    return mount(DocumentNumberList, {
        props: {
            customer_code: "C-001",
            date: "2025-01-01",
            paymentType: "Cash",
            editable_wht: true,
            whtEnabled: false,
            autoApplyWht: false,
            ...props,
        },
        global: {
            stubs: {
                Transition: { template: "<div><slot /></div>" },
                ConfirmationDialog: { template: "<div />" },
                InformationDialog: { template: "<div />" },
                ToastAlertWarning: { template: "<div />" },
                DropdownInput: {
                    template: `<select :value="modelValue" @change="$emit('update:modelValue', $event.target.value)"><slot /><option v-for="opt in options" :value="opt">{{ opt }}</option></select>`,
                    props: ["modelValue", "options"],
                },
                TextInput: {
                    template: `<input :value="modelValue" @input="$emit('update:modelValue', $event.target.value)" :readonly="readonly" />`,
                    props: ["modelValue", "readonly"],
                },
                "svg-icon": { template: "<i />" },
            },
        },
    });
};

describe("DocumentNumberList.vue", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        window.axios.get.mockResolvedValue({
            data: [
                {
                    invoice_no: "INV-001",
                    receipt_date: "2025-01-01",
                    type: "Sales Invoice",
                    amount: 1000,
                    amount_paid: 0,
                    running_balance: "1000.00",
                    trade_type: "Trade",
                    pdc_floating_amount: 0,
                    has_pdc_floating_payments: false,
                    dc_floating_amount: 0,
                    has_dc_floating_payments: false,
                    wht_floating_amount: 0,
                    has_wht_floating_payments: false,
                },
            ],
        });
    });

    it("displays legacy mode without tax controls when whtEnabled is false", async () => {
        const wrapper = mountModal({ whtEnabled: false });
        await flushPromises(); // wait for axios to resolve
        
        expect(wrapper.text()).not.toContain("Tax rate (optional):");
        expect(wrapper.text()).not.toContain("Apply BIR 2307");
        
        // Check that WHT column is not rendered in table headers
        const headers = wrapper.findAll("th");
        const whtHeader = headers.find(h => h.text() === "WHT");
        expect(whtHeader).toBeUndefined();
    });

    it("displays header tax controls when whtEnabled is true", async () => {
        const wrapper = mountModal({ whtEnabled: true });
        await flushPromises();

        expect(wrapper.text()).toContain("Tax rate (optional):");
        expect(wrapper.text()).toContain("Apply BIR 2307");
        
        // Check that WHT column is rendered in table headers
        const headers = wrapper.findAll("th");
        const whtHeader = headers.find(h => h.text().includes("WHT"));
        expect(whtHeader).toBeDefined();
    });

    it("applies WHT automatically when an invoice is selected and whtEnabled is true", async () => {
        const wrapper = mountModal({ whtEnabled: true, autoApplyWht: true });
        await flushPromises();

        const taxRateSelect = wrapper.find('[data-testid="tax-rate-select"]');
        await taxRateSelect.setValue('1%');
        await flushPromises();

        // Switch to Manual Select mode
        const radioInputs = wrapper.findAll('input[type="radio"]');
        const manualSelectRadio = radioInputs.find(r => r.attributes("value") === "Manual Select");
        await manualSelectRadio.setValue();

        // Select the invoice
        const checkbox = wrapper.find('tbody tr td input[type="checkbox"]');
        await checkbox.trigger('click');
        await flushPromises();

        // Check if wht_amount was calculated (1% of 1000 = 10)
        // Emitted submit event should contain the wht details
        const submitButton = wrapper.find('.submitButton');
        await submitButton.trigger('click');
        
        const submitEvents = wrapper.emitted('submit');
        expect(submitEvents).toBeTruthy();
        
        const payload = submitEvents[0][0];
        expect(payload.selectedDocuments[0].wht_amount).toBe(10);
        expect(payload.selectedDocuments[0].amountToPay).toBe(990);
        expect(payload.apply_bir_2307).toBe(true);
        expect(payload.tax_rate).toBe("1%");
    });

    it("updates WHT when tax rate is changed", async () => {
        const wrapper = mountModal({ whtEnabled: true, autoApplyWht: true });
        await flushPromises();

        const taxRateSelect = wrapper.find('[data-testid="tax-rate-select"]');
        await taxRateSelect.setValue('1%');
        await flushPromises();

        // Switch to Manual Select mode
        const radioInputs = wrapper.findAll('input[type="radio"]');
        const manualSelectRadio = radioInputs.find(r => r.attributes("value") === "Manual Select");
        await manualSelectRadio.setValue();

        // Select the invoice
        const checkbox = wrapper.find('tbody tr td input[type="checkbox"]');
        await checkbox.trigger('click');
        await flushPromises();

        // Change tax rate to 5%
        const dropdown = wrapper.find('[data-testid="tax-rate-select"]');
        await dropdown.setValue('5%');
        await flushPromises();

        // Emitted submit event should contain the new wht details
        const submitButton = wrapper.find('.submitButton');
        await submitButton.trigger('click');
        
        const submitEvents = wrapper.emitted('submit');
        const payload = submitEvents[0][0];
        
        // 5% of 1000 = 50
        expect(payload.selectedDocuments[0].wht_amount).toBe(50);
        expect(payload.selectedDocuments[0].amountToPay).toBe(950);
        expect(payload.tax_rate).toBe("5%");
    });

    it("allows manual WHT when tax rate is None", async () => {
        const wrapper = mountModal({ whtEnabled: true, autoApplyWht: true });
        await flushPromises();

        // Switch to Manual Select mode
        const radioInputs = wrapper.findAll('input[type="radio"]');
        const manualSelectRadio = radioInputs.find(r => r.attributes("value") === "Manual Select");
        await manualSelectRadio.setValue();

        // Select the invoice
        const checkbox = wrapper.find('tbody tr td input[type="checkbox"]');
        await checkbox.trigger('click');
        await flushPromises();

        const whtInput = wrapper.find('[data-testid="wht-manual-input"]');
        expect(whtInput.exists()).toBe(true);
        await whtInput.setValue('25');
        await whtInput.trigger('input');
        await flushPromises();

        const submitButton = wrapper.find('.submitButton');
        await submitButton.trigger('click');

        const submitEvents = wrapper.emitted('submit');
        const payload = submitEvents[0][0];
        expect(payload.tax_rate).toBe('None');
        expect(payload.selectedDocuments[0].wht_amount).toBe(25);
        expect(payload.selectedDocuments[0].amountToPay).toBe(975);
    });

    it("shows error feedback for invalid manual WHT values", async () => {
        const wrapper = mountModal({ whtEnabled: true, autoApplyWht: true });
        await flushPromises();

        // Switch to Manual Select mode
        const radioInputs = wrapper.findAll('input[type="radio"]');
        const manualSelectRadio = radioInputs.find(r => r.attributes("value") === "Manual Select");
        await manualSelectRadio.setValue();

        // Select the invoice
        const checkbox = wrapper.find('tbody tr td input[type="checkbox"]');
        await checkbox.trigger('click');
        await flushPromises();

        const whtInput = wrapper.find('[data-testid="wht-manual-input"]');
        await whtInput.setValue('abc');
        await flushPromises();

        const updatedWhtInput = wrapper.find('[data-testid="wht-manual-input"]');
        expect(updatedWhtInput.attributes('class')).toContain('border-red-400');

        const submitButton = wrapper.find('.submitButton');
        await submitButton.trigger('click');

        const submitEvents = wrapper.emitted('submit');
        const payload = submitEvents[0][0];
        expect(payload.selectedDocuments[0].wht_amount).toBe(0);
        expect(payload.selectedDocuments[0].amountToPay).toBe(1000);
    });
});
