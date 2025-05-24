// src/admin/global.ts
// This file provides access to the localized data from WordPress

// Define the interface for the localized data
export interface AlvobotProTemporaryLoginData {
    apiUrl: string;
    rest_nonce: string;
    ajax_nonce: string;
    api_key_nonce: string;
    current_api_key: string;
    page_slug: string;
    text_domain: string;
}

// Access the global object that WordPress created via wp_localize_script
declare global {
    interface Window {
        alvobotProTemporaryLogin: AlvobotProTemporaryLoginData;
    }
}

// Export the localized data to be used in other files
export const alvobotProTemporaryLogin = window.alvobotProTemporaryLogin;
