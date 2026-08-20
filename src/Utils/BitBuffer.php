<?php

declare(strict_types=1);

namespace Fortress\Utils;

use InvalidArgumentException;
use OutOfBoundsException;
use RangeException;

/**
 * Native Bit-Addressable Buffer implementation in PHP.
 * Supports arbitrary bit-level reading, writing, seeking, integer bitfields,
 * schema packing/unpacking, and bitwise buffer logic.
 */
class BitBuffer
{
    private string $buffer;
    private int $bitLength;
    private int $cursor = 0;
    private bool $autoExpand;

    public function __construct(int $capacity = 64, bool $isBits = false, bool $autoExpand = true)
    {
        $this->autoExpand = $autoExpand;
        if ($isBits) {
            $this->bitLength = $capacity;
            $byteCount = (int)ceil($capacity / 8.0);
            $this->buffer = str_repeat("\x00", max(0, $byteCount));
        } else {
            $this->buffer = str_repeat("\x00", max(0, $capacity));
            $this->bitLength = $capacity * 8;
        }
    }

    public static function allocate(int $bitLength): self
    {
        return new self($bitLength, true);
    }

    public static function from(string $data, string $encoding = 'binary'): self
    {
        if ($encoding === 'bit') {
            return self::fromBitString($data);
        }
        if ($encoding === 'hex') {
            return self::fromHexString($data);
        }

        $bb = new self(0, true, true);
        $bb->buffer = $data;
        $bb->bitLength = strlen($data) * 8;
        return $bb;
    }

    public static function fromBitString(string $bitString): self
    {
        $sanitized = preg_replace('/\s+/', '', $bitString);
        $len = strlen($sanitized);
        $bb = self::allocate($len);
        for ($i = 0; $i < $len; $i++) {
            $char = $sanitized[$i];
            if ($char === '1') {
                $bb->setBit($i, 1);
            } elseif ($char === '0') {
                $bb->setBit($i, 0);
            } else {
                throw new InvalidArgumentException("Invalid bit character '{$char}' at index {$i}");
            }
        }
        return $bb;
    }

    public static function fromHexString(string $hexString): self
    {
        $sanitized = preg_replace('/\s+/', '', $hexString);
        if (strlen($sanitized) % 2 !== 0) {
            throw new InvalidArgumentException("Hex string length must be even");
        }
        $binary = hex2bin($sanitized);
        if ($binary === false) {
            throw new InvalidArgumentException("Invalid hex string provided");
        }
        return self::from($binary);
    }

    // --- Getters & Cursor Positioning ---

    public function getBitLength(): int
    {
        return $this->bitLength;
    }

    public function getByteLength(): int
    {
        return (int)ceil($this->bitLength / 8.0);
    }

    public function getBitPosition(): int
    {
        return $this->cursor;
    }

    public function getRemainingBits(): int
    {
        return max(0, $this->bitLength - $this->cursor);
    }

    public function isEOF(): bool
    {
        return $this->cursor >= $this->bitLength;
    }

    public function seek(int $bitIndex): self
    {
        if ($bitIndex < 0) {
            throw new RangeException("Bit position cannot be negative");
        }
        if ($bitIndex > $this->bitLength) {
            if ($this->autoExpand) {
                $this->ensureBitCapacity($bitIndex);
            } else {
                throw new OutOfBoundsException("Bit index {$bitIndex} exceeds bit length {$this->bitLength}");
            }
        }
        $this->cursor = $bitIndex;
        return $this;
    }

    public function skip(int $bitCount): self
    {
        return $this->seek($this->cursor + $bitCount);
    }

    public function rewind(): self
    {
        $this->cursor = 0;
        return $this;
    }

