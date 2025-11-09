<?php
// ==========================================
// ORDERS PAGE - Customer Order Management
// ==========================================
session_start();

// Authentication check
if (empty($_SESSION['user']) || ($_SESSION['user']['aud'] ?? '') !== 'customer') {
    header('Location: /customer/login.php');
    exit;
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get customer info
$customerName = $_SESSION['user']['full_name'] ?? 'Customer';
$customerId = $_SESSION['user']['id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - RADS Tooling</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Material Icons -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="/assets/CSS/orders.css">
    <link rel="stylesheet" href="/assets/CSS/responsive.css">
    
    <style>
        /* Navigation Header */
        .orders-navbar {
            background: white;
            border-bottom: 1px solid var(--gray-200);
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow-sm);
        }
        
        .navbar-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .navbar-menu-btn {
            width: 2.5rem;
            height: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--gray-100);
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .navbar-menu-btn:hover {
            background: var(--gray-200);
        }
        
        .navbar-menu-btn .material-symbols-rounded {
            font-size: 1.5rem;
            color: var(--gray-700);
        }
        
        .navbar-brand {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-900);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .navbar-brand .material-symbols-rounded {
            color: var(--primary-color);
        }
        
        .navbar-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .navbar-user {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 1rem;
            background: var(--gray-50);
            border-radius: var(--radius-lg);
        }
        
        .navbar-user .material-symbols-rounded {
            font-size: 1.5rem;
            color: var(--primary-color);
        }
        
        .navbar-username {
            font-size: 0.9375rem;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        @media (max-width: 768px) {
            .orders-navbar {
                padding: 1rem;
            }
            
            .navbar-brand span {
                display: none;
            }
            
            .navbar-username {
                display: none;
            }
        }
    </style>
</head>

<body>

    <!-- ==========================================
         NAVIGATION HEADER
         ========================================== -->
    <nav class="orders-navbar">
        <div class="navbar-left">
            <button class="navbar-menu-btn" onclick="window.location.href='/customer/homepage.php'" title="Back to Home">
                <span class="material-symbols-rounded">menu</span>
            </button>
            <a href="/customer/homepage.php" class="navbar-brand">
                <span class="material-symbols-rounded">home</span>
                <span>RADS Tooling</span>
            </a>
        </div>
        
        <div class="navbar-right">
            <div class="navbar-user">
                <span class="material-symbols-rounded">account_circle</span>
                <span class="navbar-username"><?php echo htmlspecialchars($customerName); ?></span>
            </div>
        </div>
    </nav>

    <!-- ==========================================
         MAIN CONTENT
         ========================================== -->
    <div class="orders-layout">

        <!-- SIDEBAR -->
        <aside class="orders-sidebar">
            <div class="sidebar-header">
                <h3>
                    <span class="material-symbols-rounded">filter_list</span>
                    Filter Orders
                </h3>
            </div>

            <nav class="sidebar-menu">
                <button class="menu-item active" data-status="all" onclick="filterOrders('all')">
                    <span class="material-symbols-rounded">list_alt</span>
                    <span>All Orders</span>
                    <span class="badge" id="badge-all">0</span>
                </button>

                <button class="menu-item" data-status="pending" onclick="filterOrders('pending')">
                    <span class="material-symbols-rounded">schedule</span>
                    <span>To Pay</span>
                    <span class="badge badge-pending" id="badge-pending">0</span>
                </button>

                <button class="menu-item" data-status="processing" onclick="filterOrders('processing')">
                    <span class="material-symbols-rounded">autorenew</span>
                    <span>Processing</span>
                    <span class="badge badge-processing" id="badge-processing">0</span>
                </button>

                <button class="menu-item" data-status="completed" onclick="filterOrders('completed')">
                    <span class="material-symbols-rounded">check_circle</span>
                    <span>Completed</span>
                    <span class="badge badge-completed" id="badge-completed">0</span>
                </button>

                <button class="menu-item" data-status="cancelled" onclick="filterOrders('cancelled')">
                    <span class="material-symbols-rounded">cancel</span>
                    <span>Cancelled</span>
                    <span class="badge badge-cancelled" id="badge-cancelled">0</span>
                </button>
            </nav>
        </aside>

        <!-- MAIN CONTENT AREA -->
        <main class="orders-main">
            <!-- Header -->
            <div class="orders-header">
                <div>
                    <h1>My Orders</h1>
                    <p class="subtitle">View and manage your orders</p>
                </div>

                <!-- Search Box -->
                <div class="search-box">
                    <span class="material-symbols-rounded">search</span>
                    <input type="text" id="searchInput" placeholder="Search orders by code or item..." onkeyup="searchOrders()">
                </div>
            </div>

            <!-- Orders Container -->
            <div id="ordersContainer" class="orders-container">
                <div class="loading-state">
                    <div class="spinner"></div>
                    <p>Loading your orders...</p>
                </div>
            </div>
        </main>
    </div>

    <!-- ==========================================
         ORDER DETAILS MODAL
         ========================================== -->
    <div id="orderDetailsModal" class="modal">
        <div class="modal-overlay" onclick="closeOrderDetailsModal()"></div>
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2>
                    <span class="material-symbols-rounded">receipt_long</span>
                    Order Details
                </h2>
                <button class="modal-close" onclick="closeOrderDetailsModal()">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>
            <div class="modal-body" id="orderDetailsContent">
                <!-- Populated by JS -->
            </div>
        </div>
    </div>

    <!-- ==========================================
         FEEDBACK MODAL
         ========================================== -->
    <div id="feedbackModal" class="modal">
        <div class="modal-overlay"></div>
        <div class="modal-content modal-small">
            <div class="modal-header">
                <h2>
                    <span class="material-symbols-rounded">rate_review</span>
                    Rate Your Order
                </h2>
                <button class="modal-close" onclick="closeFeedbackModal()">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="feedback-stars" id="feedbackStars">
                    <button type="button" class="fb-star" data-star="1">★</button>
                    <button type="button" class="fb-star" data-star="2">★</button>
                    <button type="button" class="fb-star" data-star="3">★</button>
                    <button type="button" class="fb-star" data-star="4">★</button>
                    <button type="button" class="fb-star" data-star="5">★</button>
                </div>
                <textarea 
                    id="feedbackComment" 
                    class="feedback-textarea" 
                    placeholder="Tell us about your experience with the cabinet (optional)"
                    rows="4"
                ></textarea>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeFeedbackModal()">Cancel</button>
                <button id="submitFeedback" class="btn btn-primary">Submit Feedback</button>
            </div>
        </div>
    </div>

    
    <script src="/assets/JS/orders.js"></script>

  
    <script>
        (function() {
            let currentOrderId = null;
            let currentRating = 0;

            const modal = document.getElementById('feedbackModal');
            const stars = Array.from(document.querySelectorAll('.fb-star'));
            const commentEl = document.getElementById('feedbackComment');
            const submitBtn = document.getElementById('submitFeedback');

            // Open feedback modal
            window.openFeedbackModal = function(orderId) {
                currentOrderId = Number(orderId);
                currentRating = 0;
                if (commentEl) commentEl.value = '';
                stars.forEach(s => s.classList.remove('is-on'));
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            };

            // Close feedback modal
            window.closeFeedbackModal = function() {
                modal.classList.remove('active');
                document.body.style.overflow = '';
                currentOrderId = null;
                currentRating = 0;
            };

            // Close on overlay click
            modal.querySelector('.modal-overlay')?.addEventListener('click', closeFeedbackModal);

            // Star click handling
            stars.forEach(btn => {
                btn.addEventListener('click', () => {
                    currentRating = Number(btn.dataset.star);
                    stars.forEach(s => {
                        s.classList.toggle('is-on', Number(s.dataset.star) <= currentRating);
                    });
                });
            });

            // Submit feedback
            submitBtn?.addEventListener('click', async () => {
                if (!currentOrderId || currentRating < 1) {
                    alert('Please select a star rating (1-5).');
                    return;
                }

                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="material-symbols-rounded">hourglass_empty</span> Submitting...';

                try {
                    const res = await fetch('/backend/api/mark_received.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            order_id: currentOrderId,
                            rating: currentRating,
                            comment: commentEl?.value?.trim() || ''
                        })
                    });

                    const data = await res.json().catch(() => ({}));

                    if (!res.ok || !data.success) {
                        alert(data.message || 'Failed to submit feedback.');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = 'Submit Feedback';
                        return;
                    }

                    closeFeedbackModal();
                    alert('Thank you! Your feedback has been published and is now live!');

                    // Refresh orders to show updated feedback status
                    if (typeof loadCustomerOrders === 'function') {
                        loadCustomerOrders(window.__ordersCurrentFilter || 'all');
                    }
                } catch (err) {
                    console.error('Feedback submission error:', err);
                    alert('Network error. Please try again.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Submit Feedback';
                }
            });
        })();
    </script>

</body>

</html>