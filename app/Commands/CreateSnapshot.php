<?php

namespace App\Commands;

use App\Services\Bash;
use App\Services\SlackApi;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use LaravelZero\Framework\Commands\Command;

class CreateSnapshot extends Command
{
    /**
     * The signature of the command.
     * @var string
     */
    protected $signature = 'snapshots:run {env} {hash}';

    /**
     * The description of the command.
     * @var string
     */
    protected $description = 'Run Database Snapshot Routines.';

    /**
     * Execute the console command.
     * @return mixed
     */
    public function handle()
    {
        @ini_set('max_execution_time', 10080);
        @ini_set('memory_limit', '100M');
        $env = $this->argument('env');
        if(method_exists($this, $env)){
            $this->$env($this->argument('hash'));
        }
    }

    /**
     * Handle Production Environment.
     * @param $hash
     */
    protected function production($hash)
    {
        $alert = "Deploying Staging to Production";
        $this->alert($alert); SlackApi::message("*$alert*");

        $isNewRelease = (!Storage::disk('production')->exists("$hash.sql"));
        $path = config('filesystems.disks.production.root');
        $snapshot = "$path/$hash.sql";

        //Insure Directory Exists.
        $this->makeDirectory($path);

        //Create Production Snapshot from Staging Database.
        if($isNewRelease){
            $this->stopOnFailure(
                $this->task("Creating Snapshot $hash from Staging", function() use ($snapshot){
                    return Bash::script("local", 'snapshots/dump', "staging $snapshot")->isSuccessful();
                })
            );
            SlackApi::message("Created Snapshot $hash from Staging Successfully. ($snapshot)");
        }

        //Load Production Snapshot into Live Database.
        $this->stopOnFailure(
            $this->task("Loading Snapshot $hash to Production", function() use ($snapshot){
                return Bash::script("local", 'snapshots/load', "production $snapshot")->isSuccessful();
            })
        );
        SlackApi::message("Loaded Snapshot $hash to Production Successfully. ($snapshot)");

        //Cleaning Up Old Snapshots.
        if($isNewRelease){
            $this->task("Cleaning Up Old Snapshots", function() use ($path){
                return (
                    Bash::script("local", 'snapshots/trim', $path)->isSuccessful()
                );
            });
            SlackApi::message("Old Snapshots Cleaned Up Successfully.");
        }
    }

    /**
     * Handle Staging Environment.
     * @param $hash
     */
    protected function staging($hash)
    {
        $alert = "Creating Staging Database Snapshot";
        $this->alert($alert); SlackApi::message("*$alert*");

        $path = config('filesystems.disks.staging.root');
        $snapshot = "$path/$hash.sql";

        //Insure Directory Exists.
        $this->makeDirectory($path);

        //Create Snapshot for Staging Database.
        $this->stopOnFailure(
            $this->task("Create Snapshot $hash for Staging", function() use ($snapshot){
                return Bash::script("localhost", 'snapshots/dump', "staging $snapshot")->isSuccessful();
            })
        );
        SlackApi::message("Staging Snapshot Created Successfully. ($snapshot)");

        //Cleaning Up Old Snapshots.
        $this->task("Cleaning Up Old Snapshots", function() use ($path){
            return Bash::script("local", 'snapshots/trim', $path)->isSuccessful();
        });

        SlackApi::message("Old Snapshots Cleaned Up Successfully.");
    }

    /**
     * Stop on Failure
     * @param bool $status
     */
    protected function stopOnFailure(bool $status){
        if($status === false){
            SlackApi::message("An error was encountered. Process Aborted.");
            exit;
        }
    }

    /**
     * Make New Directory
     * @param string $path
     */
    protected function makeDirectory(string $path){
        if(!File::isDirectory($path)){
            File::makeDirectory($path, 0755, true);
        }
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
