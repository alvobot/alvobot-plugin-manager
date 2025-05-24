// src/admin/common/constants.ts

// Define constants that might be used across the admin panel
export const TEMPORARY_LOGIN_PAGE_TITLE = 'Temporary Login';
export const TEMPORARY_LOGIN_API_NONCE_HEADER = 'X-WP-Nonce';

// User roles that can be selected for temporary users
export const USER_ROLES = [
	{ value: 'subscriber', label: 'Subscriber' },
	{ value: 'contributor', label: 'Contributor' },
	{ value: 'author', label: 'Author' },
	{ value: 'editor', label: 'Editor' },
	{ value: 'administrator', label: 'Administrator' },
];

// Default duration for temporary logins (e.g., in hours or days)
export const DEFAULT_DURATION = {
	amount: 24,
	unit: 'hours', // 'hours', 'days', 'weeks'
};

// Maximum number of temporary logins allowed (if any)
export const MAX_TEMPORARY_LOGINS = 0; // 0 for unlimited

// Language domain for internationalization
export const TEXT_DOMAIN = 'alvobot-pro'; // Updated text domain

// Query key for data fetching libraries (e.g., React Query, SWR)
export const QUERY_KEY_TEMPORARY_LOGINS = 'alvobotProTemporaryLogins';
export const QUERY_KEY_TEMPORARY_LOGIN_SETTINGS = 'alvobotProTemporaryLoginSettings';
export const QUERY_KEY_TEMPORARY_LOGIN_STATUS = 'alvobotProTemporaryLoginStatus';

// Add other constants as needed for your plugin
