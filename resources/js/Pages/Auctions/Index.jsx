import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function AuctionsIndex({ dates }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Auction Dates
                </h2>
            }
        >
            <Head title="Auction Dates" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <h3 className="text-lg font-semibold mb-4">Select a date to view cars</h3>
                            
                            {dates.length === 0 ? (
                                <p className="text-gray-500">No auctions found.</p>
                            ) : (
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    {dates.map((dateItem) => (
                                        <Link
                                            key={dateItem.date}
                                            href={route('auctions.date', dateItem.date)}
                                            className="block p-4 border border-gray-300 rounded-lg hover:bg-gray-50 hover:shadow-md transition"
                                        >
                                            <div className="flex justify-between items-center">
                                                <div>
                                                    <div className="text-lg font-semibold text-gray-900">
                                                        {new Date(dateItem.date).toLocaleDateString('en-US', {
                                                            weekday: 'long',
                                                            year: 'numeric',
                                                            month: 'long',
                                                            day: 'numeric'
                                                        })}
                                                    </div>
                                                    <div className="text-sm text-gray-500 mt-1">
                                                        {dateItem.date}
                                                    </div>
                                                </div>
                                                <div className="text-right">
                                                    <div className="text-2xl font-bold text-blue-600">
                                                        {dateItem.car_count}
                                                    </div>
                                                    <div className="text-xs text-gray-500">
                                                        {dateItem.car_count === 1 ? 'car' : 'cars'}
                                                    </div>
                                                </div>
                                            </div>
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

