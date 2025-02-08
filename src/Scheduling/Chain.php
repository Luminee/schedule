<?php

namespace Luminee\Schedule\Scheduling;

class Chain
{
    /**
     * @var string
     */
    protected $name;

    protected $eventNames = [];

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEvents($events)
    {
        $names = [];
        foreach ($events as $event) {
            $names[] = $event->getEventName();
        }
        if (count(array_unique($names)) < $events->count()) {
            throw new \Exception('Chained event names must be unique.');
        }
        return $events;
    }

}
