<?php
/**
 * Create Post Page
 * Handles new post creation, including text content, images (via Cloudinary), 
 * and structured product data for marketplace listings.
 */
session_start();
require_once "../config/db.php";
require_once "../config/cloudinary_helpers.php";

// Debug flag: append ?debug_post=1 to the composer form action to see errors inline
$debugPost = (isset($_GET['debug_post']) && $_GET['debug_post'] === '1');

function handle_post_error($message, $conn = null, $debug = false) {
    if ($debug) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Post error: " . $message . "\n\n";
        if ($conn) {
            echo "MySQL error: " . mysqli_error($conn) . "\n\n";
        }
        echo "\
_FILES: \n";
        if (!empty($_FILES)) {
            print_r(array_map(function($f){
                return [
                    'name'=>$f['name'] ?? null,
                    'error'=>$f['error'] ?? null,
                    'size'=>$f['size'] ?? null,
                    'type'=>$f['type'] ?? null,
                ];
            }, $_FILES));
        } else {
            echo "(no files)\n";
        }
        exit;
    }

    $_SESSION['post_error'] = $message;
    header('Location: home.php');
    exit;
}

// Redirect to login if user is not authenticated
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Retrieve post data from the form
$content = trim($_POST["content"] ?? "");
$productName = trim($_POST["product_name"] ?? "");
$category = trim($_POST["category"] ?? "");
$price = trim($_POST["price"] ?? "");
$quantityAvailable = trim($_POST["quantity_available"] ?? "");
$locationText = trim($_POST["location_text"] ?? "");
$contactNumber = trim($_POST["contact_number"] ?? "");
$imagePath = null;

// Handle image upload if a file was provided
if (isset($_FILES["post_image"]) && $_FILES["post_image"]["error"] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES["post_image"]["error"] === UPLOAD_ERR_OK) {
        $allowedExtensions = ["jpg", "jpeg", "png", "gif", "webp"];
        $fileName = $_FILES["post_image"]["name"];
        $tmpName = $_FILES["post_image"]["tmp_name"];
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Validate allowed file types
        if (in_array($extension, $allowedExtensions, true)) {
            try {
                // Upload to Cloudinary under the 'milk_market/posts' folder
                $uploadResult = cloudinary_upload_image($tmpName, $fileName, 'milk_market/posts', 'post');
                $imagePath = $uploadResult['secure_url'] ?? null;
            } catch (RuntimeException $exception) {
                handle_post_error($exception->getMessage(), $conn, $debugPost);
            }
        } else {
            handle_post_error('Post image must be jpg, jpeg, png, gif, or webp.', $conn, $debugPost);
        }
    } else {
        handle_post_error('Could not upload the selected image.', $conn, $debugPost);
    }
}

$hasStructuredProduct = $productName !== "" || $category !== "" || $price !== "" || $quantityAvailable !== "" || $locationText !== "" || $contactNumber !== "";

if ($content !== "" || $imagePath !== null || $hasStructuredProduct) {
    $sql = "INSERT INTO posts (user_id, content, image_path, product_name, category, price, quantity_available, location_text, contact_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt === false) {
        handle_post_error('Prepare failed: ' . mysqli_error($conn), $conn, $debugPost);
    }

    // Bind to variables (by reference) to avoid "Only variables should be passed" warnings
    $userId = (int)($_SESSION["user_id"] ?? 0);
    $contentVar = $content;
    $imageVar = $imagePath !== null ? $imagePath : "";

    // Use empty strings for optional fields so DB constraints are simpler to satisfy
    $pName = $productName !== "" ? $productName : "";
    $pCat = $category !== "" ? $category : "";
    $pPrice = $price !== "" ? $price : "";
    $pQty = $quantityAvailable !== "" ? $quantityAvailable : "";
    $pLoc = $locationText !== "" ? $locationText : "";
    $pCont = $contactNumber !== "" ? $contactNumber : "";

    $bindOk = mysqli_stmt_bind_param($stmt, "issssssss",
        $userId,
        $contentVar,
        $imageVar,
        $pName,
        $pCat,
        $pPrice,
        $pQty,
        $pLoc,
        $pCont
    );

    if ($bindOk === false) {
        handle_post_error('Bind failed: ' . mysqli_stmt_error($stmt), $conn, $debugPost);
    }

    $insertSuccess = mysqli_stmt_execute($stmt);

    if ($insertSuccess === false) {
        handle_post_error('Insert failed: ' . mysqli_stmt_error($stmt), $conn, $debugPost);
    }

    // Success path: notify other users about the new post
    $postId = mysqli_insert_id($conn);
    $ntMsg = htmlspecialchars($_SESSION['username'] ?? '') . " just published a new post to the community feed!";
    $notifSql = "INSERT INTO notifications (user_id, type, message, related_id) SELECT id, 'post', ?, ? FROM users WHERE id != ?";
    $notifStmt = mysqli_prepare($conn, $notifSql);

    if ($notifStmt !== false) {
        mysqli_stmt_bind_param($notifStmt, "sii", $ntMsg, $postId, $userId);
        mysqli_stmt_execute($notifStmt);
    }
}

header("Location: home.php");
exit;
