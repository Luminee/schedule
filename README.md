# schedule

## Installation
1). Require

```
composer require luminee/schedule
```

2). Config

配置服务

`\Luminee\Schedule\ScheduleServiceProvider::class,`

3). Kernel

定义链式任务

in `App\Console\Kernel`

```php
class Kernel
{
    use \Luminee\Schedule\Console\Concerns\ScheduleKernel;
    
    protected function scheduleChain(\Luminee\Schedule\Scheduling\Schedule $schedule)
    {
        // Chains and Schedules
        
    }
    
}
```

4). Migrate

执行迁移，创建相关表

```
php artisan luminee:provider:migrate luminee/schedule
```

## Usage

in `App\Console\Kernel` at `scheduleChain()`

```php
$schedule->newChain('Chain A', function (\Luminee\Schedule\Scheduling\Chain $chain) {
    $chain->dailyAt('02:00');
});

$schedule->call(function () {
    // code...
})->alias('Closure A')
->dependsOn(['Closure M', 'Closure N'])
->leads(['Closure B', 'Closure C'])
->blocked()
->needRedo();
```

`newChain` 必须的，指定当前链式的名称

`alias` 必须的，指定当前任务的名称，需要记录在数据库中

`dependsOn` 可选，指定依赖的任务

`leads` 可选，指定当前任务执行后调取的任务

`blocked` 可选，指定当前任务如果失败，是否阻塞，默认为 false

`needRedo` 可选，指定当前任务是否需要重做，默认为 false


## Boot 启动

```shell
* * * * * php /path-to-your-project/artisan schedule-chain:run >> /dev/null 2>&1
```

## Other 其它应用

1). 查看任务列表

```shell
php artisan schedule-chain:list
```

2). 执行单个链式任务

```shell
php artisan schedule-chain:run-single "Chain A"
```