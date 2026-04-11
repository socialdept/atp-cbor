<?php

declare(strict_types=1);

namespace SocialDept\AtpCbor\Crypto;

use SocialDept\AtpCbor\Core\CBOR;
use SocialDept\AtpCbor\Core\CID;

/**
 * Build and sign PLC (DID PLC) operations.
 *
 * PLC operations are signed mutations to DID documents stored on plc.directory.
 * Each operation references the previous one by CID, forming a hash-linked chain.
 */
class PlcOperation
{
    /**
     * Build an unsigned operation for updating a PDS endpoint.
     *
     * @param  array<string, mixed>  $lastOp  The last operation from the PLC log
     * @param  string  $newEndpoint  The new PDS service endpoint URL
     * @return array<string, mixed>  Unsigned operation ready for signing
     */
    public static function updateServiceEndpoint(array $lastOp, string $newEndpoint): array
    {
        $normalized = self::normalize($lastOp);
        $prev = self::computePrev($lastOp);

        $normalized['services']['atproto_pds']['endpoint'] = $newEndpoint;
        $normalized['prev'] = $prev;

        return $normalized;
    }

    /**
     * Build an unsigned operation for updating the handle.
     *
     * @param  array<string, mixed>  $lastOp
     * @return array<string, mixed>
     */
    public static function updateHandle(array $lastOp, string $newHandle): array
    {
        $normalized = self::normalize($lastOp);
        $prev = self::computePrev($lastOp);

        $handle = str_starts_with($newHandle, 'at://') ? $newHandle : "at://{$newHandle}";

        // Replace the at:// entry in alsoKnownAs
        $normalized['alsoKnownAs'] = array_map(
            fn ($aka) => str_starts_with($aka, 'at://') ? $handle : $aka,
            $normalized['alsoKnownAs'],
        );

        $normalized['prev'] = $prev;

        return $normalized;
    }

    /**
     * Build an unsigned operation for updating rotation keys.
     *
     * @param  array<string, mixed>  $lastOp
     * @param  string[]  $newRotationKeys  Array of did:key strings
     * @return array<string, mixed>
     */
    public static function updateRotationKeys(array $lastOp, array $newRotationKeys): array
    {
        $normalized = self::normalize($lastOp);
        $prev = self::computePrev($lastOp);

        $normalized['rotationKeys'] = $newRotationKeys;
        $normalized['prev'] = $prev;

        return $normalized;
    }

    /**
     * Sign an unsigned operation.
     *
     * DAG-CBOR encodes the operation, signs the bytes, and adds the sig field.
     *
     * @param  array<string, mixed>  $unsignedOp
     * @return array<string, mixed>  Signed operation with 'sig' field
     */
    public static function sign(array $unsignedOp, Secp256k1Keypair $signer): array
    {
        $sig = $signer->signDagCbor($unsignedOp);

        return array_merge($unsignedOp, ['sig' => $sig]);
    }

    /**
     * Compute the CID of an operation for the prev chain.
     *
     * @param  array<string, mixed>  $operation  The operation (including sig)
     * @return string  CID string (e.g. "bafyrei...")
     */
    public static function computePrev(array $operation): string
    {
        return CID::forDagCbor($operation)->toString();
    }

    /**
     * Normalize an operation to the v2 format.
     *
     * Strips the sig field and ensures the structure matches the current spec.
     *
     * @param  array<string, mixed>  $op
     * @return array<string, mixed>
     */
    public static function normalize(array $op): array
    {
        // Remove sig so it doesn't leak into the next operation
        unset($op['sig']);

        // If already v2 format, return as-is
        if (isset($op['type']) && $op['type'] === 'plc_operation') {
            return $op;
        }

        // Convert v1 (legacy) format to v2
        return [
            'type' => 'plc_operation',
            'verificationMethods' => [
                'atproto' => $op['signingKey'] ?? '',
            ],
            'rotationKeys' => array_filter([
                $op['recoveryKey'] ?? null,
                $op['signingKey'] ?? null,
            ]),
            'alsoKnownAs' => [
                str_starts_with($op['handle'] ?? '', 'at://')
                    ? $op['handle']
                    : 'at://' . ($op['handle'] ?? ''),
            ],
            'services' => [
                'atproto_pds' => [
                    'type' => 'AtprotoPersonalDataServer',
                    'endpoint' => $op['service'] ?? $op['pds'] ?? '',
                ],
            ],
            'prev' => $op['prev'] ?? null,
        ];
    }
}
