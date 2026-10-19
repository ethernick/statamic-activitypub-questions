# Statamic Marketplace Launch Package: ActivityPubQuestions

This document contains the complete production launch package for the **ActivityPubQuestions** addon on the Statamic Marketplace and Packagist.

---

## 1. Visual Asset Specifications

### A. Marketplace Hero Card & Social Preview

| Dimension | Aspect Ratio | Color Palette | Focal Elements |
| :--- | :--- | :--- | :--- |
| **1200 x 630 px** | ~1.91:1 | Deep Indigo (`#1e1b4b`), Violet (`#7c3aed`), Electric Cyan (`#06b6d4`), Clean Slate | Statamic CP Polls dashboard floating mockup, horizontal vote progress bars in motion, Mastodon fediverse network rings, clean typography: *"ActivityPub Questions & Polls for Statamic"* |

---

## 2. Screenshot Capture Briefs

| Filename | Capture Context / Subject | Key Details to Highlight |
| :--- | :--- | :--- |
| `01-polls-dashboard.png` | Statamic CP -> ActivityPub -> Polls | Masonry grid of poll cards showing live percentages, active status tags, and cumulative voter count rollups. |
| `02-create-poll-stack.png` | CP Slide-Over Stack -> "New Poll" | Question text field, single choice (`oneOf`) vs multiple choice (`anyOf`) toggles, dynamic option list, and date-time expiration picker. |
| `03-poll-breakdown-stack.png` | Deep-Dive Poll Drawer / Stack | Option-by-option percentage breakdown, voter timeline chart, and remote voter actor URIs. |
| `04-web-voting-preview.png` | Public Frontend Template | Clean Antlers poll rendering showing responsive ballot options and live bar chart results for closed polls. |

---

## 3. 15-Second Demo Video Storyboard

| Timestamp | Scene Description | On-Screen Text / Focus |
| :--- | :--- | :--- |
| **0:00 - 0:04** | Author opens Statamic CP and crafts a poll with 3 options and a 48-hour expiration. | *"Create native polls in seconds directly from Statamic."* |
| **0:04 - 0:08** | Clicks "Publish". Show the `Create(Question)` activity transmitting across the fediverse. | *"Instantly broadcast to Mastodon and the Fediverse."* |
| **0:08 - 0:11** | Switch to Mastodon client: a user votes on the poll. Show the vote tally update live in Statamic. | *"Circular vote tallying with zero double-counting."* |
| **0:11 - 0:15** | Quick pan across the CP Polls analytics dashboard with vote trend graphs and call to action. | *"Engage your audience across the open social web."* |

---

## 4. Marketplace Listing Copy Pack

### Short Tagline (120 characters max):
> Broadcast interactive polls to the Fediverse and tally live votes directly inside Statamic CMS.

### Category:
> Social, Content & Publishing, Developer Tools

### Price:
> **$10** (Early-Access Beta Pricing — Expected to scale to $29–$39 at v1.0).

### Version:
> **v0.8.0** (Beta)

### Long Description:

```markdown
### Turn Your Statamic Site into an Interactive Fediverse Hub

The **ActivityPubQuestions** addon extends your Statamic site with native **W3C ActivityStreams 2.0 Question** capabilities. Whether you're running community surveys, reader polls, or engaging Mastodon followers, manage the entire lifecycle directly from the Statamic Control Panel.

---

### Highlights & Key Features:

- **Full ActivityPub Question Support**: Seamlessly broadcast single-choice (`oneOf`) and multiple-choice (`anyOf`) polls to Mastodon, Pleroma, Pixelfed, and the broader fediverse.
- **Circular Vote Tallying**: Inbound votes cast by remote fediverse users are automatically captured by `PollVoteListener`, updating your local tallies in real time.
- **Double-Voting Prevention**: Actor URIs are verified and tracked per poll to ensure ballot integrity.
- **Dedicated CP Polls Dashboard**: Monitor all active and archived polls with rollup analytics, percentage breakdowns, and cumulative trend graphs.
- **Automated Expiration Worker**: Schedule automatic poll closure via `activitypub:polls-close`, broadcasting final `Update(Question)` activities to freeze remote ballots.
- **Flexible Frontend Antlers Integration**: Display active interactive forms or closed results on your public site with simple, elegant Antlers tags.
- **Dual-Version Compatibility**: Ships with native Vue 2.7 (Statamic 5.x) and Vue 3.5 (Statamic 6.x) Control Panel components out of the box.

---

### Quick Installation:

```bash
composer require ethernick/activitypub-questions
php artisan activitypub:questions:install
```
```
