<?php


namespace NextDeveloper\AI;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Commons\AbstractServiceProvider;

/**
 * Class AIServiceProvider
 *
 * @package NextDeveloper\AI
 */
class AIServiceProvider extends AbstractServiceProvider
{
    /**
     * @var bool
     */
    protected $defer = false;

    /**
     * @return void
     * @throws \Exception
     *
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/ai.php' => config_path('ai.php'),
        ], 'config');

        $this->loadViewsFrom($this->dir . '/../resources/views', 'AI');

//        $this->bootErrorHandler();
        $this->bootChannelRoutes();
        $this->bootModelBindings();
        $this->bootLogger();
    }

    /**
     * @return void
     */
    public function register()
    {
        $this->registerHelpers();
        $this->registerMiddlewares('ai');
        $this->registerRoutes();
        $this->registerCommands();

        $this->mergeConfigFrom(__DIR__ . '/../config/ai.php', 'ai');
    }

    /**
     * @return void
     */
    public function bootLogger()
    {
//        $monolog = Log::getMonolog();
//        $monolog->pushProcessor(new \Monolog\Processor\WebProcessor());
//        $monolog->pushProcessor(new \Monolog\Processor\MemoryUsageProcessor());
//        $monolog->pushProcessor(new \Monolog\Processor\MemoryPeakUsageProcessor());
    }

    /**
     * @return array
     */
    public function provides()
    {
        return ['ai'];
    }

//    public function bootErrorHandler() {
//        $this->app->singleton(
//            ExceptionHandler::class,
//            Handler::class
//        );
//    }

    /**
     * @return void
     */
    private function bootChannelRoutes()
    {
        if (file_exists(($file = $this->dir . '/../config/channel.routes.php'))) {
            require_once $file;
        }
    }

    /**
     * Register module routes
     *
     * @return void
     */
    protected function registerRoutes()
    {
        if (!$this->app->routesAreCached() && config('leo.allowed_routes.ai', true)) {
            $this->app['router']
                ->namespace('NextDeveloper\AI\Http\Controllers')
                ->group(__DIR__ . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'api.routes.php');
        }
    }

    /**
     * Registers module based commands
     * @return void
     */
    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([

            ]);
        }
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}
