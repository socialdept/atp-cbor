# Binary reading

Part of the [atp-cbor docs](../introduction.md#documentation). The `Reader` and `Varint` classes for custom binary parsing.

`Reader` reads a binary string from the front, tracking its position:

```php
use SocialDept\AtpCbor\Binary\Reader;

$reader = new Reader($data);

$byte = $reader->readByte();        // one byte, as an int
$chunk = $reader->readBytes(32);    // exactly 32 bytes
$varint = $reader->readVarint();    // an unsigned varint

$reader->peek();            // the next byte, without moving
$reader->skip(4);
$reader->hasMore();
$reader->remaining();       // bytes left
$reader->getPosition();
$reader->readRemaining();   // everything left, moving to the end
```

Reading past the end throws a `RuntimeException`.

`Varint` decodes the unsigned variable-length integers CAR and CID use. `decode()` takes the offset by reference and moves it past the varint, and `decodeFirst()` returns the value with the bytes after it:

```php
use SocialDept\AtpCbor\Binary\Varint;

$offset = 0;
$value = Varint::decode($data, $offset);

[$value, $rest] = Varint::decodeFirst($data);
```

A varint longer than 64 bits, or data that ends inside one, throws a `RuntimeException`.
