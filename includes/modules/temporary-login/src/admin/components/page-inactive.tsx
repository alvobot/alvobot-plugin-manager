// src/admin/components/page-inactive.tsx
import React from 'react';
import { Button, Container, Typography, Paper, Box } from '@mui/material';
import { RocketLaunch } from '@mui/icons-material';
import { __ } from '@wordpress/i18n';
import { TEXT_DOMAIN } from '../common/constants'; // Corrected path

interface PageInactiveProps {
    onActivate: () => void;
}

const PageInactive: React.FC<PageInactiveProps> = ({ onActivate }) => {
    return (
        <Container component="main" maxWidth="sm" sx={{ mt: 8 }}>
            <Paper elevation={3} sx={{ p: 4, display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
                <RocketLaunch sx={{ fontSize: 60, color: 'primary.main', mb: 2 }} />
                <Typography component="h1" variant="h4" gutterBottom>
                    {__('Temporary Login Plugin is Inactive', TEXT_DOMAIN)}
                </Typography>
                <Typography variant="body1" color="text.secondary" paragraph>
                    {__('Activate the plugin to start creating and managing temporary login access for your users. This allows you to grant secure, time-limited access to your WordPress site without creating permanent accounts.', TEXT_DOMAIN)}
                </Typography>
                <Box sx={{ mt: 3 }}>
                    <Button
                        variant="contained"
                        color="primary"
                        size="large"
                        onClick={onActivate}
                        startIcon={<RocketLaunch />}
                    >
                        {__('Activate Plugin', TEXT_DOMAIN)}
                    </Button>
                </Box>
            </Paper>
        </Container>
    );
};

export default PageInactive;
