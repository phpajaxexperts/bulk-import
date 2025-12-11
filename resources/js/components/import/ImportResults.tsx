import { CheckCircle, XCircle, AlertTriangle, RefreshCw, Copy } from 'lucide-react';

interface ImportResultsProps {
    result: {
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
    };
}

export default function ImportResults({ result }: ImportResultsProps) {
    const { statistics, errors, status } = result;
    const hasErrors = errors && (Array.isArray(errors) ? errors.length > 0 : Object.keys(errors).length > 0);

    // Handle failed imports
    if (status === 'failed') {
        return (
            <div className="space-y-4">
                <div className="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
                    <h3 className="text-lg font-semibold text-red-900 dark:text-red-100 mb-2">
                        Import Failed
                    </h3>
                    {errors && typeof errors === 'object' && 'general' in errors && (
                        <p className="text-red-700 dark:text-red-300">
                            {errors.general}
                        </p>
                    )}
                </div>
                <div className="pt-4 border-t border-gray-200 dark:border-gray-700">
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-gray-600 dark:text-gray-400">Import Log ID:</span>
                        <span className="font-mono font-medium">#{result.import_log_id}</span>
                    </div>
                </div>
            </div>
        );
    }


    return (
        <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
                <StatCard
                    icon={<CheckCircle className="w-5 h-5 text-green-500" />}
                    label="Total Rows"
                    value={statistics.total_rows}
                    color="blue"
                />
                <StatCard
                    icon={<CheckCircle className="w-5 h-5 text-green-500" />}
                    label="Imported"
                    value={statistics.imported}
                    color="green"
                />
                <StatCard
                    icon={<RefreshCw className="w-5 h-5 text-blue-500" />}
                    label="Updated"
                    value={statistics.updated}
                    color="blue"
                />
                <StatCard
                    icon={<XCircle className="w-5 h-5 text-red-500" />}
                    label="Invalid"
                    value={statistics.invalid}
                    color="red"
                />
                <StatCard
                    icon={<Copy className="w-5 h-5 text-orange-500" />}
                    label="Duplicates"
                    value={statistics.duplicates}
                    color="orange"
                />
            </div>

            {hasErrors && (
                <div className="mt-6">
                    <h3 className="text-sm font-semibold mb-3 flex items-center gap-2">
                        <AlertTriangle className="w-4 h-4 text-red-500" />
                        Errors ({errors.length})
                    </h3>
                    <div className="max-h-60 overflow-y-auto space-y-2">
                        {errors.slice(0, 10).map((error, index) => (
                            <div
                                key={index}
                                className="p-3 bg-red-50 dark:bg-red-900/20 rounded-lg text-sm"
                            >
                                <div className="font-medium text-red-900 dark:text-red-100">
                                    Row {error.row}
                                </div>
                                <div className="text-red-700 dark:text-red-300 text-xs mt-1">
                                    {Array.isArray(error.errors)
                                        ? error.errors.join(', ')
                                        : error.errors}
                                </div>
                            </div>
                        ))}
                        {errors.length > 10 && (
                            <div className="text-xs text-gray-500 text-center pt-2">
                                +{errors.length - 10} more errors
                            </div>
                        )}
                    </div>
                </div>
            )}

            <div className="pt-4 border-t border-gray-200 dark:border-gray-700">
                <div className="flex items-center justify-between text-sm">
                    <span className="text-gray-600 dark:text-gray-400">Import Log ID:</span>
                    <span className="font-mono font-medium">#{result.import_log_id}</span>
                </div>
            </div>
        </div>
    );
}

interface StatCardProps {
    icon: React.ReactNode;
    label: string;
    value: number;
    color: 'blue' | 'green' | 'red' | 'orange';
}

function StatCard({ icon, label, value, color }: StatCardProps) {
    const colorClasses = {
        blue: 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800',
        green: 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800',
        red: 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800',
        orange: 'bg-orange-50 dark:bg-orange-900/20 border-orange-200 dark:border-orange-800',
    };

    return (
        <div className={`p-4 rounded-lg border ${colorClasses[color]}`}>
            <div className="flex items-center gap-2 mb-2">{icon}</div>
            <div className="text-2xl font-bold">{value.toLocaleString()}</div>
            <div className="text-xs text-gray-600 dark:text-gray-400">{label}</div>
        </div>
    );
}
