/**
 * BitBuffer - Native Bit-Addressable Buffer implementation.
 * Provides fine-grained, bit-level memory access, seeking, and manipulation
 * on top of binary buffers (Uint8Array / ArrayBuffer).
 */

export interface BitBufferOptions {
  /** If true, capacity argument is in bits. If false, capacity argument is in bytes. Default: false */
  isBits?: boolean;
  /** If true, buffer will automatically grow when writing beyond capacity. Default: true */
  autoExpand?: boolean;
}

export interface FieldSchema {
  name: string;
  bits: number;
}

export class BitBuffer {
  private buffer: Uint8Array;
  private _bitLength: number;
  private _cursor: number = 0;
  private autoExpand: boolean;

  /**
   * Constructs a new BitBuffer.
   * @param capacity Size in bytes (or bits if options.isBits is true).
   * @param options Configuration options.
   */
  constructor(capacity: number = 64, options: BitBufferOptions = {}) {
    const isBits = options.isBits ?? false;
    this.autoExpand = options.autoExpand ?? true;

    if (isBits) {
      this._bitLength = capacity;
      const bytes = Math.ceil(capacity / 8);
      this.buffer = new Uint8Array(bytes);
    } else {
      this.buffer = new Uint8Array(capacity);
      this._bitLength = capacity * 8;
    }
  }

  /**
   * Creates a BitBuffer wrapping or copying the given input data.
   */
  static from(
    data: Uint8Array | ArrayBuffer | number[] | string,
    encoding: 'utf8' | 'hex' | 'bit' = 'utf8'
  ): BitBuffer {
    if (data instanceof Uint8Array) {
      const bb = new BitBuffer(0, { autoExpand: true });
      bb.buffer = new Uint8Array(data);
      bb._bitLength = data.length * 8;
      return bb;
    }

    if (data instanceof ArrayBuffer) {
      const arr = new Uint8Array(data);
      const bb = new BitBuffer(0, { autoExpand: true });
      bb.buffer = new Uint8Array(arr);
      bb._bitLength = arr.length * 8;
      return bb;
    }

    if (Array.isArray(data)) {
      const arr = new Uint8Array(data);
      const bb = new BitBuffer(0, { autoExpand: true });
      bb.buffer = arr;
      bb._bitLength = arr.length * 8;
      return bb;
    }

    if (typeof data === 'string') {
      if (encoding === 'bit') {
        return BitBuffer.fromBitString(data);
      }
      if (encoding === 'hex') {
        return BitBuffer.fromHexString(data);
      }
      // UTF-8 string
      const encoder = new TextEncoder();
      const arr = encoder.encode(data);
      return BitBuffer.from(arr);
    }

    throw new Error('Unsupported data type for BitBuffer.from()');
  }

  /**
   * Creates a BitBuffer with a specific size in bits.
   */
  static allocate(bitLength: number): BitBuffer {
    return new BitBuffer(bitLength, { isBits: true });
  }

  /**
   * Creates a BitBuffer from a bit string (e.g. "11010010").
   */
  static fromBitString(bitString: string): BitBuffer {
    const sanitized = bitString.replace(/\s+/g, '');
    const bb = BitBuffer.allocate(sanitized.length);
    for (let i = 0; i < sanitized.length; i++) {
      const char = sanitized[i];
      if (char === '1') {
        bb.setBit(i, 1);
      } else if (char === '0') {
        bb.setBit(i, 0);
      } else {
        throw new Error(`Invalid bit character '${char}' at index ${i}`);
      }
    }
    return bb;
  }

  /**
   * Creates a BitBuffer from a hexadecimal string (e.g. "a5f0").
   */
  static fromHexString(hexString: string): BitBuffer {
    const sanitized = hexString.replace(/\s+/g, '');
    if (sanitized.length % 2 !== 0) {
      throw new Error('Hex string must have an even number of characters');
    }
    const byteCount = sanitized.length / 2;
    const bytes = new Uint8Array(byteCount);
    for (let i = 0; i < byteCount; i++) {
      const byteHex = sanitized.substr(i * 2, 2);
      const val = parseInt(byteHex, 16);
      if (isNaN(val)) {
        throw new Error(`Invalid hex sequence '${byteHex}' at index ${i * 2}`);
      }
      bytes[i] = val;
    }
    return BitBuffer.from(bytes);
  }

