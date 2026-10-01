<?php

declare(strict_types=1);

namespace Kjgcoop\VikunjaToMd;

use Kjgcoop\Vikunja\Resource\AttachmentResource;

final class AttachmentDownloader
{
    public function __construct(private readonly AttachmentResource $attachments) {}

    /**
     * Downloads every attachment on every task in every bucket into $directory.
     *
     * @param  array<int,\stdClass> $buckets
     * @return array<int,string>  Attachment id => path relative to the export file, for linking.
     */
    public function downloadAll(array $buckets, string $directory): array
    {
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException("Could not create attachments directory: {$directory}");
        }

        $paths = [];

        foreach ($buckets as $bucket) {
            foreach ($bucket->tasks as $task) {
                foreach ($task->attachments ?? [] as $attachment) {
                    $paths[$attachment->id] = $this->downloadOne($task, $attachment, $directory);
                }
            }
        }

        return $paths;
    }

    private function downloadOne(\stdClass $task, \stdClass $attachment, string $directory): string
    {
        $name = AttachmentNaming::displayName($attachment);

        // Prefix with the attachment id so two attachments sharing a filename (on the same or
        // different tasks) never collide on disk.
        $filename    = "{$attachment->id}-" . preg_replace('/[\/\\\\]/', '_', $name);
        $destination = rtrim($directory, '/') . '/' . $filename;

        $this->attachments->download($task->id, $attachment->id, $destination);

        return 'attachments/' . $filename;
    }
}
