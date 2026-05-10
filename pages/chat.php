<?php
$pageTitle  = 'Community Chat';
$activePage = 'chat';
require_once __DIR__ . '/../includes/header.php';
$me = getCurrentUser();
?>

<div class="chat-container" style="height:calc(100vh - 180px)">
    <div class="chat-header">
        <div class="chat-header-info">
            <div class="chat-avatar"><i class="fas fa-users"></i></div>
            <div class="chat-header-text">
                <strong>SK Community Chat</strong>
                <span><span class="online-dot"></span>Live Channel</span>
            </div>
        </div>
        <div style="font-size:.8rem;color:var(--gray-400)">Messages auto-update every 3s</div>
    </div>

    <div class="chat-messages" id="chatMessages">
        
        <div style="text-align:center;padding:2rem;color:var(--gray-400)"><i class="fas fa-spinner fa-spin"></i> Loading messages…</div>
    </div>

    <div class="chat-input-area">
        <img src="<?= getAvatarUrl($me['profile_picture']) ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0">
        <textarea class="chat-input" id="chatInput" placeholder="Type a message… (Enter to send, Shift+Enter for new line)" rows="1"></textarea>
        <button class="btn-send" id="sendBtn" onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button>
    </div>
</div>