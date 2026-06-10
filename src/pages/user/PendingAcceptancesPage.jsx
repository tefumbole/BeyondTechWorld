import React, { useState, useEffect } from 'react';
import { useLocation } from 'react-router-dom';
import { useToast } from '@/components/ui/use-toast';
import {
  getMyTasks,
  getAllPendingAcceptances,
  acceptTaskAssignment,
  declineTaskAssignment,
  bulkDeclineTaskAssignments,
} from '@/services/taskService';
import { Card, CardContent, CardFooter, CardHeader } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Loader2, Calendar, Clock, AlertCircle, User, Check, X, Pencil, Trash2 } from 'lucide-react';
import { format } from 'date-fns';
import TaskDetailsModal from '@/components/user/TaskDetailsModal';
import TaskBulkActionsBar from '@/components/user/TaskBulkActionsBar';

export const getPriorityColor = (priority) => {
  switch (priority) {
    case 'Low': return 'bg-blue-100 text-blue-800 hover:bg-blue-100';
    case 'Medium': return 'bg-yellow-100 text-yellow-800 hover:bg-yellow-100';
    case 'High': return 'bg-orange-100 text-orange-800 hover:bg-orange-100';
    case 'Critical': return 'bg-red-100 text-red-800 hover:bg-red-100';
    default: return 'bg-gray-100 text-gray-800 hover:bg-gray-100';
  }
};

