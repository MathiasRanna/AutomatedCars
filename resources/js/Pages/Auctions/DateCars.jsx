import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function DateCars({ date, auctions }) {
    const formattedDate = new Date(date).toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-4">
                    <Link
                        href={route('auctions.index')}
                        className="text-blue-600 hover:text-blue-800"
                    >
                        ← Back to Dates
                    </Link>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Cars from {formattedDate}
                    </h2>
                </div>
            }
        >
            <Head title={`Cars - ${date}`} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            {auctions.length === 0 ? (
                                <p className="text-gray-500">No cars found for this date.</p>
                            ) : (
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    {auctions.map((auction) => (
                                        <Link
                                            key={auction.id}
                                            href={route('auctions.show', auction.id)}
                                            className="block p-4 border border-gray-300 rounded-lg hover:bg-gray-50 hover:shadow-md transition"
                                        >
                                            <div className="space-y-2">
                                                <div className="text-lg font-semibold">
                                                    {auction.make} {auction.model} {auction.year}
                                                </div>
                                                <div className="text-sm text-gray-600">
                                                    💶 {parseInt(auction.price).toLocaleString('et-EE')}€
                                                </div>
                                                <div className="flex items-center gap-2 text-xs text-gray-500">
                                                    <span className={`px-2 py-1 rounded ${
                                                        auction.status === 'processed' ? 'bg-green-100 text-green-800' :
                                                        auction.status === 'processing' ? 'bg-yellow-100 text-yellow-800' :
                                                        auction.status === 'failed' ? 'bg-red-100 text-red-800' :
                                                        'bg-gray-100 text-gray-800'
                                                    }`}>
                                                        {auction.status}
                                                    </span>
                                                    <span>📷 {auction.image_count} images</span>
                                                    {!auction.has_extracted_data && (
                                                        <span className="text-orange-600">⚠️ Processing</span>
                                                    )}
                                                </div>
                                                {auction.custom_folder_name && (
                                                    <div className="text-xs text-gray-400">
                                                        {auction.custom_folder_name}
                                                    </div>
                                                )}
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

