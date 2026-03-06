<?php
// modules/cashier/receipt.php
session_start();
require_once '../../config/config.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../../cashier-login.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: pos.php");
    exit();
}

$invoice_id = intval($_GET['id']);

// 1. Fetch ALL the joined data (Invoice, Transaction, Order, Customer, Cashier)
$query = "SELECT 
            i.invoice_no, i.subtotal, i.tax_amount, i.grand_total, i.issued_at,
            o.order_id, o.tracking_no, o.upfront_payment, o.payment_type, o.online_reference,
            u.username AS customer_name,
            t.payment_method, t.cash_tendered, t.change_given, t.reference_no,
            c.username AS cashier_name
          FROM invoices i
          JOIN orders o ON i.order_id = o.order_id
          JOIN users u ON o.user_id = u.user_id
          JOIN transactions t ON i.invoice_id = t.invoice_id
          JOIN users c ON t.cashier_id = c.user_id
          WHERE i.invoice_id = ?";
          
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$receipt_result = $stmt->get_result();

if ($receipt_result->num_rows === 0) {
    header("Location: pos.php");
    exit();
}
$receipt = $receipt_result->fetch_assoc();
$stmt->close();

// 2. Fetch the specific groceries they bought
$items_query = "SELECT oi.quantity, oi.price_at_checkout, p.name 
                FROM order_items oi 
                JOIN products p ON oi.product_id = p.product_id 
                WHERE oi.order_id = ?";
$items_stmt = $conn->prepare($items_query);
$items_stmt->bind_param("i", $receipt['order_id']);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

$items = [];
while ($row = $items_result->fetch_assoc()) {
    $items[] = $row;
}
$items_stmt->close();

