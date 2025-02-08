<?php

namespace Luminee\Schedule\Console\Commands;

use Closure;
use DateTimeZone;
use Exception;
use Illuminate\Console\Application;
use Illuminate\Console\Command;
use Luminee\Schedule\Scheduling\CallbackEvent;
use Luminee\Schedule\Scheduling\Schedule;
use ReflectionClass;
use ReflectionFunction;
use Symfony\Component\Console\Terminal;

class ScheduleChainListCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'schedule-chain:list
        {--timezone= : The timezone that times should be displayed in}
        {--next : Sort the listed tasks by their next due date}
    ';

    /**
     * The name of the console command.
     *
     * This name is used to identify the command during lazy loading.
     *
     * @var string|null
     *
     * @deprecated
     */
    protected static $defaultName = 'schedule-chain:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all scheduled chain tasks';

    /**
     * The terminal width resolver callback.
     *
     * @var \Closure|null
     */
    protected static $terminalWidthResolver;

    /**
     * The schedule instance.
     *
     * @var Schedule
     */
    protected $schedule;

    /**
     * Create a new command instance.
     *
     * @param Schedule $schedule
     * @return void
     */
    public function __construct(Schedule $schedule)
    {
        $this->schedule = $schedule;

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     *
     * @throws Exception
     */
    public function handle()
    {
        $allEvents = collect($this->schedule->events())->groupBy(function ($event) {
            return $event->getChain()->getName();
        });

        $chains = collect($this->schedule->chains());

        if ($chains->isEmpty()) {
            $this->components->info('No Chained scheduled tasks have been defined.');

            return;
        }

        $terminalWidth = self::getTerminalWidth();

        $timezone = new DateTimeZone($this->option('timezone') ?? config('app.timezone'));

        foreach ($chains as $chain) {
            $events = $chain->getEvents($allEvents[$chain->getName()] ?? []);

            $output = $events->map(function ($event) use ($terminalWidth, $timezone) {
                $command = $event->command ?? '';

                $description = $event->description ?? '';

                if (!$this->output->isVerbose()) {
                    $command = str_replace([Application::phpBinary(), Application::artisanBinary()], [
                        'php',
                        preg_replace("#['\"]#", '', Application::artisanBinary()),
                    ], $command);
                }

                if ($event instanceof CallbackEvent) {
                    if (class_exists($description)) {
                        $command = $description;
                    } else {
                        $command = sprintf(
                            'Closure at: <fg=yellow>%s</>',
                            $this->getClosureLocation($event)
                        );
                    }
                }

                $output = [];

                $this->outputCommand($output, $command);

                $this->outputEventName($output, $event);

                $this->outputDependsOn($output, $event);

                $this->outputLeads($output, $event);

                $this->outputBlocked($output, $event);

                $this->outputNeedRedo($output, $event);

                $output[] = '  ';

                return $output;
            });

            $this->line(sprintf(
                '<fg=yellow;options=bold>%s</>: ',
                $chain->getName()
            ));

            $this->line(
                $output->flatten()->filter()->prepend('')->push('')->toArray()
            );
        }

    }

    protected function outputCommand(&$output, $command)
    {
        $output[] = sprintf(
            '  <fg=green>%s</>',
            preg_replace(
                "#(php artisan [\w\-:]+) (.+)#",
                '$1 <fg=yellow;options=bold>$2</>',
                mb_strlen($command) > 1 ? "{$command} " : ''
            )
        );
    }

    protected function getFormat($key): string
    {
        return '   - ' . $key . ': <fg=yellow>%s</>';
    }

    protected function outputEventName(&$output, $event)
    {
        if ($eventName = $event->getEventName()) {
            $output[] = sprintf(
                $this->getFormat('Name'),
                $eventName
            );
        }
    }

    protected function outputDependsOn(&$output, $event)
    {
        if ($dependsOn = $event->getDependsOn()) {
            $output[] = sprintf(
                $this->getFormat('Depends On'),
                implode(', ', $dependsOn)
            );
        }
    }

    protected function outputLeads(&$output, $event)
    {
        if ($leads = $event->getLeads()) {
            $output[] = sprintf(
                $this->getFormat('Leads'),
                implode(', ', $leads)
            );
        }
    }

    protected function outputBlocked(&$output, $event)
    {
        if ($event->isBlocked()) {
            $output[] = sprintf(
                $this->getFormat('Blocked'),
                'True'
            );
        }
    }

    protected function outputNeedRedo(&$output, $event)
    {
        if ($event->isNeedRedo()) {
            $output[] = sprintf(
                $this->getFormat('Need Redo'),
                'True'
            );
        }
    }


    /**
     * Get the file and line number for the event closure.
     *
     * @param CallbackEvent $event
     * @return string
     */
    private function getClosureLocation(CallbackEvent $event)
    {
        $callback = tap((new ReflectionClass($event))->getProperty('callback'))
            ->setAccessible(true)
            ->getValue($event);

        if ($callback instanceof Closure) {
            $function = new ReflectionFunction($callback);

            return sprintf(
                '%s:%s',
                str_replace($this->laravel->basePath() . DIRECTORY_SEPARATOR, '', $function->getFileName() ?: ''),
                $function->getStartLine()
            );
        }

        if (is_string($callback)) {
            return $callback;
        }

        if (is_array($callback)) {
            $className = is_string($callback[0]) ? $callback[0] : get_class($callback[0]);

            return sprintf('%s::%s', $className, $callback[1]);
        }

        return sprintf('%s::__invoke', get_class($callback));
    }

    /**
     * Get the terminal width.
     *
     * @return int
     */
    public static function getTerminalWidth()
    {
        return is_null(static::$terminalWidthResolver)
            ? (new Terminal)->getWidth()
            : call_user_func(static::$terminalWidthResolver);
    }

}
