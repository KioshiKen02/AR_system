import { mount } from "@vue/test-utils";
import { nextTick, reactive } from "vue";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import TransactionAudit from "../TransactionAudit.vue";
import ToastAlert from "../Components/ToastAlert.vue";

const mocks = vi.hoisted(() => ({ post: vi.fn(), get: vi.fn(), permissions: {} }));
vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { tenant: "iceplant", auth: { permissions: mocks.permissions } } }),
    router: { get: mocks.get },
    useForm: (data) => reactive({ ...data, errors: {}, processing: false, post: mocks.post, reset: vi.fn() }),
}));
vi.mock("../../../../vendor/tightenco/ziggy/src/js", () => ({
    route: (name, params) => `/${params.tenant}/${name}`,
}));

let wrapper;
beforeEach(() => {
    mocks.permissions = {
        "0405-UNTAG": { can_view: true, can_update: true },
        "0406-AUDIT": { can_view: true },
    };
});
const mountPage = (props = {}) => {
    wrapper = mount(TransactionAudit, {
        attachTo: document.body,
        props: {
            auditReady: true, auditTimezone: "Asia/Manila", filters: {},
            logs: { total: 1, data: [{
                id: 1, transaction_type: "Payment", transaction_no: "26001802",
                action: "receipt_correction", occurred_at: "2026-09-04T02:00:00.000000Z",
                actor_name: "Test Editor", actor_username: "editor", actor_id: "7",
                remarks: "Correct DS No", changes: [
                    { table: "payment", record_id: 1, field: "ds_no", old: "DS#0245", new: "DS#0246" },
                ],
            }] },
            ...props,
        },
        global: { stubs: { Head: true, PaginationLinks: true } },
    });
    return wrapper;
};
afterEach(() => { wrapper?.unmount(); vi.useRealTimers(); vi.clearAllMocks(); });

