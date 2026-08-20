import { describe, it, expect } from '@jest/globals';
import { BitBuffer } from '../src/BitBuffer';
// @ts-ignore
import { compressTextMessage, decompressTextMessage } from '../public/assets/js/ivc.bitbuffer.js';

describe('BitBuffer - Native Bit-Addressable Buffer (TypeScript)', () => {
  it('should initialize buffer with default and custom capacities', () => {
    const bbDefault = new BitBuffer();
    expect(bbDefault.byteLength).toBe(64);
    expect(bbDefault.bitLength).toBe(512);

    const bbBits = BitBuffer.allocate(13);
    expect(bbBits.bitLength).toBe(13);
    expect(bbBits.byteLength).toBe(2);
  });

  it('should parse bit strings and hex strings correctly', () => {
    const bbBitStr = BitBuffer.fromBitString('1101 0010');
    expect(bbBitStr.bitLength).toBe(8);
    expect(bbBitStr.toBitString()).toBe('11010010');

    const bbHex = BitBuffer.fromHexString('a5f0');
    expect(bbHex.bitLength).toBe(16);
    expect(bbHex.toHexString()).toBe('a5f0');
    expect(bbHex.toBitString()).toBe('1010010111110000');
  });

  it('should handle single bit operations (get, set, toggle, clear, read, write)', () => {
    const bb = BitBuffer.allocate(8);
    expect(bb.toBitString()).toBe('00000000');

    bb.setBit(0, 1);
    bb.setBit(3, true);
    bb.setBit(7, 1);
    expect(bb.toBitString()).toBe('10010001');

    bb.toggleBit(0);
    bb.clearBit(7);
    expect(bb.toBitString()).toBe('00010000');

    bb.rewind();
    expect(bb.readBit()).toBe(0);
    expect(bb.readBit()).toBe(0);
    expect(bb.readBit()).toBe(0);
    expect(bb.readBit()).toBe(1);

    bb.rewind();
    bb.writeBit(1).writeBit(0).writeBit(1);
    expect(bb.toBitString()).toBe('10110000');
  });

  it('should handle cursor movement and positioning', () => {
    const bb = BitBuffer.allocate(16);
    expect(bb.bitPosition).toBe(0);
    expect(bb.remainingBits).toBe(16);

    bb.seek(5);
    expect(bb.bitPosition).toBe(5);
    expect(bb.remainingBits).toBe(11);

    bb.skip(3);
    expect(bb.bitPosition).toBe(8);

    bb.rewind();
    expect(bb.bitPosition).toBe(0);
  });

  it('should read and write multi-bit unsigned and signed integers across bit offsets', () => {
    const bb = BitBuffer.allocate(32);
    // Write 5-bit integer (value 21 = 10101) at bit offset 0
    bb.writeBits(21, 5);
    // Write 11-bit integer (value 1234 = 10011010010) at bit offset 5
    bb.writeBits(1234, 11);

    expect(bb.toBitString().substring(0, 16)).toBe('1010110011010010');

    bb.rewind();
    expect(bb.readBits(5)).toBe(21);
    expect(bb.readBits(11)).toBe(1234);

    // Test signed integer 5-bit negative value (-9 = 10111 in 5-bit 2's complement)
    bb.rewind();
    bb.writeSignedBits(-9, 5);
    bb.rewind();
    expect(bb.readSignedBits(5)).toBe(-9);

    // Test 32-bit signed integer negative value
    bb.rewind();
    bb.writeInt32(-123456789);
    bb.rewind();
    expect(bb.readInt32()).toBe(-123456789);
  });

  it('should support BigInt for bit lengths > 32', () => {
    const bb = BitBuffer.allocate(48);
    const bigVal = BigInt('0x123456789abc');
    bb.writeBits(bigVal, 48);

    bb.rewind();
    expect(bb.readBitsBigInt(48)).toBe(bigVal);
  });

  it('should read and write typed integers and byte sequences', () => {
    const bb = BitBuffer.allocate(64);
    bb.writeUInt8(0xab);
    bb.writeUInt16(0x1234, true); // Big endian
    bb.writeUInt16(0x5678, false); // Little endian

    bb.rewind();
    expect(bb.readUInt8()).toBe(0xab);
    expect(bb.readUInt16(true)).toBe(0x1234);
    expect(bb.readUInt16(false)).toBe(0x5678);

    // Test string writing/reading
    const strBuffer = BitBuffer.allocate(64);
    strBuffer.writeString('IVC');
    strBuffer.rewind();
    expect(strBuffer.readString(3)).toBe('IVC');
  });

  it('should pack and unpack bitfields using schemas', () => {
    const schema = [
      { name: 'version', bits: 4 },
      { name: 'flags', bits: 4 },
      { name: 'streamId', bits: 16 },
      { name: 'seqNumber', bits: 8 }
    ];

    const data = {
      version: 2,
      flags: 15,
      streamId: 4321,
      seqNumber: 100
    };

    const bb = BitBuffer.allocate(32);
    bb.pack(data, schema);

    bb.rewind();
    const unpacked = bb.unpack<{ version: number; flags: number; streamId: number; seqNumber: number }>(schema);
    expect(unpacked).toEqual(data);
  });

  it('should perform bitwise operations AND, OR, XOR, NOT, Shifts', () => {
    const bb1 = BitBuffer.fromBitString('1100 1010');
    const bb2 = BitBuffer.fromBitString('1010 1100');

    expect(bb1.and(bb2).toBitString()).toBe('10001000');
    expect(bb1.or(bb2).toBitString()).toBe('11101110');
    expect(bb1.xor(bb2).toBitString()).toBe('01100110');
    expect(bb1.not().toBitString()).toBe('00110101');

    expect(bb1.shiftLeft(2).toBitString()).toBe('00101000');
    expect(bb1.shiftRight(2).toBitString()).toBe('00110010');
  });

  it('should slice, clone, fill, and handle auto-expansion', () => {
    const bb = BitBuffer.fromBitString('1111 0000 1010 0101');
    const sub = bb.slice(4, 12);
    expect(sub.toBitString()).toBe('00001010');

    const copy = bb.clone();
    expect(copy.toBitString()).toBe(bb.toBitString());

    const empty = BitBuffer.allocate(8);
    empty.fill(1);
    expect(empty.toBitString()).toBe('11111111');

    // Test auto-expansion
    const small = BitBuffer.allocate(8);
    small.seek(20);
    small.setBit(20, 1);
    expect(small.bitLength).toBe(21);
    expect(small.getBit(20)).toBe(1);
  });

  it('should throw RangeError on invalid bounds when autoExpand is false', () => {
    const strict = new BitBuffer(8, { isBits: true, autoExpand: false });
    expect(() => strict.getBit(10)).toThrow(RangeError);
    expect(() => strict.setBit(10, 1)).toThrow(RangeError);
    expect(() => strict.seek(-1)).toThrow(RangeError);
  });

  it('should bit-compress and decompress WebRTC text messages', () => {
    const asciiMsg = 'Hello WebRTC DataChannel! Encryption and BitBuffer compression active.';
    const compressedAscii = compressTextMessage(asciiMsg);
    expect(typeof compressedAscii).toBe('string');
    expect(compressedAscii).toContain('"__bc":true');
    expect(compressedAscii).toContain('"mode":"ascii7"');

    const decompressedAscii = decompressTextMessage(compressedAscii);
    expect(decompressedAscii).toBe(asciiMsg);

    const utf8Msg = 'Hello 🚀 WebRTC 🔥 Encryption & BitBuffer!';
    const compressedUtf8 = compressTextMessage(utf8Msg);
    expect(compressedUtf8).toContain('"mode":"utf8"');

    const decompressedUtf8 = decompressTextMessage(compressedUtf8);
    expect(decompressedUtf8).toBe(utf8Msg);

    // Fallback for uncompressed message
    const plainMsg = 'Plain uncompressed message';
    expect(decompressTextMessage(plainMsg)).toBe(plainMsg);
  });
});
