// src/admin/components/temporary-users.tsx
import React, { useState, useEffect } from 'react';
import { useSnackbar } from 'notistack';
import { __ } from '@wordpress/i18n';
import { TEXT_DOMAIN } from '../common/constants';

// Material UI components
import {
    Box,
    Button,
    Card,
    CardContent,
    CardActions,
    Chip,
    Dialog,
    DialogActions,
    DialogContent,
    DialogContentText,
    DialogTitle,
    Divider,
    FormControl,
    FormHelperText,
    Grid,
    IconButton,
    InputLabel,
    MenuItem,
    Paper,
    Select,
    TextField,
    Typography,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    TablePagination,
    Tooltip,
    CircularProgress
} from '@mui/material';

// Icons
import {
    Add as AddIcon,
    ContentCopy as CopyIcon,
    Delete as DeleteIcon,
    Refresh as RefreshIcon,
    Edit as EditIcon,
    Visibility as ViewIcon
} from '@mui/icons-material';

// Interfaces for data types
interface TemporaryUser {
    id: number;
    username: string;
    role: string;
    login_url: string;
    created_at: string;
    expires_at: string;
    created_by_user_id: number | null;
}

// Mock API calls - these will be replaced with actual API calls
const fetchTemporaryUsers = async (): Promise<TemporaryUser[]> => {
    // Simulate API delay
    await new Promise(resolve => setTimeout(resolve, 1000));
    
    // Return mock data
    return [
        {
            id: 1,
            username: 'temp_60a3f5e8c4b2d',
            role: 'administrator',
            login_url: 'https://example.com/wp-login.php?temporary-login-key=abc123&user-id=1',
            created_at: '2023-05-18 12:00:00',
            expires_at: '2023-06-01 12:00:00',
            created_by_user_id: 1
        },
        {
            id: 2,
            username: 'temp_60b4f6e9d5c3e',
            role: 'editor',
            login_url: 'https://example.com/wp-login.php?temporary-login-key=def456&user-id=2',
            created_at: '2023-05-20 15:30:00',
            expires_at: '2023-06-03 15:30:00',
            created_by_user_id: null
        }
    ];
};

const deleteTemporaryUser = async (userId: number): Promise<void> => {
    // Simulate API delay
    await new Promise(resolve => setTimeout(resolve, 1000));
    // In a real implementation, this would make an API call to delete the user
};

const createTemporaryUser = async (data: {
    role: string;
    duration_value: number;
    duration_unit: string;
}): Promise<TemporaryUser> => {
    // Simulate API delay
    await new Promise(resolve => setTimeout(resolve, 1000));
    
    // Return mock new user
    return {
        id: 3,
        username: 'temp_' + Math.random().toString(36).substring(2, 10),
        role: data.role,
        login_url: 'https://example.com/wp-login.php?temporary-login-key=' + Math.random().toString(36).substring(2, 10) + '&user-id=3',
        created_at: new Date().toISOString().replace('T', ' ').substring(0, 19),
        expires_at: new Date(Date.now() + 14 * 24 * 60 * 60 * 1000).toISOString().replace('T', ' ').substring(0, 19),
        created_by_user_id: 1
    };
};

