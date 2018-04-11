<?php

namespace Lorisleiva\LaravelDeployer\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Process\Process;

class BaseCommand extends Command
{
    protected $depBinary;
    protected $useDeployerOptions = true;

    public function __construct()
    {
        $this->depBinary = base_path('vendor/bin/dep');

        $deployerOptions = "
            {--p|parallel : Run tasks in parallel}
            {--l|limit= : How many host to run in parallel?}
            {--no-hooks : Run task without after/before hooks}
            {--log= : Log to file}
            {--roles= : Roles to deploy}
            {--hosts= : Host to deploy, comma separated, supports ranges [:]}
            {--o|option=* : Sets configuration option}
            {--f|file= : Specify Deployer file}
            {--tag= : Tag to deploy}
            {--revision= : Revision to deploy}
            {--branch= : Branch to deploy}
        ";

        if ($this->useDeployerOptions) {
            $this->signature .= $deployerOptions;
        }
        
        parent::__construct();
    }

    public function dep($command)
    {
        if (! $this->hasConfigFile()) {
            $this->error("config/deploy.php file not found.");
            $this->error("Please run `php artisan deploy:init` to get started.");
            return;
        }

        $basePath = base_path();
        $parameters = $this->parseParameters();
        $this->process("cd $basePath && $this->depBinary $command $parameters");
    }

    public function parseParameters()
    {
        $parameters = $this->parseArguments()->merge($this->parseOptions());

        if ($this->getOutput()->isDebug()) {
            $parameters->push('-vvv');
        } elseif ($this->getOutput()->isVeryVerbose()) {
            $parameters->push('-vv');
        } elseif ($this->getOutput()->isVerbose()) {
            $parameters->push('-v');
        } elseif ($this->getOutput()->isQuiet()) {
            $parameters->push('-q');
        }

        return (string) new ArrayInput($parameters->toArray(), null);
    }

    public function parseArguments()
    {
        return collect($this->arguments())
            ->reject(function ($value) {
                return ! $value && ! is_string($value) && ! is_numeric($value);
            })
            ->pipe(function ($arguments) {
                $command = $arguments->get('command');
                return $command && $arguments->get(0) === $command
                    ? $arguments->forget(0)
                    : $arguments;
            })
            ->forget('command');
    }

    public function parseOptions()
    {
        $i = 0;
        return collect($this->options())
            ->reject(function ($value) {
                return ! $value && ! is_string($value) && ! is_numeric($value);
            })
            ->mapWithKeys(function ($value, $key) use (&$i) {
                return is_bool($value) ? [ $i++ => "--$key" ] : [ "--$key" => $value ];
            })
            ->pipe(function ($options) {
                return ! $options->contains('--no-ansi')
                    ? $options->push('--ansi')
                    : $options;
            });
    }

    public function hasConfigFile()
    {
        // Check that the deploy.php config file exists.
        return file_exists(base_path('config/deploy.php'))

        // Or that the project has its own deployer file. 
            || file_exists(base_path('deploy.php'));
    }

    public function process($command)
    {
        $process = new Process($command);
        $process->setTimeout(null);
        $process->setIdleTimeout(null);
        $process->run(function($type, $buffer) {
            $this->output->write($buffer);
        });
    }
}