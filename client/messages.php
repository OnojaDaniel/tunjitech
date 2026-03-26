<?php
// Start session if not already started
//if (session_status() == PHP_SESSION_NONE) {
//    session_start();
//}

// Define root path and include config
define('ROOT_PATH', dirname(dirname(__FILE__)));
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

// Check if user is logged in and is client
if (!isLoggedIn() || (!isClient() && !isClientUser())) {
    header("Location: ../login.php");
    exit();
}

// Check if user is approved
if (!isUserApproved($_SESSION['user_id'])) {
    session_destroy();
    header("Location: ../login.php?error=not_approved");
    exit();
}

$client_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    switch ($_GET['ajax']) {
        case 'get_messages':
            $partner_id = isset($_GET['partner_id']) ? intval($_GET['partner_id']) : 0;
            if ($partner_id) {
                $conversation = getConversation($client_id, $partner_id);
                echo json_encode(['success' => true, 'messages' => $conversation]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid partner ID']);
            }
            exit();

        case 'send_message':
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $message = sanitizeInput($_POST['message']);
                $receiver_id = intval($_POST['receiver_id']);

                if (empty($message)) {
                    echo json_encode(['success' => false, 'error' => 'Message cannot be empty.']);
                } elseif (empty($receiver_id)) {
                    echo json_encode(['success' => false, 'error' => 'Invalid receiver.']);
                } else {
                    if (sendMessage($client_id, $receiver_id, $message)) {
                        echo json_encode(['success' => true, 'message' => 'Message sent successfully!']);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Failed to send message.']);
                    }
                }
            }
            exit();

        case 'get_unread_counts':
            $unread_counts = getUnreadCounts($client_id);
            echo json_encode(['success' => true, 'unread_counts' => $unread_counts]);
            exit();

        case 'search_admins':
            $search_term = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
            $admins = searchAdmins($search_term);
            echo json_encode(['success' => true, 'admins' => $admins]);
            exit();
    }
}

// Get all admins for chat list
$admins = getAdmins();

// Get or set current chat partner (default to first admin)
$current_partner_id = isset($_GET['partner_id']) ? intval($_GET['partner_id']) : null;
if (!$current_partner_id && !empty($admins)) {
    $current_partner_id = $admins[0]['id'];
}

// Handle sending new message (non-AJAX fallback)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message']) && !isset($_GET['ajax'])) {
    $message = sanitizeInput($_POST['message']);
    $receiver_id = intval($_POST['receiver_id']);

    if (empty($message)) {
        $error = 'Message cannot be empty.';
    } elseif (empty($receiver_id)) {
        $error = 'Invalid receiver.';
    } else {
        if (sendMessage($client_id, $receiver_id, $message)) {
            $success = 'Message sent successfully!';
            $_POST['message'] = '';

            // Redirect to prevent form resubmission
            header("Location: messages.php?partner_id=" . $receiver_id);
            exit();
        } else {
            $error = 'Failed to send message. Please try again.';
        }
    }
}

// Mark messages as read when viewing a conversation
if ($current_partner_id) {
    markMessagesAsRead($current_partner_id, $client_id);
}

// Get conversation with current partner
$conversation = [];
if ($current_partner_id) {
    $conversation = getConversation($client_id, $current_partner_id);
}

// Get unread counts for each admin
$unread_counts = getUnreadCounts($client_id);

/**
 * Get all admin users
 */
function getAdmins() {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.user_type,
               (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count
        FROM users u 
        WHERE u.user_type = 'admin' AND u.status = 'approved'
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Search admins by name or email
 */
function searchAdmins($search_term) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.user_type
        FROM users u 
        WHERE u.user_type = 'admin' 
        AND u.status = 'approved'
        AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)
        ORDER BY u.first_name, u.last_name
    ");
    $search_pattern = '%' . $search_term . '%';
    $stmt->execute([$search_pattern, $search_pattern, $search_pattern, $search_pattern]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Send a message
 */
function sendMessage($sender_id, $receiver_id, $message) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    return $stmt->execute([$sender_id, $receiver_id, $message]);
}

/**
 * Get conversation between two users
 */
function getConversation($user1_id, $user2_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT m.*, 
               u_sender.username as sender_username,
               u_sender.first_name as sender_first_name,
               u_sender.last_name as sender_last_name,
               u_receiver.username as receiver_username
        FROM messages m
        LEFT JOIN users u_sender ON m.sender_id = u_sender.id
        LEFT JOIN users u_receiver ON m.receiver_id = u_receiver.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?) 
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$user1_id, $user2_id, $user2_id, $user1_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Mark messages as read
 */
function markMessagesAsRead($sender_id, $receiver_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
    $stmt->execute([$sender_id, $receiver_id]);
}

/**
 * Get unread message counts for each conversation
 */
function getUnreadCounts($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT sender_id, COUNT(*) as unread_count 
        FROM messages 
        WHERE receiver_id = ? AND is_read = 0 
        GROUP BY sender_id
    ");
    $stmt->execute([$user_id]);

    $counts = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $counts[$row['sender_id']] = $row['unread_count'];
    }
    return $counts;
}
?>

