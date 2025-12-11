import { useState, useCallback } from 'react';
import { useDropzone } from 'react-dropzone';
import { Upload, Image as ImageIcon, CheckCircle, XCircle, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import axios from 'axios';
import CryptoJS from 'crypto-js';

const CHUNK_SIZE = 1024 * 1024; // 1MB chunks

interface UploadFile {
    file: File;
    id: string;
    uuid?: string;
    status: 'pending' | 'uploading' | 'completed' | 'failed';
    progress: number;
    uploadedChunks: number;
    totalChunks: number;
    error?: string;
    variants?: any[];
}

export default function ImageUploader() {
    const [files, setFiles] = useState<UploadFile[]>([]);

    const onDrop = useCallback((acceptedFiles: File[]) => {
        const newFiles = acceptedFiles.map((file) => ({
            file,
            id: Math.random().toString(36).substring(7),
            status: 'pending' as const,
            progress: 0,
            uploadedChunks: 0,
            totalChunks: Math.ceil(file.size / CHUNK_SIZE),
        }));

        setFiles((prev) => [...prev, ...newFiles]);

        // Start uploading automatically
        newFiles.forEach((uploadFile) => {
            startUpload(uploadFile);
        });
    }, []);

    const { getRootProps, getInputProps, isDragActive } = useDropzone({
        onDrop,
        accept: {
            'image/*': ['.png', '.jpg', '.jpeg', '.gif', '.webp'],
        },
        multiple: true,
    });

    const calculateChecksum = (data: ArrayBuffer): string => {
        const wordArray = CryptoJS.lib.WordArray.create(data as any);
        return CryptoJS.SHA256(wordArray).toString();
    };

    const startUpload = async (uploadFile: UploadFile) => {
        try {
            updateFile(uploadFile.id, { status: 'uploading' });

            // Step 1: Initialize upload
            const initResponse = await axios.post('/api/uploads/init', {
                filename: uploadFile.file.name,
                total_size: uploadFile.file.size,
                mime_type: uploadFile.file.type,
                total_chunks: uploadFile.totalChunks,
            });

            if (!initResponse.data.success) {
                throw new Error('Failed to initialize upload');
            }

            const { uuid } = initResponse.data.data;
            updateFile(uploadFile.id, { uuid });

            // Step 2: Upload chunks
            const { uploadedChunkIndices } = await checkResumeCapability(uuid);
            await uploadChunks(uploadFile, uuid, uploadedChunkIndices || []);

            // Step 3: Complete upload
            await completeUpload(uploadFile, uuid);
        } catch (error: any) {
            updateFile(uploadFile.id, {
                status: 'failed',
                error: error.message || 'Upload failed',
            });
        }
    };

    const checkResumeCapability = async (uuid: string) => {
        try {
            const response = await axios.get(`/api/uploads/${uuid}/resume`);
            if (response.data.success && response.data.data.exists) {
                return response.data.data;
            }
        } catch (error) {
            // Resume not available
        }
        return { uploadedChunkIndices: [] };
    };

    const uploadChunks = async (
        uploadFile: UploadFile,
        uuid: string,
        uploadedChunkIndices: number[]
    ) => {
        const { file, totalChunks } = uploadFile;

        for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
            // Skip already uploaded chunks (resume support)
            if (uploadedChunkIndices.includes(chunkIndex)) {
                updateFile(uploadFile.id, {
                    uploadedChunks: chunkIndex + 1,
                    progress: Math.round(((chunkIndex + 1) / totalChunks) * 100),
                });
                continue;
            }

            const start = chunkIndex * CHUNK_SIZE;
            const end = Math.min(start + CHUNK_SIZE, file.size);
            const chunk = file.slice(start, end);

            // Read chunk as ArrayBuffer
            const chunkBuffer = await chunk.arrayBuffer();

            // Calculate checksum
            const checksum = calculateChecksum(chunkBuffer);

            // Convert to base64
            const base64Chunk = btoa(
                new Uint8Array(chunkBuffer).reduce(
                    (data, byte) => data + String.fromCharCode(byte),
                    ''
                )
            );

            // Upload chunk with retry logic
            let retries = 3;
            while (retries > 0) {
                try {
                    const response = await axios.post(`/api/uploads/${uuid}/chunk`, {
                        chunk_index: chunkIndex,
                        chunk_data: base64Chunk,
                        checksum: checksum,
                    });

                    if (!response.data.success) {
                        throw new Error(response.data.message || 'Chunk upload failed');
                    }

                    updateFile(uploadFile.id, {
                        uploadedChunks: chunkIndex + 1,
                        progress: Math.round(((chunkIndex + 1) / totalChunks) * 100),
                    });

                    break;
                } catch (error) {
                    retries--;
                    if (retries === 0) {
                        throw error;
                    }
                    // Wait before retrying
                    await new Promise((resolve) => setTimeout(resolve, 1000));
                }
            }
        }
    };

    const completeUpload = async (uploadFile: UploadFile, uuid: string) => {
        // Calculate final checksum
        const fileBuffer = await uploadFile.file.arrayBuffer();
        const finalChecksum = calculateChecksum(fileBuffer);

        const response = await axios.post(`/api/uploads/${uuid}/complete`, {
            checksum: finalChecksum,
        });

        if (!response.data.success) {
            throw new Error(response.data.message || 'Failed to complete upload');
        }

        updateFile(uploadFile.id, {
            status: 'completed',
            progress: 100,
            variants: response.data.data.variants,
        });
    };

    const updateFile = (id: string, updates: Partial<UploadFile>) => {
        setFiles((prev) =>
            prev.map((f) => (f.id === id ? { ...f, ...updates } : f))
        );
    };

    return (
        <div className="space-y-6">
            <div
                {...getRootProps()}
                className={`border-2 border-dashed rounded-lg p-12 text-center cursor-pointer transition-all ${isDragActive
                        ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                        : 'border-gray-300 dark:border-gray-700 hover:border-blue-400'
                    }`}
            >
                <input {...getInputProps()} />
                <div className="flex flex-col items-center space-y-4">
                    <Upload className="w-16 h-16 text-gray-400" />
                    {isDragActive ? (
                        <p className="text-lg font-medium">Drop images here...</p>
                    ) : (
                        <>
                            <p className="text-lg font-medium">Drag & drop images here</p>
                            <p className="text-sm text-gray-500">
                                or click to browse files
                            </p>
                            <p className="text-xs text-gray-400">
                                Supports: PNG, JPG, JPEG, GIF, WebP
                            </p>
                        </>
                    )}
                </div>
            </div>

            {files.length > 0 && (
                <div className="space-y-3">
                    <h3 className="text-lg font-semibold">Uploads ({files.length})</h3>
                    {files.map((uploadFile) => (
                        <UploadItem key={uploadFile.id} uploadFile={uploadFile} />
                    ))}
                </div>
            )}
        </div>
    );
}

