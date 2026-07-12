<?php
require_once 'config.php';
requireLogin();

$user_id = $_SESSION['user_logged']['user_id'];
$notifications = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$notifications->execute([$user_id]);
$notifications = $notifications->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Notifications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { background: #f0f4f8; }
        .card { border-radius: 1.25rem; border: none; box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
        .card-header { background: transparent; border-bottom: 1px solid rgba(0,0,0,0.03); padding: 1.25rem 1.5rem; }
        .notif-item {
            padding: 0.8rem 1rem;
            border-bottom: 1px solid #F3F4F6;
            transition: 0.2s;
            text-decoration: none;
            color: #1F2937;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .notif-item:hover { background: #F9FAFB; }
        .notif-item:last-child { border-bottom: none; }
        .notif-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        .notif-icon.payment { background: #D1FAE5; color: #065F46; }
        .notif-icon.appointment { background: #DBEAFE; color: #1E40AF; }
        .notif-icon.reminder { background: #FEF3C7; color: #92400E; }
        .notif-icon.alert { background: #FEE2E2; color: #991B1B; }
        .btn-outline-primary {
            border: 1px solid #2563EB;
            border-radius: 2rem;
            padding: 0.4rem 1.2rem;
            transition: 0.2s;
            color: #2563EB;
        }
        .btn-outline-primary:hover {
            background: #2563EB;
            color: white;
        }
        .badge-read {
            background: #E5E7EB;
            color: #6B7280;
            padding: 0.2rem 0.6rem;
            border-radius: 2rem;
            font-size: 0.7rem;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0"><i class="fas fa-bell me-2 text-primary"></i>All Notifications</h4>
            <a href="dashboard.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-list me-2"></i>Notifications</span>
                <span class="badge bg-primary rounded-pill"><?= count($notifications) ?> total</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($notifications)): ?>
                    <div class="text-center text-muted py-4">No notifications found.</div>
                <?php else: ?>
                    <?php foreach ($notifications as $n): ?>
                        <a href="<?= htmlspecialchars($n['url']) ?>" class="notif-item" data-id="<?= $n['id'] ?>" data-unread="<?= $n['is_read'] ? 0 : 1 ?>">
                            <span class="notif-icon <?= $n['type'] ?>">
                                <i class="fas <?= $n['type'] == 'payment' ? 'fa-credit-card' : ($n['type'] == 'appointment' ? 'fa-calendar-check' : 'fa-bell') ?>"></i>
                            </span>
                            <div class="flex-grow-1">
                                <div class="fw-medium"><?= htmlspecialchars($n['message']) ?></div>
                                <div class="small text-muted"><?= date('M j, Y g:i A', strtotime($n['created_at'])) ?></div>
                            </div>
                            <?php if ($n['is_read']): ?>
                                <span class="badge-read">Read</span>
                            <?php else: ?>
                                <span class="badge bg-primary">New</span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mark as read when clicked on this page too
        document.querySelectorAll('.notif-item').forEach(item => {
            item.addEventListener('click', function(e) {
                const id = this.dataset.id;
                const isUnread = this.dataset.unread == '1';
                if (isUnread) {
                    fetch(`api.php?action=mark_read&id=${id}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                // Update badge and reload count
                                const badge = this.querySelector('.badge');
                                if (badge) {
                                    badge.className = 'badge-read';
                                    badge.textContent = 'Read';
                                }
                                this.dataset.unread = '0';
                                // Optionally update the count in the header via an AJAX call (not required here)
                            }
                        })
                        .catch(() => {});
                }
            });
        });
    </script>
</body>
</html>