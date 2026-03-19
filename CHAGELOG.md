# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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