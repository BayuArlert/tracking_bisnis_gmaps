import { router } from '@inertiajs/react';
import axios from 'axios';

// Configure Inertia to use the same token as axios
const setupInertiaAuth = () => {
    // Set axios defaults
    axios.defaults.withCredentials = true;
    
    // Function to get current token
    const getCurrentToken = () => {
        return localStorage.getItem('token');
    };
    
    // Configure Inertia to always use the current token
    router.on('before', (event) => {
        const token = getCurrentToken();
        if (token) {
            event.detail.visit.headers = {
                ...event.detail.visit.headers,
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            };
        }
    });
    
    // Also set axios header initially
    const token = getCurrentToken();
    if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
    
    // Listen for storage changes to update token when it changes
    window.addEventListener('storage', (e) => {
        if (e.key === 'token') {
            const newToken = e.newValue;
            if (newToken) {
                axios.defaults.headers.common['Authorization'] = `Bearer ${newToken}`;
            } else {
                delete axios.defaults.headers.common['Authorization'];
            }
        }
    });
};

export { setupInertiaAuth };

