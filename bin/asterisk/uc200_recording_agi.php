#!/usr/bin/env php
<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require BASE_PATH . '/vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;

while (($line = fgets(STDIN)) !== false) {
    if (trim($line) === '') {
        break;
    }
}

Env::load(BASE_PATH . '/.env');
$config = new Config(require BASE_PATH . '/config/app.php');
$db = Database::connect($config->get('database'));

$action = $argv[1] ?? '';
$companyId = max(0, (int) ($argv[2] ?? 0));
$caller = cleanArg($argv[3] ?? '');
$callee = cleanArg($argv[4] ?? '');
$direction = cleanDirection($argv[5] ?? 'internal');
$filePath = (string) ($argv[6] ?? '');
$uniqueid = cleanArg($argv[7] ?? '');
$linkedid = cleanArg($argv[8] ?? '');

if ($companyId <= 0 || $uniqueid === '') {
    agiVerbose('UC200 recording skipped: missing company_id or uniqueid');
    exit(0);
}

if ($action === 'start') {
    $statement = $db->prepare(
        'INSERT INTO pbx_recordings (uuid, company_id, direction, caller, callee, started_at, file_path, uniqueid, linkedid, status)
         VALUES (:uuid, :company_id, :direction, :caller, :callee, NOW(), :file_path, :uniqueid, :linkedid, "recording")
         ON DUPLICATE KEY UPDATE
             caller = VALUES(caller),
             callee = VALUES(callee),
             direction = VALUES(direction),
             file_path = VALUES(file_path),
             linkedid = VALUES(linkedid),
             status = "recording",
             deleted_at = NULL'
    );
    $statement->execute([
        'uuid' => uuid(),
        'company_id' => $companyId,
        'direction' => $direction,
        'caller' => $caller,
        'callee' => $callee,
        'file_path' => $filePath,
        'uniqueid' => $uniqueid,
        'linkedid' => $linkedid !== '' ? $linkedid : null,
    ]);
    agiVerbose('UC200 recording metadata started for ' . $uniqueid);
    exit(0);
}

if ($action === 'end') {
    $statement = $db->prepare(
        'UPDATE pbx_recordings
         SET ended_at = NOW(),
             duration_seconds = IF(started_at IS NULL, 0, TIMESTAMPDIFF(SECOND, started_at, NOW())),
             status = IF(file_path <> "" AND file_path IS NOT NULL, "completed", "failed")
         WHERE uniqueid = :uniqueid'
    );
    $statement->execute(['uniqueid' => $uniqueid]);
    agiVerbose('UC200 recording metadata ended for ' . $uniqueid);
}

function cleanArg(string $value): string
{
    return substr(trim($value), 0, 120);
}

function cleanDirection(string $value): string
{
    return in_array($value, ['internal', 'inbound', 'outbound'], true) ? $value : 'internal';
}

function agiVerbose(string $message): void
{
    fwrite(STDOUT, 'VERBOSE "' . addcslashes($message, '"') . "\" 1\n");
    fflush(STDOUT);
}
