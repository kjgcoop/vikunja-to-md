<?php

declare(strict_types=1);

namespace Kjgcoop\VikunjaToMd;

final class AttachmentNaming
{
    /**
     * The exact shape of a Vikunja attachment object hasn't been confirmed against a live
     * instance, so this tries the field names documented/observed for Vikunja's API in order of
     * likelihood rather than assuming one.
     */
    public static function displayName(\stdClass $attachment): string
    {
        return $attachment->file->name
            ?? $attachment->file_name
            ?? $attachment->filename
            ?? $attachment->name
            ?? "attachment-{$attachment->id}";
    }
}
