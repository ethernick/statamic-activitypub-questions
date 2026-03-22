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

