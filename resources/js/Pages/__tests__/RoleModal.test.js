import { mount } from "@vue/test-utils";
import { reactive } from "vue";
import { afterEach, describe, expect, it, vi } from "vitest";
import RoleModal from "../../Modals/MasterfileModals/RoleModal.vue";

const mocks = vi.hoisted(() => ({ post: vi.fn(), form: null }));
vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { tenant: "iceplant" } }),
    useForm: (data) => {
        mocks.form = reactive({ ...data, processing: false, post: mocks.post });
        return mocks.form;
    },
}));
vi.stubGlobal("route", (name) => `/${name}`);
let wrapper;
const mountRoleModal = (permissions = {}) => {
    wrapper = mount(RoleModal, {
        props: { userId: 7, permissions },
        global: { stubs: { "svg-icon": true, ToastAlertWarning: true } },
    });
    return wrapper;
};
afterEach(() => { wrapper?.unmount(); vi.clearAllMocks(); });

describe("Role permission modal", () => {
    it.each([true, 1, "1"])("loads Beginning Balance Update correctly when its saved value is %s", (enabled) => {
        const modal = mountRoleModal({ "0204-BGBLT": { can_update: enabled } });
        expect(modal.get('input[aria-label="AR Beg Bal Transaction can_update"]').element.checked).toBe(true);
    });

    it.each([false, 0, "0", null])("does not treat %s as an enabled permission", (disabled) => {
        const modal = mountRoleModal({ "0204-BGBLT": { can_update: disabled } });
        expect(modal.get('input[aria-label="AR Beg Bal Transaction can_update"]').element.checked).toBe(false);
    });

    it("saves Beginning Balance Update and allows Adjustment Update and utility permissions", async () => {
        const modal = mountRoleModal();
        for (const label of ["AR Beg Bal Transaction can_update", "Adjustment Transaction can_update",
            "Untag Transaction Export can_view", "Untag Transaction Export can_update", "Transaction Audit Trail can_view"]) {
            const checkbox = modal.get(`input[aria-label="${label}"]`);
            expect(checkbox.attributes("disabled")).toBeUndefined();
            await checkbox.setValue(true);
        }
        expect(modal.get('input[aria-label="Transaction Audit Trail can_update"]').attributes("disabled")).toBeDefined();
        await modal.get("form").trigger("submit");
        expect(mocks.form.roles["0204-BGBLT"].can_update).toBe(true);
        expect(mocks.form.roles["0405-UNTAG"].can_update).toBe(true);
        expect(mocks.form.roles["0406-AUDIT"].can_view).toBe(true);
        expect(mocks.post).toHaveBeenCalledWith("/assign.role.permissions", expect.any(Object));
    });

    it("refreshes permission values when a different user's permissions are loaded", async () => {
        const modal = mountRoleModal({ "0204-BGBLT": { can_update: true } });
        await modal.setProps({ userId: 8, permissions: { "0204-BGBLT": { can_update: "0" } } });
        expect(modal.get('input[aria-label="AR Beg Bal Transaction can_update"]').element.checked).toBe(false);
    });
});
