<?php

declare(strict_types=1);

namespace Kjgcoop\VikunjaToMd\Tests\Unit;

use Kjgcoop\VikunjaToMd\Exporter;
use League\HTMLToMarkdown\HtmlConverter;
use PHPUnit\Framework\TestCase;

final class ThrowingHtmlConverter extends HtmlConverter
{
    public function convert(string $html): string
    {
        throw new \RuntimeException('simulated conversion failure');
    }
}

final class ExporterTest extends TestCase
{
    private function task(
        string $title,
        bool $done = false,
        ?string $description = null,
        array $attachments = [],
    ): \stdClass {
        $task              = new \stdClass();
        $task->title       = $title;
        $task->done        = $done;
        $task->description = $description;
        $task->attachments = $attachments;

        return $task;
    }

    private function attachment(int $id, string $name): \stdClass
    {
        $attachment       = new \stdClass();
        $attachment->id   = $id;
        $attachment->name = $name;

        return $attachment;
    }

    private function bucket(string $title, array $tasks): \stdClass
    {
        $bucket        = new \stdClass();
        $bucket->title = $title;
        $bucket->tasks = $tasks;

        return $bucket;
    }

    public function testRendersBucketsAndPlainTasksAsCheckboxBullets(): void
    {
        $buckets = [
            $this->bucket('Column A', [
                $this->task('Thing 1'),
                $this->task('Thing 2', done: true),
            ]),
        ];

        $markdown = (new Exporter())->render('My Project', $buckets);

        $this->assertStringContainsString("## Column A\n", $markdown);
        $this->assertStringContainsString('* [ ] Thing 1', $markdown);
        $this->assertStringContainsString('* [x] Thing 2', $markdown);
    }

    public function testConvertsHtmlDescriptionToBlockquote(): void
    {
        $task = $this->task('Thing 2', description: '<p>Line one</p><p>Line two</p>');

        $markdown = (new Exporter())->render('P', [$this->bucket('A', [$task])]);

        $this->assertStringContainsString("  > Line one\n  >\n  > Line two", $markdown);
    }

    public function testEmptyDescriptionProducesNoBlockquote(): void
    {
        $task = $this->task('Thing 1', description: '<p></p>');

        $markdown = (new Exporter())->render('P', [$this->bucket('A', [$task])]);

        $this->assertStringNotContainsString('>', $markdown);
    }

    public function testNullDescriptionProducesNoBlockquote(): void
    {
        $markdown = (new Exporter())->render('P', [$this->bucket('A', [$this->task('Thing 1')])]);

        $this->assertStringNotContainsString('>', $markdown);
    }

    public function testAttachmentsRenderAsNestedLinksWhenDownloaded(): void
    {
        $task = $this->task('Thing 2', attachments: [$this->attachment(7, 'notes.pdf')]);

        $markdown = (new Exporter())->render('P', [$this->bucket('A', [$task])], [7 => 'attachments/notes.pdf']);

        $this->assertStringContainsString('  - [notes.pdf](attachments/notes.pdf)', $markdown);
    }

    public function testAttachmentNotDownloadedIsFlaggedRatherThanLinked(): void
    {
        $task = $this->task('Thing 2', attachments: [$this->attachment(7, 'notes.pdf')]);

        $markdown = (new Exporter())->render('P', [$this->bucket('A', [$task])], []);

        $this->assertStringContainsString('  - notes.pdf _(not downloaded)_', $markdown);
    }

    public function testFallbackPlaceholderUsedWhenConversionThrows(): void
    {
        $task = $this->task('Thing 2', description: '<p>Some real text the converter chokes on</p>');

        $markdown = (new Exporter(new ThrowingHtmlConverter()))->render('P', [$this->bucket('A', [$task])]);

        $this->assertStringContainsString('  > ' . Exporter::DESCRIPTION_FALLBACK, $markdown);
    }

    public function testDoneTaskWithDescriptionAndAttachmentsRendersAllThreeParts(): void
    {
        $task = $this->task(
            'Thing 2',
            done: true,
            description: '<p>Detail</p>',
            attachments: [$this->attachment(1, 'a.png')],
        );

        $markdown = (new Exporter())->render('P', [$this->bucket('A', [$task])], [1 => 'attachments/a.png']);

        $this->assertStringContainsString("* [x] Thing 2\n  > Detail\n  - [a.png](attachments/a.png)", $markdown);
    }
}
