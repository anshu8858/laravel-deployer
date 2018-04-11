<?php

namespace Lorisleiva\LaravelDeployer\Concerns;

use Symfony\Component\Console\Input\ArrayInput;

trait ParsesCliParameters
{
    public function parseParametersAsString($parameters = null)
    {
        $parameters = $parameters ?? $this->parseParameters();
        return (string) new ArrayInput($parameters->toArray(), null);
    }

    public function parseParameters()
    {
        return $this->parseArguments()
            ->merge($this->parseOptions())
            ->merge($this->parseVerbosityLevel());
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

    public function parseVerbosityLevel()
    {
        if ($this->getOutput()->isDebug()) {
            return ['-vvv'];
        } elseif ($this->getOutput()->isVeryVerbose()) {
            return ['-vv'];
        } elseif ($this->getOutput()->isVerbose()) {
            return ['-v'];
        } elseif ($this->getOutput()->isQuiet()) {
            return ['-q'];
        } else {
            return [];
        }
    }
}