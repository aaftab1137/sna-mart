<?php



namespace App\Console\Commands;



use Illuminate\Console\Command;



class TestSchedule extends Command

{

    /**

     * The name and signature of the console command.

     *

     * @var string

     */

    protected $signature = 'app:test-schedule';



    /**

     * The console command description.

     *

     * @var string

     */

    protected $description = 'Command description';



    /**

     * Execute the console command.

     */

    public function handle()
    {
        \Log::info('TestSchedule command ran at: ' . now());
    }

}

