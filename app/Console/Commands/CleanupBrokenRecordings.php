<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\File;

class CleanupBrokenRecordings extends Command
{
  protected $signature = 'recordings:cleanup';
  protected $description = 'Remove recording_url from logs where the file does not exist locally';

  public function handle()
  {
    $logs = Activity::where('log_name', 'call')
      ->whereNotNull('properties->recording_url')
      ->get();

    $count = 0;
    foreach ($logs as $log) {
      $recordingUrl = $log->properties['recording_url'] ?? null;
      if (!$recordingUrl) continue;

      $localPath = public_path($recordingUrl);

      if (!File::exists($localPath)) {
        $this->info("Cleaning up log #{$log->id} - file not found: {$recordingUrl}");
        $properties = $log->properties->toArray();
        unset($properties['recording_url']);

        \Illuminate\Support\Facades\DB::table('activity_log')
          ->where('id', $log->id)
          ->update(['properties' => json_encode($properties)]);

        $count++;
      }
    }

    $this->info("Cleaned up {$count} logs.");
  }
}
