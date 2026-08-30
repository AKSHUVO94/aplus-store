<?php
require_once dirname(dirname(__DIR__)) . '/app/bootstrap.php';
Auth::requireAdmin();
$pageTitle = 'Live Chat';

// Ensure tables exist
try {
    Database::query("CREATE TABLE IF NOT EXISTS chat_conversations (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        session_key VARCHAR(64) NOT NULL DEFAULT '',
        user_id INT UNSIGNED NULL DEFAULT NULL,
        guest_no INT UNSIGNED NULL DEFAULT NULL,
        visitor_name VARCHAR(120) DEFAULT '',
        visitor_email VARCHAR(180) DEFAULT '',
        status VARCHAR(20) DEFAULT 'open',
        last_message_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_session (session_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    Database::query("CREATE TABLE IF NOT EXISTS chat_messages (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT UNSIGNED NOT NULL,
        sender VARCHAR(20) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_conv (conversation_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    try { Database::query("ALTER TABLE chat_conversations MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'open'"); } catch (Exception $e) {}
    foreach (array('uq_session','session_key') as $idx) {
        try { Database::query("ALTER TABLE chat_conversations DROP INDEX `{$idx}`"); } catch (Exception $e) {}
    }
} catch (Exception $e) {}

$unread = 0;
try {
    $r = Database::fetch("SELECT COUNT(*) c FROM chat_messages WHERE sender='visitor' AND is_read=0");
    $unread = $r ? (int)$r['c'] : 0;
} catch (Exception $e) {}

ob_start();
?>
<div class="panel">
  <div class="panel-header">
    <h3><i class="fas fa-comments"></i> Live Chat <?php if ($unread): ?><span class="badge badge-danger"><?= $unread ?> new</span><?php endif; ?></h3>
    <button type="button" class="btn btn-outline btn-sm" id="lc-refresh"><i class="fas fa-sync"></i> Refresh</button>
  </div>
  <div class="panel-body" style="padding:0">
    <div class="lc-layout">
      <aside class="lc-list" id="lc-list">
        <div class="lc-empty">Loading…</div>
      </aside>
      <section class="lc-thread">
        <div class="lc-thread-head" id="lc-head-wrap" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
          <div id="lc-head">Select a conversation</div>
          <div id="lc-actions" style="display:none;gap:8px;flex-wrap:wrap">
            <button type="button" class="btn btn-primary btn-sm" id="lc-approve">Approve chat</button>
            <button type="button" class="btn btn-outline btn-sm" id="lc-block" style="color:#e11d48;border-color:#e11d48">Block</button>
            <button type="button" class="btn btn-outline btn-sm" id="lc-unblock" style="display:none">Unblock</button>
            <button type="button" class="btn btn-outline btn-sm" id="lc-delete" style="color:#b91c1c;border-color:#b91c1c">Delete chat</button>
          </div>
        </div>
        <div class="lc-msgs" id="lc-msgs"></div>
        <form class="lc-compose" id="lc-form" style="display:none">
          <input type="hidden" id="lc-cid" value="">
          <textarea id="lc-input" class="form-control" rows="2" placeholder="Type reply or paste screenshot (Ctrl+V)…" required></textarea>
          <button type="submit" class="btn btn-primary">Send Reply</button>
        </form>
      </section>
    </div>
  </div>
</div>
<style>
.lc-layout{display:grid;grid-template-columns:280px 1fr;min-height:520px}
.lc-list{border-right:1px solid #e5e5e5;overflow-y:auto;max-height:70vh}
.lc-item{padding:12px 14px;border-bottom:1px solid #f0f0f0;cursor:pointer}
.lc-item:hover,.lc-item.active{background:#f7f7f7}
.lc-item .lc-name{font-weight:600;font-size:.9rem}
.lc-item .lc-preview{font-size:.78rem;color:#888;margin-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.lc-item .lc-meta{font-size:.7rem;color:#aaa;margin-top:4px;display:flex;justify-content:space-between}
.lc-badge{background:#e11d48;color:#fff;border-radius:999px;padding:1px 7px;font-size:.65rem;font-weight:700}
.lc-st{font-size:.65rem;font-weight:700;padding:2px 6px;border-radius:6px;margin-left:6px}
.lc-st.pending{background:#fef3c7;color:#b45309}
.lc-st.blocked{background:#fee2e2;color:#b91c1c}
.lc-thread{display:flex;flex-direction:column;min-height:520px}
.lc-thread-head{padding:14px 16px;border-bottom:1px solid #eee;font-weight:600}
.lc-msgs{flex:1;overflow-y:auto;padding:16px;background:#fafafa;max-height:55vh}
.lc-bubble{max-width:75%;padding:10px 14px;border-radius:14px;margin-bottom:10px;font-size:.9rem;line-height:1.45;white-space:pre-wrap}
.lc-bubble.visitor{background:#fff;border:1px solid #e8e8e8;margin-right:auto}
.lc-bubble.admin{background:#111;color:#fff;margin-left:auto}
.lc-bubble .t{display:block;font-size:.68rem;opacity:.6;margin-top:4px}
.lc-img{display:block;max-width:220px;max-height:180px;border-radius:8px;margin-top:4px}
.lc-compose .hint{font-size:.72rem;color:#888;margin-top:4px}
.lc-compose{display:flex;gap:10px;padding:12px;border-top:1px solid #eee;align-items:flex-end}
.lc-compose textarea{flex:1;min-height:44px}
.lc-empty{padding:24px;color:#999;text-align:center}
@media(max-width:800px){.lc-layout{grid-template-columns:1fr}}
</style>
<script>
(function(){
  var listEl = document.getElementById('lc-list');
  var msgsEl = document.getElementById('lc-msgs');
  var headEl = document.getElementById('lc-head');
  var form = document.getElementById('lc-form');
  var cidInput = document.getElementById('lc-cid');
  var input = document.getElementById('lc-input');
  var activeId = 0;
  var lastUnreadTotal = 0;
  var knownMsgCount = 0;
  var titleBase = document.title;

  function renderMsg(text){
    var m = String(text||'').match(/^\{\{img:(.+?)\}\}$/);
    if (m) {
      var src = m[1];
      return '<a href="'+esc(src)+'" target="_blank" rel="noopener"><img class="lc-img" src="'+esc(src)+'" alt="Screenshot"></a>';
    }
    return esc(text||'');
  }
  function esc(s){
    var d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
  }
  function playBeep(){
    try {
      var Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return;
      var ctx = new Ctx();
      var o = ctx.createOscillator();
      var g = ctx.createGain();
      o.type = 'sine';
      o.frequency.value = 660;
      o.connect(g); g.connect(ctx.destination);
      g.gain.value = 0.06;
      o.start();
      setTimeout(function(){ o.stop(); ctx.close(); }, 180);
    } catch(e) {}
  }

  function loadList(){
    fetch('/api-chat.php?action=admin_list&_ts=' + Date.now(), {credentials:'same-origin', cache:'no-store'})
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (!data.ok) { listEl.innerHTML = '<div class="lc-empty">Error loading chats</div>'; return; }
        var items = data.items || [];
        if (!items.length) { listEl.innerHTML = '<div class="lc-empty">No chats yet</div>'; return; }
        var totalUnread = 0;
        listEl.innerHTML = items.map(function(c){
          var name = c.display_name || c.visitor_name || 'Guest';
          var st = c.status || 'open';
          var prev = c.last_msg || '';
          var unread = parseInt(c.unread,10) || 0;
          totalUnread += unread;
          return '<div class="lc-item'+(activeId===parseInt(c.id,10)?' active':'')+'" data-id="'+c.id+'">'+
            '<div class="lc-name">'+esc(name)+(st==='pending'?' <span class="lc-st pending">Pending</span>':(st==='blocked'?' <span class="lc-st blocked">Blocked</span>':''))+'</div>'+
            '<div class="lc-preview">'+esc(prev)+'</div>'+
            '<div class="lc-meta"><span>'+esc(c.last_message_at||c.created_at||'')+'</span>'+
            (unread?'<span class="lc-badge">'+unread+'</span>':'')+'</div></div>';
        }).join('');
        if (totalUnread > lastUnreadTotal) {
          playBeep();
          document.title = '(' + totalUnread + ') New chat — ' + titleBase;
          try {
            if (window.Notification && Notification.permission === 'granted') {
              new Notification('New live chat message', { body: 'You have ' + totalUnread + ' unread message(s)' });
            }
          } catch(e) {}
        }
        if (totalUnread === 0) document.title = titleBase;
        lastUnreadTotal = totalUnread;
      })
      .catch(function(){});
  }

  function loadThread(id, quiet){
    activeId = id;
    cidInput.value = id;
    form.style.display = 'flex';
    fetch('/api-chat.php?action=admin_thread&id='+id+'&_ts='+Date.now(), {credentials:'same-origin', cache:'no-store'})
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (!data.ok) return;
        var c = data.conversation;
        headEl.textContent = (c.display_name || c.visitor_name || 'Guest') + (c.visitor_email ? ' · '+c.visitor_email : '') + (c.user_id ? ' · Customer' : '') + (c.status ? ' · ' + c.status : '');
        var acts = document.getElementById('lc-actions');
        var btnA = document.getElementById('lc-approve');
        var btnB = document.getElementById('lc-block');
        var btnU = document.getElementById('lc-unblock');
        if (acts) {
          acts.style.display = 'flex';
          if (btnA) btnA.style.display = (c.status === 'pending' || c.status === 'blocked') ? '' : (c.status === 'open' ? 'none' : '');
          if (c.status === 'pending') { if (btnA) btnA.style.display = ''; if (btnB) btnB.style.display = ''; if (btnU) btnU.style.display = 'none'; }
          else if (c.status === 'blocked') { if (btnA) btnA.style.display = 'none'; if (btnB) btnB.style.display = 'none'; if (btnU) btnU.style.display = ''; }
          else { if (btnA) btnA.style.display = 'none'; if (btnB) btnB.style.display = ''; if (btnU) btnU.style.display = 'none'; }
        }
        var list = data.messages || [];
        if (!quiet && list.length > knownMsgCount && knownMsgCount > 0) {
          playBeep();
        }
        knownMsgCount = list.length;
        var atBottom = msgsEl.scrollHeight - msgsEl.scrollTop - msgsEl.clientHeight < 80;
        msgsEl.innerHTML = list.map(function(m){
          return '<div class="lc-bubble '+m.sender+'">'+renderMsg(m.message)+'<span class="t">'+esc(m.created_at)+'</span></div>';
        }).join('');
        if (atBottom || !quiet) msgsEl.scrollTop = msgsEl.scrollHeight;
        loadList();
      })
      .catch(function(){});
  }

  listEl.addEventListener('click', function(e){
    var item = e.target.closest('.lc-item');
    if (!item) return;
    knownMsgCount = 0;
    loadThread(parseInt(item.getAttribute('data-id'),10), false);
  });

  form.addEventListener('submit', function(e){
    e.preventDefault();
    var msg = input.value.trim();
    if (!msg || !cidInput.value) return;
    var fd = new FormData();
    fd.append('conversation_id', cidInput.value);
    fd.append('message', msg);
    fetch('/api-chat.php?action=admin_reply', {method:'POST', body:fd, credentials:'same-origin', cache:'no-store'})
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.ok) {
          input.value = '';
          loadThread(parseInt(cidInput.value,10), true);
        } else {
          alert(data.error || 'Send failed');
        }
      });
  });

  document.getElementById('lc-refresh').addEventListener('click', function(){
    loadList();
    if (activeId) loadThread(activeId, true);
  });

  try {
    if (window.Notification && Notification.permission === 'default') {
      Notification.requestPermission();
    }
  } catch(e) {}


  function postAction(action){
    if (!cidInput.value) return;
    var fd = new FormData();
    fd.append('conversation_id', cidInput.value);
    fetch('/api-chat.php?action=' + action, {method:'POST', body:fd, credentials:'same-origin', cache:'no-store'})
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.ok) loadThread(parseInt(cidInput.value,10), true);
        else alert(data.error || 'Failed');
      });
  }
  var btnApprove = document.getElementById('lc-approve');
  var btnBlock = document.getElementById('lc-block');
  var btnUnblock = document.getElementById('lc-unblock');
  if (btnApprove) btnApprove.addEventListener('click', function(){ postAction('admin_approve'); });
  if (btnBlock) btnBlock.addEventListener('click', function(){
    if (confirm('Block this customer from chatting?')) postAction('admin_block');
  });
  if (btnUnblock) btnUnblock.addEventListener('click', function(){ postAction('admin_unblock'); });
  var btnDelete = document.getElementById('lc-delete');
  if (btnDelete) btnDelete.addEventListener('click', function(){
    if (!cidInput.value) return;
    if (!confirm('Permanently delete this entire conversation?')) return;
    var fd = new FormData();
    fd.append('conversation_id', cidInput.value);
    fetch('/api-chat.php?action=admin_delete', {method:'POST', body:fd, credentials:'same-origin', cache:'no-store'})
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (!data.ok) { alert(data.error || 'Delete failed'); return; }
        activeId = 0;
        cidInput.value = '';
        knownMsgCount = 0;
        msgsEl.innerHTML = '';
        headEl.textContent = 'Select a conversation';
        form.style.display = 'none';
        var acts = document.getElementById('lc-actions');
        if (acts) acts.style.display = 'none';
        loadList();
      });
  });


  var lcInput = document.getElementById('lc-input');
  if (lcInput) {
    lcInput.addEventListener('paste', function(e){
      var items = e.clipboardData && e.clipboardData.items;
      if (!items || !cidInput.value) return;
      var file = null;
      for (var i=0;i<items.length;i++){
        if (items[i].type && items[i].type.indexOf('image/')===0){
          file = items[i].getAsFile(); break;
        }
      }
      if (!file) return;
      e.preventDefault();
      var fd = new FormData();
      fd.append('image', file, 'screenshot.png');
      fd.append('conversation_id', cidInput.value);
      fetch('/api-chat.php?action=send_image', {method:'POST', body:fd, credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(data){
          if (data.ok) loadThread(parseInt(cidInput.value,10), true);
          else alert(data.error || 'Upload failed');
        });
    });
  }
  loadList();
  setInterval(function(){
    loadList();
    if (activeId) loadThread(activeId, true);
  }, 1000);
})();
</script></script>
<?php
$content = ob_get_clean();
require dirname(dirname(__DIR__)) . '/app/views/layouts/admin.php';