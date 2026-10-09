<?php
declare(strict_types=1);

namespace App\Identity;

/**
 * Minimal identity stub implementing the getIdentifier() contract expected by
 * the plugin, without depending on cakephp/authentication.
 */
class TestIdentity
{
    /**
     * @param string|int $id Identity identifier.
     */
    public function __construct(private string|int $id)
    {
    }

    /**
     * @return string|int
     */
    public function getIdentifier(): string|int
    {
        return $this->id;
    }
}