  // --- Getters & Cursor Management ---

  get bitLength(): number {
    return this._bitLength;
  }

  get byteLength(): number {
    return Math.ceil(this._bitLength / 8);
  }

  get bitPosition(): number {
    return this._cursor;
  }

  set bitPosition(pos: number) {
    this.seek(pos);
  }

  get remainingBits(): number {
    return Math.max(0, this._bitLength - this._cursor);
  }

  get isEOF(): boolean {
    return this._cursor >= this._bitLength;
  }

  /**
   * Moves the cursor to the specified bit position.
   */
  seek(bitIndex: number): this {
    if (bitIndex < 0) {
      throw new RangeError('Bit position cannot be negative');
    }
    if (bitIndex > this._bitLength) {
      if (this.autoExpand) {
        this.ensureBitCapacity(bitIndex);
      } else {
        throw new RangeError(`Bit index ${bitIndex} exceeds buffer bit length ${this._bitLength}`);
      }
    }
    this._cursor = bitIndex;
    return this;
  }

  /**
   * Advances cursor by bitCount.
   */
  skip(bitCount: number): this {
    return this.seek(this._cursor + bitCount);
  }

  /**
   * Resets the cursor to position 0.
   */
  rewind(): this {
    this._cursor = 0;
    return this;
  }

  // --- Capacity Handling ---

  private ensureBitCapacity(requiredBits: number): void {
    if (requiredBits <= this._bitLength) {
      return;
    }
    const targetByteCount = Math.ceil(requiredBits / 8);
    if (targetByteCount > this.buffer.length) {
      let newCapacity = Math.max(this.buffer.length * 2, targetByteCount, 8);
      const newBuffer = new Uint8Array(newCapacity);
      newBuffer.set(this.buffer);
      this.buffer = newBuffer;
    }
    this._bitLength = requiredBits;
  }

  // --- Single Bit Operations ---

  /**
   * Reads a single bit (0 or 1) at the given bit index without moving the cursor.
   */
  getBit(bitIndex: number): number {
    if (bitIndex < 0 || bitIndex >= this._bitLength) {
      throw new RangeError(`Bit index ${bitIndex} out of bounds (length: ${this._bitLength})`);
    }
    const byteIndex = bitIndex >> 3;
    const bitOffset = bitIndex & 7;
    const mask = 1 << (7 - bitOffset);
    return (this.buffer[byteIndex] & mask) !== 0 ? 1 : 0;
  }

  /**
   * Sets a single bit (0 or 1) at the given bit index without moving the cursor.
   */
  setBit(bitIndex: number, value: number | boolean): this {
    if (bitIndex < 0) {
      throw new RangeError('Bit index cannot be negative');
    }
    if (bitIndex >= this._bitLength) {
      if (this.autoExpand) {
        this.ensureBitCapacity(bitIndex + 1);
      } else {
        throw new RangeError(`Bit index ${bitIndex} out of bounds (length: ${this._bitLength})`);
      }
    }

    const bitVal = value ? 1 : 0;
    const byteIndex = bitIndex >> 3;
    const bitOffset = bitIndex & 7;
    const mask = 1 << (7 - bitOffset);

    if (bitVal === 1) {
      this.buffer[byteIndex] |= mask;
    } else {
      this.buffer[byteIndex] &= ~mask;
    }
    return this;
  }

  /**
   * Toggles (flips) a single bit at the given bit index without moving the cursor.
   */
  toggleBit(bitIndex: number): this {
    const current = this.getBit(bitIndex);
    return this.setBit(bitIndex, current === 0 ? 1 : 0);
  }

  /**
   * Clears (sets to 0) a single bit at the given bit index without moving the cursor.
   */
  clearBit(bitIndex: number): this {
    return this.setBit(bitIndex, 0);
  }

  /**
   * Reads a single bit at current cursor and advances cursor by 1 bit.
   */
  readBit(): number {
    const bit = this.getBit(this._cursor);
    this._cursor++;
    return bit;
  }

