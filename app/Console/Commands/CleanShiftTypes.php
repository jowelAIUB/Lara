<?php

namespace Lara\Console\Commands;

use Illuminate\Console\Command;
use Lara\Jobtype;
use Lara\ScheduleEntry;
use Log;

class CleanShiftTypes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lara:clean-shifttypes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove all ShiftTypes that are never used from the DB.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Inform the users
        $this->info(''); // new line
        Log::info('Starting shift type cleanup...');
        $this->info('Starting shift type cleanup...');

        // Start counting time before processing every person
        $counterStart = microtime(true);

        // Retrieve all ShiftTypes,
        // and sort them backwards to delete rarely used latest entries first and avoid costly DB queries on older ones that are used more
        $shifttypes = Jobtype::orderBy('id', 'desc')->get();

        // Initialize progress bar
        $bar = $this->output->createProgressBar(count($shifttypes));

        // Iterate through all ShiftTypes, check if there is any ScheduleEntry it is used in, and if not - delete this ShiftType
        foreach ($shifttypes as $shifttype) {

            // Get corresponding ScheduleEntries
            $scheduleEntries = ScheduleEntry::where('jbtyp_id', '=', $shifttype->id)->get();

            if (  $scheduleEntries->count() === 0  ) {
                
                // Step 1: if this shift type is not used anywhere it can be remove without side effects
                
                // Log the action while we still have the data
                $this->info(''); // new line
                $this->info('Shift type deleted: "' . $shifttype->jbtyp_title . '" (id:' . $shifttype->id . '), it was not used in any schedule.');
                
                // Now delete the jobtype
                Jobtype::destroy($shifttype->id);
            } else {

                // Step 2: check if there exists another shift with the same name and duration
                // 
                // At this step, for every combination there will be one or more ShiftTypes.
                // We started checking backwards, so if the first one we get has the same id, this shift is the only representation of 
                // this name&times combination -> do nothing.
                // If they mismatch, another one is found, we want to delete this one (the newest) and copy the id of the very first one 
                // to every schedule entry that used current shift type -> this will get us n-1 shift types for this combination.
                
                // Get a the first shift type with this combination of name&times
                $alternative = JobType::where('jbtyp_title', '=', $shifttype->jbtyp_title)
                                      ->where('jbtyp_time_start', '=', $shifttype->jbtyp_time_start)
                                      ->where('jbtyp_time_end', '=', $shifttype->jbtyp_time_end)
                                      ->first();

                // Check if we have found current shift type or another one
                if ($alternative->id !== $shifttype->id) {
                    // Another version exists

                    // Substitute this shift type for the alternative
                    foreach ($scheduleEntries as $entry) {
                        $entry->jbtyp_id = $alternative->id;
                        $entry->save();
                    }

                    // inform the user
                    $this->info(''); // new line
                    $this->info('Substituted "' . $shifttype->jbtyp_title . '" with id ' . $shifttype->id 
                                . " for id " . $alternative->id . " in " . $scheduleEntries->count() . " shifts.");

                    // This shift type is empty now - delete it
                    // Log the action while we still have the data
                    $this->info(''); // new line
                    $this->info('Shift type deleted: "' . $shifttype->jbtyp_title . '" (id:' . $shifttype->id . '), it was not used in any schedule.');
                    
                    // Now delete the jobtype
                    JobType::destroy($shifttype->id);


                }   // Else this is the only shifttype with this name&times combination -> do nothing.
        
            }

            // Adjust progress bar
            $bar->advance();
        }

        // Finish progress bar and end counter
        $bar->finish();
        $counterEnd = microtime(true);

        // Inform the users
        $this->info(''); // new line
        $this->info(''); // new line
        Log::info('Finished shift type cleanup after ' . ($counterEnd - $counterStart) . ' seconds.');
        $this->info('Finished shift type cleanup after ' . ($counterEnd - $counterStart) . ' seconds.');
    }
}