    private function ensureBitCapacity(int $requiredBits): void
    {
        if ($requiredBits <= $this->bitLength) {
            return;
        }
        $targetBytes = (int)ceil($requiredBits / 8.0);
        $currentBytes = strlen($this->buffer);
        if ($targetBytes > $currentBytes) {
            $newCapacityBytes = max($currentBytes * 2, $targetBytes, 8);
            $this->buffer = str_pad($this->buffer, $newCapacityBytes, "\x00");
        }
        $this->bitLength = $requiredBits;
    }

    // --- Single Bit Operations ---

    public function getBit(int $bitIndex): int
    {
        if ($bitIndex < 0 || $bitIndex >= $this->bitLength) {
            throw new OutOfBoundsException("Bit index {$bitIndex} out of bounds (length: {$this->bitLength})");
        }
        $byteIndex = $bitIndex >> 3;
        $bitOffset = $bitIndex & 7;
        $byteVal = ord($this->buffer[$byteIndex]);
        $mask = 1 << (7 - $bitOffset);
        return ($byteVal & $mask) !== 0 ? 1 : 0;
    }

    public function setBit(int $bitIndex, int|bool $value): self
    {
        if ($bitIndex < 0) {
            throw new RangeException("Bit index cannot be negative");
        }
        if ($bitIndex >= $this->bitLength) {
            if ($this->autoExpand) {
                $this->ensureBitCapacity($bitIndex + 1);
            } else {
                throw new OutOfBoundsException("Bit index {$bitIndex} out of bounds (length: {$this->bitLength})");
            }
        }

        $bitVal = $value ? 1 : 0;
        $byteIndex = $bitIndex >> 3;
        $bitOffset = $bitIndex & 7;
        $mask = 1 << (7 - $bitOffset);
        $byteVal = ord($this->buffer[$byteIndex]);

        if ($bitVal === 1) {
            $byteVal |= $mask;
        } else {
            $byteVal &= ~$mask;
        }

        $this->buffer[$byteIndex] = chr($byteVal);
        return $this;
    }

    public function toggleBit(int $bitIndex): self
    {
        $curr = $this->getBit($bitIndex);
        return $this->setBit($bitIndex, $curr === 0 ? 1 : 0);
    }

    public function clearBit(int $bitIndex): self
    {
        return $this->setBit($bitIndex, 0);
    }

    public function readBit(): int
    {
        $bit = $this->getBit($this->cursor);
        $this->cursor++;
        return $bit;
    }

    public function writeBit(int|bool $value): self
    {
        $this->setBit($this->cursor, $value);
        $this->cursor++;
        return $this;
    }

    // --- Multi-Bit Operations ---

    public function getBits(int $bitIndex, int $count): int
    {
        if ($count < 0 || $count > 63) {
            throw new RangeException("Count must be between 0 and 63 bits");
        }
        if ($count === 0) {
            return 0;
        }

        $result = 0;
        for ($i = 0; $i < $count; $i++) {
            $bit = $this->getBit($bitIndex + $i);
            $result = ($result << 1) | $bit;
        }
        return $result;
    }

    public function setBits(int $bitIndex, int $value, int $count): self
    {
        if ($count < 0 || $count > 63) {
            throw new RangeException("Count must be between 0 and 63 bits");
        }
        if ($count === 0) {
            return $this;
        }

        for ($i = 0; $i < $count; $i++) {
            $bitVal = ($value >> ($count - 1 - $i)) & 1;
            $this->setBit($bitIndex + $i, $bitVal);
        }
        return $this;
    }

    public function readBits(int $count): int
    {
        $val = $this->getBits($this->cursor, $count);
        $this->cursor += $count;
        return $val;
    }

    public function writeBits(int $value, int $count): self
    {
        $this->setBits($this->cursor, $value, $count);
        $this->cursor += $count;
        return $this;
    }

