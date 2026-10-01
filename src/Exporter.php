<?php

declare(strict_types=1);

namespace Kjgcoop\VikunjaToMd;

use League\HTMLToMarkdown\HtmlConverter;

final class Exporter
{
    public const DESCRIPTION_FALLBACK = '[[[SEE DESCRIPTION]]]';

    public function __construct(private readonly HtmlConverter $htmlConverter = new HtmlConverter()) {}

    /**
     * Renders a kanban project's buckets as a single Markdown document.
     *
     * Assumes Vikunja stores task descriptions as HTML (its editor is TipTap-based), not Markdown.
     * If a real description turns out to already be Markdown, this will mostly pass it through
     * unchanged (there are no tags for the converter to act on) rather than mangling it, but that
     * assumption is unverified against a live instance.
     *
     * @param  array<int,\stdClass>  $buckets          As returned by TaskResource::forView().
     * @param  array<int,string>     $attachmentPaths  Attachment id => relative path to link to.
     */
    public function render(string $projectTitle, array $buckets, array $attachmentPaths = []): string
    {
        $lines = ["# {$projectTitle}", ''];

        foreach ($buckets as $bucket) {
            $lines[] = "## {$bucket->title}";
            $lines[] = '';

            foreach ($bucket->tasks as $task) {
                $lines = [...$lines, ...$this->renderTask($task, $attachmentPaths)];
            }
        }

        return rtrim(implode("\n", $lines)) . "\n";
    }

    /**
     * @param  array<int,string> $attachmentPaths
     * @return array<int,string>
     */
    private function renderTask(\stdClass $task, array $attachmentPaths): array
    {
        $checkbox = ($task->done ?? false) ? '[x]' : '[ ]';
        $lines    = ["* {$checkbox} {$task->title}"];

        $description = $this->renderDescription($task->description ?? null);
        if ($description !== null) {
            $lines[] = $description;
        }

        foreach ($task->attachments ?? [] as $attachment) {
            $lines[] = $this->renderAttachmentLink($attachment, $attachmentPaths);
        }

        return $lines;
    }

    private function renderDescription(?string $html): ?string
    {
        if ($html === null || trim(strip_tags($html)) === '') {
            return null;
        }

        try {
            $markdown = trim($this->htmlConverter->convert($html));
        } catch (\Throwable) {
            $markdown = '';
        }

        if ($markdown === '') {
            $markdown = self::DESCRIPTION_FALLBACK;
        }

        $quoted = array_map(
            static fn (string $line) => rtrim('  > ' . $line),
            explode("\n", $markdown),
        );

        return implode("\n", $quoted);
    }

    /** @param array<int,string> $attachmentPaths */
    private function renderAttachmentLink(\stdClass $attachment, array $attachmentPaths): string
    {
        $name = AttachmentNaming::displayName($attachment);
        $path = $attachmentPaths[$attachment->id] ?? null;

        return $path !== null
            ? "  - [{$name}]({$path})"
            : "  - {$name} _(not downloaded)_";
    }

}
