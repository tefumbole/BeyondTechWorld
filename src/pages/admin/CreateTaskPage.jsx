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
import { getAnnouncementSettings } from '@/services/announcementSettingsService';
import { normalizeScheduleTime } from '@/services/announcementService';
import QuickAssigneeDialog from '@/components/admin/QuickAssigneeDialog';
import {
  Loader2,
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
  Palette,
} from 'lucide-react';
import { DEFAULT_TASK_NOTIFICATION_TEMPLATE } from '@/utils/taskPersonalization';

const DRAFT_KEY = 'task_draft_new_v4';
const PRIORITIES = ['Low', 'Medium', 'High', 'Emergency'];

const TASK_COLORS = [
  { hex: '#003D82', name: 'Blue' },
  { hex: '#16a34a', name: 'Green' },
  { hex: '#d97706', name: 'Amber' },
  { hex: '#dc2626', name: 'Red' },
  { hex: '#7c3aed', name: 'Purple' },
  { hex: '#0d9488', name: 'Teal' },
];

const ASSIGNEE_TABS = [
  { id: 'all', label: 'All', selectAllLabel: 'Select all' },
  { id: 'staff', label: 'Staff', selectAllLabel: 'Select all staff' },
  { id: 'customers', label: 'Customers', selectAllLabel: 'Select all customers' },
];

const emptyTaskRow = (index = 0) => ({
  subject: '',
  description: '',
  start_date: '',
  start_time: '',
  deadline: '',
  deadline_time: '',
  priority: 'Medium',
  color: TASK_COLORS[index % TASK_COLORS.length].hex,
  pdfFile: null,
  sendMode: 'now',
  scheduleTimes: [''],
  assignees: [],
  ccUsers: [],
  assigneeTab: 'all',
  ccTab: 'all',
  searchQuery: '',
  ccSearchQuery: '',
  reminderTimes: [''],
});

