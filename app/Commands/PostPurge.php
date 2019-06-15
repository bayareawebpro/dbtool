<?php

namespace App\Commands;

use App\Commands\Traits\BashSuccess;
use App\Services\Bash;
use App\Services\SlackApi;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class PostPurge extends Command
{
    use BashSuccess;

    /**
     * The signature of the command.
     * @var string
     */
    protected $signature = 'post:purge {env} {path} {hash}';

    /**
     * The description of the command.
     * @var string
     */
    protected $description = 'Post Purge Old Releases';

    /**
     * Execute the console command.
     * @return mixed
     */
    public function handle()
    {
        $path = $this->argument('path');
        $hash = $this->argument('hash');
        $env = $this->argument('env');

        $project = config("envoyer.$env.project");
        $url = config("envoyer.$env.url");

        $message = "🍺 *Deployment to \"$env\" Completed Successfully!*";
        $btnText = "Envoyer.io";
        $btnUrl = "https://envoyer.io/projects/$project";

        SlackApi::message($message, $btnText, $btnUrl);
    }

    /**
     * Define the command's schedule.
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}