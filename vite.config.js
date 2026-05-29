// import { defineConfig } from "vite";
// import laravel from "laravel-vite-plugin";
// import vue from "@vitejs/plugin-vue";
// import tailwindcss from "@tailwindcss/vite";

// export default defineConfig(({ mode }) => {
//     // const isDev = mode === "development";

//     return {
//         plugins: [
//             vue(),
//             laravel({
//                 input: ["resources/css/app.css", "resources/js/app.js"],
//                 refresh: true,
//             }),
//             tailwindcss(),
//         ],
//         resolve: {
//             alias: {
//                 "@": "/resources/js",
//             },
//         },
//         // ...(isDev && {
//         //     server: {
//         //         host: "0.0.0.0",
//         //         port: 8000,
//         //         hmr: {
//         //             host: "172.16.43.232",
//         //             protocol: "http",
//         //             port: 8000,
//         //         },
//         //         cors: true,
//         //     },
//         // }),
//     };
// });

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5174,
        strictPort: true, 
        hmr: {
            host: '172.16.42.112'
        },
        cors: {
            origin: '*',
            methods: ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
            allowedHeaders: ['X-Requested-With', 'content-type', 'Authorization'],
        }
    }
});

