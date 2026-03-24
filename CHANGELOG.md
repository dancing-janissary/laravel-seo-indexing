# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2026-03-24

### Added
- Multi-language route support via `getIndexableUrls()` method on the `Indexable` trait
- When a model defines locale-specific URLs, all variants are batch-submitted on create/update/delete
- Fully backward compatible — models without `getIndexableUrls()` work exactly as before
- PHPUnit test suite (106 tests) covering all package components

## [1.0.0] - 2024-01-01

### Added
- Google Indexing API v3 integration via `google/apiclient`
- IndexNow integration (Bing, Yandex, Seznam, Naver)
- `Indexable` trait for automatic CRUD model event hooks
- `SeoIndexing` facade for manual submissions
- Queue-based job dispatch with sync fallback
- Per-engine failure isolation via `SubmitUrlJob`
- Full submission logging via `SeoIndexingLog` model
- Auto-pruning via Laravel model pruning
- Deduplication guard for rapid successive saves
- Native IndexNow batch submission (up to 10,000 URLs)
- Google API batch requests via `setUseBatch(true)`
- `withoutIndexing()` for suppressing submissions in bulk operations
- `disableIndexing()` / `enableIndexing()` static toggles