  /**
   * Writes a single bit at current cursor and advances cursor by 1 bit.
   */
  writeBit(value: number | boolean): this {
    this.setBit(this._cursor, value);
    this._cursor++;
    return this;
  }

  // --- Multi-Bit Operations (Unsigned & Signed Integers) ---

  /**
   * Reads up to 32 bits at bitIndex as an unsigned integer without moving cursor.
   */
  getBits(bitIndex: number, count: number): number {
    if (count < 0 || count > 32) {
      throw new RangeError('Count must be between 0 and 32 bits');
    }
    if (count === 0) return 0;

    let result = 0;
    for (let i = 0; i < count; i++) {
      const bit = this.getBit(bitIndex + i);
      result = (result << 1) | bit;
    }
    return result >>> 0;
  }

  /**
   * Reads up to 64 bits at bitIndex as BigInt without moving cursor.
   */
  getBitsBigInt(bitIndex: number, count: number): bigint {
    if (count < 0 || count > 64) {
      throw new RangeError('Count must be between 0 and 64 bits');
    }
    if (count === 0) return 0n;

    let result = 0n;
    for (let i = 0; i < count; i++) {
      const bit = BigInt(this.getBit(bitIndex + i));
      result = (result << 1n) | bit;
    }
    return result;
  }

  /**
   * Sets count bits starting at bitIndex from value without moving cursor.
   */
  setBits(bitIndex: number, value: number | bigint, count: number): this {
    if (count < 0 || count > 64) {
      throw new RangeError('Count must be between 0 and 64 bits');
    }
    if (count === 0) return this;

    if (typeof value === 'bigint') {
      for (let i = 0; i < count; i++) {
        const bitVal = Number((value >> BigInt(count - 1 - i)) & 1n);
        this.setBit(bitIndex + i, bitVal);
      }
    } else {
      for (let i = 0; i < count; i++) {
        const bitVal = (value >>> (count - 1 - i)) & 1;
        this.setBit(bitIndex + i, bitVal);
      }
    }
    return this;
  }

  /**
   * Reads count bits at cursor position as unsigned integer and advances cursor.
   */
  readBits(count: number): number {
    const val = this.getBits(this._cursor, count);
    this._cursor += count;
    return val;
  }

  /**
   * Reads count bits at cursor position as BigInt and advances cursor.
   */
  readBitsBigInt(count: number): bigint {
    const val = this.getBitsBigInt(this._cursor, count);
    this._cursor += count;
    return val;
  }

  /**
   * Writes count bits of value at cursor position and advances cursor.
   */
  writeBits(value: number | bigint, count: number): this {
    this.setBits(this._cursor, value, count);
    this._cursor += count;
    return this;
  }

  /**
   * Reads count bits as a signed integer (2's complement) without moving cursor.
   */
  getSignedBits(bitIndex: number, count: number): number {
    if (count <= 0 || count > 32) {
      throw new RangeError('Count must be between 1 and 32 bits for signed integer');
    }
    const raw = this.getBits(bitIndex, count);
    if (count === 32) {
      return raw | 0;
    }
    const signBit = (raw >>> (count - 1)) & 1;
    if (signBit === 1) {
      // Negative 2's complement
      const mask = (1 << count) - 1;
      return raw | ~mask;
    }
    return raw;
  }

  /**
   * Sets count bits as signed integer (2's complement) without moving cursor.
   */
  setSignedBits(bitIndex: number, value: number, count: number): this {
    if (count <= 0 || count > 32) {
      throw new RangeError('Count must be between 1 and 32 bits for signed integer');
    }
    const mask = count === 32 ? -1 : (1 << count) - 1;
    const unsignedVal = value & mask;
    return this.setBits(bitIndex, unsignedVal, count);
  }

  /**
   * Reads count signed bits at cursor position and advances cursor.
   */
  readSignedBits(count: number): number {
    const val = this.getSignedBits(this._cursor, count);
    this._cursor += count;
    return val;
  }

  /**
   * Writes count signed bits at cursor position and advances cursor.
   */
  writeSignedBits(value: number, count: number): this {
    this.setSignedBits(this._cursor, value, count);
    this._cursor += count;
    return this;
  }

