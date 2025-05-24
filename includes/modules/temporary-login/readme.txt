=== Temporary Login ===
Contributors: alialv, wpengine
Tags: temporary login, user, admin, access, security, temporary user, temporary access, secure login
Requires at least: 5.0
Tested up to: 6.3
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Grant temporary, secure access to your WordPress site without creating permanent user accounts.

== Description ==

The Temporary Login plugin allows administrators to create temporary login links for users. This is perfect for granting developers, support staff, or collaborators access to your site for a limited time without the need to create full user accounts.

Features:
* Create unlimited temporary login links.
* Set expiration dates for temporary access.
* Assign user roles to temporary logins.
* Securely managed - temporary users are automatically removed after expiry.
* Easy to use interface integrated into the WordPress admin area.
* Control plugin activation status directly from the plugin's admin page.

== Installation ==

1. Upload the `temporary-login` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to the 'Temporary Login' menu in your WordPress admin sidebar to start creating temporary logins.

== Frequently Asked Questions ==

= How do I create a temporary login? =
Go to the 'Temporary Login' page in your admin dashboard. Fill in the required details like user role and expiration time, then click "Create New". A unique access link will be generated.

= What happens when a temporary login expires? =
The temporary login link will no longer work, and the associated temporary user data is cleaned up.

= Can I delete a temporary login before it expires? =
Yes, you can delete any active temporary login from the management table on the 'Temporary Login' admin page.

= Is this plugin secure? =
Yes, temporary logins are generated with unique, strong tokens. Access is automatically revoked after the set expiration time.

== Screenshots ==
1. The main admin page for managing temporary logins.
2. The form for creating a new temporary login.
3. The plugin inactive page with an option to activate it.

== Changelog ==

= 1.0.0 - YYYY-MM-DD =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release of the plugin.

== REST API ==

This module provides a REST API endpoint for generating temporary login links programmatically.

=== Generate Temporary Login Link ===

*   **Endpoint:** `POST /wp-json/alvobot-pro/v1/temporary-login/generate`
*   **Authentication:** API Key. Provide the key via the `X-AlvoBot-API-Key` request header or as an `api_key` field in the JSON request body. API keys can be managed in the Temporary Login module settings within the AlvoBot Pro dashboard.
*   **Request Body (JSON):**
    *   `api_key` (string): (Required if not in header) Your API Key.
    *   `duration_value` (integer, optional): The numerical value for the link's duration.
    *   `duration_unit` (string, optional): The unit for the duration. Accepts 'hours' or 'days'. Defaults to 'days' if `duration_value` is present. If both `duration_value` and `duration_unit` are omitted, the plugin's default duration (e.g., 7 or 14 days) will be used.
    *   `user_role` (string, optional): The WordPress role for the temporary user (e.g., 'administrator', 'editor'). Defaults to 'administrator'.
    *   `reassign_to_user_id` (integer, optional): User ID to which the temporary user's posts will be reassigned upon deletion. If not provided, content created by the temporary user might be deleted or reassigned to the site admin based on plugin settings.
*   **Success Response (200 OK):**
    ```json
    {
        "success": true,
        "login_url": "https://your-site.com/wp-admin/?temp-login-token=...",
        "expires_at": "YYYY-MM-DD HH:MM:SS" // Expiration timestamp in UTC
    }
    ```
*   **Error Responses:**
    *   `400 Bad Request`: For invalid parameters (e.g., bad `duration_unit`).
        ```json
        {
            "success": false,
            "error_code": "rest_invalid_param", // Or a more specific code
            "message": "Invalid parameter(s): [parameter name]."
        }
        ```
    *   `401 Unauthorized`: If the API key is missing.
        ```json
        {
            "success": false,
            "error_code": "rest_forbidden_api_key_missing",
            "message": "API Key is missing."
        }
        ```
    *   `403 Forbidden`: If the API key is invalid.
        ```json
        {
            "success": false,
            "error_code": "rest_forbidden_api_key_invalid",
            "message": "Invalid API Key."
        }
        ```
    *   `500 Internal Server Error`: If user generation or link creation fails for other reasons.
        ```json
        {
            "success": false,
            "error_code": "user_generation_failed", // Or "link_generation_failed"
            "message": "Error message describing the failure."
        }
        ```
