import { vi } from "vitest";

if (typeof window !== "undefined" && !window.IntersectionObserver) {
    window.IntersectionObserver = class IntersectionObserver {
        constructor() {}
        observe() {}
        unobserve() {}
        disconnect() {}
    };
}

vi.stubGlobal("scrollTo", () => {});
