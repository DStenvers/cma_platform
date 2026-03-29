<?php
/**
 * Password Change Page
 * Standalone page for changing password, used by classic tab menu
 */
use App\Library\Application;
use App\Library\Cookie;
use App\Library\Server;
use Cma\SecurityHelper;

require_once __DIR__ . '/bootstrap.inc';

// Check user is logged in
if (!SecurityHelper::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$language = Application::get('cma_language', 'NL');
$pageTitle = $language === 'UK' ? 'Change Password' : 'Wachtwoord wijzigen';
$userName = SecurityHelper::getCurrentUserName();

cma_html_header($pageTitle);
?>
<body class="contentbody">
<style>
.pwd-container {
    max-width: 400px;
    margin: 40px auto;
    padding: 20px;
}
.pwd-container h2 {
    margin-bottom: 20px;
    color: var(--text-primary, #333);
}
.pwd-form input[type="password"] {
    width: 100%;
    padding: 10px;
    margin-bottom: 15px;
    border: 1px solid var(--border-color, #ccc);
    border-radius: 4px;
    font-size: var(--font-size-md);
    box-sizing: border-box;
}
.pwd-form label {
    display: block;
    margin-bottom: 5px;
    color: var(--text-secondary, #666);
    font-size: var(--font-size);
}
.pwd-buttons {
    margin-top: 20px;
}
.pwd-buttons .button {
    padding: 10px 20px;
    margin-right: 10px;
}
.pwd-message {
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 15px;
    display: none;
}
.pwd-message.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.pwd-message.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
</style>

<div class="pwd-container">
    <h2><?= Server::htmlEncode($pageTitle) ?></h2>

    <div id="pwdMessage" class="pwd-message"></div>

    <form id="pwdForm" class="pwd-form" onsubmit="return submitPassword(event)">
        <label for="oldPwd"><?= $language === 'UK' ? 'Current password' : 'Huidig wachtwoord' ?></label>
        <input type="password" id="oldPwd" name="old_password" autocomplete="current-password" required>

        <label for="newPwd"><?= $language === 'UK' ? 'New password' : 'Nieuw wachtwoord' ?></label>
        <input type="password" id="newPwd" name="new_password" autocomplete="new-password" required>

        <div class="pwd-buttons">
            <button type="submit" class="btn btn-primary"><?= $language === 'UK' ? 'Change' : 'Wijzigen' ?></button>
        </div>
    </form>
</div>

<script>
function submitPassword(e) {
    e.preventDefault();

    var oldPwd = document.getElementById('oldPwd').value;
    var newPwd = document.getElementById('newPwd').value;
    var msgEl = document.getElementById('pwdMessage');

    if (!oldPwd || !newPwd) {
        msgEl.className = 'pwd-message error';
        msgEl.textContent = '<?= $language === 'UK' ? 'Please fill in all fields' : 'Vul alle velden in' ?>';
        msgEl.style.display = 'block';
        return false;
    }

    fetch('api/change-password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'old_password=' + encodeURIComponent(oldPwd) + '&new_password=' + encodeURIComponent(newPwd)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            msgEl.className = 'pwd-message success';
            msgEl.textContent = '<?= $language === 'UK' ? 'Password changed successfully' : 'Wachtwoord is gewijzigd' ?>';
            msgEl.style.display = 'block';
            document.getElementById('oldPwd').value = '';
            document.getElementById('newPwd').value = '';
        } else {
            msgEl.className = 'pwd-message error';
            msgEl.textContent = data.message || '<?= $language === 'UK' ? 'Could not change password' : 'Wachtwoord kon niet worden gewijzigd' ?>';
            msgEl.style.display = 'block';
        }
    })
    .catch(function() {
        msgEl.className = 'pwd-message error';
        msgEl.textContent = '<?= $language === 'UK' ? 'Error changing password' : 'Fout bij wijzigen wachtwoord' ?>';
        msgEl.style.display = 'block';
    });

    return false;
}
</script>
<?php
cma_body_end();
?>
