# API reference

Part of the [atp-cbor docs](introduction.md#documentation). The public classes and their main methods.

## Core

| Class | Method | Description |
|-------|--------|-------------|
| `CBOR` | `decode(string $data)` | Decode one CBOR item |
| `CBOR` | `decodeFirst(string $data)` | Decode the first item, and return it with the bytes after it |
| `CBOR` | `decodeAll(string $data)` | Decode every item in a sequence |
| `CBOR` | `encode(mixed $data)` | Encode a value as DAG-CBOR |
| `CAR` | `blockMap(string $data, ?string $did = null)` | Read a CAR into a map of CID string to block bytes. `$did` is unused |
| `CID` | `fromString(string $str)` | Parse a base32 or base58btc CID |
| `CID` | `fromBinary(string $data)` | Parse a binary CID |
| `CID` | `forDagCbor(mixed $data)` | The CIDv1 of a value encoded as DAG-CBOR |
| `CID` | `forDagCborBytes(string $bytes)` | The CIDv1 of DAG-CBOR bytes |
| `CID` | `toString()`, `toBinary()` | Write the CID back as a string or bytes |

All three live in `SocialDept\AtpCbor\Core`.

## Readers

| Class | Description |
|-------|-------------|
| `CAR\BlockReader` | Reads a CAR's blocks through a generator, or into a map with `getBlockMap()` |
| `CAR\RecordExtractor` | Walks a repository's Merkle Search Tree to its records |
| `CBOR\Decoder` | The RFC 8949 decoder, with DAG-CBOR CID links |
| `CBOR\Encoder` | The deterministic DAG-CBOR encoder |
| `Binary\Reader` | Reads a binary string from the front, tracking its position |
| `Binary\Varint` | Decodes unsigned varints |

## Proofs

| Class | Description |
|-------|-------------|
| `Proof\RecordProof` | Checks a `getRecord` proof's structure, down to the record |
| `Proof\ProvenRecord` | A record whose place in a repo a proof established |
| `Proof\ProofException` | Thrown when a proof does not hold |

## Signing

| Class | Description |
|-------|-------------|
| `Crypto\Secp256k1Keypair` | A secp256k1 keypair that signs, verifies and formats `did:key` strings |
| `Crypto\PlcOperation` | Builds and signs PLC operations |

Class names here are relative to `SocialDept\AtpCbor`.
