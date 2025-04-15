function generateIV() {
    return generateInitializationVector();
}

function generateInitializationVector() {
    return crypto.getRandomValues(new Uint8Array(12));
}

async function generateKeyPair() {
    try {
        return await crypto.subtle.generateKey(
            {
                name: "ECDH",
                namedCurve: "P-384",
            },
            true,
            ["deriveKey"]
        );
    } catch (e) {
        console.log(e);
        return null;
    }
}

function deriveSecretKey(ourPrivateKey, theirPublicKey) {
    return crypto.subtle.deriveKey(
        {
            name: "ECDH",
            public: theirPublicKey,
        },
        ourPrivateKey,
        {
            name: "AES-GCM",
            length: 256
        },
        false,
        ["encrypt", "decrypt"]
    );
}

async function encryptMessage(key, initializationVector, message) {
    try {
        const encoder = new TextEncoder();
        const encodedMessage = encoder.encode(message);

        return await crypto.subtle.encrypt(
            {name: "AES-GCM", iv: initializationVector},
            key,
            encodedMessage
        );
    } catch (e) {
        console.log(e);
        return `Encoding error`;
    }
}

async function decryptMessage(key, initializationVector, cipherText) {
    try {
        const decodedMessage = await crypto.subtle.decrypt(
            {name: "AES-GCM", iv: initializationVector},
            key,
            cipherText
        );

        const decoder = new TextDecoder();
        return decoder.decode(decodedMessage);
    } catch (e) {
        console.log(e);
        return "Decryption error";
    }
}