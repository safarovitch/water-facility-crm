<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AsteriskAmiService;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

class AsteriskAmiListener extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'asterisk:listen';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Listen to Asterisk AMI events to log offline missed calls';

  /**
   * Execute the console command.
   */
  public function handle()
  {
    $this->info("Connecting to Asterisk AMI...");
    $ami = new AsteriskAmiService();

    try {
      $ami->login();
      $this->info("Login successful. Listening for events...");

      $ami->listenForEvents(function ($event) use ($ami) {
        $eventName = $event['Event'] ?? '';
        $this->info("Event received: " . $eventName); // Uncommented for verbose debugging

        if (strtolower($eventName) === 'cdr') {
          $this->info("CDR event received: " . ($event['UniqueID'] ?? $event['Uniqueid'] ?? 'no-id'));
          $this->info("Event data: " . json_encode($event));
          $disposition = $event['Disposition'] ?? '';
          $destination = $event['Destination'] ?? '';
          $source = $event['Source'] ?? '';
          $duration = $event['Duration'] ?? 0;
          $uniqueid = $event['UniqueID'] ?? '';

          if (!$destination || !$source) return;

          // Attempt to find user
          $user = User::where('sip_extension', $destination)
            ->orWhere('sip_extension', $source)
            ->first();

          if (!$user) return;

          $direction = ($user->sip_extension === $source) ? 'outbound' : 'inbound';
          $remotePhone = ($direction === 'outbound') ? $destination : $source;

          // Process MISSED calls
          if (in_array($disposition, ['NO ANSWER', 'CANCEL', 'BUSY', 'FAILED']) && $direction === 'inbound') {
            $recentLogExists = Activity::where('log_name', 'call')
              ->where('causer_id', $user->id)
              ->where('causer_type', get_class($user))
              ->whereJsonContains('properties->phone', $remotePhone)
              ->where('created_at', '>=', now()->subMinutes(2))
              ->exists();

            if (!$recentLogExists) {
              $this->info("Missed inbound call to user {$user->name} ({$destination}) from {$source}");
              activity('call')
                ->causedBy($user)
                ->withProperties([
                  'phone' => $remotePhone,
                  'duration' => (int)$duration,
                  'direction' => 'inbound',
                  'status' => 'missed',
                  'offline' => true,
                ])
                ->log("Missed inbound call to/from {$remotePhone} (Offline)");
            }
          }

          // Process ANSWERED calls and fetch the REC file
          if ($disposition === 'ANSWERED' && $uniqueid) {
            $recordingUrl = null;
            $remoteFile = "/var/spool/asterisk/monitor/{$uniqueid}.wav";
            $localFilename = "{$uniqueid}.wav";
            $localPath = public_path("recordings/{$localFilename}");

            // Execute secure copy to pull the recording
            $this->info("Fetching recording for UniqueId: {$uniqueid}...");
            $scpCommand = "scp -o StrictHostKeyChecking=no root@65.21.55.168:{$remoteFile} {$localPath} 2>/dev/null";
            exec($scpCommand, $output, $returnVar);

            if ($returnVar === 0 && file_exists($localPath)) {
              $this->info("Successfully downloaded recording: {$localFilename}");
              $recordingUrl = "/recordings/{$localFilename}";
            } else {
              $this->warn("Failed to download recording: {$remoteFile}");
            }

            // Wait a brief moment to ensure the frontend HTTP API finished saving the log first
            sleep(2);

            // Sync with existing Activity Log or Create new one (Offline Zoiper)
            $existingLog = Activity::where('log_name', 'call')
              ->where('causer_id', $user->id)
              ->where('causer_type', get_class($user))
              ->whereJsonContains('properties->phone', $remotePhone)
              ->where('created_at', '>=', now()->subMinutes(5)) // broader window for answered
              ->orderBy('id', 'desc')
              ->first();

            if ($existingLog) {
              $this->info("Attaching recording to existing log #{$existingLog->id}");
              $properties = $existingLog->properties->toArray();
              $properties['recording_url'] = $recordingUrl;

              // Force direct DB JSON update to bypass Spatie property mutator caching in CLI
              \Illuminate\Support\Facades\DB::table('activity_log')
                ->where('id', $existingLog->id)
                ->update(['properties' => json_encode($properties)]);

              $this->info("Successfully attached {$recordingUrl} to Activity properties!");
            } else {
              $this->info("No frontend log found. Creating offline log for answered call {$remotePhone}");
              activity('call')
                ->causedBy($user)
                ->withProperties([
                  'phone' => $remotePhone,
                  'duration' => (int)$duration,
                  'direction' => $direction,
                  'status' => 'answered',
                  'offline' => true,
                  'recording_url' => $recordingUrl,
                ])
                ->log(ucfirst('answered') . ' ' . $direction . ' call to/from ' . $remotePhone . ' (Offline)');
            }
          }
        }
      });
    } catch (\Exception $e) {
      $this->error("AMI Connection closed or failed: " . $e->getMessage());
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }
}
