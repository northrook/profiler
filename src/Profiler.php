<?php

declare(strict_types=1);

namespace Core;

use Core\Contracts\Profiler\ProfilerEvent;
use Core\Contracts\ProfilerInterface;
use Core\Profiler\Event;

final class Profiler implements ProfilerInterface
{
    public const string DEFAULT_CATEGORY = '_events';

    protected readonly float $createdAt;

    protected readonly float $closedAt;

    /** @var array<string, array<string, Event>> */
    protected array $events = [
        Profiler::DEFAULT_CATEGORY => [],
    ];

    /** @var null|non-empty-string */
    protected ?string $category = null;

    /**
     * @param null|non-empty-string $category
     * @param bool                  $disabled
     * @param bool                  $getMemoryUsage
     *
     * @return void
     */
    public function __construct(
        ?string        $category = null,
        protected bool $disabled = false,
        protected bool $getMemoryUsage = false,
    ) {
        $this->createdAt = \microtime( true );
        $this->setCategory( $category );
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * Retrieve or create a {@see ProfilerEvent} by name and optional category.
     *
     * - The event is started on instantiation.
     * - `null` if the profiler is `disabled`.
     *
     * @param non-empty-string      $name     the name of the event to retrieve
     * @param null|non-empty-string $category an optional category for the event
     *
     * @return null|ProfilerEvent
     */
    public function __invoke(
        string  $name,
        ?string $category = null,
    ) : ?ProfilerEvent {
        return $this->event( $name, $category );
    }

    /**
     * @inheritDoc
     */
    public function event(
        string  $name,
        ?string $category = null,
    ) : ?ProfilerEvent {
        if ( $this->disabled ) {
            return null;
        }

        // ?? $category === [null] use callstack to retrieve origin?

        $name     = $this->name( $name );
        $category = $this->category( $category );

        return $this->events[$category][$name] ??= new Event( $name, $category, $this->getMemoryUsage );
    }

    /**
     * @inheritDoc
     */
    public function start(
        string  $name,
        ?string $category = null,
    ) : ?ProfilerEvent {
        return $this->event( $name, $category )?->start();
    }

    /**
     * Stops an ongoing stopwatch event by name, or all events in the given category.
     *
     * @param null|non-empty-string $name     optional name of the event to stop
     * @param null|non-empty-string $category optional category name to filter or group events
     *
     * @return void
     */
    public function stop(
        ?string $name = null,
        ?string $category = null,
    ) : void {
        if ( $name && $category ) {
            $this->event( $name, $category )?->stop();
        }
        elseif ( $name ) {
            foreach ( $this->events as $events ) {
                if ( \array_key_exists( $name, $events ) ) {
                    $events[$name]->stop();
                }
            }
        }
        elseif ( $category ) {
            foreach ( $this->events[$this->category( $category )] ?? [] as $event ) {
                $event->stop();
            }
        }
    }

    /**
     * Checks if the functionality is currently enabled.
     *
     * @return bool true if enabled, false otherwise
     */
    public function isEnabled() : bool
    {
        return $this->disabled === false;
    }

    /**
     * Enables the {@see Profiler} globally.
     *
     * @return $this
     */
    public function enable() : static
    {
        $this->disabled = false;

        return $this;
    }

    /**
     * Disables the {@see Profiler} globally.
     *
     * @return void
     */
    public function disable() : void
    {
        $this->disabled = true;
    }

    /**
     * Sets the category for the current instance.
     *
     * @param null|non-empty-string $category
     *
     * @return self
     */
    public function setCategory( ?string $category ) : static
    {
        if ( $this->category ) {
            \trigger_error( __METHOD__." cannot override existing category: '{$this->category}'" );
        }
        else {
            $this->category = $this->category( $category );
        }

        return $this;
    }

    public function close() : void
    {
        foreach ( $this->events as $event ) {
            foreach ( $event as $profilerEvent ) {
                $profilerEvent->stopAll();
            }
        }

        $this->closedAt ??= \microtime( true );
    }

    /**
     * Formats and validates the provided event name, associating it with the optional category if specified.
     *
     * @param string $string name of the event
     *
     * @return non-empty-string
     */
    private function name( string $string ) : string
    {
        // @phpstan-ignore-next-line | Assertions assert
        \assert( \strlen( $string ) > 0, 'Event name must not be empty.' );
        \assert(
            \ctype_alnum( \str_replace( ['\\', '/', ':', '.', '-', '_'], '', $string ) ),
            'Event name must not be empty.',
        );

        return $string;
    }

    /**
     * Handles the category string transformation or returns the current category if no string is provided.
     *
     * @param null|non-empty-string $string
     *
     * @return non-empty-string
     */
    private function category( ?string $string ) : string
    {
        if ( ! $string ) {
            return $this->category ?? Profiler::DEFAULT_CATEGORY;
        }

        $namespaced = \explode( '\\', $string );

        return \end( $namespaced ) ?: Profiler::DEFAULT_CATEGORY;
    }
}
