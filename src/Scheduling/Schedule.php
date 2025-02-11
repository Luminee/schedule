<?php

namespace Luminee\Schedule\Scheduling;

use Illuminate\Console\Application;
use Illuminate\Console\Scheduling\CacheMutex;
use Illuminate\Console\Scheduling\Mutex;
use Illuminate\Container\Container;
use Illuminate\Support\ProcessUtils;
use Illuminate\Contracts\Queue\ShouldQueue;

class Schedule
{
    /**
     * All the events on the schedule.
     *
     * @var Event[]
     */
    protected $events = [];

    /**
     * @var Chain[]
     */
    protected $chains = [];

    /**
     * @var Chain
     */
    protected $currentChain;

    /**
     * The mutex implementation.
     *
     * @var \Illuminate\Console\Scheduling\Mutex
     */
    protected $mutex;

    /**
     * Create a new schedule instance.
     *
     * @return void
     */
    public function __construct()
    {
        $container = Container::getInstance();

        $this->mutex = $container->bound(Mutex::class)
            ? $container->make(Mutex::class)
            : $container->make(CacheMutex::class);
    }

    public function newChain($name, $callback = null): self
    {
        $this->chains[] = $this->currentChain = new Chain($name);

        if (is_callable($callback)) {
            $callback($this->currentChain);
        }

        return $this;
    }

    public function switchChain($name): self
    {
        foreach ($this->chains as $chain) {
            if ($chain->getName() === $name) {
                $this->currentChain = $chain;
                return $this;
            }
        }

        return $this;
    }

    /**
     * Add a new callback event to the schedule.
     *
     * @param string|callable $callback
     * @param array $parameters
     * @return CallbackEvent
     */
    public function call($callback, array $parameters = [])
    {
        $this->events[] = $event = new CallbackEvent(
            $this->mutex, $callback, $parameters
        );

        $event->bindChain($this->currentChain);

        return $event;
    }

    /**
     * Add a new Artisan command event to the schedule.
     *
     * @param string $command
     * @param array $parameters
     * @return Event
     */
    public function command($command, array $parameters = [])
    {
        if (class_exists($command)) {
            $command = Container::getInstance()->make($command)->getName();
        }

        return $this->exec(
            Application::formatCommandString($command), $parameters
        );
    }

    /**
     * Add a new job callback event to the schedule.
     *
     * @param object|string $job
     * @param string|null $queue
     * @return CallbackEvent
     */
    public function job($job, $queue = null)
    {
        return $this->call(function () use ($job, $queue) {
            $job = is_string($job) ? resolve($job) : $job;

            if ($job instanceof ShouldQueue) {
                dispatch($job)->onQueue($queue);
            } else {
                dispatch_now($job);
            }
        })->name(is_string($job) ? $job : get_class($job));
    }

    /**
     * Add a new command event to the schedule.
     *
     * @param string $command
     * @param array $parameters
     * @return Event
     */
    public function exec($command, array $parameters = [])
    {
        if (count($parameters)) {
            $command .= ' ' . $this->compileParameters($parameters);
        }

        $this->events[] = $event = new Event($this->mutex, $command);

        $event->bindChain($this->currentChain);

        return $event;
    }

    /**
     * Compile parameters for a command.
     *
     * @param array $parameters
     * @return string
     */
    protected function compileParameters(array $parameters)
    {
        return collect($parameters)->map(function ($value, $key) {
            if (is_array($value)) {
                $value = collect($value)->map(function ($value) {
                    return ProcessUtils::escapeArgument($value);
                })->implode(' ');
            } elseif (!is_numeric($value) && !preg_match('/^(-.$|--.*)/i', $value)) {
                $value = ProcessUtils::escapeArgument($value);
            }

            return is_numeric($key) ? $value : "{$key}={$value}";
        })->implode(' ');
    }

    /**
     * Get all the events on the schedule that are due.
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     * @return \Illuminate\Support\Collection
     */
    public function dueEvents($app)
    {
        return collect($this->events)->filter->isDue($app);
    }

    /**
     * Get all the events on the schedule.
     *
     * @return Event[]
     */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * @return Chain[]
     */
    public function chains(): array
    {
        return $this->chains;
    }
}
