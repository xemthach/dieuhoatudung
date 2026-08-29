# v1.31.1

## Executive Summary

Production hotfix for XLSX import failures when PHP `open_basedir` is enabled.

## Fixed

PhpSpreadsheet probes OOXML members such as `/xl/worksheets/sheet1.xml` while reading an `.xlsx` ZIP package. Under `open_basedir`, PHP emits a warning for that internal archive path and Laravel converts it to an exception. The application now suppresses only this narrowly identified benign probe and continues to enforce `open_basedir` for real filesystem paths.

## Validation

- Full PHPUnit: 476 tests, 475 passed, 1 skipped, 2,950 assertions, 0 failures/errors.
- Dedicated `open_basedir` XLSX regression: PASS.
- No migration added.
- No Product/catalog data was written.
- No AI provider call was made.

## Deployment

Deploy the commit for this release, clear/rebuild Laravel caches, restart the PHP handler so old OPcache is not retained, and restart OS-managed workers according to the existing live runbook. Do not disable `open_basedir` or add `/xl` to its allowed paths.

## Rollback

Checkout the previous known-good release and restart the PHP handler and managed workers. Verify the application version and import behavior afterward.
