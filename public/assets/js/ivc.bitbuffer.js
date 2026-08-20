'use strict';

/**
 * WebRTC BitBuffer Text Compression Utility
 * Provides bit-level packing and compression for WebRTC DataChannel text messages.
 */

const BITBUFFER_MAGIC = 0xBC;
const MODE_ASCII7 = 0x01;
const MODE_UTF8 = 0x02;

/**
 * Packs a text string into a bit-compressed payload.
 * @param {string} text Plain text message
 * @returns {string} JSON-serialized compressed payload object
 */
function compressTextMessage(text) {
    if (!text || typeof text !== 'string') return text;

    let isAscii = true;
    for (let i = 0; i < text.length; i++) {
        if (text.charCodeAt(i) > 127) {
            isAscii = false;
            break;
        }
    }

    if (isAscii && text.length <= 65535) {
        // Mode 1: 7-bit ASCII bit packing
        const bitCount = 32 + (text.length * 7);
        const byteCount = Math.ceil(bitCount / 8);
        const buffer = new Uint8Array(byteCount);

        buffer[0] = BITBUFFER_MAGIC;
        buffer[1] = MODE_ASCII7;
        buffer[2] = (text.length >> 8) & 0xFF;
        buffer[3] = text.length & 0xFF;

        let bitPos = 32;
        for (let i = 0; i < text.length; i++) {
            const charCode = text.charCodeAt(i) & 0x7F;
            for (let b = 6; b >= 0; b--) {
                const bit = (charCode >> b) & 1;
                const byteIdx = bitPos >> 3;
                const bitOffset = bitPos & 7;
                if (bit === 1) {
                    buffer[byteIdx] |= (1 << (7 - bitOffset));
                }
                bitPos++;
            }
        }

        let binaryStr = '';
        for (let i = 0; i < buffer.length; i++) {
            binaryStr += String.fromCharCode(buffer[i]);
        }
        const base64Data = btoa(binaryStr);

        return JSON.stringify({
            __bc: true,
            mode: 'ascii7',
            origLen: text.length,
            compBytes: buffer.length,
            data: base64Data
        });
    } else {
        // Mode 2: UTF-8 bit-stream byte packing
        const encoder = new TextEncoder();
        const utf8Bytes = encoder.encode(text);
        if (utf8Bytes.length > 65535) return text;

        const buffer = new Uint8Array(4 + utf8Bytes.length);
        buffer[0] = BITBUFFER_MAGIC;
        buffer[1] = MODE_UTF8;
        buffer[2] = (utf8Bytes.length >> 8) & 0xFF;
        buffer[3] = utf8Bytes.length & 0xFF;
        buffer.set(utf8Bytes, 4);

        let binaryStr = '';
        for (let i = 0; i < buffer.length; i++) {
            binaryStr += String.fromCharCode(buffer[i]);
        }
        const base64Data = btoa(binaryStr);

        return JSON.stringify({
            __bc: true,
            mode: 'utf8',
            origLen: text.length,
            compBytes: buffer.length,
            data: base64Data
        });
    }
}

/**
 * Decompresses a WebRTC text message payload if compressed, or returns as-is.
 * @param {string|object} payload
 * @returns {string} Decompressed text string
 */
function decompressTextMessage(payload) {
    if (!payload) return '';

    let obj = null;
    if (typeof payload === 'object' && payload !== null) {
        obj = payload;
    } else if (typeof payload === 'string' && payload.trim().startsWith('{')) {
        try {
            obj = JSON.parse(payload);
        } catch (e) {
            return payload;
        }
    }

    if (!obj || !obj.__bc || !obj.data) {
        return typeof payload === 'string' ? payload : JSON.stringify(payload);
    }

    try {
        const binaryStr = atob(obj.data);
        const buffer = new Uint8Array(binaryStr.length);
        for (let i = 0; i < binaryStr.length; i++) {
            buffer[i] = binaryStr.charCodeAt(i);
        }

        if (buffer.length < 4 || buffer[0] !== BITBUFFER_MAGIC) {
            return payload;
        }

        const mode = buffer[1];
        const len = (buffer[2] << 8) | buffer[3];

        if (mode === MODE_ASCII7) {
            let result = '';
            let bitPos = 32;
            for (let i = 0; i < len; i++) {
                let charCode = 0;
                for (let b = 0; b < 7; b++) {
                    const byteIdx = bitPos >> 3;
                    const bitOffset = bitPos & 7;
                    const bit = (buffer[byteIdx] & (1 << (7 - bitOffset))) !== 0 ? 1 : 0;
                    charCode = (charCode << 1) | bit;
                    bitPos++;
                }
                result += String.fromCharCode(charCode);
            }
            return result;
        } else if (mode === MODE_UTF8) {
            const utf8Bytes = buffer.subarray(4, 4 + len);
            const decoder = new TextDecoder();
            return decoder.decode(utf8Bytes);
        }
    } catch (err) {
        console.error('Failed to decompress BitBuffer WebRTC text payload:', err);
    }

    return typeof payload === 'string' ? payload : JSON.stringify(payload);
}

// Export for Node / CommonJS / Jest environments
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        compressTextMessage,
        decompressTextMessage,
        BITBUFFER_MAGIC,
        MODE_ASCII7,
        MODE_UTF8
    };
}
