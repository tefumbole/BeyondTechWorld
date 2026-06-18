import React, { useEffect, useState, useCallback } from 'react';
import { getActivityLogs, deleteActivityLogs, clearActivityLogs } from '@/services/activityLogService';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useToast } from '@/components/ui/use-toast';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { ScrollText, Loader2, Trash2, RefreshCw, Search } from 'lucide-react';

const ACTION_COLORS = {
  create: 'bg-green-100 text-green-800',
  update: 'bg-blue-100 text-blue-800',
  upsert: 'bg-blue-100 text-blue-800',
  delete: 'bg-red-100 text-red-800',
  login: 'bg-purple-100 text-purple-800',
};

const PAGE_SIZE = 100;

const AdminActivityLogsPage = () => {
  const { toast } = useToast();
  const [logs, setLogs] = useState([]);
  const [count, setCount] = useState(0);
  const [loading, setLoading] = useState(true);
  const [selected, setSelected] = useState([]);
  const [offset, setOffset] = useState(0);
  const [search, setSearch] = useState('');
  const [actionFilter, setActionFilter] = useState('all');

  const load = useCallback(async () => {
    setLoading(true);
    const res = await getActivityLogs({
      search,
      action: actionFilter === 'all' ? '' : actionFilter,
      limit: PAGE_SIZE,
      offset,
    });
    if (res.success) {
      setLogs(res.data);
      setCount(res.count);
    } else {
      toast({ title: 'Could not load logs', description: res.error, variant: 'destructive' });
    }
    setLoading(false);
  }, [search, actionFilter, offset, toast]);

  useEffect(() => { load(); }, [load]);

  const toggle = (id) => {
    setSelected((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
  };

  const toggleAll = (checked) => {
    setSelected(checked ? logs.map((l) => l.id) : []);
  };

  const handleDeleteSelected = async () => {
    if (!selected.length) return;
    const res = await deleteActivityLogs(selected);
    if (res.success) {
      toast({ title: 'Logs deleted', description: `${res.count} entr(ies) removed.` });
      setSelected([]);
      load();
    } else {
      toast({ title: 'Delete failed', description: res.error, variant: 'destructive' });
    }
  };

  const handleClearAll = async () => {
    const res = await clearActivityLogs();
    if (res.success) {
      toast({ title: 'All logs cleared' });
      setSelected([]);
      setOffset(0);
      load();
    } else {
      toast({ title: 'Clear failed', description: res.error, variant: 'destructive' });
    }
  };

  const allSelected = logs.length > 0 && selected.length === logs.length;

  return (
    <div className="space-y-6 pb-12">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-3xl font-bold text-[#003D82] flex items-center gap-3">
            <ScrollText className="w-8 h-8" /> Activity Logs
          </h1>
          <p className="text-gray-500 mt-2">A record of create, update, delete and login events across the system.</p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" onClick={load} disabled={loading}>
            <RefreshCw className={`w-4 h-4 mr-2 ${loading ? 'animate-spin' : ''}`} /> Refresh
          </Button>
          <AlertDialog>
            <AlertDialogTrigger asChild>
              <Button variant="outline" className="text-red-600 border-red-200 hover:bg-red-50">
                <Trash2 className="w-4 h-4 mr-2" /> Clear All
              </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
              <AlertDialogHeader>
                <AlertDialogTitle>Clear all activity logs?</AlertDialogTitle>
                <AlertDialogDescription>This permanently deletes every log entry. This cannot be undone.</AlertDialogDescription>
              </AlertDialogHeader>
              <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction onClick={handleClearAll} className="bg-red-600 hover:bg-red-700">Clear All</AlertDialogAction>
              </AlertDialogFooter>
            </AlertDialogContent>
          </AlertDialog>
        </div>
      </div>

      <Card className="shadow-sm">
        <CardHeader className="pb-3">
          <div className="flex flex-wrap items-center gap-3">
            <div className="relative flex-1 min-w-[220px]">
              <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
              <Input
                className="pl-9"
                placeholder="Search user, summary, entity..."
                value={search}
                onChange={(e) => { setOffset(0); setSearch(e.target.value); }}
              />
            </div>
            <Select value={actionFilter} onValueChange={(v) => { setOffset(0); setActionFilter(v); }}>
              <SelectTrigger className="w-[170px]"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All actions</SelectItem>
                <SelectItem value="create">Create</SelectItem>
                <SelectItem value="update">Update</SelectItem>
                <SelectItem value="upsert">Save (upsert)</SelectItem>
                <SelectItem value="delete">Delete</SelectItem>
                <SelectItem value="login">Login</SelectItem>
              </SelectContent>
            </Select>
            {selected.length > 0 && (
              <Button variant="outline" className="text-red-600 border-red-200 hover:bg-red-50" onClick={handleDeleteSelected}>
                <Trash2 className="w-4 h-4 mr-2" /> Delete Selected ({selected.length})
              </Button>
            )}
          </div>
        </CardHeader>
        <CardContent>
          <CardTitle className="sr-only">Logs</CardTitle>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-10">
                  <Checkbox checked={allSelected} onCheckedChange={(c) => toggleAll(Boolean(c))} aria-label="Select all" />
                </TableHead>
                <TableHead>When</TableHead>
                <TableHead>User</TableHead>
                <TableHead>Action</TableHead>
                <TableHead>Entity</TableHead>
                <TableHead>Summary</TableHead>
                <TableHead>IP</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {loading ? (
                <TableRow><TableCell colSpan={7} className="text-center py-10"><Loader2 className="w-6 h-6 animate-spin mx-auto text-gray-400" /></TableCell></TableRow>
              ) : logs.length === 0 ? (
                <TableRow><TableCell colSpan={7} className="text-center py-10 text-gray-500">No activity logged yet.</TableCell></TableRow>
              ) : (
                logs.map((log) => (
                  <TableRow key={log.id} className={selected.includes(log.id) ? 'bg-blue-50/40' : ''}>
                    <TableCell>
                      <Checkbox checked={selected.includes(log.id)} onCheckedChange={() => toggle(log.id)} aria-label="Select row" />
                    </TableCell>
                    <TableCell className="whitespace-nowrap text-xs text-gray-600">{new Date(log.created_at).toLocaleString()}</TableCell>
                    <TableCell className="text-sm">
                      <div className="font-medium text-gray-800">{log.user_name || 'System'}</div>
                      {log.user_role && <div className="text-xs text-gray-400 capitalize">{String(log.user_role).replace(/_/g, ' ')}</div>}
                    </TableCell>
                    <TableCell>
                      <Badge className={`${ACTION_COLORS[log.action] || 'bg-gray-100 text-gray-700'} capitalize`}>{log.action}</Badge>
                    </TableCell>
                    <TableCell className="text-sm text-gray-700">{log.entity || '—'}</TableCell>
                    <TableCell className="text-sm text-gray-600 max-w-[320px] truncate" title={log.summary || ''}>{log.summary || '—'}</TableCell>
                    <TableCell className="text-xs font-mono text-gray-400">{log.ip_address || '—'}</TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>

          {count > PAGE_SIZE && (
            <div className="flex items-center justify-between mt-4 text-sm text-gray-600">
              <span>Showing {offset + 1}–{Math.min(offset + PAGE_SIZE, count)} of {count}</span>
              <div className="flex gap-2">
                <Button variant="outline" size="sm" disabled={offset === 0} onClick={() => setOffset(Math.max(0, offset - PAGE_SIZE))}>Previous</Button>
                <Button variant="outline" size="sm" disabled={offset + PAGE_SIZE >= count} onClick={() => setOffset(offset + PAGE_SIZE)}>Next</Button>
              </div>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
};

export default AdminActivityLogsPage;