describe("Transaction audit utility", () => {
    it("shows a simple audit table and opens old/new values with View Details", async () => {
        const page = mountPage();
        for (const text of ["26001802", "Test Editor", "Correct DS No", "Actions", "Asia/Manila"]) {
            expect(page.text()).toContain(text);
        }
        expect(page.find("time").exists()).toBe(true);
        expect(page.find('[role="dialog"]').exists()).toBe(false);
        expect(page.text()).not.toContain("DS#0245");
        await page.get('button[aria-label="View details for Payment 26001802"]').trigger("click");
        expect(page.get('[role="dialog"]').text()).toContain("DS#0245");
        expect(page.get('[role="dialog"]').text()).toContain("DS#0246");
        await page.get('[role="dialog"] button').trigger("click");
        expect(page.find('[role="dialog"]').exists()).toBe(false);
    });

    it("requires acknowledgement and remarks before untagging", async () => {
        const page = mountPage({ filters: { tab: "untag" } });
        expect(page.get('button[type="submit"]').attributes("disabled")).toBeDefined();
        await page.get("#untag-number").setValue("26001802");
        await page.get("#untag-remarks").setValue("Approved re-export");
        await page.get('input[type="checkbox"]').setValue(true);
        expect(page.get('button[type="submit"]').attributes("disabled")).toBeUndefined();
        await page.get("form").trigger("submit");
        expect(mocks.post).toHaveBeenCalledWith("/iceplant/transactionAudit.untag", expect.any(Object));
        expect(page.text()).toContain("Existing WHT export markers remain unchanged");
    });

    it.each(["Payment", "Adjustment"])("shows a success toast and persistent confirmation after untagging a %s", async (type) => {
        vi.useFakeTimers();
        const page = mountPage({ filters: { tab: "untag" } });
        await page.get("#untag-type").setValue(type);
        await page.get("#untag-number").setValue("26001802");
        await page.get("#untag-remarks").setValue("Approved correction");
        await page.get("#untag-confirm").setValue(true);
        await page.get("form").trigger("submit");
        const options = mocks.post.mock.lastCall[1];
        expect(options.preserveState).toBe(true);
        options.onSuccess();
        await nextTick();
        const message = `${type} 26001802 export tag removed successfully.`;
        expect(page.findComponent(ToastAlert).props("show")).toBe(true);
        expect(page.findComponent(ToastAlert).props("message")).toBe(message);
        expect(page.get('[role="status"]').text()).toBe(message);
        await vi.advanceTimersByTimeAsync(3000);
        expect(page.findComponent(ToastAlert).props("show")).toBe(false);
        expect(page.get('[role="status"]').text()).toBe(message);
    });

    it("uses the server success message and clears old feedback when submitting again", async () => {
        const page = mountPage({ filters: { tab: "untag" } });
        await page.get("#untag-number").setValue("26001802");
        await page.get("#untag-remarks").setValue("Approved correction");
        await page.get("#untag-confirm").setValue(true);
        await page.get("form").trigger("submit");
        const options = mocks.post.mock.lastCall[1];
        options.onSuccess({ props: { flash: { success: "Adjustment untagged successfully." } } });
        await nextTick();
        expect(page.findComponent(ToastAlert).props("message")).toBe("Adjustment untagged successfully.");
        options.onStart();
        await nextTick();
        expect(page.findComponent(ToastAlert).props("show")).toBe(false);
        expect(page.find('[role="status"]').exists()).toBe(false);
    });

    it("disables untagging when the BU audit table is not installed", () => {
        const page = mountPage({ auditReady: false, filters: { tab: "untag" } });
        expect(page.text()).toContain("Audit logging is not installed");
        expect(page.get('button[type="submit"]').attributes("disabled")).toBeDefined();
    });

    it("submits audit filters to the utility route", async () => {
        const page = mountPage();
        await page.get("#audit-search").setValue("26001802");
        await page.get("#audit-type").setValue("Payment");
        await page.get("form").trigger("submit");
        expect(mocks.get).toHaveBeenCalledWith("/iceplant/transactionAudit",
            expect.objectContaining({ search: "26001802", transaction_type: "Payment", tab: "logs" }),
            expect.any(Object));
    });

    it("blocks view-only untag submissions without showing cross-page tabs", async () => {
        mocks.permissions = { "0405-UNTAG": { can_view: true, can_update: false } };
        const page = mountPage({ logs: null, filters: { tab: "untag" } });
        expect(page.findAll("button").some((button) => button.text() === "Audit Trail")).toBe(false);
        expect(page.get('button[type="submit"]').attributes("disabled")).toBeDefined();
        await page.get("form").trigger("submit");
        expect(mocks.post).not.toHaveBeenCalled();
    });

    it.each([{}, { tab: "untag" }])("has no cross-page tabs even with both utility permissions (%j)", (filters) => {
        const page = mountPage({ filters });
        expect(page.find('nav[aria-label="Transaction utilities"]').exists()).toBe(false);
        expect(page.find('button[aria-current]').exists()).toBe(false);
        expect(page.findAll("button").some((button) => ["Audit Trail", "Untag Export"].includes(button.text()))).toBe(false);
    });

    it("keeps advanced filters hidden until requested and exposes their expanded state", async () => {
        const page = mountPage();
        const toggle = page.get('button[aria-controls="audit-more-filters"]');
        expect(page.get("#audit-more-filters").isVisible()).toBe(false);
        expect(toggle.attributes("aria-expanded")).toBe("false");
        await toggle.trigger("click");
        expect(page.get("#audit-more-filters").isVisible()).toBe(true);
        expect(toggle.attributes("aria-expanded")).toBe("true");
    });

    it("shows active date filters immediately", () => {
        const page = mountPage({ filters: { date_start: "2026-09-01" } });
        expect(page.get("#audit-more-filters").isVisible()).toBe(true);
    });

    it("keeps synchronized rows out of the list and shows all of them in the details modal", async () => {
        const page = mountPage({ logs: { total: 1, data: [{
            id: 1, transaction_type: "Payment", transaction_no: "26001802",
            action: "receipt_correction", occurred_at: "2026-09-04T02:00:00Z",
            actor_name: "Test Editor", actor_id: "7", remarks: "Correct receipt date",
            changes: [
                { table: "payment", record_id: 1, field: "receipt_date", old: "2026-09-02", new: "2026-09-03" },
                { table: "payment_details", record_id: 11, field: "payment_receipt_date", old: "2026-09-02", new: "2026-09-03" },
                { table: "customer_ledger", record_id: 20, field: "date", old: "2026-09-02", new: "2026-09-03" },
            ],
        }] } });
        expect(page.findAll("tbody tr")).toHaveLength(1);
        expect(page.text()).not.toContain("Payment Receipt Date");
        await page.get('button[aria-label="View details for Payment 26001802"]').trigger("click");
        const details = page.get('[role="dialog"]');
        expect(details.text()).toContain("Receipt Date");
        expect(details.text()).toContain("Payment Receipt Date");
        expect(details.text()).toContain("Ledger Date");
        expect(details.findAll("tbody tr")).toHaveLength(3);
        await details.trigger("keydown", { key: "Escape" });
        expect(page.find('[role="dialog"]').exists()).toBe(false);
    });

    it("requires a transaction number even after the reason and acknowledgement are supplied", async () => {
        const page = mountPage({ filters: { tab: "untag" } });
        await page.get("#untag-remarks").setValue("Approved correction");
        await page.get('input[type="checkbox"]').setValue(true);
        expect(page.get('button[type="submit"]').attributes("disabled")).toBeDefined();
        await page.get("#untag-number").setValue("26001802");
        expect(page.get('button[type="submit"]').attributes("disabled")).toBeUndefined();
    });

    it("reviews the entered transaction type and number without claiming it has been verified", async () => {
        const page = mountPage({ filters: { tab: "untag" } });
        await page.get("#untag-type").setValue("Adjustment");
        await page.get("#untag-number").setValue("26001001");
        expect(page.text()).toContain("Entered adjustment number");
        expect(page.text()).toContain("26001001");
        expect(page.text()).toContain("Review and confirm");
        expect(page.text()).toContain("Existing export files and GL entries");
    });

    it("clears the transaction draft and acknowledgement", async () => {
        const page = mountPage({ filters: { tab: "untag" } });
        await page.get("#untag-type").setValue("Adjustment");
        await page.get("#untag-number").setValue("26001001");
        await page.get("#untag-remarks").setValue("Correction needed");
        await page.get("#untag-confirm").setValue(true);
        const clear = page.findAll("button").find((button) => button.text() === "Clear Form");
        await clear.trigger("click");
        expect(page.get("#untag-type").element.value).toBe("Payment");
        expect(page.get("#untag-number").element.value).toBe("");
        expect(page.get("#untag-remarks").element.value).toBe("");
        expect(page.get("#untag-confirm").element.checked).toBe(false);
        expect(page.get('button[type="submit"]').attributes("disabled")).toBeDefined();
    });
});
