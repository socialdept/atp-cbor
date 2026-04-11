<?php

declare(strict_types=1);

namespace SocialDept\AtpCbor\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SocialDept\AtpCbor\CBOR\Encoder;
use SocialDept\AtpCbor\Core\CBOR;
use SocialDept\AtpCbor\Core\CID;

class EncoderTest extends TestCase
{
    public function test_encode_unsigned_integers(): void
    {
        // Small values (0-23) encode as single byte
        $this->assertSame("\x00", Encoder::encode(0));
        $this->assertSame("\x01", Encoder::encode(1));
        $this->assertSame("\x17", Encoder::encode(23));

        // 1-byte value
        $this->assertSame("\x18\x18", Encoder::encode(24));
        $this->assertSame("\x18\xFF", Encoder::encode(255));

        // 2-byte value
        $this->assertSame("\x19\x01\x00", Encoder::encode(256));
        $this->assertSame("\x19\x03\xE8", Encoder::encode(1000));
    }

    public function test_encode_negative_integers(): void
    {
        $this->assertSame("\x20", Encoder::encode(-1));
        $this->assertSame("\x29", Encoder::encode(-10));
        $this->assertSame("\x38\x63", Encoder::encode(-100));
    }

    public function test_encode_text_strings(): void
    {
        $this->assertSame("\x60", Encoder::encode(''));
        $this->assertSame("\x65hello", Encoder::encode('hello'));
        $this->assertSame("\x64IETF", Encoder::encode('IETF'));
    }

    public function test_encode_booleans(): void
    {
        $this->assertSame("\xF4", Encoder::encode(false));
        $this->assertSame("\xF5", Encoder::encode(true));
    }

    public function test_encode_null(): void
    {
        $this->assertSame("\xF6", Encoder::encode(null));
    }

    public function test_encode_arrays(): void
    {
        // Empty array
        $this->assertSame("\x80", Encoder::encode([]));

        // [1, 2, 3]
        $this->assertSame("\x83\x01\x02\x03", Encoder::encode([1, 2, 3]));
    }

    public function test_encode_maps(): void
    {
        // Empty map — tricky: empty array is sequential, so force assoc
        // {"a": 1}
        $this->assertSame("\xA1\x61a\x01", Encoder::encode(['a' => 1]));

        // {"a": 1, "b": 2}
        $this->assertSame("\xA2\x61a\x01\x61b\x02", Encoder::encode(['a' => 1, 'b' => 2]));
    }

    public function test_map_keys_sorted_by_length_then_lexicographic(): void
    {
        // DAG-CBOR canonical ordering: shorter keys first, then alphabetical
        $input = ['bb' => 2, 'a' => 1, 'ccc' => 3, 'aa' => 4];

        $encoded = Encoder::encode($input);
        $decoded = CBOR::decode($encoded);

        // Keys should be ordered: "a" (1 char), "aa", "bb" (2 chars), "ccc" (3 chars)
        $keys = array_keys($decoded);
        $this->assertSame(['a', 'aa', 'bb', 'ccc'], $keys);
    }

    public function test_encode_nested_structures(): void
    {
        $data = [
            'key' => [1, 2, ['inner' => true]],
        ];

        $encoded = Encoder::encode($data);
        $decoded = CBOR::decode($encoded);

        $this->assertSame($data, $decoded);
    }

    public function test_encode_cid(): void
    {
        // Create a CID
        $hash = str_repeat("\xAB", 32);
        $multihash = chr(0x12).chr(0x20).$hash;
        $cid = new CID(1, 0x71, $multihash);

        $encoded = Encoder::encode($cid);
        $decoded = CBOR::decode($encoded);

        $this->assertInstanceOf(CID::class, $decoded);
        $this->assertSame(1, $decoded->version);
        $this->assertSame(0x71, $decoded->codec);
    }

    public function test_roundtrip_complex_structure(): void
    {
        // Simulate a PLC operation structure
        $data = [
            'type' => 'plc_operation',
            'rotationKeys' => ['did:key:zQ3shTest123'],
            'verificationMethods' => [
                'atproto' => 'did:key:zQ3shTest456',
            ],
            'alsoKnownAs' => ['at://test.example.com'],
            'services' => [
                'atproto_pds' => [
                    'type' => 'AtprotoPersonalDataServer',
                    'endpoint' => 'https://pds.example.com',
                ],
            ],
            'prev' => null,
        ];

        $encoded = Encoder::encode($data);
        $decoded = CBOR::decode($encoded);

        $this->assertSame($data['type'], $decoded['type']);
        $this->assertSame($data['rotationKeys'], $decoded['rotationKeys']);
        $this->assertSame($data['services']['atproto_pds']['endpoint'], $decoded['services']['atproto_pds']['endpoint']);
        $this->assertNull($decoded['prev']);
    }

    public function test_encode_is_deterministic(): void
    {
        $data = ['z' => 1, 'a' => 2, 'mm' => 3];

        // Encoding same data twice should produce identical bytes
        $this->assertSame(Encoder::encode($data), Encoder::encode($data));
    }

    public function test_cid_for_dag_cbor(): void
    {
        $data = ['hello' => 'world'];

        $cid = CID::forDagCbor($data);

        $this->assertSame(1, $cid->version);
        $this->assertSame(0x71, $cid->codec);
        $this->assertSame(34, strlen($cid->hash)); // 2 byte prefix + 32 byte hash

        // Same data should produce same CID
        $cid2 = CID::forDagCbor($data);
        $this->assertSame($cid->toString(), $cid2->toString());
    }

    public function test_cid_for_dag_cbor_bytes(): void
    {
        $data = ['test' => true];
        $encoded = Encoder::encode($data);

        $cid1 = CID::forDagCbor($data);
        $cid2 = CID::forDagCborBytes($encoded);

        $this->assertSame($cid1->toString(), $cid2->toString());
    }

    public function test_encode_float(): void
    {
        $encoded = Encoder::encode(1.5);
        $decoded = CBOR::decode($encoded);

        $this->assertSame(1.5, $decoded);
    }
}
