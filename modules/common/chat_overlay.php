<!-- Floating Chat Widget -->
<?php
// Ensure this is only included for authorized roles
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'registrar', 'department_head', 'transcript_pro', 'cost_sharing_pro'])) {
    return;
}
?>
<style>
    /* Floating Button */
    #chat-fab {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 60px;
        height: 60px;
        background-color: #007bff;
        color: #fff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        cursor: pointer;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        z-index: 9999;
        transition: transform 0.3s ease;
    }

    #chat-fab:hover {
        transform: scale(1.1);
        background-color: #0056b3;
    }

    #chat-notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background-color: #e74c3c;
        color: #fff;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #fff;
        display: none;
        /* Hidden by default */
    }

    /* Chat Window */
    #chat-window {
        position: fixed;
        bottom: 90px;
        right: 20px;
        width: 350px;
        height: 500px;
        background-color: #fff;
        border-radius: 12px;
        box-shadow: 0 5px 15px rgba(16, 73, 180, 0.62);
        z-index: 9998;
        display: none;
        /* Hidden by default */
        flex-direction: column;
        overflow: hidden;
        border: 1px solid #ddd;
    }

    #chat-header {
        background-color: #007bff;
        color: #fff;
        padding: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: bold;
    }

    #chat-header .title {
        font-size: 16px;
    }

    #chat-header .close-icon {
        cursor: pointer;
    }

    #chat-body {
        flex: 1;
        overflow-y: auto;
        padding: 10px;
        background-color: #f9f9f9;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    /* User List Styles */
    .chat-user-item {
        padding: 10px;
        border-bottom: 1px solid #eee;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: background 0.2s;
    }

    .chat-user-item:hover {
        background-color: #f0f0f0;
    }

    .chat-user-avatar {
        width: 35px;
        height: 35px;
        background-color: #333;
        color: #fff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        font-weight: bold;
    }

    /* Message Styles */
    .message-bubble {
        max-width: 80%;
        padding: 8px 12px;
        border-radius: 15px;
        font-size: 14px;
        line-height: 1.4;
        position: relative;
    }

    .message-sent {
        background-color: #007bff;
        color: #fff;
        align-self: flex-end;
        border-bottom-right-radius: 2px;
    }

    .message-received {
        background-color: #fff;
        border: 1px solid #ddd;
        align-self: flex-start;
        border-bottom-left-radius: 2px;
    }

    #chat-footer {
        padding: 10px;
        border-top: 1px solid #ddd;
        background-color: #fff;
        display: flex;
        /* Hidden when viewing user list */
        gap: 8px;
    }

    #chat-input {
        flex: 1;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 20px;
        outline: none;
    }

    #chat-send-btn {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        background-color: #007bff;
        color: #fff;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Back Button for User List */
    #back-to-users {
        cursor: pointer;
        margin-right: 10px;
        display: none;
        /* Hidden by default */
    }

    /* Responsive adjustments */
    /* Context Menu */
    #context-menu {
        position: fixed;
        background: #fff;
        border: 1px solid #ccc;
        box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.2);
        z-index: 10000;
        display: none;
        border-radius: 4px;
        overflow: hidden;
        min-width: 120px;
    }

    #context-menu div {
        padding: 10px 15px;
        cursor: pointer;
        font-size: 14px;
        color: #333;
        transition: background 0.2s;
    }

    #context-menu div:hover {
        background-color: #f0f0f0;
    }

    @media (max-width: 480px) {
        #chat-window {
            width: 90%;
            bottom: 90px;
            right: 5%;
        }
    }
</style>

<div id="chat-fab" onclick="toggleChat()">
    <i class="fas fa-comments"></i>
    <div id="chat-notification-badge">0</div>
</div>

