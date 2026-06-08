import React, { useState, useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { useToast } from '@/components/ui/use-toast';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { createBatchTasksWithAssignments } from '@/services/taskService';
import { searchUsersForTaskAssignment } from '@/services/userService';
import QuickAssigneeDialog from '@/components/admin/QuickAssigneeDialog';
import {
  Loader2,
  Users,
  AlertCircle,
  X,
  Search,
  Paperclip,
  Calendar,
  Plus,
  Trash2,
  UserPlus,
  Send,
  Clock,
} from 'lucide-react';
import { DEFAULT_TASK_NOTIFICATION_TEMPLATE } from '@/utils/taskPersonalization';

const DRAFT_KEY = 'task_draft_new_v3';
const PRIORITIES = ['Low', 'Medium', 'High', 'Emergency'];

const ASSIGNEE_TABS = [
  { id: 'all', label: 'All', selectAllLabel: 'Select all users' },
  { id: 'staff', label: 'Staff', selectAllLabel: 'Select all staff' },
  { id: 'customers', label: 'Customers & Members', selectAllLabel: 'Select all customers' },
];

const emptyTaskRow = () => ({
  subject: '',
  description: '',
  start_date: '',
  deadline: '',
  priority: 'Medium',
  pdfFile: null,
});

const CreateTaskPage = () => {
  const navigate = useNavigate();
  const { toast } = useToast();
  const fileInputRefs = useRef({});

  const [loading, setLoading] = useState(false);
  const [searchResults, setSearchResults] = useState([]);
  const [searchLoading, setSearchLoading] = useState(false);
  const [selectingAll, setSelectingAll] = useState(false);
  const [selectedUsers, setSelectedUsers] = useState([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [assigneeTab, setAssigneeTab] = useState('all');
  const [showNewUserDialog, setShowNewUserDialog] = useState(false);
  const [sendMode, setSendMode] = useState('now');
  const [scheduleTimes, setScheduleTimes] = useState(['']);
  const [notificationTemplate] = useState(DEFAULT_TASK_NOTIFICATION_TEMPLATE);
  const [taskRows, setTaskRows] = useState([emptyTaskRow()]);

  const activeTab = ASSIGNEE_TABS.find((t) => t.id === assigneeTab);

  useEffect(() => {
    const draft = localStorage.getItem(DRAFT_KEY);
    if (draft) {
      try {
        const parsed = JSON.parse(draft);
        setTaskRows(parsed.taskRows?.length ? parsed.taskRows.map((r) => ({ ...emptyTaskRow(), ...r, pdfFile: null })) : [emptyTaskRow()]);
        setSelectedUsers(parsed.selectedUsers || []);
        setSendMode(parsed.sendMode || 'now');
        setScheduleTimes(parsed.scheduleTimes || ['']);
        setAssigneeTab(parsed.assigneeTab || 'all');
      } catch {
        /* ignore */
      }
    }
  }, []);

  useEffect(() => {
    const timer = setTimeout(() => {
      localStorage.setItem(
        DRAFT_KEY,
        JSON.stringify({
          taskRows: taskRows.map(({ pdfFile, ...rest }) => rest),
          selectedUsers,
          sendMode,
          scheduleTimes,
          assigneeTab,
        })
      );
    }, 1000);
    return () => clearTimeout(timer);
  }, [taskRows, selectedUsers, sendMode, scheduleTimes, assigneeTab]);

  useEffect(() => {
    const timer = setTimeout(async () => {
      setSearchLoading(true);
      const res = await searchUsersForTaskAssignment(searchQuery, assigneeTab);
      setSearchResults(res.success ? res.data || [] : []);
      setSearchLoading(false);
    }, searchQuery.trim() ? 250 : 0);
    return () => clearTimeout(timer);
  }, [searchQuery, assigneeTab]);

  const handleAddUser = (user) => {
    if (!selectedUsers.find((u) => u.id === user.id)) {
      setSelectedUsers((prev) => [...prev, user]);
    }
  };

  const handleSelectAllInCategory = async () => {
    setSelectingAll(true);
    try {
      const res = await searchUsersForTaskAssignment('', assigneeTab);
      const rows = res.success ? res.data || [] : [];
      if (!rows.length) {
        toast({ title: 'No users found', description: `No ${activeTab?.label.toLowerCase()} in this category.` });
        return;
      }
      setSelectedUsers((prev) => {
        const next = [...prev];
        rows.forEach((row) => {
          if (!next.find((u) => u.id === row.id)) next.push(row);
        });
        return next;
      });
      toast({ title: `${rows.length} user(s) added` });
    } finally {
      setSelectingAll(false);
    }
  };

  const handleRemoveUser = (userId) => {
    setSelectedUsers((prev) => prev.filter((u) => u.id !== userId));
  };

  const availableResults = searchResults.filter((user) => !selectedUsers.find((su) => su.id === user.id));

  const updateTaskRow = (index, field, value) => {
    setTaskRows((prev) => prev.map((row, i) => (i === index ? { ...row, [field]: value } : row)));
  };

  const addTaskRow = () => setTaskRows((prev) => [...prev, emptyTaskRow()]);

  const removeTaskRow = (index) => {
    if (taskRows.length === 1) return;
    setTaskRows((prev) => prev.filter((_, i) => i !== index));
  };

  const handlePdfChange = (index, e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
      toast({ title: 'PDF only', description: 'Please attach a PDF file.', variant: 'destructive' });
      e.target.value = '';
      return;
    }
    updateTaskRow(index, 'pdfFile', file);
    e.target.value = '';
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    const validTasks = taskRows.filter((row) => row.subject.trim());
    if (!validTasks.length) {
      toast({ title: 'Required', description: 'Add at least one task with a subject.', variant: 'destructive' });
      return;
    }

    const missingDeadline = validTasks.find((row) => !row.deadline);
    if (missingDeadline) {
      toast({ title: 'Required', description: 'Each task needs an end date.', variant: 'destructive' });
      return;
    }

    if (selectedUsers.length === 0) {
      toast({ title: 'Required', description: 'Select or create at least one assignee.', variant: 'destructive' });
      return;
    }

    if (sendMode === 'schedule' && !scheduleTimes.some((t) => t.trim())) {
      toast({ title: 'Required', description: 'Pick at least one send date/time.', variant: 'destructive' });
      return;
    }

    setLoading(true);
    const assigneeIds = selectedUsers.map((u) => u.id);
    const scheduleLater = sendMode === 'schedule';
    const schedules = scheduleLater
      ? scheduleTimes.filter((t) => t.trim()).map((t) => new Date(t).toISOString())
      : [];

    const tasksPayload = validTasks.map((row) => ({
      title: row.subject.trim(),
      description: row.description.trim(),
      priority: row.priority,
      start_date: row.start_date || null,
      deadline: row.deadline,
      sourceFiles: row.pdfFile ? [row.pdfFile] : [],
    }));

    const res = await createBatchTasksWithAssignments(tasksPayload, assigneeIds, {
      notificationTemplate,
      schedules,
      scheduleLater,
    });

    if (res.success || res.count > 0) {
      toast({
        title: scheduleLater ? 'Tasks scheduled' : 'All tasks sent',
        description: scheduleLater
          ? `${res.count} task(s) scheduled. Messages will go out at 6-second intervals when due.`
          : `${res.count} task(s) sent to ${selectedUsers.length} recipient(s). WhatsApp messages are queued 6 seconds apart.`,
      });
      localStorage.removeItem(DRAFT_KEY);
      navigate('/admin/tasks/dashboard');
    } else {
      toast({ title: 'Failed to create tasks', description: res.error, variant: 'destructive' });
    }
    setLoading(false);
  };

  return (
    <div className="max-w-4xl mx-auto space-y-6 pb-10">
      <div>
        <h1 className="text-3xl font-bold text-[#003D82]">Create Task</h1>
        <p className="text-gray-500">
          Create multiple tasks with different periods, priorities, and PDFs. Send all at once to selected recipients.
        </p>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        <Card>
          <CardHeader>
            <CardTitle>1. Task Details</CardTitle>
            <CardDescription>Each task has its own subject, description, period, priority, and optional PDF.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {taskRows.map((row, index) => (
              <div key={`task-row-${index}`} className="rounded-xl border bg-slate-50/50 p-4 space-y-4">
                <div className="flex items-center justify-between">
                  <span className="text-sm font-semibold text-[#003D82]">Task {index + 1}</span>
                  {taskRows.length > 1 && (
                    <Button type="button" variant="ghost" size="sm" className="text-rose-600" onClick={() => removeTaskRow(index)}>
                      <Trash2 className="w-4 h-4 mr-1" /> Remove
                    </Button>
                  )}
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-[1fr_200px] gap-4">
                  <div className="space-y-3">
                    <div className="space-y-2">
                      <Label>Subject *</Label>
                      <Input
                        value={row.subject}
                        onChange={(e) => updateTaskRow(index, 'subject', e.target.value)}
                        placeholder="e.g. Software Testing Report"
                      />
                    </div>
                    <div className="space-y-2">
                      <Label>Description</Label>
                      <Textarea
                        rows={4}
                        value={row.description}
                        onChange={(e) => updateTaskRow(index, 'description', e.target.value)}
                        placeholder="Detailed instructions for the assignee..."
                      />
                    </div>
                  </div>

                  <div className="space-y-2">
                    <Label>Priority</Label>
                    <div className="rounded-lg border bg-white p-2 space-y-1">
                      {PRIORITIES.map((p) => (
                        <label
                          key={p}
                          className={`flex items-center gap-2 rounded-md px-3 py-2 text-sm cursor-pointer ${
                            row.priority === p ? 'bg-[#003D82] text-white' : 'hover:bg-slate-50'
                          }`}
                        >
                          <input
                            type="radio"
                            name={`priority-${index}`}
                            checked={row.priority === p}
                            onChange={() => updateTaskRow(index, 'priority', p)}
                            className="sr-only"
                          />
                          {p}
                        </label>
                      ))}
                    </div>
                  </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2 border-t">
                  <div className="space-y-2">
                    <Label>Start Date</Label>
                    <Input type="date" value={row.start_date} onChange={(e) => updateTaskRow(index, 'start_date', e.target.value)} />
                  </div>
                  <div className="space-y-2">
                    <Label>End Date *</Label>
                    <Input
                      type="date"
                      value={row.deadline}
                      min={row.start_date || new Date().toISOString().split('T')[0]}
                      onChange={(e) => updateTaskRow(index, 'deadline', e.target.value)}
                    />
                  </div>
                  <div className="space-y-2">
                    <Label>Attach PDF</Label>
                    {row.pdfFile ? (
                      <div className="flex items-center justify-between rounded-md border bg-white px-3 py-2 text-sm">
                        <span className="truncate flex items-center gap-1"><Paperclip className="w-3 h-3" />{row.pdfFile.name}</span>
                        <button type="button" className="text-xs text-rose-600 ml-2" onClick={() => updateTaskRow(index, 'pdfFile', null)}>Remove</button>
                      </div>
                    ) : (
                      <>
                        <input
                          ref={(el) => { fileInputRefs.current[index] = el; }}
                          type="file"
                          accept=".pdf,application/pdf"
                          className="hidden"
                          onChange={(e) => handlePdfChange(index, e)}
                        />
                        <Button type="button" variant="outline" className="w-full" onClick={() => fileInputRefs.current[index]?.click()}>
                          <Paperclip className="w-4 h-4 mr-2" /> Browse PDF
                        </Button>
                      </>
                    )}
                  </div>
                </div>
              </div>
            ))}
            <Button type="button" variant="outline" onClick={addTaskRow} className="w-full border-dashed">
              <Plus className="w-4 h-4 mr-2" /> Add Another Task
            </Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Users className="w-5 h-5" /> 2. Assign To *
            </CardTitle>
            <CardDescription>Click a category to browse names, then pick individuals or select all.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex flex-wrap gap-2">
              {ASSIGNEE_TABS.map((tab) => (
                <button
                  key={tab.id}
                  type="button"
                  onClick={() => { setAssigneeTab(tab.id); setSearchQuery(''); }}
                  className={`rounded-full px-3 py-1.5 text-xs font-semibold border ${
                    assigneeTab === tab.id
                      ? 'bg-[#003D82] text-white border-[#003D82]'
                      : 'bg-slate-100 text-slate-700 border-transparent hover:bg-slate-200'
                  }`}
                >
                  {tab.label}
                </button>
              ))}
              <Button type="button" variant="outline" size="sm" onClick={() => setShowNewUserDialog(true)}>
                <UserPlus className="w-4 h-4 mr-1" /> Create New User
              </Button>
            </div>

            <div className="flex gap-2">
              <div className="relative flex-1">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                <Input
                  className="pl-9"
                  placeholder="Search name, email, or phone..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                />
              </div>
              {activeTab?.selectAllLabel && (
                <Button type="button" variant="outline" onClick={handleSelectAllInCategory} disabled={selectingAll}>
                  {selectingAll ? 'Loading…' : activeTab.selectAllLabel}
                </Button>
              )}
            </div>

            <div className="max-h-52 overflow-auto rounded-xl border bg-white">
              {searchLoading ? (
                <div className="px-3 py-8 text-center text-sm text-gray-500 flex items-center justify-center gap-2">
                  <Loader2 className="w-4 h-4 animate-spin" /> Loading...
                </div>
              ) : availableResults.length === 0 ? (
                <div className="px-3 py-8 text-center text-sm text-gray-500">
                  No users in this category.
                  <button type="button" className="block mx-auto mt-2 text-[#003D82] underline" onClick={() => setShowNewUserDialog(true)}>
                    Create new user
                  </button>
                </div>
              ) : (
                availableResults.map((user) => (
                  <button
                    key={user.id}
                    type="button"
                    onClick={() => handleAddUser(user)}
                    className="block w-full border-b px-3 py-2.5 text-left text-sm hover:bg-slate-50 last:border-b-0"
                  >
                    <div className="font-semibold">{user.name || user.full_name || 'Unnamed'}</div>
                    <div className="text-xs text-gray-500">
                      {[user.email, user.phone, user.role].filter(Boolean).join(' · ') || '—'}
                    </div>
                  </button>
                ))
              )}
            </div>

            {selectedUsers.length > 0 && (
              <div className="flex flex-wrap gap-2">
                {selectedUsers.map((user) => (
                  <Badge key={user.id} variant="secondary" className="px-3 py-1.5 bg-blue-50 text-[#003D82] border-blue-200 flex items-center gap-2">
                    {user.name || user.full_name || user.email}
                    <button type="button" onClick={() => handleRemoveUser(user.id)}><X className="w-3 h-3" /></button>
                  </Badge>
                ))}
              </div>
            )}

            {selectedUsers.length === 0 && (
              <p className="text-xs text-red-500 flex items-center">
                <AlertCircle className="w-3 h-3 mr-1" /> Select at least one assignee or create a new user.
              </p>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2"><Clock className="w-5 h-5" /> 3. When to Send</CardTitle>
            <CardDescription>Messages and PDFs are sent with a 6-second interval between each WhatsApp delivery.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <RadioGroup value={sendMode} onValueChange={setSendMode} className="space-y-2">
              <label className="flex items-center gap-3 rounded-lg border p-3 cursor-pointer hover:bg-slate-50">
                <RadioGroupItem value="now" />
                <div>
                  <p className="font-medium flex items-center gap-2"><Send className="w-4 h-4" /> Send immediately</p>
                  <p className="text-xs text-gray-500">All tasks sent now, one message every 6 seconds.</p>
                </div>
              </label>
              <label className="flex items-center gap-3 rounded-lg border p-3 cursor-pointer hover:bg-slate-50">
                <RadioGroupItem value="schedule" />
                <div>
                  <p className="font-medium flex items-center gap-2"><Calendar className="w-4 h-4" /> Schedule for later</p>
                  <p className="text-xs text-gray-500">Notifications go out at the chosen time(s).</p>
                </div>
              </label>
            </RadioGroup>

            {sendMode === 'schedule' && (
              <div className="space-y-2 pl-1">
                {scheduleTimes.map((time, index) => (
                  <div key={`sched-${index}`} className="flex gap-2">
                    <Input
                      type="datetime-local"
                      value={time}
                      onChange={(e) => setScheduleTimes((prev) => prev.map((t, i) => (i === index ? e.target.value : t)))}
                    />
                    {scheduleTimes.length > 1 && (
                      <Button type="button" variant="ghost" className="text-rose-600" onClick={() => setScheduleTimes((prev) => prev.filter((_, i) => i !== index))}>Remove</Button>
                    )}
                  </div>
                ))}
                <Button type="button" variant="link" className="px-0" onClick={() => setScheduleTimes((prev) => [...prev, ''])}>
                  <Plus className="w-4 h-4 mr-1" /> Add another schedule
                </Button>
              </div>
            )}
          </CardContent>
        </Card>

        <Card className="bg-blue-50/40 border-blue-100">
          <CardHeader>
            <CardTitle className="text-base">Message Preview</CardTitle>
            <CardDescription>Each recipient receives priority, dates, and their personalized task details.</CardDescription>
          </CardHeader>
          <CardContent>
            <pre className="whitespace-pre-wrap text-xs font-mono text-gray-700 bg-white rounded-lg p-4 border">{notificationTemplate}</pre>
          </CardContent>
        </Card>

        <div className="flex flex-col sm:flex-row gap-3 justify-end sticky bottom-4 bg-gray-50/95 py-3 border-t">
          <Button type="button" variant="outline" onClick={() => navigate(-1)}>Cancel</Button>
          <Button type="submit" disabled={loading || selectedUsers.length === 0} className="bg-[#003D82] hover:bg-[#002a5a] min-w-[220px]">
            {loading ? <Loader2 className="w-4 h-4 mr-2 animate-spin" /> : <Send className="w-4 h-4 mr-2" />}
            {sendMode === 'schedule' ? 'Schedule & Send All' : 'Send All Tasks'}
          </Button>
        </div>
      </form>

      <QuickAssigneeDialog
        open={showNewUserDialog}
        onOpenChange={setShowNewUserDialog}
        onCreated={handleAddUser}
      />
    </div>
  );
};

export default CreateTaskPage;