const TemporaryUsers: React.FC = () => {
    const { enqueueSnackbar } = useSnackbar();
    const [users, setUsers] = useState<TemporaryUser[]>([]);
    const [loading, setLoading] = useState<boolean>(true);
    const [openCreateDialog, setOpenCreateDialog] = useState<boolean>(false);
    const [openDeleteDialog, setOpenDeleteDialog] = useState<boolean>(false);
    const [selectedUser, setSelectedUser] = useState<TemporaryUser | null>(null);
    const [page, setPage] = useState<number>(0);
    const [rowsPerPage, setRowsPerPage] = useState<number>(10);
    const [formData, setFormData] = useState({
        role: 'administrator',
        duration_value: 14,
        duration_unit: 'days'
    });
    const [formErrors, setFormErrors] = useState({
        role: '',
        duration_value: ''
    });

    // Load users on component mount
    useEffect(() => {
        loadUsers();
    }, []);

    const loadUsers = async () => {
        try {
            setLoading(true);
            const data = await fetchTemporaryUsers();
            setUsers(data);
        } catch (error) {
            console.error('Error fetching temporary users:', error);
            enqueueSnackbar(__('Failed to load temporary users', TEXT_DOMAIN), { variant: 'error' });
        } finally {
            setLoading(false);
        }
    };

    // Handle pagination
    const handleChangePage = (event: unknown, newPage: number) => {
        setPage(newPage);
    };

    const handleChangeRowsPerPage = (event: React.ChangeEvent<HTMLInputElement>) => {
        setRowsPerPage(parseInt(event.target.value, 10));
        setPage(0);
    };

    // Copy login URL to clipboard
    const handleCopyUrl = (url: string) => {
        navigator.clipboard.writeText(url)
            .then(() => {
                enqueueSnackbar(__('Login URL copied to clipboard', TEXT_DOMAIN), { variant: 'success' });
            })
            .catch(() => {
                enqueueSnackbar(__('Failed to copy URL', TEXT_DOMAIN), { variant: 'error' });
            });
    };

    // Open delete confirmation dialog
    const handleOpenDeleteDialog = (user: TemporaryUser) => {
        setSelectedUser(user);
        setOpenDeleteDialog(true);
    };

    // Delete a temporary user
    const handleDeleteUser = async () => {
        if (!selectedUser) return;
        
        try {
            setLoading(true);
            await deleteTemporaryUser(selectedUser.id);
            setUsers(users.filter(user => user.id !== selectedUser.id));
            enqueueSnackbar(__('Temporary user deleted successfully', TEXT_DOMAIN), { variant: 'success' });
            setOpenDeleteDialog(false);
        } catch (error) {
            console.error('Error deleting temporary user:', error);
            enqueueSnackbar(__('Failed to delete temporary user', TEXT_DOMAIN), { variant: 'error' });
        } finally {
            setLoading(false);
        }
    };

    // Form handling for create user
    const handleFormChange = (event: React.ChangeEvent<HTMLInputElement | { name?: string; value: unknown }>) => {
        const name = event.target.name as string;
        const value = event.target.value;
        
        setFormData({
            ...formData,
            [name]: value
        });
        
        // Clear errors when field is changed
        if (formErrors[name as keyof typeof formErrors]) {
            setFormErrors({
                ...formErrors,
                [name]: ''
            });
        }
    };

    // Validate form before submission
    const validateForm = (): boolean => {
        const newErrors = { ...formErrors };
        let isValid = true;
        
        if (!formData.role) {
            newErrors.role = __('Role is required', TEXT_DOMAIN);
            isValid = false;
        }
        
        if (!formData.duration_value || formData.duration_value <= 0) {
            newErrors.duration_value = __('Duration must be a positive number', TEXT_DOMAIN);
            isValid = false;
        }
        
        setFormErrors(newErrors);
        return isValid;
    };

    // Create a new temporary user
    const handleCreateUser = async () => {
        if (!validateForm()) return;
        
        try {
            setLoading(true);
            const newUser = await createTemporaryUser(formData);
            setUsers([newUser, ...users]);
            enqueueSnackbar(__('Temporary user created successfully', TEXT_DOMAIN), { variant: 'success' });
            setOpenCreateDialog(false);
            
            // Reset form data
            setFormData({
                role: 'administrator',
                duration_value: 14,
                duration_unit: 'days'
            });
        } catch (error) {
            console.error('Error creating temporary user:', error);
            enqueueSnackbar(__('Failed to create temporary user', TEXT_DOMAIN), { variant: 'error' });
        } finally {
            setLoading(false);
        }
    };

    // Determine if a user is expired
    const isExpired = (expiresAt: string): boolean => {
        return new Date(expiresAt) < new Date();
    };

    return (
        <div>
            <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <Typography variant="h5" component="h1">
                    {__('Temporary Users', TEXT_DOMAIN)}
                </Typography>
                <Box>
                    <Button 
                        variant="outlined" 
                        startIcon={<RefreshIcon />} 
                        onClick={loadUsers}
                        sx={{ mr: 1 }}
                        disabled={loading}
                    >
                        {__('Refresh', TEXT_DOMAIN)}
                    </Button>
                    <Button 
                        variant="contained" 
                        color="primary"
                        startIcon={<AddIcon />}
                        onClick={() => setOpenCreateDialog(true)}
                        disabled={loading}
                    >
                        {__('Create New User', TEXT_DOMAIN)}
                    </Button>
                </Box>
            </Box>

            {loading && users.length === 0 ? (
                <Box sx={{ display: 'flex', justifyContent: 'center', my: 4 }}>
                    <CircularProgress />
                </Box>
            ) : (
                <>
                    {users.length === 0 ? (
                        <Paper sx={{ p: 3, textAlign: 'center' }}>
                            <Typography variant="body1">
                                {__('No temporary users found', TEXT_DOMAIN)}
                            </Typography>
                            <Button 
                                variant="contained" 
                                color="primary"
                                startIcon={<AddIcon />}
                                onClick={() => setOpenCreateDialog(true)}
                                sx={{ mt: 2 }}
                            >
                                {__('Create Your First User', TEXT_DOMAIN)}
                            </Button>
                        </Paper>
                    ) : (
                        <Paper>
                            <TableContainer>
                                <Table>
                                    <TableHead>
                                        <TableRow>
                                            <TableCell>{__('Username', TEXT_DOMAIN)}</TableCell>
                                            <TableCell>{__('Role', TEXT_DOMAIN)}</TableCell>
                                            <TableCell>{__('Created', TEXT_DOMAIN)}</TableCell>
                                            <TableCell>{__('Expires', TEXT_DOMAIN)}</TableCell>
                                            <TableCell>{__('Status', TEXT_DOMAIN)}</TableCell>
                                            <TableCell align="right">{__('Actions', TEXT_DOMAIN)}</TableCell>
                                        </TableRow>
                                    </TableHead>
                                    <TableBody>
                                        {users
                                            .slice(page * rowsPerPage, page * rowsPerPage + rowsPerPage)
                                            .map((user) => (
                                                <TableRow key={user.id}>
                                                    <TableCell>{user.username}</TableCell>
                                                    <TableCell>{user.role}</TableCell>
                                                    <TableCell>{user.created_at}</TableCell>
                                                    <TableCell>{user.expires_at}</TableCell>
                                                    <TableCell>
                                                        <Chip 
                                                            label={isExpired(user.expires_at) ? __('Expired', TEXT_DOMAIN) : __('Active', TEXT_DOMAIN)} 
                                                            color={isExpired(user.expires_at) ? 'error' : 'success'} 
                                                            size="small" 
                                                        />
                                                    </TableCell>
                                                    <TableCell align="right">
                                                        <Tooltip title={__('Copy Login URL', TEXT_DOMAIN)}>
                                                            <IconButton 
                                                                size="small" 
                                                                onClick={() => handleCopyUrl(user.login_url)}
                                                                color="primary"
                                                            >
                                                                <CopyIcon fontSize="small" />
                                                            </IconButton>
                                                        </Tooltip>
                                                        <Tooltip title={__('Delete User', TEXT_DOMAIN)}>
                                                            <IconButton 
                                                                size="small" 
                                                                onClick={() => handleOpenDeleteDialog(user)}
                                                                color="error"
                                                            >
                                                                <DeleteIcon fontSize="small" />
                                                            </IconButton>
                                                        </Tooltip>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                    </TableBody>
                                </Table>
                            </TableContainer>
                            <TablePagination
                                rowsPerPageOptions={[5, 10, 25]}
                                component="div"
                                count={users.length}
                                rowsPerPage={rowsPerPage}
                                page={page}
                                onPageChange={handleChangePage}
                                onRowsPerPageChange={handleChangeRowsPerPage}
                            />
                        </Paper>
                    )}
                </>
            )}

            {/* Create User Dialog */}
            <Dialog open={openCreateDialog} onClose={() => setOpenCreateDialog(false)}>
                <DialogTitle>{__('Create Temporary User', TEXT_DOMAIN)}</DialogTitle>
                <DialogContent>
                    <DialogContentText sx={{ mb: 2 }}>
                        {__('Create a new temporary user with limited time access to this site.', TEXT_DOMAIN)}
                    </DialogContentText>
                    <Grid container spacing={2}>
                        <Grid item xs={12}>
                            <FormControl fullWidth error={!!formErrors.role}>
                                <InputLabel id="role-label">{__('User Role', TEXT_DOMAIN)}</InputLabel>
                                <Select
                                    labelId="role-label"
                                    name="role"
                                    value={formData.role}
                                    label={__('User Role', TEXT_DOMAIN)}
                                    onChange={handleFormChange}
                                >
                                    <MenuItem value="administrator">{__('Administrator', TEXT_DOMAIN)}</MenuItem>
                                    <MenuItem value="editor">{__('Editor', TEXT_DOMAIN)}</MenuItem>
                                    <MenuItem value="author">{__('Author', TEXT_DOMAIN)}</MenuItem>
                                    <MenuItem value="contributor">{__('Contributor', TEXT_DOMAIN)}</MenuItem>
                                    <MenuItem value="subscriber">{__('Subscriber', TEXT_DOMAIN)}</MenuItem>
                                </Select>
                                {formErrors.role && <FormHelperText>{formErrors.role}</FormHelperText>}
                            </FormControl>
                        </Grid>
                        <Grid item xs={6}>
                            <TextField
                                name="duration_value"
                                label={__('Duration', TEXT_DOMAIN)}
                                type="number"
                                fullWidth
                                value={formData.duration_value}
                                onChange={handleFormChange}
                                error={!!formErrors.duration_value}
                                helperText={formErrors.duration_value}
                                InputProps={{ inputProps: { min: 1 } }}
                            />
                        </Grid>
                        <Grid item xs={6}>
                            <FormControl fullWidth>
                                <InputLabel id="duration-unit-label">{__('Unit', TEXT_DOMAIN)}</InputLabel>
                                <Select
                                    labelId="duration-unit-label"
                                    name="duration_unit"
                                    value={formData.duration_unit}
                                    label={__('Unit', TEXT_DOMAIN)}
                                    onChange={handleFormChange}
                                >
                                    <MenuItem value="hours">{__('Hours', TEXT_DOMAIN)}</MenuItem>
                                    <MenuItem value="days">{__('Days', TEXT_DOMAIN)}</MenuItem>
                                </Select>
                            </FormControl>
                        </Grid>
                    </Grid>
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setOpenCreateDialog(false)}>
                        {__('Cancel', TEXT_DOMAIN)}
                    </Button>
                    <Button 
                        onClick={handleCreateUser} 
                        variant="contained" 
                        color="primary"
                        disabled={loading}
                    >
                        {loading ? <CircularProgress size={24} /> : __('Create', TEXT_DOMAIN)}
                    </Button>
                </DialogActions>
            </Dialog>

            {/* Delete Confirmation Dialog */}
            <Dialog open={openDeleteDialog} onClose={() => setOpenDeleteDialog(false)}>
                <DialogTitle>{__('Delete Temporary User', TEXT_DOMAIN)}</DialogTitle>
                <DialogContent>
                    <DialogContentText>
                        {__('Are you sure you want to delete this temporary user? This action cannot be undone.', TEXT_DOMAIN)}
                    </DialogContentText>
                    {selectedUser && (
                        <Box sx={{ mt: 2, p: 2, bgcolor: 'background.paper', borderRadius: 1 }}>
                            <Typography variant="subtitle2">{__('Username', TEXT_DOMAIN)}: {selectedUser.username}</Typography>
                            <Typography variant="subtitle2">{__('Role', TEXT_DOMAIN)}: {selectedUser.role}</Typography>
                            <Typography variant="subtitle2">{__('Expires', TEXT_DOMAIN)}: {selectedUser.expires_at}</Typography>
                        </Box>
                    )}
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setOpenDeleteDialog(false)}>
                        {__('Cancel', TEXT_DOMAIN)}
                    </Button>
                    <Button 
                        onClick={handleDeleteUser} 
                        variant="contained" 
                        color="error"
                        disabled={loading}
                    >
                        {loading ? <CircularProgress size={24} /> : __('Delete', TEXT_DOMAIN)}
                    </Button>
                </DialogActions>
            </Dialog>
        </div>
    );
};

export default TemporaryUsers;
