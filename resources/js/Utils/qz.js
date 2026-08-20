import qz from "qz-tray";
import axios from "axios";

// Add this — without it, QZ Tray has no way to verify requests come from your app
qz.security.setCertificatePromise((resolve, reject) => {
    axios
        .get("/qz/cert")
        .then((res) => resolve(res.data))
        .catch(reject);
});

qz.security.setSignaturePromise((toSign) => {
    return (resolve, reject) => {
        axios
            .post("/qz/sign", { request: toSign })
            .then((res) => resolve(res.data))
            .catch(reject);
    };
});

let isConnected = false;

export async function ensureQzConnected() {
    if (isConnected && qz.websocket.isActive()) return;

    if (!qz.websocket.isActive()) {
        await qz.websocket.connect();
    }
    isConnected = true;
}

export { qz };
