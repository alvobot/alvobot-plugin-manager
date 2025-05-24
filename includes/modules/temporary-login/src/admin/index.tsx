// src/admin/index.tsx
import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './app'; // Assuming App is in the same directory or a sub-directory

document.addEventListener('DOMContentLoaded', () => {
    const adminAppDiv = document.getElementById('temporary-login-admin-app');
    if (adminAppDiv) {
        const root = createRoot(adminAppDiv);
        root.render(<App />);
    }
});
