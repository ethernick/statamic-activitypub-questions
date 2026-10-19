# Product Requirements Document: ActivityPubQuestions

## 1. Executive Summary
This addon introduces `Question` (Poll) object capabilities to the Statamic ActivityPub network. It handles the complete lifecycle of polls, from creation and distribution (Outbox) to receiving answers and updating results (Inbox).

## 2. Strategic Context
### Vision
To enable engaging, interactive content types directly from Statamic, fostering community interaction across the Fediverse through native poll support.

### Goals
- Extract all Question/Poll logic currently hardcoded in `ActivityPubCore` into this dedicated modular addon.
- Establish robust processing for incoming and outgoing poll responses.
- Implement a dynamic extension system in Core to allow this addon (and others) to inject custom UI components and backend handlers without Core needing to know about specific types.

## 3. Architecture & Transition Gaps (Core -> Questions)

To successfully extract polls from Core, the following architectural gaps must be addressed:

### Backend Extensibility Gaps
- **ActivityPub JSON Generation**: `ActivityPubListener` in Core currently hardcodes the JSON structure for `Question` (options, `anyOf`, `oneOf`, `endTime`). **Solution**: Core must provide a hook, interface, or event (e.g., `ActivityPubTypes::getFormatter('Question')`) that allows this addon to process the payload.
- **Inbox Handling**: Core's `InboxHandler` hardcodes the processing of incoming `Question` objects and votes. **Solution**: Refactor `InboxHandler` to dispatch events or delegate to registered type handlers (e.g., passing control to `QuestionController` or a custom `QuestionInboxHandler`).

### Frontend Extensibility Gaps (Vue)
- **Component Injection via Hooks**: `InboxNote.vue` and `Inbox.vue` hardcode Question UI and action buttons. **Solution**: Introduce a Vue Hook/Action registry system in Core (similar to WordPress hooks). Addons can register their components to specific hooks (e.g., `inbox-actions`, `inbox-note-content`), and Core will dynamically iterate and render them using `<component :is="...">`.
- **Migration**: `InboxPollForm.vue` and `InboxPollForm.v6.vue` must be physically relocated to the Questions addon, and registered via a Statamic service provider to make the assets available to the CP.

## 4. Work Log / Session History

### 2026-03-14: Modular Extraction Session
- **Backend**: Successfully refactored `ActivityPubCore` to use dynamic registries (`ActivityPubTypes`).
- **Frontend**: Implemented a global Vue hook system (`Statamic.$activitypub.hooks`) and `HookLoader.vue`.
- **Questions Addon**: Created `QuestionPayloadFormatter`, `QuestionInboxHandler`, and `PollBox.vue`.
- **Outcome**: The `Question` type is now fully modular. Core no longer contains poll-specific logic, and the Questions addon handles its own UI and logic via hooks.

### 2026-03-22: Poll Federation & Vote Tallying
- **Lifecycle Fixes**: Resolved issues with non-unique poll IDs and outbox generation.
- **Vote Tallying**: Implemented robust vote matching that resolve local polls via URI/Slug parsing. Added fallback logic to extract vote names from raw ActivityPub JSON to avoid Statamic title UUID collisions.
- **Circular Verification**: Confirmed that vote tallies trigger `Update` activities to synchronize counts across the Fediverse.

### 2026-03-25: Navigation Migration
- Refactored the addon to use the new centralized `ActivityPubNav` service from Core. Migrated the "Polls" menu item to the registry with priority `20`, ensuring it correctly appears after "Inbox" and resolving layout conflicts between addons.

