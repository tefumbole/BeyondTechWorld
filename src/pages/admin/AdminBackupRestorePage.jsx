import React from 'react';
import { Database, FileCode } from 'lucide-react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { createFullBackup, restoreDatabaseBackup, getBackupHistory, deleteBackup, createFullDbBackup, restoreFullDbBackup } from '@/services/backupService';
import { useToast } from '@/components/ui/use-toast';
import { DownloadCloud, UploadCloud, History, Trash2, AlertTriangle, Loader2, FileJson, Save } from 'lucide-react';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from "@/components/ui/alert-dialog";
import { Badge } from '@/components/ui/badge';
import CodeBackupTab from '@/components/admin/CodeBackupTab';

const AdminBackupRestorePage = () => {
    return (
        <div className="space-y-6 animate-in fade-in duration-500 pb-12">
            <div>
                <h1 className="text-3xl font-bold text-[#003D82] flex items-center gap-3">
                    <Database className="w-8 h-8" /> 
                    System Backup & Restore
                </h1>
                <p className="text-gray-500 mt-2">Manage disaster recovery snapshots for database and source code.</p>
            </div>

            <Tabs defaultValue="database" className="w-full">
                <TabsList className="grid w-full grid-cols-2 max-w-md mb-8">
                    <TabsTrigger value="database" className="flex items-center gap-2">
                        <Database className="w-4 h-4" /> Database Backup
                    </TabsTrigger>
                    <TabsTrigger value="code" className="flex items-center gap-2">
                        <FileCode className="w-4 h-4" /> Code Backup
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="database">
                     <DatabaseBackupContent />
                </TabsContent>

                <TabsContent value="code">
                     <CodeBackupTab />
                </TabsContent>
            </Tabs>
        </div>
    );
};

