<?php
/**
 * Cron Job: Process Background Jobs
 * Run every minute: * * * * * php /path/to/cron/process_jobs.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/job_queue.php';

$queue = new JobQueue();
$processed = $queue->processJobs(20);

if ($processed > 0) {
    error_log("JobQueue: Processed {$processed} jobs at " . date('Y-m-d H:i:s'));
}

$queue->cleanup(7);

echo "Job queue processed at " . date('Y-m-d H:i:s') . " - {$processed} jobs completed\n";
