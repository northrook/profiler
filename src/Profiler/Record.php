<?php

declare(strict_types=1);

namespace Core\Profiler;

/**
 * @internal
 */
final readonly class Record
{
    public float $start;

    public float $stop;

    public float $delta;

    public ?int $startMemory;

    public ?int $stopMemory;

    public ?string $startNote;

    public ?string $stopNote;

    public function __construct(
        ?float       $microtime = null,
        ?string      $note = null,
        private bool $getMemoryUsage = false,
    ) {
        $this->start       = $microtime ?? \microtime( true );
        $this->startNote   = $note;
        $this->startMemory = $getMemoryUsage ? \memory_get_usage( true ) : null;
    }

    public function stop( ?float $microtime = null, ?string $note = null ) : static
    {
        if ( isset( $this->stop ) ) {
            \trigger_error( __METHOD__.' called on an already stopped entry.', E_USER_WARNING );
            return $this;
        }

        $this->stopMemory ??= $this->getMemoryUsage ? \memory_get_usage( true ) : null;

        $this->stop = $microtime ?? \microtime( true );

        $this->delta = $this->stop - $this->start;

        $this->stopNote = $note;

        return $this;
    }

    public function close() : static
    {
        return isset( $this->stop ) ? $this : $this->stop();
    }

    public function stopped() : bool
    {
        return isset( $this->stop );
    }
}
