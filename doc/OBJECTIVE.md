## 🎯 OBJECTIVE

Build a **modern, production-ready Laravel package for Shopify integration** that is:

* Extremely easy to install and configure
* Built for **multi-tenant Shopify apps (multiple stores)**
* Fully compliant with the **latest Shopify API (GraphQL-first)**
* Designed for **scalability, maintainability, and extensibility**

---

## ⚠️ NON-NEGOTIABLE PRINCIPLES

* Follow **SOLID principles**
* Use **clean architecture (service + contracts + DTOs)**
* Avoid fat controllers and helpers
* Everything must be **testable**
* No “quick hacks” or shortcuts
* Prefer **GraphQL over REST**, REST only as fallback
* Use **queues for all heavy operations**
* Design for **real-world production load**

---

## 🏗️ PACKAGE ARCHITECTURE

Design the package with the following structure:

```
src/
 ├── Contracts/
 ├── Services/
 ├── DTOs/
 ├── Actions/
 ├── Jobs/
 ├── Events/
 ├── Listeners/
 ├── Http/
 │    ├── Controllers/
 │    ├── Middleware/
 ├── Webhooks/
 ├── Billing/
 ├── Exceptions/
 ├── Support/
 ├── Facades/
 ├── Providers/
config/
database/
tests/
```

---

## 🔑 CORE FEATURES (MUST IMPLEMENT)

### 1. 🔐 Authentication & Store Management

* Shopify OAuth 2.0 flow
* Install & uninstall handling
* Store model (multi-tenant support)
* Secure token storage
* Re-authentication handling

---

### 2. 🔌 API LAYER (GraphQL-first)

* Central API client service
* GraphQL query/mutation builder
* REST fallback support
* Rate limit handling (retry + backoff)
* Typed DTO responses (no raw arrays)

---

### 3. ⚡ WEBHOOK SYSTEM (CRITICAL — MUST BE BEST-IN-CLASS)

Design this as a **standout feature**:

* Automatic webhook registration
* HMAC verification middleware
* Queue-based processing (Jobs)
* Idempotency (prevent duplicate processing)
* Retry mechanism
* Dead-letter handling
* Event-driven dispatching

Example flow:
Webhook → Verify → Dispatch Job → Fire Event → Listener handles logic

---

### 4. 💳 BILLING SYSTEM (HIGH PRIORITY)

* Shopify billing API integration
* Support:

  * Recurring subscriptions
  * Usage-based billing
* Middleware to enforce active subscription
* Plan management system

Example DX:

```php
Shopify::billing()->requirePlan('pro');
```

---

### 5. 🔄 APP LIFECYCLE

* Install flow
* Uninstall webhook handling
* Data cleanup strategy
* Reinstall support

---

### 6. 🧑‍💻 DEVELOPER EXPERIENCE (DX)

Provide:

#### Artisan Commands:

* `shopify:install`
* `shopify:webhook:register`
* `shopify:sync`
* `shopify:billing:setup`

#### Config:

* Clean config file
* Minimal required setup

#### Facade:

```php
Shopify::api()->query(...)
```

---

### 7. 🧠 ARCHITECTURE PATTERNS

* Service layer for business logic
* Contracts (interfaces) for all major services
* DTOs instead of arrays
* Actions for single-responsibility tasks
* Events + Listeners for decoupling
* Jobs for async processing

---

### 8. 🧪 TESTING

* PHPUnit setup
* Mock Shopify API
* Webhook simulation tests
* Billing flow tests

---

### 9. 📦 CONFIGURATION

Provide:

* `config/shopify.php`
* Environment variables:

  * SHOPIFY_API_KEY
  * SHOPIFY_API_SECRET
  * SCOPES
  * HOST

---

## 💡 ADVANCED FEATURES (DIFFERENTIATION)

You MUST include:

### 1. 🚀 GraphQL Bulk Operations

* Support large data sync (orders/products)

---

### 2. 🔁 Webhook Replay System

* Ability to reprocess failed webhooks

---

### 3. 🧩 Plugin System

* Allow extending package via modules

---

### 4. 📊 Observability

* Logging
* Debug mode
* Request tracing

---

## ⚙️ TECH STACK

* PHP 8.4+
* Laravel 13
* Redis (queue)
* Guzzle or Laravel HTTP client
* MySQL/PostgreSQL

---

## 🚀 OUTPUT REQUIREMENTS

Generate:

### 1. Full folder structure

### 2. Key implementations:

* Service Provider
* OAuth Controller
* API Client
* Webhook Controller + Middleware
* Billing Service
* Store Model + Migration

### 3. Example Usage:

* Install flow
* API call
* Webhook handling
* Billing enforcement

### 4. Sample Config File

### 5. At least 2–3 real test cases

---

## ⚠️ QUALITY BAR

* Code must look like a **popular open-source package**
* No pseudo-code
* No incomplete methods
* No vague comments
* Use proper naming conventions

---

## 🧭 FINAL INSTRUCTION

Think like you are building a package to compete with top Shopify Laravel libraries.

Prioritize:

1. Developer experience
2. Reliability
3. Scalability

---

# 💥 How to Use This (Strategic Tip)

Don’t just run this once.

Break it into phases:

1. Run → “architecture only”
2. Run → “implement API layer”
3. Run → “implement webhook system”

This is how you get **senior-level output instead of AI garbage**.

---