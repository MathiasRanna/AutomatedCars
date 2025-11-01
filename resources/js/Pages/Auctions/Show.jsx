import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import PrimaryButton from '@/Components/PrimaryButton';
import { useState, useEffect } from 'react';

export default function AuctionShow({ auction, images, formattedPost, customPost }) {
    const [isSaving, setIsSaving] = useState(false);
    const [saveMessage, setSaveMessage] = useState('');
    
    // Debug: Log images on component mount
    useEffect(() => {
        console.log('Auction Show - Images received:', images);
        console.log('Auction Show - Images count:', images?.length || 0);
        if (images && images.length > 0) {
            console.log('First image URL:', images[0].url);
        }
    }, [images]);
    
    const { data, setData, put, processing, errors } = useForm({
        custom_post: customPost || formattedPost || '',
    });

    const submit = (e) => {
        e.preventDefault();
        setIsSaving(true);
        setSaveMessage('');
        put(route('auctions.update-post', auction.id), {
            onSuccess: () => {
                setIsSaving(false);
                setSaveMessage('Post saved successfully!');
                setTimeout(() => setSaveMessage(''), 3000);
            },
            onFinish: () => {
                setIsSaving(false);
            },
        });
    };

    // Extract date from first image path if available, or use auction_date
    const getDateFromImages = () => {
        if (images && images.length > 0) {
            const path = images[0].path;
            const match = path.match(/auctions\/(\d{4}-\d{2}-\d{2})\//);
            if (match) {
                return match[1];
            }
        }
        return auction.auction_date || new Date().toISOString().split('T')[0];
    };

    const dateForNavigation = getDateFromImages();

    const formattedDate = auction.auction_date 
        ? new Date(auction.auction_date).toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        })
        : null;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-4">
                    <Link
                        href={route('auctions.date', dateForNavigation)}
                        className="text-blue-600 hover:text-blue-800"
                    >
                        ← Back to Cars
                    </Link>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        {auction.make} {auction.model} {auction.year}
                    </h2>
                </div>
            }
        >
            <Head title={`${auction.make} ${auction.model}`} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* Left Column: Images */}
                        <div className="space-y-4">
                            <div className="bg-white shadow-sm rounded-lg p-6">
                                <h3 className="text-lg font-semibold mb-4">Car Images ({images?.length || 0})</h3>
                                {!images || images.length === 0 ? (
                                    <p className="text-gray-500 text-center py-8">No images available</p>
                                ) : (
                                    <div className="grid grid-cols-2 gap-4 max-h-[600px] overflow-y-auto">
                                        {images.map((image) => (
                                            <div
                                                key={image.id}
                                                className="relative border border-gray-300 rounded-lg overflow-hidden bg-gray-100"
                                            >
                                                <img
                                                    src={image.url}
                                                    alt={image.is_sheet ? 'Auction Sheet' : `Image ${image.position}`}
                                                    className="w-full h-auto object-cover min-h-[150px]"
                                                    loading="lazy"
                                                    onError={(e) => {
                                                        console.error('Image failed to load:', image.url);
                                                        e.target.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="200" height="200"%3E%3Crect fill="%23ddd" width="200" height="200"/%3E%3Ctext fill="%23999" font-family="sans-serif" font-size="14" x="50%25" y="50%25" text-anchor="middle" dy=".3em"%3EImage not found%3C/text%3E%3C/svg%3E';
                                                    }}
                                                />
                                                {image.is_sheet && (
                                                    <div className="absolute top-2 right-2 bg-blue-600 text-white text-xs px-2 py-1 rounded">
                                                        Sheet
                                                    </div>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Right Column: Post Editor */}
                        <div className="space-y-4">
                            <div className="bg-white shadow-sm rounded-lg p-6">
                                <div className="flex justify-between items-center mb-4">
                                    <h3 className="text-lg font-semibold">Auction Post</h3>
                                    <span className={`px-2 py-1 rounded text-xs ${
                                        auction.status === 'processed' ? 'bg-green-100 text-green-800' :
                                        auction.status === 'processing' ? 'bg-yellow-100 text-yellow-800' :
                                        auction.status === 'failed' ? 'bg-red-100 text-red-800' :
                                        'bg-gray-100 text-gray-800'
                                    }`}>
                                        {auction.status}
                                    </span>
                                </div>

                                <form onSubmit={submit} className="space-y-4">
                                    <div>
                                        <textarea
                                            value={data.custom_post}
                                            onChange={(e) => setData('custom_post', e.target.value)}
                                            rows={20}
                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm"
                                            placeholder="Enter or edit your auction post here..."
                                        />
                                        {errors.custom_post && (
                                            <div className="mt-1 text-sm text-red-600">
                                                {errors.custom_post}
                                            </div>
                                        )}
                                    </div>

                                    {saveMessage && (
                                        <div className="p-3 bg-green-100 border border-green-300 rounded-md text-sm text-green-800">
                                            {saveMessage}
                                        </div>
                                    )}

                                    <div className="flex justify-end gap-2">
                                        <Link
                                            href={route('auctions.date', dateForNavigation)}
                                            className="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300"
                                        >
                                            Cancel
                                        </Link>
                                        <PrimaryButton disabled={processing || isSaving}>
                                            {processing || isSaving ? 'Saving...' : 'Save Post'}
                                        </PrimaryButton>
                                    </div>
                                </form>

                                {auction.status === 'processed' && (
                                    <div className="mt-4 p-4 bg-green-50 border border-green-200 rounded-md">
                                        <p className="text-sm text-green-800">
                                            ✅ AI processing completed. You can customize the post above.
                                        </p>
                                    </div>
                                )}

                                {auction.status === 'processing' && (
                                    <div className="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
                                        <p className="text-sm text-yellow-800">
                                            ⏳ AI is currently processing this auction. Please wait...
                                        </p>
                                    </div>
                                )}
                            </div>

                            {/* Car Info Summary */}
                            <div className="bg-white shadow-sm rounded-lg p-6">
                                <h3 className="text-lg font-semibold mb-4">Car Information</h3>
                                <div className="space-y-2 text-sm">
                                    <div><strong>Price:</strong> {parseInt(auction.price || 0).toLocaleString('et-EE')}€</div>
                                    {formattedDate && <div><strong>Auction Date:</strong> {formattedDate}</div>}
                                    {auction.bid_deadline && <div><strong>Bid Deadline:</strong> {auction.bid_deadline}</div>}
                                    {auction.custom_folder_name && (
                                        <div><strong>Folder:</strong> {auction.custom_folder_name}</div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

