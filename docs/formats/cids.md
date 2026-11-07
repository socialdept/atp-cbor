# CIDs

Part of the [atp-cbor docs](../introduction.md#documentation). Parsing CIDs from strings and bytes, writing them back, and computing the CID of data.

## Parsing

A `CID` can be parsed from its string form or its binary form:

```php
use SocialDept\AtpCbor\Core\CID;

// From a string, base32 for v1 or base58btc for v0
$cid = CID::fromString('bafyreie5cvv4h45feadgeuwhbcutmh6t7ceseocckahdoe6uat64zmz454');

// From binary data
$cid = CID::fromBinary($bytes);
```

`fromString()` reads the multibase prefix: `b` is base32 and is read as a CIDv1, and a string starting with `Q` or `1` is base58btc and is read as a CIDv0. Any other prefix, an empty string or a version other than 0 or 1 throws a `RuntimeException`.

## Properties and output

A `CID` is immutable, with three public properties:

```php
$cid->version;   // 0 or 1
$cid->codec;     // the multicodec code, an int, 0x71 for dag-cbor
$cid->hash;      // the multihash bytes: hash type, length and digest

$cid->toString();   // base32 with a b prefix for v1, base58btc for v0
$cid->toBinary();   // the raw bytes
(string) $cid;      // the same as toString()
```

## Computing a CID

`forDagCbor()` encodes a value as DAG-CBOR, hashes it with SHA-256 and returns the CIDv1 for it. `forDagCborBytes()` does the same for bytes that are already encoded:

```php
$cid = CID::forDagCbor(['text' => 'Hello']);

$cid = CID::forDagCborBytes($block);
```

The class also exposes the base58btc helpers it uses, `CID::encodeBase58()` and `CID::decodeBase58()`.
