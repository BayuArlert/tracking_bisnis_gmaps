import React, { useContext } from "react";
import { AuthContext } from "../context/AuthContext";
import { Button } from "./ui/button";
import { router, usePage } from "@inertiajs/react";

interface NavItem {
    path: string;
    name: string;
    icon: React.ReactNode;
}

const Navbar: React.FC = () => {
    const { logout, user, isLoading } = useContext(AuthContext);

    const handleLogout = () => {
        console.log('=== LOGOUT BUTTON CLICKED ===');
        console.log('Current user from context:', user);
        console.log('Calling logout function...');
        logout();
    };

    // Get current URL from Inertia
    const { url } = usePage() as { url: string };

    const navItems: NavItem[] = [
        {
            path: "/dashboard",
            name: "Dashboard",
            icon: (
                <svg
                    className="w-5 h-5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"
                    />
                </svg>
            ),
        },
        {
            path: "/businesslist",
            name: "Daftar Bisnis",
            icon: (
                <svg
                    className="w-5 h-5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                    />
                </svg>
            ),
        },
        {
            path: "/statistics",
            name: "Statistics",
            icon: (
                <svg
                    className="w-5 h-5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                    />
                </svg>
            ),
        },
    ];

    return (
        <div className="w-56 bg-white shadow-2xl border-r border-gray-200 flex flex-col h-screen fixed top-0 left-0 z-50">
            {/* Header */}
            <div className="p-4 border-b border-gray-100">
                <div className="flex items-center space-x-2">
                    <div className="w-8 h-8 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
                        <svg
                            className="w-5 h-5 text-white"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                            />
                        </svg>
                    </div>
                    <div>
                        <h2 className="font-bold text-lg bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                            TrendBuss
                        </h2>
                        <p className="text-xs text-gray-500">Dashboard</p>
                    </div>
                </div>
            </div>

            {/* Navigation */}
            <nav className="flex-1 p-3 space-y-1">
                {navItems.map((item) => {
                    // Improved active state detection
                    const isActive = url === item.path || 
                                   (item.path === "/dashboard" && (url === "/" || url === "/dashboard")) ||
                                   (item.path !== "/dashboard" && url.startsWith(item.path));

                    const baseClasses =
                        "flex items-center space-x-2 px-3 py-2.5 rounded-lg w-full text-left transition-all duration-300 transform hover:scale-[1.02] relative overflow-hidden";
                    const activeClasses =
                        "bg-gradient-to-r from-blue-500 to-purple-600 text-white shadow-lg shadow-blue-500/25 before:absolute before:inset-0 before:bg-gradient-to-r before:from-blue-400 before:to-purple-500 before:opacity-0 hover:before:opacity-100 before:transition-opacity before:duration-300";
                    const inactiveClasses =
                        "text-gray-700 hover:bg-gradient-to-r hover:from-blue-50 hover:to-purple-50 hover:text-gray-900 hover:shadow-md hover:border-l-4 hover:border-blue-500";

                    const icon = React.isValidElement(item.icon)
                        ? React.cloneElement(item.icon as React.ReactElement<{ className?: string }>, {
                              className: `w-4 h-4 transition-colors ${isActive ? "text-white" : "text-gray-600"}`,
                          })
                        : item.icon;

                    return (
                        <button
                            key={item.path}
                            onClick={() => router.visit(item.path)}
                            className={`${baseClasses} ${isActive ? activeClasses : inactiveClasses}`}
                            data-testid={`nav-${item.name.toLowerCase().replace(/\s+/g, "-")}`}
                            aria-current={isActive ? "page" : undefined}
                        >
                            <span className="flex-shrink-0">{icon}</span>
                            <span className="font-medium text-sm">{item.name}</span>
                            {isActive && (
                                <div className="ml-auto flex items-center space-x-1">
                                    <div className="w-1.5 h-1.5 bg-white rounded-full shadow-sm animate-pulse"></div>
                                    <div className="w-1 h-4 bg-white rounded-full opacity-60"></div>
                                </div>
                            )}
                        </button>
                    );
                })}
            </nav>

            {/* User Section */}
            <div className="p-3 border-t border-gray-100 space-y-3">
                <div className="flex items-center space-x-2 px-3 py-2 bg-gray-50 rounded-lg">
                    <div className="w-7 h-7 bg-gradient-to-br from-gray-200 to-gray-300 rounded-full flex items-center justify-center">
                        <svg
                            className="w-3.5 h-3.5 text-gray-600"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                            />
                        </svg>
                    </div>
                    <div className="flex-1 min-w-0">
                        <p className="text-xs font-semibold text-gray-800 truncate">
                            {isLoading ? "Loading..." : (user?.name || "Guest")}
                        </p>
                        <p className="text-xs text-gray-500">
                            {isLoading ? "Please wait..." : (user?.email ? user.email.split('@')[0] : "User")}
                        </p>
                    </div>
                </div>

                <Button
                    onClick={handleLogout}
                    variant="outline"
                    size="sm"
                    data-testid="logout-button"
                    className="w-full text-gray-600 border-gray-200 hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition-all duration-200 text-xs py-2"
                >
                    <svg
                        className="w-3.5 h-3.5 mr-1.5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                        />
                    </svg>
                    Keluar
                </Button>
            </div>
        </div>
    );
};

export default Navbar;