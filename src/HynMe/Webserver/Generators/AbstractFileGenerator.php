<?php namespace HynMe\Webserver\Generators;

use Laraflock\MultiTenant\Models\Website;
use File;
use HynMe\Webserver\Abstracts\AbstractGenerator;
use ReflectionClass;

abstract class AbstractFileGenerator extends AbstractGenerator
{

    /**
     * @var Website
     */
    protected $website;

    /**
     * @param Website $website
     */
    public function __construct(Website $website)
    {
        $this->website = $website;
    }
    /**
     * Writes the contents to disk
     * @return int
     */
    public function onCreate()
    {
        // take no action with no hostnames
        if($this->website->hostnames->count() == 0)
            return;
        return File::put(
            $this->publishPath(),
            $this->generate()->render(),
            true
        ) && $this->serviceReload();
    }

    public function onUpdate()
    {
        if($this->website->isDirty('identifier'))
        {
            $new = $this->website->identifier;

            $this->website->identifier = $this->website->getOriginal('identifier');
            $this->onDelete();
            $this->website->identifier = $new;
        }

        return $this->onCreate();
    }

    public function onRename($from, $to)
    {
        // .. no implementation
    }

    public function onDelete()
    {
        return File::delete($this->publishPath()) && $this->serviceReload();
    }

    public function name()
    {
        return sprintf("%d-%s", $this->website->id, $this->website->identifier);
    }

    /**
     * Generates the content
     * @return \Illuminate\View\View
     */
    abstract public function generate();
    /**
     * Provides the complete path to publish the generated content to
     * @return string
     */
    abstract protected function publishPath();

    /**
     * Reloads service if possible
     * @return bool
     */
    protected function serviceReload()
    {
        if(!$this->isInstalled())   return null;

        exec(array_get($this->configuration() ,'actions.configtest'), $out, $test);

        if($test == 0)
        {
            exec(array_get($this->configuration() ,'actions.reload'), $out, $reload);
        }
        else
            $reload = 1;

        return $test == 0 && $reload == 0;
    }

    /**
     * @return string
     */
    protected function baseName()
    {
        $reflect = new ReflectionClass($this);
        return strtolower($reflect->getShortName());
    }

    /**
     * Loads possible configuration from config file
     *
     * @return array
     * @throws \Exception
     */
    public function configuration()
    {
        $configuration = config('webserver');
        if(!$configuration || !array_has($configuration, $this->baseName()))
            throw new \Exception("No configuration for {$this->baseName()}");

        return array_get($configuration, $this->baseName());
    }

    /**
     * tests whether a certain service is installed
     * @return bool
     */
    public function isInstalled()
    {
        $service = array_get($this->configuration(), 'service');
        return $service && File::exists($service);
    }

    /**
     * Registers the service
     */
    public function register()
    {
        if(!$this->isInstalled())   return null;

        // create a unique filename for the global include directory
        $webserviceFileLocation = sprintf("%s%s",
            $this->findPathForRegistration(array_get($this->configuration(), "conf", [])),
            sprintf(array_get($this->configuration(), "mask", "%s"), substr(md5(env('APP_KEY')), 0, 10))
        );

        // load the tenant include path
        $targetPath = array_get($this->configuration(), 'path');

        // save file to global include path
        File::put($webserviceFileLocation, sprintf(array_get($this->configuration(), "include"), $targetPath));

        /*
         * Register any depending services as well
         */
        $depends = array_get($this->configuration(), 'depends', []);

        foreach($depends as $depend)
        {
            $class = config("webserver.{$depend}.class");
            if(empty($class))
                continue;
            (new $class($this->website))->register();
        }

        // reload any services
        if(method_exists($this, 'serviceReload'))
            $this->serviceReload();
    }

    /**
     * Finds first directory that exists
     * @param array $paths
     * @return string
     */
    protected function findPathForRegistration($paths = [])
    {
        foreach($paths as $path) {
            if (!empty($path) && File::isDirectory($path)) {
                return $path;
            }
        }
    }
}