<?php include 'include/header.php'; ?>

    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
            <h6 class="fw-semibold mb-0">Chat with Support</h6>
            <ul class="d-flex align-items-center gap-2">
                <li class="fw-medium">
                    <a href="dashboard.php" class="d-flex align-items-center gap-1 hover-text-primary">
                        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                        Dashboard
                    </a>
                </li>
                <li>-</li>
                <li class="fw-medium">Messages</li>
            </ul>
        </div>

        <?php if ($error && !isset($_GET['ajax'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($success && !isset($_GET['ajax'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="chat-wrapper">
            <div class="chat-sidebar card">
                <!-- Current User Profile -->
                <div class="chat-sidebar-single active top-profile">
                    <div class="img">
                        <?php
                        $user_initials = strtoupper(substr($_SESSION['first_name'] ?? '', 0, 1) . substr($_SESSION['last_name'] ?? '', 0, 1));
                        ?>
                        <div class="avatar-placeholder bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <?php echo $user_initials ?: 'U'; ?>
                        </div>
                    </div>
                    <div class="info">
                        <h6 class="text-md mb-0"><?php echo htmlspecialchars($_SESSION['username']); ?></h6>
                        <p class="mb-0"><span class="badge bg-success" id="connectionStatus">Online</span></p>
                    </div>
                    <div class="action">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshMessages()" title="Refresh Messages">
                            <iconify-icon icon="mdi:refresh"></iconify-icon>
                        </button>
                    </div>
                </div>

                <!-- Search -->
                <div class="chat-search">
                    <span class="icon">
                        <iconify-icon icon="iconoir:search"></iconify-icon>
                    </span>
                    <input type="text" name="search" autocomplete="off" placeholder="Search support staff..." id="adminSearch">
                </div>

                <!-- Support Staff List -->
                <div class="chat-all-list" id="adminsList">
                    <?php if (empty($admins)): ?>
                        <div class="text-center p-3 text-muted">
                            <p>No support staff available at the moment.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($admins as $admin): ?>
                            <?php
                            $unread_count = $unread_counts[$admin['id']] ?? 0;
                            $is_active = $current_partner_id == $admin['id'];
                            $admin_name = htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']);
                            $admin_initials = strtoupper(substr($admin['first_name'] ?? '', 0, 1) . substr($admin['last_name'] ?? '', 0, 1));
                            ?>
                            <a href="messages.php?partner_id=<?php echo $admin['id']; ?>" class="chat-sidebar-single <?php echo $is_active ? 'active' : ''; ?>" style="text-decoration: none; color: inherit;" data-admin-id="<?php echo $admin['id']; ?>">
                                <div class="img">
                                    <div class="avatar-placeholder bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <?php echo $admin_initials ?: 'A'; ?>
                                    </div>
                                </div>
                                <div class="info">
                                    <h6 class="text-sm mb-1"><?php echo $admin_name; ?></h6>
                                    <p class="mb-0 text-xs">Support Administrator</p>
                                </div>
                                <div class="action text-end">
                                    <p class="mb-0 text-neutral-400 text-xs lh-1">
                                        <?php echo $admin['unread_count'] > 0 ? 'Unread' : 'Read'; ?>
                                    </p>
                                    <?php if ($unread_count > 0): ?>
                                        <span class="w-16-px h-16-px text-xs rounded-circle bg-warning-main text-white d-inline-flex align-items-center justify-content-center unread-badge" data-admin-id="<?php echo $admin['id']; ?>">
                                            <?php echo $unread_count > 9 ? '9+' : $unread_count; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Main Chat Area -->
            <div class="chat-main card">
                <?php if ($current_partner_id && !empty($admins)): ?>
                    <?php
                    $current_admin = null;
                    foreach ($admins as $admin) {
                        if ($admin['id'] == $current_partner_id) {
                            $current_admin = $admin;
                            break;
                        }
                    }
                    ?>

                    <?php if ($current_admin): ?>
                        <div class="chat-sidebar-single active">
                            <div class="img">
                                <div class="avatar-placeholder bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <?php echo strtoupper(substr($current_admin['first_name'] ?? '', 0, 1) . substr($current_admin['last_name'] ?? '', 0, 1)); ?>
                                </div>
                            </div>
                            <div class="info">
                                <h6 class="text-md mb-0"><?php echo htmlspecialchars($current_admin['first_name'] . ' ' . $current_admin['last_name']); ?></h6>
                                <p class="mb-0">Support Team</p>
                            </div>
                            <div class="action d-inline-flex align-items-center gap-3">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshMessages()" title="Refresh Messages">
                                    <iconify-icon icon="mdi:refresh"></iconify-icon>
                                </button>
                                <div class="btn-group">
                                    <button type="button" class="text-primary-light text-xl" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                                        <iconify-icon icon="tabler:dots-vertical"></iconify-icon>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-lg-end border">
                                        <li>
                                            <a href="mailto:<?php echo htmlspecialchars($current_admin['email']); ?>" class="dropdown-item rounded text-secondary-light bg-hover-neutral-200 text-hover-neutral-900 d-flex align-items-center gap-2">
                                                <iconify-icon icon="ic:outline-email"></iconify-icon>
                                                Send Email
                                            </a>
                                        </li>
                                        <li>
                                            <button class="dropdown-item rounded text-secondary-light bg-hover-neutral-200 text-hover-neutral-900 d-flex align-items-center gap-2" type="button" onclick="exportConversation()">
                                                <iconify-icon icon="mdi:download-outline"></iconify-icon>
                                                Export Chat
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Messages List -->
                        <div class="chat-message-list" id="messageList">
                            <div id="messagesContainer">
                                <?php if (empty($conversation)): ?>
                                    <div class="text-center p-4 text-muted">
                                        <p>No messages yet. Start a conversation with the support team!</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($conversation as $message): ?>
                                        <?php
                                        $is_sender = $message['sender_id'] == $client_id;
                                        $message_time = date('g:i A', strtotime($message['created_at']));
                                        $message_date = date('M j, Y', strtotime($message['created_at']));
                                        ?>
                                        <div class="chat-single-message <?php echo $is_sender ? 'right' : 'left'; ?>" data-message-id="<?php echo $message['id']; ?>">
                                            <?php if (!$is_sender): ?>
                                                <div class="avatar-placeholder bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    <?php echo strtoupper(substr($current_admin['first_name'] ?? '', 0, 1) . substr($current_admin['last_name'] ?? '', 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="chat-message-content">
                                                <p class="mb-3"><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                                                <p class="chat-time mb-0">
                                                    <span><?php echo $message_time; ?></span>
                                                    <?php if ($is_sender && $message['is_read']): ?>
                                                        <span class="text-alert ms-2">✓ Read</span>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Message Input Form -->
                        <form class="chat-message-box" id="messageForm" onsubmit="return sendMessageAjax(event)">
                            <input type="hidden" name="receiver_id" id="receiver_id" value="<?php echo $current_partner_id; ?>">
                            <input type="text" name="message" id="messageInput" placeholder="Type your message here..." required>
                            <div class="chat-message-box-action">
                                <button type="submit" class="btn btn-sm btn-primary-600 radius-8 d-inline-flex align-items-center gap-1">
                                    Send
                                    <iconify-icon icon="f7:paperplane"></iconify-icon>
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="d-flex align-items-center justify-content-center h-100">
                        <div class="text-center">
                            <div class="mb-3">
                                <iconify-icon icon="mdi:chat-outline" class="text-muted" style="font-size: 64px;"></iconify-icon>
                            </div>
                            <h5 class="text-muted">Select a support staff to start chatting</h5>
                            <p class="text-muted">Choose from the list on the left to begin your conversation.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="assets/js/lib/jquery-3.7.1.min.js"></script>
    <script>
        let currentPartnerId = <?php echo $current_partner_id ?: 0; ?>;
        let refreshInterval;
        let lastMessageId = 0;

        // Initialize last message ID
        function initLastMessageId() {
            const lastMessage = $('#messagesContainer .chat-single-message:last');
            if (lastMessage.length) {
                lastMessageId = parseInt(lastMessage.data('message-id')) || 0;
            }
        }

        // Auto-scroll to bottom of message list
        function scrollToBottom() {
            const messageList = document.getElementById('messageList');
            if (messageList) {
                messageList.scrollTop = messageList.scrollHeight;
            }
        }

        // Format message HTML
        function formatMessage(message, isSender, adminInitials) {
            const messageTime = new Date(message.created_at).toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });

            return `
                <div class="chat-single-message ${isSender ? 'right' : 'left'}" data-message-id="${message.id}">
                    ${!isSender ? `
                        <div class="avatar-placeholder bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            ${adminInitials}
                        </div>
                    ` : ''}
                    <div class="chat-message-content">
                        <p class="mb-3">${$('<div>').text(message.message).html().replace(/\n/g, '<br>')}</p>
                        <p class="chat-time mb-0">
                            <span>${messageTime}</span>
                            ${isSender && message.is_read ? '<span class="text-success ms-2">✓ Read</span>' : ''}
                        </p>
                    </div>
                </div>
            `;
        }

        // Load messages via AJAX
        function loadMessages(showNotification = false) {
            if (!currentPartnerId) return;

            $.get('messages.php?ajax=get_messages&partner_id=' + currentPartnerId, function(response) {
                if (response.success) {
                    const messages = response.messages;
                    if (messages.length > 0) {
                        const latestMessageId = messages[messages.length - 1].id;

                        // Only update if there are new messages
                        if (latestMessageId > lastMessageId) {
                            const messagesContainer = $('#messagesContainer');
                            const adminInitials = '<?php echo isset($current_admin) ? strtoupper(substr($current_admin['first_name'] ?? '', 0, 1) . substr($current_admin['last_name'] ?? '', 0, 1)) : 'A'; ?>';

                            // Add new messages
                            messages.forEach(message => {
                                if (message.id > lastMessageId) {
                                    const isSender = message.sender_id == <?php echo $client_id; ?>;
                                    messagesContainer.append(formatMessage(message, isSender, adminInitials));
                                }
                            });

                            lastMessageId = latestMessageId;
                            scrollToBottom();

                            if (showNotification && messages.length > 0) {
                                showNotification('New message received');
                            }
                        }
                    }
                }
            }).fail(function() {
                console.error('Failed to load messages');
            });
        }

        // Send message via AJAX
        function sendMessageAjax(event) {
            event.preventDefault();

            const messageInput = $('#messageInput');
            const message = messageInput.val().trim();
            const receiverId = $('#receiver_id').val();

            if (!message) {
                alert('Please enter a message.');
                return false;
            }

            if (!receiverId) {
                alert('Invalid receiver.');
                return false;
            }

            // Disable form during submission
            const submitBtn = $('#messageForm button[type="submit"]');
            submitBtn.prop('disabled', true).html('Sending... <iconify-icon icon="line-md:loading-twotone-loop"></iconify-icon>');

            $.post('messages.php?ajax=send_message', {
                message: message,
                receiver_id: receiverId
            }, function(response) {
                if (response.success) {
                    messageInput.val('');
                    loadMessages();
                    updateUnreadCounts();
                } else {
                    alert('Error: ' + response.error);
                }
            }).fail(function() {
                alert('Failed to send message. Please try again.');
            }).always(function() {
                submitBtn.prop('disabled', false).html('Send <iconify-icon icon="f7:paperplane"></iconify-icon>');
            });

            return false;
        }

        // Refresh messages
        function refreshMessages() {
            loadMessages();
            updateUnreadCounts();
            showNotification('Messages refreshed');
        }

        // Update unread counts
        function updateUnreadCounts() {
            $.get('messages.php?ajax=get_unread_counts', function(response) {
                if (response.success) {
                    // Update unread badges
                    $('.unread-badge').each(function() {
                        const adminId = $(this).data('admin-id');
                        const unreadCount = response.unread_counts[adminId] || 0;

                        if (unreadCount > 0) {
                            $(this).text(unreadCount > 9 ? '9+' : unreadCount).show();
                        } else {
                            $(this).hide();
                        }
                    });
                }
            });
        }

        // Search admins
        function searchAdmins(searchTerm) {
            if (searchTerm.length < 2) {
                // Reset to all admins if search term is too short
                loadAdminsList();
                return;
            }

            $.get('messages.php?ajax=search_admins&search=' + encodeURIComponent(searchTerm), function(response) {
                if (response.success) {
                    const adminsList = $('#adminsList');
                    adminsList.empty();

                    if (response.admins.length === 0) {
                        adminsList.html('<div class="text-center p-3 text-muted"><p>No support staff found.</p></div>');
                        return;
                    }

                    response.admins.forEach(admin => {
                        const adminName = admin.first_name + ' ' + admin.last_name;
                        const adminInitials = (admin.first_name?.charAt(0) || '') + (admin.last_name?.charAt(0) || '') || 'A';

                        const adminHtml = `
                            <a href="messages.php?partner_id=${admin.id}" class="chat-sidebar-single" style="text-decoration: none; color: inherit;" data-admin-id="${admin.id}">
                                <div class="img">
                                    <div class="avatar-placeholder bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        ${adminInitials}
                                    </div>
                                </div>
                                <div class="info">
                                    <h6 class="text-sm mb-1">${adminName}</h6>
                                    <p class="mb-0 text-xs">Support Administrator</p>
                                </div>
                                <div class="action text-end">
                                    <p class="mb-0 text-neutral-400 text-xs lh-1">Online</p>
                                </div>
                            </a>
                        `;

                        adminsList.append(adminHtml);
                    });
                }
            });
        }

        // Load all admins list
        function loadAdminsList() {
            // This would reload the original admin list
            // For simplicity, we'll just show all admins by reloading the section
            $('#adminsList').load('messages.php #adminsList > *');
        }

        // Export conversation
        function exportConversation() {
            if (!currentPartnerId) return;

            const conversationText = [];
            $('#messagesContainer .chat-single-message').each(function() {
                const isSender = $(this).hasClass('right');
                const sender = isSender ? 'You' : 'Support';
                const message = $(this).find('.chat-message-content p:first').text();
                const time = $(this).find('.chat-time span:first').text();

                conversationText.push(`[${time}] ${sender}: ${message}`);
            });

            const blob = new Blob([conversationText.join('\n')], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `support-chat-${new Date().toISOString().split('T')[0]}.txt`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            showNotification('Conversation exported successfully');
        }

        // Show notification
        function showNotification(message) {
            // Create toast notification
            const toast = $(`
                <div class="toast align-items-center text-bg-primary border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `);

            $('.toast-container').append(toast);
            const bsToast = new bootstrap.Toast(toast[0]);
            bsToast.show();

            // Remove toast after hide
            toast.on('hidden.bs.toast', function() {
                $(this).remove();
            });
        }

        // Initialize chat
        $(document).ready(function() {
            // Initialize last message ID
            initLastMessageId();
            scrollToBottom();

            // Create toast container
            $('body').append('<div class="toast-container position-fixed top-0 end-0 p-3"></div>');

            // Start auto-refresh (every 5 seconds)
            if (currentPartnerId) {
                refreshInterval = setInterval(() => {
                    loadMessages(true);
                    updateUnreadCounts();
                }, 5000);
            }

            // Search functionality with debounce
            let searchTimeout;
            $('#adminSearch').on('keyup', function() {
                clearTimeout(searchTimeout);
                const searchText = $(this).val().trim();

                searchTimeout = setTimeout(() => {
                    if (searchText.length >= 2) {
                        searchAdmins(searchText);
                    } else if (searchText.length === 0) {
                        loadAdminsList();
                    }
                }, 300);
            });

            // Handle admin click - prevent full page reload
            $(document).on('click', '.chat-sidebar-single[data-admin-id]', function(e) {
                const adminId = $(this).data('admin-id');
                if (adminId !== currentPartnerId) {
                    currentPartnerId = adminId;
                    $('#receiver_id').val(adminId);
                    lastMessageId = 0;
                    loadMessages();

                    // Update URL without reload
                    history.pushState(null, null, 'messages.php?partner_id=' + adminId);

                    // Update active state
                    $('.chat-sidebar-single').removeClass('active');
                    $(this).addClass('active');
                }
                e.preventDefault();
            });

            // Handle browser back/forward
            window.onpopstate = function() {
                const urlParams = new URLSearchParams(window.location.search);
                const newPartnerId = urlParams.get('partner_id');
                if (newPartnerId && newPartnerId != currentPartnerId) {
                    currentPartnerId = parseInt(newPartnerId);
                    lastMessageId = 0;
                    loadMessages();
                }
            };

            // Connection status indicator
            window.addEventListener('online', function() {
                $('#connectionStatus').text('Online').removeClass('bg-danger').addClass('bg-success');
            });

            window.addEventListener('offline', function() {
                $('#connectionStatus').text('Offline').removeClass('bg-success').addClass('bg-danger');
            });

            // Auto-focus message input when conversation is selected
            if (currentPartnerId) {
                setTimeout(() => {
                    $('#messageInput').focus();
                }, 500);
            }
        });

        // Cleanup on page unload
        $(window).on('beforeunload', function() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        });
    </script>

    <style>
        .toast-container {
            z-index: 9999;
        }
        .chat-single-message {
            opacity: 0;
            animation: fadeIn 0.3s ease-in-out forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .connection-status {
            font-size: 0.8em;
            padding: 2px 8px;
            border-radius: 12px;
        }
        .chat-search {
            position: relative;
        }
        .chat-search .icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        .chat-search input {
            padding-left: 40px;
        }
    </style>

<?php include 'include/footer.php'; ?>