    public function getSignedBits(int $bitIndex, int $count): int
    {
        if ($count <= 0 || $count > 63) {
            throw new RangeException("Count must be between 1 and 63 bits for signed integer");
        }
        $raw = $this->getBits($bitIndex, $count);
        $signBit = ($raw >> ($count - 1)) & 1;
        if ($signBit === 1) {
            if ($count >= 63) {
                return $raw;
            }
            $mask = (1 << $count) - 1;
            return $raw | ~$mask;
        }
        return $raw;
    }

    public function setSignedBits(int $bitIndex, int $value, int $count): self
    {
        if ($count <= 0 || $count > 63) {
            throw new RangeException("Count must be between 1 and 63 bits for signed integer");
        }
        if ($count >= 63) {
            $unsignedVal = $value;
        } else {
            $mask = (1 << $count) - 1;
            $unsignedVal = $value & $mask;
        }
        return $this->setBits($bitIndex, $unsignedVal, $count);
    }

    public function readSignedBits(int $count): int
    {
        $val = $this->getSignedBits($this->cursor, $count);
        $this->cursor += $count;
        return $val;
    }

    public function writeSignedBits(int $value, int $count): self
    {
        $this->setSignedBits($this->cursor, $value, $count);
        $this->cursor += $count;
        return $this;
    }

    // --- High Level Typed Reading & Writing ---

    public function readUInt8(): int
    {
        return $this->readBits(8);
    }

    public function writeUInt8(int $value): self
    {
        return $this->writeBits($value & 0xff, 8);
    }

    public function readUInt16(bool $bigEndian = true): int
    {
        if ($bigEndian) {
            return $this->readBits(16);
        } else {
            $b0 = $this->readBits(8);
            $b1 = $this->readBits(8);
            return $b0 | ($b1 << 8);
        }
    }

    public function writeUInt16(int $value, bool $bigEndian = true): self
    {
        if ($bigEndian) {
            return $this->writeBits($value & 0xffff, 16);
        } else {
            $this->writeBits($value & 0xff, 8);
            $this->writeBits(($value >> 8) & 0xff, 8);
            return $this;
        }
    }

    public function readUInt32(bool $bigEndian = true): int
    {
        if ($bigEndian) {
            return $this->readBits(32);
        } else {
            $b0 = $this->readBits(8);
            $b1 = $this->readBits(8);
            $b2 = $this->readBits(8);
            $b3 = $this->readBits(8);
            return $b0 | ($b1 << 8) | ($b2 << 16) | ($b3 << 24);
        }
    }

    public function writeUInt32(int $value, bool $bigEndian = true): self
    {
        if ($bigEndian) {
            return $this->writeBits($value & 0xffffffff, 32);
        } else {
            $this->writeBits($value & 0xff, 8);
            $this->writeBits(($value >> 8) & 0xff, 8);
            $this->writeBits(($value >> 16) & 0xff, 8);
            $this->writeBits(($value >> 24) & 0xff, 8);
            return $this;
        }
    }

    public function readInt8(): int
    {
        return $this->readSignedBits(8);
    }

    public function writeInt8(int $value): self
    {
        return $this->writeSignedBits($value, 8);
    }

    public function readInt16(bool $bigEndian = true): int
    {
        if ($bigEndian) {
            return $this->readSignedBits(16);
        } else {
            $val = $this->readUInt16(false);
            return $val >= 0x8000 ? $val - 0x10000 : $val;
        }
    }

    public function writeInt16(int $value, bool $bigEndian = true): self
    {
        return $this->writeUInt16($value & 0xffff, $bigEndian);
    }

    public function readInt32(bool $bigEndian = true): int
    {
        if ($bigEndian) {
            return $this->readSignedBits(32);
        } else {
            $val = $this->readUInt32(false);
            return $val >= 0x80000000 ? $val - 0x100000000 : $val;
        }
    }

    public function writeInt32(int $value, bool $bigEndian = true): self
    {
        return $this->writeUInt32($value & 0xffffffff, $bigEndian);
    }

