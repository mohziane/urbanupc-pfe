/**
 * UrbanUpC — Floating Chat Widget
 *
 * VULNERABILITIES (intentional, cyber-range):
 *  - Broken Access Control : currentChannel can be set to 'staff' via JS console
 *    (e.g. chatWidget.switchChannel('staff')) by any authenticated user; the API
 *    will happily serve staff messages.
 *  - Stored XSS : msg.message is injected via innerHTML without sanitization.
 */

(function () {
    'use strict';

    // ── State ──────────────────────────────────────────────────────────────────
    var currentChannel = 'general';
    var lastMessageId  = 0;
    var isOpen         = false;
    var unreadCount    = 0;
    var pollTimer      = null;
    var isSending      = false;

    // ── DOM refs (resolved after DOMContentLoaded) ─────────────────────────────
    var toggleBtn, chatWindow, badge, messagesArea,
        chatForm, chatInput, channelBtns, channelLabel, closeBtn;

    // ── Bootstrap (lazy) ──────────────────────────────────────────────────────

    function init() {
        toggleBtn     = document.getElementById('chat-toggle-btn');
        chatWindow    = document.getElementById('chat-window');
        badge         = document.getElementById('chat-notif-badge');
        messagesArea  = document.getElementById('chat-messages');
        chatForm      = document.getElementById('chat-form');
        chatInput     = document.getElementById('chat-input');
        channelLabel  = document.getElementById('chat-channel-label');
        closeBtn      = document.getElementById('chat-close-btn');
        channelBtns   = document.querySelectorAll('.chat-ch-btn');

        if (!toggleBtn) return; // widget absent (page publique)

        toggleBtn.addEventListener('click', toggleWindow);
        closeBtn.addEventListener('click', closeWindow);
        chatForm.addEventListener('submit', onSubmit);

        channelBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                switchChannel(btn.dataset.channel, btn.dataset.label);
            });
        });

        // First load + start polling
        fetchMessages(true);
        pollTimer = setInterval(function () { fetchMessages(false); }, 3000);
    }

    // ── Window toggle ──────────────────────────────────────────────────────────

    function toggleWindow() {
        if (isOpen) { closeWindow(); } else { openWindow(); }
    }

    function openWindow() {
        isOpen = true;
        chatWindow.classList.add('open');
        toggleBtn.classList.add('is-open');
        resetBadge();
        scrollToBottom();
    }

    function closeWindow() {
        isOpen = false;
        chatWindow.classList.remove('open');
        toggleBtn.classList.remove('is-open');
    }

    // ── Channel switching ──────────────────────────────────────────────────────

    function switchChannel(channel, label) {
        currentChannel = channel;
        lastMessageId  = 0;
        messagesArea.innerHTML = '';

        channelBtns.forEach(function (b) {
            b.classList.toggle('active', b.dataset.channel === channel);
        });

        if (channelLabel) {
            channelLabel.textContent = label || channel;
        }
        if (chatInput) {
            chatInput.placeholder = 'Message dans #' + (label || channel) + '\u2026';
        }

        fetchMessages(true);
    }

    // ── Fetch messages (polling) ───────────────────────────────────────────────

    function fetchMessages(initial) {
        var url = '/api/chat_api.php?action=fetch'
                + '&channel=' + encodeURIComponent(currentChannel)
                + '&last_id=' + lastMessageId;

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) return;

                var msgs = data.messages || [];

                if (initial && msgs.length === 0) {
                    messagesArea.innerHTML =
                        '<div class="chat-empty">'
                        + '<i class="fas fa-comments" style="font-size:2rem;opacity:.2;display:block;margin-bottom:.5rem"></i>'
                        + 'Aucun message pour l\'instant. Soyez le premier !'
                        + '</div>';
                    return;
                }

                var newCount = 0;
                msgs.forEach(function (msg) {
                    // Remove empty placeholder on first real message
                    var empty = messagesArea.querySelector('.chat-empty');
                    if (empty) empty.remove();

                    appendMessage(msg);
                    if (msg.id > lastMessageId) lastMessageId = parseInt(msg.id, 10);
                    newCount++;
                });

                if (newCount > 0) {
                    if (isOpen) {
                        scrollToBottom();
                    } else {
                        unreadCount += newCount;
                        renderBadge();
                    }
                }
            })
            .catch(function () { /* silently ignore network errors */ });
    }

    // ── Append a single message ────────────────────────────────────────────────

    function appendMessage(msg) {
        var div = document.createElement('div');
        div.className = 'chat-msg';

        var name = (msg.first_name || '') + ' ' + (msg.last_name || '');
        var time = formatTime(msg.created_at);
        var roleLabel = roleText(msg.user_role);

        // VULNERABILITY: Stored XSS — msg.message injecté via innerHTML,
        // aucun assainissement (htmlspecialchars absent côté serveur et côté client).
        div.innerHTML =
            '<div class="chat-msg-meta">'
            +   '<span class="chat-msg-name">' + escName(name) + '</span>'
            +   '<span class="chat-msg-role">' + roleLabel + '</span>'
            +   '<span class="chat-msg-time">' + time + '</span>'
            + '</div>'
            + '<div class="chat-msg-body">' + msg.message + '</div>';

        messagesArea.appendChild(div);
    }

    // ── Send message ───────────────────────────────────────────────────────────

    function onSubmit(e) {
        e.preventDefault();
        if (isSending) return;

        var msg = chatInput.value.trim();
        if (!msg) return;

        isSending = true;
        chatInput.disabled = true;

        var url = '/api/chat_api.php?action=send'
                + '&channel=' + encodeURIComponent(currentChannel);

        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'message=' + encodeURIComponent(msg),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                chatInput.value = '';
                fetchMessages(false);
            }
        })
        .catch(function () { /* ignore */ })
        .finally(function () {
            isSending = false;
            chatInput.disabled = false;
            chatInput.focus();
        });
    }

    // ── Badge ──────────────────────────────────────────────────────────────────

    function renderBadge() {
        if (!badge) return;
        if (unreadCount > 0) {
            badge.textContent = unreadCount > 9 ? '9+' : String(unreadCount);
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }

    function resetBadge() {
        unreadCount = 0;
        renderBadge();
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    function scrollToBottom() {
        if (messagesArea) messagesArea.scrollTop = messagesArea.scrollHeight;
    }

    function formatTime(ts) {
        if (!ts) return '';
        // ts is "YYYY-MM-DD HH:MM:SS" from MySQL — replace space with T for Safari
        var d = new Date(ts.replace(' ', 'T'));
        if (isNaN(d)) return ts;
        return d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    }

    function roleText(role) {
        var map = { user: 'Étudiant', manager: 'Manager', admin: 'Admin' };
        return map[role] || role || '';
    }

    // Minimal name escaping (XSS not intentional on names — they come from DB via PHP htmlspecialchars)
    function escName(s) {
        return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // ── Public API (accessible depuis la console — démo BAC) ──────────────────
    // Un attaquant peut appeler : chatWidget.switchChannel('staff', 'Staff')
    window.chatWidget = { switchChannel: switchChannel };

    // ── Entry point ───────────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

}());