// Extracted from previous version to keep file clean
const DatabaseBackupContent = () => {
    const [backups, setBackups] = React.useState([]);
    const [loading, setLoading] = React.useState(false);
    const [restoreFile, setRestoreFile] = React.useState(null);
    const [historyLoading, setHistoryLoading] = React.useState(true);
    const { toast } = useToast();

    React.useEffect(() => {
        loadHistory();
    }, []);

    const loadHistory = async () => {
        try {
            setHistoryLoading(true);
            const data = await getBackupHistory();
            setBackups(data || []);
        } catch (error) {
            console.error("Failed to load history", error);
        } finally {
            setHistoryLoading(false);
        }
    };

    const handleCreateBackup = async () => {
        setLoading(true);
        try {
            const { blob, tableCount, recordCount } = await createFullDbBackup();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
            a.download = `alphabridge_full_backup_${timestamp}.json`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);

            toast({
                title: "Backup Successful",
                description: `Full database export complete — ${tableCount} tables, ${recordCount} records downloaded.`,
                className: "bg-green-50 border-green-200 text-green-900"
            });
            loadHistory();
        } catch (error) {
            // Fall back to legacy partial export if the full endpoint is unavailable.
            try {
                const blob = await createFullBackup();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `backup_${new Date().toISOString().replace(/[:.]/g, '-')}.json`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                toast({ title: "Backup Successful", description: "Database export downloaded.", className: "bg-green-50 border-green-200 text-green-900" });
            } catch (fallbackError) {
                toast({ title: "Backup Failed", description: error.message || fallbackError.message, variant: "destructive" });
            }
        } finally {
            setLoading(false);
        }
    };

    const handleRestore = async () => {
        if (!restoreFile) return;
        setLoading(true);
        try {
            const result = await restoreFullDbBackup(restoreFile);
            const errors = result?.results?.errors || [];
            const restored = result?.results?.restored || [];
            if (errors.length > 0) {
                toast({ title: "Restore Completed with Errors", description: `Restored: ${restored.length} table(s). Errors: ${errors.length}.`, variant: "destructive" });
            } else {
                toast({ title: "Restore Successful", description: `${restored.length} table(s) restored from backup.`, className: "bg-green-50 border-green-200 text-green-900" });
            }
            setRestoreFile(null);
        } catch (error) {
            // Fall back to legacy per-table upsert restore.
            try {
                const result = await restoreDatabaseBackup(restoreFile);
                if (result.errors.length > 0) {
                    toast({ title: "Restore Completed with Errors", description: `Success: ${result.success.length}. Errors: ${result.errors.length}.`, variant: "destructive" });
                } else {
                    toast({ title: "Restore Successful", description: "All tables restored successfully.", className: "bg-green-50 border-green-200 text-green-900" });
                }
                setRestoreFile(null);
            } catch (fallbackError) {
                toast({ title: "Restore Failed", description: error.message || fallbackError.message, variant: "destructive" });
            }
        } finally {
            setLoading(false);
        }
    };

    const handleDelete = async (id) => {
        try {
            await deleteBackup(id);
            toast({ title: "Backup Deleted", description: "Record removed from history." });
            loadHistory();
        } catch (error) {
            toast({ title: "Delete Failed", variant: "destructive" });
        }
    };

    return (
        <div className="space-y-6">
             <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                {/* CREATE BACKUP CARD */}
                <Card className="shadow-lg border-t-4 border-t-[#003D82]">
                    <CardHeader className="bg-gray-50/50 pb-8">
                        <CardTitle className="flex items-center gap-2 text-[#003D82]">
                            <DownloadCloud className="w-6 h-6" /> Create Database Backup
                        </CardTitle>
                        <CardDescription>Export every database table to a single JSON file.</CardDescription>
                    </CardHeader>
                    <CardContent className="pt-6">
                        <div className="flex flex-col items-center justify-center space-y-4 py-8">
                            <div className="p-4 bg-blue-50 rounded-full">
                                <Database className="w-12 h-12 text-[#003D82]" />
                            </div>
                            <Button onClick={handleCreateBackup} disabled={loading} className="w-full max-w-xs bg-[#003D82] hover:bg-[#002855]">
                                {loading ? <><Loader2 className="mr-2 h-5 w-5 animate-spin" /> Exporting...</> : <><Save className="mr-2 h-5 w-5" /> Download Backup</>}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* RESTORE BACKUP CARD */}
                <Card className="shadow-lg border-t-4 border-t-[#D4AF37]">
                    <CardHeader className="bg-gray-50/50 pb-8">
                        <CardTitle className="flex items-center gap-2 text-yellow-700">
                            <UploadCloud className="w-6 h-6" /> Restore Data
                        </CardTitle>
                        <CardDescription>Import data from JSON backup file.</CardDescription>
                    </CardHeader>
                    <CardContent className="pt-6">
                        <div className="space-y-6">
                             <Input type="file" accept=".json" onChange={(e) => setRestoreFile(e.target.files[0])} className="cursor-pointer" />
                            {restoreFile && (
                                <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4 flex items-start gap-3">
                                    <AlertTriangle className="w-5 h-5 text-yellow-600 shrink-0 mt-0.5" />
                                    <div className="text-sm text-yellow-800">
                                        <p className="font-bold">Warning: Full Overwrite</p>
                                        <p>Restoring replaces all data in every table contained in this file.</p>
                                    </div>
                                </div>
                            )}
                            <AlertDialog>
                                <AlertDialogTrigger asChild>
                                    <Button disabled={!restoreFile || loading} className="w-full bg-yellow-600 hover:bg-yellow-700 text-white">
                                        {loading ? <Loader2 className="animate-spin mr-2" /> : <UploadCloud className="mr-2 h-5 w-5" />} Start Restore
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>Are you sure?</AlertDialogTitle>
                                        <AlertDialogDescription>This action will overwrite data in your database. Existing data may be lost.</AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                                        <AlertDialogAction onClick={handleRestore} className="bg-red-600 hover:bg-red-700">Yes, Overwrite Data</AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        </div>
                    </CardContent>
                </Card>
            </div>
            
             <Card className="shadow-md">
                <CardHeader>
                    <CardTitle className="flex items-center gap-2"><History className="w-5 h-5 text-gray-500" /> Database Backup History</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Date & Time</TableHead>
                                <TableHead>Filename</TableHead>
                                <TableHead>Size</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {historyLoading ? (
                                <TableRow><TableCell colSpan={5} className="text-center py-8"><Loader2 className="w-6 h-6 animate-spin mx-auto text-gray-400" /></TableCell></TableRow>
                            ) : backups.length === 0 ? (
                                <TableRow><TableCell colSpan={5} className="text-center py-8 text-gray-500">No backup history found.</TableCell></TableRow>
                            ) : (
                                backups.map((backup) => (
                                    <TableRow key={backup.id}>
                                        <TableCell>{new Date(backup.created_at).toLocaleString()}</TableCell>
                                        <TableCell className="font-mono text-xs text-gray-600"><div className="flex items-center gap-2"><FileJson className="w-4 h-4 text-blue-500" />{backup.name}</div></TableCell>
                                        <TableCell>{backup.size}</TableCell>
                                        <TableCell><Badge variant={backup.status === 'stored' ? 'default' : 'secondary'}>{backup.status === 'stored' ? 'Cloud Saved' : 'Local Log'}</Badge></TableCell>
                                        <TableCell className="text-right space-x-2">
                                            {backup.download_url && <Button variant="outline" size="sm" onClick={() => window.open(backup.download_url, '_blank')}><DownloadCloud className="w-4 h-4" /></Button>}
                                            <Button variant="ghost" size="sm" onClick={() => handleDelete(backup.id)} className="text-red-500"><Trash2 className="w-4 h-4" /></Button>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    );
};

export default AdminBackupRestorePage;