const PendingAcceptancesPage = () => {
  const location = useLocation();
  const isAdminView = location.pathname.startsWith('/admin/tasks/pending');
  const [tasks, setTasks] = useState([]);
  const [loading, setLoading] = useState(true);
  const [selectedIds, setSelectedIds] = useState([]);
  const [bulkDeleting, setBulkDeleting] = useState(false);
  const [selectedTask, setSelectedTask] = useState(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const { toast } = useToast();

  const loadTasks = async () => {
    setLoading(true);
    const res = isAdminView
      ? await getAllPendingAcceptances()
      : await getMyTasks('Pending', 'All');
    if (res.success) {
      setTasks(res.data);
    } else {
      toast({ title: 'Error loading pending tasks', description: res.error, variant: 'destructive' });
    }
    setLoading(false);
  };

  useEffect(() => {
    loadTasks();
  }, []);

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
    if (!window.confirm(`Decline ${ids.length} selected task assignment(s)?`)) return;

    setBulkDeleting(true);
    const res = await bulkDeclineTaskAssignments(ids);
    if (res.success) {
      toast({ title: 'Tasks declined', description: `${res.count} assignment(s) removed from pending.` });
      setSelectedIds([]);
      loadTasks();
    } else {
      toast({ title: 'Delete failed', description: res.error, variant: 'destructive' });
    }
    setBulkDeleting(false);
  };

  const handleAccept = async (assignmentId) => {
    const res = await acceptTaskAssignment(assignmentId);
    if (res.success) {
      toast({ title: 'Task accepted!', description: 'You can now update its progress in My Tasks.' });
      loadTasks();
    } else {
      toast({ title: 'Error accepting task', description: res.error, variant: 'destructive' });
    }
  };

  const handleDecline = async (assignmentId) => {
    if (!window.confirm('Are you sure you want to decline this task?')) return;

    const res = await declineTaskAssignment(assignmentId);
    if (res.success) {
      toast({ title: 'Task declined.' });
      loadTasks();
    } else {
      toast({ title: 'Error declining task', description: res.error, variant: 'destructive' });
    }
  };

  return (
    <div className="max-w-7xl mx-auto space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-[#003D82]">Pending Acceptances</h1>
        <p className="text-gray-500">
          {isAdminView
            ? 'Assignments waiting for assignees to accept. New tasks appear here as soon as they are created.'
            : 'Review tasks assigned to you. Accept to start working or decline if unavailable.'}
        </p>
      </div>

      {!loading && tasks.length > 0 && !isAdminView && (
        <TaskBulkActionsBar
          totalCount={tasks.length}
          selectedIds={selectedIds}
          onSelectAll={handleSelectAll}
          onClearSelection={() => setSelectedIds([])}
          onDeleteSelected={() => handleBulkDelete()}
          deleting={bulkDeleting}
          deleteLabel="Decline Selected"
        />
      )}

      {loading ? (
        <div className="flex justify-center py-12">
          <Loader2 className="w-8 h-8 animate-spin text-[#003D82]" />
        </div>
      ) : tasks.length === 0 ? (
        <div className="text-center py-16 bg-white rounded-lg border border-dashed shadow-sm">
          <Check className="w-12 h-12 mx-auto text-green-400 mb-3" />
          <h3 className="text-lg font-medium text-gray-900">All Caught Up!</h3>
          <p className="text-gray-500">You have no pending task assignments.</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {tasks.map((task) => {
            const isOverdue = new Date(task.deadline) < new Date();

            return (
              <Card key={task.assignment_id} className="flex flex-col border-t-4 border-t-gray-300">
                <CardHeader className="pb-2">
                  <div className="flex justify-between items-start mb-2 gap-2">
                    <div className="flex items-start gap-2 flex-1 min-w-0">
                      <Checkbox
                        checked={selectedIds.includes(task.assignment_id)}
                        onCheckedChange={() => toggleSelection(task.assignment_id)}
                        className="mt-1"
                        aria-label={`Select ${task.title}`}
                      />
                      <div className="flex gap-2 flex-wrap">
                        <Badge className={getPriorityColor(task.priority)}>{task.priority}</Badge>
                        {task.task_categories && (
                          <Badge
                            variant="outline"
                            style={{
                              borderColor: task.task_categories.color,
                              color: task.task_categories.color,
                              backgroundColor: `${task.task_categories.color}15`,
                            }}
                          >
                            {task.task_categories.name}
                          </Badge>
                        )}
                      </div>
                    </div>
                    <div className="flex items-center gap-1 shrink-0">
                      {!isAdminView && (
                        <>
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 text-[#003D82]"
                            onClick={() => openTask(task)}
                            title="View and edit task"
                          >
                            <Pencil className="w-4 h-4" />
                          </Button>
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 text-red-500 hover:text-red-700"
                            onClick={() => handleBulkDelete([task.assignment_id])}
                            title="Decline task"
                          >
                            <Trash2 className="w-4 h-4" />
                          </Button>
                        </>
                      )}
                      <Badge variant="outline" className="bg-gray-100 text-gray-700 border-gray-200 flex items-center">
                        <Clock className="w-3 h-3 mr-1" /> Pending
                      </Badge>
                    </div>
                  </div>
                  <h3 className="font-bold text-lg text-gray-900">{task.title}</h3>
                  {isAdminView && task.assignee_name && (
                    <div className="flex items-center text-xs text-[#003D82] font-medium mt-1">
                      <User className="w-3 h-3 mr-1" />
                      Assignee: {task.assignee_name}
                      {task.assignee_phone && <> · {task.assignee_phone}</>}
                    </div>
                  )}
                  {!isAdminView && task.created_by_profile && (
                    <div className="flex items-center text-xs text-gray-500 mt-1">
                      <User className="w-3 h-3 mr-1" />
                      Assigned by {task.created_by_profile.full_name}
                    </div>
                  )}
                </CardHeader>
                <CardContent className="flex-1 pb-4">
                  <p className="text-sm text-gray-600 mb-4">{task.description}</p>

                  <div className="mt-auto pt-4 border-t border-gray-100">
                    {task.deadline && (
                      <div className={`flex items-center text-sm p-2 rounded-md ${isOverdue ? 'bg-red-50 text-red-700' : 'bg-gray-50 text-gray-600'}`}>
                        {isOverdue ? <AlertCircle className="w-4 h-4 mr-2" /> : <Calendar className="w-4 h-4 mr-2" />}
                        <span className="font-medium">
                          Due: {format(new Date(task.deadline), 'MMM dd, yyyy')} {isOverdue && '(Overdue)'}
                        </span>
                      </div>
                    )}
                  </div>
                </CardContent>
                <CardFooter className="bg-gray-50/50 border-t p-3 flex gap-3">
                  {!isAdminView && (
                    <>
                      <Button
                        variant="outline"
                        className="flex-1 border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700"
                        onClick={() => handleDecline(task.assignment_id)}
                      >
                        <X className="w-4 h-4 mr-2" /> Decline
                      </Button>
                      <Button
                        className="flex-1 bg-[#003D82] hover:bg-[#002a5a] text-white"
                        onClick={() => handleAccept(task.assignment_id)}
                      >
                        <Check className="w-4 h-4 mr-2" /> Accept
                      </Button>
                    </>
                  )}
                  {isAdminView && (
                    <p className="text-xs text-gray-500 w-full text-center py-1">Waiting for assignee to accept via WhatsApp link</p>
                  )}
                </CardFooter>
              </Card>
            );
          })}
        </div>
      )}

      <TaskDetailsModal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        task={selectedTask}
        onTaskUpdated={loadTasks}
      />
    </div>
  );
};

export default PendingAcceptancesPage;
