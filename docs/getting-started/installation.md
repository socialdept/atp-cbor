# Installation

Part of the [atp-cbor docs](../introduction.md#documentation). Installing the package and the PHP extension it needs.

## Installing

```bash
composer require socialdept/atp-cbor
```

The package needs the `gmp` PHP extension, which it uses for base58 encoding and decoding.

## Configuration

There is nothing to configure. The package is plain PHP with no service provider, so its classes are ready to use once Composer has installed it.
