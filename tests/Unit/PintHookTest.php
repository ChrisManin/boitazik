<?php

use Symfony\Component\Process\Process;

function runPintHook(array $payload): Process
{
    $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/.claude/hooks/pint.php']);
    $process->setInput(json_encode($payload));
    $process->run();

    return $process;
}

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/pint-hook-'.uniqid();
    mkdir($this->dir);
});

afterEach(function () {
    array_map('unlink', glob($this->dir.'/*'));
    rmdir($this->dir);
});

it('formats an edited php file silently', function () {
    $file = $this->dir.'/Sample.php';
    file_put_contents($file, "<?php\nfunction  foo( \$a ){return \$a;}\n");

    $process = runPintHook(['tool_input' => ['file_path' => $file]]);

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toBe('')
        ->and(file_get_contents($file))->toBe("<?php\n\nfunction foo(\$a)\n{\n    return \$a;\n}\n");
});

it('ignores non php files', function () {
    $file = $this->dir.'/notes.txt';
    $content = "function  foo( \$a ){return \$a;}\n";
    file_put_contents($file, $content);

    $process = runPintHook(['tool_input' => ['file_path' => $file]]);

    expect($process->getExitCode())->toBe(0)
        ->and(file_get_contents($file))->toBe($content);
});

it('never blocks on missing or invalid input', function (array $payload) {
    $process = runPintHook($payload);

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toBe('');
})->with([
    'no file path' => [['tool_input' => []]],
    'missing file' => [['tool_input' => ['file_path' => '/nowhere/Missing.php']]],
]);
