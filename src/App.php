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
use function Support\{normalizePath};

class App
{
    /** @var array<string, mixed> */
    private array $parameters = [
        'env'   => 'dev',
        'debug' => true,
    ];

    public readonly string $title;

    public readonly string $env;

    public readonly bool $debug;

    public readonly Pathfinder $pathfinder;

    public readonly CacheItemPoolInterface $cacheItemPool;

    public readonly LoggerInterface $logger;

    public bool $showLogs = true;

    public bool $logsExpanded = true;

    /**
     * @param array<string, mixed> $parameters
     * @param null|LoggerInterface $logger
     * @param bool                 $enableDebug
     */
    public function __construct(
        array            $parameters = [],
        ?LoggerInterface $logger = null,
        bool             $enableDebug = true,
    ) {
        if ( $enableDebug ) {
            \Tracy\Debugger::enable();
        }

        $this->parameters = \array_merge( $this->parameters, $parameters );
        // 'title'     => $_SERVER['HTTP_HOST'] ?? 'Development Environment',
        $this->parameters['title'] ??= \ucwords(
            \str_replace( ['.', '-', '_'], ' ', \basename( __DIR__ ) ),
        );
        $this->parameters['dir.root']          ??= $this->getProjectDir();
        $this->parameters['dir.assets']        ??= 'dir.root/assets';
        $this->parameters['dir.cache']         ??= 'dir.root/var';
        $this->parameters['dir.public']        ??= 'dir.root/public';
        $this->parameters['dir.public.assets'] ??= 'dir.root/public/assets';

        $this->env   = $this->parameters['env']   ?? 'dev';
        $this->debug = $this->parameters['debug'] ?? true;
        $this->title = $this->parameters['title'];

        $this->logger = $logger ?? new Logger();
        Log::setLogger( $this->logger );

        $this->pathfinder    = new Pathfinder( $this->parameters, logger : $this->logger );
        $this->cacheItemPool = new LocalStorage( $this->pathfinder->get( 'dir.cache/_dev-cacheItemPool.php' ) );

        \register_shutdown_function( [$this, 'onShutdown'] );
    }

    public function newLocalStorage( string $name ) : CacheItemPoolInterface
    {
        $name = (string) \preg_replace( '/[^a-z0-9.]+/i', '.', $name );
        return new LocalStorage( $this->pathfinder->get( "dir.cache/{$name}.php" ) );
    }

    public function getProjectDir() : string
    {
        return $this->parameters['dir.root'] ??= ( static function() : string {
            // Split the current directory into an array of directory segments
            $segments = \explode( '', __DIR__ );

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
