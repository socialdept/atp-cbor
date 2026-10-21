<?php

namespace SocialDept\AtpCbor\Tests\Unit;

use Elliptic\EC;
use PHPUnit\Framework\TestCase;
use SocialDept\AtpCbor\Binary\Reader;
use SocialDept\AtpCbor\Core\CBOR;
use SocialDept\AtpCbor\Core\CID;
use SocialDept\AtpCbor\Proof\ProofException;
use SocialDept\AtpCbor\Proof\RecordProof;

/**
 * Record proofs as a PDS serves them from com.atproto.sync.getRecord. The
 * fixtures are public records of did:plc:eob75vcjtmbaef2tn4evc4sl:
 * its profile, app.bsky.actor.profile/self, and a status,
 * xyz.statusphere.status/3my5ep45gyc2z, fetched from its PDS on 2026-10-21.
 */
class RecordProofTest extends TestCase
{
    private const DID = 'did:plc:eob75vcjtmbaef2tn4evc4sl';
    private const STATUS = '3my5ep45gyc2z';
    // The repo's #atproto key in its DID document, a compressed secp256k1 point.
    private const KEY = 'zQ3shTquy1Z17A2xuEhbCdPZEfASJL6W7jSoCeEyfT685GKPz';

    private function car(string $name): string
    {
        return (string) file_get_contents(__DIR__."/../Fixtures/proof/{$name}.car");
    }

    public function test_a_proof_establishes_the_record_at_its_key(): void
    {
        $proven = RecordProof::verify($this->car('profile'), 'app.bsky.actor.profile', 'self', self::DID);

        $this->assertSame(self::DID, $proven->did);
        $this->assertSame('at://'.self::DID.'/app.bsky.actor.profile/self', $proven->uri());
        $this->assertSame('app.bsky.actor.profile', $proven->record['$type']);
        $this->assertSame(CID::forDagCbor($proven->record)->toString(), $proven->cid->toString(), 'The record re-encodes to its CID.');
        $this->assertNotSame('', $proven->rev);
        $this->assertSame(64, strlen($proven->signature));

        $status = RecordProof::verify($this->car('status'), 'xyz.statusphere.status', self::STATUS, self::DID);
        $this->assertSame('xyz.statusphere.status', $status->record['$type']);
    }

    public function test_the_unsigned_commit_is_what_the_repo_signed(): void
    {
        $proven = RecordProof::verify($this->car('profile'), 'app.bsky.actor.profile', 'self');

        $point = substr(CID::decodeBase58(substr(self::KEY, 1)), 2);
        $key = (new EC('secp256k1'))->keyFromPublic(bin2hex($point), 'hex');

        $this->assertTrue($key->verify(hash('sha256', $proven->unsignedCommit), [
            'r' => bin2hex(substr($proven->signature, 0, 32)),
            's' => bin2hex(substr($proven->signature, 32)),
        ]));
        $this->assertFalse($key->verify(hash('sha256', $proven->unsignedCommit.'x'), [
            'r' => bin2hex(substr($proven->signature, 0, 32)),
            's' => bin2hex(substr($proven->signature, 32)),
        ]));
    }

    public function test_a_key_the_proof_does_not_reach_is_refused(): void
    {
        $this->expectException(ProofException::class);
        $this->expectExceptionMessage('does not reach');

        RecordProof::verify($this->car('profile'), 'app.bsky.actor.profile', 'other');
    }

    public function test_a_proof_for_another_repo_is_refused(): void
    {
        $this->expectException(ProofException::class);
        $this->expectExceptionMessage('not did:plc:someone');

        RecordProof::verify($this->car('profile'), 'app.bsky.actor.profile', 'self', 'did:plc:someone');
    }

    public function test_a_tampered_block_is_refused(): void
    {
        $car = $this->car('profile');
        // Flip one byte in the last block, the record or a node.
        $car[strlen($car) - 5] = chr(ord($car[strlen($car) - 5]) ^ 0x01);

        $this->expectException(ProofException::class);
        $this->expectExceptionMessage('does not match its bytes');

        RecordProof::verify($car, 'app.bsky.actor.profile', 'self');
    }

    public function test_a_missing_record_block_is_refused(): void
    {
        $car = $this->car('profile');
        $record = RecordProof::verify($car, 'app.bsky.actor.profile', 'self')->cid->toBinary();

        // Rebuild the CAR without the record's block.
        $reader = new Reader($car);
        $reader->readBytes($reader->readVarint());
        $start = $reader->getPosition();
        $kept = substr($car, 0, $start);

        while ($reader->hasMore()) {
            $from = $reader->getPosition();
            $block = $reader->readBytes($reader->readVarint());

            if (! str_starts_with($block, $record)) {
                $kept .= substr($car, $from, $reader->getPosition() - $from);
            }
        }

        $this->assertLessThan(strlen($car), strlen($kept));
        $this->expectException(ProofException::class);
        $this->expectExceptionMessage('missing the record block');

        RecordProof::verify($kept, 'app.bsky.actor.profile', 'self');
    }

    /**
     * A CAR of these blocks, each hashed into its CID, with these roots.
     *
     * @param  array<int, string>  $blocks  Encoded blocks
     * @param  array<int, CID>|null  $roots  The first block when null
     */
    private function synthetic(array $blocks, ?array $roots = null): string
    {
        $cids = array_map(fn (string $block) => CID::forDagCborBytes($block), $blocks);
        $header = CBOR::encode(['version' => 1, 'roots' => $roots ?? [$cids[0]]]);
        $car = self::varint(strlen($header)).$header;

        foreach ($blocks as $i => $block) {
            $car .= self::varint(strlen($cids[$i]->toBinary().$block)).$cids[$i]->toBinary().$block;
        }

        return $car;
    }

    private static function varint(int $value): string
    {
        $bytes = '';

        do {
            $byte = $value & 0x7F;
            $value >>= 7;
            $bytes .= chr($value > 0 ? $byte | 0x80 : $byte);
        } while ($value > 0);

        return $bytes;
    }

    /**
     * A one-record repo: commit, a tree node holding a/b, and the record.
     *
     * @return array<int, string>
     */
    private function repo(int $version = 3): array
    {
        $record = CBOR::encode(['$type' => 'a', 'text' => 'hi']);
        $node = CBOR::encode(['l' => null, 'e' => [['p' => 0, 'k' => 'a/b', 'v' => CID::forDagCborBytes($record), 't' => null]]]);
        $commit = CBOR::encode(['did' => 'did:plc:test', 'version' => $version, 'data' => CID::forDagCborBytes($node), 'rev' => '3l2aaaaaaaaaa', 'prev' => null, 'sig' => str_repeat('s', 64)]);

        return [$commit, $node, $record];
    }

    public function test_a_synthetic_proof_holds_and_a_wrong_commit_version_does_not(): void
    {
        $this->assertSame('hi', RecordProof::verify($this->synthetic($this->repo()), 'a', 'b')->record['text']);

        $this->expectException(ProofException::class);
        $this->expectExceptionMessage('version 2, not 3');

        RecordProof::verify($this->synthetic($this->repo(2)), 'a', 'b');
    }

    public function test_a_car_without_exactly_one_root_is_refused(): void
    {
        $blocks = $this->repo();
        $commit = CID::forDagCborBytes($blocks[0]);

        foreach ([[], [$commit, $commit]] as $roots) {
            try {
                RecordProof::verify($this->synthetic($blocks, $roots), 'a', 'b');
                $this->fail(count($roots).' roots were accepted.');
            } catch (ProofException $e) {
                $this->assertStringContainsString('one root', $e->getMessage());
            }
        }
    }
}