interface UploadItemProps {
    uploadFile: UploadFile;
}

function UploadItem({ uploadFile }: UploadItemProps) {
    const { file, status, progress, error, uploadedChunks, totalChunks, variants } = uploadFile;

    return (
        <div className="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg space-y-3">
            <div className="flex items-start gap-3">
                <ImageIcon className="w-10 h-10 text-gray-400 flex-shrink-0" />
                <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between gap-2">
                        <p className="font-medium truncate">{file.name}</p>
                        <StatusIcon status={status} />
                    </div>
                    <p className="text-xs text-gray-500">
                        {(file.size / 1024 / 1024).toFixed(2)} MB
                    </p>
                </div>
            </div>

            {status === 'uploading' && (
                <div className="space-y-1">
                    <Progress value={progress} className="h-2" />
                    <p className="text-xs text-gray-600 dark:text-gray-400">
                        Uploading chunk {uploadedChunks} of {totalChunks} ({progress}%)
                    </p>
                </div>
            )}

            {status === 'failed' && error && (
                <div className="text-sm text-red-600 dark:text-red-400">{error}</div>
            )}

            {status === 'completed' && variants && (
                <div className="text-xs text-green-600 dark:text-green-400">
                    ✓ Upload complete • Generated {variants.length} variants
                </div>
            )}
        </div>
    );
}

function StatusIcon({ status }: { status: UploadFile['status'] }) {
    switch (status) {
        case 'pending':
            return <div className="w-5 h-5 rounded-full bg-gray-300 dark:bg-gray-600" />;
        case 'uploading':
            return <Loader2 className="w-5 h-5 text-blue-500 animate-spin" />;
        case 'completed':
            return <CheckCircle className="w-5 h-5 text-green-500" />;
        case 'failed':
            return <XCircle className="w-5 h-5 text-red-500" />;
    }
}