<div id="chat-window">
    <div id="chat-header">
        <div style="display:flex; align-items:center;">
            <i class="fas fa-arrow-left" id="back-to-users" onclick="showUserList()"></i>
            <span class="title" id="chat-title" data-en="Chat" data-am="ውይይት">Chat</span>
        </div>
        <i class="fas fa-times close-icon" onclick="toggleChat()"></i>
    </div>

    <div id="chat-body">
        <!-- Content injected by JS -->
        <div style="text-align:center; padding:20px; color:#666;" data-en="Loading..." data-am="በመጫን ላይ...">Loading...
        </div>
    </div>

    <div id="chat-footer" style="display:none;">
        <input type="text" id="chat-input" placeholder="Type a message..." data-en="Type a message..."
            data-en-placeholder="Type a message..." data-am-placeholder="መልዕክት ይጻፉ..." autocomplete="off">
        <button id="chat-send-btn" onclick="sendChatMessage()"><i class="fas fa-paper-plane"></i></button>
    </div>
</div>

<!-- Context Menu -->
<div id="context-menu">
    <div onclick="editMessageContext()"><i class="fas fa-edit"></i> <span data-en="Edit" data-am="አርም">Edit</span></div>
    <div onclick="deleteMessageContext()"><i class="fas fa-trash-alt" style="color:red;"></i> <span data-en="Delete"
            data-am="ሰርዝ">Delete</span></div>
</div>

