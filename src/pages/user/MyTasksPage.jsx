import React, { useState, useEffect } from 'react';
import { useToast } from '@/components/ui/use-toast';
import { getMyTasks, acceptTaskAssignment, updateTaskProgress, bulkRemoveMyTaskAssignments } from '@/services/taskService';
import { getTaskCategories } from '@/services/taskCategoryService';
import { Card, CardContent, CardFooter, CardHeader } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Loader2, Calendar, CheckCircle, Clock, AlertCircle, ArrowRight, User, Save, Pencil, Trash2 } from 'lucide-react';
import { format } from 'date-fns';
import { isTaskOverdue } from '@/utils/taskDeadline';
import TaskDetailsModal from '@/components/user/TaskDetailsModal';
import TaskBulkActionsBar from '@/components/user/TaskBulkActionsBar';
import SignaturePadModal from '@/components/SignaturePadModal';
import { Slider } from '@/components/ui/slider';

export const getPriorityColor = (priority) => {
    switch(priority) {
        case 'Low': return 'bg-blue-100 text-blue-800 hover:bg-blue-100';
        case 'Medium': return 'bg-yellow-100 text-yellow-800 hover:bg-yellow-100';
        case 'High': return 'bg-orange-100 text-orange-800 hover:bg-orange-100';
        case 'Critical': return 'bg-red-100 text-red-800 hover:bg-red-100';
        default: return 'bg-gray-100 text-gray-800 hover:bg-gray-100';
    }
};

export const getStatusColor = (status) => {
    switch(status) {
        case 'Pending': return 'bg-gray-100 text-gray-700 hover:bg-gray-100 border-gray-200';
        case 'Accepted': return 'bg-indigo-100 text-indigo-700 hover:bg-indigo-100 border-indigo-200';
        case 'In Progress': return 'bg-blue-100 text-blue-700 hover:bg-blue-100 border-blue-200';
        case 'Completed': return 'bg-green-100 text-green-700 hover:bg-green-100 border-green-200';
        case 'Overdue': return 'bg-red-100 text-red-700 hover:bg-red-100 border-red-200';
        case 'Declined': return 'bg-red-50 text-red-500 hover:bg-red-50 border-red-100';
        default: return 'bg-gray-100 text-gray-700 hover:bg-gray-100';
    }
};

export const getStatusIcon = (status) => {
    switch(status) {
        case 'Pending': return <Clock className="w-3 h-3 mr-1 inline" />;
        case 'Accepted': return <CheckCircle className="w-3 h-3 mr-1 inline text-indigo-500" />;
        case 'In Progress': return <Clock className="w-3 h-3 mr-1 inline text-blue-500 animate-pulse" />;
        case 'Completed': return <CheckCircle className="w-3 h-3 mr-1 inline" />;
        case 'Overdue': return <AlertCircle className="w-3 h-3 mr-1 inline" />;
        default: return null;
    }
};

