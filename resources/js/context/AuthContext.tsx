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
      const savedToken = localStorage.getItem("token");
      const savedUser = localStorage.getItem("user");
      
      if (savedToken && savedUser) {
        // Set the user state immediately to prevent redirect
        setToken(savedToken);
        setUser(JSON.parse(savedUser));
        axios.defaults.headers.common['Authorization'] = `Bearer ${savedToken}`;
        
        // Mark as initialized and not loading immediately
        setIsInitialized(true);
        setIsLoading(false);
        
        // Verify token in background without blocking UI
        setTimeout(async () => {
          try {
            const response = await axios.get(`${API}/auth/verify`, {
              headers: {
                'Authorization': `Bearer ${savedToken}`,
                'Accept': 'application/json',
              }
            });
            
            if (!response.data.success) {
              // Token is invalid, clear storage
              setToken(null);
              setUser(null);
              localStorage.removeItem("token");
              localStorage.removeItem("user");
              delete axios.defaults.headers.common['Authorization'];
            }
          } catch (error) {
            // Check if it's a network error or server error
            if (error && typeof error === 'object' && 'response' in error) {
              const axiosError = error as any;
              if (axiosError.response?.status === 401) {
                // Unauthorized - token is invalid
                setToken(null);
                setUser(null);
                localStorage.removeItem("token");
                localStorage.removeItem("user");
                delete axios.defaults.headers.common['Authorization'];
              }
            }
          }
        }, 100);
      } else {
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
        await axios.get(`${API.replace('/api', '')}/sanctum/csrf-cookie`);
      } catch (error) {
        // Silent fail for CSRF setup
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
