import React, { useState, useEffect } from 'react';
import { useToast } from '@/components/ui/use-toast';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import QueueMonitor from '@/components/admin/QueueMonitor';
import { supabase } from '@/lib/customSupabaseClient';
import { hydrateQueueJobs } from '@/services/queueWorkerService';
import { format } from 'date-fns';
import { Loader2, Mail, MessageSquare, CheckCircle, AlertCircle, Clock, RefreshCw } from 'lucide-react';

const QueueListingPage = () => {
  const { toast } = useToast();
  const [jobs, setJobs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('all');

  const fetchJobs = async () => {
    setLoading(true);
    try {
      let query = supabase
        .from('message_queue')
        .select('id, status, channel, attempts, created_at, last_error, run_after, message_id, message_recipient_id')
        .order('created_at', { ascending: false })
        .limit(100);

      if (filter !== 'all') {
        query = query.eq('status', filter);
      }

      const { data, error } = await query;
      if (error) throw error;
      setJobs(await hydrateQueueJobs(data || []));
    } catch (error) {
      toast({
        title: "Error fetching queue list",
        description: error.message,
        variant: "destructive"
      });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchJobs();
    // Auto refresh listing every 10 seconds
    const interval = setInterval(fetchJobs, 10000);
    return () => clearInterval(interval);
  }, [filter]);

  const StatusIcon = ({ status }) => {
    switch(status) {
      case 'completed': return <CheckCircle className="w-4 h-4 text-green-500" />;
      case 'failed': return <AlertCircle className="w-4 h-4 text-red-500" />;
      case 'processing': return <RefreshCw className="w-4 h-4 text-yellow-500 animate-spin" />;
      default: return <Clock className="w-4 h-4 text-blue-500" />;
    }
  };

  const getStatusBadge = (status) => {
    switch(status) {
      case 'completed': return <Badge variant="outline" className="bg-green-50 text-green-700 border-green-200">Completed</Badge>;
      case 'failed': return <Badge variant="outline" className="bg-red-50 text-red-700 border-red-200">Failed</Badge>;
      case 'processing': return <Badge variant="outline" className="bg-yellow-50 text-yellow-700 border-yellow-200">Processing</Badge>;
      default: return <Badge variant="outline" className="bg-blue-50 text-blue-700 border-blue-200">Pending</Badge>;
    }
  };

  const filters = [
    { label: 'All Jobs', value: 'all' },
    { label: 'Pending', value: 'pending' },
    { label: 'Processing', value: 'processing' },
    { label: 'Completed', value: 'completed' },
    { label: 'Failed', value: 'failed' }
  ];

  return (
    <div className="max-w-7xl mx-auto space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold text-[#003D82]">Message Queue</h1>
          <p className="text-gray-500">Monitor and manage automated background message deliveries.</p>
        </div>
      </div>

      <QueueMonitor />

      <Card>
        <CardHeader className="pb-3 border-b flex flex-row items-center justify-between">
          <CardTitle>Queue Jobs</CardTitle>
          <div className="flex gap-2">
            {filters.map(f => (
              <Button 
                key={f.value}
                variant={filter === f.value ? "default" : "outline"}
                size="sm"
                onClick={() => setFilter(f.value)}
                className={filter === f.value ? "bg-[#003D82] text-white" : ""}
              >
                {f.label}
              </Button>
            ))}
          </div>
        </CardHeader>
        <CardContent className="p-0">
          {loading ? (
            <div className="flex justify-center items-center py-12">
              <Loader2 className="w-8 h-8 animate-spin text-[#003D82]" />
            </div>
          ) : jobs.length === 0 ? (
            <div className="text-center py-12 text-gray-500">
              <CheckCircle className="w-12 h-12 mx-auto text-gray-300 mb-3" />
              <p>No jobs found for the selected filter.</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <Table>
                <TableHeader className="bg-gray-50">
                  <TableRow>
                    <TableHead>Status</TableHead>
                    <TableHead>Channel</TableHead>
                    <TableHead>Recipient</TableHead>
                    <TableHead>Subject</TableHead>
                    <TableHead>Created</TableHead>
                    <TableHead>Attempts</TableHead>
                    <TableHead>Details</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {jobs.map((job) => (
                    <TableRow key={job.id} className="hover:bg-gray-50/50">
                      <TableCell>
                        <div className="flex items-center gap-2">
                          <StatusIcon status={job.status} />
                          {getStatusBadge(job.status)}
                        </div>
                      </TableCell>
                      <TableCell>
                        <div className="flex items-center gap-2">
                          {job.channel === 'email' ? <Mail className="w-4 h-4 text-blue-600" /> : <MessageSquare className="w-4 h-4 text-green-600" />}
                          <span className="capitalize text-sm font-medium">{job.channel}</span>
                        </div>
                      </TableCell>
                      <TableCell>
                        <div className="text-sm">
                          <p className="font-medium text-gray-900">{job.message_recipients?.recipient_name || 'Unknown'}</p>
                          <p className="text-xs text-gray-500">
                            {job.channel === 'email' ? job.message_recipients?.recipient_email : job.message_recipients?.recipient_phone}
                          </p>
                        </div>
                      </TableCell>
                      <TableCell>
                        <span className="text-sm truncate max-w-[200px] inline-block">
                          {job.messages?.subject || 'No Subject'}
                        </span>
                      </TableCell>
                      <TableCell className="text-sm text-gray-500 whitespace-nowrap">
                        {format(new Date(job.created_at), 'MMM dd, HH:mm')}
                      </TableCell>
                      <TableCell className="text-center text-sm font-medium text-gray-700">
                        {job.attempts || 0}
                      </TableCell>
                      <TableCell className="text-xs max-w-[250px] truncate text-red-500">
                        {job.last_error || '-'}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
};

export default QueueListingPage;