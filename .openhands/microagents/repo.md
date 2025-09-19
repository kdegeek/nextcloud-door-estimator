# Repository Overview

This repository contains a Nextcloud app that provides a professional door and hardware estimating system. It replaces spreadsheet-based workflows with a modern Vue 3 + TypeScript frontend and a PHP (Nextcloud) backend, preserving complex pricing logic while adding quoting, PDF generation, and admin features.

## Structure at a Glance
- appinfo/ – App metadata, routes, bootstrap
- lib/ – PHP backend (Controllers, Services, Migrations, Commands)
- src/ – Vue 3 + TypeScript frontend (built with Vite)
- templates/ – PHP templates rendered by Nextcloud
- tests/ – Frontend (Jest) and PHP (PHPUnit) tests, incl. integration/e2e
- scripts/ – Utilities for data extraction/import and build/test helpers
- package.json / vite.config.js – Frontend build and dev tooling
- composer.json / phpunit.xml – PHP dependencies and test config

## Quickstart for Developers
Prerequisites: Node.js 20+, npm 10+, PHP 8+, a compatible Nextcloud (v25+). For local frontend work:

- Install dependencies: `npm install`
- Build production assets: `npm run build`
- Dev server (fast rebuilds): `npm run dev`
- Type-check: `npm run type-check`

## Tests and Quality
- Frontend tests (Jest): `npm test` or `npm run test:frontend`
- PHP tests (PHPUnit): `npm run test:php` (or `composer test`)
- All tests (CI-like): `npm run test:ci`
- Linting: `npm run lint` and `npm run stylelint`
- Clean build artifacts: `npm run clean`

## Helpful Notes
- Edit frontend code in `src/`; built assets are emitted for Nextcloud to serve.
- Backend API/controllers live under `lib/`. Start with `EstimatorController` and related services.
- When integrating with a real Nextcloud instance, enable the app and deploy built assets (`npm run build`).
- See README.md for detailed features and environment setup guidance.
