<?php

declare(strict_types=1);

namespace _Dev;

use Cache\LocalStorage;
use Core\Pathfinder;
use Northrook\Logger;
use Northrook\Logger\{Log, Output};
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use RuntimeException;
use Throwable;
use Tracy\Debugger;
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
            Debugger::enable();
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

        $this->pathfinder = new Pathfinder( \array_filter( $this->parameters, 'is_string' ) );
        $this->pathfinder->setLogger( $this->logger );
        $this->cacheItemPool = new LocalStorage( $this->pathfinder->get( 'dir.cache/_dev-cacheItemPool.php' ) );
        $this->cacheItemPool->setLogger( $this->logger );

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

    public function newPhpFilesAdapter(
        string  $namespace = 'dev.app',
        int     $defaultLifetime = 0,
        ?string $directory = null,
        bool    $appendOnly = false,
    ) : PhpFilesAdapter {
        if ( ! \class_exists( PhpFilesAdapter::class ) ) {
            throw new RuntimeException(
                "PhpFilesAdapter is not available.\nRun 'composer require symfony/cache' to install it.",
            );
        }
        try {
            $directory ??= $this->pathfinder->get( "dir.cache/{$namespace}.php" );
            return new PhpFilesAdapter( $namespace, $defaultLifetime, $directory, $appendOnly );
        }
        catch ( Throwable $e ) {
            throw new RuntimeException( $e->getMessage(), $e->getCode(), $e );
        }
    }
}
