import React, { useState, useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { useToast } from '@/components/ui/use-toast';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { createTaskWithAssignments } from '@/services/taskService';
import { getAllUsersForAssignment } from '@/services/userService';
import { Loader2, Save, Users, AlertCircle, X, Search, Paperclip, Calendar, Plus } from 'lucide-react';
import {
  TASK_PLACEHOLDERS,
  DEFAULT_TASK_NOTIFICATION_TEMPLATE,
} from '@/utils/taskPersonalization';

const DRAFT_KEY = 'task_draft_new';

const CreateTaskPage = () => {
  const navigate = useNavigate();
  const { toast } = useToast();
  const dropdownRef = useRef(null);
  const descriptionRef = useRef(null);
  const fileInputRef = useRef(null);

  const [loading, setLoading] = useState(false);
  const [users, setUsers] = useState([]);
  const [selectedUsers, setSelectedUsers] = useState([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [showUserDropdown, setShowUserDropdown] = useState(false);
  const [sourceFiles, setSourceFiles] = useState([]);
  const [scheduleLater, setScheduleLater] = useState(false);
  const [scheduleTimes, setScheduleTimes] = useState(['']);
  const [notificationTemplate, setNotificationTemplate] = useState(DEFAULT_TASK_NOTIFICATION_TEMPLATE);

  const [formData, setFormData] = useState({
    title: '',
    description: '',
    priority: 'Medium',
    start_date: '',
    deadline: '',
  });

  useEffect(() => {
    fetchUsers();
    const draft = localStorage.getItem(DRAFT_KEY);
    if (draft) {
      try {
        const parsed = JSON.parse(draft);
        setFormData(parsed.formData || formData);
        setSelectedUsers(parsed.selectedUsers || []);
        setNotificationTemplate(parsed.notificationTemplate || DEFAULT_TASK_NOTIFICATION_TEMPLATE);
        setScheduleLater(parsed.scheduleLater || false);
        setScheduleTimes(parsed.scheduleTimes || ['']);
      } catch (e) {
        /* ignore */
      }
    }

    const handleClickOutside = (event) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
        setShowUserDropdown(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  useEffect(() => {
    const timer = setTimeout(() => {
      localStorage.setItem(
        DRAFT_KEY,
        JSON.stringify({ formData, selectedUsers, notificationTemplate, scheduleLater, scheduleTimes })
      );
    }, 1000);
    return () => clearTimeout(timer);
  }, [formData, selectedUsers, notificationTemplate, scheduleLater, scheduleTimes]);

  const fetchUsers = async () => {
    const res = await getAllUsersForAssignment();
    if (res.success) setUsers(res.data);
    else toast({ title: 'Failed to load users', description: res.error, variant: 'destructive' });
  };

  const insertPlaceholder = (token) => {
    const el = descriptionRef.current;
    if (!el) {
      setFormData((prev) => ({ ...prev, description: `${prev.description}${token}` }));
      return;
    }
    const start = el.selectionStart ?? el.value.length;
    const end = el.selectionEnd ?? el.value.length;
    const next = `${formData.description.slice(0, start)}${token}${formData.description.slice(end)}`;
    setFormData({ ...formData, description: next });
  };

  const handleAddUser = (user) => {
    if (!selectedUsers.find((u) => u.id === user.id)) {
      setSelectedUsers([...selectedUsers, user]);
    }
    setSearchQuery('');
    setShowUserDropdown(false);
  };

  const handleRemoveUser = (userId) => {
    setSelectedUsers(selectedUsers.filter((u) => u.id !== userId));
  };

  const filteredUsers = users.filter(
    (user) =>
      !selectedUsers.find((su) => su.id === user.id) &&
      ((user.name || user.full_name || '').toLowerCase().includes(searchQuery.toLowerCase()) ||
        (user.email || '').toLowerCase().includes(searchQuery.toLowerCase()))
  );

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!formData.title.trim()) return toast({ title: 'Required', description: 'Title is required', variant: 'destructive' });
    if (!formData.deadline) return toast({ title: 'Required', description: 'Deadline is required', variant: 'destructive' });
    if (selectedUsers.length === 0) return toast({ title: 'Required', description: 'Assign at least one user', variant: 'destructive' });

    setLoading(true);
    const assigneeIds = selectedUsers.map((u) => u.id);
    const schedules = scheduleLater
      ? scheduleTimes.filter((t) => t.trim()).map((t) => new Date(t).toISOString())
      : [];

    const res = await createTaskWithAssignments(formData, assigneeIds, {
      notificationTemplate,
      sourceFiles,
      schedules,
      scheduleLater,
    });

    if (res.success) {
      toast({
        title: scheduleLater ? 'Task scheduled' : 'Task created',
        description: scheduleLater
          ? 'Assignees will be notified at the scheduled times.'
          : 'Personalized WhatsApp notifications sent with dashboard links.',
      });
      localStorage.removeItem(DRAFT_KEY);
      navigate('/admin/tasks/dashboard');
    } else {
      toast({ title: 'Failed to create task', description: res.error, variant: 'destructive' });
    }
    setLoading(false);
  };

  return (
    <div className="max-w-5xl mx-auto space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-[#003D82]">Create New Task</h1>
        <p className="text-gray-500">Assign personalized tasks with documents, placeholders, and optional schedules.</p>
      </div>

      <form onSubmit={handleSubmit} className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <Card className="shadow-md">
          <CardHeader className="bg-gray-50/50 border-b">
            <CardTitle>Task Details</CardTitle>
          </CardHeader>
          <CardContent className="space-y-6 p-6">
            <div className="space-y-2">
              <Label htmlFor="title">Task Title <span className="text-red-500">*</span></Label>
              <Input
                id="title"
                value={formData.title}
                onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                placeholder="e.g. Software Testing Report"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="description">Instructions (supports placeholders)</Label>
              <div className="mb-2 flex flex-wrap gap-2">
                {TASK_PLACEHOLDERS.map((token) => (
                  <button
                    key={token}
                    type="button"
                    onClick={() => insertPlaceholder(token)}
                    className="rounded-lg border bg-slate-50 px-2 py-1 text-xs font-medium hover:bg-slate-100"
                  >
                    {token}
                  </button>
                ))}
              </div>
              <Textarea
                ref={descriptionRef}
                id="description"
                className="min-h-[140px] font-mono text-sm"
                placeholder="Hi {name}, please complete..."
                value={formData.description}
                onChange={(e) => setFormData({ ...formData, description: e.target.value })}
              />
              <p className="text-xs text-gray-500">Each assignee receives their own personalized message.</p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="space-y-2">
                <Label>Priority</Label>
                <Select value={formData.priority} onValueChange={(v) => setFormData({ ...formData, priority: v })}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    {['Low', 'Medium', 'High', 'Critical'].map((p) => (
                      <SelectItem key={p} value={p}>{p}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>Start Date</Label>
                <Input type="date" value={formData.start_date} onChange={(e) => setFormData({ ...formData, start_date: e.target.value })} />
              </div>
              <div className="space-y-2">
                <Label>Deadline <span className="text-red-500">*</span></Label>
                <Input
                  type="date"
                  value={formData.deadline}
                  min={new Date().toISOString().split('T')[0]}
                  onChange={(e) => setFormData({ ...formData, deadline: e.target.value })}
                />
              </div>
            </div>

            <div className="space-y-2">
              <Label>Source Documents (PDF, Word, etc.)</Label>
              <div className="rounded-xl border-2 border-dashed bg-slate-50/60 px-4 py-4">
                {sourceFiles.length > 0 && (
                  <ul className="mb-3 space-y-1 text-sm">
                    {sourceFiles.map((file, index) => (
                      <li key={`${file.name}-${index}`} className="flex justify-between rounded bg-white px-3 py-2">
                        <span className="truncate">{file.name}</span>
                        <button type="button" className="text-xs text-rose-600" onClick={() => setSourceFiles((prev) => prev.filter((_, i) => i !== index))}>Remove</button>
                      </li>
                    ))}
                  </ul>
                )}
                <input
                  ref={fileInputRef}
                  type="file"
                  multiple
                  className="hidden"
                  onChange={(e) => {
                    if (e.target.files?.length) setSourceFiles((prev) => [...prev, ...Array.from(e.target.files)]);
                    e.target.value = '';
                  }}
                />
                <Button type="button" variant="outline" onClick={() => fileInputRef.current?.click()}>
                  <Paperclip className="w-4 h-4 mr-2" /> Browse Files
                </Button>
              </div>
            </div>

            <div className="space-y-3 pt-4 border-t">
              <Label className="flex items-center text-base font-semibold">
                <Users className="w-5 h-5 mr-2 text-[#003D82]" /> Assign To <span className="text-red-500 ml-1">*</span>
              </Label>
              {selectedUsers.length > 0 && (
                <div className="flex flex-wrap gap-2 mb-3">
                  {selectedUsers.map((user) => (
                    <Badge key={user.id} variant="secondary" className="px-3 py-1.5 bg-blue-50 text-[#003D82] border-blue-200 flex items-center gap-2">
                      {user.name || user.full_name || user.email}
                      <button type="button" onClick={() => handleRemoveUser(user.id)}><X className="w-3 h-3" /></button>
                    </Badge>
                  ))}
                </div>
              )}
              <div className="relative" ref={dropdownRef}>
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                <Input
                  className="pl-9"
                  placeholder="Search users by name or email..."
                  value={searchQuery}
                  onChange={(e) => { setSearchQuery(e.target.value); setShowUserDropdown(true); }}
                  onFocus={() => setShowUserDropdown(true)}
                />
                {showUserDropdown && (
                  <div className="absolute z-10 w-full mt-1 bg-white border rounded-md shadow-lg max-h-60 overflow-y-auto">
                    {filteredUsers.length === 0 ? (
                      <div className="p-4 text-center text-gray-500 text-sm">No users found.</div>
                    ) : (
                      filteredUsers.map((user) => (
                        <div key={user.id} className="px-4 py-2 hover:bg-gray-50 cursor-pointer border-b last:border-b-0" onClick={() => handleAddUser(user)}>
                          <p className="font-medium">{user.name || user.full_name || 'Unnamed'}</p>
                          <p className="text-xs text-gray-500">{user.email}</p>
                        </div>
                      ))
                    )}
                  </div>
                )}
              </div>
              {selectedUsers.length === 0 && (
                <p className="text-xs text-red-500 flex items-center"><AlertCircle className="w-3 h-3 mr-1" /> Select at least one assignee.</p>
              )}
            </div>
          </CardContent>
        </Card>

        <div className="space-y-6">
          <Card>
            <CardHeader><CardTitle>WhatsApp Notification</CardTitle></CardHeader>
            <CardContent className="space-y-3">
              <p className="text-xs text-gray-500">Use placeholders. Each person gets a unique link to sign in or create a staff account.</p>
              <div className="flex flex-wrap gap-2">
                {['{name}', '{task_title}', '{deadline}', '{login_link}', '{document_links}'].map((token) => (
                  <button
                    key={token}
                    type="button"
                    onClick={() => setNotificationTemplate((prev) => `${prev}${token}`)}
                    className="rounded border px-2 py-1 text-xs bg-slate-50"
                  >
                    {token}
                  </button>
                ))}
              </div>
              <Textarea rows={12} value={notificationTemplate} onChange={(e) => setNotificationTemplate(e.target.value)} className="font-mono text-xs" />
            </CardContent>
          </Card>

          <Card>
            <CardHeader><CardTitle>Notification Schedule</CardTitle></CardHeader>
            <CardContent className="space-y-3">
              <label className="flex items-center gap-2 text-sm">
                <Checkbox checked={scheduleLater} onCheckedChange={setScheduleLater} />
                <Calendar className="w-4 h-4" /> Schedule notifications for later
              </label>
              {scheduleLater && scheduleTimes.map((time, index) => (
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
              {scheduleLater && (
                <Button type="button" variant="link" className="px-0" onClick={() => setScheduleTimes((prev) => [...prev, ''])}>
                  <Plus className="w-4 h-4 mr-1" /> Add another schedule
                </Button>
              )}
            </CardContent>
          </Card>

          <div className="flex flex-col gap-2">
            <Button type="button" variant="outline" onClick={() => navigate(-1)}>Cancel</Button>
            <Button type="submit" disabled={loading || selectedUsers.length === 0} className="bg-[#003D82] hover:bg-[#002a5a]">
              {loading ? <Loader2 className="w-4 h-4 mr-2 animate-spin" /> : <Save className="w-4 h-4 mr-2" />}
              {scheduleLater ? 'Create & Schedule Notifications' : 'Create & Notify Now'}
            </Button>
          </div>
        </div>
      </form>
    </div>
  );
};

export default CreateTaskPage;
