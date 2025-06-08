<?php

declare(strict_types=1);

namespace _Dev;

use Cache\LocalStorage;
use Core\{Interface\ProfilerInterface, Pathfinder, Profiler, SettingsProvider};
use Northrook\Logger;
use Northrook\Logger\{Log, Output};
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Throwable;
use Tracy\Debugger;
use function Support\{
    get_project_directory,
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

    public readonly Request $request;

    public readonly Pathfinder $pathfinder;

    public readonly SettingsProvider $settingsProvider;

    public readonly CacheItemPoolInterface $cacheItemPool;

    public readonly LoggerInterface $logger;

    public readonly ProfilerInterface $profiler;

    public bool $showLogs = true;

    public bool $logsExpanded = true;

    /**
     * @param array<string, mixed>        $parameters
     * @param null|LoggerInterface        $logger
     * @param null|bool|ProfilerInterface $profiler
     * @param bool                        $enableDebug
     *
     * @noinspection PhpInternalEntityUsedInspection
     */
    public function __construct(
        array                       $parameters = [],
        ?LoggerInterface            $logger = null,
        null|bool|ProfilerInterface $profiler = true,
        bool                        $enableDebug = true,
    ) {
        if ( $enableDebug ) {
            Debugger::enable();
        }

        $this->request = Request::createFromGlobals();

        $this->parameters = \array_merge( $this->parameters, $parameters );

        $this->parameters['site.title'] ??= $this->resolveTitle();
        $this->parameters['site.url']   ??= "{$this->request->getScheme()}://{$this->request->getHttpHost()}";

        $this->parameters['dir.root']          ??= get_project_directory();
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
                'site.title' => (string) $this->parameters['site.title'],
                'site.url'   => (string) $this->parameters['site.url'],
            ],
            assignMissingDefaults : true,
        );

        $this->logger = $logger ?? new Logger();
        Log::setLogger( $this->logger );

        $this->pathfinder = new Pathfinder( $this->pathfinderParameters() );
        $this->pathfinder->setLogger( $this->logger );

        $this->cacheItemPool = new LocalStorage( $this->pathfinder->get( 'dir.cache/_dev-fileCache.php' ) );
        $this->cacheItemPool->setLogger( $this->logger );

        $this->profiler = $profiler instanceof ProfilerInterface
                ? $profiler
                : new Profiler( $profiler );

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
     * @return array<non-empty-string, string>
     */
    private function pathfinderParameters() : array
    {
        $parameters = [];

        foreach ( $this->parameters as $key => $value ) {
            if (
                \is_string( $key )
                && ! empty( $key )
                && is_stringable( $value )
            ) {
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
            \str_replace( ['.', '-', '_'], ' ', \basename( get_project_directory() ) ),
        );
    }
}
