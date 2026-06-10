import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useToast } from '@/components/ui/use-toast';
import { getScheduledTasks } from '@/services/taskService';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Loader2, Calendar, Clock, Plus, Send, Users } from 'lucide-react';
import { format } from 'date-fns';
import { getPriorityColor, getStatusColor } from '@/components/admin/TaskDashboardCard';

const ScheduledTasksPage = () => {
  const [tasks, setTasks] = useState([]);
  const [loading, setLoading] = useState(true);
  const { toast } = useToast();

  const loadTasks = async () => {
    setLoading(true);
    const res = await getScheduledTasks();
    if (res.success) setTasks(res.data);
    else toast({ title: 'Error', description: res.error, variant: 'destructive' });
    setLoading(false);
  };

  useEffect(() => {
    loadTasks();
  }, []);

  return (
    <div className="max-w-7xl mx-auto space-y-6">
      <div className="flex flex-col sm:flex-row justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold text-[#003D82]">Scheduled Tasks</h1>
          <p className="text-gray-500">Tasks waiting to send WhatsApp notifications at the scheduled times.</p>
        </div>
        <Link to="/admin/tasks/create">
          <Button className="bg-[#003D82]"><Plus className="w-4 h-4 mr-2" /> Create Task</Button>
        </Link>
      </div>

      {loading ? (
        <div className="flex justify-center py-16"><Loader2 className="w-8 h-8 animate-spin text-[#003D82]" /></div>
      ) : tasks.length === 0 ? (
        <Card>
          <CardContent className="py-16 text-center text-gray-500">
            <Clock className="w-10 h-10 mx-auto mb-3 opacity-40" />
            <p>No scheduled tasks. Use <strong>Schedule</strong> or <strong>Multi Schedule</strong> when creating tasks.</p>
          </CardContent>
        </Card>
      ) : (
        <div className="space-y-4">
          {tasks.map((task) => (
            <Card key={task.id} className="overflow-hidden" style={{ borderLeftWidth: 4, borderLeftColor: task.color || '#003D82' }}>
              <CardHeader className="pb-2">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <h3 className="text-lg font-bold text-gray-900">{task.title}</h3>
                    <p className="text-sm text-gray-500 mt-1 line-clamp-2">{task.description}</p>
                  </div>
                  <div className="flex gap-2 flex-wrap">
                    <Badge className={getPriorityColor(task.priority)}>{task.priority}</Badge>
                    <Badge className={getStatusColor(task.status || 'Scheduled')}>{task.status || 'Scheduled'}</Badge>
                  </div>
                </div>
              </CardHeader>
              <CardContent className="space-y-3">
                <div className="flex flex-wrap gap-4 text-sm text-gray-600">
                  {task.deadline && (
                    <span className="flex items-center gap-1"><Calendar className="w-4 h-4" /> Due {format(new Date(task.deadline), 'MMM dd, yyyy')}{task.deadline_time ? ` ${String(task.deadline_time).slice(0, 5)}` : ''}</span>
                  )}
                  <span className="flex items-center gap-1"><Users className="w-4 h-4" /> {task.task_assignments?.length || 0} assignee(s)</span>
                </div>

                {task.pending_notifications?.length > 0 ? (
                  <div className="rounded-lg bg-amber-50 border border-amber-100 p-3">
                    <p className="text-xs font-semibold text-amber-900 mb-2 flex items-center gap-1"><Send className="w-3.5 h-3.5" /> Pending sends</p>
                    <ul className="space-y-1">
                      {task.pending_notifications.map((n) => (
                        <li key={n.id} className="text-sm text-amber-800 flex items-center gap-2">
                          <Clock className="w-3.5 h-3.5 shrink-0" />
                          {n.scheduled_at ? format(new Date(n.scheduled_at), 'MMM dd, yyyy · HH:mm') : '—'}
                          <Badge variant="outline" className="text-xs capitalize">{n.status}</Badge>
                        </li>
                      ))}
                    </ul>
                  </div>
                ) : task.schedules_json ? (
                  <div className="rounded-lg bg-gray-50 border p-3 text-sm text-gray-700">
                    <p className="text-xs font-semibold text-gray-500 mb-1">Schedule times</p>
                    {(() => {
                      try {
                        const times = typeof task.schedules_json === 'string'
                          ? JSON.parse(task.schedules_json)
                          : task.schedules_json;
                        return (Array.isArray(times) ? times : []).map((t, i) => (
                          <div key={i} className="flex items-center gap-2"><Clock className="w-3.5 h-3.5" /> {format(new Date(t), 'MMM dd, yyyy · HH:mm')}</div>
                        ));
                      } catch {
                        return <span className="text-xs text-gray-400">Schedule data unavailable</span>;
                      }
                    })()}
                  </div>
                ) : null}
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
};

export default ScheduledTasksPage;
