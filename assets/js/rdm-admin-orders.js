jQuery(document).ready(function($) {
    // --- Variables ---
    var $orderList = $('#rdm-order-list-container');
    var $loading = $('#rdm-order-list-loading');
    var $statusFilter = $('#rdm-order-status-filter');
    var $modalContainer = $('#rdm-agent-modal-container');
    var refreshInterval = 30000; // 30 seconds
    var autoRefreshTimer = null;
    var currentStatus = '';
    var currentOrders = [];
    var currentOrderIdForAgent = null;

    // --- Core Functions ---

    // Load orders from server
    function loadOrders() {
        $loading.show();
        $orderList.empty();
        $.ajax({
            url: rdmOrders.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'rdm_fetch_orders',
                status: $statusFilter.val(),
                nonce: rdmOrders.nonce
            },
            success: function(response) {
                $loading.hide();
                if (response.success && response.data && response.data.length > 0) {
                    currentOrders = response.data;
                    response.data.forEach(function(order) {
                        $orderList.append(renderOrderCard(order));
                    });
                } else {
                    $orderList.html('<div class="rdm-no-orders">' + rdmOrders.i18n.noData + '</div>');
                }
            },
            error: function() {
                $loading.hide();
                $orderList.html('<div class="rdm-error">' + rdmOrders.i18n.error + '</div>');
            }
        });
    }

    // Start auto-refresh
    function startAutoRefresh() {
        if (autoRefreshTimer) clearInterval(autoRefreshTimer);
        autoRefreshTimer = setInterval(loadOrders, refreshInterval);
    }

    // Render order card (client-side, matches PHP partial structure)
    function renderOrderCard(order) {
        // Build item summary string
        var itemSummary = '';
        if (order.items && typeof order.items === 'object') {
            var items = [];
            $.each(order.items, function(key, item) {
                items.push(item.name + ' x' + item.qty);
            });
            itemSummary = items.join(', ');
        }
        // Build notes HTML
        var notesHtml = '';
        if (order.notes && order.notes.length) {
            order.notes.forEach(function(note) {
                notesHtml += '<div class="rdm-order-note">' +
                    $('<div>').text(note.note_text).html() +
                    ' <span class="rdm-note-meta">' + $('<span>').text(note.created_at).html() + '</span></div>';
            });
        }
        // Build actions
        var actions = '';
        if (order.status === 'processing') {
            actions += '<button class="button rdm-btn-start-preparing" data-action="start-preparing">' + rdmOrders.i18n.startPreparing + '</button>';
        }
        if (order.status === 'preparing') {
            actions += '<button class="button rdm-btn-mark-ready" data-action="mark-ready">' + rdmOrders.i18n.markReady + '</button>';
        }
        if (order.status === 'ready') {
            actions += '<button class="button rdm-btn-assign-agent" data-action="assign-agent">' + rdmOrders.i18n.assignAgent + '</button>';
        }
        actions += '<button class="button rdm-btn-print-ticket" data-action="print-ticket">' + rdmOrders.i18n.printTicket + '</button>';
        // Card HTML
        var html = '<div class="rdm-order-card order-card-status-' + order.status + '" data-order-id="' + order.id + '">' +
            '<div class="rdm-order-card-header">' +
                '<span class="rdm-order-id">#' + order.id + '</span>' +
                '<span class="rdm-order-status-badge status-' + order.status + '">' + ucwords(order.status.replace(/-/g, ' ')) + '</span>' +
                '<span class="rdm-order-time">' + order.date + '</span>' +
            '</div>' +
            '<div class="rdm-order-card-body">' +
                '<div class="rdm-order-customer"><strong>' + rdmOrders.i18n.customer + '</strong> ' + escapeHtml(order.customer) + '</div>' +
                '<div class="rdm-order-items"><strong>' + rdmOrders.i18n.items + '</strong> ' + escapeHtml(itemSummary) + '</div>' +
                '<div class="rdm-order-total"><strong>' + rdmOrders.i18n.total + '</strong> ' + escapeHtml(order.total) + '</div>' +
                '<div class="rdm-order-address"><strong>' + rdmOrders.i18n.address + '</strong> ' + escapeHtml(order.address) + '</div>' +
                '<div class="rdm-order-agent"><strong>' + rdmOrders.i18n.agent + '</strong> ' + escapeHtml(order.agent || '-') + '</div>' +
            '</div>' +
            '<div class="rdm-order-card-actions">' + actions + '</div>' +
            '<div class="rdm-order-notes-section">' +
                '<div class="rdm-order-notes-list">' + notesHtml + '</div>' +
                '<form class="rdm-add-note-form" data-order-id="' + order.id + '">' +
                    '<textarea name="note_text" class="rdm-note-text" rows="1" placeholder="' + rdmOrders.i18n.addNote + '"></textarea>' +
                    '<button type="submit" class="button rdm-btn-add-note">' + rdmOrders.i18n.saveNote + '</button>' +
                '</form>' +
            '</div>' +
        '</div>';
        return html;
    }

    // Helper: Capitalize words
    function ucwords(str) {
        return (str + '').replace(/^(.)|\s+(.)/g, function($1) { return $1.toUpperCase(); });
    }
    // Helper: Escape HTML
    function escapeHtml(text) {
        return $('<div>').text(text).html();
    }

    // --- Event Handlers ---

    // Status filter change
    $statusFilter.on('change', function() {
        loadOrders();
    });

    // Delegated order card actions
    $orderList.on('click', '.rdm-btn-start-preparing, .rdm-btn-mark-ready', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $card = $btn.closest('.rdm-order-card');
        var orderId = $card.data('order-id');
        var newStatus = ($btn.hasClass('rdm-btn-start-preparing')) ? 'preparing' : 'ready';
        $btn.prop('disabled', true);
        $.ajax({
            url: rdmOrders.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'rdm_update_order_status',
                order_id: orderId,
                new_status: newStatus,
                nonce: rdmOrders.nonce
            },
            success: function(response) {
                loadOrders();
            },
            error: function() {
                alert(rdmOrders.i18n.error);
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    // Assign Agent button
    $orderList.on('click', '.rdm-btn-assign-agent', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $card = $btn.closest('.rdm-order-card');
        var orderId = $card.data('order-id');
        currentOrderIdForAgent = orderId;
        // Fetch available agents
        $.ajax({
            url: rdmOrders.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'rdm_get_available_agents',
                nonce: rdmOrders.nonce
            },
            success: function(response) {
                if (response.success && response.data && response.data.length > 0) {
                    showAgentModal(response.data);
                } else {
                    alert(rdmOrders.i18n.noAgents);
                }
            },
            error: function() {
                alert(rdmOrders.i18n.error);
            }
        });
    });

    // Print Ticket button
    $orderList.on('click', '.rdm-btn-print-ticket', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $card = $btn.closest('.rdm-order-card');
        var orderId = $card.data('order-id');
        var url = rdmOrders.printTicketUrl + '?order_id=' + orderId + '&_wpnonce=' + rdmOrders.nonce;
        window.open(url, '_blank');
    });

    // Add Note form submit
    $orderList.on('submit', '.rdm-add-note-form', function(e) {
        e.preventDefault();
        var $form = $(this);
        var orderId = $form.data('order-id');
        var noteText = $form.find('.rdm-note-text').val();
        if (!noteText) return;
        $form.find('button').prop('disabled', true);
        $.ajax({
            url: rdmOrders.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'rdm_add_order_note',
                order_id: orderId,
                note_text: noteText,
                nonce: rdmOrders.nonce
            },
            success: function(response) {
                loadOrders();
            },
            error: function() {
                alert(rdmOrders.i18n.error);
            },
            complete: function() {
                $form.find('button').prop('disabled', false);
                $form.find('.rdm-note-text').val('');
            }
        });
    });

    // --- Agent Assignment Modal Logic ---
    // Show modal with agent list
    function showAgentModal(agents) {
        var $modal = $('#rdm-agent-assignment-modal');
        if ($modal.length === 0) {
            // If not present, load from partial (optional)
            $modalContainer.html('');
            // Could AJAX load the modal partial here if needed
        }
        $modal = $('#rdm-agent-assignment-modal');
        var $select = $modal.find('#rdm-agent-select');
        $select.empty();
        agents.forEach(function(agent) {
            $select.append('<option value="' + agent.id + '">' + escapeHtml(agent.name) + '</option>');
        });
        $modal.show();
    }
    // Confirm Assignment
    $(document).on('click', '#rdm-confirm-assign-agent', function(e) {
        e.preventDefault();
        var $modal = $('#rdm-agent-assignment-modal');
        var agentId = $modal.find('#rdm-agent-select').val();
        if (!currentOrderIdForAgent || !agentId) return;
        $(this).prop('disabled', true);
        $.ajax({
            url: rdmOrders.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'rdm_assign_agent_to_order',
                order_id: currentOrderIdForAgent,
                agent_id: agentId,
                nonce: rdmOrders.nonce
            },
            success: function(response) {
                $('#rdm-agent-assignment-modal').hide();
                loadOrders();
            },
            error: function() {
                alert(rdmOrders.i18n.error);
            },
            complete: function() {
                $('#rdm-confirm-assign-agent').prop('disabled', false);
                currentOrderIdForAgent = null;
            }
        });
    });
    // Cancel/Close modal
    $(document).on('click', '#rdm-cancel-assign-agent, .rdm-modal-overlay', function(e) {
        e.preventDefault();
        $('#rdm-agent-assignment-modal').hide();
        currentOrderIdForAgent = null;
    });

    // --- Initial Load ---
    loadOrders();
    startAutoRefresh();

    // --- i18n defaults (for client-side rendering) ---
    if (!rdmOrders.i18n.startPreparing) rdmOrders.i18n.startPreparing = 'Start Preparing';
    if (!rdmOrders.i18n.markReady) rdmOrders.i18n.markReady = 'Mark as Ready';
    if (!rdmOrders.i18n.assignAgent) rdmOrders.i18n.assignAgent = 'Assign Agent';
    if (!rdmOrders.i18n.printTicket) rdmOrders.i18n.printTicket = 'Print Ticket';
    if (!rdmOrders.i18n.customer) rdmOrders.i18n.customer = 'Customer:';
    if (!rdmOrders.i18n.items) rdmOrders.i18n.items = 'Items:';
    if (!rdmOrders.i18n.total) rdmOrders.i18n.total = 'Total:';
    if (!rdmOrders.i18n.address) rdmOrders.i18n.address = 'Address:';
    if (!rdmOrders.i18n.agent) rdmOrders.i18n.agent = 'Agent:';
    if (!rdmOrders.i18n.addNote) rdmOrders.i18n.addNote = 'Add note...';
    if (!rdmOrders.i18n.saveNote) rdmOrders.i18n.saveNote = 'Save Note';
    if (!rdmOrders.i18n.noAgents) rdmOrders.i18n.noAgents = 'No available agents.';
}); 