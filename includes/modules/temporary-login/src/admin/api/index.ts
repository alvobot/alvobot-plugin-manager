// src/admin/api/index.ts
import apiFetch from '@wordpress/api-fetch';
import { TemporaryLoginData, TemporaryLoginSettings } from '../common/types';

// Export all user-related API functions
export * from './users';

const BASE_PATH = '/alvobot-pro/v1/temporary-login'; // Updated BASE_PATH

export const getTemporaryLogins = (): Promise<TemporaryLoginData[]> => {
    return apiFetch({ path: `${BASE_PATH}/logins` });
};

export const createTemporaryLogin = (data: Omit<TemporaryLoginData, 'id' | 'created_at' | 'login_url'>): Promise<TemporaryLoginData> => {
    return apiFetch({
        path: `${BASE_PATH}/logins`,
        method: 'POST',
        data,
    });
};

export const deleteTemporaryLogin = (id: number): Promise<void> => {
    return apiFetch({
        path: `${BASE_PATH}/logins/${id}`,
        method: 'DELETE',
    });
};

export const getSettings = (): Promise<TemporaryLoginSettings> => {
    return apiFetch({ path: `${BASE_PATH}/settings` });
};

export const updateSettings = (settings: TemporaryLoginSettings): Promise<TemporaryLoginSettings> => {
    return apiFetch({
        path: `${BASE_PATH}/settings`,
        method: 'POST',
        data: settings,
    });
};

export const getPluginStatus = (): Promise<{ active: boolean }> => {
    return apiFetch({ path: `${BASE_PATH}/status` });
};

export const activatePlugin = (): Promise<{ active: boolean }> => {
    return apiFetch({
        path: `${BASE_PATH}/status/activate`,
        method: 'POST',
    });
};

interface GenerateApiKeyResponse {
    success: boolean;
    data?: {
        message?: string;
        api_key?: string;
    };
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    errors?: any; // For wp_send_json_error
}

/**
 * Generates a new API key via admin-ajax.php.
 */
export const generateNewApiKey = async (): Promise<string> => {
    // Ensure the global localized object and its properties exist.
    // Fallback to a global ajaxurl if not specifically localized (though it should be).
    const ajaxUrl = (window as any).ajaxurl || '/wp-admin/admin-ajax.php';
    const nonce = (window as any).alvobotProTemporaryLogin?.api_key_nonce;

    if (!nonce) {
        // eslint-disable-next-line no-console
        console.error('API Key Nonce not found. Ensure alvobotProTemporaryLogin.api_key_nonce is localized.');
        throw new Error('API Key generation nonce is missing.');
    }

    const formData = new URLSearchParams();
    formData.append('action', 'alvobot_pro_temporary_login_generate_api_key');
    formData.append('_ajax_nonce', nonce); // Nonce field name expected by check_ajax_referer

    const response = await fetch(ajaxUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: formData.toString(),
    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result: GenerateApiKeyResponse = await response.json();

    if (result.success && result.data?.api_key) {
        return result.data.api_key;
    } else if (result.success && result.data?.message) {
        // Handle cases where success is true but no key (should not happen with current PHP)
        throw new Error(result.data.message || 'API key generation failed but no error message provided.');
    } else if (!result.success && result.data?.message) {
        // Handle wp_send_json_error if 'data' contains the message (common for WP < 5.5 or custom error handling)
        throw new Error(result.data.message);
    } else if (!result.success && result.errors) {
        // Handle wp_send_json_error where 'errors' is an array of { code, message }
        const primaryError = Array.isArray(result.errors) ? result.errors[0] : result.errors;
        throw new Error(primaryError?.message || 'An unknown error occurred during API key generation.');
    } else {
        throw new Error('An unknown error occurred or API key was not returned.');
    }
};

export const deactivatePlugin = (): Promise<{ active: boolean }> => {
    return apiFetch({
        path: `${BASE_PATH}/status/deactivate`,
        method: 'POST',
    });
};
