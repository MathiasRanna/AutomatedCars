import ApplicationLogo from '@/Components/ApplicationLogo';
import PrimaryButton from '@/Components/PrimaryButton';
import { Head, Link } from '@inertiajs/react';

export default function Welcome({ auth }) {
    return (
        <>
            <Head title="Welcome" />
            <div className="flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:justify-center sm:pt-0">
                <div className="text-center">
                    <Link href="/" className="inline-block">
                        <ApplicationLogo />
                    </Link>
                    <h1 className="mt-6 text-3xl font-bold text-gray-900">
                        Boiler plate
                    </h1>
                    <p className="mt-4 text-lg text-gray-600 max-w-md mx-auto">
                        Laravel 12 boilerplate for finfratech
                    </p>
                </div>

                <div className="mt-8 w-full overflow-hidden bg-white px-6 py-8 shadow-md sm:max-w-md sm:rounded-lg">
                    <div className="text-center">
                        {auth.user ? (
                            <div>
                                <p className="text-sm text-gray-600 mb-4">
                                    Welcome back, {auth.user.name}!
                                </p>
                                <Link href={route('dashboard')}>
                                    <PrimaryButton className="w-full">
                                        Go to Dashboard
                                    </PrimaryButton>
                                </Link>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                <Link href={route('login')}>
                                    <PrimaryButton className="w-full">
                                        Log in
                                    </PrimaryButton>
                                </Link>
                                <Link href={route('register')}>
                                    <PrimaryButton className="w-full bg-gray-600 hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900">
                                        Register
                                    </PrimaryButton>
                                </Link>
                            </div>
                        )}
                    </div>
                </div>

                <footer className="mt-8 text-center text-sm text-gray-600">
                    FinfraTech OÜ
                </footer>
            </div>
        </>
    );
}
