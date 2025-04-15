function getKeyMaterial(password){
    const encoder = new TextEncoder();
    return crypto.subtle.importKey(
        "raw",
        encoder.encode(password),
        {name: "PBKDF2"},
        false,
        ["deriveBits", "deriveKey"]
    )
}

function getKey(keyMaterial, salt){
   return crypto.subtle.deriveKey(
       {
           name: "PBKDF2",
           salt,
           iterations: 100_000,
           hash: "SHA-256",
       },
       keyMaterial,
       {name: "AES-GCM", length: 256},
       true,
       ["wrapKey", "unwrapKey"]
   );
}

async function wrapKey(keyToWrap, password){
    const keyMaterial = await getKeyMaterial(password);
    let salt = crypto.getRandomValues(new Uint8Array(16));
    const wrappingKey = await getKey(keyMaterial, salt);
    let iv = crypto.getRandomValues(new Uint8Array(12));

    const wrappedKey = await crypto.subtle.wrapKey("pkcs8", keyToWrap, wrappingKey, {
        name: "AES-GCM",
        iv,
    });

    return {
        wrappedKey: arrayBufferToBase64(wrappedKey),
        salt: arrayBufferToBase64(salt),
        iv: arrayBufferToBase64(iv),
    }
}

async function unwrapKey(encryptedData, password){
    const keyMaterial = await getKeyMaterial(password);
    const key = await getKey(keyMaterial, base64ToArrayBuffer(encryptedData.salt));
    const iv = base64ToArrayBuffer(encryptedData.iv);

    return await crypto.subtle.unwrapKey(
        "pkcs8",
        base64ToArrayBuffer(encryptedData.wrappedKey),
        key,
        {
            name: "AES-GCM",
            iv: iv,
        },
        {
            name: "ECDH",
            namedCurve: "P-256",
        },
        true,
        ["deriveBits", "deriveKey"]
    );
}

async function exportPrivateKey(privateKey){
    const exported = await crypto.subtle.exportKey("pkcs8", privateKey);
    const exportedAsBase64 = arrayBufferToBase64(exported);
    return `-----BEGIN PRIVATE KEY-----\n${exportedAsBase64}\n-----END PRIVATE KEY-----`;
}

function importPrivateKey(pem){
    const pemHeader = "-----BEGIN PRIVATE KEY-----";
    const pemFooter = "-----END PRIVATE KEY-----";
    const pemContents = pem.substring(
        pemHeader.length,
        pem.length - pemFooter.length - 1,
    );
    const buffer = base64ToArrayBuffer(pemContents);

    return crypto.subtle.importKey(
        "pkcs8",
        buffer,
        {
            name: "ECDH",
            namedCurve: "P-256",
        },
        true,
        ["deriveBits", "deriveKey"],
    );
}

async function exportPublicKey(publicKey){
    const exported = await crypto.subtle.exportKey("spki", publicKey);
    const exportedAsBase64 = arrayBufferToBase64(exported);
    return `-----BEGIN PUBLIC KEY-----\n${exportedAsBase64}\n-----END PUBLIC KEY-----`;
}

function importPublicKey(pem) {
    const pemHeader = "-----BEGIN PUBLIC KEY-----";
    const pemFooter = "-----END PUBLIC KEY-----";
    const pemContents = pem.substring(
        pemHeader.length,
        pem.length - pemFooter.length - 1,
    );
    const buffer = base64ToArrayBuffer(pemContents);

    return crypto.subtle.importKey(
        "spki",
        buffer,
        {
            name: "ECDH",
            namedCurve: "P-256",
        },
        true,
        [],
    );
}

function arrayBufferToBase64(buffer) {
    let binary = '';
    const bytes = new Uint8Array(buffer);
    for (let i = 0; i < bytes.length; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return window.btoa(binary);
}

function base64ToArrayBuffer(base64) {
    const binary = window.atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
}