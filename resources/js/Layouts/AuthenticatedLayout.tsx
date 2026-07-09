import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { BillingStatus } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState } from 'react';

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const pageProps = usePage().props;
    const user = pageProps.auth.user;
    const billing = pageProps.billing as BillingStatus | null | undefined;
    const schedulingLab = pageProps.schedulingLab as { enabled?: boolean } | undefined;
    const configSchedulingLab = schedulingLab?.enabled ?? false;

    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);

    if (!user) {
        return null;
    }

    const roles = (user.roles as string[] | undefined) ?? [];
    const isCompanyAdmin = roles.includes('company_admin');
    const isStaffMember = roles.includes('staff');

    return (
        <div className="min-h-screen bg-gray-100">
            <nav className="border-b border-gray-100 bg-white">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 justify-between">
                        <div className="flex">
                            <div className="flex shrink-0 items-center">
                                <Link href="/" className="flex items-center gap-2">
                                    <ApplicationLogo className="block h-9 w-9 text-teal-600" />
                                    <span className="hidden font-bold tracking-tight text-slate-900 sm:inline">
                                        Termin<span className="text-teal-600">Buddy</span>
                                    </span>
                                </Link>
                            </div>

                            <div className="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink href={route('dashboard')} active={route().current('dashboard')}>
                                    Dashboard
                                </NavLink>
                                {user.is_super_admin && (
                                    <>
                                        <NavLink href={route('admin.companies.index')} active={route().current('admin.companies.*')}>
                                            Unternehmen
                                        </NavLink>
                                        <NavLink href={route('admin.plans.index')} active={route().current('admin.plans.*')}>
                                            Abos
                                        </NavLink>
                                        <NavLink href={route('admin.coupons.index')} active={route().current('admin.coupons.*')}>
                                            Gutscheine
                                        </NavLink>
                                        {configSchedulingLab && (
                                            <NavLink
                                                href={route('admin.scheduling-lab.index')}
                                                active={route().current('admin.scheduling-lab.*')}
                                            >
                                                Scheduling Lab
                                            </NavLink>
                                        )}
                                    </>
                                )}
                                {!user.is_super_admin && (
                                    <>
                                        <NavLink href={route('customers.index')} active={route().current('customers.*')}>
                                            Kunden
                                        </NavLink>
                                        <NavLink href={route('service-types.index')} active={route().current('service-types.*')}>
                                            Services
                                        </NavLink>
                                        {isCompanyAdmin && (
                                            <NavLink href={route('prospects.index')} active={route().current('prospects.*')}>
                                                Kundensuche
                                            </NavLink>
                                        )}
                                        {isCompanyAdmin && (
                                            <NavLink href={route('staff.index')} active={route().current('staff.*')}>
                                                Mitarbeiter
                                            </NavLink>
                                        )}
                                        {isStaffMember && !isCompanyAdmin && (
                                            <NavLink
                                                href={route('working-hours.index')}
                                                active={route().current('working-hours.*')}
                                            >
                                                Arbeitszeiten
                                            </NavLink>
                                        )}
                                        <NavLink href={route('appointments.index')} active={route().current('appointments.*')}>
                                            Termine
                                        </NavLink>
                                        <NavLink href={route('staff.calendar')} active={route().current('staff.calendar')}>
                                            Kalender
                                        </NavLink>
                                        {isCompanyAdmin && (
                                            <NavLink href={route('billing.index')} active={route().current('billing.*')}>
                                                Abo
                                            </NavLink>
                                        )}
                                    </>
                                )}
                            </div>
                        </div>

                        <div className="hidden sm:ms-6 sm:flex sm:items-center">
                            <div className="relative ms-3">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                className="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                            >
                                                {user.name}

                                                <svg
                                                    className="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fillRule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clipRule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content>
                                        <Dropdown.Link
                                            href={route('profile.edit')}
                                        >
                                            Profile
                                        </Dropdown.Link>
                                        <Dropdown.Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>
                        </div>

                        <div className="-me-2 flex items-center sm:hidden">
                            <button
                                onClick={() =>
                                    setShowingNavigationDropdown(
                                        (previousState) => !previousState,
                                    )
                                }
                                className="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                            >
                                <svg
                                    className="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        className={
                                            !showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        className={
                                            showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    className={
                        (showingNavigationDropdown ? 'block' : 'hidden') +
                        ' sm:hidden'
                    }
                >
                    <div className="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            href={route('dashboard')}
                            active={route().current('dashboard')}
                        >
                            Dashboard
                        </ResponsiveNavLink>
                    </div>

                    <div className="border-t border-gray-200 pb-1 pt-4">
                        <div className="px-4">
                            <div className="text-base font-medium text-gray-800">
                                {user.name}
                            </div>
                            <div className="text-sm font-medium text-gray-500">
                                {user.email}
                            </div>
                        </div>

                        <div className="mt-3 space-y-1">
                            <ResponsiveNavLink href={route('profile.edit')}>
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                method="post"
                                href={route('logout')}
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            {billing?.read_only && (
                <div className="bg-red-600 px-4 py-2 text-center text-sm text-white">
                    Kein aktives Abo – Daten können nur gelesen werden.{' '}
                    {isCompanyAdmin ? (
                        <Link href={route('billing.index')} className="font-semibold underline">
                            Jetzt Abo abschließen
                        </Link>
                    ) : (
                        'Bitte wenden Sie sich an Ihren Administrator.'
                    )}
                </div>
            )}

            {billing && !billing.read_only && billing.on_trial && !billing.subscribed && (
                <div className="bg-amber-500 px-4 py-2 text-center text-sm text-white">
                    Testzeitraum bis {billing.trial_ends_at}.{' '}
                    {isCompanyAdmin && (
                        <Link href={route('billing.index')} className="font-semibold underline">
                            Abo auswählen
                        </Link>
                    )}
                </div>
            )}

            {header && (
                <header className="bg-white shadow">
                    <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        {header}
                    </div>
                </header>
            )}

            <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">{children}</main>
        </div>
    );
}
