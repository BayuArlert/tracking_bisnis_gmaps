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

export const AuthContext = createContext<AuthContextType>({
  user: null,
  token: null,
  API: "http://localhost:8000/api", // ganti sesuai backend
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
  const API = "http://localhost:8000/api";

  useEffect(() => {
    // Configure axios to send credentials with requests
    axios.defaults.withCredentials = true;
    
    const savedToken = localStorage.getItem("token");
    const savedUser = localStorage.getItem("user");
    if (savedToken && savedUser) {
      setToken(savedToken);
      setUser(JSON.parse(savedUser));
      
      // Set authorization header for axios
      axios.defaults.headers.common['Authorization'] = `Bearer ${savedToken}`;
    }
    setIsLoading(false);
  }, []);

  const login = (newToken: string, newUser: User) => {
    setToken(newToken);
    setUser(newUser);
    localStorage.setItem("token", newToken);
    localStorage.setItem("user", JSON.stringify(newUser));
    
    // Set default authorization header for axios
    axios.defaults.headers.common['Authorization'] = `Bearer ${newToken}`;
    axios.defaults.withCredentials = true;
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
