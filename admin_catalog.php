<?php
// admin_catalog.php - Redesigned Clean & Structured Catalog Manager
include 'includes/auth_check.php';
include 'includes/db_connect.php';

// Handle Form Submissions
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Delete Product
    if (isset($_POST['delete_product'])) {
        $product_id = (int)$_POST['product_id'];
        try {
            $conn->prepare("DELETE FROM product_test WHERE product_id = ?")->execute([$product_id]);
            $conn->prepare("DELETE FROM product_discipline WHERE product_id = ?")->execute([$product_id]);
            $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
            if ($stmt->execute([$product_id])) {
                $message = "Product sector deleted successfully!";
            }
        } catch (PDOException $e) {
            $message = "Error deleting product: " . $e->getMessage();
            $messageType = 'error';
        }
    }
    // 2. Delete Category / Discipline
    elseif (isset($_POST['delete_discipline'])) {
        $discipline_id = (int)$_POST['discipline_id'];
        try {
            $conn->prepare("DELETE FROM product_test WHERE test_id IN (SELECT id FROM tests WHERE discipline_id = ?)")->execute([$discipline_id]);
            $conn->prepare("DELETE FROM tests WHERE discipline_id = ?")->execute([$discipline_id]);
            $conn->prepare("DELETE FROM product_discipline WHERE discipline_id = ?")->execute([$discipline_id]);
            $stmt = $conn->prepare("DELETE FROM disciplines WHERE id = ?");
            if ($stmt->execute([$discipline_id])) {
                $message = "Testing Category deleted successfully!";
            }
        } catch (PDOException $e) {
            $message = "Error deleting category: " . $e->getMessage();
            $messageType = 'error';
        }
    }
    // 2b. Delete Individual Test
    elseif (isset($_POST['delete_test'])) {
        $test_id = (int)$_POST['test_id'];
        try {
            $conn->prepare("DELETE FROM product_test WHERE test_id = ?")->execute([$test_id]);
            $stmt = $conn->prepare("DELETE FROM tests WHERE id = ?");
            if ($stmt->execute([$test_id])) {
                $message = "Test deleted successfully!";
            }
        } catch (PDOException $e) {
            $message = "Error deleting test: " . $e->getMessage();
            $messageType = 'error';
        }
    }
    // 3. Add Test
    elseif (isset($_POST['add_test'])) {
        $discipline_id = $_POST['discipline_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description'] ?? '');
        $standards = trim($_POST['standards'] ?? '');
        
        if (!empty($name) && !empty($discipline_id)) {
            $stmt = $conn->prepare("INSERT INTO tests (discipline_id, name, description, standards) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$discipline_id, $name, $description, $standards])) {
                $message = "New test added successfully!";
            }
        } else {
            $message = "Category and Test Name are required.";
            $messageType = 'error';
        }
    } 
    // 4. Map Test
    elseif (isset($_POST['map_test'])) {
        $product_id = $_POST['product_id'];
        $test_id = $_POST['test_id'];
        
        $check = $conn->prepare("SELECT * FROM product_test WHERE product_id = ? AND test_id = ?");
        $check->execute([$product_id, $test_id]);
        if ($check->rowCount() > 0) {
            $message = "This test is already mapped to the selected product sector.";
            $messageType = 'error';
        } else {
            $stmt = $conn->prepare("INSERT INTO product_test (product_id, test_id) VALUES (?, ?)");
            if ($stmt->execute([$product_id, $test_id])) {
                $message = "Test mapped to product sector successfully!";
            }
        }
    } 
    // 5. Unmap Test
    elseif (isset($_POST['unmap_test'])) {
        $product_id = $_POST['product_id'];
        $test_id = $_POST['test_id'];
        $stmt = $conn->prepare("DELETE FROM product_test WHERE product_id = ? AND test_id = ?");
        if ($stmt->execute([$product_id, $test_id])) {
            $message = "Test unmapped from product sector successfully!";
        }
    }
    // 6. Add Product
    elseif (isset($_POST['add_product'])) {
        $product_name = trim($_POST['product_name']);
        if (!empty($product_name)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $product_name)));
            $stmt = $conn->prepare("INSERT INTO products (name, slug) VALUES (?, ?)");
            if ($stmt->execute([$product_name, $slug])) {
                $message = "New product sector added successfully!";
            }
        }
    }
    // 7. Add Discipline / Category
    elseif (isset($_POST['add_discipline'])) {
        $discipline_name = trim($_POST['discipline_name']);
        if (!empty($discipline_name)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $discipline_name)));
            $stmt = $conn->prepare("INSERT INTO disciplines (name, slug) VALUES (?, ?)");
            if ($stmt->execute([$discipline_name, $slug])) {
                $message = "New testing category added successfully!";
            }
        }
    }
}

