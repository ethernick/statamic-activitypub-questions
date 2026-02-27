# Product Requirements Document: ActivityPubQuestions

## 1. Executive Summary
This addon introduces `Question` (Poll) object capabilities to the Statamic ActivityPub network. It handles the complete lifecycle of polls, from creation and distribution (Outbox) to receiving answers and updating results (Inbox).

## 2. Strategic Context
### Vision
To enable engaging, interactive content types directly from Statamic, fostering community interaction across the Fediverse through native poll support.

### Goals
- Port and refactor existing poll logic into this dedicated modular addon.
- Establish robust processing for incoming and outgoing poll responses.
- Integrate smoothly with the core addon's UI extensibility (New Poll button, specialized formatting).

## 3. Architecture
### Key Components
- **Question Objects**: Support for single and multiple-choice `Question` activities.
- **Response Handling**: Processing incoming `Create` activities that act as votes/responses to active polls.
- **State Management**: Updating poll tallies and notifying followers of concluded polls.
- **Vue Components**: Modular UI elements that plug into the core Inbox stream.

## 4. Work Log / Session History
*Consult `docs/session/` for detailed logs of specific development sessions.*
