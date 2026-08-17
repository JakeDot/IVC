<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Security/SecurityHeaders.php';
require_once __DIR__ . '/../src/Security/TokenManager.php';
require_once __DIR__ . '/../src/Security/Sanitizer.php';

use Fortress\Security\SecurityHeaders;
use Fortress\Security\TokenManager;
use Fortress\Security\Sanitizer;

SecurityHeaders::apply();

$csrfToken = TokenManager::generateCsrfToken();

// Parse room ID from request URI if provided as ivc.com/#room or ivc.com/<room>
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$uriPath = trim($requestUri, '/');

$urlRoom = '';
if (!empty($uriPath) && !str_contains($uriPath, '.') && !str_starts_with($uriPath, 'api/')) {
    $urlRoom = Sanitizer::sanitizeRoomId($uriPath);
} elseif (isset($_GET['room'])) {
    $urlRoom = Sanitizer::sanitizeRoomId((string)$_GET['room']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IVC WebRTC — IRC Anonymous & Encrypted Video Network</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
    <div class="app-container">
        <!-- Top Security Bar -->
        <header class="top-bar">
            <div class="brand">
                <span class="shield-icon">🏰</span>
                <h1>IVC<span class="highlight">WebRTC</span> <span style="font-size: 0.8rem; font-weight: normal; opacity: 0.8;">IRC Infrastructure</span></h1>
            </div>
            <div class="security-badges">
                <span class="badge green">⚡ PHP 8.5 & MySQL</span>
                <span class="badge shield">🔑 NAMESERV / CHANSERV</span>
                <span class="badge purple">🕵️ #room Hash Channels</span>
                <span class="badge blue">📊 #stats Room</span>
            </div>
        </header>

        <!-- IRC Channel Tabs Navigation Bar -->
        <nav class="tabs-nav-container">
            <div class="tabs-bar" id="tabs-bar">
                <!-- Dynamically populated room tabs -->
            </div>
            <button id="btn-open-new-tab" class="btn btn-sm btn-primary" title="Join or Open Channel">➕ Open #channel</button>
        </nav>

        <!-- Main Grid Layout -->
        <main class="main-content">
            <!-- Room Lobby / Open Channel Panel -->
            <section id="room-lobby" class="card lobby-card">
                <h2>Join or Create Anonymous IRC Video Channel</h2>
                <p class="subtitle">Direct Peer-to-Peer encrypted communication. Share via <code>domain.com/#&lt;channel&gt;</code> (e.g. <code>#lobby</code>, <code>#general</code>).</p>

                <div class="lobby-controls">
                    <div class="input-row">
                        <div class="input-group">
                            <label for="room-input">Channel Name (Scheme: domain.com/#&lt;channel&gt;)</label>
                            <input type="text" id="room-input" value="<?php echo htmlspecialchars($urlRoom, ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. #general, #fortress" autocomplete="off" spellcheck="false">
                        </div>
                        <div class="input-group">
                            <label for="key-input">Channel Passkey (Optional)</label>
                            <input type="text" id="key-input" placeholder="Optional CHANSERV key" autocomplete="off" spellcheck="false">
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="nickname-input">Your IRC Nickname (Generated or Custom)</label>
                        <div class="nickname-box">
                            <input type="text" id="nickname-input" placeholder="e.g. CyberFox" autocomplete="off">
                            <button id="btn-random-name" class="btn btn-secondary btn-sm" type="button">🎲 Randomize Nick</button>
                        </div>
                    </div>

                    <div class="button-group">
                        <button id="btn-create-room" class="btn btn-primary">➕ Create #channel</button>
                        <button id="btn-join-room" class="btn btn-secondary">🚀 Join #channel</button>
                    </div>
                </div>

                <div id="room-share-section" class="room-share hidden">
                    <p>🔒 Shareable IRC Channel Link (domain.com/#&lt;channel&gt; scheme):</p>
                    <div class="share-box">
                        <input type="text" id="share-url" readonly>
                        <button id="btn-copy-link" class="btn btn-primary btn-sm">📋 Copy Channel Link</button>
                    </div>
                </div>
            </section>

            <!-- Video Stage Area -->
            <section id="video-stage" class="card video-card hidden">
                <div class="video-grid" id="video-grid">
                    <!-- Local Self Video Container -->
                    <div class="video-wrapper local-wrapper">
                        <video id="local-video" autoplay playsinline muted></video>
                        <div class="video-label">
                            <span class="dot live-dot"></span> <span id="local-name-label">You</span>
                        </div>
                    </div>

                    <!-- Remote Peer Video Container -->
                    <div class="video-wrapper remote-wrapper" id="remote-wrapper">
                        <video id="remote-video" autoplay playsinline></video>
                        <div class="video-placeholder" id="remote-placeholder">
                            <div class="pulse-ring"></div>
                            <span class="placeholder-icon">👤</span>
                            <p id="remote-status-text">Waiting for peer to join channel...</p>
                        </div>
                        <div class="video-label" id="remote-label">
                            <span class="dot"></span> <span id="remote-name-label">Remote Peer</span>
                        </div>
                    </div>
                </div>

                <!-- Call Control Toolbar -->
                <div class="call-controls">
                    <button id="btn-toggle-mic" class="btn-control" title="Mute Microphone" aria-label="Mute Microphone" aria-pressed="false">
                        <span class="icon" aria-hidden="true">🎙️</span>
                    </button>
                    <button id="btn-toggle-cam" class="btn-control" title="Turn Off Camera" aria-label="Turn Off Camera" aria-pressed="false">
                        <span class="icon" aria-hidden="true">📹</span>
                    </button>
                    <button id="btn-share-screen" class="btn-control" title="Share Screen" aria-label="Share Screen" aria-pressed="false">
                        <span class="icon" aria-hidden="true">🖥️</span>
                    </button>
                    <button id="btn-leave-call" class="btn-control btn-danger" title="Leave Channel" aria-label="Leave Channel">
                        <span class="icon" aria-hidden="true">📞</span> Leave Channel
                    </button>
                </div>
            </section>

            <!-- Text & DataChannel Chat Panel with IRC User List -->
            <section id="chat-panel" class="card chat-card hidden">
                <div class="chat-header">
                    <div>
                        <h3 id="chat-channel-title">💬 #lobby (P2P DataChannel Chat)</h3>
                        <div id="channel-topic-bar" class="channel-topic">Topic: Welcome to IVC IRC WebRTC!</div>
                    </div>
                    <span class="chat-security-label">🔒 End-to-End Encrypted</span>
                </div>
                <div class="chat-body-container">
                    <div id="chat-messages" class="chat-messages">
                        <div class="system-message">
                            Channel initialized. Type <code>/help</code> for IRC commands (NAMESERV, CHANSERV).
                        </div>
                    </div>
                    <div class="user-list-sidebar">
                        <h4>Nicks in Channel</h4>
                        <ul id="user-list" class="user-list">
                            <!-- Populated dynamically -->
                        </ul>
                    </div>
                </div>
                <div class="chat-input-area">
                    <input type="text" id="chat-input" placeholder="Type a message or IRC command (/msg CHANSERV, /op, /topic, /help)..." autocomplete="off">
                    <button id="btn-send-chat" class="btn btn-primary">Send</button>
                </div>
            </section>

            <!-- Special Connection Stats Room Stage (#stats) -->
            <section id="stats-stage" class="card stats-card hidden">
                <div class="stats-header">
                    <h2>📊 IRC Server & WebRTC Connection Stats (#stats)</h2>
                    <button id="btn-refresh-stats" class="btn btn-secondary btn-sm">🔄 Refresh Stats</button>
                </div>

                <div class="stats-grid">
                    <!-- Server / MySQL Stats Card -->
                    <div class="stats-panel">
                        <h3>🌐 IRC Infrastructure & MySQL Database</h3>
                        <div id="server-stats-content" class="stats-list">
                            <div class="stats-row"><span class="stats-label">Database Status:</span><span class="stats-value">Checking...</span></div>
                        </div>
                    </div>

                    <!-- Client WebRTC Connection Stats Card -->
                    <div class="stats-panel">
                        <h3>⚡ Client WebRTC Connection Metrics</h3>
                        <div id="client-stats-content" class="stats-list">
                            <div class="stats-row"><span class="stats-label">Open Channel Tabs:</span><span class="stats-value">0</span></div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="footer">
            <p>Protected by <strong>The Fortress IT Security Infrastructure</strong> | PHP 8.5 & MySQL IRC Network</p>
        </footer>
    </div>

    <script>
        window.FORTRESS_CSRF_TOKEN = "<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>";
        window.FORTRESS_PRELOAD_ROOM = "<?php echo htmlspecialchars($urlRoom, ENT_QUOTES, 'UTF-8'); ?>";
    </script>
    <script src="assets/js/webrtc.js"></script>
</body>
</html>
