<?php

/**
 * Runs once, via composer.json's post-create-project-cmd, right after
 * `composer create-project telegram-bot-essentials/skeleton my-package`.
 * Renames every skeleton/Skeleton/tbe-skeleton reference to the real
 * package, then deletes itself and its own composer.json hook.
 *
 * No dependencies beyond plain PHP - this runs before we know require-dev
 * definitely finished installing, so nothing here can assume Composer's
 * autoloader or any vendor package is available yet.
 */

$root = dirname(__DIR__);

function prompt(string $question, ?string $default = null): string
{
    $suffix = $default !== null ? " [{$default}]" : '';
    fwrite(STDOUT, "{$question}{$suffix}: ");
    $answer = trim((string) fgets(STDIN));

    return $answer === '' ? ($default ?? '') : $answer;
}

function studly(string $slug): string
{
    return str_replace(['-', '_'], '', ucwords($slug, '-_'));
}

// --- Gather inputs -----------------------------------------------------

$slug = '';
while ($slug === '' || ! preg_match('/^[a-z][a-z0-9-]*$/', $slug)) {
    $slug = strtolower(prompt('Package slug (lowercase, hyphens - e.g. "billing", "channel-lock")'));
    if ($slug !== '' && ! preg_match('/^[a-z][a-z0-9-]*$/', $slug)) {
        fwrite(STDOUT, "  Must start with a letter and contain only lowercase letters, digits, and hyphens.\n");
    }
}

$description = prompt('Description', "{$slug} support built for telegram-bot-essentials/essence");
$authorName = prompt('Author name', 'Your Name');
$authorEmail = prompt('Author email', 'you@example.com');

$studly = studly($slug);
$packageName = "telegram-bot-essentials/{$slug}";
$namespace = "TelegramBotEssentials\\{$studly}";
$providerClass = "Tbe{$studly}ServiceProvider";
$translationPrefix = "tbe-{$slug}";

// --- Replace across every tracked file ----------------------------------

$replacements = [
    'telegram-bot-essentials/skeleton' => $packageName,
    // composer.json stores namespaces JSON-escaped (double backslash), plain
    // .php files use a single backslash - both forms need a search key, or
    // composer.json's autoload/extra.laravel.providers entries are missed.
    'TelegramBotEssentials\\\\Skeleton' => str_replace('\\', '\\\\', $namespace),
    'TelegramBotEssentials\\Skeleton' => $namespace,
    'TbeSkeletonServiceProvider' => $providerClass,
    'tbe-skeleton' => $translationPrefix,
    'Skeleton support built for telegram-bot-essentials/essence' => $description,
    '"Your Name"' => '"'.$authorName.'"',
    'you@example.com' => $authorEmail,
];

$skip = ['.git', 'vendor', 'bin'];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (! $file->isFile()) {
        continue;
    }

    $relative = substr($file->getPathname(), strlen($root) + 1);
    $topLevel = explode(DIRECTORY_SEPARATOR, $relative)[0];

    if (in_array($topLevel, $skip, true)) {
        continue;
    }

    $contents = file_get_contents($file->getPathname());
    $updated = strtr($contents, $replacements);

    if ($updated !== $contents) {
        file_put_contents($file->getPathname(), $updated);
    }
}

// --- Rename the provider file to match its new class name --------------

$oldProvider = $root.'/src/TbeSkeletonServiceProvider.php';
$newProvider = $root."/src/{$providerClass}.php";

if (file_exists($oldProvider)) {
    rename($oldProvider, $newProvider);
}

// --- Remove the post-create-project-cmd hook and this script -----------

$composerJsonPath = $root.'/composer.json';
$composerJson = json_decode(file_get_contents($composerJsonPath), true);
unset($composerJson['scripts']['post-create-project-cmd']);
if (empty($composerJson['scripts'])) {
    unset($composerJson['scripts']);
}
file_put_contents(
    $composerJsonPath,
    json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
);

unlink(__FILE__);
@rmdir($root.'/bin');

// --- Regenerate the autoloader: it still maps the old Skeleton namespace,
// --- since composer install ran (and wrote it) before this script did ---

if (file_exists($root.'/vendor/autoload.php')) {
    exec('cd '.escapeshellarg($root).' && composer dump-autoload -q');
}

// --- Reformat: renaming can reorder how imports should sort -------------

$pint = $root.'/vendor/bin/pint';
if (file_exists($pint)) {
    exec('cd '.escapeshellarg($root).' && '.escapeshellarg($pint));
}

// --- Fresh git history ---------------------------------------------------

if (is_dir($root.'/.git')) {
    exec('rm -rf '.escapeshellarg($root.'/.git'));
}
exec('cd '.escapeshellarg($root).' && git init -q && git add -A && git commit -q -m "chore: scaffold from telegram-bot-essentials/skeleton"');

fwrite(STDOUT, "\nDone. {$packageName} is ready in {$root}.\n");
fwrite(STDOUT, "Next: composer test && composer lint && composer analyse\n");
