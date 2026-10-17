# Messaging & Chat Module — Multi-Tenant Multi-Vendor SaaS

An enterprise real-time messaging platform: direct messages, group
chats, project conversations, support chats — with presence, read
receipts, threads, reactions, file sharing, AI assistance, and a
voice/video-ready foundation.

This is the **platform-wide generalization** of the project chat already
speced in [`projects-architecture.md`](projects-architecture.md) §9
(`project_chats` / `project_messages` / `message_reads`, Reverb private
channels, typing indicators, read receipts). That section described chat
*scoped to a project*; this doc defines the general conversation engine
that project chat becomes one type of.

It composes infrastructure other docs own:

- **WebSocket transport** ← Reverb, as introduced in [`notifications-architecture.md`](notifications-architecture.md) §7 (private + presence channels, channel auth).
- **Project chat** ← [`projects-architecture.md`](projects-architecture.md) §9 (the prototype this generalizes).
- **CRM communication hub** ← [`crm-architecture.md`](crm-architecture.md) §8 (customer↔vendor threads feed the CRM timeline).
- **AI replies / summaries / translation** ← [`ai-architecture.md`](ai-architecture.md) §2, §15.
- **Mobile chat UX** ← [`mobile-architecture.md`](mobile-architecture.md) §12.
- **File handling** ← the signed-URL + sanitize pipeline (projects §8 / payments / branding).

Follows the shipped/planned convention of the other twelve docs.

## Table of contents

