import { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Search, Package, ChevronLeft, ChevronRight } from 'lucide-react';
import axios from 'axios';

interface Product {
    id: number;
    sku: string;
    name: string;
    description?: string;
    price: number;
    category?: string;
    stock: number;
    primary_image?: {
        id: number;
        url: string;
        variant: string;
    };
    created_at: string;
}

interface PaginationData {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
}

export default function Products() {
    const [products, setProducts] = useState<Product[]>([]);
    const [pagination, setPagination] = useState<PaginationData>({
        current_page: 1,
        per_page: 20,
        total: 0,
        last_page: 1,
    });
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('');

    useEffect(() => {
        fetchProducts();
    }, [pagination.current_page, search, category]);

    const fetchProducts = async () => {
        setLoading(true);
        try {
            const response = await axios.get('/api/products', {
                params: {
                    page: pagination.current_page,
                    per_page: pagination.per_page,
                    search,
                    category: category || undefined,
                },
            });

            if (response.data.success) {
                setProducts(response.data.data);
                setPagination(response.data.pagination);
            }
        } catch (error) {
            console.error('Failed to fetch products:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleSearch = (value: string) => {
        setSearch(value);
        setPagination((prev) => ({ ...prev, current_page: 1 }));
    };

    const nextPage = () => {
        if (pagination.current_page < pagination.last_page) {
            setPagination((prev) => ({ ...prev, current_page: prev.current_page + 1 }));
        }
    };

    const prevPage = () => {
        if (pagination.current_page > 1) {
            setPagination((prev) => ({ ...prev, current_page: prev.current_page - 1 }));
        }
    };

    return (
        <AppLayout>
            <Head title="Products" />

            <div className="container mx-auto py-8 px-4 max-w-7xl">
                <div className="mb-8">
                    <h1 className="text-4xl font-bold mb-2 bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                        Products
                    </h1>
                    <p className="text-gray-600 dark:text-gray-400">
                        Browse and manage imported products
                    </p>
                </div>

                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle>Search & Filter</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex gap-4">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5" />
                                <Input
                                    placeholder="Search by name or SKU..."
                                    value={search}
                                    onChange={(e) => handleSearch(e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {loading ? (
                    <div className="text-center py-12">
                        <div className="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                        <p className="mt-4 text-gray-600 dark:text-gray-400">Loading products...</p>
                    </div>
                ) : products.length === 0 ? (
                    <Card>
                        <CardContent className="py-12 text-center">
                            <Package className="w-16 h-16 text-gray-400 mx-auto mb-4" />
                            <p className="text-gray-600 dark:text-gray-400">No products found</p>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                            {products.map((product) => (
                                <ProductCard key={product.id} product={product} />
                            ))}
                        </div>

                        <div className="mt-8 flex items-center justify-between">
                            <div className="text-sm text-gray-600 dark:text-gray-400">
                                Showing {((pagination.current_page - 1) * pagination.per_page) + 1} to{' '}
                                {Math.min(pagination.current_page * pagination.per_page, pagination.total)} of{' '}
                                {pagination.total} products
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    onClick={prevPage}
                                    disabled={pagination.current_page === 1}
                                >
                                    <ChevronLeft className="w-4 h-4 mr-1" />
                                    Previous
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={nextPage}
                                    disabled={pagination.current_page === pagination.last_page}
                                >
                                    Next
                                    <ChevronRight className="w-4 h-4 ml-1" />
                                </Button>
                            </div>
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}

interface ProductCardProps {
    product: Product;
}

function ProductCard({ product }: ProductCardProps) {
    return (
        <Card className="overflow-hidden hover:shadow-lg transition-shadow">
            <div className="h-64 w-full bg-gray-100 dark:bg-gray-800 relative">
                {product.primary_image ? (
                    <img
                        src={product.primary_image.url}
                        alt={product.name}
                        className="w-full h-full object-cover"
                    />
                ) : (
                    <div className="w-full h-full flex items-center justify-center">
                        <Package className="w-16 h-16 text-gray-400" />
                    </div>
                )}
            </div>
            <CardContent className="p-4">
                <div className="space-y-2">
                    <h3 className="font-semibold truncate">{product.name}</h3>
                    <p className="text-xs text-gray-500 font-mono">{product.sku}</p>
                    {product.category && (
                        <span className="inline-block px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 text-xs rounded">
                            {product.category}
                        </span>
                    )}
                    <div className="flex items-center justify-between pt-2 border-t border-gray-200 dark:border-gray-700">
                        <span className="text-lg font-bold text-blue-600">
                            ${parseFloat(product.price.toString()).toFixed(2)}
                        </span>
                        <span className="text-sm text-gray-600 dark:text-gray-400">
                            Stock: {product.stock}
                        </span>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