### 2026-06-11: Vote TypeError Fix
- **Error Resolved**: Fixed a critical `TypeError: strtolower(): Argument #1 ($string) must be of type string, array given` in `QuestionController->vote` that crashed the Control Panel when a user tried to vote on a poll.
- **Root Cause**: Statamic's Stache builder evaluated `where('actor', $actor->id())` by calling `filterTestEquals` on every entry in the `notes` collection. Since some notes stored `actor` as a single-element array (common for entry relationships) instead of a string, this triggered a PHP TypeError inside `strtolower()`.
- **Solution**: Refactored the already-voted check to query the `notes` replying to the poll first and then execute the actor check safely in PHP memory.
- **Preload Optimization**: Applied a matching fix to the preload hook in `ActivityPubQuestionsServiceProvider` to ensure user votes are correctly identified even if `actor` is stored as an array.
### 2026-06-18: Poll Vote Outgoing Formatting Fix
- **Error Resolved**: Outgoing votes cast on polls from the CP were being interpreted by Mastodon as generic replies rather than votes.
- **Root Cause**: Votes stored as standard `Note` entries retained their HTML `<p>` content in the JSON payload and lacked the `name` property that Mastodon requires to map the vote to a poll option.
- **Solution**: Registered a new Outbox hook in `ActivityPubQuestionsServiceProvider`. The hook intercepts outgoing notes that reply to local or remote `polls`, extracts the plaintext option into the `name` property, and strips the `content` property to ensure Mastodon recognizes it as a structured poll response.
- **Session Reference**: [2026-06-18-Poll-Vote-Formatting.md](file:///private/srv/statamic/addons/ethernick/ActivityPubQuestions/docs/sessions/2026-06-18-Poll-Vote-Formatting.md)

## 5. Future Roadmap / UI Enhancements
Based on recent discovery notes, the following enhancements are planned for the Questions addon:

### Better Inbox UI for Polls
- Needs a visual way to see results directly in the Inbox feed.
- Add a small bar chart icon that, when clicked, reveals a horizontal bar chart showing percentages of voted options.

### Dedicated "Polls" Analytics Section (Implemented)
- A new section under the "ActivityPub" CP nav menu (matching the chart outline icon style).
- Displays a list of `is_internal` (local) polls.
- **Rollup Metrics**: Total active polls, total collected votes.
- **Trend Chart**: Cumulative line chart of vote counts over time, grouped by poll.
- **Masonry Layout**: Cards for each poll showing the question and a horizontal bar chart of results.
- **Deep-Dive Stack**: Clicking a card opens a slide-over stack with percentage breakdowns and voter list.
- **Inbox Integration**: A "chart" icon in the Inbox Note content that opens the corresponding Poll Stack.

### Public Frontend Poll Page
- Allow the public to vote on polls directly on the web, not just through Fediverse platforms.
- A public-facing form to submit a vote.
- Submissions increment the vote tally and trigger an ActivityPub `Update` activity.
- The poll page should include oEmbed/OpenGraph/Social Card tags for rich sharing when linked elsewhere.

---

## 6. Commercial Strategy & Versioning Policy

### Pricing Tier: Beta Early-Access ($10)
- **Marketplace Listing Price**: **$10** (Early-Access Beta pricing).
- **Price Escalation Expectations**: As fediverse client implementations and frontend public poll templates solidify, the price will scale to **$29–$39** for general v1.0 release. Existing early adopters lock in lifetime updates.
- **Semantic Versioning Strategy**: To clearly telegraph to developers and buyers that this package is in active beta prior to a frozen spec/API, versions remain in the **`v0.8.x`** series (e.g., `v0.8.0-beta.1`). Reaching `v1.0.0` will coincide with graduation out of beta pricing.
- **Actor Limitation**: Inherits `ActivityPubCore`'s licensing (Single Actor free / Multi-Actor unlocked with Core Pro $79).

---

## 7. Work Log / Session History (Continued)


### 2026-09-21: Marketplace & Packagist Release Preparation (Phases 2 & 3)
- **Status**: **Release Candidate (v1.0.0-RC1)**.
- **Package Standards**: Sanitized `composer.json` with strict schema, MIT license, Statamic addon classification, author profile, and support links. Removed stale `composer.lock`. Passed `composer validate --no-check-publish`.
- **Installer Sandbox Compatibility**: Refactored `ActivityPubQuestionsInstall.php` to use `ActivityPubUtils::blueprintPath()` so blueprint generation safely redirects to sandbox during automated tests without modifying host files.
- **Test Infrastructure**: Created `FreshInstallationTest.php`. Validated all 14 unit and integration tests passing (46 assertions).
- **Dual Bundle Compilation**: Confirmed clean Vite builds for both Vue 2 (Statamic 5.x) and Vue 3 (Statamic 6.x).
- **Production Documentation Suite**: Overhauled `README.md` and authored comprehensive documentation suite (`INSTALLATION.md`, `TROUBLESHOOTING.md`, `QUESTIONS_GUIDE.md`, `HOOKS.md`, `MARKETPLACE_LAUNCH.md`).
- **Outcome**: The addon is production-ready and fully packaged for Statamic Marketplace and Packagist release.


