<?php

declare(strict_types=1);

namespace _Dev;

use Cache\LocalStorage;
use Core\{Pathfinder, SettingsProvider};
use Northrook\Logger;
use Northrook\Logger\{Log, Output};
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use RuntimeException;
use Throwable;
use Tracy\Debugger;
use function Support\{getProjectDirectory,
    is_path,
    is_stringable,
    is_url,
    normalize_path,
    normalize_url,
    str_starts_with_any
};

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

    public readonly SettingsProvider $settingsProvider;

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
            Debugger::enable();
        }

        $scheme = $_SERVER['REQUEST_SCHEME'];
        $domain = $_SERVER['SERVER_NAME'];
        $host   = $_SERVER['HTTP_HOST'];

        \assert( \is_string( $scheme ) && \is_string( $domain ) && \is_string( $host ) );

        $this->parameters = \array_merge( $this->parameters, $parameters );

        $this->parameters['site.title'] ??= $this->resolveTitle();
        $this->parameters['site.url']   ??= "{$scheme}://{$domain}";

        $this->parameters['dir.root']          ??= getProjectDirectory();
        $this->parameters['dir.assets']        ??= '%dir.root%/assets';
        $this->parameters['dir.cache']         ??= '%dir.root%/var/cache';
        $this->parameters['dir.temp']          ??= '%dir.root%/var/temp';
        $this->parameters['dir.public']        ??= '%dir.root%/public';
        $this->parameters['dir.public.assets'] ??= '%dir.root%/public/assets';

        $this->env   = (string) $this->parameters['env'];
        $this->debug = (bool) $this->parameters['debug'];
        $this->title = (string) $this->parameters['site.title'];

        $this->settingsProvider = new SettingsProvider(
            defaults              : [
                'site.title' => (string) $parameters['site.title'],
                'site.url'   => (string) $parameters['site.url'],
            ],
            assignMissingDefaults : true,
        );

        $this->logger = $logger ?? new Logger();
        Log::setLogger( $this->logger );

        $this->pathfinder = new Pathfinder( $this->pathfinderParameters() );
        $this->pathfinder->setLogger( $this->logger );

        $this->cacheItemPool = new LocalStorage( $this->pathfinder->get( 'dir.cache/_dev-fileCache.php' ) );
        $this->cacheItemPool->setLogger( $this->logger );

        \register_shutdown_function( [$this, 'onShutdown'] );
    }

    /**
     * @param null|string $key
     *
     * @return ($key is null ?  array<string, mixed> : null|string)
     */
    public function getParameter( ?string $key = null ) : string|array|null
    {
        if ( $key === null ) {
            return $this->parameters;
        }
        return $this->parameters[$key] ?? null;
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

    /**
     * @return array<string, string>
     */
    private function pathfinderParameters() : array
    {
        $parameters = [];

        foreach ( $this->parameters as $key => $value ) {
            if ( is_stringable( $value ) ) {
                $string = (string) $value;
            }
            else {
                continue;
            }

            $isUrl   = is_url( $string );
            $isPath  = is_path( $string );
            $isNamed = str_starts_with_any( $key, 'dir.', 'path.' );

            if ( ! ( $isUrl || $isPath || $isNamed ) ) {
                continue;
            }

            $parameters[$key] = match ( true ) {
                $isUrl  => normalize_url( $string ),
                $isPath => normalize_path( $string ),
                default => $string,
            };
        }

        \ksort( $parameters );
        return $parameters;
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

    private function resolveTitle() : string
    {
        return \ucwords(
            \str_replace( ['.', '-', '_'], ' ', \basename( getProjectDirectory() ) ),
        );
    }
}
