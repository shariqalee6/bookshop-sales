<?php
require_once 'TimezoneConverter.php';

// Database connection
$servername = "localhost:4306";
$username = "root";
$password = "";
$database = "bookshop-sales";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Read JSON file
$jsonData = file_get_contents("sales_data.json");
$sales = json_decode($jsonData, true);

// Prepare statement for inserting data into database
$insertStmt = $conn->prepare("INSERT INTO sales (customer_name, customer_mail, product_id, product_name, product_price, sale_date, version) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)");

$insertStmt->bind_param("ssisdss", $customer_name, $customer_mail, $product_id, $product_name, $product_price, $sale_date, $version);

foreach ($sales as $sale) {
    $sale_id = $sale['sale_id'];

    // Check if sale_id already exists in database
    $checkQuery = $conn->prepare("SELECT sale_id FROM sales WHERE sale_id = ?");
    $checkQuery->bind_param("i", $sale_id);
    $checkQuery->execute();
    $checkResult = $checkQuery->get_result();

    if ($checkResult->num_rows == 0) {
        $customer_name = $sale['customer_name'];
        $customer_mail = $sale['customer_mail'];
        $product_id = $sale['product_id'];
        $product_name = $sale['product_name'];
        $product_price = $sale['product_price'];
        $sale_date = $sale['sale_date'];
        $version = $sale['version'];

        // Adjust timezone if version is 1.0.17+60 or newer
        $sale_date = TimezoneConverter::convertToBerlinTime($sale_date, $version);

        $insertStmt->execute();
    }
}

$insertStmt->close();

// Filter query
$sql_filter = "SELECT * FROM sales WHERE 1";

// Build dynamic SQL query for filtering
$filters = [];
$types = "";
$params = [];

if (!empty($_GET['customer'])) {
    $filters[] = "customer_name LIKE ?";
    $types .= "s";
    $params[] = "%" . $_GET['customer'] . "%";
}
if (!empty($_GET['product'])) {
    $filters[] = "product_name LIKE ?";
    $types .= "s";
    $params[] = "%" . $_GET['product'] . "%";
}
if (!empty($_GET['price'])) {
    $filters[] = "product_price LIKE ?";
    $types .= "s";
    $params[] = "%" . $_GET['price'] . "%";
}

// Add filters to the SQL query
if (!empty($filters)) {
    $sql_filter .= " AND " . implode(" AND ", $filters);
}

$filterStmt = $conn->prepare($sql_filter);

// Bind parameters
if (!empty($params)) {
    $filterStmt->bind_param($types, ...$params);
}

$filterStmt->execute();
$result = $filterStmt->get_result();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        .container {
            margin-top: 50px;
        }

        .table-responsive {
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1 class="mb-4">Sales Report</h1>

        <form action="" method="GET" class="mb-4">
            <div class="form-row">
                <div class="col">
                    <label for="customer">Customer:</label>
                    <input type="text" id="customer" name="customer" class="form-control"
                        value="<?= htmlspecialchars($_GET['customer'] ?? '') ?>">
                </div>
                <div class="col">
                    <label for="product">Product:</label>
                    <input type="text" id="product" name="product" class="form-control"
                        value="<?= htmlspecialchars($_GET['product'] ?? '') ?>">
                </div>
                <div class="col">
                    <label for="price">Price:</label>
                    <input type="text" id="price" name="price" class="form-control"
                        value="<?= htmlspecialchars($_GET['price'] ?? '') ?>">
                </div>
                <div class="col align-self-end">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="thead-dark">
                    <tr>
                        <th>Sale ID</th>
                        <th>Customer Name</th>
                        <th>Customer Email</th>
                        <th>Product ID</th>
                        <th>Product Name</th>
                        <th>Product Price</th>
                        <th>Sale Date</th>
                        <th>Version</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total_price = 0;
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $total_price += $row['product_price'];
                            echo "<tr>";
                            echo "<td>" . $row['sale_id'] . "</td>";
                            echo "<td>" . htmlspecialchars($row['customer_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['customer_mail']) . "</td>";
                            echo "<td>" . $row['product_id'] . "</td>";
                            echo "<td>" . htmlspecialchars($row['product_name']) . "</td>";
                            echo "<td>" . $row['product_price'] . "</td>";
                            echo "<td>" . $row['sale_date'] . "</td>";
                            echo "<td>" . $row['version'] . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='8'>No results found</td></tr>";
                    }
                    ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5">Total Price</td>
                        <td>
                            <?= $total_price ?>
                        </td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</body>

</html>

<?php
// Close database connection
$conn->close();
?>