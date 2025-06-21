# AIFormPro (aiformpro-20250620_220320)

AIFormPro is a modular WordPress plugin that enables site owners to auto-generate, customize, publish, and analyze AI-powered quizzes, surveys, lead-capture, and payment forms. It provides a React-based drag-and-drop builder, branching logic, scoring, payment support, subscription-tier feature gating, GDPR/CCPA consent management, and deep integrations with AI APIs, Stripe/PayPal, CRM platforms, and WordPress via Gutenberg blocks, shortcodes, widgets, and template functions.

---

## Table of Contents

1. [Overview](#overview)  
2. [Features](#features)  
3. [Installation](#installation)  
4. [Usage](#usage)  
5. [Components](#components)  
6. [Dependencies](#dependencies)  
7. [Development & Testing](#development--testing)  
8. [Support & Contribution](#support--contribution)  
9. [License](#license)  

---

## Overview

AIFormPro streamlines the creation of intelligent, interactive forms by combining AI-driven content generation with a powerful visual builder and advanced back-end services:

- Auto-generate questions and templates via external AI APIs  
- Drag-and-drop React builder with branching logic & scoring  
- Payment integration (Stripe & PayPal), subscription-tier gating  
- GDPR/CCPA consent capture & logging  
- Analytics dashboard with Chart.js charts, CSV export, CRM sync  
- Embeddable via Gutenberg blocks, shortcodes, widgets, and template tags  

---

## Features

- **React Form Builder**: Field palette, property sidebar, template library, styling presets, live preview  
- **Conditional Logic & Scoring**: Real-time show/hide rules, point assignments, partial submissions  
- **AI Integration**: Prompt templates, response caching, rate limiting, error handling  
- **Payments**: Secure Stripe/PayPal tokenization, webhooks, checkout flows  
- **Consent Management**: GDPR/CCPA checkboxes, logged with timestamp & IP  
- **Embedding Options**: Gutenberg block `<ai-form>`, `[ai_form id=""]` shortcode, widget, and template functions  
- **Analytics & Reporting**: Aggregation, AI-powered summaries, Chart.js dashboards, CSV export, CRM synchronization  
- **Cron & Maintenance**: License checks, analytics cleanup, email digests, DB optimization  

---

## Installation

1. **Download** the `aiformpro-20250620_220320.zip` package.  
2. **Upload & Activate**  
   - In WordPress Admin ? Plugins ? Add New ? Upload Plugin ? Choose ZIP and Install ? Activate.  
3. **Setup**  
   1. Go to **Admin ? AIFormPro ? General**  
   2. Enter your AI API credentials, payment gateway keys, CRM details, styling presets, and license key.  
   3. Save settings.  

---

## Usage

### 1. Create a Form

1. In Admin ? **AIFormPro ? Forms**, click **Add New**.  
2. Choose an AI-generated template or start from scratch.  
3. Use the **React Builder** to drag fields, configure properties, branching logic, scoring, and consent fields.  
4. Preview and **Save**.

### 2. Embed a Form

- **Gutenberg Block**  
  In the editor, add the **AIFormPro Form** block, select your form, and publish.

- **Shortcode**  
  ```php
  echo do_shortcode('[ai_form id="123"]');
  ```

- **Widget**  
  In Appearance ? Widgets, add **AI Form Pro** widget and select your form.

- **Template Function**  
  ```php
  if ( function_exists('aiformpro_render_form') ) {
    aiformpro_render_form(123);
  }
  ```

### 3. Form Interaction

- Live logic & scoring run via AJAX/fetch calls to REST endpoints.  
- On final submission, payments are processed (if any), consent is recorded, notifications are sent, and entries stored.

### 4. Analytics & Reporting

- View dashboards under **Admin ? AIFormPro ? Reports**  
- Download CSV exports or sync data with your CRM.

---

## Components

### Core Bootstrap & Loader

- **pluginmain.php**  
  - Plugin header, activation/deactivation hooks, i18n, constants, CPT & taxonomy registration, REST routes, global asset enqueue.  
- **classAiFormProPlugin.php**  
  - Orchestrates core modules, initializes services, adds WP hooks & filters.

### Settings & License

- **classSettingsPage.php**  
  - Admin menus (General, AI, Payments, Integrations, Styling, Licenses), settings forms, remote license checks, tier gating, admin CSS.

### AI Integration

- **classAiIntegrationService.php**  
  - Wraps AI APIs, builds prompts, caches responses, rate limiting, error handling.

### Form Builder UI

- **classFormBuilderUI.php**  
  - Registers/react-renders drag-and-drop builder; enqueues `assets/js/builder.js` and `assets/css/builder.css`.

### Conditional Logic & Scoring

- **classConditionalLogicEngine.php**  
  - Evaluates branching rules, show/hide logic, point assignments, partial submissions.

### Payment Processor

- **classPaymentProcessor.php**  
  - Integrates Stripe & PayPal SDKs, tokenization, checkout flows, webhook handlers.

### Submission Handling & Notifications

- **classSubmissionHandler.php**  
  - Defines REST endpoints, validation & sanitization, entry storage, email/SMS notifications.

### Consent Management

- **classConsentManager.php**  
  - Injects GDPR/CCPA checkboxes, logs user consent in custom table.

### Front-End Rendering

- **classGutenbergBlocks.php**  
  - Registers `aiformpro/form` block, editor scripts, and render callbacks.  
- **classWidgetIntegration.php**  
  - Registers `[ai_form]` shortcode and ?AI Form Pro? widget.

### Analytics & Reporting

- **classAnalyticsService.php**  
  - Aggregates submission data, schedules summarization, renders Chart.js dashboards, CSV export, CRM sync.

### Cron & Maintenance

- **classCronManager.php**  
  - Schedules recurring tasks: license checks, data cleanup, email digests, DB optimization.

### Shared & Support Files

- **assets/js/builder.js** ? React builder bundle  
- **assets/css/builder.css** ? Builder styling  
- **assets/css/admin.css** ? Admin UI styling  

---

## Dependencies

- WordPress ? 5.0  
- PHP ? 7.4  
- AI API Key (OpenAI, Anthropic, etc.)  
- Stripe & PayPal API credentials  
- Chart.js (bundled)  
- Recommended: WP Cron enabled, HTTPS on front-end for secure payments  

---

## Development & Testing

- Follow [PSR-4](https://www.php-fig.org/psr/psr-4/) autoloading for PHP  
- Use WP Coding Standards for PHP, JS, CSS  
- **Unit Tests**: PHPUnit for core classes  
- **Integration Tests**: WP REST API endpoints  
- **Build Tooling**: Webpack for React builder assets  

To run tests:  
```bash
composer install
npm install
npm run build
vendor/bin/phpunit
```

---

## Support & Contribution

- Report bugs or feature requests on the GitHub [Issues](https://github.com/your-org/aiformpro/issues) page.  
- Contributions welcome via pull requests?please follow the development guidelines and include tests.

---

## License

MIT License ? [Your Name or Company]

```text
Permission is hereby granted, free of charge, to any person obtaining a copy...
```

---

*Thank you for using AIFormPro!*