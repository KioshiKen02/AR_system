<?php

namespace Tests\Unit;

use Tests\TestCase;

class NotedByPartialTest extends TestCase
{
    public function test_it_is_hidden_when_no_noted_by_exists(): void
    {
        $htmlNull = view('pdf.components.noted_by', ['notedBy' => null])->render();
        $htmlEmpty = view('pdf.components.noted_by', ['notedBy' => ''])->render();
        $htmlWhitespace = view('pdf.components.noted_by', ['notedBy' => '   '])->render();
        $htmlArrayEmpty = view('pdf.components.noted_by', ['notedBy' => ['', null, '  ']])->render();

        $this->assertSame('', trim($htmlNull));
        $this->assertSame('', trim($htmlEmpty));
        $this->assertSame('', trim($htmlWhitespace));
        $this->assertSame('', trim($htmlArrayEmpty));
    }

    public function test_it_renders_for_exactly_one_entry(): void
    {
        $html = view('pdf.components.noted_by', ['notedBy' => 'ALEXIO AVENIDO JR.'])->render();

        $this->assertStringContainsString('Note By:', $html);
        $this->assertStringContainsString('ALEXIO AVENIDO JR.', $html);
        $this->assertStringContainsString('(Signature Over Printed Name)', $html);
        $this->assertStringContainsString('signatory-compact-container', $html);
    }

    public function test_it_does_not_render_for_multiple_entries_and_is_reasonably_fast(): void
    {
        $multiple = view('pdf.components.noted_by', ['notedBy' => ['A', 'B']])->render();
        $this->assertSame('', trim($multiple));

        $start = microtime(true);
        for ($i = 0; $i < 250; $i++) {
            view('pdf.components.noted_by', ['notedBy' => 'ALEXIO AVENIDO JR.'])->render();
        }
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(2.0, $elapsed);
    }
}

