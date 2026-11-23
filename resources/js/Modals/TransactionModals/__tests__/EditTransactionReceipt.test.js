import { mount } from "@vue/test-utils";
import { reactive } from "vue";
import { afterEach, describe, expect, it, vi } from "vitest";
import EditTransactionReceipt from "../EditTransactionReceipt.vue";

const mocks = vi.hoisted(() => ({ put: vi.fn() }));
vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { tenant: "iceplant" } }),
    useForm: (data) => reactive({ ...data, errors: {}, processing: false, put: mocks.put }),
}));
vi.mock("../../../../../vendor/tightenco/ziggy/src/js", () => ({
    route: (name, params) => `/${params.tenant}/${name}/${params.id}`,
}));

let wrapper;
const mountEditor = (kind = "Payment") => {
    wrapper = mount(EditTransactionReceipt, {
        attachTo: document.body,
        props: {
            kind,
            selected: { id: 1, payment_no: kind === "Payment" ? "26001802" : null,
                adjustment_no: "26001802", receipt_date: "2026-09-02", ds_no: "DS#0245" },
        },
    });
    return wrapper;
};
afterEach(() => { wrapper?.unmount(); vi.clearAllMocks(); });

describe("Audited receipt correction editor", () => {
    it("requires a change and remarks, previews old/new values, and posts the payment receipt endpoint", async () => {
        const editor = mountEditor();
        expect(editor.get('button[type="submit"]').attributes("disabled")).toBeDefined();
        await editor.get("#edit-ds-no").setValue("DS#0246");
        expect(editor.get('button[type="submit"]').attributes("disabled")).toBeDefined();
        await editor.get("#edit-receipt-remarks").setValue("Correct DS No");
        expect(editor.get('button[type="submit"]').attributes("disabled")).toBeUndefined();
        expect(editor.text()).toContain("DS#0245");
        expect(editor.text()).toContain("DS#0246");
        await editor.get("form").trigger("submit");
        expect(mocks.put).toHaveBeenCalledWith("/iceplant/payment.updateReceipt/1",
            expect.objectContaining({ preserveScroll: true, onSuccess: expect.any(Function) }));
        mocks.put.mock.calls[0][1].onSuccess();
        expect(editor.emitted("saved")).toHaveLength(1);
    });

    it("limits adjustment edits to receipt date and uses the adjustment endpoint", async () => {
        const editor = mountEditor("Adjustment");
        expect(editor.find("#edit-ds-no").exists()).toBe(false);
        await editor.get("#edit-receipt-date").setValue("2026-09-03");
        await editor.get("#edit-receipt-remarks").setValue("Correct adjustment date");
        await editor.get("form").trigger("submit");
        expect(mocks.put).toHaveBeenCalledWith("/iceplant/adjustment.updateReceipt/1", expect.any(Object));
    });

    it("closes with Escape and locks background scrolling until unmounted", async () => {
        const editor = mountEditor();
        expect(document.body.style.overflow).toBe("hidden");
        await editor.get('[role="dialog"]').trigger("keydown", { key: "Escape" });
        expect(editor.emitted("close")).toHaveLength(1);
        editor.unmount();
        wrapper = null;
        expect(document.body.style.overflow).not.toBe("hidden");
    });
});