    public function readBytes(int $byteCount): string
    {
        $bytes = '';
        for ($i = 0; $i < $byteCount; $i++) {
            $bytes .= chr($this->readUInt8());
        }
        return $bytes;
    }

    public function writeBytes(string $bytes): self
    {
        $len = strlen($bytes);
        for ($i = 0; $i < $len; $i++) {
            $this->writeUInt8(ord($bytes[$i]));
        }
        return $this;
    }

    public function readString(?int $byteLength = null): string
    {
        $len = $byteLength ?? ($this->getRemainingBits() >> 3);
        return $this->readBytes($len);
    }

    public function writeString(string $str): self
    {
        return $this->writeBytes($str);
    }

    // --- Schema Bitfield Packing & Unpacking ---

    /**
     * @param array<string, int> $fields
     * @param array<int, array{name: string, bits: int}> $schema
     */
    public function pack(array $fields, array $schema): self
    {
        foreach ($schema as $item) {
            $name = $item['name'];
            $bits = $item['bits'];
            if (!isset($fields[$name])) {
                throw new InvalidArgumentException("Missing field '{$name}' in schema packing");
            }
            $this->writeBits((int)$fields[$name], $bits);
        }
        return $this;
    }

    /**
     * @param array<int, array{name: string, bits: int}> $schema
     * @return array<string, int>
     */
    public function unpack(array $schema): array
    {
        $result = [];
        foreach ($schema as $item) {
            $result[$item['name']] = $this->readBits($item['bits']);
        }
        return $result;
    }

    // --- Bitwise Buffer Logic ---

    public function and(BitBuffer $other): self
    {
        $minLen = min($this->bitLength, $other->getBitLength());
        $res = self::allocate($minLen);
        for ($i = 0; $i < $minLen; $i++) {
            $res->setBit($i, $this->getBit($i) & $other->getBit($i));
        }
        return $res;
    }

    public function or(BitBuffer $other): self
    {
        $maxLen = max($this->bitLength, $other->getBitLength());
        $res = self::allocate($maxLen);
        for ($i = 0; $i < $maxLen; $i++) {
            $b1 = $i < $this->bitLength ? $this->getBit($i) : 0;
            $b2 = $i < $other->getBitLength() ? $other->getBit($i) : 0;
            $res->setBit($i, $b1 | $b2);
        }
        return $res;
    }

    public function xor(BitBuffer $other): self
    {
        $maxLen = max($this->bitLength, $other->getBitLength());
        $res = self::allocate($maxLen);
        for ($i = 0; $i < $maxLen; $i++) {
            $b1 = $i < $this->bitLength ? $this->getBit($i) : 0;
            $b2 = $i < $other->getBitLength() ? $other->getBit($i) : 0;
            $res->setBit($i, $b1 ^ $b2);
        }
        return $res;
    }

    public function not(): self
    {
        $res = self::allocate($this->bitLength);
        for ($i = 0; $i < $this.bitLength; $i++) {
            $res->setBit($i, $this->getBit($i) === 0 ? 1 : 0);
        }
        return $res;
    }

    public function shiftLeft(int $shiftCount): self
    {
        $res = self::allocate($this->bitLength);
        if ($shiftCount >= $this->bitLength) {
            return $res;
        }
        for ($i = 0; $i < $this.bitLength - $shiftCount; $i++) {
            $res->setBit($i, $this->getBit($i + $shiftCount));
        }
        return $res;
    }

    public function shiftRight(int $shiftCount): self
    {
        $res = self::allocate($this->bitLength);
        if ($shiftCount >= $this->bitLength) {
            return $res;
        }
        for ($i = 0; $i < $this.bitLength - $shiftCount; $i++) {
            $res->setBit($i + $shiftCount, $this->getBit($i));
        }
        return $res;
    }

    // --- Sub-buffer Slicing & Formatting ---

