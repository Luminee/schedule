<?php

namespace Luminee\Schedule\Scheduling\Concerns;

use Luminee\Schedule\Scheduling\Chain;

trait ManagesChains
{
    /**
     * @var Chain
     */
    protected $chain;

    /**
     * @var string
     */
    protected $eventName;

    /**
     * @var string[]
     */
    protected $dependsOn = [];

    /**
     * @var string[]
     */
    protected $leads = [];

    /**
     * @var bool
     */
    protected $blocked = false;

    /**
     * @var bool
     */
    protected $needRedo = false;

    public function bindChain($chain): self
    {
        $this->chain = $chain;

        return $this;
    }

    public function getChain(): Chain
    {
        return $this->chain;
    }

    public function alias($name): self
    {
        $this->eventName = $name;

        return $this;
    }

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function dependsOn($schedule_list): self
    {
        $this->dependsOn = $schedule_list;

        return $this;
    }

    public function getDependsOn(): array
    {
        return $this->dependsOn;
    }

    public function leads($schedule_list): self
    {
        $this->leads = $schedule_list;

        return $this;
    }

    public function getLeads(): array
    {
        return $this->leads;
    }

    public function blocked(): self
    {
        $this->blocked = true;

        return $this;
    }

    public function isBlocked(): bool
    {
        return $this->blocked;
    }

    public function needRedo(): self
    {
        $this->needRedo = true;

        return $this;
    }

    public function isNeedRedo(): bool
    {
        return $this->needRedo;
    }


}
