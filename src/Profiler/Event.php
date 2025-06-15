<?php

namespace Core\Profiler;

use Core\Contracts\Profiler\ProfilerEvent;
use Stringable;

/**
 * @internal
 */
final class Event extends ProfilerEvent implements Stringable
{
    /** @var Record[] */
    protected array $records = [];

    /** @var Snapshot[] */
    protected array $snapshots = [];

    public function __construct(
        string                $name,
        string                $category,
        private readonly bool $memory,
    ) {
        parent::__construct( $name, $category );
    }

    public function start( ?string $note = null ) : static
    {
        $this->records[] = new Record( \microtime( true ), $note, $this->memory );
        return $this;
    }

    public function stop( ?string $note = null ) : static
    {
        if ( empty( $this->records ) ) {
            \trigger_error( __METHOD__.' called with no started events.' );
        }
        else {
            \end( $this->records )->stop( \microtime( true ), $note );
        }
        return $this;
    }

    public function snapshot( ?string $note = null ) : static
    {
        $this->snapshots[] = new Snapshot( \microtime( true ), $note, $this->memory );
        return $this;
    }

    public function isRunning() : bool
    {
        return empty( $this->records )
                ? false
                : \end( $this->records )->stopped();
    }

    public function stopAll() : static
    {
        foreach ( $this->records as $record ) {
            $record->close();
        }
        return $this;
    }

    /**
     * @return Record[]|Snapshot[]
     */
    public function getAll() : array
    {
        return $this->records + $this->snapshots;
    }

    /**
     * @return Record[]
     */
    public function getRecords() : array
    {
        return $this->records;
    }

    /**
     * @return Snapshot[]
     */
    public function getSnapshots() : array
    {
        return $this->snapshots;
    }

    /**
     * Gets the {@see microtime} float of the first {@see Record}.
     *
     * @return ?float returns `null` if not started
     */
    public function getStartTime() : ?float
    {
        return empty( $this->records ) ? null : $this->records[0]->start;
    }

    /**
     * Gets the {@see microtime} float of the first {@see Record}.
     *
     * @return ?float returns `null` if not started
     */
    public function getEndTime() : ?float
    {
        return empty( $this->records ) ? null : \end( $this->records )->stop;
    }

    public function getElapsedTime() : ?float
    {
        $start = $this->getStartTime();
        $stop  = $this->getEndTime();

        if ( ! $start || ! $stop ) {
            return null;
        }
        return $stop - $start;
    }

    /**
     * Gets the duration of the events in milliseconds (including all periods).
     */
    public function getDuration() : int|float
    {
        $total = 0;

        foreach ( $this->records as $record ) {
            if ( ! isset( $record->delta ) ) {
                \trigger_error(
                    __METHOD__." called before closing the '"
                        .( $this->category ? "{$this->category}::" : '' )
                        .$this->name."' event.",
                    E_USER_WARNING,
                );

                continue;
            }
            $total += $record->delta;
        }

        return $total;
    }

    /**
     * Gets the max memory usage of all periods in bytes.
     */
    public function getMemory() : int
    {
        $memory = 0;

        foreach ( $this->records as $record ) {
            if ( ! isset( $record->stopMemory ) ) {
                \trigger_error(
                    __METHOD__." called before closing the '"
                        .( $this->category ? "{$this->category}::" : '' )
                        .$this->name."' event.",
                    E_USER_WARNING,
                );

                continue;
            }

            if ( $record->stopMemory > $memory ) {
                $memory = $record->stopMemory;
            }
        }

        return $memory;
    }

    public function __toString() : string
    {
        return \sprintf(
            '%s/%s: %.2F MiB - %d ms',
            $this->category,
            $this->name,
            $this->getMemory() / 1_024 / 1_024,
            $this->getDuration(),
        );
    }
}