    public function slice(int $startBit = 0, ?int $endBit = null): self
    {
        $end = $endBit ?? $this->bitLength;
        if ($startBit < 0 || $startBit > $this->bitLength) {
            throw new OutOfBoundsException("startBit {$startBit} out of bounds");
        }
        if ($end < $startBit || $end > $this->bitLength) {
            throw new OutOfBoundsException("endBit {$end} out of bounds");
        }

        $bitLen = $end - $startBit;
        $sliced = self::allocate($bitLen);
        for ($i = 0; $i < $bitLen; $i++) {
            $sliced->setBit($i, $this->getBit($startBit + $i));
        }
        return $sliced;
    }

    public function cloneBuffer(): self
    {
        $copy = self::allocate($this->bitLength);
        $copy->buffer = substr($this->buffer, 0, $this->getByteLength());
        $copy->cursor = $this->cursor;
        return $copy;
    }

    public function fill(int|bool $value, int $startBit = 0, ?int $endBit = null): self
    {
        $end = $endBit ?? $this->bitLength;
        for ($i = $startBit; $i < $end; $i++) {
            $this->setBit($i, $value);
        }
        return $this;
    }

    public function toBitString(): string
    {
        $str = '';
        for ($i = 0; $i < $this->bitLength; $i++) {
            $str .= (string)$this->getBit($i);
        }
        return $str;
    }

    public function toHexString(): string
    {
        return bin2hex($this->toBinaryString());
    }

    public function toBinaryString(): string
    {
        return substr($this->buffer, 0, $this->getByteLength());
    }

    // --- WebRTC Text Message Bit-Compression ---

    public static function compressTextMessage(string $text): string
    {
        if ($text === '') {
            return $text;
        }

        $isAscii = true;
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            if (ord($text[$i]) > 127) {
                $isAscii = false;
                break;
            }
        }

        if ($isAscii && $len <= 65535) {
            $bitCount = 32 + ($len * 7);
            $bb = self::allocate($bitCount);
            $bb->writeUInt8(0xBC);
            $bb->writeUInt8(0x01);
            $bb->writeUInt16($len, true);

            for ($i = 0; $i < $len; $i++) {
                $bb->writeBits(ord($text[$i]) & 0x7F, 7);
            }

            $base64 = base64_encode($bb->toBinaryString());
            return json_encode([
                '__bc' => true,
                'mode' => 'ascii7',
                'origLen' => $len,
                'compBytes' => $bb->getByteLength(),
                'data' => $base64
            ]);
        } else {
            $bb = self::allocate(32 + ($len * 8));
            $bb->writeUInt8(0xBC);
            $bb->writeUInt8(0x02);
            $bb->writeUInt16($len, true);
            $bb->writeBytes($text);

            $base64 = base64_encode($bb->toBinaryString());
            return json_encode([
                '__bc' => true,
                'mode' => 'utf8',
                'origLen' => mb_strlen($text, 'UTF-8'),
                'compBytes' => $bb->getByteLength(),
                'data' => $base64
            ]);
        }
    }

    public static function decompressTextMessage(string $payload): string
    {
        if ($payload === '' || !str_contains($payload, '{')) {
            return $payload;
        }

        $json = json_decode($payload, true);
        if (!is_array($json) || empty($json['__bc']) || empty($json['data'])) {
            return $payload;
        }

        $binary = base64_decode((string)$json['data'], true);
        if ($binary === false || strlen($binary) < 4) {
            return $payload;
        }

        $bb = self::from($binary);
        $magic = $bb->readUInt8();
        if ($magic !== 0xBC) {
            return $payload;
        }

        $mode = $bb->readUInt8();
        $len = $bb->readUInt16(true);

        if ($mode === 0x01) {
            $result = '';
            for ($i = 0; $i < $len; $i++) {
                $result .= chr($bb->readBits(7));
            }
            return $result;
        } elseif ($mode === 0x02) {
            return $bb->readBytes($len);
        }

        return $payload;
    }
}
