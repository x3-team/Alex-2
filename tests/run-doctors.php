<?php

/**
 * Standalone checks for doctors helpers (no Laravel bootstrap required).
 * Run: php tests/run-doctors.php
 */

require __DIR__.'/../app/Services/DetectSite.php';
require __DIR__.'/../app/Support/DoctorsRedirect.php';
require __DIR__.'/../app/Support/DoctorsCopy.php';

use App\Services\DetectSite;
use App\Support\DoctorsCopy;
use App\Support\DoctorsRedirect;

$failures = 0;

function expect_same(mixed $expected, mixed $actual, string $label): void
{
    global $failures;

    if ($expected !== $actual) {
        $failures++;
        fwrite(STDERR, "FAIL {$label}: expected ".var_export($expected, true).' got '.var_export($actual, true).PHP_EOL);

        return;
    }

    echo "ok  {$label}\n";
}

expect_same(true, DetectSite::hostIsDoctors('doc.alexallergotest.ru', 'doc.alexallergotest.ru'), 'exact doc host');
expect_same(true, DetectSite::hostIsDoctors('doc.local.test', 'doc.alexallergotest.ru'), 'doc.* wildcard');
expect_same(true, DetectSite::hostIsDoctors('doctors.staging.test', 'doctors.staging.test'), 'configured non-doc host');
expect_same(false, DetectSite::hostIsDoctors('alexallergotest.ru', 'doc.alexallergotest.ru'), 'patient apex host');
expect_same(false, DetectSite::hostIsDoctors('docs.alexallergotest.ru', 'doc.alexallergotest.ru'), 'docs. is not doc.');

expect_same(true, DetectSite::pathIsDoctors('doctors', 'doctors', true), 'path /doctors');
expect_same(true, DetectSite::pathIsDoctors('doctors/login', 'doctors', true), 'path /doctors/login');
expect_same(true, DetectSite::pathIsDoctors('doctors/register', 'doctors', true), 'path /doctors/register');
expect_same(true, DetectSite::pathIsDoctors('doctors/materials', 'doctors', true), 'path /doctors/materials');
expect_same(false, DetectSite::pathIsDoctors('blog', 'doctors', true), 'patient /blog');
expect_same(false, DetectSite::pathIsDoctors('doctors/materials', 'doctors', false), 'path preview off');

expect_same('/doctors/materials', DetectSite::prefixDoctorsUrl('/materials', 'doctors', false), 'apex materials url');
expect_same('/doctors/login', DetectSite::prefixDoctorsUrl('/login', 'doctors', false), 'apex login url');
expect_same('/materials', DetectSite::prefixDoctorsUrl('/materials', 'doctors', true), 'subdomain materials url');

expect_same('/materials', DoctorsRedirect::stripPathPrefix('doctors/materials', 'doctors'), 'strip materials');
expect_same('/', DoctorsRedirect::stripPathPrefix('doctors', 'doctors'), 'strip root');
expect_same(null, DoctorsRedirect::stripPathPrefix('blog', 'doctors'), 'strip ignores blog');

expect_same('1 документ', DoctorsCopy::documentLabel(1), '1 document');
expect_same('3 документа', DoctorsCopy::documentLabel(3), '3 documents');
expect_same('5 документов', DoctorsCopy::documentLabel(5), '5 documents');
expect_same('11 документов', DoctorsCopy::documentLabel(11), '11 documents');

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} failed\n");
    exit(1);
}

echo "\nAll doctors helper checks passed.\n";