// Fetch Catalog Data
$disciplines = $conn->query("
    SELECT d.*, COUNT(t.id) as test_count 
    FROM disciplines d 
    LEFT JOIN tests t ON d.id = t.discipline_id 
    GROUP BY d.id 
    ORDER BY d.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$products = $conn->query("
    SELECT p.*, COUNT(pt.test_id) as test_count 
    FROM products p 
    LEFT JOIN product_test pt ON p.id = pt.product_id 
    GROUP BY p.id 
    ORDER BY p.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$allTests = $conn->query("
    SELECT t.*, d.name as discipline_name 
    FROM tests t 
    LEFT JOIN disciplines d ON t.discipline_id = d.id 
    ORDER BY d.name, t.name
")->fetchAll(PDO::FETCH_ASSOC);

$mappingsStmt = $conn->query("
    SELECT p.id as product_id, p.name as product_name, t.id as test_id, t.name as test_name, d.name as disc_name
    FROM product_test pt
    JOIN products p ON pt.product_id = p.id
    JOIN tests t ON pt.test_id = t.id
    LEFT JOIN disciplines d ON t.discipline_id = d.id
    ORDER BY p.name, d.name, t.name
");
$mappings = $mappingsStmt->fetchAll(PDO::FETCH_ASSOC);

$totalCategories = count($disciplines);
$totalProducts = count($products);
$totalTestsCount = count($allTests);
$totalMappingsCount = count($mappings);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalog &amp; Mappings - AKC Admin</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom Styles -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        body, .admin-wrapper {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif !important;
        }

        .catalog-container {
            max-width: 1440px;
            margin: 0 auto;
        }

        /* Hero Banner Section */
        .catalog-hero-bar {
            background: linear-gradient(135deg, #1e3a8a 0%, #0d47a1 50%, #0284c7 100%);
            border-radius: 18px;
            padding: 26px 30px;
            color: #ffffff;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            box-shadow: 0 10px 30px rgba(13, 71, 161, 0.18);
            position: relative;
            overflow: hidden;
        }
        .catalog-hero-bar::before {
            content: '';
            position: absolute;
            top: -40px;
            right: -40px;
            width: 220px;
            height: 220px;
            background: radial-gradient(circle, rgba(255,255,255,0.18) 0%, rgba(255,255,255,0) 70%);
            border-radius: 50%;
        }
        .hero-title-group h2 {
            margin: 0 0 6px 0;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.4px;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .hero-title-group p {
            margin: 0;
            font-size: 13.5px;
            color: rgba(255, 255, 255, 0.85);
            font-weight: 500;
        }
        .hero-stat-pills {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .hero-stat-pill {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 14px;
            padding: 10px 18px;
            text-align: right;
            cursor: pointer;
            transition: all 0.25s ease;
        }
        .hero-stat-pill:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }
        .hero-stat-num {
            font-size: 22px;
            font-weight: 800;
            line-height: 1.1;
            color: #ffffff;
        }
        .hero-stat-lbl {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: rgba(255, 255, 255, 0.85);
            font-weight: 600;
        }

        /* Pill Segmented Tab Navigation */
        .catalog-tabs-bar {
            display: inline-flex;
            background: #e2e8f0;
            padding: 5px;
            border-radius: 14px;
            margin-bottom: 24px;
            gap: 6px;
        }
        .catalog-tab-btn {
            padding: 10px 22px;
            border: none;
            background: transparent;
            color: #64748b;
            font-size: 13.5px;
            font-weight: 700;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            white-space: nowrap;
        }
        .catalog-tab-btn i {
            font-size: 14px;
        }
        .catalog-tab-btn .tab-count {
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            background: rgba(100, 116, 139, 0.15);
            color: #475569;
        }
        .catalog-tab-btn.active {
            background: #ffffff;
            color: #0f172a;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
        }
        .catalog-tab-btn.active .tab-count {
            background: #eff6ff;
            color: #2563eb;
        }

        /* Tab Panels */
        .tab-panel {
            display: none;
            animation: modernFadeIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .tab-panel.active {
            display: block;
        }
        @keyframes modernFadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .section-split {
            display: flex;
            flex-direction: column;
            gap: 22px;
        }

        /* Modern Form Card */
        .admin-form-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 24px 26px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            border: 1px solid #e2e8f0;
            position: relative;
        }
        .form-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid #f1f5f9;
        }
        .form-card-header h3 {
            margin: 0;
            font-size: 15.5px;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-card-badge {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            background: #f8fafc;
            padding: 4px 10px;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
        }

        .admin-form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 16px;
            align-items: flex-end;
        }
        .admin-form-group label {
            display: block;
            margin-bottom: 7px;
            font-size: 12.5px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .admin-form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            font-family: inherit;
            font-size: 13.5px;
            color: #0f172a;
            background: #f8fafc;
            box-sizing: border-box;
            transition: all 0.2s ease;
        }
        .admin-form-control:focus {
            outline: none;
            background: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
        }
        .btn-submit {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 11px 20px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            height: 42px;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
        }
        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.35);
        }

        /* Modern Data Table Card */
        .card.table-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            overflow: hidden;
        }
        .card-header-modern {
            padding: 18px 24px;
            background: #ffffff;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
        }
        .header-title-sub h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .search-pill-input {
            position: relative;
            min-width: 240px;
        }
        .search-pill-input i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 13px;
        }
        .search-pill-input input {
            width: 100%;
            padding: 9px 14px 9px 38px;
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            font-size: 13px;
            background: #f8fafc;
            outline: none;
            font-family: inherit;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }
        .search-pill-input input:focus {
            background: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .filter-select {
            padding: 9px 14px;
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            font-size: 13px;
            background: #f8fafc;
            outline: none;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .filter-select:focus {
            border-color: #2563eb;
            background: #fff;
        }
        .btn-reset-filter {
            padding: 9px 14px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            border-radius: 10px;
            font-size: 12.5px;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        .btn-reset-filter:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        /* Modern Table Styles */
        .admin-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .admin-table th {
            background: #f8fafc;
            color: #475569;
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 14px 18px;
            border-bottom: 1.5px solid #e2e8f0;
            text-align: left;
        }
        .admin-table td {
            padding: 14px 18px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13.5px;
            color: #334155;
            vertical-align: middle;
            transition: background 0.15s ease;
        }
        .admin-table tbody tr:hover td {
            background: #f8fafc;
        }
        .code-pill {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            background: #f1f5f9;
            color: #0f172a;
            padding: 3px 8px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            display: inline-block;
        }
        .badge-discipline {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 9px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 700;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #dbeafe;
        }
        .badge-sector {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 700;
            color: #0f172a;
        }
        .badge-sector i {
            color: #0284c7;
            font-size: 12px;
        }
        .btn-action-del {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fee2e2;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        .btn-action-del:hover {
            background: #dc2626;
            color: #ffffff;
            border-color: #dc2626;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(220, 38, 38, 0.25);
        }
        .table-scroll-container {
            overflow-x: auto;
        }

        /* Modern Pagination Controls */
        .table-pagination-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 24px;
            background: #ffffff;
            border-top: 1px solid #f1f5f9;
            flex-wrap: wrap;
            gap: 14px;
        }
        .pagination-info {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
        }
        .pagination-info strong {
            color: #0f172a;
        }
        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .pagination-btn {
            min-width: 36px;
            height: 36px;
            padding: 0 10px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: #475569;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        .pagination-btn:hover:not(:disabled) {
            background: #f1f5f9;
            color: #0f172a;
            border-color: #cbd5e1;
        }
        .pagination-btn.active {
            background: #2563eb;
            color: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.28);
        }
        .pagination-btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
            background: #f8fafc;
        }
        .page-size-selector {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #64748b;
        }
        .page-size-select {
            padding: 6px 10px;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            background: #f8fafc;
            font-size: 12.5px;
            font-weight: 600;
            color: #0f172a;
            cursor: pointer;
            outline: none;
        }
    </style>
</head>
<body class="admin-body-bg">

<div class="admin-wrapper">
    <?php 
    $current_page = 'catalog';
    $page_title = 'Catalog & Mappings';
    include 'includes/admin_sidebar.php'; 
    ?>

    <!-- Right Content Area -->
    <main class="admin-main">
        <?php include 'includes/admin_topbar.php'; ?>

        <div class="admin-content">
            <div class="catalog-container">

                <!-- Alert Message -->
                <?php if (!empty($message)): ?>
                    <div class="alert <?php echo $messageType; ?>" style="padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; <?php echo $messageType === 'success' ? 'background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;' : 'background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;'; ?>">
                        <i class="fa-solid <?php echo $messageType === 'success' ? 'fa-circle-check' : 'fa-circle-xmark'; ?>"></i>
                        <span style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($message); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Premium Hero Section -->
                <div class="catalog-hero-bar">
                    <div class="hero-title-group">
                        <h2><i class="fa-solid fa-layer-group"></i> Catalog &amp; Testing Mappings</h2>
                        <p>Central directory of scientific tests, accreditations, and industry product sector mappings</p>
                    </div>
                    <div class="hero-stat-pills">
                        <div class="hero-stat-pill" onclick="switchCatalogTab('tab-tests')">
                            <div class="hero-stat-num"><?php echo $totalTestsCount; ?></div>
                            <div class="hero-stat-lbl">Master Tests</div>
                        </div>
                        <div class="hero-stat-pill" onclick="switchCatalogTab('tab-mappings')">
                            <div class="hero-stat-num"><?php echo $totalMappingsCount; ?></div>
                            <div class="hero-stat-lbl">Mapped Tests</div>
                        </div>
                    </div>
                </div>

                <!-- Modern Segmented Tab Navigation -->
                <div class="catalog-tabs-bar">
                    <button class="catalog-tab-btn active" id="btn-tab-tests" onclick="switchCatalogTab('tab-tests')">
                        <i class="fa-solid fa-flask" style="color: #10b981;"></i> 
                        Tests Directory
                        <span class="tab-count"><?php echo $totalTestsCount; ?></span>
                    </button>
                    <button class="catalog-tab-btn" id="btn-tab-mappings" onclick="switchCatalogTab('tab-mappings')">
                        <i class="fa-solid fa-link" style="color: #f59e0b;"></i> 
                        Test Mappings
                        <span class="tab-count"><?php echo $totalMappingsCount; ?></span>
                    </button>
                </div>

                <!-- ========================================== -->
                <!-- TAB 1: TESTS DIRECTORY                     -->
                <!-- ========================================== -->
                <div class="tab-panel active" id="tab-tests">
                    <div class="section-split">
                        <!-- Top: Add Test Form (Horizontal Form Bar) -->
                        <div class="admin-form-card">
                            <div class="form-card-header">
                                <h3><i class="fa-solid fa-circle-plus" style="color: #10b981; font-size: 18px;"></i> Add New Test to Library</h3>
                                <span class="form-card-badge"><i class="fa-solid fa-database" style="color: #10b981;"></i> Master Database</span>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="add_test" value="1">
                                <div class="admin-form-row">
                                    <div class="admin-form-group">
                                        <label>Testing Category <span style="color:#ef4444;">*</span></label>
                                        <select name="discipline_id" class="admin-form-control" required>
                                            <option value="">-- Select Category --</option>
                                            <?php foreach($disciplines as $d): ?>
                                                <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="admin-form-group">
                                        <label>Test Name <span style="color:#ef4444;">*</span></label>
                                        <input type="text" name="name" class="admin-form-control" required placeholder="e.g. Viscosity Measurement">
                                    </div>
                                    <div class="admin-form-group">
                                        <label>Standard / Protocol</label>
                                        <input type="text" name="standards" class="admin-form-control" placeholder="e.g. ISO 1628 / ASTM D445">
                                    </div>
                                    <div class="admin-form-group">
                                        <label>Description (Optional)</label>
                                        <input type="text" name="description" class="admin-form-control" placeholder="Brief scope or method summary...">
                                    </div>
                                    <div class="admin-form-group" style="margin-bottom: 0;">
                                        <button type="submit" class="btn-submit">
                                            <i class="fa-solid fa-plus"></i> Save Test
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Right: Tests Table with Search & Filter -->
                        <div class="card table-card" style="margin-top: 0;">
                            <div class="card-header-modern">
                                <div class="header-title-sub">
                                    <h3><i class="fa-solid fa-flask" style="color: #10b981; font-size: 18px;"></i> All Configured Tests</h3>
                                    <span id="tests-count-badge" class="badge-discipline" style="margin-top: 5px;"><?php echo count($allTests); ?> Total Tests</span>
                                </div>

                                <!-- Search & Filters -->
                                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                    <div class="search-pill-input">
                                        <i class="fa-solid fa-magnifying-glass"></i>
                                        <input type="text" id="testSearchInput" placeholder="Search test or standard..." onkeyup="filterTestsTable()">
                                    </div>

                                    <select id="testCategoryFilter" onchange="filterTestsTable()" class="filter-select">
                                        <option value="">All Categories</option>
                                        <?php foreach ($disciplines as $d): ?>
                                            <option value="<?php echo htmlspecialchars(strtolower($d['name'])); ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                    <button type="button" onclick="resetTestsFilter()" title="Reset Filters" class="btn-reset-filter">
                                        <i class="fa-solid fa-rotate-left"></i> Reset
                                    </button>
                                </div>
                            </div>

                            <div class="table-responsive table-scroll-container">
                                <table class="admin-table" id="testsDirectoryTable">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px; text-align: center;">S.No</th>
                                            <th style="width: 60px;">ID</th>
                                            <th>Category</th>
                                            <th>Test Name</th>
                                            <th>Standard / Protocol</th>
                                            <th>Description</th>
                                            <th style="text-align: center; width: 85px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($allTests)): ?>
                                            <?php $sno = 1; foreach($allTests as $t): ?>
                                            <tr class="test-table-row" data-category="<?php echo htmlspecialchars(strtolower($t['discipline_name'] ?? 'general')); ?>" data-name="<?php echo htmlspecialchars(strtolower($t['name'])); ?>" data-standard="<?php echo htmlspecialchars(strtolower($t['standards'] ?? '')); ?>">
                                                <td style="text-align: center; font-weight: 700; color: #94a3b8;" class="test-sno"><?php echo $sno++; ?></td>
                                                <td><span class="code-pill">#<?php echo $t['id']; ?></span></td>
                                                <td><span class="badge-discipline"><?php echo htmlspecialchars($t['discipline_name'] ?? 'General'); ?></span></td>
                                                <td><strong style="color: #0f172a; font-weight: 700;"><?php echo htmlspecialchars($t['name']); ?></strong></td>
                                                <td><span class="code-pill"><?php echo htmlspecialchars($t['standards'] ?: 'N/A'); ?></span></td>
                                                <td style="max-width: 280px; font-size: 13px; color: #64748b; line-height: 1.5;"><?php echo htmlspecialchars($t['description'] ?: 'No description'); ?></td>
                                                <td style="text-align: center;">
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Permanently delete test: <?php echo addslashes(htmlspecialchars($t['name'])); ?>?');">
                                                        <input type="hidden" name="delete_test" value="1">
                                                        <input type="hidden" name="test_id" value="<?php echo $t['id']; ?>">
                                                        <button type="submit" class="btn-action-del" title="Delete Test">
                                                            <i class="fa-solid fa-trash-can"></i> Delete
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr id="noTestsRow"><td colspan="7" class="empty-state">No tests found in the database.</td></tr>
                                        <?php endif; ?>
                                        <tr id="noResultsRow" style="display: none;"><td colspan="7" class="empty-state">No tests match your search criteria.</td></tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination Bar for Tests -->
                            <div class="table-pagination-bar" id="testsPaginationBar">
                                <div class="page-size-selector">
                                    <span>Show</span>
                                    <select id="testsPageSize" class="page-size-select" onchange="changeTestsPageSize(this.value)">
                                        <option value="10">10</option>
                                        <option value="20" selected>20</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                    </select>
                                    <span>per page</span>
                                </div>

                                <div class="pagination-info" id="testsPaginationInfo">
                                    Showing <strong>1</strong> to <strong>20</strong> of <strong><?php echo count($allTests); ?></strong> tests
                                </div>

                                <div class="pagination-controls" id="testsPaginationControls">
                                    <!-- Rendered dynamically via JS -->
                                </div>
                            </div>
                        </div>
                <div class="tab-panel" id="tab-mappings">
                    <div class="section-split">
                        <!-- Top: Map Test Form -->
                        <div class="admin-form-card">
                            <div class="form-card-header">
                                <h3><i class="fa-solid fa-link" style="color: #f59e0b; font-size: 18px;"></i> Link Test to Product Sector</h3>
                                <span class="form-card-badge"><i class="fa-solid fa-diagram-project" style="color: #f59e0b;"></i> Front-End Mapping</span>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="map_test" value="1">
                                <div class="admin-form-row">
                                    <div class="admin-form-group" style="flex: 1.2;">
                                        <label>Select Product Sector <span style="color:#ef4444;">*</span></label>
                                        <select name="product_id" class="admin-form-control" required>
                                            <option value="">-- Select Product --</option>
                                            <?php foreach($products as $p): ?>
                                                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="admin-form-group" style="flex: 1.5;">
                                        <label>Select Test <span style="color:#ef4444;">*</span></label>
                                        <select name="test_id" class="admin-form-control" required>
                                            <option value="">-- Select Test --</option>
                                            <?php foreach($allTests as $t): ?>
                                                <option value="<?php echo $t['id']; ?>">[<?php echo htmlspecialchars($t['discipline_name'] ?? 'General'); ?>] <?php echo htmlspecialchars($t['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="admin-form-group" style="flex: 0.8; margin-bottom: 0;">
                                        <button type="submit" class="btn-submit" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); box-shadow: 0 4px 12px rgba(245, 158, 11, 0.25);">
                                            <i class="fa-solid fa-link"></i> Link Test
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Right: Active Mappings Table with Search & Filter -->
                        <div class="card table-card" style="margin-top: 0;">
                            <div class="card-header-modern">
                                <div class="header-title-sub">
                                    <h3><i class="fa-solid fa-list-check" style="color: #f59e0b; font-size: 18px;"></i> Active Front-End Mappings</h3>
                                    <span id="mappings-count-badge" class="badge-discipline" style="background: #fffbeb; color: #b45309; border-color: #fef3c7; margin-top: 5px;"><?php echo count($mappings); ?> Active Links</span>
                                </div>

                                <!-- Filter by product -->
                                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                    <select id="mappingProductFilter" onchange="filterMappingsTable()" class="filter-select" style="max-width: 250px;">
                                        <option value="">All Product Sectors</option>
                                        <?php foreach ($products as $p): ?>
                                            <option value="<?php echo htmlspecialchars(strtolower($p['name'])); ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                    <div class="search-pill-input">
                                        <i class="fa-solid fa-magnifying-glass"></i>
                                        <input type="text" id="mappingSearchInput" placeholder="Search test name..." onkeyup="filterMappingsTable()">
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive table-scroll-container">
                                <table class="admin-table" id="mappingsDirectoryTable">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px; text-align: center;">S.No</th>
                                            <th>Product Sector</th>
                                            <th>Category</th>
                                            <th>Test Name</th>
                                            <th style="text-align: center; width: 90px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($mappings)): ?>
                                            <?php $msno = 1; foreach($mappings as $m): ?>
                                            <tr class="mapping-table-row" data-product="<?php echo htmlspecialchars(strtolower($m['product_name'])); ?>" data-test="<?php echo htmlspecialchars(strtolower($m['test_name'])); ?>">
                                                <td style="text-align: center; font-weight: 700; color: #94a3b8;" class="mapping-sno"><?php echo $msno++; ?></td>
                                                <td><span class="badge-sector"><i class="fa-solid fa-layer-group"></i> <?php echo htmlspecialchars($m['product_name']); ?></span></td>
                                                <td><span class="badge-discipline"><?php echo htmlspecialchars($m['disc_name'] ?? 'General'); ?></span></td>
                                                <td><strong style="color: #0f172a; font-weight: 600;"><?php echo htmlspecialchars($m['test_name']); ?></strong></td>
                                                <td style="text-align: center;">
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Unmap this test from <?php echo addslashes(htmlspecialchars($m['product_name'])); ?>?');">
                                                        <input type="hidden" name="unmap_test" value="1">
                                                        <input type="hidden" name="product_id" value="<?php echo $m['product_id']; ?>">
                                                        <input type="hidden" name="test_id" value="<?php echo $m['test_id']; ?>">
                                                        <button type="submit" class="btn-action-del" title="Unmap Test">
                                                            <i class="fa-solid fa-link-slash"></i> Unmap
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="5" class="empty-state">No product-test mappings found.</td></tr>
                                        <?php endif; ?>
                                        <tr id="noMappingResultsRow" style="display: none;"><td colspan="5" class="empty-state">No mappings match your filter criteria.</td></tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination Bar for Mappings -->
                            <div class="table-pagination-bar" id="mappingsPaginationBar">
                                <div class="page-size-selector">
                                    <span>Show</span>
                                    <select id="mappingsPageSize" class="page-size-select" onchange="changeMappingsPageSize(this.value)">
                                        <option value="15">15</option>
                                        <option value="25" selected>25</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                    </select>
                                    <span>per page</span>
                                </div>

                                <div class="pagination-info" id="mappingsPaginationInfo">
                                    Showing <strong>1</strong> to <strong>25</strong> of <strong><?php echo count($mappings); ?></strong> mappings
                                </div>

                                <div class="pagination-controls" id="mappingsPaginationControls">
                                    <!-- Rendered dynamically via JS -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>
</div>

<script>
// Tab Switching
function switchCatalogTab(tabId) {
    document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.remove('active'));
    document.querySelectorAll('.catalog-tab-btn').forEach(btn => btn.classList.remove('active'));
    
    const targetPanel = document.getElementById(tabId);
    if (targetPanel) targetPanel.classList.add('active');

    const targetBtn = document.getElementById('btn-' + tabId);
    if (targetBtn) targetBtn.classList.add('active');

    // Save in sessionStorage so page reload stays on the same tab
    sessionStorage.setItem('active_catalog_tab', tabId);
}

// Restore saved tab on reload
document.addEventListener('DOMContentLoaded', function() {
    const savedTab = sessionStorage.getItem('active_catalog_tab');
    if (savedTab && document.getElementById(savedTab)) {
        switchCatalogTab(savedTab);
    }
});

// ==========================================
// PAGINATION & FILTER LOGIC: TESTS DIRECTORY
// ==========================================
let currentTestsPage = 1;
let testsPageSize = 20;
let matchingTestRows = [];

function initTestsPagination() {
    filterTestsTable();
}

function changeTestsPageSize(size) {
    testsPageSize = parseInt(size);
    currentTestsPage = 1;
    renderTestsPage();
}

function goToTestsPage(page) {
    const totalPages = Math.ceil(matchingTestRows.length / testsPageSize) || 1;
    if (page < 1) page = 1;
    if (page > totalPages) page = totalPages;
    currentTestsPage = page;
    renderTestsPage();
}

function renderTestsPage() {
    const allRows = document.querySelectorAll('.test-table-row');
    allRows.forEach(row => row.style.display = 'none');

    const total = matchingTestRows.length;
    const totalPages = Math.ceil(total / testsPageSize) || 1;
    if (currentTestsPage > totalPages) currentTestsPage = totalPages;

    const startIndex = (currentTestsPage - 1) * testsPageSize;
    const endIndex = Math.min(startIndex + testsPageSize, total);

    for (let i = startIndex; i < endIndex; i++) {
        matchingTestRows[i].style.display = '';
        const snoEl = matchingTestRows[i].querySelector('.test-sno');
        if (snoEl) snoEl.textContent = i + 1;
    }

    const noResultsRow = document.getElementById('noResultsRow');
    if (noResultsRow) {
        noResultsRow.style.display = (total === 0 && allRows.length > 0) ? '' : 'none';
    }

    // Info Text
    const infoEl = document.getElementById('testsPaginationInfo');
    if (infoEl) {
        if (total === 0) {
            infoEl.innerHTML = 'Showing <strong>0</strong> tests';
        } else {
            infoEl.innerHTML = `Showing <strong>${startIndex + 1}</strong> to <strong>${endIndex}</strong> of <strong>${total}</strong> tests`;
        }
    }

    // Controls
    const controlsEl = document.getElementById('testsPaginationControls');
    if (controlsEl) {
        let html = '';
        html += `<button type="button" class="pagination-btn" onclick="goToTestsPage(${currentTestsPage - 1})" ${currentTestsPage <= 1 ? 'disabled' : ''}><i class="fa-solid fa-chevron-left"></i></button>`;

        let startP = Math.max(1, currentTestsPage - 2);
        let endP = Math.min(totalPages, currentTestsPage + 2);

        if (startP > 1) {
            html += `<button type="button" class="pagination-btn" onclick="goToTestsPage(1)">1</button>`;
            if (startP > 2) html += `<span style="padding: 0 4px; color: #94a3b8;">...</span>`;
        }

        for (let p = startP; p <= endP; p++) {
            html += `<button type="button" class="pagination-btn ${p === currentTestsPage ? 'active' : ''}" onclick="goToTestsPage(${p})">${p}</button>`;
        }

        if (endP < totalPages) {
            if (endP < totalPages - 1) html += `<span style="padding: 0 4px; color: #94a3b8;">...</span>`;
            html += `<button type="button" class="pagination-btn" onclick="goToTestsPage(${totalPages})">${totalPages}</button>`;
        }

        html += `<button type="button" class="pagination-btn" onclick="goToTestsPage(${currentTestsPage + 1})" ${currentTestsPage >= totalPages ? 'disabled' : ''}><i class="fa-solid fa-chevron-right"></i></button>`;
        controlsEl.innerHTML = html;
    }
}

// Filter Tests Table
function filterTestsTable() {
    const searchInput = document.getElementById('testSearchInput').value.toLowerCase().trim();
    const categoryFilter = document.getElementById('testCategoryFilter').value.toLowerCase().trim();
    const rows = document.querySelectorAll('.test-table-row');
    const countBadge = document.getElementById('tests-count-badge');
    
    matchingTestRows = [];

    rows.forEach(row => {
        const cat = row.getAttribute('data-category') || '';
        const name = row.getAttribute('data-name') || '';
        const std = row.getAttribute('data-standard') || '';

        const matchesCat = !categoryFilter || cat.includes(categoryFilter);
        const matchesSearch = !searchInput || name.includes(searchInput) || std.includes(searchInput);

        if (matchesCat && matchesSearch) {
            matchingTestRows.push(row);
        }
    });

    if (countBadge) {
        if (searchInput || categoryFilter) {
            countBadge.textContent = `${matchingTestRows.length} of ${rows.length} Tests`;
        } else {
            countBadge.textContent = `${rows.length} Total Tests`;
        }
    }

    currentTestsPage = 1;
    renderTestsPage();
}

function resetTestsFilter() {
    document.getElementById('testSearchInput').value = '';
    document.getElementById('testCategoryFilter').value = '';
    filterTestsTable();
}

// ==========================================
// PAGINATION & FILTER LOGIC: TEST MAPPINGS
// ==========================================
let currentMappingsPage = 1;
let mappingsPageSize = 25;
let matchingMappingRows = [];

function initMappingsPagination() {
    filterMappingsTable();
}

function changeMappingsPageSize(size) {
    mappingsPageSize = parseInt(size);
    currentMappingsPage = 1;
    renderMappingsPage();
}

function goToMappingsPage(page) {
    const totalPages = Math.ceil(matchingMappingRows.length / mappingsPageSize) || 1;
    if (page < 1) page = 1;
    if (page > totalPages) page = totalPages;
    currentMappingsPage = page;
    renderMappingsPage();
}

function renderMappingsPage() {
    const allRows = document.querySelectorAll('.mapping-table-row');
    allRows.forEach(row => row.style.display = 'none');

    const total = matchingMappingRows.length;
    const totalPages = Math.ceil(total / mappingsPageSize) || 1;
    if (currentMappingsPage > totalPages) currentMappingsPage = totalPages;

    const startIndex = (currentMappingsPage - 1) * mappingsPageSize;
    const endIndex = Math.min(startIndex + mappingsPageSize, total);

    for (let i = startIndex; i < endIndex; i++) {
        matchingMappingRows[i].style.display = '';
        const snoEl = matchingMappingRows[i].querySelector('.mapping-sno');
        if (snoEl) snoEl.textContent = i + 1;
    }

    const noResultsRow = document.getElementById('noMappingResultsRow');
    if (noResultsRow) {
        noResultsRow.style.display = (total === 0 && allRows.length > 0) ? '' : 'none';
    }

    // Info Text
    const infoEl = document.getElementById('mappingsPaginationInfo');
    if (infoEl) {
        if (total === 0) {
            infoEl.innerHTML = 'Showing <strong>0</strong> mappings';
        } else {
            infoEl.innerHTML = `Showing <strong>${startIndex + 1}</strong> to <strong>${endIndex}</strong> of <strong>${total}</strong> mappings`;
        }
    }

    // Controls
    const controlsEl = document.getElementById('mappingsPaginationControls');
    if (controlsEl) {
        let html = '';
        html += `<button type="button" class="pagination-btn" onclick="goToMappingsPage(${currentMappingsPage - 1})" ${currentMappingsPage <= 1 ? 'disabled' : ''}><i class="fa-solid fa-chevron-left"></i></button>`;

        let startP = Math.max(1, currentMappingsPage - 2);
        let endP = Math.min(totalPages, currentMappingsPage + 2);

        if (startP > 1) {
            html += `<button type="button" class="pagination-btn" onclick="goToMappingsPage(1)">1</button>`;
            if (startP > 2) html += `<span style="padding: 0 4px; color: #94a3b8;">...</span>`;
        }

        for (let p = startP; p <= endP; p++) {
            html += `<button type="button" class="pagination-btn ${p === currentMappingsPage ? 'active' : ''}" onclick="goToMappingsPage(${p})">${p}</button>`;
        }

        if (endP < totalPages) {
            if (endP < totalPages - 1) html += `<span style="padding: 0 4px; color: #94a3b8;">...</span>`;
            html += `<button type="button" class="pagination-btn" onclick="goToMappingsPage(${totalPages})">${totalPages}</button>`;
        }

        html += `<button type="button" class="pagination-btn" onclick="goToMappingsPage(${currentMappingsPage + 1})" ${currentMappingsPage >= totalPages ? 'disabled' : ''}><i class="fa-solid fa-chevron-right"></i></button>`;
        controlsEl.innerHTML = html;
    }
}

// Filter Mappings Table
function filterMappingsTable() {
    const productFilter = document.getElementById('mappingProductFilter').value.toLowerCase().trim();
    const searchInput = document.getElementById('mappingSearchInput').value.toLowerCase().trim();
    const rows = document.querySelectorAll('.mapping-table-row');
    const countBadge = document.getElementById('mappings-count-badge');

    matchingMappingRows = [];

    rows.forEach(row => {
        const prod = row.getAttribute('data-product') || '';
        const test = row.getAttribute('data-test') || '';

        const matchesProd = !productFilter || prod.includes(productFilter);
        const matchesSearch = !searchInput || test.includes(searchInput);

        if (matchesProd && matchesSearch) {
            matchingMappingRows.push(row);
        }
    });

    if (countBadge) {
        if (productFilter || searchInput) {
            countBadge.textContent = `${matchingMappingRows.length} of ${rows.length} Mappings`;
        } else {
            countBadge.textContent = `${rows.length} Total Mappings`;
        }
    }

    currentMappingsPage = 1;
    renderMappingsPage();
}

// Init on Load
document.addEventListener('DOMContentLoaded', function() {
    initTestsPagination();
    initMappingsPagination();
});
</script>

</body>
</html>
