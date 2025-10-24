// components/Layout.tsx
import React, { useContext, useEffect } from "react";
import { AuthContext } from "../context/AuthContext";
import Navbar from "./Sidebar";
import { router } from "@inertiajs/react";
import ErrorBoundary from "./ErrorBoundary";

interface LayoutProps {
    children: React.ReactNode;
}

const Layout: React.FC<LayoutProps> = ({ children }) => {
    const { user, isLoading } = useContext(AuthContext);

    useEffect(() => {
        // Only redirect if we're not loading AND we have no user
        // This prevents redirect during initialization
        if (!isLoading && !user) {
            const timer = setTimeout(() => {
                router.visit('/login');
            }, 1000);
            return () => clearTimeout(timer);
        }
    }, [user, isLoading]);

    // Show loading while checking authentication
    if (isLoading) {
        return (
            <div className="min-h-screen bg-gray-50 flex items-center justify-center">
                <div className="text-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                    <p className="text-gray-600">Loading...</p>
                </div>
            </div>
        );
    }

    // If no user after loading, don't render anything (will redirect)
    if (!user) {
        return null;
    }

    return (
        <ErrorBoundary>
            <div className="flex min-h-screen">
                {/* Fixed Sidebar */}
                <Navbar />

                {/* Main content with left margin to compensate for fixed sidebar */}
                <main className="flex-1 bg-gray-50 ml-56">
                    {children}
                </main>
            </div>
        </ErrorBoundary>
    );
};

export default Layout;