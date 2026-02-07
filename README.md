<h1 align="center">ATP CBOR</h1>

<h3 align="center">
    CBOR, CAR, and CID binary encoding for AT Protocol in PHP.
</h3>

<p align="center">
    <br>
    <a href="https://packagist.org/packages/socialdept/atp-cbor" title="Latest Version on Packagist"><img src="https://img.shields.io/packagist/v/socialdept/atp-cbor.svg?style=flat-square"></a>
    <a href="https://packagist.org/packages/socialdept/atp-cbor" title="Total Downloads"><img src="https://img.shields.io/packagist/dt/socialdept/atp-cbor.svg?style=flat-square"></a>
    <a href="https://github.com/socialdept/atp-cbor/actions/workflows/tests.yml" title="GitHub Tests Action Status"><img src="https://img.shields.io/github/actions/workflow/status/socialdept/atp-cbor/tests.yml?branch=main&label=tests&style=flat-square"></a>
    <a href="LICENSE" title="Software License"><img src="https://img.shields.io/github/license/socialdept/atp-cbor?style=flat-square"></a>
</p>

---

## What is ATP CBOR?

**ATP CBOR** is a standalone PHP library for decoding CBOR (Concise Binary Object Representation), CAR (Content Addressable aRchive), and CID (Content Identifier) binary data from the AT Protocol network. It powers the binary decoding layer used by firehose consumers and repository loaders across the Social Dept. AT Protocol packages.

No Laravel dependency required — this is a pure PHP package.

## Quick Example

```php
use SocialDept\AtpCbor\Core\CBOR;
use SocialDept\AtpCbor\Core\CAR;
use SocialDept\AtpCbor\Core\CID;

// Decode CBOR data from a firehose event
$decoded = CBOR::decode($binaryData);

// Parse CAR blocks from a repository export
$blocks = CAR::blockMap($carData);

// Parse a CID string
$cid = CID::fromString('bafyreie5cvv4h45feadgeuwhbcutmh6t7ceseocckahdoe6uat64zmz454a');
echo $cid->version; // 1
```

## Installation

```bash
composer require socialdept/atp-cbor
```

Requires the `gmp` PHP extension for large integer handling.

## Usage

### Decoding CBOR

The `CBOR` facade provides three methods for decoding binary CBOR data:

```php
use SocialDept\AtpCbor\Core\CBOR;

// Decode a single CBOR item
$value = CBOR::decode($data);

// Decode the first item and get remaining data
[$value, $remaining] = CBOR::decodeFirst($data);

// Decode all sequential CBOR items
$items = CBOR::decodeAll($data);
```

Supports all CBOR major types including unsigned/negative integers, byte strings, text strings, arrays, maps, tagged values (including DAG-CBOR tag 42 for CID links), and special values (booleans, null, floats).

### Parsing CAR Archives

CAR (Content Addressable aRchive) files contain blocks of CBOR data indexed by CID:

```php
use SocialDept\AtpCbor\Core\CAR;
use SocialDept\AtpCbor\CAR\RecordExtractor;

// Get all blocks as a CID => data map
$blocks = CAR::blockMap($carData);

// Extract records from AT Protocol MST blocks
$extractor = new RecordExtractor($blocks, 'did:plc:xyz');
foreach ($extractor->extractRecords($rootCid) as $path => $record) {
    echo $record['uri'];       // at://did:plc:xyz/app.bsky.feed.post/3k4...
    echo $record['cid'];       // bafyrei...
    print_r($record['value']); // Decoded record data
}
```

For memory-efficient processing, use the `BlockReader` generator directly:

```php
use SocialDept\AtpCbor\CAR\BlockReader;

$reader = new BlockReader($carData);
foreach ($reader->blocks() as [$cid, $blockData]) {
    // Process each block individually
}
```

### Working with CIDs

Content Identifiers can be parsed from strings or binary data:

```php
use SocialDept\AtpCbor\Core\CID;

// Parse from string (auto-detects v0 vs v1)
$cid = CID::fromString('bafyreie5cvv4h45feadgeuwhbcutmh6t7ceseocckahdoe6uat64zmz454a');

// Parse from binary data
$cid = CID::fromBinary($bytes);

// Access properties
$cid->version; // 0 or 1
$cid->codec;   // Codec identifier
$cid->hash;    // Raw multihash bytes

// Convert back
$cid->toString();  // Base32 (v1) or base58btc (v0)
$cid->toBinary();  // Raw binary
```

### Low-level Binary Reading

For custom binary parsing, use the `Reader` and `Varint` utilities:

```php
use SocialDept\AtpCbor\Binary\Reader;
use SocialDept\AtpCbor\Binary\Varint;

// Stream-style binary reader
$reader = new Reader($data);
$byte = $reader->readByte();
$chunk = $reader->readBytes(32);
$varint = $reader->readVarint();

// Standalone varint decoding
$offset = 0;
$value = Varint::decode($data, $offset);
```

## API Reference

### Core Facades

| Class | Method | Description |
|-------|--------|-------------|
| `CBOR` | `decode(string $data)` | Decode complete CBOR data |
| `CBOR` | `decodeFirst(string $data)` | Decode first item, return remaining |
| `CBOR` | `decodeAll(string $data)` | Decode all sequential items |
| `CAR` | `blockMap(string $data, ?string $did)` | Parse CAR into CID => block map |

### Value Objects

| Class | Description |
|-------|-------------|
| `CID` | Immutable Content Identifier (v0 and v1) |

### Readers

| Class | Description |
|-------|-------------|
| `BlockReader` | Generator-based CAR block reader |
| `RecordExtractor` | AT Protocol MST record walker |
| `Decoder` | RFC 8949 CBOR decoder with DAG-CBOR |
| `Reader` | Stream-style binary data reader |
| `Varint` | Variable-length integer decoder |

## Supported Standards

- **CBOR** - RFC 8949 (Concise Binary Object Representation)
- **DAG-CBOR** - IPLD extension with tag 42 for CID links
- **CAR** - Content Addressable aRchive (v1)
- **CID** - IPLD Content Identifiers (v0 and v1)
- **Multihash** - SHA-256 and other hash types
- **Varint** - Protocol Buffers-style variable-length integers

## Requirements

- PHP 8.2+
- ext-gmp

## Resources

- [AT Protocol Documentation](https://atproto.com/)
- [CBOR RFC 8949](https://www.rfc-editor.org/rfc/rfc8949)
- [IPLD CID Specification](https://github.com/multiformats/cid)
- [CAR Specification](https://ipld.io/specs/transport/car/)

## Support & Contributing

Found a bug or have a feature request? [Open an issue](https://github.com/socialdept/atp-cbor/issues).

Want to contribute? We'd love your help! Check out the [contribution guidelines](CONTRIBUTING.md).

## Credits

- [Miguel Batres](https://batres.co) - founder & lead maintainer
- [All contributors](https://github.com/socialdept/atp-cbor/graphs/contributors)

## License

ATP CBOR is open-source software licensed under the [MIT license](LICENSE).

---

**Built for the Atmosphere** • By Social Dept.
