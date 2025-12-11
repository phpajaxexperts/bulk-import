import { useState } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import CSVImporter from '@/components/import/CSVImporter';
import ImageUploader from '@/components/import/ImageUploader';
import ImportResults from '@/components/import/ImportResults';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

interface ImportResult {
    import_log_id: number;
    status: string;
    statistics: {
        total_rows: number;
        imported: number;
        updated: number;
        invalid: number;
        duplicates: number;
    };
    errors?: any[];
}

export default function Import() {
    const [importResult, setImportResult] = useState<ImportResult | null>(null);

    return (
        <AppLayout>
            <Head title="Bulk Import" />

            <div className="container mx-auto py-8 px-4 max-w-7xl">
                <div className="mb-8">
                    <h1 className="text-4xl font-bold mb-2 bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                        Bulk Import System
                    </h1>
                    <p className="text-gray-600 dark:text-gray-400">
                        Import products from CSV and upload product images with chunked upload support
                    </p>
                </div>

                <Tabs defaultValue="csv" className="w-full">
                    <TabsList className="grid w-full grid-cols-2 mb-6">
                        <TabsTrigger value="csv">CSV Import</TabsTrigger>
                        <TabsTrigger value="images">Image Upload</TabsTrigger>
                    </TabsList>

                    <TabsContent value="csv">
                        <div className="grid gap-6 lg:grid-cols-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Upload CSV File</CardTitle>
                                    <CardDescription>
                                        Import products from a CSV file. Required columns: sku, name, price
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <CSVImporter onImportComplete={setImportResult} />
                                </CardContent>
                            </Card>

                            {importResult && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Import Results</CardTitle>
                                        <CardDescription>
                                            Summary of the import operation
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <ImportResults result={importResult} />
                                    </CardContent>
                                </Card>
                            )}
                        </div>
                    </TabsContent>

                    <TabsContent value="images">
                        <Card>
                            <CardHeader>
                                <CardTitle>Upload Product Images</CardTitle>
                                <CardDescription>
                                    Drag and drop images or click to browse. Supports chunked upload with resume capability.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <ImageUploader />
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>

                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle>CSV Format Guide</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <div>
                                <h3 className="font-semibold mb-2">Required Columns:</h3>
                                <ul className="list-disc list-inside space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                    <li><code className="bg-gray-100 dark:bg-gray-800 px-1 rounded">sku</code> - Unique product identifier</li>
                                    <li><code className="bg-gray-100 dark:bg-gray-800 px-1 rounded">name</code> - Product name</li>
                                    <li><code className="bg-gray-100 dark:bg-gray-800 px-1 rounded">price</code> - Product price (numeric)</li>
                                </ul>
                            </div>
                            <div>
                                <h3 className="font-semibold mb-2">Optional Columns:</h3>
                                <ul className="list-disc list-inside space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                    <li><code className="bg-gray-100 dark:bg-gray-800 px-1 rounded">category</code> - Product category</li>
                                    <li><code className="bg-gray-100 dark:bg-gray-800 px-1 rounded">stock</code> - Stock quantity (integer)</li>
                                    <li><code className="bg-gray-100 dark:bg-gray-800 px-1 rounded">description</code> - Product description</li>
                                </ul>
                            </div>
                            <div className="bg-blue-50 dark:bg-blue-950 p-4 rounded-lg">
                                <h3 className="font-semibold mb-2 text-blue-900 dark:text-blue-100">Important Notes:</h3>
                                <ul className="list-disc list-inside space-y-1 text-sm text-blue-800 dark:text-blue-200">
                                    <li>Existing products with matching SKU will be updated</li>
                                    <li>Invalid rows will be skipped but won't stop the import</li>
                                    <li>Duplicate SKUs within the same import will be ignored</li>
                                </ul>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
