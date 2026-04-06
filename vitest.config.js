import { defineConfig } from "vitest/config";
import vue from "@vitejs/plugin-vue";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            "@": path.resolve(__dirname, "resources/js"),
        },
    },
    test: {
        environment: "happy-dom",
        globals: true,
        setupFiles: [path.resolve(__dirname, "resources/js/tests/setup.js")],
        include: ["resources/js/**/*.{test,spec}.{js,ts}"],
    },
});
