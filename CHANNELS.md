# Channels Documentation

In Internet Video Chat (IVC), channels represent distinct chat rooms where users can congregate for real-time text and peer-to-peer WebRTC video communication. The backend architecture bridges traditional IRC channel paradigms with modern WebRTC signaling contexts.

## Channel Model (`src/Models/Channel.php`)

The `Channel` model acts as the core representation of a room. Channels can be ephemeral (created on the fly when users join) or persistent (registered and managed).

### Core Attributes
- **Name**: The unique identifier for the channel (typically prefixed with `#` in IRC convention).
- **Topic**: A descriptive text string set by channel operators indicating the subject of the channel.
- **Passkey**: An optional passphrase. If set, users must provide the correct passkey to join or receive signaling data.
- **Modes**: A string representing the active channel flags (e.g., `+t`, `+s`).
- **Creator/Founder**: The primary owner of a registered channel.

### Registration
Channels can be registered using `CHANSERV`. Once registered, the channel's properties (like topic and access controls) are persisted, and the founder retains ownership across sessions.

## Channel Management (`ChanServ`)

`CHANSERV` (`src/IRC/ChanServ.php`) is the dedicated IRC Service bot responsible for channel management. It intercepts commands directed to it to enforce permissions, modify channel attributes, and handle administrative actions.

### Key `CHANSERV` Commands
- `REGISTER <#channel> [passkey]`: Registers ownership of an unregistered channel.
- `OP <#channel> <nick>`: Grants a user channel operator (`+o`) privileges.
- `TOPIC <#channel> <topic>`: Changes the channel's topic (often subject to `+t` mode restrictions).
- `MODE <#channel> <modes>`: Adjusts the channel's modes.
- `SUBSCRIBE <#channel> [tier]`: Initiates a paid subscription tier (e.g., `channel_pro`) to unlock advanced limits and capabilities via `PayServ` and Stripe.

## Interaction with WebRTC

In the context of the WebRTC frontend, a channel identifier maps directly to a "Room ID" in the `RoomManager`.

1. **Signaling Boundary**: When a user connects to a channel (`domain.com/#my-channel`), the frontend polls or connects via SSE to `/api/signal.php` requesting the `my-channel` room.
2. **Access Control**: Before the backend distributes WebRTC SDP offers/answers or ICE candidates, it validates the channel's status. If a channel has a passkey or specific modes (like invite-only), the signaling request may be rejected at the API layer.
3. **Data Channels**: Once WebRTC connections are established via signaling, direct Peer-to-Peer DataChannels are opened between participants within the channel. Real-time text chats bypass the server entirely and flow only between connected peers in the channel matrix.
