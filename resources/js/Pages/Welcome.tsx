import ApplicationLogo from '@/Components/ApplicationLogo';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    CalendarDays,
    Clock,
    Mail,
    Sparkles,
    Users,
    Wrench,
} from 'lucide-react';

const features = [
    {
        icon: CalendarDays,
        title: 'Intelligente Terminplanung',
        description:
            'Automatische Vorschläge, Verhandlungslinks für Kunden und klare Kalenderübersicht für dein Team.',
    },
    {
        icon: Users,
        title: 'Kunden & Mitarbeiter',
        description:
            'Verwalte Kunden, wiederkehrende Leistungen und Arbeitszeiten — alles an einem Ort.',
    },
    {
        icon: Wrench,
        title: 'Leistungen & Wartungen',
        description:
            'Servicearten definieren, fällige Wartungen erkennen und Termine gezielt vorschlagen.',
    },
    {
        icon: Sparkles,
        title: 'KI-Kundensuche',
        description:
            'Finde neue Interessenten in deiner Region und starte Outreach direkt aus der App.',
    },
];

export default function Welcome({
    auth,
    canLogin,
    canRegister,
}: PageProps<{ canLogin: boolean; canRegister: boolean }>) {
    return (
        <>
            <Head title="TerminBuddy — Termine planen, Kunden binden" />

            <div className="min-h-screen bg-slate-50 text-slate-900">
                <div className="pointer-events-none absolute inset-x-0 top-0 h-[520px] overflow-hidden">
                    <div className="absolute -left-24 top-0 h-72 w-72 rounded-full bg-teal-400/30 blur-3xl" />
                    <div className="absolute right-0 top-12 h-80 w-80 rounded-full bg-indigo-400/25 blur-3xl" />
                </div>

                <header className="relative z-10 mx-auto flex max-w-6xl items-center justify-between px-6 py-6">
                    <Link href="/" className="flex items-center gap-3">
                        <ApplicationLogo className="h-10 w-10 text-teal-600" />
                        <span className="text-xl font-bold tracking-tight">
                            Termin<span className="text-teal-600">Buddy</span>
                        </span>
                    </Link>

                    <nav className="flex items-center gap-2 sm:gap-3">
                        {auth.user ? (
                            <Button asChild>
                                <Link href={route('dashboard')}>Zum Dashboard</Link>
                            </Button>
                        ) : (
                            <>
                                {canLogin && (
                                    <Button variant="ghost" asChild className="hidden sm:inline-flex">
                                        <Link href={route('login')}>Anmelden</Link>
                                    </Button>
                                )}
                                {canRegister && (
                                    <Button
                                        asChild
                                        className="bg-teal-600 text-white hover:bg-teal-700"
                                    >
                                        <Link href={route('register')}>Kostenlos starten</Link>
                                    </Button>
                                )}
                            </>
                        )}
                    </nav>
                </header>

                <main className="relative z-10">
                    <section className="mx-auto max-w-6xl px-6 pb-20 pt-10 md:pb-28 md:pt-16">
                        <div className="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
                            <div>
                                <p className="mb-4 inline-flex items-center gap-2 rounded-full border border-teal-200 bg-teal-50 px-3 py-1 text-sm font-medium text-teal-800">
                                    <Clock className="h-4 w-4" />
                                    Terminplanung für Dienstleister
                                </p>

                                <h1 className="text-4xl font-bold leading-tight tracking-tight text-slate-900 md:text-5xl lg:text-6xl">
                                    Weniger Koordination.
                                    <span className="block text-teal-600">Mehr erledigte Termine.</span>
                                </h1>

                                <p className="mt-6 max-w-xl text-lg leading-relaxed text-slate-600">
                                    TerminBuddy hilft Handwerks- und Servicebetrieben, Termine zu planen,
                                    Kunden zu binden und den Überblick über Mitarbeiter und Leistungen
                                    zu behalten — ohne Excel-Chaos.
                                </p>

                                <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                                    {auth.user ? (
                                        <Button
                                            size="lg"
                                            asChild
                                            className="bg-teal-600 text-white hover:bg-teal-700"
                                        >
                                            <Link href={route('dashboard')}>Dashboard öffnen</Link>
                                        </Button>
                                    ) : (
                                        <>
                                            {canRegister && (
                                                <Button
                                                    size="lg"
                                                    asChild
                                                    className="bg-teal-600 text-white hover:bg-teal-700"
                                                >
                                                    <Link href={route('register')}>
                                                        Jetzt kostenlos registrieren
                                                    </Link>
                                                </Button>
                                            )}
                                            {canLogin && (
                                                <Button size="lg" variant="outline" asChild>
                                                    <Link href={route('login')}>Anmelden</Link>
                                                </Button>
                                            )}
                                        </>
                                    )}
                                </div>

                                <p className="mt-4 text-sm text-slate-500">
                                    Keine Kreditkarte nötig · In wenigen Minuten eingerichtet
                                </p>
                            </div>

                            <div className="relative mx-auto w-full max-w-lg lg:max-w-none">
                                <div className="absolute -inset-4 rounded-3xl bg-gradient-to-br from-teal-500/20 to-indigo-500/20 blur-2xl" />
                                <Card className="relative overflow-hidden border-slate-200/80 shadow-2xl shadow-slate-900/10">
                                    <div className="border-b border-slate-100 bg-slate-900 px-5 py-4 text-white">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <p className="text-xs uppercase tracking-wider text-slate-400">
                                                    Heute
                                                </p>
                                                <p className="text-lg font-semibold">Dein Terminüberblick</p>
                                            </div>
                                            <div className="rounded-lg bg-teal-500/20 px-3 py-1 text-sm font-medium text-teal-200">
                                                4 Termine
                                            </div>
                                        </div>
                                    </div>
                                    <CardContent className="space-y-3 p-5">
                                        {[
                                            {
                                                time: '09:00',
                                                title: 'Wartung Heizung',
                                                customer: 'Müller GmbH',
                                                tone: 'bg-teal-50 text-teal-700',
                                            },
                                            {
                                                time: '11:30',
                                                title: 'Erstberatung',
                                                customer: 'Schmidt & Co.',
                                                tone: 'bg-indigo-50 text-indigo-700',
                                            },
                                            {
                                                time: '14:00',
                                                title: 'Inspektion',
                                                customer: 'Weber Service',
                                                tone: 'bg-amber-50 text-amber-700',
                                            },
                                        ].map((item) => (
                                            <div
                                                key={item.time}
                                                className="flex items-center gap-4 rounded-xl border border-slate-100 bg-white p-4"
                                            >
                                                <div className="min-w-[3.5rem] text-sm font-semibold text-slate-500">
                                                    {item.time}
                                                </div>
                                                <div className="flex-1">
                                                    <p className="font-medium text-slate-900">{item.title}</p>
                                                    <p className="text-sm text-slate-500">{item.customer}</p>
                                                </div>
                                                <span
                                                    className={`rounded-full px-2.5 py-1 text-xs font-medium ${item.tone}`}
                                                >
                                                    Bestätigt
                                                </span>
                                            </div>
                                        ))}
                                        <div className="flex items-center gap-2 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500">
                                            <Mail className="h-4 w-4 shrink-0" />
                                            Automatische Erinnerungen & Verhandlungslinks inklusive
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    </section>

                    <section className="border-y border-slate-200/80 bg-white py-20">
                        <div className="mx-auto max-w-6xl px-6">
                            <div className="mx-auto max-w-2xl text-center">
                                <h2 className="text-3xl font-bold tracking-tight text-slate-900">
                                    Alles, was dein Betrieb braucht
                                </h2>
                                <p className="mt-4 text-lg text-slate-600">
                                    Von der ersten Anfrage bis zum bestätigten Termin — TerminBuddy
                                    begleitet dich durch den gesamten Ablauf.
                                </p>
                            </div>

                            <div className="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                                {features.map((feature) => (
                                    <Card
                                        key={feature.title}
                                        className="border-slate-200/80 shadow-sm transition-shadow hover:shadow-md"
                                    >
                                        <CardContent className="p-6">
                                            <div className="mb-4 inline-flex rounded-xl bg-teal-50 p-3 text-teal-600">
                                                <feature.icon className="h-6 w-6" />
                                            </div>
                                            <h3 className="text-lg font-semibold text-slate-900">
                                                {feature.title}
                                            </h3>
                                            <p className="mt-2 text-sm leading-relaxed text-slate-600">
                                                {feature.description}
                                            </p>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        </div>
                    </section>

                    {!auth.user && (canLogin || canRegister) && (
                        <section className="px-6 py-20">
                            <div className="mx-auto max-w-4xl rounded-3xl bg-gradient-to-br from-teal-600 to-indigo-700 px-8 py-12 text-center text-white shadow-xl shadow-teal-900/20 md:px-12">
                                <h2 className="text-3xl font-bold tracking-tight md:text-4xl">
                                    Bereit für entspanntere Terminplanung?
                                </h2>
                                <p className="mx-auto mt-4 max-w-2xl text-lg text-teal-50">
                                    Erstelle dein Konto und richte dein Team, deine Leistungen und
                                    deinen Kalender in wenigen Schritten ein.
                                </p>
                                <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                                    {canRegister && (
                                        <Button
                                            size="lg"
                                            asChild
                                            className="bg-white text-teal-700 hover:bg-teal-50"
                                        >
                                            <Link href={route('register')}>Kostenlos registrieren</Link>
                                        </Button>
                                    )}
                                    {canLogin && (
                                        <Button
                                            size="lg"
                                            variant="outline"
                                            asChild
                                            className="border-white/30 bg-transparent text-white hover:bg-white/10 hover:text-white"
                                        >
                                            <Link href={route('login')}>Ich habe bereits ein Konto</Link>
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </section>
                    )}
                </main>

                <footer className="relative z-10 border-t border-slate-200/80 bg-white py-8">
                    <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-6 text-sm text-slate-500 sm:flex-row">
                        <div className="flex items-center gap-2">
                            <ApplicationLogo className="h-6 w-6 text-teal-600" />
                            <span className="font-medium text-slate-700">TerminBuddy</span>
                        </div>
                        <p>© {new Date().getFullYear()} TerminBuddy. Termine planen, Kunden binden.</p>
                    </div>
                </footer>
            </div>
        </>
    );
}
