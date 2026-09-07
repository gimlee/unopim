# UnoPim Project Context & Technical Overview

## 1. Project Overview

**UnoPim** is an enterprise-grade, open-source Product Information Management (PIM) system built on top of Laravel 13 and PHP 8.4. It serves as a centralized hub for organizing, enriching, categorizing, and publishing product data across multiple channels, locales, and currencies.

The application combines a high-performance modular backend with a modern administration UI (Blade + Vue 3 + TailwindCSS), background queued pipelines for large-scale data transfers, dual-mode AI capabilities (generative content via Magic AI and autonomous copilot via AI Agent), and specialized cross-border e-commerce features (standardized category taxonomy, multi-platform category mapping, and automated foreign exchange rate updates).

---

## 2. Core Technology Stack

| Layer / Concern | Technology / Library | Description |
| :--- | :--- | :--- |
| **Language** | PHP `^8.4.1` | Uses modern PHP 8.4 features (typed properties, constructor promotion, attributes, enums, match expressions). |
| **Framework** | Laravel `^13.0` | Application skeleton, routing, queue system, events, Eloquent ORM. |
| **Modular Architecture** | Konekt Concord `^1.16` | Dynamic modular package system (`packages/Webkul/*`), contract bindings, model proxies. |
| **Relational Databases** | MySQL 8.0.32+ / PostgreSQL 16+ / MariaDB | Full multi-driver database support. |
| **Search Engine** | Elasticsearch `^8.17` | Optional catalog query builder and scalable indexing engine. |
| **Caching & Queues** | Redis / Database / Predis `^3.4` | Background queues: `webhooks`, `system`, `default`, `completeness`, `publication`. |
| **Frontend Framework** | Vue 3 + Vite + TailwindCSS | Embedded reactive components inside Laravel Blade templates. |
| **Data Grid Engine** | UnoPim DataGrid (`Webkul\DataGrid`) | High-performance datatable system with backend filtering, sorting, pagination, and mass actions. |
| **API & Authentication** | Laravel Passport `^13.7` & Sanctum `^4.0` | OAuth2 (Password & Client Credentials grants) and personal API tokens. |
| **AI Integration** | `laravel/ai` + Custom Magic AI Adapters | 10+ LLM providers (OpenAI, Anthropic Claude, Google Gemini, DeepSeek, Zhipu GLM, Ollama, Groq, xAI, etc.). |
| **Testing & Quality** | Pest 5.0, PHPUnit 13.2, Larastan/PHPStan, Laravel Pint, Rector | Test-driven with Impact Analysis (TIA) support and automated linting. |

---

## 3. Package & Directory Structure

UnoPim organizes its domain logic into modular packages under `packages/Webkul/`:

```
unopim/
├── app/                           # Application core (ExchangeRateService, Console commands, Providers)
│   ├── Console/Commands/          # Custom artisan commands (e.g. RefreshExchangeRates)
│   ├── Http/Controllers/          # Root controllers (Admin and API exchange rates)
│   ├── Providers/                 # Core service providers (AppServiceProvider)
│   └── Services/                  # Application-wide domain services
├── bootstrap/                     # Framework bootstrap, providers.php, app.php
├── config/                        # Laravel and package configuration files
├── docs/                          # Architectural and operational documentation
├── openspec/                      # OpenSpec root (specifications, project context, changes)
│   ├── project.md                 # This project context document
│   ├── architecture.md            # In-depth architectural specification
│   ├── config.yaml                # OpenSpec configuration
│   └── specs/                     # Core capability specifications
├── packages/Webkul/               # Modular domain packages (Concord modules)
│   ├── Admin/                     # Admin UI layout, controllers, Blade views, assets
│   ├── AdminApi/                  # OAuth2 RESTful APIs, data sources, API provisioning
│   ├── AiAgent/                   # Autonomous conversational agent, tool registry, chat context
│   ├── AppUrlGuard/               # URL security & protection
│   ├── Attribute/                 # Attributes, families, groups, options, scoping
│   ├── Category/                  # Nested set categories, platform taxonomies, AI/rule classifiers
│   ├── Completeness/              # Channel & locale product completeness scoring
│   ├── Core/                      # Base foundation, channels, locales, currencies, repositories
│   ├── DataGrid/                  # Datagrid generation and filtering engine
│   ├── DataTransfer/              # Bulk import & export engine (CSV, XLSX, ZIP)
│   ├── DebugBar/                  # Laravel Debugbar package integration
│   ├── ElasticSearch/             # Elasticsearch indexer & query builder
│   ├── HistoryControl/            # Audit history, versioning, change tracking
│   ├── Installer/                 # Installation CLI and web wizard
│   ├── MagicAI/                   # Multi-platform LLM gateway, prompts, structured translation
│   ├── Measurement/               # Measurement families, conversion factors, units of measure
│   ├── Notification/              # Internal system notifications
│   ├── Product/                   # Product models, EAV values, variant structures, associations
│   ├── ProductPassport/           # Digital Product Passport (DPP) templates, QR carriers, JSON-LD
│   ├── Publication/               # Publication channels, payload versions, queued publishing
│   ├── Resource/                  # Resource asset management
│   ├── Theme/                     # Theme management
│   ├── User/                      # Admin users, roles, ACL permissions
│   └── Webhook/                   # Asynchronous webhook events and delivery logs
├── public/                        # Public entry points and compiled assets
├── routes/                        # Application web and API route definitions
└── SQL/                           # Reference and audit SQL scripts
```

