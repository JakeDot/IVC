# Deltas Documentation

In the IVC architecture, "Deltas" refer to **∆event objects** that encapsulate discrete state changes, asynchronous notifications, or lifecycle transitions within the system. They are the primary mechanism for propagating state mutations in a decoupled manner.

## Concept: The ∆event Object

Rather than continuously polling large monolithic state trees, IVC relies on event-driven deltas. A ∆event object represents *what changed* at a specific moment in time.

### Core Structure

While the specific payload varies based on context, a ∆event object generally conforms to the following conceptual structure:

```json
{
  "type": "string (The classification of the event)",
  "timestamp": "integer (Unix epoch of occurrence)",
  "source": "string (Originator of the delta)",
  "data": "object (Context-specific payload describing the change)"
}
```

## Key Application Domains for Deltas

### 1. WebRTC Signaling Deltas

In WebRTC negotiation, peers must exchange Session Description Protocol (SDP) objects and ICE candidates. These are treated as ephemeral signaling deltas.

*   **Offer/Answer Deltas**: Represent a change in a peer's proposed media configuration.
    *   *Example Type*: `offer`, `answer`
    *   *Payload*: WebRTC SDP string.
*   **ICE Candidate Deltas**: Represent newly discovered network routing options.
    *   *Example Type*: `candidate`
    *   *Payload*: ICE Candidate object.
*   **Lifecycle Deltas**: Indicate a change in room occupancy.
    *   *Example Types*: `join`, `leave`, `peer-joined`

These signaling deltas are routed through `/api/signal.php` and broadcasted to room participants (via Polling or Server-Sent Events). The frontend WebRTC implementation (e.g., `ivc.webrtc.js`) listens for these `MessageEvent` objects and applies the ∆event to the local `RTCPeerConnection` state.

### 2. Stripe Webhook Deltas

The `StripeService` (`src/Services/StripeService.php`) handles asynchronous deltas originating from the Stripe payment gateway.

*   **Webhook Events**: When a subscription is updated, a payment succeeds, or a mandate is revoked, Stripe sends a JSON ∆event object to the application's webhook endpoint.
*   **Processing**: `StripeService::handleWebhookEvent(array $event)` parses the `type` (e.g., `checkout.session.completed`, `customer.subscription.updated`) and the `data.object`. It then applies this delta to the local database, updating the subscription status for the relevant User or Channel model.

### 3. Frontend UI Event Deltas

The JavaScript frontend relies heavily on DOM and WebRTC API events, which function as local deltas updating the user interface.

*   **`onicecandidate` / `ontrack`**: WebRTC API events that trigger localized UI updates (like rendering a new `<video>` element when a track delta is received).
*   **DataChannel `onmessage`**: When a text message is received via the P2P DataChannel, the resulting `MessageEvent` object is parsed, and the chat UI is updated to reflect the new state (the incoming message delta).

## Handling Deltas Safely

Because deltas represent state changes from external or asynchronous sources, they are subject to strict validation:

1.  **Schema Validation**: The type and structure of the ∆event object must be verified before processing.
2.  **Sanitization**: For signaling deltas, properties like `room` and `client` IDs undergo strict regex filtering to prevent injection.
<<<<<<< HEAD
3.  **Authentication/Verification**: Webhook deltas from Stripe must be cryptographically verified using webhook secrets to ensure they originated from the trusted payment provider.
=======
3.  **Authentication/Verification**: Webhook deltas from Stripe must be cryptographically verified using webhook secrets to ensure they originated from the trusted payment provider.

### 4. Dynamic Data & AI Analytics (∆data)

The `∆data` subobject is a specialized delta type used to query and stream live telemetry and AI-driven insights for IVC objects.

*   **Targets**: Can be attached to server nodes (`ivc://$node∆data`), networks (`ivc://network/£global∆data`), channels (`ivc://localhost/#lobby∆data`), or users.
*   **Payload**: Contains live metrics such as active nodes, peer mesh connections, bandwidth throughput (Kbps), latency, memory, and a computed health score.
*   **Access Control**: Streaming `∆data` requires the user to possess the `+d` (Data Viewer) user mode. Additionally, target objects can explicitly opt out of telemetry sharing by setting the `-d` object mode. Queries blocked by mode restrictions return HTTP 403.
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
