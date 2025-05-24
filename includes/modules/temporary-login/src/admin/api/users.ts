// src/admin/api/users.ts
// API service for temporary users management

import { alvobotProTemporaryLogin } from '../global';

export interface TemporaryUser {
    id: number;
    username: string;
    role: string;
    login_url: string;
    created_at: string;
    expires_at: string;
    created_by_user_id: number | null;
}

export interface CreateUserData {
    role: string;
    duration_value: number;
    duration_unit: string;
    reassign_to_user_id?: number;
}

/**
 * Fetch all temporary users
 */
export const fetchTemporaryUsers = async (): Promise<TemporaryUser[]> => {
    try {
        const response = await fetch(`${alvobotProTemporaryLogin.apiUrl}/users`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': alvobotProTemporaryLogin.rest_nonce,
            },
        });

        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }

        const data = await response.json();
        return data.users || [];
    } catch (error) {
        console.error('Error fetching temporary users:', error);
        throw error;
    }
};

/**
 * Create a new temporary user
 */
export const createTemporaryUser = async (userData: CreateUserData): Promise<TemporaryUser> => {
    try {
        const response = await fetch(`${alvobotProTemporaryLogin.apiUrl}/generate`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': alvobotProTemporaryLogin.rest_nonce,
            },
            body: JSON.stringify(userData),
        });

        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }

        const data = await response.json();
        return data.user;
    } catch (error) {
        console.error('Error creating temporary user:', error);
        throw error;
    }
};

/**
 * Delete a temporary user
 */
export const deleteTemporaryUser = async (userId: number): Promise<void> => {
    try {
        const response = await fetch(`${alvobotProTemporaryLogin.apiUrl}/users/${userId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': alvobotProTemporaryLogin.rest_nonce,
            },
        });

        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
    } catch (error) {
        console.error('Error deleting temporary user:', error);
        throw error;
    }
};

/**
 * Extend the expiration time for a temporary user
 */
export const extendUserExpiration = async (userId: number): Promise<TemporaryUser> => {
    try {
        const response = await fetch(`${alvobotProTemporaryLogin.apiUrl}/users/${userId}/extend`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': alvobotProTemporaryLogin.rest_nonce,
            },
        });

        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }

        const data = await response.json();
        return data.user;
    } catch (error) {
        console.error('Error extending user expiration:', error);
        throw error;
    }
};
