# Simple Social Media Starter

This project is a beginner-friendly starter for a small social media page using PHP and SQL Server.

## Current files

- `index.php` redirects to the login page
- `config/db.php` contains the SQL Server connection
- `pages/register.php` is the registration form
- `pages/login.php` is the login form
- `pages/home.php` is the home/feed page
- `pages/create_post.php` is the post handler placeholder
- `pages/logout.php` logs the user out
- `assets/style.css` contains the basic design
- `database.sql` creates the database tables

## Next step

Update `config/db.php` with your SQL Server connection details, then test:

- register a user
- log in
- create a post
- confirm the post appears on the home page

## Environment variables

API credentials are read from environment variables so secrets are not committed to the project:

- `CLOUDINARY_CLOUD_NAME`
- `CLOUDINARY_API_KEY`
- `CLOUDINARY_API_SECRET`
- `OPENWEATHER_API_KEY`
- `GOOGLE_CLIENT_ID`
- `GOOGLE_CLIENT_SECRET`
- `GOOGLE_REDIRECT_URI` (defaults to `http://localhost/socmed/pages/google_callback.php`)

If real keys were committed before, rotate them in Cloudinary, Google Cloud, and OpenWeather before using the app again.
