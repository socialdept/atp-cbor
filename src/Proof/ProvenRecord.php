<?php

namespace SocialDept\AtpCbor\Proof;

use SocialDept\AtpCbor\Core\CID;

/**
 * A record whose place in a repo a proof established: every block's bytes
 * match its CID, and the commit's tree leads to the record at its key.
 *
 * The commit's signature is not checked here. Check it against the repo's
 * signing key, over $unsignedCommit, before trusting the repo said this.
 */
final class ProvenRecord
{
    /**
     * @param  array<string, mixed>  $record
     */
    public function __construct(
        public readonly string $did,
        public readonly string $collection,
        public readonly string $rkey,
        public readonly CID $cid,
        public readonly array $record,
        public readonly CID $commit,
        public readonly string $rev,
        public readonly string $signature,
        public readonly string $unsignedCommit,
    ) {
    }

    public function uri(): string
    {
        return "at://{$this->did}/{$this->collection}/{$this->rkey}";
    }
}
