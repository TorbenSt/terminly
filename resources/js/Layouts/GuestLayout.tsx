import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="flex min-h-screen flex-col items-center bg-slate-50 pt-6 sm:justify-center sm:pt-0">
            <div className="text-center">
                <Link href="/" className="inline-flex flex-col items-center gap-2">
                    <ApplicationLogo className="h-14 w-14 text-teal-600" />
                    <span className="text-lg font-bold tracking-tight text-slate-900">
                        Termin<span className="text-teal-600">Buddy</span>
                    </span>
                </Link>
            </div>

            <div className="mt-6 w-full overflow-hidden border border-slate-200 bg-white px-6 py-4 shadow-sm sm:max-w-md sm:rounded-xl">
                {children}
            </div>
        </div>
    );
}
