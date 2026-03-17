# Adlaire Platform

> **This repository's source code is published for reference purposes only.**
> **Modification, redistribution, and commercial use are prohibited under Adlaire License Ver.2.0.**
> See [docs/Licenses/LICENSE_Ver.2.0.md](docs/Licenses/LICENSE_Ver.2.0.md) for full terms.

Adlaire Platform (AP) is a flat-file CMS framework built on Deno, featuring a template engine, static site generation, a block-based editor, and a headless REST API. It requires no database and organizes all functionality into modular Framework engines for incremental extensibility.

> **Current version**: Ver.2.3-44

---

## Key Features

- **Flat-file JSON storage** -- no database required
- **Template engine** -- Mustache-style syntax (`{{var}}`, `{{#if}}`, `{{#each}}`, `{{> partial}}`, filters)
- **Static site generation** -- incremental builds with content-hash diffing
- **Headless CMS API** -- RESTful endpoints with API key auth and CORS
- **Webhook system** -- automatic dispatch on content and build events
- **i18n support** -- locale-aware rendering with translation dictionaries
- **Middleware pipeline** -- security headers, request logging, auth, CSRF, rate limiting

---

## Prerequisites

| Requirement | Version |
|-------------|---------|
| **Deno**    | 2.x or later |
| Database    | None (flat-file) |

No external dependencies are required. The framework is designed to be fully self-contained with zero third-party library imports.

---

## Getting Started

1. Clone the repository:

   ```bash
   git clone <repository-url>
   cd AdlairePlatform
   ```

2. (Optional) Configure environment variables:

   | Variable       | Description              | Default     |
   |----------------|--------------------------|-------------|
   | `AP_PORT`      | HTTP server port         | `8080`      |
   | `AP_BASE_URL`  | Base URL for API client  | `""`        |
   | `AP_TOKEN`     | API authentication token | _(none)_    |
   | `AP_LOCALE`    | Default locale           | `ja`        |

3. Start the server:

   ```bash
   deno task start
   ```

4. Open `http://localhost:8080` in your browser.

---

## Available Tasks

All tasks are defined in `deno.json` and run via `deno task <name>`:

| Task      | Command | Description |
|-----------|---------|-------------|
| `start`   | `deno task start` | Run the production server |
| `dev`     | `deno task dev`   | Run with `--watch` for automatic reload on file changes |
| `check`   | `deno task check` | Type-check all Framework and root TypeScript files |
| `lint`    | `deno task lint`  | Lint source files (recommended ruleset) |
| `fmt`     | `deno task fmt`   | Format source files (2-space indent, 100-char line width) |
| `test`    | `deno task test`  | Run tests in `tests/` with required permissions |

---

## Project Structure

```
AdlairePlatform/
├── main.ts              # HTTP server entry point (Deno.serve)
├── bootstrap.ts         # DI container init, service registration, event listeners
├── routes.ts            # Route and middleware registration
├── deno.json            # Tasks, compiler options, lint/fmt config
├── Framework/
│   ├── mod.ts           # Barrel re-export for all framework modules
│   ├── types.ts         # Shared type definitions across all frameworks
│   ├── APF/             # Adlaire Platform Foundation
│   ├── ACE/             # Adlaire Content Engine
│   ├── AIS/             # Adlaire Infrastructure Services
│   ├── ASG/             # Adlaire Static Generator
│   ├── AP/              # Adlaire Platform Controllers
│   ├── ACS/             # Adlaire Client Services
│   ├── AEB/             # Adlaire Editor & Blocks (JavaScript)
│   └── ADS/             # Adlaire Design System (CSS)
├── data/                # Runtime data (settings, content, cache)
├── themes/              # Theme templates and static assets
├── tests/               # Test suite
├── lang/                # i18n translation files
└── docs/                # Internal documentation
```

### Framework Modules

Each module follows the **Engine-Driven Model**: 5 files per framework (Core, Api, Utilities, Interface, Class).

| Module | Full Name | Role |
|--------|-----------|------|
| **APF** | Adlaire Platform Foundation | Container, Router, Request/Response, middleware pipeline, validation, utilities |
| **ACE** | Adlaire Content Engine | Collection management, content CRUD, revisions, webhooks |
| **AIS** | Adlaire Infrastructure Services | App context, i18n, event bus, diagnostics, API cache, Git/update services |
| **ASG** | Adlaire Static Generator | Static site builds, template rendering, Markdown processing, theme management |
| **AP**  | Adlaire Platform Controllers | Auth, admin, API, and dashboard controllers; security middleware (Auth, CORS, CSRF, rate limit) |
| **ACS** | Adlaire Client Services | HTTP transport abstraction, auth/storage/file service clients |
| **AEB** | Adlaire Editor & Blocks | Block-based WYSIWYG editor (frontend JavaScript) |
| **ADS** | Adlaire Design System | Design system and component styles (frontend CSS) |

---

## Development Workflow

1. Run `deno task dev` to start the server with file watching.
2. Edit source files under `Framework/` or the root entry points.
3. Run `deno task check` to verify type safety.
4. Run `deno task lint` and `deno task fmt` before committing.
5. Run `deno task test` to execute the test suite.

Documentation standards are governed by [docs/DOC_RULEBOOK.md](docs/DOC_RULEBOOK.md). Framework design rules are specified in [docs/FRAMEWORK_RULEBOOK_v2.0.md](docs/FRAMEWORK_RULEBOOK_v2.0.md). Versioning follows the cumulative scheme described in [docs/VERSIONING.md](docs/VERSIONING.md).

---

## License

> **This repository's source code is published for reference purposes only.**
> **Adlaire License Ver.2.0 prohibits modification, redistribution, and commercial use.**

This software is licensed under **Adlaire License Ver.2.0**.
Full license text: [docs/Licenses/LICENSE_Ver.2.0.md](docs/Licenses/LICENSE_Ver.2.0.md)

This software is **"Source Available, Not Open Source"**.

### Permitted

- Personal, non-commercial use
- Internal business use (no redistribution to third parties)
- Source code review for learning and research
- Contributions approved by the rights holder (copyright transfers to the rights holder)

### Prohibited

- Redistribution of source code or binaries
- Modification or derivative works without written permission
- Sublicensing
- Commercial use without a separate agreement
- Reverse engineering, decompilation, or disassembly
- Unauthorized use of "Adlaire", "Adlaire Group", "AdlairePlatform", or "AP" trademarks
- Development of competing products based on this software

---

## Copyright

Copyright (c) 2014 - 2026 Adlaire Group
All Rights Reserved.
