<?php

declare(strict_types=1);

namespace SocialDept\AtpCbor\Crypto;

use Elliptic\EC;
use Elliptic\EC\KeyPair;
use SocialDept\AtpCbor\Core\CID;

/**
 * secp256k1 keypair for AT Protocol signing operations.
 *
 * Used to sign PLC operations and other cryptographic payloads.
 * Produces compact 64-byte low-S signatures compatible with the AT Protocol spec.
 */
class Secp256k1Keypair
{
    private EC $ec;

    private KeyPair $key;

    private function __construct(KeyPair $key)
    {
        $this->ec = new EC('secp256k1');
        $this->key = $key;
    }

    /**
     * Generate a new random keypair.
     */
    public static function create(): self
    {
        $ec = new EC('secp256k1');

        return new self($ec->genKeyPair());
    }

    /**
     * Import a keypair from a hex-encoded private key.
     */
    public static function fromHex(string $privateKeyHex): self
    {
        $ec = new EC('secp256k1');

        return new self($ec->keyFromPrivate($privateKeyHex, 'hex'));
    }

    /**
     * Import a keypair from raw private key bytes.
     */
    public static function fromBytes(string $privateKeyBytes): self
    {
        return self::fromHex(bin2hex($privateKeyBytes));
    }

    /**
     * Sign a message. Returns a compact 64-byte low-S signature.
     *
     * The message is SHA-256 hashed before signing, matching the AT Protocol spec.
     */
    public function sign(string $message): string
    {
        $hash = hash('sha256', $message, true);

        $signature = $this->key->sign(bin2hex($hash), ['canonical' => true]);

        $r = $signature->r->toString('hex', 64);
        $s = $signature->s->toString('hex', 64);

        return hex2bin($r . $s);
    }

    /**
     * Sign DAG-CBOR encoded data. Convenience for PLC operations.
     *
     * Encodes the data as DAG-CBOR, then signs the CBOR bytes.
     * Returns the base64url-encoded signature.
     */
    public function signDagCbor(array $data): string
    {
        $cbor = \SocialDept\AtpCbor\Core\CBOR::encode($data);
        $sig = $this->sign($cbor);

        return self::base64urlEncode($sig);
    }

    /**
     * Get the compressed public key bytes (33 bytes).
     */
    public function publicKeyBytes(): string
    {
        $hex = $this->key->getPublic(true, 'hex');

        return hex2bin($hex);
    }

    /**
     * Get the private key as hex string.
     */
    public function privateKeyHex(): string
    {
        return $this->key->getPrivate('hex');
    }

    /**
     * Get the private key as raw bytes.
     */
    public function privateKeyBytes(): string
    {
        return hex2bin($this->privateKeyHex());
    }

    /**
     * Format as did:key string.
     *
     * did:key:z + base58btc(0xe7 0x01 + compressed-public-key)
     */
    public function did(): string
    {
        // Multicodec prefix for secp256k1-pub
        $prefix = chr(0xe7) . chr(0x01);
        $prefixedKey = $prefix . $this->publicKeyBytes();

        return 'did:key:z' . CID::encodeBase58($prefixedKey);
    }

    /**
     * Verify a signature against a message.
     */
    public function verify(string $message, string $signature): bool
    {
        $hash = hash('sha256', $message, true);

        $r = substr($signature, 0, 32);
        $s = substr($signature, 32, 32);

        $sig = [
            'r' => bin2hex($r),
            's' => bin2hex($s),
        ];

        return $this->key->verify(bin2hex($hash), $sig);
    }

    /**
     * Base64url encode (no padding).
     */
    public static function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64url decode.
     */
    public static function base64urlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
