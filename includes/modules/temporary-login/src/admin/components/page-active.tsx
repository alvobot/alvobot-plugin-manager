// src/admin/components/page-active.tsx
import React from 'react';
import AppContent from './app-content'; // Assuming AppContent is in the same directory

interface PageActiveProps {
    onDeactivate: () => void;
}

const PageActive: React.FC<PageActiveProps> = ({ onDeactivate }) => {
    // This component will likely house the main UI for when the plugin is active
    // For now, it can be a simple wrapper around AppContent or include other elements.
    return (
        <div>
            {/* You can add headers, footers, or other global elements here if needed */}
            <AppContent onDeactivate={onDeactivate} />
        </div>
    );
};

export default PageActive;
