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
     * @param null|bool|Stopwatch $stopwatch
     * @param null|string         $category
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


    public function __invoke(
        string  $name,
        ?string $category = null,
    ) : ?StopwatchEvent {
        return $this->event( $name, $category );
    }

    public function setCategory( ?string $category ) : self
    {
        $this->category = $this->category( $category );
        return $this;
    }

    public function start(
        string  $name,
        ?string $category = null,
    ) : void {
        $this->event( $name, $category )?->start();
    }

    public function lap( string $name, ?string $category = null ) : void
    {
        $this->event( $name, $category )?->lap();
    }

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

    public function getStopwatch() : ?Stopwatch
    {
        return $this->stopwatch;
    }

    /**
     * @param string      $string
     * @param null|string $category
     *
     * @return non-empty-string
     */
    private function name( string $string, ?string $category ) : string
    {
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
     * @param null|string $string
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

    public static function isEnabled() : bool
    {
        return isset( self::$disabled ) && self::$disabled === false;
    }

    public static function enable() : void
    {
        self::$disabled = false;
    }

    public static function disable() : void
    {
        self::$disabled = true;
    }
}
