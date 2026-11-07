# CBOR

Part of the [atp-cbor docs](../introduction.md#documentation). Decoding CBOR and DAG-CBOR, and encoding values as deterministic DAG-CBOR.

## Decoding

The `CBOR` class has three methods for decoding:

```php
use SocialDept\AtpCbor\Core\CBOR;

// Decode a single CBOR item
$value = CBOR::decode($data);

// Decode the first item and get the bytes after it
[$value, $remaining] = CBOR::decodeFirst($data);

// Decode every item in a sequence
$items = CBOR::decodeAll($data);
```

`decodeFirst()` suits a firehose frame, which is a header item followed by a body item.

The decoder handles every CBOR major type: unsigned and negative integers, byte strings, text strings, arrays, maps, tagged values and the simple values (booleans, null, and half, single and double precision floats). Maps become PHP arrays keyed by their string or integer keys. Tag 42, DAG-CBOR's CID link, becomes a [`CID`](cids.md) object. Any other tag is dropped and its value returned as it is.

Byte strings and text strings both decode to PHP strings, so the result does not say which one an item was. Indefinite-length items are not supported, and malformed data throws a `RuntimeException`.

## Encoding

`CBOR::encode()` writes a value as DAG-CBOR, following the canonical rules, so the same value always gives the same bytes:

```php
use SocialDept\AtpCbor\Core\CBOR;
use SocialDept\AtpCbor\Core\CID;

$bytes = CBOR::encode([
    'text' => 'Hello',
    'count' => 3,
    'link' => CID::fromString('bafyreie5cvv4h45feadgeuwhbcutmh6t7ceseocckahdoe6uat64zmz454'),
]);
```

- Map keys are sorted by length first, then byte by byte.
- Integers use the fewest bytes that hold them.
- Floats are written as 64-bit doubles.
- `CID` objects are written as tag 42 links.
- An array with keys `0` to `n - 1` in order is written as a CBOR array, and any other array, or an object, as a map. An empty array is written as an empty CBOR array.

PHP strings are always written as text strings, so `encode()` cannot write a byte string. A value it cannot encode, such as a resource, throws a `RuntimeException`.

The `CBOR` class wraps `SocialDept\AtpCbor\CBOR\Decoder` and `SocialDept\AtpCbor\CBOR\Encoder`, which can be used directly. A `Decoder` reads items one at a time with `decode()`, `hasMore()` and `getPosition()`.
