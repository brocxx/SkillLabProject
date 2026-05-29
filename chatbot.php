<?php
/**
 * Chatbot Handler
 * - Pulls live inventory + shipment data from the database
 * - Sends it as context to Gemini API so it can answer questions
 * - If API fails, uses simple keyword matching against real DB data
 */

session_start();
require_once 'db.php';

// Only logged-in users can use the chatbot
if (!isset($_SESSION['role'])) {
    echo json_encode(['reply' => 'Please log in to use the chatbot.']);
    exit;
}

// Get the user's message
$userMessage = isset($_POST['message']) ? trim($_POST['message']) : '';

if (empty($userMessage)) {
    echo json_encode(['reply' => 'Please type a message.']);
    exit;
}

// -------------------------------------------------------
// STEP 1: Pull live data from database to use as context
// -------------------------------------------------------

$inventorySummary = '';
$shipmentSummary  = '';

try {
    // Get all products with warehouse info
    $stmt = $pdo->query("SELECT p.name, p.category, p.quantity, p.price, w.name AS warehouse FROM products p LEFT JOIN warehouses w ON p.warehouse_id = w.id ORDER BY p.name ASC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($products)) {
        $inventorySummary = "Current Inventory:\n";
        foreach ($products as $p) {
            $wh = $p['warehouse'] ? $p['warehouse'] : 'Unassigned';
            $inventorySummary .= "- {$p['name']} | Category: {$p['category']} | Qty: {$p['quantity']} | Price: \${$p['price']} | Warehouse: $wh\n";
        }
    } else {
        $inventorySummary = "Current Inventory: No products found.\n";
    }

    // Get recent shipments
    $stmt2 = $pdo->query("SELECT s.tracking_number, s.status, s.destination, s.quantity, s.current_location, p.name AS product FROM shipments s LEFT JOIN products p ON s.product_id = p.id ORDER BY s.id DESC LIMIT 20");
    $shipments = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($shipments)) {
        $shipmentSummary = "Recent Shipments:\n";
        foreach ($shipments as $s) {
            $loc = $s['current_location'] ? $s['current_location'] : 'No location update';
            $shipmentSummary .= "- Tracking: {$s['tracking_number']} | Product: {$s['product']} | Qty: {$s['quantity']} | Status: {$s['status']} | Destination: {$s['destination']} | Location: $loc\n";
        }
    } else {
        $shipmentSummary = "Recent Shipments: No shipments found.\n";
    }

} catch (PDOException $e) {
    $inventorySummary = "Could not load inventory data.";
    $shipmentSummary  = "Could not load shipment data.";
}

// -------------------------------------------------------
// STEP 2: Try the Gemini API first
// -------------------------------------------------------

$GEMINI_API_KEY = 'YOUR_GEMINI_API_KEY_HERE'; // <-- Put your Gemini API key here

$systemContext = "You are a supply chain assistant for a warehouse management system. You ONLY answer questions about inventory and shipments. If someone asks something unrelated (like general knowledge, jokes, coding, etc.), politely say: 'I can only help with inventory and shipment questions.' Always be concise and helpful. Here is the live data you have access to:\n\n" . $inventorySummary . "\n" . $shipmentSummary;

$fullPrompt = $systemContext . "\n\nUser question: " . $userMessage;

$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $GEMINI_API_KEY;

$requestBody = json_encode([
    'contents' => [
        [
            'parts' => [
                ['text' => $fullPrompt]
            ]
        ]
    ]
]);

// Simple curl request to Gemini
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10 second timeout

$response     = curl_exec($ch);
$httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError    = curl_error($ch);
curl_close($ch);

// Try to parse Gemini response
$geminiReply = '';
if (!$curlError && $httpCode === 200 && $response) {
    $data = json_decode($response, true);
    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        $geminiReply = $data['candidates'][0]['content']['parts'][0]['text'];
    }
}

// If Gemini gave us a reply, send it back
if (!empty($geminiReply)) {
    echo json_encode(['reply' => $geminiReply, 'source' => 'gemini']);
    exit;
}

// -------------------------------------------------------
// STEP 3: FALLBACK — keyword matching using real DB data
// -------------------------------------------------------

$msg = strtolower($userMessage);
$reply = '';

// Check for tracking number pattern like TRK-123
if (preg_match('/\b(trk-?\w+)\b/i', $userMessage, $match)) {
    $trackNum = strtoupper($match[1]);
    // Look it up in our already-fetched shipment data
    $found = false;
    foreach ($shipments as $s) {
        if (stripos($s['tracking_number'], $trackNum) !== false) {
            $loc = $s['current_location'] ? $s['current_location'] : 'No location update yet';
            $reply = "Tracking {$s['tracking_number']}: {$s['product']} (Qty: {$s['quantity']}) → {$s['status']} → Destination: {$s['destination']}. Current location: $loc.";
            $found = true;
            break;
        }
    }
    if (!$found) {
        $reply = "I couldn't find a shipment with tracking number '$trackNum'. Please double-check the number.";
    }
}

