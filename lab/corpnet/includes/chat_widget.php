<?php
// =============================================================
//  UrbanUpC — Floating Chat Widget
//  Inclus dans navbar.php → présent sur toutes les pages authentifiées
//  VULNERABILITY : la sélection de canal est UI-only. L'API (chat_api.php)
//  n'applique aucun contrôle d'accès côté serveur.
// =============================================================

$_chatUser = currentUser();
if (!$_chatUser) return; // ne rien afficher si pas connecté

// Canaux affichés dans l'UI selon le rôle (même logique que channels.php)
// VULNERABILITY : le canal 'staff' est masqué pour les étudiants dans l'UI
// mais reste accessible via une requête AJAX directe (?channel=staff).
$_chatChannels = [
    'general'  => ['label' => 'Général',    'icon' => 'fa-globe',               'color' => '#198754'],
    'students' => ['label' => 'Étudiants',  'icon' => 'fa-user-graduate',       'color' => '#0d6efd'],
    'staff'    => ['label' => 'Staff',       'icon' => 'fa-chalkboard-teacher',  'color' => '#dc3545'],
];
$_chatRoles = [
    'general'  => ['user', 'manager', 'admin'],
    'students' => ['user'],
    'staff'    => ['manager', 'admin'],
];
?>
<!-- ── Chat Widget ── -->
<style>
#chat-fab {
    position: fixed;
    bottom: 28px;
    right: 28px;
    z-index: 10000;
}
#chat-toggle-btn {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: #8b1a2e;
    border: none;
    color: #fff;
    font-size: 1.25rem;
    cursor: pointer;
    box-shadow: 0 4px 16px rgba(139,26,46,.45);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    transition: background .2s, transform .15s;
}
#chat-toggle-btn:hover { background: #6e1524; transform: scale(1.07); }
#chat-toggle-btn.is-open { background: #555; }
#chat-notif-badge {
    display: none;
    position: absolute;
    top: -4px;
    right: -4px;
    min-width: 20px;
    height: 20px;
    border-radius: 10px;
    background: #dc3545;
    color: #fff;
    font-size: .65rem;
    font-weight: 700;
    align-items: center;
    justify-content: center;
    padding: 0 5px;
    border: 2px solid #fff;
    pointer-events: none;
}

#chat-window {
    display: none;
    position: fixed;
    bottom: 96px;
    right: 28px;
    width: 360px;
    height: 500px;
    z-index: 9999;
    border-radius: 16px;
    box-shadow: 0 8px 40px rgba(0,0,0,.18);
    background: #fff;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid #e9ecef;
}
#chat-window.open { display: flex; }

/* Header */
#chat-header {
    background: linear-gradient(135deg, #8b1a2e 0%, #6e1524 100%);
    color: #fff;
    padding: .75rem 1rem .5rem;
    flex-shrink: 0;
}
#chat-header-title {
    font-weight: 700;
    font-size: .9rem;
    margin-bottom: .5rem;
    display: flex;
    align-items: center;
    gap: .5rem;
}
#chat-close-btn {
    margin-left: auto;
    background: none;
    border: none;
    color: rgba(255,255,255,.75);
    cursor: pointer;
    font-size: .9rem;
    padding: 0;
    line-height: 1;
}
#chat-close-btn:hover { color: #fff; }

