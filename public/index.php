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

// Parse room ID from request URI if provided as ivc.com/<room> or /?room=<room>
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
    <title>IVC WebRTC — Anonymous & Encrypted Video Chat</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
    <div class="app-container">
        <!-- Top Security Bar -->
        <header class="top-bar">
            <div class="brand">
                <span class="shield-icon">🏰</span>
                <h1>IVC<span class="highlight">WebRTC</span></h1>
            </div>
            <div class="security-badges">
                <span class="badge green">⚡ PHP 8.5</span>
                <span class="badge shield">🔑 Optional Key</span>
                <span class="badge purple">🕵️ Anonymous Joining</span>
                <span class="badge blue">🚫 Non-Logging</span>
            </div>
        </header>

        <!-- Main Grid Layout -->
        <main class="main-content">
            <!-- Room Join / Controls Panel -->
            <section id="room-lobby" class="card lobby-card">
                <h2>Join or Create Anonymous Video Room</h2>
                <p class="subtitle">Direct Peer-to-Peer encrypted communication. Share via <code>ivc.com/&lt;room&gt;</code>. Key is optional!</p>

                <div class="lobby-controls">
                    <div class="input-row">
                        <div class="input-group">
                            <label for="room-input">Room Name / ID (URL Scheme: ivc.com/&lt;room&gt;)</label>
                            <input type="text" id="room-input" value="<?php echo htmlspecialchars($urlRoom, ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. secret-room-42" autocomplete="off" spellcheck="false">
                        </div>
                        <div class="input-group">
                            <label for="key-input">Room Passkey (Optional)</label>
                            <input type="text" id="key-input" placeholder="Optional key for private lock" autocomplete="off" spellcheck="false">
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="nickname-input">Your Anonymous Identity (Generated or Custom)</label>
                        <div class="nickname-box">
                            <input type="text" id="nickname-input" placeholder="e.g. Cyber Fox" autocomplete="off">
                            <button id="btn-random-name" class="btn btn-secondary btn-sm" type="button">🎲 Randomize Name</button>
                        </div>
                    </div>

                    <div class="button-group">
                        <button id="btn-create-room" class="btn btn-primary">➕ Create New Room</button>
                        <button id="btn-join-room" class="btn btn-secondary">🚀 Join Room</button>
                    </div>
                </div>

                <div id="room-share-section" class="room-share hidden">
                    <p>🔒 Shareable Room Link (ivc.com/&lt;room&gt; scheme):</p>
                    <div class="share-box">
                        <input type="text" id="share-url" readonly>
                        <button id="btn-copy-link" class="btn btn-primary btn-sm">📋 Copy Room Link</button>
                    </div>
                </div>
            </section>

            <!-- Video Grid Area -->
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
                            <p id="remote-status-text">Waiting for peer to join...</p>
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
                    <button id="btn-leave-call" class="btn-control btn-danger" title="Leave Call">
                        <span class="icon">📞</span> Leave Call
                    </button>
                </div>
            </section>

            <!-- Text & File Data Channel Chat Panel -->
            <section id="chat-panel" class="card chat-card hidden">
                <div class="chat-header">
                    <h3>💬 Anonymous Peer Chat (P2P DataChannel)</h3>
                    <span class="chat-security-label">🔒 End-to-End Encrypted</span>
                </div>
                <div id="chat-messages" class="chat-messages">
                    <div class="system-message">
                        Room initialized. Messages travel directly peer-to-peer and are never stored on any server.
                    </div>
                </div>
                <div class="chat-input-area">
                    <input type="text" id="chat-input" placeholder="Type an encrypted message..." autocomplete="off">
                    <button id="btn-send-chat" class="btn btn-primary">Send</button>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="footer">
            <p>Protected by <strong>The Fortress IT Security Infrastructure</strong> | PHP 8.5 & WebRTC API</p>
        </footer>
    </div>

    <script>
        window.FORTRESS_CSRF_TOKEN = "<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>";
        window.FORTRESS_PRELOAD_ROOM = "<?php echo htmlspecialchars($urlRoom, ENT_QUOTES, 'UTF-8'); ?>";
    </script>
    <script src="assets/js/webrtc.js"></script>
</body>
</html>
