# Milk Market: Technical Project Documentation

This document provides a comprehensive technical walkthrough of the **Milk Market** social media platform. It covers the system architecture, file structure, code logic, and detailed API descriptions.

---

## 1. System Architecture

The project is built using a **traditional LAMP stack** (Linux/Apache/MySQL/PHP) but modernized with several external cloud integrations and real-time JavaScript features.

*   **Server-Side**: PHP (7.4+) using the `mysqli` extension for database interactions and `cURL` for external API requests.
*   **Database**: MySQL, normalized into 7 core tables (`users`, `posts`, `post_likes`, `comments`, `messages`, `notifications`, `friend_requests`).
*   **Media Storage**: Decentralized storage via **Cloudinary**. Local server storage is used only as a temporary buffer for Direct Message attachments.
*   **Frontend**: Vanilla HTML5, CSS3 (with CSS Variables for theme consistency), and asynchronous JavaScript (Fetch API) for real-time updates.

---

## 2. File-by-File Breakdown

### Root Directory
*   [index.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/index.php): The entry point. Redirects unauthenticated traffic to the login page.
*   [database.sql](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/database.sql): The base schema for setting up the MySQL database.
*   `database_updates_*.sql`: Incremental migration scripts that added features like Google Auth, Real-time messaging, and Image storage.

### [config/](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/config/cloudinary_helpers.php#9-16) (Logic and Credentials)
*   [db.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/config/db.php): Centralized database connection logic. Sets the timezone and establishes the `$conn` object.
*   [cloudinary.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/config/cloudinary.php) / [google_oauth.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/config/google_oauth.php) / [openweather.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/config/openweather.php): Configuration files containing secret keys and API credentials.
*   [cloudinary_helpers.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/config/cloudinary_helpers.php): A robust wrapper for the Cloudinary API.
    ```php
    function cloudinary_upload_image($localPath, $originalName, $folder, $prefix) {
        // Builds a signed request using sha1 and uploads via cURL to Cloudinary
    }
    ```

### `pages/` (UI and Form Processing)
*   [login.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/pages/login.php) / [register.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/pages/register.php): Handle local authentication and Google OAuth links. [register.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/pages/register.php) enforces strong password regex and hashes passwords using `PASSWORD_DEFAULT`.
*   [home.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/pages/home.php): The main application hub. Queries the feed, fetches weather data, and renders the "Composer" area.
*   [create_post.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/pages/create_post.php) / [edit_post.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/pages/edit_post.php) / [delete_post.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/pages/delete_post.php): Form-based action endpoints. They process data and use `header("Location: home.php")` to refresh the feed.

### `api/` (Asynchronous JSON Endpoints)
*   **Retrieval**: [get_posts.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/api/get_posts.php), [get_notifications.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/api/get_notifications.php), [get_messages.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/api/get_messages.php).
*   **Actions**: [send_message.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/api/send_message.php), [send_friend_request.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/api/send_friend_request.php), `toggle_like.php`.
*   **Response Format**: All return `{"status": "success", "data": [...]}` or `{"status": "error", "message": "..."}`.

### `assets/` (Static Assets and Logic)
*   [style.css](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/assets/style.css): Modern styling with a grid-based layout and "Milk Market" branding.
*   [realtime_feed.js](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/assets/realtime_feed.js): Polls [api/get_feed_updates.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/api/get_feed_updates.php) every few seconds to refresh like counts and inject new comments without reloading the page.
*   `composer_preview.js`: Provides a client-side preview of a post (including images) before the user confirms the upload.

---

## 3. API Documentation (Detailed)

### External Third-Party APIs

| Service | File | Role |
| :--- | :--- | :--- |
| **Cloudinary** | [config/cloudinary.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/config/cloudinary.php) | **Media Storage**: All posts and profile images are uploaded here. Uses a signed API request for security. |
| **Google OAuth** | [config/google_oauth.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/config/google_oauth.php) | **SSO Authentication**: Allows users to log in with their Google Account. Managed via [google_login.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/pages/google_login.php) and [google_callback.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/pages/google_callback.php). |
| **OpenWeather** | [config/openweather.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/config/openweather.php) | **Data Fetching**: Fetches live weather for the sidebar based on the location text in the user's latest post. |

### Internal Action APIs (JSON)

#### Example: [api/send_message.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/api/send_message.php)
Used for real-time messaging between users.
```php
// Code Logic (Simplified)
$sql = "INSERT INTO messages (sender_id, receiver_id, message_text, attachment_path) VALUES (?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "iiss", $senderId, $receiverId, $text, $path);
mysqli_stmt_execute($stmt);
// Also inserts a notification for the receiver
```

---

## 4. Key Functional Flows

1.  **Image Uploading**:
    *   User selects a file in the "Composer" on [home.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/pages/home.php).
    *   Form is submitted to [create_post.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/pages/create_post.php).
    *   [create_post.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/pages/create_post.php) calls [cloudinary_upload_image()](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/config/cloudinary_helpers.php#37-99) in [cloudinary_helpers.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/config/cloudinary_helpers.php).
    *   The helper uploads the file to Cloudinary and returns a `secure_url`.
    *   The `secure_url` is saved in the MySQL `posts` table.

2.  **Real-Time Notifications**:
    *   [assets/realtime_notifications.js](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/assets/realtime_notifications.js) runs a background timer.
    *   Every 10 seconds, it calls [api/get_unread_counts.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/api/get_unread_counts.php).
    *   The response updates the red badge in the header without a page refresh.

3.  **Product Marketplace**:
    *   Posts can contain "Structured Product Data" (Price, Quantity, Location).
    *   If these fields are filled, [home.php](file:///d:/Gabby/Download/School/CPE/4th_year/New%20folder/Web%20design/xampp/htdocs/SocMedia-main/pages/home.php) renders the post inside a specialized `product-card-box` style.
