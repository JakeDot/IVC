# IVC Protocol Documentation

The Internet Video Chat (IVC) architecture employs a custom pseudo-protocol and advanced HTTP routing mechanisms to seamlessly bridge legacy IRC behaviors with modern WebRTC signaling contexts.

## 1. The `ivc://` Connection URI

The core mechanism for routing users to networks, local servers, and specific chat rooms is the `ivc://` pseudo-protocol URI scheme. This URI is parsed by `IrcServices::parseServerUri()` to resolve the destination object and requested modes.

### 1.1 Object Prefixes

Objects targeted within the IVC protocol are classified by explicit prefix symbols. The backend `Sanitizer::sanitizeRoomId` strictly enforces multibyte validation on these prefixes:

*   **`#` (Global Channel)**: A standard, network-wide chat room (e.g., `#lobby`). Broadcasts signals to all connected peers.
*   **`&` (Local Channel)**: A chat room restricted to the local server node (e.g., `&oper`).
*   **`@` (User)**: Targets a specific user nick for direct interaction or memo routing (e.g., `@CyberFox`).
*   **`£` (Network)**: Targets network-wide services or broadcast channels.
*   **`$` (Server)**: Targets server-specific administrative operations.

### 1.2 Mode Modifiers

Connection URIs can request immediate elevation or specific states by appending `+modes` to the object path.

**Format:** `ivc://<host>/<prefix><object>+<modes>`
**Example:** `ivc://local.host/#fortress+ov` (Connect to `#fortress` requesting Operator `+o` and Voice `+v` states).

When parsing this URI, the backend explicitly splits the string at the `+` character. The base object (`#fortress`) is routed, and the `+ov` string is evaluated against the user's active permissions. If a user requests a privileged mode (like `+o`) but fails authorization via `ChanServ`, the `/connect` request is denied.

---

## 2. HTTP Routing and Memos

The signaling API (`public/api/signal.php`) uses HTTP verbs to interpret the intent of a payload toward the specified object.

### 2.1 The `PUT` Method and Non-Channel Objects

By default, WebRTC signaling signals (Offers, Answers, ICE Candidates) and standard chat messages are handled via `POST` requests and broadcast to active WebRTC peers in the target channel.

However, IVC supports `PUT` requests to handle offline notices, comments, and direct memos.

If a `PUT` payload targets a **non-channel object** (an object prefixed with `@`, `£`, or `$`), the backend halts the WebRTC broadcast loop. Instead, the payload's text is seamlessly routed into the **`MemoServ` offline messaging database** as a `[PUT Notice]`. This allows external services or clients to leave messages for users or networks even when WebRTC peer-to-peer data channels are unavailable.

---

## 3. Protocol Status Headers

To allow clients to dynamically assess the active permissions of a user in a given context without requiring heavy JSON polling, IVC injects a custom HTTP response header into the API endpoints.

### 3.1 The `Status` Header Format

The header combines the standard HTTP status code with an application-specific mode descriptor:

`Status: <httpstatus>+modes:<appstatus>`

**Example:**
`Status: 200+modes:CyberFox{subs [#room+t+o]}`

### 3.2 Evaluation

The `<appstatus>` string is generated natively during the request lifecycle. It maps the connecting user's identifier to the active channel they are polling/posting to, and appends any inline privileges (e.g., if `CyberFox` is currently an Operator `+o` in a Topic-locked `+t` room).

Clients can parse this HTTP header immediately upon receiving the XHR/Fetch response to rapidly adjust UI elements (like enabling/disabling the Kick User button) based on the `+o` inline evaluation.