/**
 * Restaurant Delivery Manager - Payment Interface JavaScript
 * Mobile-first payment collection and cash reconciliation
 */

(function($) {
    'use strict';

    class RDMPayments {
        constructor() {
            this.init();
        }

        init() {
            this.bindEvents();
            this.loadTodaysCashReport();
        }

        bindEvents() {
            $(document).on('click', '.rdm-collect-payment-btn', this.openPaymentModal.bind(this));
            $(document).on('click', '.rdm-payment-modal-close', this.closePaymentModal.bind(this));
            $(document).on('input', '#rdm-collected-amount', this.calculateChange.bind(this));
            $(document).on('submit', '#rdm-payment-form', this.handlePaymentSubmission.bind(this));
        }

        openPaymentModal(e) {
            e.preventDefault();
            const button = $(e.currentTarget);
            const orderId = button.data('order-id');
            const orderTotal = parseFloat(button.data('order-total'));
            
            this.createPaymentModal({
                orderId: orderId,
                orderTotal: orderTotal,
                customerName: button.data('customer-name') || 'Customer',
                orderNumber: button.data('order-number') || orderId
            });
        }

        createPaymentModal(orderData) {
            const modal = $(`
                <div class="rdm-payment-modal" id="rdm-payment-modal">
                    <div class="rdm-payment-modal-content">
                        <div class="rdm-payment-modal-header">
                            <h3 class="rdm-payment-modal-title">Collect Payment</h3>
                            <button class="rdm-payment-modal-close" type="button">&times;</button>
                        </div>
                        <div class="rdm-payment-modal-body">
                            <div class="rdm-payment-order-details">
                                <div class="rdm-payment-order-detail rdm-payment-total">
                                    <span class="rdm-payment-order-label">Total Amount:</span>
                                    <span class="rdm-payment-order-value">${this.formatCurrency(orderData.orderTotal)}</span>
                                </div>
                            </div>
                            <form id="rdm-payment-form" class="rdm-payment-form">
                                <div class="rdm-payment-form-group">
                                    <label for="rdm-collected-amount">Amount Collected:</label>
                                    <input type="number" id="rdm-collected-amount" name="collected_amount" step="0.01" required>
                                </div>
                                <div id="rdm-change-display" style="display: none;">
                                    <div>Change: <span id="rdm-change-amount">$0.00</span></div>
                                </div>
                                <input type="hidden" name="order_id" value="${orderData.orderId}">
                                <input type="hidden" name="order_total" value="${orderData.orderTotal}">
                                <input type="hidden" name="action" value="rdm_collect_cod_payment">
                                <input type="hidden" name="nonce" value="${rdmPayments.nonce}">
                                <button type="submit" id="rdm-collect-payment-submit" disabled>Collect Payment</button>
                                <button type="button" class="rdm-payment-modal-close">Cancel</button>
                            </form>
                        </div>
                    </div>
                </div>
            `);
            $('body').append(modal);
            modal.fadeIn(300);
        }

        closePaymentModal() {
            $('#rdm-payment-modal').fadeOut(300, function() {
                $(this).remove();
            });
        }

        calculateChange() {
            const collectedAmount = parseFloat($('#rdm-collected-amount').val()) || 0;
            const orderTotal = parseFloat($('#rdm-payment-form input[name="order_total"]').val()) || 0;
            
            if (collectedAmount > 0) {
                const change = Math.max(0, collectedAmount - orderTotal);
                $('#rdm-change-display').show();
                $('#rdm-change-amount').text('$' + change.toFixed(2));
                $('#rdm-collect-payment-submit').prop('disabled', collectedAmount < orderTotal);
            } else {
                $('#rdm-change-display').hide();
                $('#rdm-collect-payment-submit').prop('disabled', true);
            }
        }

        handlePaymentSubmission(e) {
            e.preventDefault();
            const formData = $(e.currentTarget).serialize();
            
            $.ajax({
                url: rdmPayments.ajaxUrl,
                type: 'POST',
                data: formData,
                success: (response) => {
                    if (response.success) {
                        this.showMessage('Payment collected successfully', 'success');
                        this.closePaymentModal();
                        this.loadTodaysCashReport();
                    } else {
                        this.showMessage(response.data.message || 'Error occurred', 'error');
                    }
                }
            });
        }

        loadTodaysCashReport() {
            $.ajax({
                url: rdmPayments.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rdm_get_agent_payments',
                    date: new Date().toISOString().split('T')[0],
                    nonce: rdmPayments.nonce
                },
                success: (response) => {
                    if (response.success && response.data.summary.transaction_count > 0) {
                        this.updateCashSummary(response.data);
                    }
                }
            });
        }

        updateCashSummary(reportData) {
            const summaryHtml = `
                <div class="rdm-cash-reconciliation">
                    <h3>Today's Cash Summary</h3>
                    <div class="rdm-cash-summary-grid">
                        <div>Total Collected: ${this.formatCurrency(reportData.summary.total_collections)}</div>
                        <div>Change Given: ${this.formatCurrency(reportData.summary.total_change)}</div>
                        <div>Net Amount: ${this.formatCurrency(reportData.summary.net_amount)}</div>
                    </div>
                </div>
            `;
            
            let cashContainer = $('#rdm-cash-summary-container');
            if (cashContainer.length === 0) {
                $('#rrm-order-list-container').after('<div id="rdm-cash-summary-container"></div>');
                cashContainer = $('#rdm-cash-summary-container');
            }
            cashContainer.html(summaryHtml);
        }

        formatCurrency(amount) {
            return '$' + parseFloat(amount).toFixed(2);
        }

        showMessage(message, type = 'info') {
            console.log(type + ': ' + message);
            alert(message);
        }
    }

    $(document).ready(function() {
        window.rdmPayments = new RDMPayments();
    });

})(jQuery);
