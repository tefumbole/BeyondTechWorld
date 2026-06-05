import React, { useState, useEffect } from 'react';
import { useToast } from '@/components/ui/use-toast';
import { getTasks, getTaskStats, checkAndUpdateOverdueTasks } from '@/services/taskService';
import { processScheduledTaskNotifications } from '@/services/taskNotificationService';
import TaskDashboardCard from '@/components/admin/TaskDashboardCard';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Loader2, ListTodo, AlertCircle, Clock, CheckCircle, RefreshCcw, X } from 'lucide-react';
import { Link } from 'react-router-dom';

const TaskDashboardPage = () => {
  const [tasks, setTasks] = useState([]);
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);
  const [filters, setFilters] = useState({ status: 'All', priority: 'All' });
  const { toast } = useToast();

  useEffect(() => {
    initDashboard();
  }, []);

  useEffect(() => {
    if (import.meta.env.VITE_DATA_BACKEND !== 'mysql') return undefined;
    processScheduledTaskNotifications().catch(() => {});
    const interval = setInterval(() => {
      processScheduledTaskNotifications().catch(() => {});
    }, 60000);
    return () => clearInterval(interval);
  }, []);

  useEffect(() => {
    loadTasks();
  }, [filters]);

  const initDashboard = async () => {
    setLoading(true);
    await checkAndUpdateOverdueTasks();
    await Promise.all([loadStats(), loadTasks()]);
    setLoading(false);
  };

  const loadStats = async () => {
    const res = await getTaskStats();
    if (res.success) setStats(res.data);
  };

  const loadTasks = async () => {
    const res = await getTasks(filters);
    if (res.success) setTasks(res.data);
    else toast({ title: "Error loading tasks", description: res.error, variant: "destructive" });
  };

  const clearFilters = () => {
    setFilters({ status: 'All', priority: 'All' });
  };

  const StatCard = ({ title, value, icon: Icon, colorClass }) => (
    <Card>
      <CardContent className="p-6 flex items-center justify-between">
        <div>
          <p className="text-sm font-medium text-gray-500">{title}</p>
          <h3 className="text-3xl font-bold mt-1 text-gray-900">{value || 0}</h3>
        </div>
        <div className={`p-3 rounded-full ${colorClass}`}>
          <Icon className="w-6 h-6" />
        </div>
      </CardContent>
    </Card>
  );

  return (
    <div className="max-w-7xl mx-auto space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold text-[#003D82]">Task Dashboard</h1>
          <p className="text-gray-500">Overview of all team assignments and progress.</p>
        </div>
        <div className="flex gap-2">
            <Button variant="outline" onClick={initDashboard} disabled={loading}><RefreshCcw className={`w-4 h-4 mr-2 ${loading ? 'animate-spin' : ''}`} /> Refresh</Button>
            <Link to="/admin/tasks/create">
                <Button className="bg-[#003D82]"><ListTodo className="w-4 h-4 mr-2" /> New Task</Button>
            </Link>
        </div>
      </div>

      {stats && (
        <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
          <StatCard title="Total Tasks" value={stats.total} icon={ListTodo} colorClass="bg-blue-100 text-blue-600" />
          <StatCard title="Pending" value={stats.pending} icon={Clock} colorClass="bg-gray-100 text-gray-600" />
          <StatCard title="In Progress" value={stats.inProgress} icon={RefreshCcw} colorClass="bg-yellow-100 text-yellow-600" />
          <StatCard title="Completed" value={stats.completed} icon={CheckCircle} colorClass="bg-green-100 text-green-600" />
          <StatCard title="Overdue" value={stats.overdue} icon={AlertCircle} colorClass="bg-red-100 text-red-600" />
        </div>
      )}

      <div className="bg-white p-4 rounded-lg shadow-sm border flex flex-wrap items-center gap-4">
        <span className="text-sm font-medium text-gray-700">Filters:</span>
        <Select value={filters.status} onValueChange={v => setFilters(prev => ({...prev, status: v}))}>
          <SelectTrigger className="w-[150px] bg-white text-gray-900">
            <SelectValue placeholder="Status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="All">All Statuses</SelectItem>
            <SelectItem value="Pending">Pending</SelectItem>
            <SelectItem value="In Progress">In Progress</SelectItem>
            <SelectItem value="Completed">Completed</SelectItem>
            <SelectItem value="Overdue">Overdue</SelectItem>
          </SelectContent>
        </Select>

        <Select value={filters.priority} onValueChange={v => setFilters(prev => ({...prev, priority: v}))}>
          <SelectTrigger className="w-[150px] bg-white text-gray-900">
            <SelectValue placeholder="Priority" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="All">All Priorities</SelectItem>
            <SelectItem value="Low">Low</SelectItem>
            <SelectItem value="Medium">Medium</SelectItem>
            <SelectItem value="High">High</SelectItem>
            <SelectItem value="Critical">Critical</SelectItem>
          </SelectContent>
        </Select>

        {(filters.status !== 'All' || filters.priority !== 'All') && (
            <Button variant="ghost" size="sm" onClick={clearFilters} className="text-gray-500">
                <X className="w-4 h-4 mr-1" /> Clear
            </Button>
        )}
      </div>

      {loading && !tasks.length ? (
        <div className="flex justify-center py-20">
          <Loader2 className="w-10 h-10 animate-spin text-[#003D82]" />
        </div>
      ) : tasks.length === 0 ? (
        <div className="text-center py-20 bg-white rounded-lg border border-dashed">
          <ListTodo className="w-12 h-12 mx-auto text-gray-300 mb-3" />
          <p className="text-gray-500 text-lg">No tasks found matching criteria.</p>
        </div>
      ) : (
        <div className="space-y-4">
          {tasks.map(task => (
            <TaskDashboardCard key={task.id} task={task} />
          ))}
        </div>
      )}
    </div>
  );
};

export default TaskDashboardPage;