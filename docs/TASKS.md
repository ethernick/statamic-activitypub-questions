# ActivityPubQuestions Task List & Roadmap

> **Legend**
> - [ ] To Do
> - [/] In Progress
> - [x] Done

---

## 1. Core Architecture, Data Modeling & Federation

### 1.1 ActivityPub Types & Decoupling from Core
- [x] **Core Hook & Delegate Decoupling**
  - [x] Refactor Core `ActivityPubListener` to remove hardcoded `Question` payload generation and allow type-specific formatting hooks.
  - [x] Refactor Core `InboxHandler` to delegate incoming `Question` activities/votes to registered handlers.
  - [x] Implement `QuestionPayloadFormatter` and `QuestionInboxHandler`.
- [x] **Vote Processing & Actor Deduplication**
  - [x] Implement `PollVoteListener` for processing incoming `Note` replies and vote options.
  - [x] Implement duplicate voting prevention by tracking actor URIs.
  - [x] Fix critical `TypeError` when evaluating notes with array actor fields in `QuestionController->vote`.
  - [x] Register outgoing vote formatting hook to ensure remote Mastodon compatibility.

### 1.2 Automated Testing & Sandbox Isolation
- [x] Refactor `ActivityPubQuestionsInstall.php` to use `ActivityPubUtils::blueprintPath()` to respect `ProvidesSandbox` isolation.
- [x] Create comprehensive `FreshInstallationTest.php` to verify non-destructive collection and blueprint generation.
- [x] Verify full PHPUnit test suite passes with 100% success (14/14 tests, 46 assertions).

---

## 2. Control Panel & Frontend Presentation

### 2.1 CP Analytics & Poll Drawer UI
- [x] Build dedicated CP Polls Analytics Dashboard, charts, and slide-over drawer stacks.
- [x] Port `InboxPollForm.vue` and `InboxPollForm.v6.vue` from `ActivityPubCore` to `ActivityPubQuestions`.
- [x] Register Questions Vue components (`PollBox`) in `ActivityPubQuestionsServiceProvider` and addon `cp.js`.
- [x] Build Vue Hook/Action registry integration in Core (`hooks.js`) allowing dynamic component injection.

### 2.2 Dual-Build Pipeline (Statamic 5 & 6)
- [x] Configure Vite and swapper script to verify dual frontend bundles compile cleanly (`build:v5` and `build:v6`).

---

## 3. Packaging, Developer Standards & Documentation

### 3.1 Package Metadata & Cleanup
- [x] Standardize `composer.json` for Packagist & Statamic Marketplace (`statamic-addon`, proprietary/commercial source-available license, proper author/support links, keywords).
- [x] Clean up and remove stale `composer.lock` file from repository.

### 3.2 Developer Guides & Marketplace Documentation
- [x] Overhaul `README.md` with official Statamic 5/6 badges, feature matrix, Quick Start, Mermaid architecture, and Antlers tags.
- [x] Author `docs/INSTALLATION.md` detailing system requirements, interactive installer, scheduler setup, and templates.
- [x] Author `docs/TROUBLESHOOTING.md` covering common diagnostic flows, vote tallying bugs, and CLI helpers.
- [x] Author `docs/QUESTIONS_GUIDE.md` detailing W3C ActivityStreams 2.0 `Question` schema, `oneOf`/`anyOf`, and vote lifecycles.
- [x] Author `docs/HOOKS.md` detailing backend filters, vote listeners, and CP Vue hook extension points.
- [x] Author `docs/MARKETPLACE_LAUNCH.md` with 1200x630 hero banner specs, 4 screenshot capture briefs, 15-second video storyboard, and marketing copy pack.

---

## 4. Active Release Punchlist (Pre-Launch Verification)

- [ ] **Task 4.1: Tag Release**
  - [ ] Tag release **`v0.8.0`** (Beta milestone) on GitHub.
- [ ] **Task 4.2: Packagist Registration**
  - [ ] Register repository on Packagist (`ethernick/statamic-activitypub-questions`).
  - [ ] Configure GitHub webhook for automatic package sync.
- [ ] **Task 4.3: Statamic Marketplace Listing**
  - [ ] Configure Statamic Marketplace listing:
    - Price: **$10** (Early-Access Beta price, marked to scale to $29–$39 at v1.0).
    - Version: **0.8.x**.
  - [ ] Capture CP screenshots according to `MARKETPLACE_LAUNCH.md` specifications.
  - [ ] Submit addon listing on the Statamic Marketplace portal.

---

## 5. Future Roadmap (Post-v1.0)

- [ ] **Advanced Poll Expiration Schedules**: Custom cron handlers for fine-grained, second-level poll closure notifications.
- [ ] **Multi-Choice AnyOf Extensions**: Dynamic UI rendering for multi-selection checkboxes and aggregate percentage distribution.
- [ ] **Federated Live-Poll Webhooks**: Instant client notification pushes via WebSockets when new votes are accepted.