1. [Overview](#1-overview)
2. [Conversation types](#2-conversation-types)
3. [Real-time messaging](#3-real-time-messaging)
4. [Message features](#4-message-features)
5. [Message status](#5-message-status)
6. [Presence system](#6-presence-system)
7. [File sharing](#7-file-sharing)
8. [Voice & video-ready architecture](#8-voice--video-ready-architecture)
9. [Search system](#9-search-system)
10. [Chat notifications](#10-chat-notifications)
11. [AI chat features](#11-ai-chat-features)
12. [Conversation management](#12-conversation-management)
13. [Project collaboration chat](#13-project-collaboration-chat)
14. [CRM integration](#14-crm-integration)
15. [Support ticket integration](#15-support-ticket-integration)
16. [Moderation & security](#16-moderation--security)
17. [Database design](#17-database-design)
18. [API design](#18-api-design)
19. [Frontend architecture](#19-frontend-architecture)
20. [Mobile chat experience](#20-mobile-chat-experience)
21. [Performance](#21-performance)
22. [Multi-tenant messaging](#22-multi-tenant-messaging)
23. [Compliance & data retention](#23-compliance--data-retention)
24. [Scalability](#24-scalability)

### Status snapshot (today)

| Layer | Shipped | Planned in this doc |
|---|---|---|
| **Project chat** | speced (not built) in projects §9 — `project_chats`/`project_messages`/`message_reads` | generalized into `conversations`/`messages`/`message_reads` (project chat = one conversation type) |
| **Transport** | none — Reverb referenced as planned (notifications §7, projects §9) | Reverb install + private/presence channels |
| **Tables** | none | all `conversations`/`messages`/`message_*` tables |
| **Reused** | `BelongsToTenant`, notifications module, AI gateway, file pipeline, event backbone | — |

> Greenfield, but with a clear prototype: projects §9 already designed the chat tables + Reverb channels for the project scope. This doc lifts that design into a general engine — a `conversation` has a `type` (direct / group / project / support), and the project-scoped chat is just `type = 'project'`. One engine, many surfaces.

---

## 1. Overview

### Business objectives

- **Close the loop**: buyers and vendors talk in-platform (not email) → faster deals, better records, platform stickiness.
- **Collaboration**: project teams + customer teams coordinate where the work is.
- **Support**: ticket-linked chats resolve issues with full context.
- **Retention**: real-time communication is table-stakes for a modern SaaS — its absence pushes users to Slack/email and erodes the platform's centrality.

### Communication workflows

| Workflow | Conversation |
|---|---|
| Customer↔vendor pre-sale | direct message (marketplace "contact vendor") |
| Customer↔vendor on a project | project conversation (projects §9) |
| Vendor team coordination | group chat (vendor team) |
| Customer↔support | support conversation (ticket-linked) |
| Cross-functional | custom group |

### Integration map

| Module | Messaging touchpoint |
|---|---|
| Projects | Project conversation = `type='project'` linked to a project (§13) |
| Tasks | A message can reference a task; "discuss" from a task opens a thread |
| CRM | Customer↔vendor threads feed the CRM communication hub (§14) |
| Support | Ticket-linked support conversations (§15) |
| Notifications | New message / mention / reply → notification when offline |
| AI | Reply suggestions, summaries, translation, sentiment (§11) |
| Marketplace | "Contact vendor" → a direct conversation |

---

## 2. Conversation types

A single `conversations` table with a `type` discriminator:

```php
conversations
  id, tenant_id
  type enum('direct','group','project','support','custom')
  subject_type, subject_id (nullable)   // polymorphic: Project, SupportTicket, …
  title (nullable, for groups)
  created_by_id, last_message_at
  is_archived (per-conversation default), settings jsonb
  created_at, updated_at, softDeletes
  index (tenant_id, type, last_message_at)
  index (subject_type, subject_id)
```

| Type | Participants |
|---|---|
| **Direct** | exactly 2 — user↔user, vendor↔customer, vendor↔team, customer↔support |
| **Group** | N participants — project teams, vendor teams, customer teams, support teams, custom |
| **Project** | linked to a project (`subject` = Project); participants = project members (projects §7) |
| **Support** | linked to a support ticket (`subject` = SupportTicket); customer + agent(s) |
| **Custom** | ad-hoc group |

A **direct** conversation is deduplicated: a `participant_hash`
(sorted user ids) UNIQUE per tenant prevents two DM threads between the
same pair.

---

## 3. Real-time messaging

```mermaid
flowchart LR
    A[User A sends message] --> API[POST /conversations/{id}/messages]
    API --> DB[(persist message)]
    API --> PUB[Redis Pub/Sub]
    PUB --> REVERB[Reverb WS server]
    REVERB --> B[User B's private channel]
    REVERB --> PRES[presence channel: who's here]
    API --> Q[Queue: notify offline participants]
    Q --> NOTIF[Notifications module]
```

- **Transport** — Laravel Reverb (Pusher protocol, self-hosted), the same stack the notifications doc §7 introduced.
- **Channels**:
  - `conversation.{id}` — private; members receive `MessageSent`, `MessageEdited`, `MessageDeleted`, `ReactionToggled`.
  - `conversation.{id}.presence` — presence; who's viewing + typing.
  - `user.{id}.inbox` — private; conversation-list updates (new message in any thread → bump + unread).
- **Persist-then-broadcast**: the message row is written first (source of truth), then broadcast — so a reconnecting client reconciles against the DB, never loses a message.
- **Redis Pub/Sub** bridges app servers → Reverb nodes so a horizontally-scaled fleet delivers to whichever node holds the recipient's socket.

---

## 4. Message features

```php
messages
  id, tenant_id, conversation_id, sender_id
  parent_id (nullable → message_threads/replies)
  body text                       // markdown / rich text
  content_format enum('text','markdown','rich')
  mentions jsonb                  // [{user_id, offset, length}]
  metadata jsonb                  // link previews, quoted message id
  edited_at, deleted_at (soft, "this message was deleted")
  created_at
  index (conversation_id, created_at)
  index (sender_id)
```

- **Text / rich text / emoji** — markdown body, emoji picker.
- **Mentions** (`@username`) — parsed at write (`MentionParser`, reused from tasks/notifications), stored as offsets, highlighted + notified.
- **Reactions** — `message_reactions` (emoji → users).
- **Quotes / replies** — `parent_id` for inline replies; `metadata.quoted_message_id` for quote-with-context.
- **Threads** — `message_threads` groups replies under a root message (Slack-style); the channel shows the root + a "N replies" affordance.

---

## 5. Message status

```php
message_statuses (per recipient, for direct/small groups)
  message_id, user_id, status enum('sent','delivered','read'), at
  unique (message_id, user_id)

message_reads (cursor-based, scalable for large groups)
  conversation_id, user_id, last_read_message_id, read_at
  unique (conversation_id, user_id)
```

- **Sent** — persisted.
- **Delivered** — recipient's client ack'd over WS (or fetched).
- **Read** — recipient viewed it; tracked via a **read cursor** (`last_read_message_id`) per (conversation, user) — O(1) to update, scales to large groups (no per-message-per-user row explosion). Per-message statuses are kept only for direct + small group threads where "seen by" precision matters.
- **Edited / deleted** — `edited_at` shows "(edited)"; `deleted_at` tombstones the message ("this message was deleted") without hard-removing (audit).
- **Last seen** — from presence (§6).

---

## 6. Presence system

```php
user_presence
  user_id (unique), tenant_id
  status enum('online','offline','away','busy')
  last_active_at
  custom_status (nullable)         // "In a meeting"

user_sessions
  id, user_id, device, ip, user_agent, last_active_at, connection_id
  index (user_id, last_active_at)
```

- **Status** — online / offline / away / busy; auto-away after idle, manual override.
- **Tracking** — `last_active_at`, active sessions (multi-device), device info — overlaps with the auth session-management module (one source for "devices").
- **Architecture** — presence lives in **Redis** (ephemeral, TTL-based heartbeat), not the DB hot path; `user_presence` is the periodically-flushed durable snapshot. Reverb presence channels broadcast join/leave. A heartbeat every ~30s refreshes the Redis key; missing heartbeats → offline.

---

## 7. File sharing

```php
message_attachments
  id, tenant_id, message_id, uploader_id
  disk, path                       // private S3, signed-URL only
  original_name, mime, size_bytes
  kind enum('image','document','pdf','video','audio','archive','code','other')
  metadata jsonb                   // dimensions/duration/preview
  version, parent_attachment_id (nullable)  // versioning
  created_at
  index (message_id), index (conversation_id via join)
```

Supports images, documents, PDFs, videos, audio, ZIP, source code, API
specs. Reuses the file pipeline (projects §8): MIME allowlist, size cap,
signed-URL download (15-min), SVG sanitize, ClamAV scan, ULID-prefixed
tenant path. **Previews**: image thumbnails, PDF first-page, video
poster, code syntax-highlight (< 256KB). **Versioning** via
`parent_attachment_id`. **Download controls** — access checked against
conversation membership at download time.

---

## 8. Voice & video-ready architecture

Future-ready, not built now — the architecture leaves room:

- **Signaling** — a `call_signals` channel over Reverb carries WebRTC offer/answer/ICE; the existing private-channel auth gates who can join.
- **Calls** — `conversation_calls` (planned): conversation_id, type(voice/video/screen), participants, started/ended, recording_url.
- **Media** — WebRTC peer-to-peer for 1:1; an SFU (LiveKit/mediasoup/Janus) for group calls + screen sharing + recording at scale.
- **Meeting rooms** — a `type='group'` conversation can host a persistent room; a scheduled meeting (CRM §8) links to it.

The messaging engine handles signaling + presence + the conversation
shell today; plugging in an SFU later doesn't change the data model.

---

## 9. Search system

Search messages, files, users, projects, tasks, conversations.

```php
chat_search_index            // or a tsvector column on messages
  message_id, conversation_id, tenant_id
  search_vector tsvector     // GIN-indexed
  // + embedding vector(1536) for semantic (ai §11)
```

- **Full-text** — PostgreSQL `tsvector` over message bodies + attachment names; scoped to conversations the user belongs to.
- **Semantic / AI search** — embeddings (ai §11) for "find where we discussed the Stripe migration" without exact keywords; hybrid rank with keyword.
- **Permission-filtered** — results restricted to the user's conversations *after* ranking; never leak a message from a thread you're not in.
- Search across entities (users/projects/tasks) federates with the global search (ai §11).

---

## 10. Chat notifications

(Delivery via notifications doc.) Events: new message (when the
recipient isn't actively viewing the conversation), mention, reply,
group invitation, support response. Channels: in-app (bell + inbox
badge), email (digest if many), push (mobile §12). Smart suppression:
no notification if the user is *currently viewing* that conversation
(presence-aware). Respects quiet hours + preferences (notifications §6).

---

## 11. AI chat features

(Engine owned by [`ai-architecture.md`](ai-architecture.md) §2, §15.)

| Feature | Description |
|---|---|
| Message suggestions | Smart-reply chips from conversation context |
| Reply assistant | Draft a full reply for review (vendor support) |
| Summaries | "Summarize this thread" → key points + action items (→ tasks) |
| Translation | Inline translate a message to the reader's locale (i18n) |
| Sentiment analysis | Flag frustrated customers → escalation (support §15) |
| Smart search | Semantic search (§9) |
| Conversation insights | Per-thread/account insights for the CRM |

All credit-metered (ai §17), opt-in, human-reviewed before send. AI
suggestions render as dismissible chips above the composer.

---

## 12. Conversation management

```php
conversation_participants
  id, conversation_id, user_id, role enum('owner','admin','member')
  joined_at, last_read_message_id
  is_archived, is_pinned, is_muted, muted_until    // per-user state
  notification_level enum('all','mentions','none')
  unique (conversation_id, user_id)

conversation_settings        // per-conversation (group) config
  conversation_id, allow_reactions, allow_threads, retention_days, ...

conversation_archives        // soft archive history

message_stars / favorites    // starred messages, favorite conversations (per user)
```

Per-user: archive, pin, mute (with `muted_until`), star messages,
favorite conversations, per-conversation notification level. All stored
on `conversation_participants` (per-user state) so one user muting
doesn't affect others.

---

## 13. Project collaboration chat

The project conversation (`type='project'`, `subject`=Project) is the
projects §9 chat, now a conversation type. Contextual integration:

- **Project updates** — milestone completed / task assigned posts a system message into the project conversation.
- **File sharing** — files shared in chat link to project files (projects §8).
- **Discussions** — threads on decisions.
- **Activity references** — `@task:123` / `@milestone:4` mentions render as rich chips linking to the entity; a "discuss" button on a task opens a thread.

The two `kind`s from projects §9 (team vs customer) map to two project
conversations with different participant sets (internal vs
client-facing).

---

## 14. CRM integration

Customer↔vendor conversations feed the CRM communication hub
([`crm-architecture.md`](crm-architecture.md) §8): a message logs a
`crm_communication` (type=chat) + a `crm_activity` on the contact's
timeline. So a vendor's CRM shows the full conversation history with a
customer alongside emails/calls/meetings — one unified timeline. Linking
is by the conversation's participant ↔ `crm_contact.user_id`.

---

## 15. Support ticket integration

A support conversation (`type='support'`, `subject`=SupportTicket)
links chat to the (planned) support module:

- **Ticket-linked** — the conversation *is* the ticket thread.
- **Escalations** — escalating a ticket adds a senior agent to the conversation + a system message.
- **Agent transfers** — reassigning swaps the agent participant, preserving history.
- **Resolutions** — closing the ticket archives the conversation (retrievable).
- **AI** — sentiment (§11) flags angry customers; AI triage (ai §6) drafts first responses.

---

## 16. Moderation & security

| Concern | Mitigation |
|---|---|
| **Spam detection** | Rate limits + AI spam classifier (ai §16) on messages |
| **Abuse / harassment** | AI content moderation + report-message → moderation queue + human review |
| **Malicious content** | Attachment MIME allowlist + ClamAV + SVG sanitize; link-safety scan |
| **Rate limiting** | Per-user message + attachment rate limits (token bucket) |
| **Unauthorized access** | Reverb channel auth (`routes/channels.php`) checks conversation membership; every message endpoint checks membership |
| **Tenant isolation** | Every table `tenant_id` + global scope; a conversation never spans tenants |
| **Audit** | Message edits/deletes tombstoned (not hard-deleted); moderation actions logged to `activity_logs` |
| **Encryption** | TLS in transit; attachments private + signed-URL; optional at-rest encryption for sensitive tenants |

Channel authorization (the cardinal guard):
```php
Broadcast::channel('conversation.{conversation}', function (User $u, Conversation $c) {
    return $c->participants()->where('user_id', $u->id)->exists();  // membership only
});
```

---

## 17. Database design

| Table | Purpose |
|---|---|
| `conversations` | Thread container, typed (direct/group/project/support/custom) |
| `conversation_participants` | Membership + per-user state (pin/mute/archive/read cursor) |
| `conversation_groups` | Group metadata (name, avatar, description) |
| `messages` | Message rows (body, mentions, format, edited/deleted) |
| `message_threads` | Thread roots (groups replies) |
| `message_replies` | Reply links (or `messages.parent_id`) |
| `message_reactions` | emoji → users |
| `message_mentions` | @mention index (or `messages.mentions` jsonb) |
| `message_attachments` | Files (signed-URL, versioned) |
| `message_statuses` | Per-recipient sent/delivered/read (small threads) |
| `message_reads` | Read cursor per (conversation, user) — scalable |
| `conversation_settings` | Per-conversation config |
| `conversation_archives` | Archive history |
| `user_presence` | Durable presence snapshot |
| `user_sessions` | Active sessions/devices |
| `chat_notifications` | Chat-specific notification log (or reuse notifications) |
| `chat_search_index` | tsvector + embedding for search |

### Key constraints / indexes

- `conversations`: `(tenant_id, type, last_message_at)` for the inbox list; `(subject_type, subject_id)` for project/ticket linkage; direct-dedup `UNIQUE(tenant_id, participant_hash)`.
- `messages`: `(conversation_id, created_at)` — the core pagination index; partitioned by month at scale (millions of messages).
- `conversation_participants`: `UNIQUE(conversation_id, user_id)`; `(user_id, is_archived)` for the user's inbox.
- `message_reads`: `UNIQUE(conversation_id, user_id)` — O(1) read-cursor update.
- `chat_search_index`: GIN on `search_vector`, HNSW on `embedding`.
- Every table `tenant_id` + `BelongsToTenant`.

---

## 18. API design

API-first; tenant-scoped; membership-checked; cross-tenant → 404.
Real-time over WS, REST for history + actions.

| Verb | URL | Purpose |
|---|---|---|
| `GET` | `/conversations` | Inbox list (cursor, filter by type/unread) |
| `POST` | `/conversations` | Start a conversation (dedup direct) |
| `GET` | `/conversations/{id}` | Conversation + recent messages |
| `GET` | `/conversations/{id}/messages` | Message history (cursor, upward scroll) |
| `POST` | `/conversations/{id}/messages` | Send (persist + broadcast) |
| `PATCH` | `/messages/{id}` | Edit |
| `DELETE` | `/messages/{id}` | Delete (tombstone) |
| `POST` | `/messages/{id}/react` | Toggle reaction |
| `POST` | `/messages/{id}/read` | Advance read cursor |
| `POST` | `/conversations/{id}/attachments` | Upload |
| `GET` | `/attachments/{id}/download` | Signed-URL redirect |
| `POST` | `/conversations/{id}/participants` | Add member (group) |
| `DELETE` | `/conversations/{id}/participants/{user}` | Remove/leave |
| `PATCH` | `/conversations/{id}/settings` | Pin/mute/archive/notif-level (per user) |
| `GET` | `/presence` | Presence of contacts |
| `GET` | `/chat/search` | Full-text + semantic search |
| `POST` | `/conversations/{id}/typing` | Typing signal (or WS-only) |

Message history uses **cursor pagination** (id-based, upward) — the only
sane approach for an append-heavy, infinitely-scrolling log.

---

## 19. Frontend architecture

```
resources/js/
├── pages/chat/
│   ├── index.tsx              # chat dashboard (conversation list + active thread)
│   ├── conversation.tsx       # full conversation view
│   ├── group.tsx              # group chat (members, settings)
│   ├── project.tsx            # project chat (contextual)
│   ├── support.tsx            # support chat
│   └── settings.tsx
├── components/chat/
│   ├── ChatWindow.tsx          # message list + composer (streaming)
│   ├── MessageBubble.tsx       # body, reactions, status, edited/deleted
│   ├── MessageThread.tsx       # reply thread panel
│   ├── ConversationList.tsx    # inbox, unread badges, last message
│   ├── ConversationItem.tsx
│   ├── Composer.tsx            # rich text + emoji + attach + AI chips
│   ├── TypingIndicator.tsx
│   ├── PresenceDot.tsx
│   ├── AttachmentViewer.tsx    # image/pdf/video/code preview
│   ├── EmojiPicker.tsx
│   ├── MentionAutocomplete.tsx
│   ├── ReadReceipts.tsx
│   └── SearchPanel.tsx
├── hooks/chat/
│   ├── useConversation.ts      # Echo subscription + optimistic send
│   ├── useInbox.ts             # conversation list + live bumps
│   ├── usePresence.ts          # presence channel
│   ├── useTyping.ts            # throttled typing broadcast
│   └── useChatSearch.ts
└── lib/chat/
    ├── echo.ts                 # Reverb/Echo client setup
    ├── markdown.ts
    └── formatters.ts
```

- **`useConversation`** — subscribes to `conversation.{id}` via Echo, optimistic-sends (renders immediately, reconciles on ack), handles edit/delete/react events.
- **`useInbox`** — subscribes to `user.{id}.inbox`, bumps + reorders threads on new messages, maintains unread counts.
- Inertia seeds the initial conversation list + first message page; everything live is WS; React Query caches history pages.

---

## 20. Mobile chat experience

(Per [`mobile-architecture.md`](mobile-architecture.md) §12.)

- **Mobile conversations** — full-screen thread, conversation list as the home; bottom composer above the safe-area inset.
- **Swipe actions** — swipe a conversation to archive/mute/pin (mobile §8 swipe).
- **Push notifications** — new message → push via the service worker (mobile §11), deep-links into the thread.
- **Offline drafts** — composer text auto-saved to local storage; queued messages send on reconnect (mobile §10 background sync).
- **Quick replies** — from a push notification (notification action buttons) + smart-reply chips (§11).

---

## 21. Performance

Target: millions of messages, thousands of concurrent users, global.

| Concern | Approach |
|---|---|
| **Real-time fan-out** | Redis Pub/Sub bridges app servers → Reverb fleet; deliver to the node holding the socket |
| **WebSocket scaling** | Reverb horizontally scaled behind a load balancer; channels sharded by tenant |
| **Message reads** | Read *cursor* per (conversation, user) — O(1), no per-message-per-user rows |
| **History pagination** | Cursor (id-based); `(conversation_id, created_at)` index; messages partitioned by month |
| **Caching** | Recent messages + conversation list cached in Redis (busted on new message); presence in Redis (not DB) |
| **Queue processing** | Offline notifications, attachment processing, search indexing, AI features all queued |
| **Search indexing** | Async tsvector + embedding update on message create |
| **Typing/presence** | Ephemeral (Redis + WS), throttled (1 typing event / 3s), never hits the DB |
| **Connection limits** | Per-user max concurrent sockets; idle disconnect |

---

## 22. Multi-tenant messaging

Each tenant configures (white-label):

- **Chat settings** — which conversation types are enabled, group size limits, file size caps.
- **Branding** — chat UI uses tenant branding (BrandingService).
- **Moderation rules** — profanity filter, AI moderation thresholds, report handling.
- **Retention policies** — message retention window (§23).

A tenant's conversations are fully isolated; a user in two tenants has
separate inboxes (the active tenant scopes the conversation list).

---

## 23. Compliance & data retention

- **Retention policies** — per-tenant `retention_days`; a scheduled `PurgeExpiredMessages` job tombstones/hard-deletes messages past the window (configurable: soft for audit, hard for GDPR).
- **GDPR** — a user's "export my data" includes their messages; "delete my data" tombstones their messages (sender anonymized, body cleared) while preserving thread integrity for others.
- **Audit trails** — edits/deletes/moderation logged (append-only); admins can audit a conversation (with permission + its own audit entry).
- **Export conversations** — a participant/admin exports a conversation to PDF/JSON (queued, signed-URL) — for records or legal.
- **Delete conversations** — soft by default (retrievable), hard-delete on request with an audit record.
- **Legal hold** — a flag that exempts a conversation from retention purging during a dispute.

---

## 24. Scalability

100k+ tenants, millions of conversations, millions of messages/day,
global real-time:

- **Partitioned** `messages` (+ attachments, statuses) by month; old partitions to cold storage.
- **Reverb fleet** behind a load balancer; Redis Pub/Sub for cross-node delivery; channels sharded by tenant.
- **Read cursors** keep read-tracking O(1) regardless of group size.
- **Read replicas** for history/search; primary for writes.
- **Multi-region** — Reverb + app workers per region; messages written to the regional primary; presence is regional.
- **Backpressure** — per-tenant fair-share on notification + indexing queues.
- **Search offload** — dedicated search service (Meilisearch/ES) at extreme scale.
- **Voice/video** — an SFU (LiveKit) scales group media independently of the messaging plane (§8).

---

## File map for the next phase

| Path | Status |
|---|---|
| `composer require laravel/reverb` + `laravel-echo` / `pusher-js` (frontend) | planned |
| `database/migrations/*_create_conversations_table.php` + `_conversation_participants_` + `_groups_` | planned |
| `database/migrations/*_create_messages_table.php` (partitioned) + `_message_reactions_` + `_attachments_` | planned |
| `database/migrations/*_create_message_reads_table.php` + `_message_statuses_` | planned |
| `database/migrations/*_create_user_presence_table.php` + `_user_sessions_` | planned |
| `database/migrations/*_create_conversation_settings_table.php` + `_archives_` | planned |
| `database/migrations/*_add_chat_search_index.php` (tsvector + pgvector) | planned |
| `app/Domain/Messaging/{ConversationService,MessageService,PresenceTracker,ReadCursor,MentionParser}.php` | planned |
| `app/Events/Messaging/{MessageSent,MessageEdited,MessageDeleted,ReactionToggled,UserTyping}.php` | planned |
| `app/Models/{Conversation,ConversationParticipant,Message,MessageReaction,MessageAttachment,MessageRead,UserPresence}.php` | planned |
| `app/Http/Controllers/Messaging/{Conversation,Message,Attachment,Presence,ChatSearch}Controller.php` | planned |
| `routes/channels.php` — conversation private + presence channels | planned |
| `app/Listeners/Messaging/{NotifyOfflineParticipants,LogCrmCommunication,IndexMessage}.php` | planned |
| `resources/js/pages/chat/*` + `components/chat/*` + `hooks/chat/*` + `lib/chat/echo.ts` | planned |
| `tests/Feature/Messaging/*` (membership channel auth, read-cursor, direct dedup, mention notify, tenant isolation, retention purge) | planned |

The next pass installs Reverb, commits the `conversations` +
`conversation_participants` + `messages` + `message_reads` tables, the
`ConversationService` + `MessageService` + the channel auth, and a
working 1:1 direct-message thread end-to-end (send → broadcast →
read-cursor) — proving the real-time plane before groups, threads,
presence, files, and AI layer on. Project chat (projects §9) then
becomes a `type='project'` conversation rather than a separate build.

---

## The architecture doc set

This is the thirteenth architecture doc. The complete set under `docs/`:

1. `dashboard-architecture.md`
2. `payments-architecture.md`
3. `projects-architecture.md`
4. `tasks-architecture.md`
5. `notifications-architecture.md`
6. `billing-architecture.md`
7. `ai-architecture.md`
8. `analytics-architecture.md`
9. `workflow-automation-architecture.md`
10. `marketplace-architecture.md`
11. `mobile-architecture.md`
12. `crm-architecture.md`
13. `messaging-architecture.md`

All in the same shipped-vs-planned format, cross-referenced, each
ending with a concrete "File map for the next phase".
