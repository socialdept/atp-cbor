<?php

namespace SocialDept\AtpCbor\Proof;

use SocialDept\AtpCbor\Binary\Reader;
use SocialDept\AtpCbor\CAR\BlockReader;
use SocialDept\AtpCbor\Core\CBOR;
use SocialDept\AtpCbor\Core\CID;
use Throwable;

/**
 * Checks the structure of a record proof: the CAR com.atproto.sync.getRecord
 * returns, holding a repo's signed commit, the tree nodes on the path to one
 * key, and the record.
 *
 * It establishes that the record is at that key under that commit: every
 * block's bytes hash to its CID, the CAR's root is the commit, and walking
 * the tree from the commit's data root reaches the record's CID. It does not
 * check the commit's signature, which needs the repo's key from its DID
 * document. ProvenRecord::$unsignedCommit holds the bytes that signature
 * covers.
 */
final class RecordProof
{
    /**
     * Multicodec codes for the blocks a proof holds.
     */
    private const DAG_CBOR = 0x71;

    private const SHA2_256 = 0x12;

    /**
     * @param  string|null  $did  The repo the proof must be for, when known
     *
     * @throws ProofException
     */
    public static function verify(string $car, string $collection, string $rkey, ?string $did = null): ProvenRecord
    {
        $root = self::root($car);
        $blocks = self::blocks($car);

        $commit = self::decodeMap($blocks, $root, 'commit');

        foreach (['did' => 'is_string', 'rev' => 'is_string', 'version' => 'is_int', 'sig' => 'is_string'] as $field => $check) {
            if (! $check($commit[$field] ?? null)) {
                throw new ProofException("The commit has no valid {$field}.");
            }
        }

        if ($commit['version'] !== 3) {
            throw new ProofException("The commit is version {$commit['version']}, not 3.");
        }

        if (! ($commit['data'] ?? null) instanceof CID) {
            throw new ProofException('The commit has no data root.');
        }

        if ($did !== null && $commit['did'] !== $did) {
            throw new ProofException("The proof is for {$commit['did']}, not {$did}.");
        }

        $key = "{$collection}/{$rkey}";
        $recordCid = self::find($blocks, $commit['data'], $key);

        if ($recordCid === null) {
            throw new ProofException("The proof does not reach {$key}.");
        }

        return new ProvenRecord(
            did: $commit['did'],
            collection: $collection,
            rkey: $rkey,
            cid: $recordCid,
            record: self::decodeMap($blocks, $recordCid, 'record'),
            commit: $root,
            rev: $commit['rev'],
            signature: $commit['sig'],
            // What the signature covers: the commit without its signature,
            // every remaining field text, an integer, a CID or null.
            unsignedCommit: CBOR::encode(array_diff_key($commit, ['sig' => true])),
        );
    }

    /**
     * The CAR's one root, the commit.
     */
    private static function root(string $car): CID
    {
        try {
            $reader = new Reader($car);
            $header = CBOR::decode($reader->readBytes($reader->readVarint()));
        } catch (Throwable $e) {
            throw new ProofException('The CAR header cannot be read: '.$e->getMessage());
        }

        $roots = is_array($header) ? ($header['roots'] ?? null) : null;

        if (($header['version'] ?? null) !== 1 || ! is_array($roots) || count($roots) !== 1 || ! $roots[0] instanceof CID) {
            throw new ProofException('A proof is a version 1 CAR with one root.');
        }

        return $roots[0];
    }

    /**
     * Every block, by CID, each checked against its bytes.
     *
     * @return array<string, string>
     */
    private static function blocks(string $car): array
    {
        $blocks = [];

        try {
            foreach ((new BlockReader($car))->blocks() as [$cid, $bytes]) {
                if ($cid->version !== 1 || $cid->codec !== self::DAG_CBOR || ord($cid->hash[0] ?? "\0") !== self::SHA2_256) {
                    throw new ProofException("Block {$cid} is not a sha2-256 dag-cbor CIDv1.");
                }

                if (! hash_equals($cid->hash, CID::forDagCborBytes($bytes)->hash)) {
                    throw new ProofException("Block {$cid} does not match its bytes.");
                }

                $blocks[$cid->toString()] = $bytes;
            }
        } catch (ProofException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ProofException('The CAR blocks cannot be read: '.$e->getMessage());
        }

        return $blocks;
    }

    /**
     * The CID a key holds, walking the tree from a node down the one path
     * that can hold it, or null when the key is not in the tree.
     *
     * @param  array<string, string>  $blocks
     */
    private static function find(array $blocks, CID $node, string $key): ?CID
    {
        $visited = [];

        while (true) {
            if (isset($visited[$node->toString()])) {
                throw new ProofException('The tree has a cycle.');
            }

            $visited[$node->toString()] = true;

            // A proof holds only the nodes on its own key's path, so another
            // key can lead to a node it does not include.
            if (! isset($blocks[$node->toString()])) {
                throw new ProofException("The proof does not reach {$key}: it lacks tree node {$node}.");
            }

            $data = self::decodeMap($blocks, $node, 'tree node');
            $entries = $data['e'] ?? null;

            if (! is_array($entries)) {
                throw new ProofException("Tree node {$node} has no entries.");
            }

            // Keys smaller than the first entry's live under l, and those
            // between an entry and the next under that entry's t.
            $next = $data['l'] ?? null;
            $previous = '';

            foreach ($entries as $entry) {
                if (! is_array($entry) || ! is_int($entry['p'] ?? null) || ! is_string($entry['k'] ?? null) || ! ($entry['v'] ?? null) instanceof CID) {
                    throw new ProofException("Tree node {$node} has a malformed entry.");
                }

                $entryKey = substr($previous, 0, $entry['p']).$entry['k'];
                $previous = $entryKey;
                $order = strcmp($key, $entryKey);

                if ($order === 0) {
                    return $entry['v'];
                }

                if ($order < 0) {
                    break;
                }

                $next = $entry['t'] ?? null;
            }

            if (! $next instanceof CID) {
                return null;
            }

            $node = $next;
        }
    }

    /**
     * A block decoded as a map.
     *
     * @param  array<string, string>  $blocks
     * @return array<string, mixed>
     */
    private static function decodeMap(array $blocks, CID $cid, string $what): array
    {
        $bytes = $blocks[$cid->toString()] ?? null;

        if ($bytes === null) {
            throw new ProofException("The proof is missing the {$what} block {$cid}.");
        }

        try {
            $value = CBOR::decode($bytes);
        } catch (Throwable $e) {
            throw new ProofException("The {$what} block {$cid} cannot be decoded: ".$e->getMessage());
        }

        if (! is_array($value)) {
            throw new ProofException("The {$what} block {$cid} is not a map.");
        }

        return $value;
    }
}
