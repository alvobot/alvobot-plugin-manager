// src/admin/app.tsx
import React, { useState, useEffect } from 'react';
import { HashRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { SnackbarProvider } from 'notistack';
import { ThemeProvider, createTheme } from '@mui/material/styles';
import CssBaseline from '@mui/material/CssBaseline';
import PageActive from './components/page-active';
import PageInactive from './components/page-inactive';
import { getPluginStatus, activatePlugin, deactivatePlugin } from './api';
import CircularProgress from '@mui/material/CircularProgress';
import Box from '@mui/material/Box';

const theme = createTheme({
    // Customize your theme here if needed
});

const App: React.FC = () => {
    const [isActive, setIsActive] = useState<boolean | null>(null);
    const [loading, setLoading] = useState<boolean>(true);

    useEffect(() => {
        const fetchStatus = async () => {
            try {
                setLoading(true);
                const status = await getPluginStatus();
                setIsActive(status.active);
            } catch (error) {
                console.error('Failed to fetch plugin status:', error);
                // Handle error appropriately, maybe set an error state
            } finally {
                setLoading(false);
            }
        };
        fetchStatus();
    }, []);

    const handleActivate = async () => {
        try {
            setLoading(true);
            await activatePlugin();
            setIsActive(true);
        } catch (error) {
            console.error('Failed to activate plugin:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleDeactivate = async () => {
        try {
            setLoading(true);
            await deactivatePlugin();
            setIsActive(false);
        } catch (error) {
            console.error('Failed to deactivate plugin:', error);
        } finally {
            setLoading(false);
        }
    };

    if (loading || isActive === null) {
        return (
            <Box display="flex" justifyContent="center" alignItems="center" minHeight="100vh">
                <CircularProgress />
            </Box>
        );
    }

    return (
        <ThemeProvider theme={theme}>
            <CssBaseline />
            <SnackbarProvider maxSnack={3}>
                <Router>
                    <Routes>
                        <Route
                            path="/"
                            element={
                                isActive ? (
                                    <Navigate to="/active" />
                                ) : (
                                    <PageInactive onActivate={handleActivate} />
                                )
                            }
                        />
                        <Route
                            path="/active/*"
                            element={
                                isActive ? (
                                    <PageActive onDeactivate={handleDeactivate} />
                                ) : (
                                    <Navigate to="/" />
                                )
                            }
                        />
                    </Routes>
                </Router>
            </SnackbarProvider>
        </ThemeProvider>
    );
};

export default App;