// We won't include the standard header/footer here because this is a printable page!
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?php echo htmlspecialchars($receipt['invoice_no']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* This hides the buttons when the physical printer actually prints the page */
        @media print {
            .no-print { display: none !important; }
            body { background-color: white !important; }
            .receipt-container { box-shadow: none !important; border: none !important; margin: 0 !important; padding: 0 !important; width: 100% !important; max-width: 100% !important;}
        }
    </style>
</head>
<body class="bg-gray-200 min-h-screen flex flex-col items-center py-10 font-sans text-gray-900">

    <div class="no-print mb-6 flex space-x-4">
        <a href="pos.php" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-6 rounded-lg shadow transition">
            &larr; Next Customer
        </a>
        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg shadow transition flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Print Receipt
        </button>
    </div>

    <div class="receipt-container bg-white p-6 shadow-2xl rounded w-full max-w-sm font-mono text-sm border-t-8 border-gray-900">
        
        <div class="text-center mb-6">
            <h1 class="text-2xl font-black tracking-widest uppercase">PSS Grocery</h1>
            <p class="text-xs mt-1">123 Market Street, Caloocan City</p>
            <p class="text-xs">VAT Reg TIN: 123-456-789-000</p>
            <p class="text-xs mt-2 border-t border-b border-dashed border-gray-400 py-1">OFFICIAL RECEIPT</p>
        </div>

        <div class="mb-4 text-xs">
            <div class="flex justify-between"><span>OR No:</span> <strong><?php echo htmlspecialchars($receipt['invoice_no']); ?></strong></div>
            <div class="flex justify-between"><span>Date:</span> <span><?php echo date('m/d/Y h:i A', strtotime($receipt['issued_at'])); ?></span></div>
            <div class="flex justify-between"><span>Cashier:</span> <span><?php echo htmlspecialchars($receipt['cashier_name']); ?></span></div>
            <div class="flex justify-between"><span>Customer:</span> <span><?php echo htmlspecialchars($receipt['customer_name']); ?></span></div>
            <div class="flex justify-between"><span>Order Ref:</span> <span><?php echo htmlspecialchars($receipt['tracking_no']); ?></span></div>
        </div>

        <div class="border-t border-b border-dashed border-gray-400 py-3 mb-4">
            <table class="w-full text-xs">
                <thead>
                    <tr class="text-left border-b border-gray-200">
                        <th class="pb-1">QTY</th>
                        <th class="pb-1">ITEM</th>
                        <th class="pb-1 text-right">AMOUNT</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="py-1 align-top"><?php echo $item['quantity']; ?></td>
                            <td class="py-1 align-top pr-2">
                                <?php echo htmlspecialchars($item['name']); ?><br>
                                <span class="text-[10px] text-gray-500">@ ₱<?php echo number_format($item['price_at_checkout'], 2); ?></span>
                            </td>
                            <td class="py-1 align-top text-right">₱<?php echo number_format($item['price_at_checkout'] * $item['quantity'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mb-4 text-xs">
            <div class="flex justify-between mb-1">
                <span>Vatable Sales:</span>
                <span>₱<?php echo number_format($receipt['subtotal'], 2); ?></span>
            </div>
            <div class="flex justify-between mb-1">
                <span>VAT (12%):</span>
                <span>₱<?php echo number_format($receipt['tax_amount'], 2); ?></span>
            </div>
            <div class="flex justify-between mb-1">
                <span>Discount:</span>
                <span>₱0.00</span>
            </div>
            <div class="flex justify-between mt-2 pt-2 border-t border-gray-900 text-base font-black">
                <span>GRAND TOTAL:</span>
                <span>₱<?php echo number_format($receipt['grand_total'], 2); ?></span>
            </div>
            
            <?php if ($receipt['upfront_payment'] > 0): ?>
                <div class="flex justify-between mt-1 text-gray-600">
                    <span>Less: Online Payment (<?php echo ucfirst($receipt['payment_type']); ?>):</span>
                    <span>- ₱<?php echo number_format($receipt['upfront_payment'], 2); ?></span>
                </div>
                <div class="flex justify-between mt-1 font-bold border-t border-dashed border-gray-400 pt-1">
                    <span>AMOUNT DUE:</span>
                    <span>₱<?php echo number_format($receipt['grand_total'] - $receipt['upfront_payment'], 2); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="border-t border-dashed border-gray-400 pt-3 mb-6 text-xs">
            <div class="flex justify-between">
                <span>Payment Method:</span>
                <span class="uppercase font-bold">
                    <?php 
                        if ($receipt['payment_method'] === 'prepaid') echo 'FULL ONLINE PAYMENT';
                        else echo htmlspecialchars($receipt['payment_method']); 
                    ?>
                </span>
            </div>
            
            <?php if ($receipt['payment_method'] === 'cash'): ?>
                <div class="flex justify-between">
                    <span>Cash Tendered:</span>
                    <span>₱<?php echo number_format($receipt['cash_tendered'], 2); ?></span>
                </div>
                <div class="flex justify-between font-bold">
                    <span>Change:</span>
                    <span>₱<?php echo number_format($receipt['change_given'], 2); ?></span>
                </div>
            <?php elseif ($receipt['payment_method'] === 'gcash'): ?>
                <div class="flex justify-between">
                    <span>Reference No:</span>
                    <span><?php echo htmlspecialchars($receipt['reference_no']); ?></span>
                </div>
            <?php elseif ($receipt['payment_method'] === 'prepaid'): ?>
                 <div class="flex justify-between">
                    <span>Online Ref:</span>
                    <span><?php echo htmlspecialchars($receipt['online_reference']); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="text-center text-xs text-gray-500">
            <p>THIS DOCUMENT IS NOT VALID FOR CLAIM OF INPUT TAX</p>
            <p class="mt-2 font-bold text-gray-800">Thank you for shopping!</p>
            <p>Please come again.</p>
            
            <div class="mt-4 text-center font-barcode text-3xl tracking-widest opacity-70">
                ||| |||| | ||| || |||
            </div>
        </div>
        
    </div>

</body>
</html>