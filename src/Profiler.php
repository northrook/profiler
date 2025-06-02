<?php

declare(strict_types=1);

namespace Core;

use Core\Interface\ProfilerInterface;
use Symfony\Component\Stopwatch\{
    Stopwatch, StopwatchEvent
};
use Throwable;

final class Profiler implements ProfilerInterface
{
    private static bool $disabled;

    protected readonly ?Stopwatch $stopwatch;

    /** @var bool[][] */
    private array $events = [
        '_events' => [],
    ];

    /** @var null|non-empty-string */
    protected ?string $category = null;

    /**
     * @param null|bool|Stopwatch   $stopwatch
     * @param null|non-empty-string $category  [Profiler]
     *
     * @return void
     */
    public function __construct(
        null|bool|Stopwatch $stopwatch = null,
        ?string             $category = null,
    ) {
        self::$disabled ??= $stopwatch === false;

        if ( $stopwatch === true ) {
            $stopwatch = new Stopwatch( true );
        }

        $this->stopwatch = $stopwatch ?: null;
        $this->setCategory( $category ?? 'Profiler' );
    }

    /**
     * Retrieve or create a {@see StopwatchEvent} by name and optional category.
     *
     * - The event is started on instantiation.
     * - `null` if the profiler is `disabled`.
     *
     * @param non-empty-string      $name     the name of the event to retrieve
     * @param null|non-empty-string $category an optional category for the event
     *
     * @return null|StopwatchEvent
     */
    public function __invoke(
        string  $name,
        ?string $category = null,
    ) : ?StopwatchEvent {
        return $this->event( $name, $category );
    }

    /**
     * Creates {@see Profiler} instance, returning a {@see ProfilerInterface}.
     *
     * @param null|bool|ProfilerInterface|Stopwatch $profiler
     * @param null|non-empty-string                 $category [Profiler]
     *
     * @return null|ProfilerInterface
     */
    public static function from(
        null|bool|Stopwatch|ProfilerInterface $profiler,
        ?string                               $category = null,
    ) : ?ProfilerInterface {
        if ( self::$disabled || $profiler === null ) {
            return null;
        }
        if ( $profiler instanceof Stopwatch || \is_bool( $profiler ) ) {
            return new self( $profiler, $category );
        }

        return $profiler->setCategory( $category ?? 'Profiler' );
    }

    /**
     * Sets the category for the current instance.
     *
     * @param null|non-empty-string $category
     *
     * @return self
     */
    public function setCategory( ?string $category ) : self
    {
        $this->category = $this->category( $category );
        return $this;
    }

    /**
     * Starts an event with the given name and optional category.
     *
     *  - The event is started on instantiation.
     *
     * @param non-empty-string      $name     the name of the event to start
     * @param null|non-empty-string $category an optional category for the event
     *
     * @return void
     */
    public function start(
        string  $name,
        ?string $category = null,
    ) : void {
        $this->event( $name, $category );
    }

    /**
     * Records a lap event with the specified name and optional category.
     *
     * @param non-empty-string      $name     name of the event to record the lap for
     * @param null|non-empty-string $category optional category name to associate with the event
     *
     * @return void
     */
    public function lap( string $name, ?string $category = null ) : void
    {
        $this->event( $name, $category )?->lap();
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
        if ( $this::$disabled || $this->stopwatch === null ) {
            return;
        }

        $category = $this->category( $category );

        if ( $name ) {
            $name = $this->name( $name, $category );
            $this->stopwatch->getEvent( $name )->ensureStopped();
            $this->events[$category ?? '_events'][$name] = false;
        }

        if ( $category ) {
            foreach ( $this->events[$category] ?? [] as $event => $dummy ) {
                if ( ! $this->events[$category][$event] ) {
                    continue;
                }

                $this->events[$category][$event] = false;

                try {
                    $this->stopwatch->getEvent( $event )->ensureStopped();
                }
                catch ( Throwable ) {
                    continue;
                }
            }
        }
    }

    /**
     * Starts an event with the specified name and optional category and returns it.
     *
     * @param non-empty-string      $name     name of the event to start
     * @param null|non-empty-string $category optional category name to associate with the event
     *
     * @return null|StopwatchEvent
     */
    public function event(
        string  $name,
        ?string $category = null,
    ) : ?StopwatchEvent {
        if ( $this::$disabled || $this->stopwatch === null ) {
            return null;
        }

        $category = $this->category( $category );
        $name     = $this->name( $name, $category );

        if ( $category ) {
            $this->events[$category][$name] ??= true;
        }
        else {
            $this->events['_events'][$name] ??= true;
        }

        return $this->stopwatch->start( $name, $category ? \ucfirst( $category ) : null );
    }

    /**
     * Retrieves the stopwatch instance.
     *
     * @return null|Stopwatch
     */
    public function getStopwatch() : ?Stopwatch
    {
        return $this->stopwatch;
    }

    /**
     * Formats and validates the provided event name, associating it with the optional category if specified.
     *
     * @param non-empty-string      $string   name of the event to be formatted and validated
     * @param null|non-empty-string $category optional category to associate with the event name
     *
     * @return non-empty-string `categorized.event-name`
     */
    private function name( string $string, ?string $category ) : string
    {
        // @phpstan-ignore-next-line | Assertions assert
        \assert( \strlen( $string ) > 0, 'Event name must not be empty.' );

        if ( \class_exists( $string, false ) ) {
            return $string;
        }

        if ( ! $category ) {
            return $string;
        }

        if ( \str_starts_with( $string, "{$category}." ) ) {
            return $string;
        }

        return "{$category}.".\trim( $string, " \n\r\t\v\0." );
    }

    /**
     * Handles the category string transformation or returns the current category if no string is provided.
     *
     * @param null|non-empty-string $string
     *
     * @return null|non-empty-string
     */
    private function category( ?string $string ) : ?string
    {
        if ( ! $string ) {
            return $this->category;
        }

        $namespaced = \explode( '\\', $string );

        if ( $string = \end( $namespaced ) ) {
            return \strtolower( $string );
        }

        return null;
    }

    /**
     * Checks if the functionality is currently enabled.
     *
     * @return bool true if enabled, false otherwise
     */
    public static function isEnabled() : bool
    {
        return isset( self::$disabled ) && self::$disabled === false;
    }

    /**
     * Enables the {@see Profiler} globally.
     *
     * @return void
     */
    public static function enable() : void
    {
        self::$disabled = false;
    }

    /**
     * Disables the {@see Profiler} globally.
     *
     * @return void
     */
    public static function disable() : void
    {
        self::$disabled = true;
    }
}
