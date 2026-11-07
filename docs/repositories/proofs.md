# Record proofs

Part of the [atp-cbor docs](../introduction.md#documentation). Checking the CAR `com.atproto.sync.getRecord` returns, from the commit down to the record.

`com.atproto.sync.getRecord` returns a record with its proof: the repo's signed commit and the tree nodes on the path to the record, as a CAR. `RecordProof` checks the proof's structure. Every block must be a SHA-256 DAG-CBOR CIDv1 that hashes to its CID, the CAR must have one root and that root must be a version 3 commit, and walking the tree from the commit's data root must reach the record at its key:

```php
use SocialDept\AtpCbor\Proof\ProofException;
use SocialDept\AtpCbor\Proof\RecordProof;

try {
    $proven = RecordProof::verify($car, 'app.bsky.graph.verification', $rkey, did: $issuerDid);
} catch (ProofException $e) {
    // A block that does not match its CID, a commit for another repo,
    // or a key the proof does not reach.
}

$proven->record;          // The record, decoded
$proven->cid;             // Its CID
$proven->commit;          // The commit's CID
$proven->rev;             // The commit's revision
$proven->signature;       // The commit's signature bytes
$proven->unsignedCommit;  // The bytes that signature covers
$proven->uri();           // at://{did}/{collection}/{rkey}
```

The `did` argument is optional. When it is given, a proof for any other repo is rejected. The `ProvenRecord` also carries the `did`, `collection` and `rkey` it was proven for.

> [!IMPORTANT]
> `RecordProof` does not check the commit's signature, which needs the repo's signing key from its DID document. Verify `$proven->signature` over `$proven->unsignedCommit` with that key before trusting that the repo published the record.
