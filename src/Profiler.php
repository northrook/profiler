<?php

declare(strict_types=1);

namespace Core;

use Core\Interface\ProfilerInterface;
use Symfony\Component\Stopwatch\{
    Stopwatch, StopwatchEvent
};
use function Support\slug;
use Throwable, InvalidArgumentException;

final class Profiler implements ProfilerInterface
{
    private static bool $disabled = false;

    /** @var bool[][] */
    private array $events = [
        '_events' => [],
    ];

    /** @var null|non-empty-string */
    protected ?string $category = null;

    public readonly Stopwatch $stopwatch;

    /**
     * If no `$stopwatch` is provided, one will be initiated.
     *
     * @param null|Stopwatch $stopwatch
     * @param null|string    $category
     */
    public function __construct(
        ?Stopwatch $stopwatch = null,
        ?string    $category = null,
    ) {
        $this->stopwatch = $stopwatch ?? new Stopwatch( true );
        $this->setCategory( $category );
    }

    public function setCategory( ?string $category ) : self
    {
        $this->category = $this->category( $category );
        return $this;
    }

    public function __invoke(
        string  $name,
        ?string $category = null,
    ) : ?StopwatchEvent {
        return $this->event( $name, $category );
    }

    public function event(
        string  $name,
        ?string $category = null,
    ) : ?StopwatchEvent {
        if ( self::$disabled ) {
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

        return $this->stopwatch->start( $name, $category );
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
        if ( self::$disabled ) {
            return;
        }

        $category = $this->category( $category );

        if ( $name ) {
            $this->stopwatch->getEvent( $this->name( $name, $category ) )->ensureStopped();
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

        return slug( "{$category}.{$string}", '.' )
                ?: throw new InvalidArgumentException(
                    'Event name must not be empty.',
                );
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

        return \end( $namespaced ) ?: null;
    }

    public static function isEnabled() : bool
    {
        return ! self::$disabled;
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
