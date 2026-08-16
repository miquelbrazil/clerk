<?php

declare(strict_types=1);

/*
 * Guards the D-014 escaping convention. Plates templates are native PHP and do
 * not auto-escape, so the convention IS the defense — this test makes a
 * regression fail the suite rather than waiting for review to catch it.
 */

use League\Plates\Engine;

/**
 * @return list<string>
 */
function templateFiles(): array
{
    $files = [];

    /** @var SplFileInfo $file */
    foreach (
        new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(ROOT . DS . 'templates')
        ) as $file
    ) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

it('escapes dynamic output rendered through a template', function (): void {
    $engine = new Engine(ROOT . DS . 'templates');
    $engine->addFolder('layout', ROOT . DS . 'templates' . DS . 'layout');

    $html = $engine->render('home', [
        'title' => '<script>alert(1)</script>',
        'tagline' => 'Bill & Co "quoted"',
    ]);

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->toContain('&lt;script&gt;')
        ->and($html)->toContain('&amp;');
});

it('has no unescaped short-echo of a variable in any template', function (): void {
    $offenders = [];

    foreach (templateFiles() as $path) {
        $contents = (string) file_get_contents($path);

        // Flags any short-echo of a non-literal expression, while allowing the
        // sanctioned helpers: $this->e(), $this->section(), $this->fetch(),
        // $this->insert() and explicitly-named *Raw helpers.
        preg_match_all('/<\?=\s*(.+?)\s*\?>/s', $contents, $matches);

        foreach ($matches[1] as $expression) {
            $allowed = preg_match(
                '/^\$this->(e|section|fetch|insert|start|stop|layout|push)\(/',
                $expression
            ) === 1;

            $isRawHelper = preg_match('/Raw\s*\(/', $expression) === 1;

            if (!$allowed && !$isRawHelper) {
                $offenders[] = basename($path) . ': ' . $expression;
            }
        }
    }

    expect($offenders)->toBe([]);
});
