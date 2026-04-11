<?php

declare(strict_types=1);

namespace SocialDept\AtpCbor\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SocialDept\AtpCbor\Crypto\Secp256k1Keypair;

class Secp256k1Test extends TestCase
{
    public function test_create_keypair(): void
    {
        $keypair = Secp256k1Keypair::create();

        $this->assertSame(33, strlen($keypair->publicKeyBytes())); // compressed
        $this->assertSame(64, strlen($keypair->privateKeyHex()));
        $this->assertSame(32, strlen($keypair->privateKeyBytes()));
    }

    public function test_import_from_hex(): void
    {
        $original = Secp256k1Keypair::create();
        $hex = $original->privateKeyHex();

        $imported = Secp256k1Keypair::fromHex($hex);

        $this->assertSame($original->publicKeyBytes(), $imported->publicKeyBytes());
        $this->assertSame($original->did(), $imported->did());
    }

    public function test_import_from_bytes(): void
    {
        $original = Secp256k1Keypair::create();
        $bytes = $original->privateKeyBytes();

        $imported = Secp256k1Keypair::fromBytes($bytes);

        $this->assertSame($original->did(), $imported->did());
    }

    public function test_sign_produces_64_byte_compact_signature(): void
    {
        $keypair = Secp256k1Keypair::create();

        $signature = $keypair->sign('hello world');

        $this->assertSame(64, strlen($signature));
    }

    public function test_sign_is_deterministic(): void
    {
        $keypair = Secp256k1Keypair::create();

        $sig1 = $keypair->sign('test message');
        $sig2 = $keypair->sign('test message');

        $this->assertSame($sig1, $sig2);
    }

    public function test_different_messages_produce_different_signatures(): void
    {
        $keypair = Secp256k1Keypair::create();

        $sig1 = $keypair->sign('message one');
        $sig2 = $keypair->sign('message two');

        $this->assertNotSame($sig1, $sig2);
    }

    public function test_verify_valid_signature(): void
    {
        $keypair = Secp256k1Keypair::create();
        $message = 'verify this';

        $signature = $keypair->sign($message);

        $this->assertTrue($keypair->verify($message, $signature));
    }

    public function test_verify_rejects_wrong_message(): void
    {
        $keypair = Secp256k1Keypair::create();

        $signature = $keypair->sign('correct message');

        $this->assertFalse($keypair->verify('wrong message', $signature));
    }

    public function test_verify_rejects_wrong_key(): void
    {
        $keypair1 = Secp256k1Keypair::create();
        $keypair2 = Secp256k1Keypair::create();

        $signature = $keypair1->sign('test');

        $this->assertFalse($keypair2->verify('test', $signature));
    }

    public function test_did_key_format(): void
    {
        $keypair = Secp256k1Keypair::create();

        $did = $keypair->did();

        $this->assertStringStartsWith('did:key:z', $did);
    }

    public function test_did_key_is_deterministic(): void
    {
        $keypair = Secp256k1Keypair::create();

        $this->assertSame($keypair->did(), $keypair->did());
    }

    public function test_different_keys_produce_different_dids(): void
    {
        $keypair1 = Secp256k1Keypair::create();
        $keypair2 = Secp256k1Keypair::create();

        $this->assertNotSame($keypair1->did(), $keypair2->did());
    }

    public function test_sign_dag_cbor(): void
    {
        $keypair = Secp256k1Keypair::create();

        $data = [
            'type' => 'plc_operation',
            'prev' => null,
            'services' => [
                'atproto_pds' => [
                    'type' => 'AtprotoPersonalDataServer',
                    'endpoint' => 'https://pds.example.com',
                ],
            ],
        ];

        $sig = $keypair->signDagCbor($data);

        // base64url encoded, should be ~86 chars for 64 bytes
        $this->assertNotEmpty($sig);
        $this->assertStringNotContainsString('+', $sig);
        $this->assertStringNotContainsString('/', $sig);
        $this->assertStringNotContainsString('=', $sig);

        // Decode and verify
        $sigBytes = Secp256k1Keypair::base64urlDecode($sig);
        $this->assertSame(64, strlen($sigBytes));

        // Verify against the CBOR-encoded data
        $cbor = \SocialDept\AtpCbor\Core\CBOR::encode($data);
        $this->assertTrue($keypair->verify($cbor, $sigBytes));
    }

    public function test_base64url_roundtrip(): void
    {
        $data = random_bytes(64);

        $encoded = Secp256k1Keypair::base64urlEncode($data);
        $decoded = Secp256k1Keypair::base64urlDecode($encoded);

        $this->assertSame($data, $decoded);
    }

    public function test_known_private_key_produces_consistent_did(): void
    {
        // Import same key twice, verify same DID
        $hex = bin2hex(random_bytes(32));

        $keypair1 = Secp256k1Keypair::fromHex($hex);
        $keypair2 = Secp256k1Keypair::fromHex($hex);

        $this->assertSame($keypair1->did(), $keypair2->did());
        $this->assertSame($keypair1->publicKeyBytes(), $keypair2->publicKeyBytes());
    }
}
