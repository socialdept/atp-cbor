[![CBOR Header](./header.png)](https://github.com/socialdept/atp-cbor)

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

This package reads and writes the binary formats of the AT Protocol in plain PHP, with no Laravel dependency. It decodes and encodes DAG-CBOR, reads CAR archives and the records in a repository's tree, parses CIDs, checks `getRecord` proofs, and signs PLC operations with secp256k1 keys. To learn all about it, head over to [the documentation](https://socialde.pt/docs/atp-cbor).

Here are a few short examples of what you can do. Decode CBOR from a firehose event:

```php
use SocialDept\AtpCbor\Core\CBOR;

$decoded = CBOR::decode($binaryData);
```

Read the blocks of a CAR archive into a map of CID to bytes:

```php
use SocialDept\AtpCbor\Core\CAR;

$blocks = CAR::blockMap($carData);
```

Parse a CID and turn it back into a string:

```php
use SocialDept\AtpCbor\Core\CID;

$cid = CID::fromString('bafyreie5cvv4h45feadgeuwhbcutmh6t7ceseocckahdoe6uat64zmz454');
$cid->version;    // 1
$cid->toString();
```

Check that a record from `com.atproto.sync.getRecord` sits where its repo's commit says:

```php
use SocialDept\AtpCbor\Proof\RecordProof;

$proven = RecordProof::verify($car, 'app.bsky.graph.verification', $rkey, did: $issuerDid);
$proven->record;
```

## Documentation

You'll find the documentation on [https://socialde.pt/docs/atp-cbor](https://socialde.pt/docs/atp-cbor).

Find yourself stuck using the package? Found a bug? Do you have general questions or suggestions for improving it? Feel free to [create an issue on GitHub](https://github.com/socialdept/atp-cbor/issues).

## Testing

```bash
vendor/bin/phpunit
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
