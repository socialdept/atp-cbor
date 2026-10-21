<?php

namespace SocialDept\AtpCbor\Proof;

use RuntimeException;

/**
 * A record proof that does not hold: a block whose bytes do not match its
 * CID, a missing block, a commit for another repo, or a key the proof does
 * not reach.
 */
class ProofException extends RuntimeException
{
}
