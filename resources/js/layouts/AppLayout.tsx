import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import toast from 'react-hot-toast';

export default function AppLayout({ children }: { children: React.ReactNode }) {
    const { props } = usePage();
    const toastData: any = props.toast;

    useEffect(() => {
        if (toastData) {
            if (toastData.status === 'success') {
                toast.success(toastData.message);
            } else {
                toast.error(toastData.message);
            }
        }
    }, [toastData]);

    return <div className="min-h-screen bg-gray-50">{children}</div>;
}
