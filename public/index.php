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
                    <button id="btn-toggle-mic" class="btn-control" title="Toggle Microphone">
                        <span class="icon">🎙️</span>
                    </button>
                    <button id="btn-toggle-cam" class="btn-control" title="Toggle Camera">
                        <span class="icon">📹</span>
                    </button>
                    <button id="btn-share-screen" class="btn-control" title="Share Screen">
                        <span class="icon">🖥️</span>
                    </button>
                    <button id="btn-leave-call" class="btn-control btn-danger" title="Leave Channel">
                        <span class="icon">📞</span> Leave Channel
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
                    <button id="btn-open-dcc-modal" class="btn btn-secondary" title="DCC Direct & Multi-GB Cloud File Share">📁 DCC Share</button>
                    <input type="text" id="chat-input" placeholder="Type a message or IRC command (/msg CHANSERV, /dcc, /op, /topic, /help)..." autocomplete="off">
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

        <!-- DCC File Sharing Modal -->
        <div id="dcc-modal" class="modal-overlay hidden">
            <div class="modal-content card">
                <div class="modal-header">
                    <h3>📁 DCC File Sharing</h3>
                    <button id="btn-close-dcc-modal" class="btn-close">&times;</button>
                </div>
                <div class="dcc-tabs">
                    <button id="tab-dcc-direct" class="dcc-tab-btn active" type="button">⚡ Direct WebRTC P2P Transfer</button>
                    <button id="tab-dcc-cloud" class="dcc-tab-btn" type="button">☁️ Cloud Share (Google Drive / Mega)</button>
                </div>

                <!-- Direct WebRTC Panel -->
                <div id="dcc-panel-direct" class="dcc-panel">
                    <p class="subtitle">Stream files directly browser-to-browser over encrypted WebRTC DataChannel.</p>
                    <div class="input-group">
                        <label for="dcc-file-input">Select Local File:</label>
                        <input type="file" id="dcc-file-input" class="file-input">
                    </div>
                    <div id="dcc-file-info" class="dcc-file-info hidden">
                        <p><strong>Selected:</strong> <span id="dcc-file-name"></span> (<span id="dcc-file-size"></span>)</p>
                    </div>
                    <!-- Large File Warning Prompt -->
                    <div id="dcc-large-file-warning" class="dcc-warning-banner hidden">
                        ⚠️ <strong>Multi-Gigabyte File Detected!</strong><br>
                        Direct browser-to-browser WebRTC memory transfers can be constrained by browser RAM limits. For files &gt; 100MB / multi-GB, we recommend sharing via <strong>Google Drive</strong>, <strong>Mega.nz</strong>, or <strong>Dropbox</strong>.
                        <button id="btn-switch-to-cloud" class="btn btn-sm btn-primary" style="margin-top: 8px;" type="button">Switch to Cloud Sharing ☁️</button>
                    </div>
                    <div class="modal-actions">
                        <button id="btn-send-dcc-direct" class="btn btn-primary" disabled type="button">🚀 Send Direct DCC Offer</button>
                    </div>
                </div>

                <!-- Cloud Sharing Panel (Google Drive, Mega, Dropbox) -->
                <div id="dcc-panel-cloud" class="dcc-panel hidden">
                    <p class="subtitle">Ideal for multi-gigabyte files (5GB, 10GB, 50GB+). Share Google Drive or Mega.nz links in DCC format.</p>
                    <div class="input-group">
                        <label for="dcc-cloud-service">Cloud Service Provider:</label>
                        <select id="dcc-cloud-service" class="select-input">
                            <option value="GoogleDrive">Google Drive 🔵🟢🔴</option>
                            <option value="Mega">Mega.nz 🔴</option>
                            <option value="Dropbox">Dropbox 🔵</option>
                            <option value="Other">Other Direct URL 🌐</option>
                        </select>
                    </div>
                    <div class="input-group" style="margin-top: 12px;">
                        <label for="dcc-cloud-url">Shareable Cloud Link URL:</label>
                        <input type="text" id="dcc-cloud-url" placeholder="https://drive.google.com/file/d/... or https://mega.nz/file/..." autocomplete="off">
                    </div>
                    <div class="input-row" style="margin-top: 12px;">
                        <div class="input-group">
                            <label for="dcc-cloud-filename">File Name (Optional):</label>
                            <input type="text" id="dcc-cloud-filename" placeholder="e.g. Large_Dataset_50GB.tar" autocomplete="off">
                        </div>
                        <div class="input-group">
                            <label for="dcc-cloud-filesize">File Size (Optional):</label>
                            <input type="text" id="dcc-cloud-filesize" placeholder="e.g. 5.4 GB" autocomplete="off">
                        </div>
                    </div>
                    <div class="modal-actions" style="margin-top: 16px;">
                        <button id="btn-send-dcc-cloud" class="btn btn-primary" type="button">☁️ Share Cloud DCC Link</button>
                    </div>
                </div>
            </div>
        </div>

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
