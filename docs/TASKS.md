# Active Tasks

> **Legend**
> - [ ] To Do
> - [/] In Progress
> - [x] Done

## Current Priority: Transition & Decoupling from Core

### Core Refactoring (Dependencies)
- [x] Refactor Core `ActivityPubListener` to remove hardcoded `Question` payload generation and allow type-specific formatting hooks.
- [x] Refactor Core `InboxHandler` to delegate incoming `Question` activities/votes to registered handlers.
- [x] Build a Vue Hook/Action registry in Core (`hooks.js`) to allow dynamic injection of UI components.
- [x] Establish a Hook naming schema (e.g. `{context}-{component}-{location}`) and create `addons/ethernick/ActivityPubCore/docs/HOOKS.md` to track available hooks.
- [x] Refactor Core Vue Components (`InboxNote.vue`) to render UI hooks using `HookLoader` and dynamic components.

### Component & Logic Migration (Questions Addon)
- [x] Port over `InboxPollForm.vue` and `InboxPollForm.v6.vue` from `ActivityPubCore` to `ActivityPubQuestions`.
- [x] Register Questions Vue components (PollBox) in `ActivityPubQuestionsServiceProvider` and its own `cp.js`.
- [x] Implement the backend payload formatter (`QuestionPayloadFormatter`) within the addon.
- [x] Implement the backend inbox handler (`QuestionInboxHandler`) for incoming `Question` activities and votes.

### Cleanup & Verification
- [x] Remove all remaining hardcoded `Question` and `Poll` references from `ActivityPubCore` backend and frontend.
- [/] Finish up processing Poll Updates (e.g., closing a poll, updating tallies).
- [x] Implement `PollVoteListener` in `ActivityPubQuestions` to process incoming `Note` replies:
    - [x] Match `Note` content/title to `Poll` options.
    - [x] Increment individual option counts and total `voters_count`.
    - [x] Track ActivityPub IDs of voters on the Poll to prevent double-counting.
- [x] Fix missing slug/title for inbox Questions to match Note behavior (optional title, defaults to UUID).
- [x] Fix JS console 404 error by building and publishing Question addon assets.
- [x] Write integration tests verifying the decoupled Question addon works seamlessly with Core.
- [x] Implement `New Poll` action injection into `Inbox.vue` via hooks.

## UI & Reporting
- [x] Register CP navigation and routes for Poll Analytics <!-- id: 36 -->
- [x] Implement `PollAnalyticsController` for backend data aggregation <!-- id: 37 -->
- [x] Create `PollDashboard.vue` with rollup metrics and masonry layout <!-- id: 38 -->
- [x] Implement cumulative line chart for vote trends <!-- id: 39 -->
- [x] Develop `PollCard.vue` and `PollDrawer.vue` for detailed stats <!-- id: 40 -->
- [x] Integrate "chart" icon hook in Inbox to open Poll Drawer <!-- id: 41 -->
- [x] Ensure poll-related activities are excluded from automatic cleanup/purging <!-- id: 42 -->
- [x] Refine SVG icon for menu and inbox to match natural Statamic CP aesthetic (flat, outlined bar chart). <!-- id: 43 -->