  // --- High Level Typed Integers & Bytes ---

  readUInt8(): number {
    return this.readBits(8);
  }

  writeUInt8(value: number): this {
    return this.writeBits(value & 0xff, 8);
  }

  readUInt16(bigEndian: boolean = true): number {
    if (bigEndian) {
      return this.readBits(16);
    } else {
      const b0 = this.readBits(8);
      const b1 = this.readBits(8);
      return (b0 | (b1 << 8)) >>> 0;
    }
  }

  writeUInt16(value: number, bigEndian: boolean = true): this {
    if (bigEndian) {
      return this.writeBits(value & 0xffff, 16);
    } else {
      this.writeBits(value & 0xff, 8);
      this.writeBits((value >>> 8) & 0xff, 8);
      return this;
    }
  }

  readUInt32(bigEndian: boolean = true): number {
    if (bigEndian) {
      return this.readBits(32);
    } else {
      const b0 = this.readBits(8);
      const b1 = this.readBits(8);
      const b2 = this.readBits(8);
      const b3 = this.readBits(8);
      return (b0 | (b1 << 8) | (b2 << 16) | (b3 << 24)) >>> 0;
    }
  }

  writeUInt32(value: number, bigEndian: boolean = true): this {
    if (bigEndian) {
      return this.writeBits(value >>> 0, 32);
    } else {
      this.writeBits(value & 0xff, 8);
      this.writeBits((value >>> 8) & 0xff, 8);
      this.writeBits((value >>> 16) & 0xff, 8);
      this.writeBits((value >>> 24) & 0xff, 8);
      return this;
    }
  }

  readInt8(): number {
    return this.readSignedBits(8);
  }

  writeInt8(value: number): this {
    return this.writeSignedBits(value, 8);
  }

  readInt16(bigEndian: boolean = true): number {
    if (bigEndian) {
      return this.readSignedBits(16);
    } else {
      const val = this.readUInt16(false);
      return val >= 0x8000 ? val - 0x10000 : val;
    }
  }

  writeInt16(value: number, bigEndian: boolean = true): this {
    return this.writeUInt16(value & 0xffff, bigEndian);
  }

  readInt32(bigEndian: boolean = true): number {
    if (bigEndian) {
      return this.readSignedBits(32);
    } else {
      const val = this.readUInt32(false);
      return val | 0;
    }
  }

  writeInt32(value: number, bigEndian: boolean = true): this {
    return this.writeUInt32(value >>> 0, bigEndian);
  }

  readBytes(byteCount: number): Uint8Array {
    const bytes = new Uint8Array(byteCount);
    for (let i = 0; i < byteCount; i++) {
      bytes[i] = this.readUInt8();
    }
    return bytes;
  }

  writeBytes(bytes: Uint8Array | number[]): this {
    for (let i = 0; i < bytes.length; i++) {
      this.writeUInt8(bytes[i]);
    }
    return this;
  }

  readString(byteLength?: number, encoding: 'utf8' | 'ascii' = 'utf8'): string {
    const len = byteLength ?? (this.remainingBits >> 3);
    const bytes = this.readBytes(len);
    if (encoding === 'ascii') {
      let str = '';
      for (let i = 0; i < bytes.length; i++) {
        str += String.fromCharCode(bytes[i]);
      }
      return str;
    }
    const decoder = new TextDecoder();
    return decoder.decode(bytes);
  }

  writeString(str: string): this {
    const encoder = new TextEncoder();
    const bytes = encoder.encode(str);
    return this.writeBytes(bytes);
  }

  // --- Schema Bitfield Packing & Unpacking ---

  /**
   * Packs structured field values into bitstream according to schema definition.
   */
  pack(
    fields: Record<string, number | bigint>,
    schema: FieldSchema[]
  ): this {
    for (const item of schema) {
      const val = fields[item.name];
      if (val === undefined) {
        throw new Error(`Missing value for field '${item.name}' in schema packing`);
      }
      this.writeBits(val, item.bits);
    }
    return this;
  }

