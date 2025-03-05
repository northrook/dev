<?php

declare(strict_types=1);

namespace _Dev;

use Cache\LocalStorage;
use Core\Pathfinder;
use Northrook\Logger;
use Northrook\Logger\{Log, Output};
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use function Support\{getProjectDirectory};

class App
{
    /** @var array<string, bool|string> */
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
     * @param array<string, bool|string> $parameters
     * @param null|LoggerInterface       $logger
     * @param bool                       $enableDebug
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
            \str_replace( ['.', '-', '_'], ' ', \basename( getProjectDirectory() ) ),
        );
        $this->parameters['dir.root']          ??= getProjectDirectory();
        $this->parameters['dir.assets']        ??= '%dir.root%/assets';
        $this->parameters['dir.cache']         ??= '%dir.root%/var';
        $this->parameters['dir.public']        ??= '%dir.root%/public';
        $this->parameters['dir.public.assets'] ??= '%dir.root%/public/assets';

        $this->env   = (string) $this->parameters['env'];
        $this->debug = (bool) $this->parameters['debug'];
        $this->title = (string) $this->parameters['title'];

        $this->logger = $logger ?? new Logger();
        Log::setLogger( $this->logger );

        $this->pathfinder        = new Pathfinder( $this->parameters, logger : $this->logger );
        $this->pathfinder->quiet = true;
        $this->cacheItemPool     = new LocalStorage( $this->pathfinder->get( 'dir.cache/_dev-cacheItemPool.php' ) );

        \register_shutdown_function( [$this, 'onShutdown'] );
    }

    public function newLocalStorage( string $name ) : CacheItemPoolInterface
    {
        $name = (string) \preg_replace( '/[^a-z0-9.]+/i', '.', $name );
        return new LocalStorage( $this->pathfinder->get( "dir.cache/{$name}.php" ) );
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
