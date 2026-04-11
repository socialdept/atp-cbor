<?php

declare(strict_types=1);

namespace SocialDept\AtpCbor\CBOR;

use RuntimeException;
use SocialDept\AtpCbor\Core\CID;

/**
 * DAG-CBOR deterministic encoder.
 *
 * Implements RFC 8949 CBOR with DAG-CBOR canonical encoding rules:
 * - Map keys sorted by byte length first, then lexicographically
 * - Integers use minimum byte representation
 * - CID links encoded as tag 42 with 0x00 prefix byte
 * - No indefinite-length items
 */
class Encoder
{
    private string $buffer = '';

    /**
     * Encode a value to DAG-CBOR bytes.
     */
    public static function encode(mixed $value): string
    {
        $encoder = new self();
        $encoder->encodeValue($value);

        return $encoder->buffer;
    }

    /**
     * Encode any value.
     */
    private function encodeValue(mixed $value): void
    {
        if ($value instanceof CID) {
            $this->encodeCid($value);

            return;
        }

        if (is_null($value)) {
            $this->encodeNull();

            return;
        }

        if (is_bool($value)) {
            $this->encodeBool($value);

            return;
        }

        if (is_int($value)) {
            $this->encodeInt($value);

            return;
        }

        if (is_float($value)) {
            $this->encodeFloat($value);

            return;
        }

        if (is_string($value)) {
            $this->encodeText($value);

            return;
        }

        if (is_array($value)) {
            if ($this->isSequentialArray($value)) {
                $this->encodeArray($value);
            } else {
                $this->encodeMap($value);
            }

            return;
        }

        if (is_object($value)) {
            $this->encodeMap((array) $value);

            return;
        }

        throw new RuntimeException('Cannot encode value of type: '.gettype($value));
    }

    /**
     * Encode a CID as tag 42 with 0x00 prefix.
     */
    private function encodeCid(CID $cid): void
    {
        // Tag 42
        $this->encodeTypeAndLength(6, 42);

        // Byte string: 0x00 prefix + CID binary
        $cidBytes = "\x00".$cid->toBinary();
        $this->encodeTypeAndLength(2, strlen($cidBytes));
        $this->buffer .= $cidBytes;
    }

    /**
     * Encode null (major type 7, value 22).
     */
    private function encodeNull(): void
    {
        $this->buffer .= chr((7 << 5) | 22);
    }

    /**
     * Encode boolean (major type 7, value 20/21).
     */
    private function encodeBool(bool $value): void
    {
        $this->buffer .= chr((7 << 5) | ($value ? 21 : 20));
    }

    /**
     * Encode integer with minimum byte representation.
     */
    private function encodeInt(int $value): void
    {
        if ($value >= 0) {
            // Major type 0: unsigned integer
            $this->encodeTypeAndLength(0, $value);
        } else {
            // Major type 1: negative integer (-1 - value)
            $this->encodeTypeAndLength(1, -1 - $value);
        }
    }

    /**
     * Encode float as IEEE 754 double (64-bit).
     * DAG-CBOR requires doubles for all floats.
     */
    private function encodeFloat(float $value): void
    {
        $this->buffer .= chr((7 << 5) | 27);
        $this->buffer .= pack('E', $value);
    }

    /**
     * Encode text string (major type 3).
     */
    private function encodeText(string $value): void
    {
        $this->encodeTypeAndLength(3, strlen($value));
        $this->buffer .= $value;
    }

    /**
     * Encode byte string (major type 2).
     */
    public function encodeBytes(string $value): void
    {
        $this->encodeTypeAndLength(2, strlen($value));
        $this->buffer .= $value;
    }

    /**
     * Encode sequential array (major type 4).
     */
    private function encodeArray(array $value): void
    {
        $this->encodeTypeAndLength(4, count($value));

        foreach ($value as $item) {
            $this->encodeValue($item);
        }
    }

    /**
     * Encode map with DAG-CBOR canonical key sorting (major type 5).
     *
     * Keys are sorted by byte length first, then lexicographically.
     * This is the canonical CBOR map key ordering per RFC 7049 section 3.9.
     */
    private function encodeMap(array $value): void
    {
        // Sort keys: by byte length first, then lexicographically
        $keys = array_keys($value);
        usort($keys, function ($a, $b) {
            $a = (string) $a;
            $b = (string) $b;

            $lenDiff = strlen($a) - strlen($b);
            if ($lenDiff !== 0) {
                return $lenDiff;
            }

            return strcmp($a, $b);
        });

        $this->encodeTypeAndLength(5, count($keys));

        foreach ($keys as $key) {
            $this->encodeText((string) $key);
            $this->encodeValue($value[$key]);
        }
    }

    /**
     * Encode major type and length/value with minimum byte representation.
     */
    private function encodeTypeAndLength(int $majorType, int $value): void
    {
        $mt = $majorType << 5;

        if ($value < 24) {
            $this->buffer .= chr($mt | $value);
        } elseif ($value < 256) {
            $this->buffer .= chr($mt | 24).chr($value);
        } elseif ($value < 65536) {
            $this->buffer .= chr($mt | 25).pack('n', $value);
        } elseif ($value < 4294967296) {
            $this->buffer .= chr($mt | 26).pack('N', $value);
        } else {
            $this->buffer .= chr($mt | 27).pack('J', $value);
        }
    }

    /**
     * Check if array is sequential (list vs map).
     */
    private function isSequentialArray(array $value): bool
    {
        if (empty($value)) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}
