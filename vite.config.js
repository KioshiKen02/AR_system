import { defineConfig, loadEnv } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), "");
    const appUrl = new URL(env.APP_URL ?? "http://localhost");

    return {
        plugins: [
            laravel({
                input: ["resources/css/app.css", "resources/js/app.js"],
                refresh: true,
            }),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
            tailwindcss(),
        ],
        server: {
            host: "0.0.0.0",
            port: 5174,
            strictPort: true,
            hmr: {
                host: appUrl.hostname,
            },
            cors: {
                origin: "*",
                methods: ["GET", "POST", "PUT", "DELETE", "PATCH", "OPTIONS"],
                allowedHeaders: [
                    "X-Requested-With",
                    "content-type",
                    "Authorization",
                ],
            },
        },
    };
});
