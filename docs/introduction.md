# Introduction

**ATP CBOR** reads and writes the binary formats of the AT Protocol in plain PHP. It has no Laravel dependency, and it is the binary layer under the Social Dept. packages that read the firehose and repositories.

- **CBOR:** decode RFC 8949 CBOR with DAG-CBOR's tag 42 for CID links, and encode values as deterministic DAG-CBOR.
- **CIDs:** parse CIDv0 and CIDv1 from strings or bytes, write them back, and compute the CID of DAG-CBOR data.
- **CAR archives:** read the blocks of a CAR, one at a time or as a map, and walk a repository's Merkle Search Tree to its records.
- **Record proofs:** check the structure of the CAR `com.atproto.sync.getRecord` returns, from the commit down to the record.
- **Signing:** secp256k1 keypairs with compact low-S signatures and `did:key` output, and the PLC operations that update or tombstone a `did:plc`.

The formats it follows:

- **CBOR**: [RFC 8949](https://www.rfc-editor.org/rfc/rfc8949), the Concise Binary Object Representation
- **DAG-CBOR**: the IPLD codec, with tag 42 for CID links
- **CAR**: the [Content Addressable aRchive](https://ipld.io/specs/transport/car/), version 1
- **CID**: [IPLD Content Identifiers](https://github.com/multiformats/cid), versions 0 and 1
- **Varint**: the unsigned variable-length integers that CAR and CID use

## A first look

Decode a CBOR item from a firehose frame. CID links come back as `CID` objects:

```php
use SocialDept\AtpCbor\Core\CBOR;

$value = CBOR::decode($data);
[$header, $rest] = CBOR::decodeFirst($frame);
```

Read a CAR archive's blocks and walk the records out of a repository's tree:

```php
use SocialDept\AtpCbor\CAR\RecordExtractor;
use SocialDept\AtpCbor\Core\CAR;

$blocks = CAR::blockMap($carData);

foreach ((new RecordExtractor($blocks, $did))->extractRecords($dataRoot) as $path => $record) {
    $record['uri'];     // at://did:plc:xyz/app.bsky.feed.post/3k4...
    $record['value'];   // the record, decoded
}
```

## Documentation

Start with [Installation](getting-started/installation.md), then [CBOR](formats/cbor.md). The docs are grouped by area, and each group has an index of its own:

- **[Getting started](getting-started/README.md)**: [Installation](getting-started/installation.md)
- **[Formats](formats/README.md)**: [CBOR](formats/cbor.md), [CIDs](formats/cids.md), [Binary reading](formats/binary.md)
- **[Repositories](repositories/README.md)**: [CAR archives](repositories/car.md), [Record proofs](repositories/proofs.md)
- **[Signing](signing/README.md)**: [Keys](signing/keys.md), [PLC operations](signing/plc-operations.md)
- **[API reference](reference.md)**: The public classes and their main methods.
