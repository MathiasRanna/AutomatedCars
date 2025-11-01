import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, router } from '@inertiajs/react';
import PrimaryButton from '@/Components/PrimaryButton';
import { useState, useEffect } from 'react';

export default function AuctionShow({ auction, images, formattedPost, customPost }) {
    const [isSaving, setIsSaving] = useState(false);
    const [saveMessage, setSaveMessage] = useState('');
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);
    const [copyMessage, setCopyMessage] = useState('');
    const [copiedImageId, setCopiedImageId] = useState(null);
    
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

    const handleDelete = () => {
        if (!showDeleteConfirm) {
            setShowDeleteConfirm(true);
            return;
        }

        setIsDeleting(true);
        router.delete(route('auctions.destroy', auction.id), {
            onSuccess: () => {
                // Redirect happens automatically via Inertia
            },
            onError: () => {
                setIsDeleting(false);
                setShowDeleteConfirm(false);
            },
        });
    };

    const handleCopy = async () => {
        try {
            await navigator.clipboard.writeText(data.custom_post);
            setCopyMessage('Copied to clipboard!');
            setTimeout(() => setCopyMessage(''), 3000);
        } catch (err) {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = data.custom_post;
            textArea.style.position = 'fixed';
            textArea.style.opacity = '0';
            document.body.appendChild(textArea);
            textArea.select();
            try {
                document.execCommand('copy');
                setCopyMessage('Copied to clipboard!');
                setTimeout(() => setCopyMessage(''), 3000);
            } catch (fallbackErr) {
                setCopyMessage('Failed to copy');
                setTimeout(() => setCopyMessage(''), 3000);
            }
            document.body.removeChild(textArea);
        }
    };

    const handleCopyImage = async (imageUrl, imageId) => {
        try {
            // Convert relative URL to absolute if needed
            const absoluteUrl = imageUrl.startsWith('http') 
                ? imageUrl 
                : `${window.location.origin}${imageUrl}`;
            
            // Check if ClipboardItem API is available
            if (!window.ClipboardItem || !navigator.clipboard?.write) {
                // Fallback: copy image URL as text
                await navigator.clipboard.writeText(absoluteUrl);
                setCopiedImageId(imageId);
                setTimeout(() => setCopiedImageId(null), 2000);
                return;
            }
            
            // Use canvas to ensure we get proper image data
            const img = new Image();
            
            // Wait for image to load
            await new Promise((resolve, reject) => {
                img.onload = resolve;
                img.onerror = () => reject(new Error('Image failed to load'));
                img.src = absoluteUrl;
            });
            
            // Create canvas and draw image
            const canvas = document.createElement('canvas');
            canvas.width = img.naturalWidth || img.width;
            canvas.height = img.naturalHeight || img.height;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0);
            
            // Convert canvas to blob and copy to clipboard
            canvas.toBlob(async (blob) => {
                if (!blob) {
                    throw new Error('Failed to create blob from canvas');
                }
                
                try {
                    // Create ClipboardItem with proper MIME type
                    const clipboardItem = new ClipboardItem({
                        'image/png': blob  // Use PNG format for best compatibility
                    });
                    
                    await navigator.clipboard.write([clipboardItem]);
                    setCopiedImageId(imageId);
                    setTimeout(() => setCopiedImageId(null), 2000);
                } catch (clipboardErr) {
                    console.error('Clipboard write failed:', clipboardErr);
                    // Final fallback: copy URL as text
                    try {
                        await navigator.clipboard.writeText(absoluteUrl);
                        setCopiedImageId(imageId);
                        setTimeout(() => setCopiedImageId(null), 2000);
                    } catch (fallbackErr) {
                        console.error('Failed to copy image URL:', fallbackErr);
                        alert('Failed to copy image. Please check browser permissions for clipboard access.');
                    }
                }
            }, 'image/png'); // Always use PNG for clipboard compatibility
            
        } catch (err) {
            console.error('Failed to copy image:', err);
            // Fallback: try to copy URL as text
            try {
                const absoluteUrl = imageUrl.startsWith('http') 
                    ? imageUrl 
                    : `${window.location.origin}${imageUrl}`;
                await navigator.clipboard.writeText(absoluteUrl);
                setCopiedImageId(imageId);
                setTimeout(() => setCopiedImageId(null), 2000);
            } catch (fallbackErr) {
                console.error('Failed to copy image URL:', fallbackErr);
                alert('Failed to copy image. Please check browser permissions for clipboard access.');
            }
        }
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
                                                className="relative border border-gray-300 rounded-lg overflow-hidden bg-gray-100 group"
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
                                                {image.is_sheet ? (
                                                    <div className="absolute top-2 right-2 bg-blue-600 text-white text-xs px-2 py-1 rounded">
                                                        Sheet
                                                    </div>
                                                ) : null}
                                                <button
                                                    onClick={() => handleCopyImage(image.url, image.id)}
                                                    className={`absolute bottom-2 left-1/2 transform -translate-x-1/2 px-3 py-1.5 rounded-md text-xs font-medium transition-all duration-200 shadow-lg ${
                                                        copiedImageId === image.id
                                                            ? 'bg-green-600 text-white opacity-100'
                                                            : 'bg-blue-600 text-white opacity-90 hover:opacity-100 hover:bg-blue-700'
                                                    }`}
                                                    title="Click to copy image to clipboard"
                                                >
                                                    {copiedImageId === image.id ? '✓ Copied!' : '📋 Copy'}
                                                </button>
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
                                    <div className="flex items-center gap-2">
                                        <button
                                            type="button"
                                            onClick={handleCopy}
                                            className="px-3 py-1 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition"
                                            title="Copy post text to clipboard"
                                        >
                                            📋 Copy
                                        </button>
                                        <span className={`px-2 py-1 rounded text-xs ${
                                            auction.status === 'processed' ? 'bg-green-100 text-green-800' :
                                            auction.status === 'processing' ? 'bg-yellow-100 text-yellow-800' :
                                            auction.status === 'pending_processing' ? 'bg-blue-100 text-blue-800' :
                                            auction.status === 'downloading' ? 'bg-purple-100 text-purple-800' :
                                            auction.status === 'failed' ? 'bg-red-100 text-red-800' :
                                            'bg-gray-100 text-gray-800'
                                        }`}>
                                            {auction.status === 'pending_processing' ? 'queued' : auction.status}
                                        </span>
                                    </div>
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
                                    {copyMessage && (
                                        <div className={`p-3 border rounded-md text-sm ${
                                            copyMessage.includes('Failed') 
                                                ? 'bg-red-100 border-red-300 text-red-800' 
                                                : 'bg-blue-100 border-blue-300 text-blue-800'
                                        }`}>
                                            {copyMessage}
                                        </div>
                                    )}

                                    <div className="flex justify-between items-center">
                                        <button
                                            type="button"
                                            onClick={handleDelete}
                                            disabled={isDeleting}
                                            className="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed text-sm"
                                        >
                                            {isDeleting ? 'Deleting...' : (showDeleteConfirm ? 'Confirm Delete' : 'Delete Auction')}
                                        </button>
                                        <div className="flex gap-2">
                                            {showDeleteConfirm && (
                                                <button
                                                    type="button"
                                                    onClick={() => setShowDeleteConfirm(false)}
                                                    className="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400 text-sm"
                                                >
                                                    Cancel
                                                </button>
                                            )}
                                            <Link
                                                href={route('auctions.date', dateForNavigation)}
                                                className="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300"
                                            >
                                                Back
                                            </Link>
                                            <PrimaryButton disabled={processing || isSaving}>
                                                {processing || isSaving ? 'Saving...' : 'Save Post'}
                                            </PrimaryButton>
                                        </div>
                                    </div>
                                    {showDeleteConfirm && (
                                        <div className="mt-2 p-3 bg-red-50 border border-red-200 rounded-md">
                                            <p className="text-sm text-red-800">
                                                ⚠️ Are you sure? This will permanently delete all images and data for this auction. This action cannot be undone.
                                            </p>
                                        </div>
                                    )}
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

