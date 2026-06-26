/**
 * assets/js/app.js
 * Handles package selection, STK push trigger, payment polling, and voucher redemption.
 */

let selectedPackageId = null;
let pollInterval = null;

function selectPackage(btn) {
    const card = btn.closest('.package-card');
    selectedPackageId = card.dataset.id;
    document.getElementById('selectedPackageName').textContent = card.dataset.name;
    document.getElementById('selectedPackagePrice').textContent = 'KES ' + card.dataset.price;
    document.getElementById('payModal').classList.remove('hidden');
    document.getElementById('payStatus').textContent = '';
}

function closeModal() {
    document.getElementById('payModal').classList.add('hidden');
    clearInterval(pollInterval);
}

async function initiatePayment() {
    const phone = document.getElementById('phoneInput').value.trim();
    const mac = document.getElementById('clientMac').value;
    const statusEl = document.getElementById('payStatus');
    const payBtn = document.getElementById('payButton');

    if (!phone) {
        statusEl.textContent = 'Please enter your M-Pesa phone number.';
        return;
    }

    payBtn.disabled = true;
    statusEl.textContent = 'Sending payment request to your phone...';

    try {
        const res = await fetch('api/initiate_payment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ package_id: selectedPackageId, phone, mac }),
        });
        const data = await res.json();

        if (!data.success) {
            statusEl.textContent = data.message || 'Payment request failed.';
            payBtn.disabled = false;
            return;
        }

        statusEl.textContent = 'Check your phone and enter your M-Pesa PIN...';
        pollPaymentStatus(data.transaction_id);
    } catch (err) {
        statusEl.textContent = 'Network error. Please try again.';
        payBtn.disabled = false;
    }
}

function pollPaymentStatus(transactionId) {
    const statusEl = document.getElementById('payStatus');
    let attempts = 0;
    const maxAttempts = 30; // ~60 seconds at 2s interval

    pollInterval = setInterval(async () => {
        attempts++;
        if (attempts > maxAttempts) {
            clearInterval(pollInterval);
            statusEl.textContent = 'Payment timed out. If money was deducted, contact support.';
            document.getElementById('payButton').disabled = false;
            return;
        }

        const res = await fetch(`api/check_payment.php?transaction_id=${transactionId}`);
        const data = await res.json();

        if (data.status === 'completed') {
            clearInterval(pollInterval);
            statusEl.innerHTML = `Connected! Username: <b>${data.username}</b><br>You're online until ${data.expires_at}.`;
            // Most MikroTik hotspot setups auto-detect the new user session,
            // but you can also auto-submit the hotspot login form here if needed.
        } else if (data.status === 'failed') {
            clearInterval(pollInterval);
            statusEl.textContent = 'Payment failed or was cancelled. Please try again.';
            document.getElementById('payButton').disabled = false;
        }
        // else still pending - keep polling
    }, 2000);
}

async function redeemVoucher() {
    const code = document.getElementById('voucherInput').value.trim();
    const mac = document.getElementById('clientMac').value;
    const statusEl = document.getElementById('voucherStatus');

    if (!code) {
        statusEl.textContent = 'Enter a voucher code.';
        return;
    }

    statusEl.textContent = 'Checking voucher...';

    try {
        const res = await fetch('api/redeem_voucher.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code, mac }),
        });
        const data = await res.json();

        if (data.success) {
            statusEl.innerHTML = `Connected! Valid until ${data.expires_at}.`;
        } else {
            statusEl.textContent = data.message || 'Invalid voucher.';
        }
    } catch (err) {
        statusEl.textContent = 'Network error. Please try again.';
    }
}
