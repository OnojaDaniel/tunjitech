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

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdminOrSubAdmin()) {
    header("Location: ../login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    switch ($_GET['ajax']) {
        case 'get_messages':
            $partner_id = isset($_GET['partner_id']) ? intval($_GET['partner_id']) : 0;
            if ($partner_id) {
                $conversation = getConversation($admin_id, $partner_id);
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
                    if (sendMessage($admin_id, $receiver_id, $message)) {
                        echo json_encode(['success' => true, 'message' => 'Message sent successfully!']);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Failed to send message.']);
                    }
                }
            }
            exit();

        case 'delete_conversation':
            $partner_id = isset($_POST['partner_id']) ? intval($_POST['partner_id']) : 0;
            if ($partner_id) {
                if (deleteConversation($admin_id, $partner_id)) {
                    echo json_encode(['success' => true, 'message' => 'Conversation deleted successfully!']);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to delete conversation.']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid partner ID']);
            }
            exit();

        case 'get_unread_counts':
            $unread_counts = getUnreadCounts($admin_id);
            echo json_encode(['success' => true, 'unread_counts' => $unread_counts]);
            exit();
    }
}

// Get all clients for chat list
$clients = getClientsForChat();

// Get or set current chat partner
$current_partner_id = isset($_GET['partner_id']) ? intval($_GET['partner_id']) : null;
if (!$current_partner_id && !empty($clients)) {
    $current_partner_id = $clients[0]['id'];
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
        if (sendMessage($admin_id, $receiver_id, $message)) {
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
    markMessagesAsRead($current_partner_id, $admin_id);
}

// Get conversation with current partner
$conversation = [];
if ($current_partner_id) {
    $conversation = getConversation($admin_id, $current_partner_id);
}

// Get unread counts for each client
$unread_counts = getUnreadCounts($admin_id);

/**
 * Get all clients for chat
 */
function getClientsForChat() {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.company_name, u.user_type,
               (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count
        FROM users u 
        WHERE u.user_type LIKE 'client_%' AND u.status = 'approved'
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute([$_SESSION['user_id']]);
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

/**
 * Delete conversation between two users
 */
function deleteConversation($user1_id, $user2_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        DELETE FROM messages 
        WHERE (sender_id = ? AND receiver_id = ?) 
           OR (sender_id = ? AND receiver_id = ?)
    ");
    return $stmt->execute([$user1_id, $user2_id, $user2_id, $user1_id]);
}
?>

<?php include 'include/header.php'; ?>

    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
            <h6 class="fw-semibold mb-0">Client Messages</h6>
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
                            <?php echo $user_initials ?: 'A'; ?>
                        </div>
                    </div>
                    <div class="info">
                        <h6 class="text-md mb-0"><?php echo htmlspecialchars($_SESSION['username']); ?> (Admin)</h6>
                        <p class="mb-0"><span class="badge bg-success" id="connectionStatus">Online</span></p>
                    </div>
                </div>

                <!-- Search -->
                <div class="chat-search">
                    <span class="icon">
                        <iconify-icon icon="iconoir:search"></iconify-icon>
                    </span>
                    <input type="text" name="search" autocomplete="off" placeholder="Search clients...">
                </div>

                <!-- Clients List -->
                <div class="chat-all-list" id="clientsList">
                    <?php if (empty($clients)): ?>
                        <div class="text-center p-3 text-muted">
                            <p>No clients available for messaging.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($clients as $client): ?>
                            <?php
                            $unread_count = $unread_counts[$client['id']] ?? 0;
                            $is_active = $current_partner_id == $client['id'];
                            $client_name = htmlspecialchars($client['first_name'] . ' ' . $client['last_name']);
                            $client_initials = strtoupper(substr($client['first_name'] ?? '', 0, 1) . substr($client['last_name'] ?? '', 0, 1));
                            $client_type = $client['user_type'] == 'client_company' ? 'Company' : 'Individual';
                            ?>
                            <a href="messages.php?partner_id=<?php echo $client['id']; ?>" class="chat-sidebar-single <?php echo $is_active ? 'active' : ''; ?>" style="text-decoration: none; color: inherit;" data-client-id="<?php echo $client['id']; ?>">
                                <div class="img">
                                    <div class="avatar-placeholder bg-info text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <?php echo $client_initials ?: 'C'; ?>
                                    </div>
                                </div>
                                <div class="info">
                                    <h6 class="text-sm mb-1"><?php echo $client_name; ?></h6>
                                    <p class="mb-0 text-xs"><?php echo $client_type; ?></p>
                                    <?php if (!empty($client['company_name'])): ?>
                                        <p class="mb-0 text-xs text-muted"><?php echo htmlspecialchars($client['company_name']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="action text-end">
                                    <p class="mb-0 text-neutral-400 text-xs lh-1">
                                        <?php echo $client['unread_count'] > 0 ? 'Unread' : 'Read'; ?>
                                    </p>
                                    <?php if ($unread_count > 0): ?>
                                        <span class="w-16-px h-16-px text-xs rounded-circle bg-warning-main text-white d-inline-flex align-items-center justify-content-center unread-badge" data-client-id="<?php echo $client['id']; ?>">
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
                <?php if ($current_partner_id && !empty($clients)): ?>
                    <?php
                    $current_client = null;
                    foreach ($clients as $client) {
                        if ($client['id'] == $current_partner_id) {
                            $current_client = $client;
                            break;
                        }
                    }
                    ?>

                    <?php if ($current_client): ?>
                        <div class="chat-sidebar-single active">
                            <div class="img">
                                <div class="avatar-placeholder bg-info text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <?php echo strtoupper(substr($current_client['first_name'] ?? '', 0, 1) . substr($current_client['last_name'] ?? '', 0, 1)); ?>
                                </div>
                            </div>
                            <div class="info">
                                <h6 class="text-md mb-0"><?php echo htmlspecialchars($current_client['first_name'] . ' ' . $current_client['last_name']); ?></h6>
                                <p class="mb-0">
                                    <?php echo $current_client['user_type'] == 'client_company' ? 'Company Client' : 'Individual Client'; ?>
                                    <?php if (!empty($current_client['company_name'])): ?>
                                        - <?php echo htmlspecialchars($current_client['company_name']); ?>
                                    <?php endif; ?>
                                </p>
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
                                            <button class="dropdown-item rounded text-secondary-light bg-hover-neutral-200 text-hover-neutral-900 d-flex align-items-center gap-2" type="button" onclick="deleteConversation()">
                                                <iconify-icon icon="mdi:delete-outline"></iconify-icon>
                                                Delete Conversation
                                            </button>
                                        </li>
                                        <li>
                                            <a href="mailto:<?php echo htmlspecialchars($current_client['email']); ?>" class="dropdown-item rounded text-secondary-light bg-hover-neutral-200 text-hover-neutral-900 d-flex align-items-center gap-2">
                                                <iconify-icon icon="ic:outline-email"></iconify-icon>
                                                Send Email
                                            </a>
                                        </li>
                                        <li>
                                            <a href="view_client.php?id=<?php echo $current_client['id']; ?>" class="dropdown-item rounded text-secondary-light bg-hover-neutral-200 text-hover-neutral-900 d-flex align-items-center gap-2">
                                                <iconify-icon icon="fluent:person-32-regular"></iconify-icon>
                                                View Profile
                                            </a>
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
                                        <p>No messages yet. Start a conversation with this client!</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($conversation as $message): ?>
                                        <?php
                                        $is_sender = $message['sender_id'] == $admin_id;
                                        $message_time = date('g:i A', strtotime($message['created_at']));
                                        $message_date = date('M j, Y', strtotime($message['created_at']));
                                        ?>
                                        <div class="chat-single-message <?php echo $is_sender ? 'right' : 'left'; ?>" data-message-id="<?php echo $message['id']; ?>">
                                            <?php if (!$is_sender): ?>
                                                <div class="avatar-placeholder bg-info text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    <?php echo strtoupper(substr($current_client['first_name'] ?? '', 0, 1) . substr($current_client['last_name'] ?? '', 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="chat-message-content">
                                                <p class="mb-3"><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                                                <p class="chat-time mb-0">
                                                    <span><?php echo $message_time; ?></span>
                                                    <?php if ($is_sender && $message['is_read']): ?>
                                                        <span class="text-success ms-2">✓ Read</span>
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
                            <h5 class="text-muted">Select a client to start chatting</h5>
                            <p class="text-muted">Choose from the list on the left to begin your conversation.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Conversation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this conversation? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete Conversation</button>
                </div>
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
        function formatMessage(message, isSender, clientInitials) {
            const messageTime = new Date(message.created_at).toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });

            return `
                <div class="chat-single-message ${isSender ? 'right' : 'left'}" data-message-id="${message.id}">
                    ${!isSender ? `
                        <div class="avatar-placeholder bg-info text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            ${clientInitials}
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
                            const clientInitials = '<?php echo isset($current_client) ? strtoupper(substr($current_client['first_name'] ?? '', 0, 1) . substr($current_client['last_name'] ?? '', 0, 1)) : 'C'; ?>';

                            // Add new messages
                            messages.forEach(message => {
                                if (message.id > lastMessageId) {
                                    const isSender = message.sender_id == <?php echo $admin_id; ?>;
                                    messagesContainer.append(formatMessage(message, isSender, clientInitials));
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

        // Delete conversation
        function deleteConversation() {
            $('#deleteModal').modal('show');
        }

        // Confirm delete
        $('#confirmDelete').on('click', function() {
            const btn = $(this);
            btn.prop('disabled', true).html('Deleting...');

            $.post('messages.php?ajax=delete_conversation', {
                partner_id: currentPartnerId
            }, function(response) {
                if (response.success) {
                    $('#messagesContainer').html('<div class="text-center p-4 text-muted"><p>No messages yet. Start a conversation with this client!</p></div>');
                    $('#deleteModal').modal('hide');
                    showNotification('Conversation deleted successfully');
                } else {
                    alert('Error: ' + response.error);
                }
            }).fail(function() {
                alert('Failed to delete conversation. Please try again.');
            }).always(function() {
                btn.prop('disabled', false).html('Delete Conversation');
            });
        });

        // Refresh messages
        function refreshMessages() {
            loadMessages();
            showNotification('Messages refreshed');
        }

        // Update unread counts
        function updateUnreadCounts() {
            $.get('messages.php?ajax=get_unread_counts', function(response) {
                if (response.success) {
                    // Update unread badges
                    $('.unread-badge').each(function() {
                        const clientId = $(this).data('client-id');
                        const unreadCount = response.unread_counts[clientId] || 0;

                        if (unreadCount > 0) {
                            $(this).text(unreadCount > 9 ? '9+' : unreadCount).show();
                        } else {
                            $(this).hide();
                        }
                    });
                }
            });
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

            // Search functionality
            $('.chat-search input').on('keyup', function() {
                const searchText = $(this).val().toLowerCase();
                $('.chat-all-list .chat-sidebar-single').each(function() {
                    const clientName = $(this).find('.info h6').text().toLowerCase();
                    if (clientName.includes(searchText)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });

            // Handle client click - prevent full page reload
            $('.chat-sidebar-single[data-client-id]').on('click', function(e) {
                const clientId = $(this).data('client-id');
                if (clientId !== currentPartnerId) {
                    currentPartnerId = clientId;
                    $('#receiver_id').val(clientId);
                    lastMessageId = 0;
                    loadMessages();

                    // Update URL without reload
                    history.pushState(null, null, 'messages.php?partner_id=' + clientId);
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
    </style>

<?php include 'include/footer.php'; ?>