# PLC operations

Part of the [atp-cbor docs](../introduction.md#documentation). Building and signing the operations that change or tombstone a `did:plc`.

A `did:plc` document changes through signed operations, each pointing at the one before it by CID. `PlcOperation` builds the next unsigned operation from the last one in the DID's PLC log, then signs it:

```php
use SocialDept\AtpCbor\Crypto\PlcOperation;
use SocialDept\AtpCbor\Crypto\Secp256k1Keypair;

$unsigned = PlcOperation::updateServiceEndpoint($lastOp, 'https://pds.example.com');

$signed = PlcOperation::sign($unsigned, Secp256k1Keypair::fromHex($rotationKeyHex));
```

The builders take the last operation as it appears in the log, signature included:

- `updateServiceEndpoint($lastOp, $newEndpoint)` sets the `atproto_pds` service endpoint.
- `updateHandle($lastOp, $newHandle)` replaces the `at://` entry in `alsoKnownAs`, adding the `at://` prefix when the handle lacks it.
- `updateRotationKeys($lastOp, $newRotationKeys)` replaces the rotation keys with a list of `did:key` strings.
- `tombstone($lastOp)` builds a `plc_tombstone`, which has only `type` and `prev` and deactivates the DID for good.

Each sets `prev` to the CID of the last operation. `sign()` adds a `sig` field, signed with the keypair's `signDagCbor()`. The signing key must be one of the DID's current rotation keys for the PLC directory to accept the operation.

Two helpers are public as well. `computePrev($operation)` returns the CID string of an operation, and `normalize($op)` strips its `sig` and converts a legacy v1 operation (`signingKey`, `recoveryKey`, `handle`, `service`) to the current `plc_operation` shape.
