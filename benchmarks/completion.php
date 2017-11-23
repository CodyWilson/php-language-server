<?php

namespace LanguageServer\Tests;
require __DIR__ . '/../vendor/autoload.php';

use Exception;
use LanguageServer\Index\Index;
use LanguageServer\PhpDocument;
use LanguageServer\DefinitionResolver;
use LanguageServer\Protocol\Position;
use LanguageServer\CompletionProvider;
use Microsoft\PhpParser;
use phpDocumentor\Reflection\DocBlockFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

$totalSize = 0;

$framework = "symfony";

$iterator = new RecursiveDirectoryIterator(__DIR__ . "/../validation/frameworks/$framework");
$testProviderArray = array();

foreach (new RecursiveIteratorIterator($iterator) as $file) {
    if (strpos((string)$file, ".php") !== false) {
        $totalSize += $file->getSize();
        $testProviderArray[] = $file->getRealPath();
    }
}

if (count($testProviderArray) === 0) {
    throw new Exception("ERROR: Validation testsuite frameworks not found - run `git submodule update --init --recursive` to download.");
}

$index = new Index;
$definitionResolver = new DefinitionResolver($index);
$completionProvider = new CompletionProvider($definitionResolver, $index);
$docBlockFactory = DocBlockFactory::createInstance();
$completionFile = realpath(__DIR__ . '/../validation/frameworks/symfony/src/Symfony/Component/HttpFoundation/Request.php');
$parser = new PhpParser\Parser();
$completionDocument = null;

echo "Indexing $framework" . PHP_EOL;

foreach ($testProviderArray as $idx => $testCaseFile) {
    if (filesize($testCaseFile) > 100000) {
        continue;
    }
    if ($idx % 100 === 0) {
        echo $idx . '/' . count($testProviderArray) . PHP_EOL;
    }

    $fileContents = file_get_contents($testCaseFile);

    try {
        $d = new PhpDocument($testCaseFile, $fileContents, $index, $parser, $docBlockFactory, $definitionResolver);
        if ($testCaseFile === $completionFile) {
            $completionDocument = $d;
        }
    } catch (\Throwable $e) {
        echo $e->getMessage() . PHP_EOL;
        continue;
    }
}

echo "Getting completion". PHP_EOL;

$start = microtime(true);
$list = $completionProvider->provideCompletion($completionDocument, new Position(274, 15));
$end = microtime(true);

echo count($list->items) . ' completion items' . PHP_EOL;

echo "Time: " . ($end - $start) . 's' . PHP_EOL;

