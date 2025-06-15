<?php

declare(strict_types=1);

namespace Core\Profiler;

/**
 * @internal
 */
final readonly class Snapshot
{
    public float $microtime;

    public ?int $memory;

    public function __construct(
        ?float         $microtime = null,
        public ?string $note = null,
        bool           $memory = false,
    ) {
        $this->microtime = $microtime ?? \microtime( true );
        $this->memory    = $memory
                ? \memory_get_usage( true )
                : null;
    }
}
