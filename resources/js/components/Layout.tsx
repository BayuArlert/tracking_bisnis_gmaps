// components/Layout.tsx
import React from "react";
import Navbar from "./Sidebar";

interface LayoutProps {
    children: React.ReactNode;
}

const Layout: React.FC<LayoutProps> = ({ children }) => {
    return (
        <div className="flex min-h-screen">
            {/* Fixed Sidebar */}
            <Navbar />

            {/* Main content with left margin to compensate for fixed sidebar */}
            <main className="flex-1 bg-gray-50 ml-56">
                {children}
            </main>
        </div>
    );
};

export default Layout;