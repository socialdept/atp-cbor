<?php

declare(strict_types=1);

namespace SocialDept\AtpCbor\Core;

use SocialDept\AtpCbor\CBOR\Decoder;
use SocialDept\AtpCbor\CBOR\Encoder;

/**
 * CBOR facade for encoding and decoding operations.
 */
class CBOR
{
    /**
     * Encode a value to DAG-CBOR bytes.
     *
     * @param mixed $data Value to encode
     * @return string Binary DAG-CBOR data
     */
    public static function encode(mixed $data): string
    {
        return Encoder::encode($data);
    }

    /**
     * Decode first CBOR item and return remainder.
     *
     * @param string $data Binary CBOR data
     * @return array{0: mixed, 1: string} [decoded value, remaining data]
     */
    public static function decodeFirst(string $data): array
    {
        $decoder = new Decoder($data);
        $value = $decoder->decode();

        // Calculate remaining data based on decoder position
        $position = $decoder->getPosition();
        $remainder = substr($data, $position);

        return [$value, $remainder];
    }

    /**
     * Decode complete CBOR data.
     *
     * @param string $data Binary CBOR data
     * @return mixed Decoded value
     */
    public static function decode(string $data): mixed
    {
        $decoder = new Decoder($data);

        return $decoder->decode();
    }

    /**
     * Decode all CBOR items from data.
     *
     * @param string $data Binary CBOR data
     * @return array All decoded values
     */
    public static function decodeAll(string $data): array
    {
        $decoder = new Decoder($data);
        $items = [];

        while ($decoder->hasMore()) {
            $items[] = $decoder->decode();
        }

        return $items;
    }
}
