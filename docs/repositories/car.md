# CAR archives

Part of the [atp-cbor docs](../introduction.md#documentation). Reading the blocks of a CAR and walking a repository's tree to its records.

## Reading blocks

A CAR (Content Addressable aRchive) holds blocks of data, each under its CID. `CAR::blockMap()` reads them all into an array of CID string to block bytes. The bytes are left encoded:

```php
use SocialDept\AtpCbor\Core\CAR;

$blocks = CAR::blockMap($carData);
```

`blockMap()` also takes a `$did` as its second argument. It is not used, and is kept for compatibility.

To handle one block at a time without building the map, use `BlockReader`. Its `blocks()` generator yields each block's `CID` object with its bytes:

```php
use SocialDept\AtpCbor\CAR\BlockReader;

$reader = new BlockReader($carData);

foreach ($reader->blocks() as [$cid, $blockData]) {
    // $cid is a CID, $blockData the block's bytes
}
```

`BlockReader` skips the CAR header. To read the header's roots, decode it yourself: it is a varint length followed by a CBOR map.

```php
use SocialDept\AtpCbor\Binary\Reader;
use SocialDept\AtpCbor\Core\CBOR;

$reader = new Reader($carData);
$header = CBOR::decode($reader->readBytes($reader->readVarint()));

$root = $header['roots'][0];   // a CID, the commit for a repository CAR
```

## Extracting records

`RecordExtractor` walks a repository's Merkle Search Tree through a block map and yields each record under its `collection/rkey` path. Start it at the tree's root, which is the `data` field of the repository's commit:

```php
use SocialDept\AtpCbor\CAR\RecordExtractor;
use SocialDept\AtpCbor\Core\CBOR;

$commit = CBOR::decode($blocks[$root->toString()]);

$extractor = new RecordExtractor($blocks, 'did:plc:xyz');

foreach ($extractor->extractRecords($commit['data']) as $path => $record) {
    echo $record['uri'];       // at://did:plc:xyz/app.bsky.feed.post/3k4...
    echo $record['cid'];       // bafyrei...
    print_r($record['value']); // the record, decoded
}
```

The DID is only used to build each record's `uri`. Tree nodes and records missing from the block map are skipped, so a partial CAR, such as a firehose commit's, yields the records it holds.
