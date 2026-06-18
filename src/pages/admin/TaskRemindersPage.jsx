import React, { useEffect, useState } from 'react';
import { getTaskReminders, deleteTaskReminders } from '@/services/taskService';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useToast } from '@/components/ui/use-toast';
import { getPriorityColor } from '@/components/admin/TaskDashboardCard';
import { Loader2, Clock, Trash2, CheckCircle2 } from 'lucide-react';
import { format } from 'date-fns';

const TaskRemindersPage = () => {
  const { toast } = useToast();
  const [reminders, setReminders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [selected, setSelected] = useState([]);
  const [deleting, setDeleting] = useState(false);

  const load = async () => {
    setLoading(true);
    const res = await getTaskReminders();
    if (res.success) setReminders(res.data);
    else toast({ title: 'Error loading reminders', description: res.error, variant: 'destructive' });
    setLoading(false);
  };

  useEffect(() => { load(); }, []);

  const toggle = (id) => setSelected((p) => (p.includes(id) ? p.filter((x) => x !== id) : [...p, id]));
  const toggleAll = (checked) => setSelected(checked ? reminders.map((r) => r.id) : []);

  const handleDelete = async (ids = selected) => {
    const list = (ids || []).filter(Boolean);
    if (!list.length) return;
    if (!window.confirm(`Delete ${list.length} reminder(s)?`)) return;
    setDeleting(true);
    const res = await deleteTaskReminders(list);
    setDeleting(false);
    if (res.success) {
      toast({ title: 'Reminders deleted', description: `${res.count} removed.` });
      setSelected([]);
      load();
    } else {
      toast({ title: 'Delete failed', description: res.error, variant: 'destructive' });
    }
  };

  const allSelected = reminders.length > 0 && selected.length === reminders.length;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-3xl font-bold text-[#003D82] flex items-center gap-2">
            <Clock className="w-7 h-7" /> Task Reminders
          </h1>
          <p className="text-gray-500 mt-1">Scheduled WhatsApp reminders for upcoming task deadlines.</p>
        </div>
        {selected.length > 0 && (
          <Button
            variant="outline"
            className="text-red-600 border-red-200 hover:bg-red-50"
            disabled={deleting}
            onClick={() => handleDelete()}
          >
            {deleting ? <Loader2 className="w-4 h-4 mr-2 animate-spin" /> : <Trash2 className="w-4 h-4 mr-2" />}
            Delete Selected ({selected.length})
          </Button>
        )}
      </div>

      <Card className="shadow border overflow-hidden">
        <CardContent className="p-0">
          {loading ? (
            <div className="flex justify-center p-10"><Loader2 className="w-8 h-8 animate-spin text-[#003D82]" /></div>
          ) : reminders.length === 0 ? (
            <div className="p-10 text-center text-gray-500">No reminders scheduled. Add reminder times when creating a task.</div>
          ) : (
            <Table>
              <TableHeader className="bg-gray-50">
                <TableRow>
                  <TableHead className="w-10">
                    <Checkbox checked={allSelected} onCheckedChange={(c) => toggleAll(Boolean(c))} aria-label="Select all" />
                  </TableHead>
                  <TableHead>Task</TableHead>
                  <TableHead>Priority</TableHead>
                  <TableHead>Reminder Time</TableHead>
                  <TableHead>Task Deadline</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {reminders.map((r) => (
                  <TableRow key={r.id} className={selected.includes(r.id) ? 'bg-blue-50/40' : ''}>
                    <TableCell>
                      <Checkbox checked={selected.includes(r.id)} onCheckedChange={() => toggle(r.id)} aria-label="Select reminder" />
                    </TableCell>
                    <TableCell className="font-medium">{r.task?.title || '(task deleted)'}</TableCell>
                    <TableCell>{r.task?.priority ? <Badge className={getPriorityColor(r.task.priority)}>{r.task.priority}</Badge> : '—'}</TableCell>
                    <TableCell className="text-sm">{r.reminder_time ? format(new Date(r.reminder_time), 'MMM dd, yyyy · HH:mm') : '—'}</TableCell>
                    <TableCell className="text-sm text-gray-600">
                      {r.task?.deadline ? format(new Date(r.task.deadline), 'MMM dd, yyyy') : '—'}
                      {r.task?.deadline_time ? ` ${String(r.task.deadline_time).slice(0, 5)}` : ''}
                    </TableCell>
                    <TableCell>
                      {r.is_sent ? (
                        <Badge className="bg-green-100 text-green-800"><CheckCircle2 className="w-3 h-3 mr-1" /> Sent</Badge>
                      ) : (
                        <Badge variant="outline" className="text-amber-700 border-amber-200"><Clock className="w-3 h-3 mr-1" /> Pending</Badge>
                      )}
                    </TableCell>
                    <TableCell className="text-right">
                      <Button variant="ghost" size="icon" className="text-red-500" onClick={() => handleDelete([r.id])}>
                        <Trash2 className="w-4 h-4" />
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>
    </div>
  );
};

export default TaskRemindersPage;
