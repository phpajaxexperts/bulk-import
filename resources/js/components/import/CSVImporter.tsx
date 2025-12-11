import { useState, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Upload, FileText, AlertCircle } from 'lucide-react';
import axios from 'axios';

interface CSVImporterProps {
    onImportComplete: (result: any) => void;
}

export default function CSVImporter({ onImportComplete }: CSVImporterProps) {
    const [file, setFile] = useState<File | null>(null);
    const [uploading, setUploading] = useState(false);
    const [progress, setProgress] = useState(0);
    const [error, setError] = useState<string | null>(null);
    const [isDragging, setIsDragging] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const validateFile = (selectedFile: File): boolean => {
        if (!selectedFile.name.endsWith('.csv')) {
            setError('Please select a valid CSV file');
            return false;
        }
        return true;
    };

    const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
        const selectedFile = e.target.files?.[0];
        if (selectedFile && validateFile(selectedFile)) {
            setFile(selectedFile);
            setError(null);
        }
    };

    const handleDragEnter = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(true);
    };

    const handleDragLeave = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);
    };

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);

        const droppedFile = e.dataTransfer.files?.[0];
        if (droppedFile && validateFile(droppedFile)) {
            setFile(droppedFile);
            setError(null);
        }
    };

    const handleUpload = async () => {
        if (!file) return;

        setUploading(true);
        setProgress(0);
        setError(null);

        const formData = new FormData();
        formData.append('file', file);

        try {
            const response = await axios.post('/api/products/import', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
                onUploadProgress: (progressEvent) => {
                    const percentCompleted = progressEvent.total
                        ? Math.round((progressEvent.loaded * 100) / progressEvent.total)
                        : 0;
                    setProgress(percentCompleted);
                },
            });

            if (response.data.success) {
                onImportComplete(response.data.data);
                setFile(null);
                if (fileInputRef.current) {
                    fileInputRef.current.value = '';
                }
            } else {
                setError('Import failed: ' + (response.data.message || 'Unknown error'));
            }
        } catch (err: any) {
            setError(err.response?.data?.message || 'Failed to upload file');
        } finally {
            setUploading(false);
            setProgress(0);
        }
    };

    return (
        <div className="space-y-4">
            <div
                onDragEnter={handleDragEnter}
                onDragOver={handleDragOver}
                onDragLeave={handleDragLeave}
                onDrop={handleDrop}
                className={`border-2 border-dashed rounded-lg p-8 text-center transition-all ${isDragging
                        ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                        : 'border-gray-300 dark:border-gray-700 hover:border-blue-400 dark:hover:border-blue-500'
                    }`}
            >
                <input
                    ref={fileInputRef}
                    type="file"
                    accept=".csv"
                    onChange={handleFileSelect}
                    className="hidden"
                    id="csv-file-input"
                />
                <label
                    htmlFor="csv-file-input"
                    className="cursor-pointer flex flex-col items-center space-y-2"
                >
                    {file ? (
                        <>
                            <FileText className="w-12 h-12 text-green-500" />
                            <div className="text-sm font-medium">{file.name}</div>
                            <div className="text-xs text-gray-500">
                                {(file.size / 1024 / 1024).toFixed(2)} MB
                            </div>
                        </>
                    ) : (
                        <>
                            <Upload className="w-12 h-12 text-gray-400" />
                            <div className="text-sm font-medium">
                                {isDragging ? 'Drop CSV file here' : 'Click to select CSV file'}
                            </div>
                            <div className="text-xs text-gray-500">
                                or drag and drop your file here
                            </div>
                        </>
                    )}
                </label>
            </div>

            {error && (
                <div className="flex items-center gap-2 p-3 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 rounded-lg">
                    <AlertCircle className="w-5 h-5 flex-shrink-0" />
                    <span className="text-sm">{error}</span>
                </div>
            )}

            {uploading && (
                <div className="space-y-2">
                    <Progress value={progress} className="w-full" />
                    <p className="text-sm text-gray-600 dark:text-gray-400 text-center">
                        Uploading... {progress}%
                    </p>
                </div>
            )}

            <Button
                onClick={handleUpload}
                disabled={!file || uploading}
                className="w-full"
                size="lg"
            >
                {uploading ? 'Importing...' : 'Import CSV'}
            </Button>
        </div>
    );
}
