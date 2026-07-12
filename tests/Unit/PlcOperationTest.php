<?php

declare(strict_types=1);

namespace SocialDept\AtpCbor\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SocialDept\AtpCbor\Core\CBOR;
use SocialDept\AtpCbor\Core\CID;
use SocialDept\AtpCbor\Crypto\PlcOperation;
use SocialDept\AtpCbor\Crypto\Secp256k1Keypair;

class PlcOperationTest extends TestCase
{
    /**
     * A minimal but structurally valid last operation from a PLC log, including
     * the trailing `sig` (the prev CID is computed over the op as stored).
     *
     * @return array<string, mixed>
     */
    private function lastOp(Secp256k1Keypair $signer): array
    {
        return [
            'type' => 'plc_operation',
            'rotationKeys' => [$signer->did()],
            'verificationMethods' => ['atproto' => $signer->did()],
            'alsoKnownAs' => ['at://alice.test'],
            'services' => [
                'atproto_pds' => [
                    'type' => 'AtprotoPersonalDataServer',
                    'endpoint' => 'https://pds.test',
                ],
            ],
            'prev' => null,
            'sig' => 'ZmFrZXNpZw',
        ];
    }

    public function test_tombstone_has_only_type_and_prev(): void
    {
        $signer = Secp256k1Keypair::create();

        $unsigned = PlcOperation::tombstone($this->lastOp($signer));

        $this->assertSame(['type', 'prev'], array_keys($unsigned));
        $this->assertSame('plc_tombstone', $unsigned['type']);
    }

    public function test_tombstone_prev_is_the_cid_of_the_previous_operation(): void
    {
        $signer = Secp256k1Keypair::create();
        $lastOp = $this->lastOp($signer);

        $unsigned = PlcOperation::tombstone($lastOp);

        $this->assertSame(CID::forDagCbor($lastOp)->toString(), $unsigned['prev']);
    }

    public function test_signed_tombstone_carries_a_verifiable_unpadded_signature(): void
    {
        $signer = Secp256k1Keypair::create();

        $unsigned = PlcOperation::tombstone($this->lastOp($signer));
        $signed = PlcOperation::sign($unsigned, $signer);

        // The signed op adds exactly `sig` and nothing else leaks in.
        $this->assertSame(['type', 'prev', 'sig'], array_keys($signed));

        // base64url without padding, per the PLC spec.
        $this->assertStringNotContainsString('=', $signed['sig']);
        $this->assertStringNotContainsString('+', $signed['sig']);
        $this->assertStringNotContainsString('/', $signed['sig']);

        // The signature verifies over the DAG-CBOR of the unsigned op.
        $rawSig = Secp256k1Keypair::base64urlDecode($signed['sig']);
        $this->assertTrue($signer->verify(CBOR::encode($unsigned), $rawSig));
    }
}
