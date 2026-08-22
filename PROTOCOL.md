# IVC Protocol Documentation

The Internet Video Chat (IVC) architecture employs a custom pseudo-protocol and advanced HTTP routing mechanisms to seamlessly bridge legacy IRC behaviors with modern WebRTC signaling contexts.

## 1. The `ivc://` Connection URI

The core mechanism for routing users to networks, local servers, and specific chat rooms is the `ivc://` pseudo-protocol URI scheme. This URI is parsed by `IrcServices::parseServerUri()` to resolve the destination object and requested modes.

### 1.1 Object Prefixes

Objects targeted within the IVC protocol are classified by explicit prefix symbols. The backend `Sanitizer::sanitizeRoomId` strictly enforces multibyte validation on these prefixes:

<<<<<<< HEAD
*   **`#` (Global Channel)**: A standard, network-wide chat room (e.g., `#lobby`). Broadcasts signals to all connected peers.
=======
*   **`#` (Global Channel)**: A standard, network-wide chat room (e.g., `#`). Broadcasts signals to all connected peers.
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
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

<<<<<<< HEAD
=======
### 2.2 Native HTTP Reactions, Comment URIs & Automatic Reaction Redirects

Addressable objects and comments support emoji reactions:

1. **Comment Addressing & Network Comments:**
   - `<object>` is a placeholder for any addressable object that has comments (e.g. `ivc://#channel/:comment-id`, `ivc://my-post/:comment-id`, `ivc://object/:comment-id`).
   - The network itself can also have comments, addressable as `ivc://[£]:comment-id` (the `£` prefix is optional, e.g. `ivc://:comment-id` or `ivc://£:comment-id`).

2. **IRC Chat Reaction Commands:**
   - `❤️ ivc://:comment-id` or `❤️ ivc://object/:id`
   - `HEART ivc://:comment-id`
   - `<emoji> ivc://:comment-id` (e.g. `🔥 ivc://:comment-1`, `👍 ivc://£:comment-1`)
   - `/react <emoji> <uri>` or `/heart <uri>`

3. **Compact Encoded Representation (`Δreactions={...}`):**
   - Reactions are represented in compact encoded format on the metadata URI:
     `ivc://object/:idΔreactions={"❤️":2,"🔥":1}` or `ivc://:comment-idΔreactions={"❤️":2}`.

4. **Automatic Reaction Extension & HTTP Redirects:**
   - Comment URIs (e.g. `ivc://:comment-id`, `ivc://£:comment-id`, `ivc://object/:comment-id`) are automatically extended to their compact encoded reaction representation by using HTTP redirects (HTTP 302 / `Location: <extendedUri>`).
   - Querying `GET /api/reactions.php?uri=ivc://:comment-id` or navigating directly to the comment URL provides the extended URI `redirect_uri` / `reactions_uri`.

5. **Native HTTP PUT Reaction Endpoint:**
   - Direct HTTP `PUT` requests to `ivc://:comment-idΔreactions/<emoji>` or `ivc://object/:idΔreactions/<emoji>` apply the reaction and return:
     ```json
     {
       "count": 2,
       "redirect": "ivc://:comment-1Δreactions={\"❤️\":2}",
       "reactions_uri": "ivc://:comment-1Δreactions={\"❤️\":2}"
     }
     ```

>>>>>>> f79f4cf (local state jakedot@petar-vivo)
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