const CreateTaskPage = () => {
  const navigate = useNavigate();
  const { toast } = useToast();
  const fileInputRefs = useRef({});

  const [loading, setLoading] = useState(false);
  const [assigneeSearchCache, setAssigneeSearchCache] = useState({});
  const [ccSearchCache, setCcSearchCache] = useState({});
  const [selectingAllFor, setSelectingAllFor] = useState(null);
  const [selectingAllCcFor, setSelectingAllCcFor] = useState(null);
  const [showNewUserDialog, setShowNewUserDialog] = useState(false);
  const [newUserTaskIndex, setNewUserTaskIndex] = useState(0);
  const [notificationTemplate] = useState(DEFAULT_TASK_NOTIFICATION_TEMPLATE);
  const [taskRows, setTaskRows] = useState([emptyTaskRow(0)]);
  const [tzSettings, setTzSettings] = useState({ timezone: 'Africa/Kigali', timezoneOffset: '+02:00' });

  useEffect(() => {
    getAnnouncementSettings()
      .then((s) => setTzSettings({ timezone: s.timezone || 'Africa/Kigali', timezoneOffset: s.timezoneOffset || '+02:00' }))
      .catch(() => {});

    const draft = localStorage.getItem(DRAFT_KEY);
    if (draft) {
      try {
        const parsed = JSON.parse(draft);
        setTaskRows(
          parsed.taskRows?.length
            ? parsed.taskRows.map((r, i) => ({
                ...emptyTaskRow(i),
                ...r,
                pdfFile: null,
                assignees: r.assignees || [],
                ccUsers: r.ccUsers || [],
                reminderTimes: r.reminderTimes?.length ? r.reminderTimes : [''],
              }))
            : [emptyTaskRow(0)]
        );
      } catch {
        /* ignore */
      }
    }
  }, []);

  useEffect(() => {
    const timer = setTimeout(() => {
      localStorage.setItem(
        DRAFT_KEY,
        JSON.stringify({ taskRows: taskRows.map(({ pdfFile, ...rest }) => rest) })
      );
    }, 1000);
    return () => clearTimeout(timer);
  }, [taskRows]);

  const assigneeFetchKey = taskRows.map((r) => `${r.assigneeTab}|${r.searchQuery}`).join(';;');

  useEffect(() => {
    let cancelled = false;
    const timer = setTimeout(async () => {
      const entries = await Promise.all(
        taskRows.map((row, index) =>
          searchUsersForTaskAssignment(row.searchQuery || '', row.assigneeTab || 'all').then((res) => ({
            index,
            data: res.success ? res.data || [] : [],
          }))
        )
      );
      if (cancelled) return;
      setAssigneeSearchCache((prev) => {
        const next = { ...prev };
        entries.forEach(({ index, data }) => { next[index] = data; });
        return next;
      });
    }, 300);
    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [assigneeFetchKey, taskRows.length]);

  const ccFetchKey = taskRows.map((r) => `${r.ccTab}|${r.ccSearchQuery}`).join(';;');

  useEffect(() => {
    let cancelled = false;
    const timer = setTimeout(async () => {
      const entries = await Promise.all(
        taskRows.map((row, index) =>
          searchUsersForTaskAssignment(row.ccSearchQuery || '', row.ccTab || 'all').then((res) => ({
            index,
            data: res.success ? res.data || [] : [],
          }))
        )
      );
      if (cancelled) return;
      setCcSearchCache((prev) => {
        const next = { ...prev };
        entries.forEach(({ index, data }) => { next[index] = data; });
        return next;
      });
    }, 300);
    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [ccFetchKey, taskRows.length]);

  useEffect(() => {
    if (taskRows.length === 0) return;
    searchUsersForTaskAssignment('', 'all').then((res) => {
      if (!res.success) return;
      setAssigneeSearchCache((prev) => {
        const next = { ...prev };
        for (let i = 0; i < taskRows.length; i += 1) {
          if (!next[i]?.length) next[i] = res.data || [];
        }
        return next;
      });
      setCcSearchCache((prev) => {
        const next = { ...prev };
        for (let i = 0; i < taskRows.length; i += 1) {
          if (!next[i]?.length) next[i] = res.data || [];
        }
        return next;
      });
    });
  }, [taskRows.length]);

  const updateTaskRow = (index, field, value) => {
    setTaskRows((prev) => prev.map((row, i) => (i === index ? { ...row, [field]: value } : row)));
  };

  const addTaskRow = () => setTaskRows((prev) => [...prev, emptyTaskRow(prev.length)]);

  const removeTaskRow = (index) => {
    if (taskRows.length === 1) return;
    setTaskRows((prev) => prev.filter((_, i) => i !== index));
  };

  const handleAddUserToTask = (taskIndex, user) => {
    setTaskRows((prev) =>
      prev.map((row, i) => {
        if (i !== taskIndex) return row;
        if (row.assignees.find((u) => u.id === user.id)) return row;
        return { ...row, assignees: [...row.assignees, user], searchQuery: '' };
      })
    );
  };

  const handleRemoveUserFromTask = (taskIndex, userId) => {
    setTaskRows((prev) =>
      prev.map((row, i) => (i === taskIndex ? { ...row, assignees: row.assignees.filter((u) => u.id !== userId) } : row))
    );
  };

  const handleSelectAllForTask = async (taskIndex) => {
    const row = taskRows[taskIndex];
    setSelectingAllFor(taskIndex);
    try {
      const res = await searchUsersForTaskAssignment('', row.assigneeTab || 'all');
      const rows = res.success ? res.data || [] : [];
      if (!rows.length) {
        toast({ title: 'No users found', variant: 'destructive' });
        return;
      }
      setTaskRows((prev) =>
        prev.map((r, i) => {
          if (i !== taskIndex) return r;
          const next = [...r.assignees];
          rows.forEach((u) => {
            if (!next.find((x) => x.id === u.id)) next.push(u);
          });
          return { ...r, assignees: next };
        })
      );
    } finally {
      setSelectingAllFor(null);
    }
  };

  const handleSelectAllCcForTask = async (taskIndex) => {
    const row = taskRows[taskIndex];
    setSelectingAllCcFor(taskIndex);
    try {
      const res = await searchUsersForTaskAssignment('', row.ccTab || 'all');
      const rows = res.success ? res.data || [] : [];
      if (!rows.length) {
        toast({ title: 'No users found', variant: 'destructive' });
        return;
      }
      setTaskRows((prev) =>
        prev.map((r, i) => {
          if (i !== taskIndex) return r;
          const next = [...r.ccUsers];
          rows.forEach((u) => {
            if (r.assignees.find((a) => a.id === u.id)) return;
            if (!next.find((x) => x.id === u.id)) next.push(u);
          });
          return { ...r, ccUsers: next };
        })
      );
    } finally {
      setSelectingAllCcFor(null);
    }
  };

  const handlePdfChange = (index, e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
      toast({ title: 'PDF only', variant: 'destructive' });
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

    const missingAssignees = validTasks.find((row) => !row.assignees.length);
    if (missingAssignees) {
      toast({ title: 'Required', description: 'Each task needs at least one assignee.', variant: 'destructive' });
      return;
    }

    const missingSchedule = validTasks.find(
      (row) => row.sendMode === 'schedule' && !(row.scheduleTimes || []).some((t) => String(t).trim())
    );
    if (missingSchedule) {
      toast({ title: 'Required', description: 'Scheduled tasks need at least one send date and time.', variant: 'destructive' });
      return;
    }

    setLoading(true);
    const tzOffset = tzSettings.timezoneOffset || '+02:00';

    const tasksPayload = validTasks.map((row) => {
      const scheduleLater = row.sendMode === 'schedule';
      const schedules = scheduleLater
        ? (row.scheduleTimes || [])
            .filter((t) => String(t).trim())
            .map((t) => normalizeScheduleTime(t, tzOffset))
        : [];

      return {
        title: row.subject.trim(),
        description: row.description.trim(),
        priority: row.priority,
        color: row.color,
        start_date: row.start_date || null,
        start_time: row.start_time || null,
        deadline: row.deadline,
        deadline_time: row.deadline_time || null,
        assigneeIds: row.assignees.map((u) => u.id),
        ccUserIds: (row.ccUsers || []).map((u) => u.id),
        reminderTimes: (row.reminderTimes || []).filter((t) => String(t).trim()).map((t) => normalizeScheduleTime(t, tzOffset)),
        sourceFiles: row.pdfFile ? [row.pdfFile] : [],
        scheduleLater,
        schedules,
      };
    });

    const res = await createBatchTasksWithAssignments(tasksPayload, { notificationTemplate });

    if (res.success || res.count > 0) {
      toast({
        title: 'All tasks submitted',
        description: `${res.count} task(s) created. WhatsApp messages are queued 6 seconds apart (${tzSettings.timezone}).`,
      });
      localStorage.removeItem(DRAFT_KEY);
      navigate('/admin/tasks/pending-acceptances');
    } else {
      toast({ title: 'Failed', description: res.error, variant: 'destructive' });
    }
    setLoading(false);
  };

  return (
    <div className="max-w-4xl mx-auto space-y-6 pb-10">
      <div>
        <h1 className="text-3xl font-bold text-[#003D82]">Create Task</h1>
        <p className="text-gray-500">
          Each task can have its own color, period, assignees, PDF, and send schedule. Click Send All when ready.
        </p>
        <p className="text-xs text-gray-400 mt-1">
          Schedule times use Announcements timezone: <strong>{tzSettings.timezone}</strong> ({tzSettings.timezoneOffset})
        </p>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        <Card>
          <CardHeader>
            <CardTitle>Tasks</CardTitle>
            <CardDescription>Configure each task independently, then send all together.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {taskRows.map((row, index) => {
              const tabMeta = ASSIGNEE_TABS.find((t) => t.id === row.assigneeTab);
              const available = (assigneeSearchCache[index] || []).filter(
                (u) => !row.assignees.find((a) => a.id === u.id)
              );

              return (
                <div
                  key={`task-row-${index}`}
                  className="rounded-xl border-2 bg-white overflow-hidden"
                  style={{ borderColor: row.color }}
                >
                  <div className="h-2" style={{ backgroundColor: row.color }} />
                  <div className="p-4 space-y-4">
                    <div className="flex items-center justify-between">
                      <span className="text-sm font-semibold" style={{ color: row.color }}>Task {index + 1}</span>
                      {taskRows.length > 1 && (
                        <Button type="button" variant="ghost" size="sm" className="text-rose-600" onClick={() => removeTaskRow(index)}>
                          <Trash2 className="w-4 h-4 mr-1" /> Remove
                        </Button>
                      )}
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-[1fr_180px] gap-4">
                      <div className="space-y-3">
                        <div className="space-y-2">
                          <Label>Subject *</Label>
                          <Input value={row.subject} onChange={(e) => updateTaskRow(index, 'subject', e.target.value)} placeholder="Task subject" />
                        </div>
                        <div className="space-y-2">
                          <Label>Description</Label>
                          <Textarea rows={3} value={row.description} onChange={(e) => updateTaskRow(index, 'description', e.target.value)} />
                        </div>
                      </div>
                      <div className="space-y-2">
                        <Label>Priority</Label>
                        <div className="rounded-lg border bg-slate-50 p-1 space-y-0.5">
                          {PRIORITIES.map((p) => (
                            <label
                              key={p}
                              className={`flex items-center rounded-md px-3 py-1.5 text-sm cursor-pointer ${
                                row.priority === p ? 'bg-[#003D82] text-white' : 'hover:bg-white'
                              }`}
                            >
                              <input type="radio" name={`priority-${index}`} checked={row.priority === p} onChange={() => updateTaskRow(index, 'priority', p)} className="sr-only" />
                              {p}
                            </label>
                          ))}
                        </div>
                      </div>
                    </div>

                    <div className="space-y-2">
                      <Label className="flex items-center gap-1"><Palette className="w-3 h-3" /> Task Color</Label>
                      <div className="flex flex-wrap gap-2">
                        {TASK_COLORS.map((c) => (
                          <button
                            key={c.hex}
                            type="button"
                            title={c.name}
                            onClick={() => updateTaskRow(index, 'color', c.hex)}
                            className={`h-8 w-8 rounded-full border-2 ${row.color === c.hex ? 'ring-2 ring-offset-2 ring-slate-400' : ''}`}
                            style={{ backgroundColor: c.hex }}
                          />
                        ))}
                      </div>
                    </div>

                    <div className="grid grid-cols-2 md:grid-cols-4 gap-3 border-t pt-3">
                      <div className="space-y-1">
                        <Label className="text-xs">Start Date</Label>
                        <Input type="date" value={row.start_date} onChange={(e) => updateTaskRow(index, 'start_date', e.target.value)} />
                      </div>
                      <div className="space-y-1">
                        <Label className="text-xs">Start Time</Label>
                        <Input type="time" value={row.start_time} onChange={(e) => updateTaskRow(index, 'start_time', e.target.value)} />
                      </div>
                      <div className="space-y-1">
                        <Label className="text-xs">End Date *</Label>
                        <Input type="date" value={row.deadline} min={row.start_date || undefined} onChange={(e) => updateTaskRow(index, 'deadline', e.target.value)} />
                      </div>
                      <div className="space-y-1">
                        <Label className="text-xs">End Time</Label>
                        <Input type="time" value={row.deadline_time} onChange={(e) => updateTaskRow(index, 'deadline_time', e.target.value)} />
                      </div>
                    </div>

                    <div className="space-y-1 border-t pt-3">
                      <Label className="text-xs">PDF (optional)</Label>
                      {row.pdfFile ? (
                        <div className="flex items-center justify-between rounded border px-3 py-2 text-sm">
                          <span className="truncate"><Paperclip className="w-3 h-3 inline mr-1" />{row.pdfFile.name}</span>
                          <button type="button" className="text-xs text-rose-600" onClick={() => updateTaskRow(index, 'pdfFile', null)}>Remove</button>
                        </div>
                      ) : (
                        <>
                          <input ref={(el) => { fileInputRefs.current[index] = el; }} type="file" accept=".pdf" className="hidden" onChange={(e) => handlePdfChange(index, e)} />
                          <Button type="button" variant="outline" size="sm" onClick={() => fileInputRefs.current[index]?.click()}>
                            <Paperclip className="w-4 h-4 mr-1" /> Browse PDF
                          </Button>
                        </>
                      )}
                    </div>

                    <div className="border-t pt-3 space-y-3">
                      <Label className="text-sm font-semibold">Assign To *</Label>
                      <div className="flex flex-wrap gap-1">
                        {ASSIGNEE_TABS.map((tab) => (
                          <button
                            key={tab.id}
                            type="button"
                            onClick={() => updateTaskRow(index, 'assigneeTab', tab.id)}
                            className={`rounded-full px-2.5 py-1 text-xs font-medium ${
                              row.assigneeTab === tab.id ? 'bg-[#003D82] text-white' : 'bg-slate-100 text-slate-700'
                            }`}
                          >
                            {tab.label}
                          </button>
                        ))}
                        <Button type="button" variant="outline" size="sm" className="h-7 text-xs" onClick={() => { setNewUserTaskIndex(index); setShowNewUserDialog(true); }}>
                          <UserPlus className="w-3 h-3 mr-1" /> New User
                        </Button>
                      </div>
                      <div className="flex gap-2">
                        <div className="relative flex-1">
                          <Search className="absolute left-2 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-gray-400" />
                          <Input className="pl-8 h-9 text-sm" placeholder="Search..." value={row.searchQuery} onChange={(e) => updateTaskRow(index, 'searchQuery', e.target.value)} />
                        </div>
                        <Button type="button" variant="outline" size="sm" className="h-9 text-xs" disabled={selectingAllFor === index} onClick={() => handleSelectAllForTask(index)}>
                          {selectingAllFor === index ? '…' : tabMeta?.selectAllLabel}
                        </Button>
                      </div>
                      <div className="max-h-36 overflow-auto rounded-lg border">
                        {available.length === 0 ? (
                          <p className="p-3 text-xs text-gray-500 text-center">No users — try another tab or create a user.</p>
                        ) : (
                          available.map((user) => (
                            <button key={user.id} type="button" onClick={() => handleAddUserToTask(index, user)} className="block w-full border-b px-3 py-2 text-left text-sm hover:bg-slate-50 last:border-0">
                              <div className="font-medium">{user.name || user.full_name}</div>
                              <div className="text-xs text-gray-500">{[user.email, user.phone].filter(Boolean).join(' · ')}</div>
                            </button>
                          ))
                        )}
                      </div>
                      {row.assignees.length > 0 && (
                        <div className="flex flex-wrap gap-1">
                          {row.assignees.map((user) => (
                            <Badge key={user.id} variant="secondary" className="text-xs gap-1" style={{ borderColor: row.color, color: row.color }}>
                              {user.name || user.full_name || user.email}
                              <button type="button" onClick={() => handleRemoveUserFromTask(index, user.id)}><X className="w-3 h-3" /></button>
                            </Badge>
                          ))}
                        </div>
                      )}
                      {row.assignees.length === 0 && (
                        <p className="text-xs text-red-500 flex items-center"><AlertCircle className="w-3 h-3 mr-1" /> Pick at least one assignee.</p>
                      )}
                    </div>

                    <div className="border-t pt-3 space-y-3">
                      <Label className="text-sm font-semibold">CC (Carbon Copy)</Label>
                      <p className="text-xs text-gray-500">Teachers or supervisors who should follow progress (not assignees).</p>
                      <div className="flex flex-wrap gap-1">
                        {ASSIGNEE_TABS.map((tab) => (
                          <button
                            key={`cc-${tab.id}`}
                            type="button"
                            onClick={() => updateTaskRow(index, 'ccTab', tab.id)}
                            className={`rounded-full px-2.5 py-1 text-xs font-medium ${
                              row.ccTab === tab.id ? 'bg-[#003D82] text-white' : 'bg-slate-100 text-slate-700'
                            }`}
                          >
                            {tab.label}
                          </button>
                        ))}
                      </div>
                      <div className="flex gap-2">
                        <div className="relative flex-1">
                          <Search className="absolute left-2 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-gray-400" />
                          <Input
                            className="pl-8 h-9 text-sm"
                            placeholder="Search CC recipients..."
                            value={row.ccSearchQuery || ''}
                            onChange={(e) => updateTaskRow(index, 'ccSearchQuery', e.target.value)}
                          />
                        </div>
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          className="h-9 text-xs"
                          disabled={selectingAllCcFor === index}
                          onClick={() => handleSelectAllCcForTask(index)}
                        >
                          {selectingAllCcFor === index ? '…' : ASSIGNEE_TABS.find((t) => t.id === row.ccTab)?.selectAllLabel || 'Select all'}
                        </Button>
                      </div>
                      <div className="max-h-28 overflow-auto rounded-lg border">
                        {(ccSearchCache[index] || [])
                          .filter((u) => !row.assignees.find((a) => a.id === u.id) && !row.ccUsers.find((c) => c.id === u.id))
                          .length === 0 ? (
                          <p className="p-3 text-xs text-gray-500 text-center">No users — try another tab or search.</p>
                        ) : (
                          (ccSearchCache[index] || [])
                            .filter((u) => !row.assignees.find((a) => a.id === u.id) && !row.ccUsers.find((c) => c.id === u.id))
                            .slice(0, 20)
                            .map((user) => (
                              <button
                                key={`cc-${user.id}`}
                                type="button"
                                onClick={() => updateTaskRow(index, 'ccUsers', [...row.ccUsers, user])}
                                className="block w-full border-b px-3 py-2 text-left text-sm hover:bg-slate-50 last:border-0"
                              >
                                <div className="font-medium">{user.name || user.full_name}</div>
                                <div className="text-xs text-gray-500">{[user.email, user.phone].filter(Boolean).join(' · ')}</div>
                              </button>
                            ))
                        )}
                      </div>
                      {row.ccUsers?.length > 0 && (
                        <div className="flex flex-wrap gap-1">
                          {row.ccUsers.map((user) => (
                            <Badge key={user.id} variant="outline" className="text-xs gap-1">
                              CC: {user.name || user.full_name}
                              <button type="button" onClick={() => updateTaskRow(index, 'ccUsers', row.ccUsers.filter((c) => c.id !== user.id))}>
                                <X className="w-3 h-3" />
                              </button>
                            </Badge>
                          ))}
                        </div>
                      )}
                    </div>

                    <div className="border-t pt-3 space-y-2">
                      <Label className="text-sm font-semibold flex items-center gap-1"><Clock className="w-4 h-4" /> Reminders</Label>
                      <p className="text-xs text-gray-500">Multiple reminders before deadline — message shows time remaining.</p>
                      {(row.reminderTimes || ['']).map((timeVal, ri) => (
                        <div key={ri} className="flex gap-2 items-center">
                          <Input
                            type="datetime-local"
                            value={timeVal}
                            onChange={(e) => {
                              const next = [...(row.reminderTimes || [''])];
                              next[ri] = e.target.value;
                              updateTaskRow(index, 'reminderTimes', next);
                            }}
                            className="max-w-xs"
                          />
                          {(row.reminderTimes || []).length > 1 && (
                            <Button type="button" variant="ghost" size="icon" className="h-9 w-9 text-red-500" onClick={() => updateTaskRow(index, 'reminderTimes', (row.reminderTimes || []).filter((_, i) => i !== ri))}>
                              <Trash2 className="w-4 h-4" />
                            </Button>
                          )}
                        </div>
                      ))}
                      <Button type="button" variant="outline" size="sm" className="text-xs" onClick={() => updateTaskRow(index, 'reminderTimes', [...(row.reminderTimes || ['']), ''])}>
                        <Plus className="w-3 h-3 mr-1" /> Add reminder
                      </Button>
                    </div>

                    <div className="border-t pt-3 space-y-2">
                      <Label className="text-sm font-semibold flex items-center gap-1"><Clock className="w-4 h-4" /> When to Send</Label>
                      <RadioGroup value={row.sendMode} onValueChange={(v) => updateTaskRow(index, 'sendMode', v)} className="flex flex-col sm:flex-row gap-2">
                        <label className={`flex items-center gap-2 rounded-lg border px-3 py-2 text-sm cursor-pointer flex-1 ${row.sendMode === 'now' ? 'border-[#003D82] bg-blue-50' : ''}`}>
                          <RadioGroupItem value="now" />
                          <Send className="w-3.5 h-3.5" /> Send immediately
                        </label>
                        <label className={`flex items-center gap-2 rounded-lg border px-3 py-2 text-sm cursor-pointer flex-1 ${row.sendMode === 'schedule' ? 'border-[#003D82] bg-blue-50' : ''}`}>
                          <RadioGroupItem value="schedule" />
                          <Calendar className="w-3.5 h-3.5" /> Schedule
                        </label>
                      </RadioGroup>
                      {row.sendMode === 'schedule' && (
                        <div className="space-y-2">
                          <p className="text-xs text-gray-500">Multi Schedule — add one or more reminder send times ({tzSettings.timezone})</p>
                          {(row.scheduleTimes || ['']).map((timeVal, si) => (
                            <div key={si} className="flex gap-2 items-center">
                              <Input
                                type="datetime-local"
                                value={timeVal}
                                onChange={(e) => {
                                  const next = [...(row.scheduleTimes || [''])];
                                  next[si] = e.target.value;
                                  updateTaskRow(index, 'scheduleTimes', next);
                                }}
                                className="max-w-xs"
                              />
                              {(row.scheduleTimes || []).length > 1 && (
                                <Button
                                  type="button"
                                  variant="ghost"
                                  size="icon"
                                  className="h-9 w-9 text-red-500"
                                  onClick={() => updateTaskRow(index, 'scheduleTimes', (row.scheduleTimes || []).filter((_, i) => i !== si))}
                                >
                                  <Trash2 className="w-4 h-4" />
                                </Button>
                              )}
                            </div>
                          ))}
                          <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="text-xs"
                            onClick={() => updateTaskRow(index, 'scheduleTimes', [...(row.scheduleTimes || ['']), ''])}
                          >
                            <Plus className="w-3 h-3 mr-1" /> Add schedule time
                          </Button>
                        </div>
                      )}
                    </div>
                  </div>
                </div>
              );
            })}

            <Button type="button" variant="outline" onClick={addTaskRow} className="w-full border-dashed">
              <Plus className="w-4 h-4 mr-2" /> Add Another Task
            </Button>
          </CardContent>
        </Card>

        <Card className="bg-blue-50/40 border-blue-100">
          <CardHeader>
            <CardTitle className="text-base">Message Preview</CardTitle>
          </CardHeader>
          <CardContent>
            <pre className="whitespace-pre-wrap text-xs font-mono text-gray-700 bg-white rounded-lg p-4 border">{notificationTemplate}</pre>
          </CardContent>
        </Card>

        <div className="flex flex-col sm:flex-row gap-3 justify-end sticky bottom-4 bg-gray-50/95 py-3 border-t">
          <Button type="button" variant="outline" onClick={() => navigate(-1)}>Cancel</Button>
          <Button type="submit" disabled={loading} className="bg-[#003D82] hover:bg-[#002a5a] min-w-[220px]">
            {loading ? <Loader2 className="w-4 h-4 mr-2 animate-spin" /> : <Send className="w-4 h-4 mr-2" />}
            Send All Tasks
          </Button>
        </div>
      </form>

      <QuickAssigneeDialog
        open={showNewUserDialog}
        onOpenChange={setShowNewUserDialog}
        onCreated={(user) => handleAddUserToTask(newUserTaskIndex, user)}
      />
    </div>
  );
};

export default CreateTaskPage;
