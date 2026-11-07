# Keys

Part of the [atp-cbor docs](../introduction.md#documentation). secp256k1 keypairs, signatures and `did:key` strings.

`Secp256k1Keypair` holds a secp256k1 private key. Create a new one, or import one from hex or raw bytes:

```php
use SocialDept\AtpCbor\Crypto\Secp256k1Keypair;

$keypair = Secp256k1Keypair::create();
$keypair = Secp256k1Keypair::fromHex($privateKeyHex);
$keypair = Secp256k1Keypair::fromBytes($privateKeyBytes);

$keypair->privateKeyHex();
$keypair->privateKeyBytes();
$keypair->publicKeyBytes();   // the compressed public key, 33 bytes
$keypair->did();              // did:key:z...
```

## Signing and verifying

`sign()` hashes the message with SHA-256 and returns a compact 64-byte low-S signature, the form the AT Protocol uses. `verify()` checks one against a message:

```php
$signature = $keypair->sign($message);

$keypair->verify($message, $signature);   // true
```

`signDagCbor()` encodes an array as DAG-CBOR, signs those bytes and returns the signature base64url encoded without padding. It is what PLC operations are signed with:

```php
$sig = $keypair->signDagCbor($operation);
```

`Secp256k1Keypair::base64urlEncode()` and `base64urlDecode()` are public for converting signatures to and from that form.
