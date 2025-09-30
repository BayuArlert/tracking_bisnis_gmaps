import React, { createContext, useState, ReactNode, useEffect } from "react";
import axios from 'axios';
import { router } from "@inertiajs/react";

interface User {
  id: number;
  name: string;
  email: string;
}

interface AuthContextType {
  user: User | null;
  token: string | null;
  API: string;
  isLoading: boolean;
  isLoggingOut: boolean;
  login: (token: string, user: User) => void;
  logout: () => void;
}

// Get API URL from environment variables
const getApiUrl = () => {
  // In development, use localhost
  if (import.meta.env.DEV) {
    return "http://localhost:8000/api";
  }
  
  // In production, use the current origin (Railway URL)
  return `${window.location.origin}/api`;
};

export const AuthContext = createContext<AuthContextType>({
  user: null,
  token: null,
  API: getApiUrl(),
  isLoading: true,
  isLoggingOut: false,
  login: () => {},
  logout: () => {},
});

export const AuthProvider = ({ children }: { children: ReactNode }) => {
  const [user, setUser] = useState<User | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoggingOut, setIsLoggingOut] = useState(false);
  const [isInitialized, setIsInitialized] = useState(false);
  const API = getApiUrl();

  useEffect(() => {
    // Configure axios to send credentials with requests
    axios.defaults.withCredentials = true;
    
    const initializeAuth = () => {
      console.log('AuthContext: Starting initialization');
      const savedToken = localStorage.getItem("token");
      const savedUser = localStorage.getItem("user");
      
      if (savedToken && savedUser) {
        console.log('AuthContext: Found saved token and user, setting state immediately');
        
        // Set the user state immediately to prevent redirect
        setToken(savedToken);
        setUser(JSON.parse(savedUser));
        axios.defaults.headers.common['Authorization'] = `Bearer ${savedToken}`;
        
        // Mark as initialized and not loading immediately
        setIsInitialized(true);
        setIsLoading(false);
        
        console.log('AuthContext: State set, will verify token in background');
        
        // Verify token in background without blocking UI
        setTimeout(async () => {
          try {
            console.log('AuthContext: Verifying token in background...');
            const response = await axios.get(`${API}/auth/verify`, {
              headers: {
                'Authorization': `Bearer ${savedToken}`,
                'Accept': 'application/json',
              }
            });
            
            if (response.data.success) {
              console.log('AuthContext: Token verification successful');
            } else {
              // Token is invalid, clear storage
              console.log('AuthContext: Token verification failed - invalid token, clearing storage');
              setToken(null);
              setUser(null);
              localStorage.removeItem("token");
              localStorage.removeItem("user");
              delete axios.defaults.headers.common['Authorization'];
            }
          } catch (error) {
            // Check if it's a network error or server error
            console.log('AuthContext: Token verification failed:', error);
            
            // If it's a network error (no response), keep the token and try again later
            if (error && typeof error === 'object' && 'response' in error) {
              const axiosError = error as any;
              if (axiosError.response?.status >= 500) {
                // Server error - keep token and try again
                console.log('AuthContext: Server error during verification, keeping token for retry');
              } else if (axiosError.response?.status === 401) {
                // Unauthorized - token is invalid
                console.log('AuthContext: Token is invalid (401), clearing storage');
                setToken(null);
                setUser(null);
                localStorage.removeItem("token");
                localStorage.removeItem("user");
                delete axios.defaults.headers.common['Authorization'];
              } else {
                // Other client errors - keep token
                console.log('AuthContext: Client error during verification, keeping token');
              }
            } else {
              // Network error - keep token
              console.log('AuthContext: Network error during verification, keeping token');
            }
          }
        }, 100);
      } else {
        console.log('AuthContext: No saved token or user found');
        setIsInitialized(true);
        setIsLoading(false);
      }
    };
    
    initializeAuth();
  }, [API]);

  // Add CSRF token handling for API requests
  useEffect(() => {
    const setupCSRF = async () => {
      try {
        // Get CSRF token from Laravel
        const response = await axios.get(`${API.replace('/api', '')}/sanctum/csrf-cookie`);
        console.log('CSRF cookie set successfully');
      } catch (error) {
        console.log('Failed to set CSRF cookie:', error);
      }
    };
    
    setupCSRF();
  }, [API]);

  // Listen for storage changes to sync token across tabs
  useEffect(() => {
    const handleStorageChange = (e: StorageEvent) => {
      if (e.key === 'token') {
        const newToken = e.newValue;
        if (newToken) {
          setToken(newToken);
          axios.defaults.headers.common['Authorization'] = `Bearer ${newToken}`;
        } else {
          setToken(null);
          delete axios.defaults.headers.common['Authorization'];
        }
      } else if (e.key === 'user') {
        const newUser = e.newValue ? JSON.parse(e.newValue) : null;
        setUser(newUser);
      }
    };

    window.addEventListener('storage', handleStorageChange);
    return () => window.removeEventListener('storage', handleStorageChange);
  }, []);

  const login = async (newToken: string, newUser: User) => {
    try {
      // First, get CSRF cookie
      await axios.get(`${API.replace('/api', '')}/sanctum/csrf-cookie`);
      
      console.log('Setting user state:', newUser);
      setToken(newToken);
      setUser(newUser);
      localStorage.setItem("token", newToken);
      localStorage.setItem("user", JSON.stringify(newUser));
      
      // Set default authorization header for axios
      axios.defaults.headers.common['Authorization'] = `Bearer ${newToken}`;
      axios.defaults.withCredentials = true;
      
      console.log('Login completed, user state updated');
    } catch (error) {
      console.error('Failed to setup CSRF token:', error);
      // Still proceed with login even if CSRF fails
      console.log('Setting user state (fallback):', newUser);
      setToken(newToken);
      setUser(newUser);
      localStorage.setItem("token", newToken);
      localStorage.setItem("user", JSON.stringify(newUser));
      axios.defaults.headers.common['Authorization'] = `Bearer ${newToken}`;
      axios.defaults.withCredentials = true;
      
      console.log('Login completed (fallback), user state updated');
    }
  };

  const logout = async () => {
    setIsLoggingOut(true);
    setIsLoading(true);
    
    try {
      // Call logout API to revoke token
      if (token) {
        await axios.post(`${API}/auth/logout`, {}, {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          }
        });
      }
    } catch (error) {
      // Continue with local logout even if API fails
    } finally {
      // Always clear local data
      setToken(null);
      setUser(null);
      localStorage.removeItem("token");
      localStorage.removeItem("user");
      
      // Remove authorization header
      delete axios.defaults.headers.common['Authorization'];
      
      // Small delay to ensure state is updated
      setTimeout(() => {
        setIsLoading(false);
        setIsLoggingOut(false);
        
        // Force redirect to login page
        window.location.replace('/login');
      }, 200);
    }
  };

  return (
    <AuthContext.Provider
      value={{ user, token, API, isLoading, isLoggingOut, login, logout }}
    >
      {children}
    </AuthContext.Provider>
  );
};
