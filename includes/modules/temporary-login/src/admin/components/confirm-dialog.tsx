// src/admin/components/confirm-dialog.tsx
import React from 'react';
import { Dialog, DialogActions, DialogContent, DialogContentText, DialogTitle, Button } from '@mui/material';
import { __ } from '@wordpress/i18n';
import { TEXT_DOMAIN } from '../common/constants'; // Corrected path

interface ConfirmDialogProps {
    open: boolean;
    title: string;
    message: string;
    onConfirm: () => void;
    onCancel: () => void;
}

const ConfirmDialog: React.FC<ConfirmDialogProps> = ({ open, title, message, onConfirm, onCancel }) => {
    return (
        <Dialog
            open={open}
            onClose={onCancel}
            aria-labelledby="confirm-dialog-title"
            aria-describedby="confirm-dialog-description"
        >
            <DialogTitle id="confirm-dialog-title">{title}</DialogTitle>
            <DialogContent>
                <DialogContentText id="confirm-dialog-description">
                    {message}
                </DialogContentText>
            </DialogContent>
            <DialogActions>
                <Button onClick={onCancel} color="primary">
                    {__('Cancel', TEXT_DOMAIN)}
                </Button>
                <Button onClick={onConfirm} color="primary" autoFocus>
                    {__('Confirm', TEXT_DOMAIN)}
                </Button>
            </DialogActions>
        </Dialog>
    );
};

export default ConfirmDialog;
