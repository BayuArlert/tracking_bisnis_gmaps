import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { AuthProvider } from './context/AuthContext';
import { Toaster } from 'react-hot-toast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => title ? `${title} - ${appName}` : appName,
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <AuthProvider>
                <App {...props} />
                <Toaster 
                    position="top-right"
                    toastOptions={{
                        duration: 4000,
                        style: {
                            background: '#363636',
                            color: '#fff',
                            borderRadius: '12px',
                            padding: '16px 20px',
                            fontWeight: '500',
                            boxShadow: '0 10px 25px rgba(0, 0, 0, 0.2)'
                        },
                        success: {
                            style: {
                                background: 'linear-gradient(135deg, #10b981, #059669)',
                                color: 'white',
                                fontWeight: '500',
                                borderRadius: '12px',
                                padding: '16px 20px',
                                boxShadow: '0 10px 25px rgba(16, 185, 129, 0.3)'
                            }
                        },
                        error: {
                            style: {
                                background: 'linear-gradient(135deg, #ef4444, #dc2626)',
                                color: 'white',
                                fontWeight: '500',
                                borderRadius: '12px',
                                padding: '16px 20px',
                                boxShadow: '0 10px 25px rgba(239, 68, 68, 0.3)'
                            }
                        }
                    }}
                />
            </AuthProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});