  /**
   * Unpacks bitfields into an object record according to schema definition.
   */
  unpack<T extends Record<string, number | bigint>>(schema: FieldSchema[]): T {
    const result: Record<string, number | bigint> = {};
    for (const item of schema) {
      if (item.bits > 32) {
        result[item.name] = this.readBitsBigInt(item.bits);
      } else {
        result[item.name] = this.readBits(item.bits);
      }
    }
    return result as T;
  }

  // --- Bitwise Buffer Logic Operations ---

  and(other: BitBuffer): BitBuffer {
    const minLength = Math.min(this.bitLength, other.bitLength);
    const result = BitBuffer.allocate(minLength);
    for (let i = 0; i < minLength; i++) {
      const b = this.getBit(i) & other.getBit(i);
      result.setBit(i, b);
    }
    return result;
  }

  or(other: BitBuffer): BitBuffer {
    const maxLength = Math.max(this.bitLength, other.bitLength);
    const result = BitBuffer.allocate(maxLength);
    for (let i = 0; i < maxLength; i++) {
      const b1 = i < this.bitLength ? this.getBit(i) : 0;
      const b2 = i < other.bitLength ? other.getBit(i) : 0;
      result.setBit(i, b1 | b2);
    }
    return result;
  }

  xor(other: BitBuffer): BitBuffer {
    const maxLength = Math.max(this.bitLength, other.bitLength);
    const result = BitBuffer.allocate(maxLength);
    for (let i = 0; i < maxLength; i++) {
      const b1 = i < this.bitLength ? this.getBit(i) : 0;
      const b2 = i < other.bitLength ? other.getBit(i) : 0;
      result.setBit(i, b1 ^ b2);
    }
    return result;
  }

  not(): BitBuffer {
    const result = BitBuffer.allocate(this.bitLength);
    for (let i = 0; i < this.bitLength; i++) {
      result.setBit(i, this.getBit(i) === 0 ? 1 : 0);
    }
    return result;
  }

  shiftLeft(shiftCount: number): BitBuffer {
    const result = BitBuffer.allocate(this.bitLength);
    if (shiftCount >= this.bitLength) {
      return result;
    }
    for (let i = 0; i < this.bitLength - shiftCount; i++) {
      result.setBit(i, this.getBit(i + shiftCount));
    }
    return result;
  }

  shiftRight(shiftCount: number): BitBuffer {
    const result = BitBuffer.allocate(this.bitLength);
    if (shiftCount >= this.bitLength) {
      return result;
    }
    for (let i = 0; i < this.bitLength - shiftCount; i++) {
      result.setBit(i + shiftCount, this.getBit(i));
    }
    return result;
  }

  // --- Sub-buffer Slicing & Formatting ---

  slice(startBit: number = 0, endBit?: number): BitBuffer {
    const end = endBit ?? this._bitLength;
    if (startBit < 0 || startBit > this._bitLength) {
      throw new RangeError(`startBit ${startBit} out of bounds`);
    }
    if (end < startBit || end > this._bitLength) {
      throw new RangeError(`endBit ${end} out of bounds`);
    }

    const bitLen = end - startBit;
    const sliced = BitBuffer.allocate(bitLen);
    for (let i = 0; i < bitLen; i++) {
      sliced.setBit(i, this.getBit(startBit + i));
    }
    return sliced;
  }

  clone(): BitBuffer {
    const copy = BitBuffer.allocate(this._bitLength);
    copy.buffer.set(this.buffer.subarray(0, this.byteLength));
    copy._cursor = this._cursor;
    return copy;
  }

  fill(value: number | boolean, startBit: number = 0, endBit?: number): this {
    const end = endBit ?? this._bitLength;
    for (let i = startBit; i < end; i++) {
      this.setBit(i, value);
    }
    return this;
  }

  toBitString(): string {
    let str = '';
    for (let i = 0; i < this._bitLength; i++) {
      str += this.getBit(i).toString();
    }
    return str;
  }

  toHexString(): string {
    let hex = '';
    const bytes = this.toBuffer();
    for (let i = 0; i < bytes.length; i++) {
      hex += bytes[i].toString(16).padStart(2, '0');
    }
    return hex;
  }

  toBuffer(): Uint8Array {
    return this.buffer.subarray(0, this.byteLength);
  }
}
