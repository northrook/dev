<?php

declare(strict_types=1);

namespace _Dev;

use Cache\LocalStorage;
use Core\Pathfinder;
use Northrook\Logger;
use Northrook\Logger\{Log, Output};
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use BadFunctionCallException;
use const DIRECTORY_SEPARATOR;
use function Support\{normalizePath};

class App
{
    /** @var array<string, mixed> */
    private array $parameters = [
        'env'   => 'dev',
        'debug' => true,
    ];

    public readonly Pathfinder $pathfinder;

    public readonly CacheItemPoolInterface $cacheItemPool;

    public readonly LoggerInterface $logger;

    public bool $showLogs = true;

    public bool $logsExpanded = true;

    /**
     * @param array<string, mixed> $parameters
     * @param null|LoggerInterface $logger
     */
    public function __construct(
        array            $parameters = [],
        ?LoggerInterface $logger = null,
    ) {
        $this->parameters = \array_merge( $this->parameters, $parameters );
        $this->parameters += [
            'dir.root'  => $this->getProjectDir(),
            'dir.cache' => $this->getProjectDir().'/var',
            'title'     => $_SERVER['HTTP_HOST'] ?? 'Development Environment',
        ];

        $this->logger = $logger ?? new Logger();
        Log::setLogger( $this->logger );

        $this->pathfinder    = new Pathfinder( $this->parameters, logger : $this->logger );
        $this->cacheItemPool = new LocalStorage( $this->pathfinder->get( 'dir.cache/_dev-cacheItemPool.php' ) );

        \register_shutdown_function( [$this, 'onShutdown'] );
    }

    public function getProjectDir() : string
    {
        return $this->parameters['dir.root'] ??= ( static function() : string {
            // Split the current directory into an array of directory segments
            $segments = \explode( DIRECTORY_SEPARATOR, __DIR__ );

            // Ensure the directory array has at least 5 segments and a valid vendor value
            if ( ( \count( $segments ) >= 5 && $segments[\count( $segments ) - 4] === 'vendor' ) ) {
                // Remove the last 4 segments (vendor, package name, and Composer structure)
                $rootSegments = \array_slice( $segments, 0, -4 );
            }
            else {
                $message = __FUNCTION__.' was unable to determine a valid root. Current path: '.__DIR__;
                throw new BadFunctionCallException( $message );
            }

            // Normalize and return the project path
            return normalizePath( ...$rootSegments );
        } )();
    }

    private function onShutdown() : void
    {
        if ( $this->showLogs ) {
            $open = $this->logsExpanded ? 'open' : '';
            echo "<details {$open}><summary>Logs</summary>";
            Output::dump( $this->logger );
            echo '</details>';
        }
    }
}