const MyTasksPage = () => {
  const [tasks, setTasks] = useState([]);
  const [categories, setCategories] = useState([]);
  const [loading, setLoading] = useState(true);
  
  const [statusFilter, setStatusFilter] = useState('All');
  const [categoryFilter, setCategoryFilter] = useState('All');
  
  const [selectedTask, setSelectedTask] = useState(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [updatingTaskId, setUpdatingTaskId] = useState(null);
  const [selectedIds, setSelectedIds] = useState([]);
  const [bulkDeleting, setBulkDeleting] = useState(false);
  const [signatureOpen, setSignatureOpen] = useState(false);
  const [acceptingId, setAcceptingId] = useState(null);
  const { toast } = useToast();

  useEffect(() => {
    const init = async () => {
        const catRes = await getTaskCategories();
        if(catRes.success) setCategories(catRes.data);
    }
    init();
  }, []);

  const loadTasks = async () => {
    setLoading(true);
    const res = await getMyTasks(statusFilter, categoryFilter);
    if (res.success) {
      setTasks(res.data);
    } else {
      toast({ title: "Error loading tasks", description: res.error, variant: "destructive" });
    }
    setLoading(false);
  };

  useEffect(() => {
    loadTasks();
    setSelectedIds([]);
  }, [statusFilter, categoryFilter]);

  const handleAccept = (e, assignmentId) => {
    e.stopPropagation();
    setAcceptingId(assignmentId);
    setSignatureOpen(true);
  };

  const handleSignatureCapture = async (signatureDataUrl) => {
    if (!acceptingId) return;
    const res = await acceptTaskAssignment(acceptingId, signatureDataUrl);
    setAcceptingId(null);
    if (res.success) {
      toast({ title: 'Task accepted!', description: 'Your signature was recorded.' });
      loadTasks();
    } else {
      toast({ title: 'Error accepting task', description: res.error, variant: 'destructive' });
    }
  };

  const handleQuickUpdate = async (e, task, newProgress, newStatus) => {
    e.stopPropagation();
    setUpdatingTaskId(task.assignment_id);
    
    const res = await updateTaskProgress(task.assignment_id, task.id, newProgress, newStatus);
    if (res.success) {
        toast({ title: "Task Updated", description: "Progress saved successfully." });
        loadTasks();
    } else {
        toast({ title: "Update Failed", description: res.error, variant: "destructive" });
    }
    setUpdatingTaskId(null);
  };

  const handleLocalChange = (assignmentId, field, value) => {
      setTasks(prev => prev.map(t => {
          if (t.assignment_id === assignmentId) {
              return { ...t, [field]: value };
          }
          return t;
      }));
  };

  const openTask = (task) => {
    setSelectedTask(task);
    setIsModalOpen(true);
  };

  const toggleSelection = (assignmentId) => {
    setSelectedIds((prev) =>
      prev.includes(assignmentId)
        ? prev.filter((id) => id !== assignmentId)
        : [...prev, assignmentId]
    );
  };

  const handleSelectAll = (checked) => {
    setSelectedIds(checked ? tasks.map((t) => t.assignment_id) : []);
  };

  const handleBulkDelete = async (ids = selectedIds) => {
    if (!ids.length) return;
    if (!window.confirm(`Remove ${ids.length} selected task assignment(s) from your list?`)) return;

    setBulkDeleting(true);
    const res = await bulkRemoveMyTaskAssignments(ids);
    if (res.success) {
      toast({ title: 'Tasks removed', description: `${res.count} assignment(s) deleted.` });
      setSelectedIds([]);
      loadTasks();
    } else {
      toast({ title: 'Delete failed', description: res.error, variant: 'destructive' });
    }
    setBulkDeleting(false);
  };

  const statuses = ['All', 'Pending', 'Accepted', 'In Progress', 'Completed', 'Overdue'];

  return (
    <div className="max-w-7xl mx-auto space-y-6">
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-bold text-[#003D82]">My Tasks</h1>
          <p className="text-gray-500">Manage your assigned tasks and update progress.</p>
        </div>
      </div>

      <div className="flex flex-wrap gap-4 items-center bg-white p-3 rounded-lg shadow-sm border">
        <div className="flex gap-2 overflow-x-auto">
            {statuses.map(s => (
            <Button
                key={s}
                variant={statusFilter === s ? "default" : "outline"}
                className={statusFilter === s ? "bg-[#003D82]" : ""}
                onClick={() => setStatusFilter(s)}
                size="sm"
            >
                {s}
            </Button>
            ))}
        </div>
        <div className="h-6 w-px bg-gray-200 hidden md:block"></div>
        <Select value={categoryFilter} onValueChange={setCategoryFilter}>
            <SelectTrigger className="w-[180px] bg-white text-gray-900 border-gray-300">
                <SelectValue placeholder="Filter Category" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="All">All Categories</SelectItem>
                {categories.map(c => <SelectItem key={c.id} value={c.id}>{c.name}</SelectItem>)}
            </SelectContent>
        </Select>
      </div>

      {!loading && tasks.length > 0 && (
        <TaskBulkActionsBar
          totalCount={tasks.length}
          selectedIds={selectedIds}
          onSelectAll={handleSelectAll}
          onClearSelection={() => setSelectedIds([])}
          onDeleteSelected={handleBulkDelete}
          deleting={bulkDeleting}
          deleteLabel="Delete Selected"
        />
      )}

      {loading ? (
        <div className="flex justify-center py-12">
          <Loader2 className="w-8 h-8 animate-spin text-[#003D82]" />
        </div>
      ) : tasks.length === 0 ? (
        <div className="text-center py-12 bg-gray-50 rounded-lg border border-dashed">
          <CheckCircle className="w-12 h-12 mx-auto text-gray-300 mb-3" />
          <h3 className="text-lg font-medium text-gray-900">No tasks found</h3>
          <p className="text-gray-500">You don't have any tasks matching the selected filters.</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {tasks.map(task => {
            const isCompleted = task.assignment_status === 'Completed';
            const isPending = task.assignment_status === 'Pending';
            const isDeclined = task.assignment_status === 'Declined';
            
            return (
            <Card key={task.assignment_id} className={`flex flex-col hover:shadow-lg transition-shadow border-t-4 ${(isCompleted || isDeclined) ? 'opacity-75' : ''}`} 
                  style={{borderTopColor: task.priority === 'Critical' ? '#ef4444' : task.priority === 'High' ? '#f97316' : task.priority === 'Medium' ? '#eab308' : '#22c55e'}}>
              <CardHeader className="pb-2">
                <div className="flex justify-between items-start mb-2 gap-2">
                  <div className="flex items-start gap-2 flex-1 min-w-0">
                    <Checkbox
                      checked={selectedIds.includes(task.assignment_id)}
                      onCheckedChange={() => toggleSelection(task.assignment_id)}
                      onClick={(e) => e.stopPropagation()}
                      className="mt-1"
                      aria-label={`Select ${task.title}`}
                    />
                    <div className="flex gap-2 flex-wrap">
                      <Badge className={getPriorityColor(task.priority)}>{task.priority}</Badge>
                      {task.task_categories && (
                          <Badge variant="outline" style={{borderColor: task.task_categories.color, color: task.task_categories.color, backgroundColor: `${task.task_categories.color}15`}}>
                              {task.task_categories.name}
                          </Badge>
                      )}
                    </div>
                  </div>
                  <div className="flex items-center gap-1 shrink-0">
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      className="h-8 w-8 text-[#003D82]"
                      onClick={(e) => { e.stopPropagation(); openTask(task); }}
                      title="Edit task"
                    >
                      <Pencil className="w-4 h-4" />
                    </Button>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      className="h-8 w-8 text-red-500 hover:text-red-700"
                      onClick={(e) => {
                        e.stopPropagation();
                        handleBulkDelete([task.assignment_id]);
                      }}
                      title="Remove task"
                    >
                      <Trash2 className="w-4 h-4" />
                    </Button>
                    <Badge variant="outline" className={getStatusColor(task.assignment_status)}>
                      {getStatusIcon(task.assignment_status)} {task.assignment_status}
                    </Badge>
                  </div>
                </div>
                <h3 className="font-bold text-lg text-gray-900 line-clamp-2" title={task.title}>{task.title}</h3>
                {task.created_by_profile && (
                    <div className="flex items-center text-xs text-gray-500 mt-1">
                        <User className="w-3 h-3 mr-1" />
                        Assigned by {task.created_by_profile.full_name}
                    </div>
                )}
              </CardHeader>
              <CardContent className="flex-1 pb-4">
                <p className="text-sm text-gray-500 line-clamp-2 mb-4">{task.description}</p>
                
                {!isPending && !isDeclined && (
                    <div className="space-y-4 bg-gray-50 p-3 rounded-lg border border-gray-100" onClick={e => e.stopPropagation()}>
                        <div className="flex justify-between text-xs font-medium text-gray-700">
                            <span>Update Progress</span>
                            <span>{task.my_progress}%</span>
                        </div>
                        <Slider 
                            value={[task.my_progress || 0]} 
                            max={100} 
                            step={5} 
                            disabled={isCompleted || updatingTaskId === task.assignment_id}
                            onValueChange={(vals) => handleLocalChange(task.assignment_id, 'my_progress', vals[0])}
                            className="w-full"
                        />
                        <div className="flex items-center gap-2">
                            <Select 
                                value={task.assignment_status} 
                                onValueChange={(val) => handleLocalChange(task.assignment_id, 'assignment_status', val)}
                                disabled={isCompleted || updatingTaskId === task.assignment_id}
                            >
                                <SelectTrigger className="h-8 text-xs bg-white text-gray-900 flex-1">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="Accepted">Accepted</SelectItem>
                                    <SelectItem value="In Progress">In Progress</SelectItem>
                                    <SelectItem value="Completed">Completed</SelectItem>
                                </SelectContent>
                            </Select>
                            
                            {!isCompleted && (
                                <Button 
                                    size="sm" 
                                    className="h-8 px-3 bg-[#003D82] text-white"
                                    disabled={updatingTaskId === task.assignment_id}
                                    onClick={(e) => handleQuickUpdate(e, task, task.my_progress, task.assignment_status)}
                                >
                                    {updatingTaskId === task.assignment_id ? <Loader2 className="w-3 h-3 animate-spin" /> : <Save className="w-3 h-3" />}
                                </Button>
                            )}
                        </div>
                    </div>
                )}

                <div className="mt-4">
                  {task.deadline && (
                    <div className="flex items-center justify-between text-xs text-gray-500">
                      <span className="flex items-center">
                          <Calendar className="w-4 h-4 mr-2 text-gray-400" />
                          <span className={isTaskOverdue(task.deadline, task.deadline_time) && !isCompleted ? 'text-red-500 font-medium' : ''}>
                              Due: {format(new Date(task.deadline), 'MMM dd, yyyy')}{task.deadline_time ? ` ${String(task.deadline_time).slice(0, 5)}` : ''}
                          </span>
                      </span>
                      {task.last_update_at && (
                          <span className="text-[10px] text-gray-400" title="Last updated">
                              Upd: {format(new Date(task.last_update_at), 'MM/dd HH:mm')}
                          </span>
                      )}
                    </div>
                  )}
                </div>
              </CardContent>
              <CardFooter className="bg-gray-50/50 border-t p-3 flex justify-end">
                {isPending ? (
                  <Button size="sm" onClick={(e) => handleAccept(e, task.assignment_id)} className="w-full bg-[#003D82] text-white hover:bg-[#002a5a]">
                    Accept Task
                  </Button>
                ) : (
                  <Button variant="ghost" size="sm" className="text-[#003D82] hover:text-[#002a5a] hover:bg-blue-50 w-full" onClick={(e) => { e.stopPropagation(); openTask(task); }}>
                    View Full Details <ArrowRight className="w-4 h-4 ml-2" />
                  </Button>
                )}
              </CardFooter>
            </Card>
          )})}
        </div>
      )}

      <TaskDetailsModal 
        isOpen={isModalOpen} 
        onClose={() => setIsModalOpen(false)} 
        task={selectedTask}
        onTaskUpdated={loadTasks}
      />

      <SignaturePadModal
        isOpen={signatureOpen}
        onClose={() => {
          setSignatureOpen(false);
          setAcceptingId(null);
        }}
        onSignatureCapture={handleSignatureCapture}
        title="Sign to Accept Task"
        description="Draw your signature to confirm you accept this task assignment."
      />
    </div>
  );
};

export default MyTasksPage;