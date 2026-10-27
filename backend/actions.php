<?php
/** All POST handlers (included from index.php). Every action checks CSRF + role, then redirects. */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
if (!csrf_ok()) { http_response_code(419); exit('Session expired. Please go back and reload the page.'); }

$act  = $_POST['action'] ?? '';
$rid  = trim((string)($_POST['id'] ?? '')) ?: null;                 // asset / ticket ids are UUID strings
$nid  = ($rid !== null && ctype_digit($rid)) ? (int)$rid : null;    // user ids are integers
$back = 'index.php' . (preg_match('/^[a-z]+$/', $_POST['page'] ?? '') ? '?page=' . $_POST['page'] : '');

try {
    switch ($act) {
        /* ---------- public ---------- */
        case 'login':
            if (Auth::attempt($_POST['email'] ?? '', $_POST['password'] ?? '')) flash('success', 'Welcome back, ' . Auth::user()['name'] . '!');
            else $_SESSION['open_modal'] = 'loginModal';
            $back = 'index.php';
            break;

        case 'register':
            $err = Auth::register($_POST);
            if ($err) { flash('error', $err); $_SESSION['open_modal'] = 'registerModal'; }
            else { flash('success', 'Account created. You can log in now.'); $_SESSION['open_modal'] = 'loginModal'; }
            $back = 'index.php';
            break;

        case 'logout':
            Auth::logout();
            flash('success', 'You have been logged out.');
            $back = 'index.php';
            break;

        /* ---------- assets (Admin) ---------- */
        case 'asset_save':
            Auth::need('Admin');
            $aid = Asset::save($_POST, $rid);
            Log::add($rid ? 'update' : 'create', 'asset', $aid, trim($_POST['asset_tag'] ?? ''));
            if (($_POST['assigned_user_id'] ?? '') !== '')
                Notification::push((int)$_POST['assigned_user_id'], 'Asset ' . trim($_POST['asset_tag']) . ' (' . trim($_POST['name']) . ') was assigned to you.', $aid);
            flash('success', 'Asset saved.');
            break;

        case 'asset_delete':
            Auth::need('Admin');
            $a = $rid ? Asset::find($rid) : null;
            if (!$a) throw new InvalidArgumentException('Asset not found.');
            Asset::delete($rid);
            Log::add('delete', 'asset', $rid, $a['asset_tag']);
            flash('success', 'Asset deleted.');
            break;

        /* ---------- maintenance tickets ---------- */
        case 'ticket_save':
            Auth::need('Admin', 'Technician', 'Employee');
            $d = $_POST;
            if (Auth::is('Employee')) {                       // employees may only report issues on their own assets
                if ($rid) throw new InvalidArgumentException('Employees cannot edit tickets.');
                if (!Database::val('SELECT 1 FROM asset WHERE asset_id = ? AND assigned_user_id = ?', [trim($d['asset_id'] ?? ''), Auth::id()]))
                    throw new InvalidArgumentException('You can only report issues on assets assigned to you.');
                $d = ['asset_id' => $d['asset_id'], 'title' => $d['title'] ?? '', 'issue_description' => $d['issue_description'] ?? '',
                      'priority' => $d['priority'] ?? 'Medium', 'type' => 'Repair', 'status' => 'Open'];
            }
            $old = $rid ? Ticket::find($rid) : null;
            $tid = Ticket::save($d, $rid, Auth::id());
            Log::add($rid ? 'update' : 'create', 'ticket', $tid, trim($d['title'] ?? ''));
            $tech = (int)($d['assigned_technician_id'] ?? 0);
            if ($tech && $tech !== (int)($old['assigned_technician_id'] ?? 0))
                Notification::push($tech, 'You were assigned a ticket: ' . trim($d['title']), $d['asset_id'], $tid);
            flash('success', 'Ticket saved.');
            break;

        case 'ticket_delete':
            Auth::need('Admin');
            Ticket::delete((string)$rid);
            Log::add('delete', 'ticket', $rid);
            flash('success', 'Ticket deleted.');
            break;

        /* ---------- transaction demo (TCL: BEGIN / COMMIT / ROLLBACK) ---------- */
        case 'tx_assign':
            Auth::need('Admin');
            $_SESSION['tx_result'] = Transaction::assignAsset(trim((string)($_POST['asset_id'] ?? '')), (int)($_POST['user_id'] ?? 0), !empty($_POST['force_fail']));
            break;

        case 'tx_transfer':
            Auth::need('Admin');
            $_SESSION['tx_result'] = Transaction::transferAsset(trim((string)($_POST['asset_id'] ?? '')), (int)($_POST['from_id'] ?? 0), (int)($_POST['to_id'] ?? 0), !empty($_POST['force_fail']));
            break;

        case 'tx_ticket':
            Auth::need('Admin', 'Technician');
            $_SESSION['tx_result'] = Transaction::completeTicket(trim((string)($_POST['ticket_id'] ?? '')), (float)($_POST['cost'] ?? 0), !empty($_POST['force_fail']));
            break;

        /* ---------- users (Admin) ---------- */
        case 'user_save':
            Auth::need('Admin');
            if ($nid === Auth::id() && ($_POST['role'] ?? '') !== 'Admin') throw new InvalidArgumentException('You cannot remove your own Admin role.');
            $uid = User::save($_POST, $nid);
            Log::add($nid ? 'update' : 'create', 'user', (string)$uid, trim($_POST['email'] ?? '') . ' / ' . ($_POST['role'] ?? ''));
            flash('success', 'User saved.');
            break;

        case 'user_delete':
            Auth::need('Admin');
            if (!$nid) throw new InvalidArgumentException('User not found.');
            if ($nid === Auth::id()) throw new InvalidArgumentException('You cannot delete your own account.');
            User::delete($nid);
            Log::add('delete', 'user', (string)$nid);
            flash('success', 'User deleted.');
            break;

        /* ---------- any logged-in user ---------- */
        case 'profile_save':
            Auth::need(...ROLES);
            User::updateProfile(Auth::id(), $_POST);
            Log::add('update', 'profile', (string)Auth::id());
            flash('success', 'Profile updated.');
            break;

        case 'notif_read':
            Auth::need(...ROLES);
            Notification::markAllRead(Auth::id());
            break;

        default:
            flash('error', 'Unknown action.');
    }
} catch (InvalidArgumentException $ex) {
    flash('error', $ex->getMessage());
} catch (PDOException $ex) {
    $m = $ex->getMessage();
    flash('error', str_contains($m, 'Duplicate') ? 'That asset tag, serial number or email already exists.'
        : (APP_DEBUG ? 'Database error: ' . $m : 'Database error. Please try again.'));
}
redirect($back);