/* Channel tabs */
#chat-channels {
    display: flex;
    gap: .35rem;
    flex-wrap: wrap;
}
.chat-ch-btn {
    padding: .2rem .65rem;
    border-radius: 20px;
    border: 1px solid rgba(255,255,255,.35);
    background: rgba(255,255,255,.1);
    color: rgba(255,255,255,.8);
    font-size: .75rem;
    cursor: pointer;
    transition: all .15s;
    display: flex;
    align-items: center;
    gap: .35rem;
}
.chat-ch-btn:hover { background: rgba(255,255,255,.2); color: #fff; }
.chat-ch-btn.active { background: #fff; color: #8b1a2e; font-weight: 600; border-color: #fff; }

/* Messages area */
#chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: .75rem 1rem;
    display: flex;
    flex-direction: column;
    gap: .6rem;
    background: #f8f9fa;
}
#chat-messages::-webkit-scrollbar { width: 4px; }
#chat-messages::-webkit-scrollbar-thumb { background: #dee2e6; border-radius: 4px; }

.chat-msg { font-size: .83rem; }
.chat-msg-meta {
    display: flex;
    align-items: baseline;
    gap: .4rem;
    margin-bottom: .18rem;
}
.chat-msg-name { font-weight: 600; color: #333; }
.chat-msg-role { font-size: .68rem; color: #999; }
.chat-msg-time { font-size: .68rem; color: #aaa; margin-left: auto; }
.chat-msg-body {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 0 10px 10px 10px;
    padding: .4rem .7rem;
    color: #333;
    line-height: 1.45;
    word-break: break-word;
}
.chat-empty {
    text-align: center;
    color: #aaa;
    font-size: .82rem;
    margin: auto;
    padding: 2rem 0;
}

/* Input form */
#chat-input-area {
    border-top: 1px solid #e9ecef;
    padding: .6rem .75rem;
    background: #fff;
    flex-shrink: 0;
}
#chat-form {
    display: flex;
    gap: .4rem;
}
#chat-input {
    flex: 1;
    border: 1px solid #dee2e6;
    border-radius: 20px;
    padding: .4rem .9rem;
    font-size: .83rem;
    outline: none;
    transition: border-color .15s;
}
#chat-input:focus { border-color: #8b1a2e; }
#chat-send-btn {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #8b1a2e;
    border: none;
    color: #fff;
    font-size: .8rem;
    cursor: pointer;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background .15s;
}
#chat-send-btn:hover { background: #6e1524; }

/* Loading indicator */
#chat-loading {
    text-align: center;
    padding: .5rem;
    font-size: .75rem;
    color: #aaa;
    display: none;
}
</style>

<div id="chat-fab">
    <!-- Bouton flottant -->
    <button id="chat-toggle-btn" title="Chat interne">
        <i class="fas fa-comments"></i>
        <span id="chat-notif-badge">0</span>
    </button>

    <!-- Fenêtre de chat -->
    <div id="chat-window">

        <!-- En-tête -->
        <div id="chat-header">
            <div id="chat-header-title">
                <i class="fas fa-comments"></i>
                <span id="chat-channel-label">Général</span>
                <button id="chat-close-btn" title="Fermer">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="chat-channels">
                <?php foreach ($_chatChannels as $key => $ch): ?>
                <?php if (in_array($_chatUser['role'], $_chatRoles[$key])): ?>
                <button class="chat-ch-btn <?= $key === 'general' ? 'active' : '' ?>"
                        data-channel="<?= $key ?>"
                        data-label="<?= htmlspecialchars($ch['label']) ?>">
                    <i class="fas <?= $ch['icon'] ?>" style="font-size:.7rem"></i>
                    <?= htmlspecialchars($ch['label']) ?>
                </button>
                <?php endif; ?>
                <?php endforeach; ?>

                <?php if ($_chatUser['role'] === 'user'): ?>
                <!-- Note pédagogique : le canal 'staff' n'est pas affiché dans l'UI
                     mais reste accessible via ?channel=staff dans les requêtes AJAX -->
                <?php endif; ?>
            </div>
        </div>

        <!-- Zone messages -->
        <div id="chat-messages">
            <div class="chat-empty">
                <i class="fas fa-comments" style="font-size:2rem;opacity:.2;display:block;margin-bottom:.5rem"></i>
                Chargement…
            </div>
        </div>

        <div id="chat-loading">Chargement…</div>

        <!-- Formulaire -->
        <div id="chat-input-area">
            <form id="chat-form" autocomplete="off">
                <input id="chat-input" type="text" name="message"
                       placeholder="Message dans #Général…" maxlength="2000">
                <button id="chat-send-btn" type="submit">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        </div>

    </div><!-- /chat-window -->
</div><!-- /chat-fab -->

<script>
// Passe le rôle utilisateur au JS pour la gestion de l'UI
window.corpnetChatRole = <?= json_encode($_chatUser['role']) ?>;
</script>
<script src="/assets/js/chat.js"></script>
