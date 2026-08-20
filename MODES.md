# Modes Documentation

In the IVC application, Modes define the behavioral characteristics and access controls of Channels and Users, drawing heavily from traditional IRC conventions.

## Channel Modes

Channel modes are attributes assigned to a specific channel. They are managed through the `Channel` model and modified using `ChanServ::setModes()`. Modes are represented as a concatenated string (e.g., `+smt`).

### Supported Channel Modes

*   **`+t` (Topic Lock):**
    *   **Behavior:** Only Channel Operators (`+o`) are permitted to change the channel's topic.
    *   **Default:** Typically enabled by default when a channel is created.
*   **`+m` (Moderated):**
    *   **Behavior:** Only users with Voice (`+v`) or Operator (`+o`) status can send messages to the channel or broadcast WebRTC signals. Normal users can view/listen but cannot broadcast.
*   **`+s` (Secret):**
    *   **Behavior:** The channel is hidden from public channel lists (e.g., in a `/list` command or lobby directories).
*   **`+i` (Invite-Only):**
    *   **Behavior:** Users cannot join the channel or connect to its WebRTC signaling room unless they have been explicitly invited by an existing channel operator.
*   **`+k` (Key / Passkey):**
    *   **Behavior:** Requires a user to provide a specific password (passkey) to join the channel or access its signaling data.

### Setting Channel Modes

Modes are typically adjusted via IRC commands directed to `CHANSERV` or native `MODE` commands parsed by the server.

*   **Syntax:** `/mode <#channel> <+modes|-modes>`
*   **Example:** `/mode #my-room +sm` (Sets the channel to secret and moderated).
*   **Example:** `/mode #my-room -t` (Removes the topic lock).

When a mode change is requested, `ChanServ::setModes()` validates the permissions of the requester (ensuring they are an Operator of the target channel), parses the added (`+`) and removed (`-`) modes, and updates the `Channel` entity in the database.

## User Modes (Permissions)

User modes represent privileges granted to a specific user within the context of a specific channel. These are handled via the `ChannelUser` model.

### Supported User Modes

*   **`+o` (Operator / OP):**
    *   **Behavior:** The user has administrative privileges over the channel. They can change the topic (if `+t` is set), modify channel modes, kick users, and grant privileges to others.
    *   **Granting:** `/msg CHANSERV OP <#channel> <nick>`
*   **`+v` (Voice):**
    *   **Behavior:** The user is permitted to speak (send text/video) in a moderated channel (where `+m` is active).
    *   **Granting:** Granted by Operators to allow specific users to participate in restricted rooms.

## Mode Parsing and Application

The backend logic for parsing mode strings iterates through the provided input. It tracks the current operation (`+` for adding, `-` for removing) and applies it to the subsequent characters.

For example, parsing `+sm-t`:
1.  Operation is `+`.
2.  Add `s` (Secret).
3.  Add `m` (Moderated).
4.  Operation becomes `-`.
5.  Remove `t` (Topic Lock).

The resulting mode string is stored in the data model and evaluated whenever a user attempts an action (joining, signaling, speaking, changing topic) in the channel.