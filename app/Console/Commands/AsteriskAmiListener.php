<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AsteriskAmiService;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

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
    Log::info("AsteriskAmiListener: Command started.");
    $this->info("Connecting to Asterisk AMI...");
    $ami = new AsteriskAmiService();

    try {
      $ami->login();
      $this->info("Login successful. Listening for events...");

      $ami->listenForEvents(function ($event) use ($ami) {
        $eventName = $event['Event'] ?? '';
        // Log::debug("AMI Event received: " . $eventName);

        if (strtolower($eventName) === 'cdr') {
          $uniqueIdDisplay = $event['UniqueID'] ?? $event['Uniqueid'] ?? 'no-id';
          $this->info("CDR event received: " . $uniqueIdDisplay);
          Log::info("CDR event received: " . $uniqueIdDisplay, ['event' => $event]);
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
            $localPath = storage_path("app/public/recordings/{$localFilename}");

            // Ensure local recordings directory exists
            if (!File::isDirectory(storage_path('app/public/recordings'))) {
              File::makeDirectory(storage_path('app/public/recordings'), 0755, true);
            }

            // Wait a brief moment to ensure Asterisk has finished writing the file
            sleep(3);

            // Execute secure copy to pull the recording
            $asteriskHost = env('ASTERISK_AMI_HOST', '65.21.55.168');
            $this->info("Fetching recording for UniqueId: {$uniqueid} from {$asteriskHost}...");

            // Build command with properly escaped arguments
            $asteriskHost = env('ASTERISK_AMI_HOST', '65.21.55.168');
            $sshUser = env('ASTERISK_SSH_USER', 'root');
            $sshKey = env('ASTERISK_SSH_KEY');
            $identityFlag = $sshKey ? "-i " . escapeshellarg($sshKey) : "";

            $escapedRemote = escapeshellarg("{$sshUser}@{$asteriskHost}:{$remoteFile}");
            $escapedLocal = escapeshellarg($localPath);
            $scpCommand = "scp {$identityFlag} -o StrictHostKeyChecking=no {$escapedRemote} {$escapedLocal} 2>&1";

            Log::info("Executing SCP command: " . $scpCommand);
            exec($scpCommand, $scpOutput, $returnVar);

            if ($returnVar === 0 && file_exists($localPath)) {
              $this->info("Successfully downloaded recording: {$localFilename}");
              Log::info("Successfully downloaded recording: {$localFilename}");

              // Set permissions so web server can read it
              chmod($localPath, 0644);

              $recordingUrl = "/storage/recordings/{$localFilename}";
            } else {
              $errorMsg = "Failed to download recording: {$remoteFile} from {$asteriskHost}. Return code: {$returnVar}. Output: " . implode("\n", $scpOutput);
              $this->warn($errorMsg);
              Log::warning($errorMsg, ['command' => $scpCommand, 'output' => $scpOutput]);
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

            if ($existingLog && $recordingUrl) {
              $this->info("Attaching recording to existing log #{$existingLog->id}");
              $properties = $existingLog->properties->toArray();
              $properties['recording_url'] = $recordingUrl;

              // Force direct DB JSON update to bypass Spatie property mutator caching in CLI
              \Illuminate\Support\Facades\DB::table('activity_log')
                ->where('id', $existingLog->id)
                ->update(['properties' => json_encode($properties)]);

              $this->info("Successfully attached {$recordingUrl} to Activity properties!");
            } elseif ($existingLog) {
              $this->warn("No recording URL found for log #{$existingLog->id}, skipping attachment.");
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
