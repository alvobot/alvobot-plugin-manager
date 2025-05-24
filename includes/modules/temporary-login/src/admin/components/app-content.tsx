// src/admin/components/app-content.tsx
import React from 'react';
import { Routes, Route, Link as RouterLink } from 'react-router-dom';
import { Box, Drawer, List, ListItem, ListItemButton, ListItemIcon, ListItemText, Toolbar, Typography, Button } from '@mui/material';
import { PeopleAlt, Settings, ExitToApp, BarChart } from '@mui/icons-material'; // Added BarChart for Stats
import { __ } from '@wordpress/i18n';
import { TEXT_DOMAIN } from '../common/constants'; // Corrected path
import ApiKeySettings from './api-key-settings'; // Import the new component
import TemporaryUsers from './temporary-users'; // Import the implemented component
// import Statistics from './statistics'; // This will be for statistics page

// Placeholder for Statistics component
// PluginSettings will now be ApiKeySettings, so the placeholder can be removed or ApiKeySettings used directly.
// const PluginSettings: React.FC = () => (
// 	<Typography variant="h5" gutterBottom>
// 		{ __( 'Plugin Settings (Content to be implemented)', TEXT_DOMAIN ) }
// 	</Typography>
// );
const Statistics: React.FC = () => (
    <Typography variant="h5" gutterBottom>
        {__('Statistics (Content to be implemented)', TEXT_DOMAIN)}
    </Typography>
);


const drawerWidth = 240;

interface AppContentProps {
    onDeactivate: () => void;
}

const AppContent: React.FC<AppContentProps> = ({ onDeactivate }) => {
    return (
        <Box sx={{ display: 'flex' }}>
            <Drawer
                variant="permanent"
                sx={{
                    width: drawerWidth,
                    flexShrink: 0,
                    [`& .MuiDrawer-paper`]: { width: drawerWidth, boxSizing: 'border-box' },
                }}
            >
                <Toolbar />
                <Box sx={{ overflow: 'auto' }}>
                    <List>
                        <ListItem disablePadding component={RouterLink} to="/active/users">
                            <ListItemButton>
                                <ListItemIcon>
                                    <PeopleAlt />
                                </ListItemIcon>
                                <ListItemText primary={__('Temporary Users', TEXT_DOMAIN)} />
                            </ListItemButton>
                        </ListItem>
                        <ListItem disablePadding component={RouterLink} to="/active/settings">
                            <ListItemButton>
                                <ListItemIcon>
                                    <Settings />
                                </ListItemIcon>
                                <ListItemText primary={__('Settings', TEXT_DOMAIN)} />
                            </ListItemButton>
                        </ListItem>
                        <ListItem disablePadding component={RouterLink} to="/active/stats">
                            <ListItemButton>
                                <ListItemIcon>
                                    <BarChart />
                                </ListItemIcon>
                                <ListItemText primary={__('Stats', TEXT_DOMAIN)} />
                            </ListItemButton>
                        </ListItem>
                    </List>
                </Box>
            </Drawer>
            <Box component="main" sx={{ flexGrow: 1, p: 3 }}>
                <Toolbar />
                <Box sx={{ mb: 2, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <Typography variant="h4">{__('Temporary Login', TEXT_DOMAIN)}</Typography>
                    <Button
                        variant="contained"
                        color="secondary"
                        startIcon={<ExitToApp />}
                        onClick={onDeactivate}
                    >
                        {__('Deactivate Plugin', TEXT_DOMAIN)}
                    </Button>
                </Box>
                <Routes>
                    <Route path="users" element={<TemporaryUsers />} />
                    <Route path="settings" element={<ApiKeySettings />} /> {/* Use ApiKeySettings here */}
                    <Route path="stats" element={<Statistics />} />
                    <Route index element={<TemporaryUsers />} /> {/* Default to users */}
                </Routes>
            </Box>
        </Box>
    );
};

export default AppContent;
