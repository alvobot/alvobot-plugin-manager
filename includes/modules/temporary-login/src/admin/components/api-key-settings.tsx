// src/admin/components/api-key-settings.tsx
import React, { useState, useEffect } from 'react';
import { Button, TextField, IconButton, InputAdornment, Typography, Paper, Box, CircularProgress } from '@mui/material';
import { Visibility, VisibilityOff, ContentCopy } from '@mui/icons-material';
import { useSnackbar } from 'notistack';
import { __ } from '@wordpress/i18n';
import { TEXT_DOMAIN } from '../common/constants'; // Corrected path
import { generateNewApiKey } from '../api';

// Access initial data from the localized object
const initialApiKey = (window as any).alvobotProTemporaryLogin?.current_api_key || '';

const ApiKeySettings: React.FC = () => {
    const [apiKey, setApiKey] = useState<string>(initialApiKey);
    const [showApiKey, setShowApiKey] = useState<boolean>(false);
    const [isLoading, setIsLoading] = useState<boolean>(false);
    const { enqueueSnackbar } = useSnackbar();

    useEffect(() => {
        // If the initialApiKey changes (e.g. due to external update, though unlikely here), update the state.
        setApiKey(initialApiKey);
    }, [initialApiKey]);

    const handleToggleShowApiKey = () => {
        setShowApiKey(!showApiKey);
    };

    const handleGenerateApiKey = async () => {
        setIsLoading(true);
        try {
            const newKey = await generateNewApiKey();
            setApiKey(newKey);
            enqueueSnackbar(__('New API Key generated and saved successfully!', TEXT_DOMAIN), { variant: 'success' });
        } catch (error: any) {
            // eslint-disable-next-line no-console
            console.error('Failed to generate API key:', error);
            enqueueSnackbar(error.message || __('Failed to generate API key.', TEXT_DOMAIN), { variant: 'error' });
        } finally {
            setIsLoading(false);
        }
    };

    const handleCopyToClipboard = () => {
        if (!apiKey) {
            enqueueSnackbar(__('No API Key to copy.', TEXT_DOMAIN), { variant: 'warning' });
            return;
        }
        navigator.clipboard.writeText(apiKey)
            .then(() => {
                enqueueSnackbar(__('API Key copied to clipboard!', TEXT_DOMAIN), { variant: 'success' });
            })
            .catch((err) => {
                // eslint-disable-next-line no-console
                console.error('Failed to copy API key: ', err);
                enqueueSnackbar(__('Failed to copy API Key.', TEXT_DOMAIN), { variant: 'error' });
            });
    };

    return (
        <Paper elevation={2} sx={{ p: 3 }}>
            <Typography variant="h6" gutterBottom>
                {__('API Key Management', TEXT_DOMAIN)}
            </Typography>
            <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
                {__('This API key is used for external services to interact with the Temporary Login module securely.', TEXT_DOMAIN)}
            </Typography>
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 2 }}>
                <TextField
                    label={__('API Key', TEXT_DOMAIN)}
                    value={apiKey}
                    type={showApiKey ? 'text' : 'password'}
                    fullWidth
                    InputProps={{
                        readOnly: true,
                        endAdornment: (
                            <InputAdornment position="end">
                                <IconButton
                                    aria-label={showApiKey ? __('Hide API Key', TEXT_DOMAIN) : __('Show API Key', TEXT_DOMAIN)}
                                    onClick={handleToggleShowApiKey}
                                    edge="end"
                                >
                                    {showApiKey ? <VisibilityOff /> : <Visibility />}
                                </IconButton>
                            </InputAdornment>
                        ),
                    }}
                    helperText={!apiKey ? __('No API Key generated yet. Click "Generate New API Key" to create one.', TEXT_DOMAIN) : ' '}
                    sx={{ flexGrow: 1 }}
                />
                <Button
                    variant="outlined"
                    onClick={handleCopyToClipboard}
                    startIcon={<ContentCopy />}
                    disabled={!apiKey}
                    sx={{ height: '56px' }} // Match TextField height
                >
                    {__('Copy', TEXT_DOMAIN)}
                </Button>
            </Box>
            <Button
                variant="contained"
                onClick={handleGenerateApiKey}
                disabled={isLoading}
                startIcon={isLoading ? <CircularProgress size={20} color="inherit" /> : null}
            >
                {isLoading ? __('Generating...', TEXT_DOMAIN) : __('Generate New API Key & Save', TEXT_DOMAIN)}
            </Button>
        </Paper>
    );
};

export default ApiKeySettings;
