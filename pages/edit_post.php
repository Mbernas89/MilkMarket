<?php
/**
 * Edit Post Page
 * Allows users to modify their existing posts and product listings. 
 * Includes owner validation to ensure users only edit their own content.
 */
session_start();
require_once "../config/db.php";

// Redirect to login if user is not authenticated
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Retrieve the post ID from GET or POST parameters
$postId = (int) ($_GET["post_id"] ?? $_POST["post_id"] ?? 0);
$message = "";
$post = null;

// Exit if no valid post ID is provided
if ($postId <= 0) {
    header("Location: home.php");
    exit;
}

// Fetch the post details and verify that it belongs to the logged-in user
$selectSql = "SELECT id, user_id, content, product_name, category, price, quantity_available, location_text, contact_number FROM posts WHERE id = ? AND user_id = ?";
$stmt = mysqli_prepare($conn, $selectSql);
mysqli_stmt_bind_param($stmt, "ii", $postId, $_SESSION["user_id"]);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$post = ($result !== false) ? mysqli_fetch_assoc($result) : null;

// If the post is not found or doesn't belong to the user, redirect back
if (!$post) {
    header("Location: home.php");
    exit;
}

// Handle form submission for updates
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $content = trim($_POST["content"] ?? "");
    $productName = trim($_POST["product_name"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $price = trim($_POST["price"] ?? "");
    $quantityAvailable = trim($_POST["quantity_available"] ?? "");
    $locationText = trim($_POST["location_text"] ?? "");
    $contactNumber = trim($_POST["contact_number"] ?? "");

    // Check if the user is providing either a text body or structured product information
    $hasStructuredProduct = $productName !== "" || $category !== "" || $price !== "" || $quantityAvailable !== "" || $locationText !== "" || $contactNumber !== "";

    if ($content === "" && !$hasStructuredProduct) {
        $message = "Add either post content or product details.";
    } else {
        // Prepare the update query for MySQL
        $updateSql = "UPDATE posts SET content = ?, product_name = ?, category = ?, price = ?, quantity_available = ?, location_text = ?, contact_number = ? WHERE id = ? AND user_id = ?";
        $updStmt = mysqli_prepare($conn, $updateSql);
        
        $pName = $productName !== "" ? $productName : null;
        $pCat = $category !== "" ? $category : null;
        $pPrice = $price !== "" ? $price : null;
        $pQty = $quantityAvailable !== "" ? $quantityAvailable : null;
        $pLoc = $locationText !== "" ? $locationText : null;
        $pCont = $contactNumber !== "" ? $contactNumber : null;
        
        mysqli_stmt_bind_param($updStmt, "sssssssii", 
            $content,
            $pName,
            $pCat,
            $pPrice,
            $pQty,
            $pLoc,
            $pCont,
            $postId,
            $_SESSION["user_id"]
        );
        
        $updateSuccess = mysqli_stmt_execute($updStmt);

        if (!$updateSuccess) {
            $message = "Could not update the post right now.";
        } else {
            header("Location: home.php");
            exit;
        }
    }

    $post["content"] = $content;
    $post["product_name"] = $productName;
    $post["category"] = $category;
    $post["price"] = $price;
    $post["quantity_available"] = $quantityAvailable;
    $post["location_text"] = $locationText;
    $post["contact_number"] = $contactNumber;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Post</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="editor-page">
    <div class="editor-card">
        <span class="login-brand">Milk Market</span>
        <h1>Edit post</h1>
        <p class="login-copy">Update your dairy post or product details, then save it back to your wall.</p>

        <?php if ($message !== ""): ?>
            <p class="message error settings-error"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <form method="post" class="stack-form">
            <input type="hidden" name="post_id" value="<?php echo (int) $postId; ?>">

            <div class="product-grid product-grid-editor">
                <div>
                    <label for="product_name">Product Name</label>
                    <input type="text" id="product_name" name="product_name" value="<?php echo htmlspecialchars($post["product_name"] ?? ""); ?>">
                </div>
                <div>
                    <label for="category">Category</label>
                    <select id="category" name="category">
                        <option value="">Select category</option>
                        <?php foreach (["Fresh Milk", "Flavored Milk", "Yogurt", "Cheese", "Butter", "Dairy Tips"] as $option): ?>
                            <option value="<?php echo htmlspecialchars($option); ?>" <?php echo (($post["category"] ?? "") === $option) ? "selected" : ""; ?>><?php echo htmlspecialchars($option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="price">Price</label>
                    <input type="text" id="price" name="price" value="<?php echo htmlspecialchars($post["price"] ?? ""); ?>" placeholder="e.g. PHP 120 per liter">
                </div>
                <div>
                    <label for="quantity_available">Quantity</label>
                    <input type="text" id="quantity_available" name="quantity_available" value="<?php echo htmlspecialchars($post["quantity_available"] ?? ""); ?>" placeholder="e.g. 20 bottles">
                </div>
                <div>
                    <label for="location_text">Location</label>
                    <input type="text" id="location_text" name="location_text" value="<?php echo htmlspecialchars($post["location_text"] ?? ""); ?>">
                </div>
                <div>
                    <label for="contact_number">Contact Number</label>
                    <input type="text" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($post["contact_number"] ?? ""); ?>">
                </div>
            </div>

            <label for="content">Post content</label>
            <textarea id="content" name="content" rows="8" placeholder="Write your dairy update...\"><?php echo htmlspecialchars($post["content"] ?? ""); ?></textarea>

            <button type="submit" class="primary-button">Save Changes</button>
        </form>

        <p class="switch-link center-link"><a href="home.php">Back to feed</a></p>
    </div>
</body>
</html>