// Questions about stock levels
elseif (strpos($msg, 'stock') !== false || strpos($msg, 'inventory') !== false || strpos($msg, 'quantity') !== false) {
    if (empty($products)) {
        $reply = "No products are currently in inventory.";
    } else {
        $lowStock    = [];
        $outOfStock  = [];
        $totalItems  = count($products);

        foreach ($products as $p) {
            if ($p['quantity'] == 0) {
                $outOfStock[] = $p['name'];
            } elseif ($p['quantity'] <= 5) {
                $lowStock[] = "{$p['name']} ({$p['quantity']} left)";
            }
        }

        $reply = "There are $totalItems products in inventory. ";
        if (!empty($outOfStock)) {
            $reply .= "Out of stock: " . implode(', ', $outOfStock) . ". ";
        }
        if (!empty($lowStock)) {
            $reply .= "Low stock (≤5): " . implode(', ', $lowStock) . ".";
        }
        if (empty($outOfStock) && empty($lowStock)) {
            $reply .= "All products are well stocked!";
        }
    }
}

// Questions about shipments / deliveries
elseif (strpos($msg, 'shipment') !== false || strpos($msg, 'delivery') !== false || strpos($msg, 'shipping') !== false || strpos($msg, 'transit') !== false) {
    if (empty($shipments)) {
        $reply = "No shipments have been recorded yet.";
    } else {
        $pending   = 0;
        $transit   = 0;
        $delivered = 0;
        foreach ($shipments as $s) {
            if ($s['status'] == 'Pending')    $pending++;
            if ($s['status'] == 'In Transit') $transit++;
            if ($s['status'] == 'Delivered')  $delivered++;
        }
        $total = count($shipments);
        $reply = "Out of the last $total shipments — Pending: $pending, In Transit: $transit, Delivered: $delivered.";
    }
}

// Questions about a specific product name
elseif (strpos($msg, 'product') !== false || strpos($msg, 'item') !== false || strpos($msg, 'price') !== false) {
    if (empty($products)) {
        $reply = "No products found in the system.";
    } else {
        // Try to find product name mentioned in the message
        $foundProduct = null;
        foreach ($products as $p) {
            if (stripos($msg, strtolower($p['name'])) !== false) {
                $foundProduct = $p;
                break;
            }
        }

        if ($foundProduct) {
            $wh = $foundProduct['warehouse'] ? $foundProduct['warehouse'] : 'Unassigned';
            $reply = "{$foundProduct['name']}: {$foundProduct['quantity']} units in stock at \${$foundProduct['price']} each. Stored at: $wh.";
        } else {
            $totalProducts = count($products);
            $totalValue    = array_sum(array_map(fn($p) => $p['quantity'] * $p['price'], $products));
            $reply = "There are $totalProducts products in the system. Total inventory value: $" . number_format($totalValue, 2) . ".";
        }
    }
}

// Warehouse questions
elseif (strpos($msg, 'warehouse') !== false || strpos($msg, 'location') !== false) {
    try {
        $wStmt = $pdo->query("SELECT name, location, manager FROM warehouses ORDER BY name ASC");
        $warehouses = $wStmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($warehouses)) {
            $reply = "No warehouses have been set up yet.";
        } else {
            $reply = "Registered warehouses: ";
            $parts = [];
            foreach ($warehouses as $w) {
                $mgr = $w['manager'] ? " (Manager: {$w['manager']})" : '';
                $parts[] = "{$w['name']} at {$w['location']}$mgr";
            }
            $reply .= implode('; ', $parts) . ".";
        }
    } catch (PDOException $e) {
        $reply = "Could not load warehouse info right now.";
    }
}

// Hello / greetings
elseif (strpos($msg, 'hello') !== false || strpos($msg, 'hi') !== false || strpos($msg, 'hey') !== false) {
    $reply = "Hi there! I can help you with inventory stock levels, shipment status, or warehouse info. What do you need?";
}

// Help
elseif (strpos($msg, 'help') !== false || strpos($msg, 'what can you do') !== false) {
    $reply = "I can answer questions about: inventory stock levels, product quantities, shipment tracking, delivery status, and warehouse locations. Just ask!";
}

// Anything else
else {
    $reply = "I can only help with inventory and shipment questions. Try asking about stock levels, a tracking number, or shipment status.";
}

echo json_encode(['reply' => $reply, 'source' => 'fallback']);
?>
