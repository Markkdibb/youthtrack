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

<script>
const MY_ID      = <?= (int)$_SESSION['user_id'] ?>;
const IS_ADMIN   = <?= isAdmin() ? 'true' : 'false' ?>;
const API_URL    = '<?= SITE_URL ?>/pages/api.php';
let lastMsgId    = 0;
let isPolling    = false;

async function loadMessages() {
    if (isPolling) return;
    isPolling = true;
    try {
        const res  = await fetch(`${API_URL}?action=get_messages&since=${lastMsgId}`);
        const data = await res.json();
        if (data.messages && data.messages.length) {
            const container = document.getElementById('chatMessages');
            const wasAtBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 60;
            if (lastMsgId === 0) container.innerHTML = '';

            data.messages.forEach(m => {
                lastMsgId = Math.max(lastMsgId, m.id);
                appendMessage(m);
            });

            if (wasAtBottom || lastMsgId === data.messages[data.messages.length-1].id) {
                container.scrollTop = container.scrollHeight;
            }
        } else if (lastMsgId === 0) {
            document.getElementById('chatMessages').innerHTML = '<div class="empty-state"><i class="fas fa-comments"></i><p>No messages yet. Say hello!</p></div>';
        }
    } catch(e) { console.error(e); }
    isPolling = false;
}

</script>