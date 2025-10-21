import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { AuthProvider } from './context/AuthContext';
import { Toaster } from 'react-hot-toast';
import { setupInertiaAuth } from './lib/inertia';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Setup Inertia authentication
setupInertiaAuth();

// Enhanced global error handler to suppress browser extension errors
// Must be set up before any other code runs
window.addEventListener('error', (event) => {
  const filename = event.filename || '';
  const message = event.message || '';
  const stack = event.error?.stack || '';
  
  // Suppress errors from browser extensions (content.bundle.js, etc.)
  if (filename.includes('content.bundle.js') ||
      filename.includes('content.js') ||
      filename.includes('content-script') ||
      filename.includes('extension://') ||
      filename.includes('chrome-extension://') ||
      filename.includes('moz-extension://') ||
      filename.includes('safari-extension://')) {
    event.preventDefault();
    event.stopPropagation();
    // Use console.debug instead of console.warn/error so it doesn't clutter console
    if (import.meta.env.DEV) {
      console.debug('[Extension Error Suppressed]:', message);
    }
    return false;
  }
  
  // Suppress specific error patterns from extensions
  if (message.includes('parentElement') ||
      message.includes('Cannot read properties of null') ||
      message.includes('Cannot read property') && stack.includes('content.bundle')) {
    event.preventDefault();
    event.stopPropagation();
    if (import.meta.env.DEV) {
      console.debug('[Extension Error Suppressed]: parentElement error');
    }
    return false;
  }
  
  // Suppress errors that have extension stack traces
  if (stack.includes('content.bundle') ||
      stack.includes('extension://') ||
      stack.includes('chrome-extension://')) {
    event.preventDefault();
    event.stopPropagation();
    if (import.meta.env.DEV) {
      console.debug('[Extension Error Suppressed]: stack trace from extension');
    }
    return false;
  }
}, true); // Use capture phase to catch errors early

// Also handle unhandled promise rejections from extensions
window.addEventListener('unhandledrejection', (event) => {
  const reason = event.reason?.toString() || '';
  const stack = event.reason?.stack || '';
  
  if (reason.includes('content.bundle') ||
      reason.includes('extension://') ||
      stack.includes('content.bundle') ||
      stack.includes('extension://')) {
    event.preventDefault();
    if (import.meta.env.DEV) {
      console.debug('[Extension Promise Rejection Suppressed]:', reason);
    }
  }
});

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