---

## 4. Key Architectural Patterns

### 4.1 Modular Monolith via Concord
Each package under `packages/Webkul/` functions as an autonomous module containing its own:
- **Models & Proxies**: Eloquent models with Concord proxy interfaces (e.g. `ProductProxy::modelClass()`) allowing clean overrides without hardcoding class names.
- **Repositories**: Extending `Webkul\Core\Eloquent\Repository` based on Prettus L5 Repository.
- **Database Migrations & Seeders**: Module-scoped migrations registered in each package service provider.
- **Routes & Middleware**: Modular route definitions (`web.php`, `api.php`, `catalog-routes.php`).
- **Translations & Views**: Multi-locale Blade templates and JSON translation files.

### 4.2 Hybrid Product Data Model (EAV in JSON)
Products store their core identity columns (`id`, `sku`, `type`, `parent_id`, `attribute_family_id`, `status`, `variant_structure_id`) in relational tables, while dynamic attribute values are stored in a structured JSON column (`values`) partitioned into four scopes:
1. `common`: Global values independent of channel and locale (e.g. dimensions, brand, GTIN).
2. `locale_specific`: Values that vary solely by language/locale (e.g. descriptions, localized titles).
3. `channel_specific`: Values that vary solely by distribution channel (e.g. channel flags).
4. `channel_locale_specific`: Values that vary simultaneously by channel and locale (e.g. channel-tailored names, SEO copy).

### 4.3 High-Throughput Background Queues
Asynchronous workloads are divided across dedicated Redis/Database queues to prevent blocking web requests:
- `webhooks`: Outgoing HTTP notifications to third-party subscribers.
- `publication`: Digital Product Passport and publication channel version generation.
- `completeness`: Real-time and bulk recalculation of product completeness scores.
- `system`: Scheduled tasks, data synchronization, exchange rate refreshes.
- `default`: General background tasks, email notifications, export jobs.

### 4.4 Multi-Platform AI & Dual Intelligence
UnoPim integrates AI at two levels:
1. **Magic AI (`Webkul\MagicAI`)**: Admin-configured LLM platforms with AES-256 encrypted API keys, dynamic model listing, and structured task agents for product description generation, image creation, and multi-locale attribute translation.
2. **AI Agent (`Webkul\AiAgent`)**: A chat copilot featuring a permission-gated `ToolRegistry` that can perform multi-step operations (catalog summaries, creating categories/products, bulk attribute updates) with strict permission checks and rollback boundaries.

### 4.5 Cross-Border E-Commerce & Platform Taxonomies
- **Platform Category Mapping**: Bridges UnoPim standard categories with external e-commerce marketplaces (e.g., TikTok Shop MY category tree) and supplier platforms (e.g., 1688).
- **Dual Classification Engine**: Classifies products using rule-based criteria (1688 source mappings, aliases, keyword rules) or AI classification (Magic AI with bounded deterministic candidate pre-filtering to prevent hallucinations).
- **Multi-Currency System**: Automatically fetches exchange rates from the Frankfurter API every 3 hours with multi-tiered fallback and supports distinct real vs. commercial selling rates.

---

## 5. Development Conventions & Guidelines

- **Strictly Code-Safe**: Do not modify application core files without running test suites.
- **Strict Typing**: Declare `declare(strict_types=1);` where appropriate, specify parameter and return types on all class methods.
- **Model Proxies**: Always resolve model classes through their Concord proxy (e.g., `AttributeFamilyProxy::modelClass()`) rather than direct Eloquent model references.
- **Repository Pattern**: Perform data mutations and queries through repositories when available to maintain event hooks.
- **Event Hooks**: Dispatch standard Laravel lifecycle events (e.g. `catalog.product.create.after`, `catalog.product.update.after`) to trigger completeness updates and webhook dispatches.
- **Database Compatibility**: Write database migrations and queries compatible with both MySQL 8 and PostgreSQL 16 (avoid vendor-specific SQL dialect in core migrations).
