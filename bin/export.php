#!/usr/bin/env php
<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Kjgcoop\Vikunja\VikunjaClient;
use Kjgcoop\VikunjaToMd\AttachmentDownloader;
use Kjgcoop\VikunjaToMd\Exporter;

require_once dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Usage: php bin/export.php <project-id> [--stdout]
 *
 * Exports a Vikunja project's kanban board to export/<slug>/<slug>.md, downloading attachments
 * into export/<slug>/attachments/ alongside it. With --stdout, prints the Markdown instead of
 * writing files; attachments are not downloaded in that mode, so their links won't resolve.
 */
function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function slugify(string $title): string
{
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

    return trim($slug, '-') ?: 'project';
}

$args      = array_slice($argv, 1);
$toStdout  = in_array('--stdout', $args, true);
$args      = array_values(array_filter($args, static fn (string $a) => $a !== '--stdout'));
$projectId = isset($args[0]) ? (int) $args[0] : 0;

if ($projectId <= 0) {
    fail("Usage: {$argv[0]} <project-id> [--stdout]");
}

$rootDir = dirname(__DIR__);

if (is_file($rootDir . '/.env')) {
    Dotenv::createImmutable($rootDir)->load();
}

$baseUrl = ($_ENV['VIKUNJA_URL'] ?? '') ?: null;
$token   = ($_ENV['VIKUNJA_TOKEN'] ?? '') ?: null;

if ($baseUrl === null || $token === null) {
    fail('VIKUNJA_URL and VIKUNJA_TOKEN must be set (see .env.example).');
}

$client = new VikunjaClient(rtrim($baseUrl, '/') . '/api/v1', $token);

try {
    $project = $client->projects()->get($projectId);
    $views   = $client->projects()->views($projectId);
} catch (\Throwable $e) {
    fail("Could not load project {$projectId}: {$e->getMessage()}");
}

$kanbanView = null;
foreach ($views as $view) {
    if (($view->view_kind ?? null) === 'kanban') {
        $kanbanView = $view;
        break;
    }
}

if ($kanbanView === null) {
    fail("Project {$projectId} ({$project->title}) has no kanban view.");
}

try {
    $buckets = $client->tasks()->forView($projectId, $kanbanView->id);
} catch (\Throwable $e) {
    fail("Could not load tasks: {$e->getMessage()}");
}

$slug      = slugify($project->title);
$outputDir = $rootDir . '/export/' . $slug;

$attachmentPaths = [];
if (!$toStdout) {
    $downloader      = new AttachmentDownloader($client->attachments());
    $attachmentPaths = $downloader->downloadAll($buckets, $outputDir . '/attachments');
}

$markdown = (new Exporter())->render($project->title, $buckets, $attachmentPaths);

if ($toStdout) {
    echo $markdown;
    exit(0);
}

if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
    fail("Could not create output directory: {$outputDir}");
}

$outputFile = $outputDir . '/' . $slug . '.md';
file_put_contents($outputFile, $markdown);

fwrite(STDERR, "Wrote {$outputFile}" . PHP_EOL);