<script>
    const chatConfig = {
        role: "<?php echo $_SESSION['role'] ?? ''; ?>",
        userId: <?php echo $_SESSION['user_id'] ?? 0; ?>,
        apiUrl: '/Cost_share/modules/common/chat_api.php',
        currentPartner: null,
        isOpen: false,
        pollInterval: null
    };

    document.addEventListener('DOMContentLoaded', function () {
        // Initial check for unread messages
        checkUnreadCount();
        setInterval(checkUnreadCount, 10000); // Check every 10s

        // Heartbeat (every 30s)
        setInterval(() => {
            fetch(chatConfig.apiUrl + '?action=heartbeat');
        }, 30000);
        // Initial heartbeat
        fetch(chatConfig.apiUrl + '?action=heartbeat');

        // Hide context menu on click elsewhere
        document.addEventListener('click', function () {
            const menu = document.getElementById('context-menu');
            if (menu) menu.style.display = 'none';
        });

        // Prevent context menu from showing default browser menu on context-menu div
        const contextMenu = document.getElementById('context-menu');
        if (contextMenu) {
            contextMenu.addEventListener('contextmenu', function (e) {
                e.preventDefault();
            });
        }
    });

    // ... (rest of functions)

    function openChat(partnerId, partnerName) {
        chatConfig.currentPartner = partnerId;

        // UI Updates
        if (chatConfig.role === 'registrar') {
            document.getElementById('back-to-users').style.display = 'block';
        }

        // Header with Status (Side-by-side)
        const headerTitle = document.getElementById('chat-title');
        headerTitle.innerHTML = `
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:16px;">${partnerName}</span>
                <span id="chat-status" style="font-size:12px; font-weight:normal; color:#ccc;"></span>
            </div>
        `;

        document.getElementById('chat-footer').style.display = 'flex';

        const body = document.getElementById('chat-body');
        const loadingText = localStorage.getItem('dmu_lang') === 'am' ? 'ውይይት በመጫን ላይ...' : 'Loading chat...';
        body.innerHTML = `<div style="text-align:center; padding:20px;">${loadingText}</div>`;

        // Mark read
        markAsRead(partnerId);

        loadMessages(); // Initial load
        updateUserStatus(partnerId); // Initial status check

        // Start polling
        if (chatConfig.pollInterval) clearInterval(chatConfig.pollInterval);
        chatConfig.pollInterval = setInterval(() => {
            loadMessages();
            updateUserStatus(partnerId);
        }, 3000);
    }

    function updateUserStatus(partnerId) {
        const statusDiv = document.getElementById('chat-status');
        if (!statusDiv) return;

        fetch(`${chatConfig.apiUrl}?action=get_user_status&user_id=${partnerId}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) return;

                const secondsAgo = parseInt(data.seconds_ago);

                if (secondsAgo < 120) { // Considered online if active in last 2 mins
                    statusDiv.innerHTML = '<span style="color:#2ecc71;">●</span> Online';
                } else {
                    // Format Last Seen
                    const now = new Date();
                    // Fix: User reports DB time mismatch. 
                    // To be safe, we rely on current client time minus seconds_ago.
                    const seenTime = new Date(now.getTime() - (secondsAgo * 1000));

                    const timeString = seenTime.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    const isToday = seenTime.toDateString() === now.toDateString();

                    if (isToday) {
                        statusDiv.innerText = localStorage.getItem('dmu_lang') === 'am' ? `በ ${timeString} ታይቷል` : `last seen at ${timeString}`;
                    } else {
                        statusDiv.innerText = localStorage.getItem('dmu_lang') === 'am' ? `${seenTime.toLocaleDateString()} በ ${timeString} ታይቷል` : `last seen ${seenTime.toLocaleDateString()} at ${timeString}`;
                    }
                }
            });
    }

    function timeAgo(seconds) {
        // Fallback or specific relative logic if desired
        if (isNaN(seconds)) return 'Offline';
        if (seconds < 60) return 'Just now';
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return `${minutes} mins ago`;
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours} hours ago`;
        return 'Long ago';
    }

    // ... (rest of functions)

    function toggleChat() {
        const window = document.getElementById('chat-window');
        const fab = document.getElementById('chat-fab');

        chatConfig.isOpen = !chatConfig.isOpen;

        if (chatConfig.isOpen) {
            window.style.display = 'flex';
            fab.innerHTML = '<i class="fas fa-chevron-down"></i>';
            initializeChat();
        } else {
            window.style.display = 'none';
            fab.innerHTML = '<i class="fas fa-comments"></i>';
            if (chatConfig.pollInterval) clearInterval(chatConfig.pollInterval);
        }
    }

    function initializeChat() {
        if (chatConfig.role === 'registrar') {
            showUserList();
        } else {
            // For non-registrars, auto-connect to registrar
            // Need to find registrar ID. API fetch_users for non-registrar returns only registrar(s)
            fetchUsersAndConnect();
        }
    }

    function showUserList() {
        document.getElementById('back-to-users').style.display = 'none';
        document.getElementById('chat-title').textContent = localStorage.getItem('dmu_lang') === 'am' ? 'መልዕክቶች' : 'Messages';
        document.getElementById('chat-title').setAttribute('data-en', 'Messages');
        document.getElementById('chat-title').setAttribute('data-am', 'መልዕክቶች');
        document.getElementById('chat-footer').style.display = 'none';
        chatConfig.currentPartner = null;
        if (chatConfig.pollInterval) clearInterval(chatConfig.pollInterval);

        const body = document.getElementById('chat-body');
        const loadingUsers = localStorage.getItem('dmu_lang') === 'am' ? 'ተጠቃሚዎችን በመጫን ላይ...' : 'Loading users...';
        body.innerHTML = `<div style="text-align:center; padding:20px;">${loadingUsers}</div>`;

        fetch(chatConfig.apiUrl + '?action=fetch_users')
            .then(res => {
                if (!res.ok) throw new Error('Network response was not ok');
                return res.json();
            })
            .then(users => {
                body.innerHTML = '';

                // Search box (for registrar)
                if (chatConfig.role === 'registrar') {
                    const searchBox = document.createElement('div');
                    searchBox.style.cssText = 'padding:8px 10px; border-bottom:1px solid #eee; position:sticky; top:0; background:#fff; z-index:1;';
                    searchBox.innerHTML = `
                        <input type="text" id="chat-user-search" placeholder="🔍 Search by name or department..." data-en="🔍 Search by name or department..." data-en-placeholder="🔍 Search by name or department..." data-am-placeholder="🔍 በስም ወይም በትምህርት ክፍል ይፈልጉ..." style="width:80%; padding:7px 12px; border:1px solid #ddd; border-radius:20px; outline:none; font-size:13px;" autocomplete="off">
                    `;
                    body.appendChild(searchBox);

                    // Add search event listener
                    setTimeout(() => {
                        const searchInput = document.getElementById('chat-user-search');
                        if (searchInput) {
                            searchInput.addEventListener('input', function () {
                                const query = this.value.toLowerCase();
                                const items = body.querySelectorAll('.chat-user-item');
                                items.forEach(item => {
                                    const name = (item.getAttribute('data-name') || '').toLowerCase();
                                    const dept = (item.getAttribute('data-dept') || '').toLowerCase();
                                    if (name.includes(query) || dept.includes(query)) {
                                        item.style.display = 'flex';
                                    } else {
                                        item.style.display = 'none';
                                    }
                                });
                            });
                        }
                    }, 0);
                }

                if (users.length === 0) {
                    body.innerHTML += '<div style="padding:15px; text-align:center;">No users found.</div>';
                    return;
                }

                users.forEach(user => {
                    const div = document.createElement('div');
                    div.className = 'chat-user-item';
                    const initial = user.first_name.charAt(0).toUpperCase();
                    const fullName = `${user.first_name} ${user.last_name}`;
                    const deptName = user.dept_name || '';

                    // Set data attributes for search filtering
                    div.setAttribute('data-name', fullName);
                    div.setAttribute('data-dept', deptName);

                    let badgeHtml = '';
                    if (user.unread_count > 0) {
                        badgeHtml = `<div style="background:red; color:white; border-radius:50%; width:20px; height:20px; display:flex; align-items:center; justify-content:center; font-size:10px; margin-left:5px;">${user.unread_count}</div>`;
                    }

                    // Online indicator logic
                    let onlineHtml = '';
                    if (user.seconds_ago !== null && parseInt(user.seconds_ago) < 120) {
                        onlineHtml = '<span style="color:#2ecc71; font-size:10px; margin-left:5px;">● Online</span>';
                    }

                    // Department name for department_head role
                    let deptHtml = '';
                    if (user.role === 'department_head' && deptName) {
                        deptHtml = `<div style="font-size:11px; color:#007bff; font-weight:500;"><i class="fas fa-building" style="font-size:9px; margin-right:3px;"></i>${deptName}</div>`;
                    }

                    // Role display
                    let roleDisplay = user.role.replace(/_/g, ' ');

                    div.innerHTML = `
                        <div class="chat-user-avatar" style="position:relative;">
                            ${initial}
                        </div>
                        <div style="flex:1;">
                            <div style="font-weight:bold; display:flex; align-items:center;">
                                ${fullName}
                                ${onlineHtml}
                            </div>
                            ${deptHtml}
                            <div style="font-size:12px; color:#666;">${roleDisplay}</div>
                        </div>
                        ${badgeHtml}
                    `;
                    div.onclick = () => openChat(user.id, user.first_name);
                    body.appendChild(div);
                });
            })
            .catch(error => {
                body.innerHTML = '<div style="padding:20px; color:red; text-align:center;">Error loading users.<br><small>Check console for details.</small></div>';
                console.error('Chat Error:', error);
            });
    }

    function fetchUsersAndConnect() {
        fetch(chatConfig.apiUrl + '?action=fetch_users')
            .then(res => res.json())
            .then(users => {
                if (users.length > 0) {
                    // Connect to the first registrar found
                    openChat(users[0].id, users[0].first_name);
                } else {
                    document.getElementById('chat-body').innerHTML = '<div style="padding:20px;">Registrar not found.</div>';
                }
            });
    }

    function openChat(partnerId, partnerName) {
        chatConfig.currentPartner = partnerId;

        // UI Updates
        if (chatConfig.role === 'registrar') {
            document.getElementById('back-to-users').style.display = 'block';
        }
        document.getElementById('chat-title').textContent = partnerName;
        document.getElementById('chat-footer').style.display = 'flex';

        const body = document.getElementById('chat-body');
        const loadingChat = localStorage.getItem('dmu_lang') === 'am' ? 'ውይይት በመጫን ላይ...' : 'Loading chat...';
        body.innerHTML = `<div style="text-align:center; padding:20px;">${loadingChat}</div>`;

        // Mark read
        markAsRead(partnerId);

        loadMessages(); // Initial load

        // Start polling
        if (chatConfig.pollInterval) clearInterval(chatConfig.pollInterval);
        chatConfig.pollInterval = setInterval(loadMessages, 3000);
    }

    function markAsRead(partnerId) {
        const formData = new FormData();
        formData.append('sender_id', partnerId);
        fetch(chatConfig.apiUrl + '?action=mark_read', {
            method: 'POST',
            body: formData
        }).then(() => {
            checkUnreadCount();
        });
    }

    function loadMessages() {
        if (!chatConfig.currentPartner) return;

        // Prevent overwriting UI if user is editing or deleting
        if (document.querySelector('.edit-mode') || document.querySelector('.delete-mode')) {
            return;
        }

        fetch(`${chatConfig.apiUrl}?action=fetch_messages&partner_id=${chatConfig.currentPartner}`)
            .then(res => res.json())
            .then(messages => {
                const body = document.getElementById('chat-body');
                const wasScrolledToBottom = body.scrollHeight - body.clientHeight <= body.scrollTop + 50;

                // Optimized rendering could diff, but rebuilding is safer for synchronization
                // We'll just replace innerHTML for now, but to avoid flicker maybe check header
                // However, building a string is fast.

                let html = '';
                if (messages.length === 0) {
                    const noMsg = localStorage.getItem('dmu_lang') === 'am' ? 'እስካሁን ምንም መልዕክት የለም። ውይይት ይጀምሩ!' : 'No messages yet. Start conversation!';
                    html = `<div style="text-align:center; color:#999; margin-top:50px;">${noMsg}</div>`;
                } else {
                    messages.forEach(msg => {
                        const isMe = msg.sender_id == chatConfig.userId;
                        const safeMsg = msg.message.replace(/"/g, '&quot;');

                        let statusHtml = '';
                        if (isMe) {
                            if (msg.is_read == 1) {
                                // Seen: Blue double check
                                statusHtml = '<div style="font-size:10px; color:#3498db; text-align:right; margin-top:2px; font-weight:bold;">Seen <i class="fas fa-check-double"></i></div>';
                            } else {
                                // Sent: Grey single check
                                statusHtml = '<div style="font-size:10px; color:#aaa; text-align:right; margin-top:2px;"><i class="fas fa-check"></i></div>';
                            }
                        }

                        html += `<div class="message-bubble ${isMe ? 'message-sent' : 'message-received'}"
                                      data-id="${msg.id}" 
                                      data-text="${safeMsg}"
                                      oncontextmenu="${isMe ? 'showContextMenu(event, this)' : ''}">
                                      ${msg.message}
                                      ${statusHtml}
                                 </div>`;
                    });
                }

                // Only update DOM if content changed (simple check)
                if (body.innerHTML !== html) {
                    body.innerHTML = html;
                    if (wasScrolledToBottom || messages.length === 0) {
                        body.scrollTop = body.scrollHeight;
                    }
                }
            });
    }

    function sendChatMessage() {
        const input = document.getElementById('chat-input');
        const message = input.value.trim();

        if (!message || !chatConfig.currentPartner) return;

        const formData = new FormData();
        formData.append('receiver_id', chatConfig.currentPartner);
        formData.append('message', message);

        fetch(chatConfig.apiUrl + '?action=send_message', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    input.value = '';
                    loadMessages(); // Immediate refresh
                }
            });
    }

    // Allow Enter key to send
    document.getElementById('chat-input').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            sendChatMessage();
        }
    });

    function checkUnreadCount() {
        fetch(chatConfig.apiUrl + '?action=unread_count')
            .then(res => res.json())
            .then(data => {
                const badge = document.getElementById('chat-notification-badge');
                if (data.count > 0) {
                    badge.textContent = data.count;
                    badge.style.display = 'flex';
                } else {
                    badge.style.display = 'none';
                }
            })
            .catch(err => console.log('Chat poll error'));
    }

    /* Context Menu Logic */
    function showContextMenu(e, element) {
        e.preventDefault();
        const menu = document.getElementById('context-menu');

        chatConfig.selectedMessageId = element.getAttribute('data-id');
        chatConfig.selectedMessageText = element.getAttribute('data-text');

        // Adjust position to stay within viewport
        let x = e.pageX;
        let y = e.pageY;

        // Simple check (can be improved)
        if (x + 150 > window.innerWidth) x -= 150;
        if (y + 100 > window.innerHeight) y -= 100;

        menu.style.display = 'block';
        menu.style.left = x + 'px';
        menu.style.top = y + 'px';
    }

    function deleteMessageContext() {
        document.getElementById('context-menu').style.display = 'none';

        // Find the message bubble
        const bubble = document.querySelector(`.message-bubble[data-id="${chatConfig.selectedMessageId}"]`);
        if (!bubble) return;

        const currentText = chatConfig.selectedMessageText || bubble.innerText.trim();

        // Inline Confirmation
        bubble.innerHTML = `
            <div class="delete-mode" style="display:flex; flex-direction:column; gap:5px; background:#ffe6e6; padding:5px; border-radius:8px;">
                <div style="font-size:12px; font-weight:bold; color:#cc0000; text-align:center;">${localStorage.getItem('dmu_lang') === 'am' ? 'ይህንን መልዕክት መሰረዝ ይፈልጋሉ?' : 'Delete this message?'}</div>
                <div style="display:flex; justify-content:center; gap:10px;">
                    <button onclick="confirmDeleteInline(${chatConfig.selectedMessageId}, this)" style="background:red; color:white; border:none; padding:4px 10px; border-radius:4px; font-size:12px; cursor:pointer;">${localStorage.getItem('dmu_lang') === 'am' ? 'አዎ' : 'Yes'}</button>
                    <button onclick="cancelEdit(${chatConfig.selectedMessageId}, '${currentText.replace(/'/g, "\\'")}', this)" style="background:#ddd; border:none; padding:4px 10px; border-radius:4px; font-size:12px; cursor:pointer;">${localStorage.getItem('dmu_lang') === 'am' ? 'አይ' : 'No'}</button>
                </div>
            </div>
        `;
    }

    function confirmDeleteInline(id, btn) {
        // Optimistically remove
        const bubble = btn.closest('.message-bubble');
        bubble.remove();

        const formData = new FormData();
        formData.append('message_id', id);

        fetch(chatConfig.apiUrl + '?action=delete_message', {
            method: 'POST',
            body: formData
        }).then(() => loadMessages());
    }

    function editMessageContext() {
        document.getElementById('context-menu').style.display = 'none';

        const bubble = document.querySelector(`.message-bubble[data-id="${chatConfig.selectedMessageId}"]`);
        if (!bubble) return;

        const currentText = chatConfig.selectedMessageText || bubble.innerText.trim();
        bubble.innerHTML = `
            <div class="edit-mode" style="display:flex; flex-direction:column; gap:5px;">
                <input type="text" value="${currentText.replace(/"/g, '&quot;')}" class="edit-input" style="width:100%; border:1px solid #ccc; padding:5px; border-radius:4px;">
                <div style="display:flex; gap:5px; justify-content:flex-end;">
                    <button onclick="saveEdit(${chatConfig.selectedMessageId}, this)" style="background:#007bff; color:#fff; border:none; padding:4px 8px; border-radius:4px; font-size:12px; cursor:pointer;">${localStorage.getItem('dmu_lang') === 'am' ? 'ሴቭ' : 'Save'}</button>
                    <button onclick="cancelEdit(${chatConfig.selectedMessageId}, '${currentText.replace(/'/g, "\\'")}', this)" style="background:#ddd; border:none; padding:4px 8px; border-radius:4px; font-size:12px; cursor:pointer;">${localStorage.getItem('dmu_lang') === 'am' ? 'ሰርዝ' : 'Cancel'}</button>
                </div>
            </div>
        `;
        bubble.querySelector('input').focus();
    }

    function saveEdit(id, btn) {
        const input = btn.parentElement.parentElement.querySelector('input');
        const newText = input.value.trim();

        if (newText === "") return;

        const formData = new FormData();
        formData.append('message_id', id);
        formData.append('message', newText);

        fetch(chatConfig.apiUrl + '?action=edit_message', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const bubble = btn.closest('.message-bubble');
                    bubble.innerHTML = newText;
                    bubble.setAttribute('data-text', newText.replace(/"/g, '&quot;'));
                } else {
                    // Show inline error in the bubble
                    const bubble = btn.closest('.message-bubble');
                    bubble.innerHTML = `<div style="color:#cc0000; font-size:12px; padding:4px;">${data.error || 'Edit failed'}</div>`;
                    setTimeout(() => loadMessages(), 2000);
                }
            })
            .catch(err => {
                console.error(err);
                loadMessages();
            });
    }

    function cancelEdit(id, originalText, btn) {
        const bubble = btn.closest('.message-bubble');
        bubble.innerHTML = originalText;
    }
</script>